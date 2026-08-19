<?php
declare(strict_types=1);

/**
 * SessionGuard — Single Active Session (এক ডিভাইসে একটাই লাইভ লগইন)।
 * ────────────────────────────────────────────────────────────────────────
 *  কাজের নীতি:
 *   1) লগইন সফল হলে issueToken() একটি নতুন র‍্যান্ডম টোকেন বানিয়ে
 *      users.active_session_token-এ সেভ করে এবং সেশনেও রাখে।
 *   2) নতুন ডিভাইসে লগইন হলে DB-র টোকেন ওভাররাইট হয়ে যায়।
 *   3) পুরোনো ডিভাইস পরের যেকোনো পেজ খুললে enforce() চলে — সেশন টোকেন আর
 *      DB টোকেন না মিললে সাথে সাথে সেই ডিভাইস লগআউট হয়ে যায়।
 *
 *  সব DB অ্যাক্সেস Prepared Statement দিয়ে — SQL Injection অসম্ভব।
 *  hash_equals() দিয়ে টাইমিং-সেফ তুলনা।
 */
final class SessionGuard
{
    /** সেশনের ভেতর টোকেন যে কী-তে থাকে */
    public const SESSION_KEY = 'active_session_token';

    /**
     * নতুন সিঙ্গেল-সেশন টোকেন তৈরি করে DB + সেশনে বসায়।
     * লগইন সফল হওয়ার ঠিক পরে ডাকতে হবে।
     *
     * @return string নতুন টোকেন
     */
    public static function issueToken(\PDO $db, int $userId): string
    {
        $token = bin2hex(random_bytes(32));

        $stmt = $db->prepare(
            'UPDATE users
                SET active_session_token = ?, active_session_updated_at = NOW()
              WHERE id = ?'
        );
        $stmt->execute([$token, $userId]);

        $_SESSION[self::SESSION_KEY] = $token;
        return $token;
    }

    /**
     * প্রতিটি প্রোটেক্টেড পেজ লোডে ডাকতে হবে (db_connect.php থেকে)।
     * সেশন টোকেন ও DB টোকেন না মিললে বর্তমান (পুরোনো) ডিভাইস লগআউট।
     *
     * লগইন না থাকলে বা টোকেন কলাম না থাকলে চুপচাপ ফিরে আসে (কিছু ভাঙে না)।
     */
    public static function enforce(\PDO $db): void
    {
        if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
            return;
        }

        $sessionToken = (string) ($_SESSION[self::SESSION_KEY] ?? '');

        try {
            $stmt = $db->prepare(
                'SELECT active_session_token FROM users WHERE id = ? LIMIT 1'
            );
            $stmt->execute([(int) $_SESSION['user_id']]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // মাইগ্রেশন এখনো না চললে কলাম নেই — সিস্টেম আগের মতোই চলবে।
            return;
        }

        $dbToken = is_array($row) ? (string) ($row['active_session_token'] ?? '') : '';

        // DB-তে টোকেন থাকলে অথচ সেশনের সাথে না মিললে → অন্য ডিভাইস দখল নিয়েছে।
        if ($dbToken !== '') {
            if ($sessionToken === '' || !hash_equals($dbToken, $sessionToken)) {
                self::forceLogout('session_replaced');
            }
        }
    }

    /**
     * লগআউটের সময় DB টোকেন মুছে দেয় (যাতে অন্য ডিভাইসও পরিষ্কার হয়)।
     */
    public static function clearToken(\PDO $db, int $userId): void
    {
        try {
            $stmt = $db->prepare(
                'UPDATE users
                    SET active_session_token = NULL, active_session_updated_at = NOW()
                  WHERE id = ?'
            );
            $stmt->execute([$userId]);
        } catch (\Throwable $e) {
            // উপেক্ষণীয় — লগআউট যেকোনোভাবেই সম্পন্ন হবে।
        }
    }

    /**
     * বর্তমান সেশন সম্পূর্ণ ধ্বংস করে লগইন পেজে পাঠায়।
     */
    public static function forceLogout(string $status = 'logged_out'): never
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
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Location: index.php?status=' . rawurlencode($status));
        exit;
    }
}
