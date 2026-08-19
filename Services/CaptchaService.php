<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Interfaces/CaptchaServiceInterface.php';

final class CaptchaService implements CaptchaServiceInterface {

    private const VERIFY_URL        = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const TOKEN_FIELD_NAME  = 'cf-turnstile-response';
    private const CURL_TIMEOUT      = 8;

    private string $secretKey;
    private string $errorMessage = '';
    private string $logFilePath;

    public function __construct() {
        $this->logFilePath = dirname(__DIR__) . '/Logs/error_log.txt';
        $this->secretKey   = $this->loadSecretKey();
    }

    private function loadSecretKey(): string {
        $envPath = dirname(__DIR__, 2) . '/App/.env';
        if (file_exists($envPath)) {
            $cfg = parse_ini_file($envPath);
            if (is_array($cfg) && !empty($cfg['TURNSTILE_SECRET_KEY'])) {
                return (string)$cfg['TURNSTILE_SECRET_KEY'];
            }
        }
        $env = getenv('TURNSTILE_SECRET_KEY');
        if ($env !== false && $env !== '') return (string)$env;
        return '0x4AAAAAADfIQpUFhhvvepvHo19tRvpSGn4'; // fallback dev key
    }

    public function isPostRequest(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    public function verify(): bool {
        $token = trim((string)($_POST[self::TOKEN_FIELD_NAME] ?? ''));
        if ($token === '') {
            $this->errorMessage = '❌ দয়া করে সিকিউরিটি ভেরিফিকেশন সম্পন্ন করুন!';
            return false;
        }
        if (!function_exists('curl_init')) {
            $this->errorMessage = '❌ সার্ভার কনফিগারেশন সমস্যা!';
            $this->log('cURL not loaded');
            return false;
        }
        $response = $this->callApi($token);
        if ($response === null) {
            $this->errorMessage = '❌ নেটওয়ার্ক সমস্যা! কিছুক্ষণ পর চেষ্টা করুন।';
            return false;
        }
        if (empty($response['success'])) {
            $codes = $response['error-codes'] ?? [];
            $this->errorMessage = $this->mapErrorCode($codes);
            $this->log('Verify failed: ' . implode(',', $codes));
            return false;
        }
        return true;
    }

    private function callApi(string $token): ?array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::VERIFY_URL,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'secret'   => $this->secretKey,
                'response' => $token,
                'remoteip' => $this->getClientIp(),
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::CURL_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $err !== '') { $this->log("cURL: {$err}"); return null; }
        if ($code !== 200) { $this->log("HTTP {$code}"); return null; }
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function getClientIp(): string {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $h) {
            $v = $_SERVER[$h] ?? '';
            if ($v !== '') {
                $ip = trim(explode(',', $v)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '';
    }

    private function mapErrorCode(array $codes): string {
        return match((string)($codes[0] ?? '')) {
            'missing-input-response','missing-input-secret' => '❌ সিকিউরিটি ভেরিফিকেশন সম্পন্ন করুন!',
            'invalid-input-response' => '❌ ভেরিফিকেশন কোড ভুল। পেজ রিফ্রেশ করুন।',
            'timeout-or-duplicate'   => '❌ ভেরিফিকেশনের মেয়াদ শেষ। পেজ রিফ্রেশ করুন।',
            'internal-error'         => '❌ Cloudflare সার্ভার সমস্যা। পরে চেষ্টা করুন।',
            default                  => '❌ সিকিউরিটি ভেরিফিকেশন ফেইল! আবার চেষ্টা করুন।',
        };
    }

    public function getErrorMessage(): string {
        return htmlspecialchars($this->errorMessage, ENT_QUOTES, 'UTF-8');
    }

    private function log(string $msg): void {
        $dir = dirname($this->logFilePath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @error_log('[' . date('Y-m-d H:i:s') . '] CAPTCHA: ' . $msg . PHP_EOL, 3, $this->logFilePath);
    }
}
