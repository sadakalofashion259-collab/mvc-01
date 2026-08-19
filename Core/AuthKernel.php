<?php
declare(strict_types=1);

/**
 * AuthKernel — মডিউলগুলোর জন্য ইউনিফাইড সেশন/অথ এনফোর্সমেন্ট।
 * ────────────────────────────────────────────────────────────────────────
 * db_connect.php (লাইন ১০৯–১৯৮) এর আচরণ হুবহু রেপ্লিকেট করে, কিন্তু
 * db_connect.php নিজে অপরিবর্তিত থাকে — লিগ্যাসি পেজগুলো আগের মতোই চলে।
 *
 * enforce() যা করে (ক্রমানুসারে):
 *   1) ২০-মিনিট নিষ্ক্রিয়তা টাইমআউট — last_action_time (রুট) এবং
 *      last_activity (মডিউল) দুটো সেশন কী-ই পড়ে ও লেখে, ফলে রুট পেজ আর
 *      মডিউল পেজের টাইমার একসাথে সিঙ্ক থাকে।
 *   2) Single Active Session — users.active_session_token বনাম সেশন টোকেন।
 *      SessionGuard::enforce() এর সমতুল্য; পার্থক্য শুধু রিডাইরেক্ট absolute
 *      /index.php এ যায় (মডিউল সাব-ফোল্ডার থেকে relative index.php ৪০৪ হতো)।
 *   3) users.last_active = NOW() হার্টবিট (অনলাইন-ইউজার লিস্টের জন্য)।
 *   4) অ্যাকাউন্ট ব্লক চেক — status='blocked' বা block_start/block_end
 *      উইন্ডো → নন-অ্যাডমিনদের জন্য 403 ব্লক পেজ।
 *
 * ব্যবহার (মডিউল entry/bootstrap থেকে, session start ও login check এর পরে):
 *   require_once $root . '/Core/AuthKernel.php';
 *   AuthKernel::enforce($conn);
 */

// SessionGuard::SESSION_KEY এর একক উৎস — টোকেন কী-নাম যেন কখনো আলাদা না হয়।
require_once dirname(__DIR__) . '/Services/SessionGuard.php';

final class AuthKernel
{
    /** db_connect.php এর SESSION_TIMEOUT এর সাথে অভিন্ন রাখতে হবে */
    private const IDLE_LIMIT = 1200; // ২০ মিনিট

    /** সব রিডাইরেক্ট absolute — যেকোনো সাব-ফোল্ডার থেকে নিরাপদ */
    private const LOGIN_PAGE  = '/index.php';
    private const LOGOUT_PAGE = '/logout.php';

    public static function enforce(\PDO $conn): void
    {
        self::enforceIdleTimeout($conn);
        self::enforceSingleSession($conn);
        self::heartbeatAndBlockCheck($conn);
    }

    /* ── 1) নিষ্ক্রিয়তা টাইমআউট (db_connect.php লাইন ১০৯–১৩৩) ── */
    private static function enforceIdleTimeout(\PDO $conn): void
    {
        // রুট (last_action_time) ও মডিউল (last_activity) — যেটি নতুন সেটিই ধরা হয়,
        // যাতে ব্যবহারকারী যেকোনো অংশে সক্রিয় থাকলে কোথাও ভুল লগআউট না হয়।
        $lastSeen = max(
            (int)($_SESSION['last_action_time'] ?? 0),
            (int)($_SESSION['last_activity']    ?? 0)
        );

        if (!empty($_SESSION['loggedin']) && $lastSeen > 0
            && (time() - $lastSeen) >= self::IDLE_LIMIT) {

            $username = $_SESSION['username'] ?? null;
            if (is_string($username) && $username !== '') {
                try {
                    $conn->prepare("UPDATE users SET last_active = '2000-01-01 00:00:00' WHERE username = ?")
                         ->execute([$username]);
                } catch (\PDOException $e) {
                    error_log('AuthKernel idle-logout query failed: ' . $e->getMessage());
                }
            }

            session_unset();
            session_destroy();
            header('Location: ' . self::LOGIN_PAGE . '?status=timeout', true, 303);
            exit;
        }

        // দুটো কী-ই আপডেট — রুট আর মডিউল টাইমার সিঙ্কে থাকে।
        $now = time();
        $_SESSION['last_action_time'] = $now;
        $_SESSION['last_activity']    = $now;
    }

    /* ── 2) Single Active Session (SessionGuard::enforce এর সমতুল্য) ── */
    private static function enforceSingleSession(\PDO $conn): void
    {
        if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
            return;
        }

        $sessionToken = (string)($_SESSION[\SessionGuard::SESSION_KEY] ?? '');

        try {
            $stmt = $conn->prepare('SELECT active_session_token FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$_SESSION['user_id']]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // মাইগ্রেশন না চললে কলাম নেই — SessionGuard এর মতোই নীরবে ফিরে যায়।
            return;
        }

        $dbToken = is_array($row) ? (string)($row['active_session_token'] ?? '') : '';

        if ($dbToken !== ''
            && ($sessionToken === '' || !hash_equals($dbToken, $sessionToken))) {
            self::forceLogout('session_replaced');
        }
    }

    /* ── 3+4) হার্টবিট ও ব্লক চেক (db_connect.php লাইন ১৪১–১৯৮) ── */
    private static function heartbeatAndBlockCheck(\PDO $conn): void
    {
        if (empty($_SESSION['loggedin'])) {
            return;
        }

        $username = $_SESSION['username'] ?? '';
        $userRole = (string)($_SESSION['role'] ?? 'viewer');

        if (!is_string($username) || $username === '') {
            session_destroy();
            header('Location: ' . self::LOGIN_PAGE . '?status=invalid_session', true, 303);
            exit;
        }

        try {
            $conn->prepare('UPDATE users SET last_active = NOW() WHERE username = ?')
                 ->execute([$username]);

            $stmt = $conn->prepare('SELECT status, block_start, block_end FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $userData = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('AuthKernel heartbeat/block query failed: ' . $e->getMessage());
            return;
        }

        if (!is_array($userData)) {
            return;
        }

        $isBlocked  = (($userData['status'] ?? '') === 'blocked');
        $blockStart = is_string($userData['block_start'] ?? null) ? (string)$userData['block_start'] : '';
        $blockEnd   = is_string($userData['block_end']   ?? null) ? (string)$userData['block_end']   : '';

        if (!$isBlocked && $blockStart !== '' && $blockEnd !== '') {
            $now = date('Y-m-d H:i:s');
            if ($now >= $blockStart && $now <= $blockEnd) {
                $isBlocked = true;
            }
        }

        if ($isBlocked && $userRole !== 'admin') {
            http_response_code(403);
            die('
            <div style="font-family:sans-serif; background:#0f172a; color:white; height:100vh; display:flex; align-items:center; justify-content:center; text-align:center; padding:20px;">
                <div style="background:white; color:#1e293b; padding:40px; border-radius:15px; max-width:400px; border-top:10px solid #ef4444; box-shadow: 0 15px 35px rgba(239, 68, 68, 0.2);">
                    <h1 style="color:#ef4444; margin:0; font-size:24px;">⛔ Sada Kalo Fashion</h1>
                    <p style="font-size:16px; color:#475569; line-height: 1.6; margin: 20px 0;">
                        আপনার অ্যাকাউন্ট সাময়িকভাবে স্থগিত করা হয়েছে। আরও তথ্যের জন্য অনুগ্রহ করে অ্যাডমিনের সাথে যোগাযোগ করুন।
                    </p>
                    <a href="' . self::LOGOUT_PAGE . '" style="display:block; background:#ef4444; color:white; padding:12px 25px; text-decoration:none; border-radius:8px; font-weight:bold; margin-top:20px; text-transform:uppercase; font-size:14px;">লগআউট করুন</a>
                </div>
            </div>');
        }
    }

    /**
     * SessionGuard::forceLogout() এর প্রতিরূপ — শুধু রিডাইরেক্ট absolute।
     * (Services/SessionGuard.php অপরিবর্তিত; ওর relative redirect লিগ্যাসি
     * রুট পেজগুলোর জন্য ঠিক আছে, মডিউল সাব-ফোল্ডারের জন্য নয়।)
     */
    private static function forceLogout(string $status): never
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Location: ' . self::LOGIN_PAGE . '?status=' . rawurlencode($status));
        exit;
    }
}
