<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/Env.php';

/**
 * SmsService — MiMSMS gateway client.
 *
 * All credentials are loaded from /home/sadakalo/App/.env — nothing is
 * hardcoded inside the web root. Expected .env keys (aliases supported):
 *
 *   SMS_API_URL           (default: https://api.mimsms.com/api/SmsSending/SMS)
 *   SMS_API_USERNAME      (alias: SMS_USERNAME, SMS_USER)
 *   SMS_API_KEY           (alias: SMS_KEY, SMS_APIKEY)
 *   SMS_SENDER_NAME       (alias: SMS_SENDER)
 *   SMS_TRANSACTION_TYPE  (alias: SMS_TYPE, default: T)
 */
final class SmsService
{
    private string $apiUrl;
    private string $apiUsername;
    private string $apiKey;
    private string $senderName;
    private string $transactionType;
    private string $logFile;

    public function __construct(?string $logFile = null)
    {
        Env::load();

        $this->apiUrl          = Env::get(['SMS_API_URL'], 'https://api.mimsms.com/api/SmsSending/SMS');
        $this->apiUsername     = Env::get(['SMS_API_USERNAME', 'SMS_USERNAME', 'SMS_USER']);
        $this->apiKey          = Env::get(['SMS_API_KEY', 'SMS_KEY', 'SMS_APIKEY']);
        $this->senderName      = Env::get(['SMS_SENDER_NAME', 'SMS_SENDER']);
        $this->transactionType = Env::get(['SMS_TRANSACTION_TYPE', 'SMS_TYPE'], 'T');
        $this->logFile         = $logFile ?? dirname(__DIR__) . '/Logs/error_log.txt';
    }

    /**
     * Normalise a Bangladeshi mobile number to the 8801XXXXXXXXX format.
     * Returns '' when the number cannot be normalised.
     */
    private function normalisePhone(string $phone): string
    {
        $mobile = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($mobile) === 11 && str_starts_with($mobile, '0')) {
            $mobile = '88' . $mobile;
        } elseif (strlen($mobile) === 10) {
            $mobile = '880' . $mobile;
        }

        return strlen($mobile) === 13 && str_starts_with($mobile, '880') ? $mobile : '';
    }

    /**
     * Send an SMS.
     *
     * @return array{success: bool, error?: string, response?: mixed}
     */
    public function send(string $phone, string $message): array
    {
        if ($this->apiUsername === '' || $this->apiKey === '') {
            $this->log('SMS credentials missing in App/.env (SMS_API_USERNAME / SMS_API_KEY).');
            return ['success' => false, 'error' => 'SMS credentials not configured'];
        }

        $mobile = $this->normalisePhone($phone);
        if ($mobile === '') {
            $this->log("Invalid phone number: {$phone}");
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        $payload = json_encode([
            'UserName'        => $this->apiUsername,
            'Apikey'          => $this->apiKey,
            'MobileNumber'    => $mobile,
            'SenderName'      => $this->senderName,
            'TransactionType' => $this->transactionType,
            'CampaignId'      => 'null',
            'Message'         => $message,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError !== '') {
            $this->log("cURL error: {$curlError} | Mobile: {$mobile}");
            return ['success' => false, 'error' => $curlError];
        }

        $decoded = is_string($response) ? json_decode($response, true) : null;
        if ($httpCode !== 200 || empty($decoded)) {
            $this->log("API error: HTTP {$httpCode} | Mobile: {$mobile}");
            return ['success' => false, 'error' => "HTTP {$httpCode}"];
        }

        return ['success' => true, 'response' => $decoded];
    }

    private function log(string $message): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        error_log('[' . date('Y-m-d H:i:s') . "] SMS_ERROR: {$message}" . PHP_EOL, 3, $this->logFile);
    }
}
