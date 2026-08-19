<?php
declare(strict_types=1);
if (!defined('SK_APP')) { http_response_code(403); exit('403 Forbidden'); }

/**
 * সেশন, CSRF, রোল-অনুমতি ও অ্যাকাউন্ট-ব্লক নিয়ন্ত্রণ — সব এক জায়গায়।
 */
final class Security
{
    private const IDLE_LIMIT   = 1200;          // ২০ মিনিট
    private const LOGIN_PAGE    = '/index.php';

    /** প্রতি entry ফাইলের শুরুতে একবার কল করুন */
    public static function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Strict',
                'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
            ]);
            session_start();
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Type: text/html; charset=utf-8');
        // দ্রষ্টব্য: script-src/style-src এ 'unsafe-inline' রাখা হয়েছে কারণ ভিউগুলো
        // ইনলাইন onclick হ্যান্ডলার ও ইনলাইন <script> ব্যবহার করে। foreign object/frame/base
        // এখনো ব্লকড থাকে — সম্পূর্ণ ইনলাইন-জেএস সরানো একটি আলাদা রিফ্যাক্টর হিসেবে সুপারিশ করা হলো।
        header("Content-Security-Policy: default-src 'self'; "
            . "script-src 'self' 'unsafe-inline'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data:; "
            . "object-src 'none'; "
            . "base-uri 'self'; "
            . "frame-ancestors 'none'");
    }

    public static function requireLogin(): void
    {
        if (empty($_SESSION['loggedin'])) {
            header('Location: ' . self::LOGIN_PAGE);
            exit;
        }
    }

    public static function role(): string
    {
        return (string)($_SESSION['role'] ?? 'viewer');
    }

    public static function username(): string
    {
        return (string)($_SESSION['username'] ?? self::role());
    }

    /** নির্দিষ্ট রোল না থাকলে redirect */
    public static function requireRole(array $roles, string $redirect = '/Suppliers/suppliers.php'): void
    {
        if (!in_array(self::role(), $roles, true)) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    public static function hasRole(array $roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    /* ---------------- CSRF ---------------- */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): bool
    {
        $sent = (string)($_POST['csrf_token'] ?? '');
        return $sent !== '' && hash_equals((string)($_SESSION['csrf_token'] ?? ''), $sent);
    }

    /** আউটপুট এস্কেপ শর্টকাট */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * নিষ্ক্রিয়তা-টাইমআউট + Single Active Session + অ্যাকাউন্ট ব্লক যাচাই।
     * লগইন করা থাকলে প্রতি রিকোয়েস্টে কল করুন।
     *
     * বাস্তবায়ন এখন শেয়ার্ড AuthKernel এ (Core/AuthKernel.php) — রুট
     * db_connect.php এর লাইন ১০৯–১৯৮ এর সাথে অভিন্ন আচরণ। এতে এই মডিউল
     * SessionGuard (single active session) এনফোর্সমেন্টও পায়, যা আগে
     * এখানে অনুপস্থিত ছিল।
     */
    public static function enforceSession(\PDO $conn): void
    {
        require_once SK_ROOT . '/Core/AuthKernel.php';
        \AuthKernel::enforce($conn);
    }
}
