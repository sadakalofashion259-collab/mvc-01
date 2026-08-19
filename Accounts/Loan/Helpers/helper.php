<?php

declare(strict_types=1);

/**
 * Logger
 *
 * All application errors land in Logs/error_log.txt. Nothing is ever
 * echoed to the browser.
 */
final class Logger
{
    private const LOG_FILE = '/Logs/error_log.txt';

    public static function error(string $message): void
    {
        self::write('ERROR', $message);
    }

    public static function warning(string $message): void
    {
        self::write('WARNING', $message);
    }

    private static function write(string $level, string $message): void
    {
        $dir = APP_ROOT . '/Logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $entry = sprintf(
            "[%s] %s: %s | ip=%s | uri=%s\n",
            date('Y-m-d H:i:s'),
            $level,
            str_replace(["\r", "\n"], ' ', $message),
            $_SERVER['REMOTE_ADDR'] ?? 'cli',
            $_SERVER['REQUEST_URI'] ?? '-'
        );

        @error_log($entry, 3, APP_ROOT . self::LOG_FILE);
    }
}

/**
 * Security
 *
 * Single source of truth for CSRF tokens and output escaping.
 */
final class Security
{
    private const SESSION_KEY = 'loan_csrf_token';

    public static function ensureCsrfToken(): void
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            try {
                $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                Logger::error('CSRF generation fallback used: ' . $e->getMessage());
                $_SESSION[self::SESSION_KEY] = hash('sha256', uniqid((string)mt_rand(), true));
            }
        }
    }

    public static function token(): string
    {
        return (string)($_SESSION[self::SESSION_KEY] ?? '');
    }

    public static function verifyCsrf(mixed $submitted): bool
    {
        $sessionToken = self::token();
        if (!is_string($submitted) || $submitted === '' || $sessionToken === '') {
            return false;
        }
        return hash_equals($sessionToken, $submitted);
    }

    /** HTML-context escaping. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Whitelist guard for values that influence SQL structure or ENUMs. */
    public static function allow(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /** Validates a strict Y-m-d date string. */
    public static function isValidDate(string $date): bool
    {
        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}

/**
 * Request
 */
final class Request
{
    public static function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    public static function int(array $source, string $key, int $default = 0): int
    {
        return isset($source[$key]) ? (int)$source[$key] : $default;
    }

    public static function float(array $source, string $key, float $default = 0.0): float
    {
        return isset($source[$key]) ? (float)$source[$key] : $default;
    }

    public static function text(array $source, string $key, string $default = ''): string
    {
        return isset($source[$key]) ? trim((string)$source[$key]) : $default;
    }
}

/**
 * Response
 */
final class Response
{
    public static function json(array $payload, int $httpCode = 200): never
    {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Str
 *
 * mbstring এক্সটেনশন সব সার্ভারে থাকে না। বাংলা লেখার দৈর্ঘ্য ও কাটাকাটি
 * নিরাপদে করার জন্য এখানে ফলব্যাক রাখা হয়েছে — mbstring থাকলে সেটিই
 * ব্যবহার হয়, না থাকলে UTF-8 নিরাপদ বিকল্প চলে।
 */
final class Str
{
    private static ?bool $hasMb = null;

    private static function hasMb(): bool
    {
        return self::$hasMb ??= function_exists('mb_strlen');
    }

    /** অক্ষরের সংখ্যা (বাইট নয়)। */
    public static function length(string $value): int
    {
        if (self::hasMb()) {
            return mb_strlen($value, 'UTF-8');
        }

        // UTF-8 কন্টিনিউয়েশন বাইট বাদ দিয়ে গণনা
        return (int)strlen((string)preg_replace('/[\x80-\xBF]/', '', $value));
    }

    /** অক্ষর ভেঙে না ফেলে নির্দিষ্ট দৈর্ঘ্যে কাটে। */
    public static function cut(string $value, int $limit): string
    {
        if (self::hasMb()) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }

        if (self::length($value) <= $limit) {
            return $value;
        }

        preg_match('/^(?:.){0,' . max(0, $limit) . '}/us', $value, $m);
        return $m[0] ?? substr($value, 0, $limit);
    }
}

/**
 * Money
 */
final class Money
{
    public static function format(float|string|null $amount, int $decimals = 2): string
    {
        return '৳' . number_format((float)$amount, $decimals);
    }

    public static function round(float $amount): float
    {
        return round($amount, 2);
    }
}

/**
 * DueDate
 *
 * Produces the small status badge shown beside each borrower, matching
 * the DPS panel style: আজ / N দিন / বকেয়া.
 */
final class DueDate
{
    /**
     * ব্যাজ তৈরি করে। $status হিসেবে LoanStatus enum বা সাধারণ
     * স্ট্রিং — দুটোই দেওয়া যায়, তাই মডেল ও কন্ট্রোলার দুই জায়গা
     * থেকেই নিরাপদে ডাকা যায়।
     *
     * @return array{label:string, css:string, raw:string, days:?int}
     */
    public static function badge(?string $dueDate, LoanStatus|string|null $status): array
    {
        $statusValue = $status instanceof LoanStatus ? $status->value : (string)$status;
        $empty = ['label' => '', 'css' => '', 'raw' => (string)$dueDate, 'days' => null];

        if ($statusValue !== LoanStatus::Active->value || empty($dueDate)) {
            return $empty;
        }

        try {
            $today = new DateTimeImmutable(date('Y-m-d'));
            $due   = new DateTimeImmutable($dueDate);
        } catch (Throwable $e) {
            return $empty;
        }

        $days = (int)$today->diff($due)->format('%r%a');

        if ($days < 0) {
            return ['label' => 'বকেয়া', 'css' => 'due-overdue', 'raw' => $dueDate, 'days' => $days];
        }
        if ($days === 0) {
            return ['label' => 'আজ', 'css' => 'due-today', 'raw' => $dueDate, 'days' => 0];
        }
        if ($days <= 3) {
            return ['label' => $days . ' দিন', 'css' => 'due-soon', 'raw' => $dueDate, 'days' => $days];
        }

        return ['label' => $due->format('d M'), 'css' => 'due-normal', 'raw' => $dueDate, 'days' => $days];
    }

    /**
     * কিস্তির তারিখ এক ধাপ এগিয়ে দেয়।
     * $frequency হিসেবে LoanFrequency enum বা স্ট্রিং — দুটোই চলে।
     */
    public static function advance(string $dueDate, LoanFrequency|string $frequency): ?string
    {
        if (!Security::isValidDate($dueDate)) {
            return null;
        }

        $freq = $frequency instanceof LoanFrequency
            ? $frequency
            : LoanFrequency::fromSafe((string)$frequency);

        try {
            return (new DateTimeImmutable($dueDate))
                ->add(new DateInterval($freq->interval()))
                ->format('Y-m-d');
        } catch (Throwable $e) {
            Logger::warning('Due date advance failed: ' . $e->getMessage());
            return null;
        }
    }
}
