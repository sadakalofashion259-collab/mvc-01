<?php
/**
 * Staff/Core/SessionManager.php — সেশন টাইমআউট ও সিকিউরিটি চেক
 *
 * লিগ্যাসি পেজগুলোর ইনলাইন সেশন-গার্ড লজিকের হুবহু প্রতিরূপ:
 *   - session_start + no-cache হেডার
 *   - ২০ মিনিট (১২০০ সেকেন্ড) idle টাইমআউট → session destroy + redirect
 *   - loggedin চেক
 *   - ঐচ্ছিক admin-only রোল চেক
 *
 * ⚡ AuthKernel ইন্টিগ্রেশন:
 *   DB-নির্ভর গার্ডগুলো (Single Active Session, হার্টবিট, ব্লক চেক) মূল
 *   Core/AuthKernel দিয়ে চলে। Staff/db_connect.php include করলেই
 *   AuthKernel::enforce($conn) স্বয়ংক্রিয়ভাবে কার্যকর হয়; আলাদা করে দরকার
 *   হলে SessionManager::enforceKernel($conn) ডাকা যায়।
 *
 * পেজ থেকে ব্যবহার (Modules/<X>/ ফাইলে):
 *   require_once __DIR__ . '/../../Core/SessionManager.php';
 *   SessionManager::guardPage(adminOnly: true);
 *   include __DIR__ . '/../../db_connect.php'; // → AuthKernel::enforce()
 *
 * AJAX হ্যান্ডলার থেকে:
 *   SessionManager::guardAjax();
 */

final class SessionManager
{
    public const IDLE_LIMIT = 1200; // ২০ মিনিট

    /** সেশন শুরু + no-cache হেডার */
    public static function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!headers_sent()) {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
        }
    }

    /**
     * সাধারণ HTML পেজের জন্য গার্ড (লিগ্যাসি আচরণ অপরিবর্তিত)।
     *
     * @param bool   $adminOnly       true হলে admin রোল বাধ্যতামূলক
     * @param string $loginRedirect   টাইমআউট/লগইন-ব্যর্থতায় রিডাইরেক্ট টার্গেট
     * @param string $deniedRedirect  admin না হলে রিডাইরেক্ট টার্গেট
     */
    public static function guardPage(
        bool $adminOnly = true,
        string $loginRedirect = '../../index.php',
        string $deniedRedirect = '../../index.php'
    ): void {
        self::boot();

        // ১. ২০ মিনিট idle টাইমআউট
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > self::IDLE_LIMIT)) {
            session_unset();
            session_destroy();
            echo "<script>alert('Session Expired!');window.location.href='" . $loginRedirect . "';</script>";
            exit;
        }
        $_SESSION['last_activity'] = time();

        // ২. লগইন চেক
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            echo "<script>window.location.href='" . $loginRedirect . "';</script>";
            exit;
        }

        // ৩. রোল চেক (ঐচ্ছিক)
        if ($adminOnly) {
            $role = $_SESSION['role'] ?? 'admin';
            if ($role !== 'admin' && $role !== 'Admin') {
                echo "<script>alert('Access Denied! Admin Only.');window.location.href='" . $deniedRedirect . "';</script>";
                exit;
            }
        }
    }

    /** AJAX হ্যান্ডলারের জন্য JSON গার্ড (লিগ্যাসি আচরণ অপরিবর্তিত)। */
    public static function guardAjax(): void
    {
        self::boot();

        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
    }

    /**
     * মূল Core/AuthKernel-এর ইউনিফাইড DB-গার্ড সরাসরি চালানো:
     * idle টাইমআউট, Single Active Session, হার্টবিট, ব্লক চেক।
     * (Staff/db_connect.php include করলে এটি এমনিতেই চলে —
     *  এই মেথডটি নতুন কাস্টম এন্ট্রি-পয়েন্টের জন্য।)
     */
    public static function enforceKernel(\PDO $conn): void
    {
        require_once dirname(__DIR__, 2) . '/Core/AuthKernel.php';
        \AuthKernel::enforce($conn);
    }
}
