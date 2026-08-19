<?php
declare(strict_types=1);

/**
 * Auth.php — Login / Role / CSRF helper (Bootstrap + AuthKernel এর সাথে ব্যবহার)
 *
 * ⚠ AuthKernel::enforce() ইতিমধ্যে idle-timeout + single-session + block করে।
 *    এই ক্লাস শুধু role check ও সুবিধাজনক helper দেয়।
 *
 * ব্যবহার:
 *   Auth::requireLogin();
 *   Auth::requireAdmin();
 *   Auth::requireRole(['admin', 'manager']);
 *   Auth::check();          // bool
 *   Auth::isAdmin();        // bool
 *   Auth::userId();
 *   Auth::role();
 *   Auth::csrf();           // token string
 *   Auth::verifyCsrf($token);
 */
final class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function userId(): int
    {
        return isset($_SESSION['user_id']) && is_scalar($_SESSION['user_id'])
            ? (int) $_SESSION['user_id']
            : 0;
    }

    public static function username(): string
    {
        return isset($_SESSION['username']) && is_string($_SESSION['username'])
            ? $_SESSION['username']
            : '';
    }

    public static function role(): string
    {
        $role = $_SESSION['role'] ?? 'viewer';
        return is_string($role) ? strtolower(trim($role)) : 'viewer';
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function is(string|array $roles): bool
    {
        $roles = array_map('strtolower', (array) $roles);
        return in_array(self::role(), $roles, true);
    }

    /* ── Guards ─────────────────────────────────────────────── */

    /**
     * লগইন না থাকলে redirect বা JSON error
     */
    public static function requireLogin(bool $isAjax = false): void
    {
        if (!self::check()) {
            if ($isAjax) {
                Response::sessionExpired();
            }
            header('Location: /index.php?status=login_required', true, 303);
            exit;
        }
    }

    /**
     * নির্দিষ্ট role না থাকলে 403 / redirect
     */
    public static function requireRole(string|array $roles, bool $isAjax = false): void
    {
        self::requireLogin($isAjax);

        if (!self::is($roles)) {
            if ($isAjax) {
                Response::forbidden('এই পেজ ব্যবহারের অনুমতি নেই!');
            }
            http_response_code(403);
            echo '<h2 style="font-family:sans-serif;text-align:center;margin-top:3rem;color:#dc2626;">অনুমতি নেই</h2>';
            exit;
        }
    }

    public static function requireAdmin(bool $isAjax = false): void
    {
        self::requireRole('admin', $isAjax);
    }

    /* ── CSRF (Csrf.php এর উপর ভিত্তি করে) ──────────────────── */

    public static function csrf(): string
    {
        return Csrf::token();
    }

    public static function verifyCsrf(?string $token): bool
    {
        return Csrf::verify($token);
    }

    public static function requireCsrf(?string $token = null, bool $isAjax = true): void
    {
        $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if (!Csrf::verify($token)) {
            if ($isAjax) {
                Response::error('সিকিউরিটি টোকেন মিসম্যাচ! পেজ রিলোড করুন।', 403);
            }
            http_response_code(403);
            die('CSRF verification failed. Please refresh the page.');
        }
    }
}
