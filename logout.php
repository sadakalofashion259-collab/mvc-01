<?php
declare(strict_types=1);

/**
 * logout.php — সেশন ধ্বংস + Single Active Session টোকেন পরিষ্কার + লগআউট রেকর্ড।
 * db_connect.php-এ logout.php-এর জন্য SessionGuard::enforce() স্কিপ করা আছে,
 * তাই এখানে অন্তর্ভুক্ত করলেও ইউজার আগেই লগআউট হয়ে যাবে না।
 */

require_once __DIR__ . '/db_connect.php';           // $conn + session চালু
require_once __DIR__ . '/Services/SessionGuard.php';
require_once __DIR__ . '/Services/DeviceInfo.php';
require_once __DIR__ . '/Services/LoginLogger.php';

$userId   = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$username = (isset($_SESSION['username']) && is_string($_SESSION['username']))
    ? $_SESSION['username']
    : (string) $userId;

if ($userId > 0 && isset($conn) && $conn instanceof PDO) {
    // DB থেকে টোকেন মুছে দাও — এই ইউজারের সব লাইভ সেশন বাতিল।
    SessionGuard::clearToken($conn, $userId);

    // Login_Log.txt-এ লগআউট রেকর্ড।
    (new LoginLogger())->record('LOGOUT', $username, DeviceInfo::fromRequest());
}

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        (bool) $params["secure"],
        (bool) $params["httponly"]
    );
}
session_destroy();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Location: index.php?status=logged_out");
exit;
