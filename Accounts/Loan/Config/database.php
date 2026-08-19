<?php

declare(strict_types=1);

/**
 * ================================================================
 *  Database / Auth Bootstrap
 * ================================================================
 *
 *  এই ফাইলটি নিজে কোনো ডাটাবেস ক্রেডেনশিয়াল রাখে না। আপনার সাইটের
 *  আসল কানেক্টর (public_html/db_connect.php) ব্যবহার করা হয়, যেটি
 *  .env ভল্ট থেকে পাসওয়ার্ড পড়ে, সেশন শুরু করে এবং $conn তৈরি করে।
 *
 *  ⚠ কেন AuthKernel দরকার:
 *  db_connect.php এর ভিতরে চেক হয় —
 *      $current_page = basename($_SERVER['PHP_SELF']);
 *      if (!in_array($current_page, ['index.php','logout.php'])) { SessionGuard::enforce($conn); }
 *
 *  আমাদের ফ্রন্ট কন্ট্রোলারের নামও index.php, তাই ওই শর্তে এই মডিউলের
 *  জন্য SessionGuard এবং ব্লক-চেক দুটোই স্কিপ হয়ে যেত। AuthKernel সেই
 *  একই নিয়মগুলো (idle timeout, single active session, heartbeat,
 *  block check) মডিউলের জন্য absolute redirect সহ প্রয়োগ করে।
 */
final class Database
{
    /** public_html/ — অর্থাৎ Accounts/Loan থেকে দুই ধাপ উপরে। */
    private const DOC_ROOT = APP_ROOT . '/../..';

    private const CONNECTOR_FILE  = self::DOC_ROOT . '/db_connect.php';
    private const AUTH_KERNEL_FILE = self::DOC_ROOT . '/Core/AuthKernel.php';

    private static ?PDO $connection = null;
    private static bool $authEnforced = false;

    private function __construct()
    {
    }

    /**
     * পুরো রিকোয়েস্টের জন্য একটিই PDO কানেকশন ফিরিয়ে দেয়।
     * প্রথমবার ডাকলে db_connect.php লোড হয় এবং AuthKernel চালু হয়।
     */
    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        self::$connection = self::loadSiteConnection();
        self::enforceAuth();

        return self::$connection;
    }

    /**
     * সাইটের মূল db_connect.php লোড করে $conn সংগ্রহ করে।
     * ফাইলটি সেশনও শুরু করে, তাই আমরা আলাদা করে session_start() করি না।
     */
    private static function loadSiteConnection(): PDO
    {
        if (!is_file(self::CONNECTOR_FILE)) {
            Logger::error('db_connect.php not found at: ' . self::CONNECTOR_FILE);
            self::halt('ডাটাবেস কনফিগারেশন ফাইল খুঁজে পাওয়া যায়নি।');
        }

        // db_connect.php ফাইল-স্কোপে $conn তৈরি করে, তাই সেটি এখানেই ধরা পড়ে।
        require_once self::CONNECTOR_FILE;

        $pdo = null;
        if (isset($conn) && $conn instanceof PDO) {
            $pdo = $conn;
        } elseif (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            $pdo = $GLOBALS['conn'];
        }

        if (!$pdo instanceof PDO) {
            Logger::error('db_connect.php loaded but no PDO $conn was produced.');
            self::halt('ডাটাবেস সংযোগ তৈরি হয়নি।');
        }

        // db_connect.php ইতিমধ্যেই এগুলো সেট করে; নিরাপত্তার জন্য নিশ্চিত করা হচ্ছে।
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        return $pdo;
    }

    /**
     * AuthKernel::enforce() চালায় — idle timeout, single active session,
     * last_active হার্টবিট এবং অ্যাকাউন্ট ব্লক চেক।
     */
    private static function enforceAuth(): void
    {
        if (self::$authEnforced) {
            return;
        }
        self::$authEnforced = true;

        if (!is_file(self::AUTH_KERNEL_FILE)) {
            // ফাইলটি না থাকলে সিস্টেম চলবে, কিন্তু single-session ও block চেক
            // হবে না — তাই এটি লগে সতর্কতা হিসেবে রাখা হচ্ছে।
            Logger::warning('AuthKernel.php not found — single-session and block checks are inactive.');
            return;
        }

        require_once self::AUTH_KERNEL_FILE;

        if (!class_exists('AuthKernel') || !method_exists('AuthKernel', 'enforce')) {
            Logger::warning('AuthKernel class or enforce() method unavailable.');
            return;
        }

        // AJAX রিকোয়েস্টে AuthKernel রিডাইরেক্ট করলে ব্রাউজার HTML পেত এবং
        // JSON পার্স ভেঙে যেত। তাই সেশন শেষ হয়ে থাকলে আগেই JSON ফেরত দিই।
        if (self::isAjaxRequest() && self::sessionLooksExpired()) {
            Response::json([
                'status'  => 'error',
                'code'    => 'session_expired',
                'message' => 'সেশনের মেয়াদ শেষ। অনুগ্রহ করে আবার লগইন করুন।',
            ], 401);
        }

        try {
            AuthKernel::enforce(self::$connection);
        } catch (Throwable $e) {
            Logger::error('AuthKernel::enforce failed: ' . $e->getMessage());
        }
    }

    private static function isAjaxRequest(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    /** db_connect.php এর SESSION_TIMEOUT অনুযায়ী নিষ্ক্রিয়তা যাচাই। */
    private static function sessionLooksExpired(): bool
    {
        if (empty($_SESSION['loggedin'])) {
            return true;
        }

        $idleLimit = defined('SESSION_TIMEOUT') ? (int)SESSION_TIMEOUT : 1200;
        $lastSeen  = max(
            (int)($_SESSION['last_action_time'] ?? 0),
            (int)($_SESSION['last_activity'] ?? 0)
        );

        return $lastSeen > 0 && (time() - $lastSeen) >= $idleLimit;
    }

    private static function halt(string $message): never
    {
        if (function_exists('show_db_error_page')) {
            show_db_error_page($message);
        }

        http_response_code(503);
        exit($message);
    }
}
