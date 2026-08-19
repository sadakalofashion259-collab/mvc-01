<?php
/**
 * core/SecurityHelper.php
 * ─────────────────────────────────────────
 * মূল dps_dashboard.php থেকে অপরিবর্তিত (নিরাপদ) লজিক এখানে সরানো হয়েছে।
 */
declare(strict_types=1);

final class DpsUserException extends RuntimeException {}

final class SecurityHelper
{
    public static function safeOut(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function verifyCsrf(string $token, string $session): bool
    {
        return hash_equals($session, $token);
    }

    public static function jsonError(string $msg, int $code = 400): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function jsonSuccess(array $data = []): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['status' => 'success'], $data), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function logError(string $context, Throwable $e): void
    {
        $logDir = DPS_LOG_DIR;
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
        $line = sprintf(
            "[%s] %s :: %s @ %s:%d%s",
            date('Y-m-d H:i:s'),
            $context,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL
        );
        @file_put_contents($logDir . '/error_log.txt', $line, FILE_APPEND | LOCK_EX);
    }

    public static function validateDate(string $date, string $format = 'Y-m-d'): ?string
    {
        if (empty(trim($date))) {
            return null;
        }
        $d = DateTime::createFromFormat($format, trim($date));
        if ($d === false || $d->format($format) !== trim($date)) {
            return null;
        }
        return $d->format($format);
    }

    /** বর্তমান লগইন করা ইউজার সেশন যাচাই — না থাকলে absolute পাথে redirect করে বন্ধ */
    public static function requireLogin(string $loginRedirect = '/index.php'): void
    {
        if (empty($_SESSION['loggedin'])) {
            header('Location: ' . $loginRedirect, true, 303);
            exit;
        }
    }

    /** সেশনে CSRF token না থাকলে তৈরি করে, ফেরত দেয় */
    public static function issueCsrfToken(): string
    {
        try {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        } catch (Exception $e) {
            self::logError('CSRF_INIT', $e);
            http_response_code(500);
            exit('Security initialization failed. Please contact the administrator.');
        }
        return $_SESSION['csrf_token'];
    }
}
