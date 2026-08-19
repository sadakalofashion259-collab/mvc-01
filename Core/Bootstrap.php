<?php
declare(strict_types=1);
/**
 * Bootstrap.php — কেন্দ্রীয় এন্ট্রি পয়েন্ট (মডিউল ও নতুন পেজের জন্য)
 * ────────────────────────────────────────────────────────────────────────
 *  যা করে:
 *    1) সেশন নিরাপদভাবে স্টার্ট
 *    2) টাইমজোন সেট
 *    3) .env (Vault) থেকে কনফিগ পড়ে PDO কানেকশন তৈরি
 *    4) SessionGuard + AuthKernel + DeviceInfo + LoginLogger + LoginEmailAlert লোড
 *    5) AuthKernel::enforce() চালায় (idle + single-session + heartbeat + block)
 *    6) CSRF প্রোটেকশন লোড ও ভেরিফাই
 *
 *  ব্যবহার (মডিউল/নতুন পেজে):
 *    $root = dirname(__DIR__, 2);
 *    require_once $root . '/Core/Bootstrap.php';
 */

/* ═══════════════════════════════════════════════════════════════
 *  ১. কনস্ট্যান্ট
 * ═══════════════════════════════════════════════════════════════ */
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 1200);
}
if (!defined('VAULT_PATH')) {
    define('VAULT_PATH', '/home/sadakalo/App/.env');
}
if (!defined('TIMEZONE')) {
    define('TIMEZONE', 'Asia/Dhaka');
}

/* ═══════════════════════════════════════════════════════════════
 *  ২. সেশন স্টার্ট (নিরাপদ অপশন সহ)
 * ═══════════════════════════════════════════════════════════════ */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly'  => true,
        'cookie_secure'    => true,
        'cookie_samesite'  => 'Strict',
        'use_strict_mode'  => true,
    ]);
}
date_default_timezone_set(TIMEZONE);

/* ═══════════════════════════════════════════════════════════════
 *  ৩. প্রয়োজনীয় সার্ভিস লোড
 * ═══════════════════════════════════════════════════════════════ */
$coreDir     = __DIR__;
$servicesDir = dirname(__DIR__) . '/Services';

require_once $servicesDir . '/SessionGuard.php';
require_once $coreDir     . '/AuthKernel.php';
require_once $servicesDir . '/DeviceInfo.php';
require_once $servicesDir . '/LoginLogger.php';
require_once $servicesDir . '/LoginEmailAlert.php';
require_once $coreDir     . '/Csrf.php';          // ✅ CSRF যোগ হলো

/* ═══════════════════════════════════════════════════════════════
 *  ৪. নিরাপদ DB এরর পেজ (তথ্য লিক রোধ)
 * ═══════════════════════════════════════════════════════════════ */
if (!function_exists('show_db_error_page')) {
    function show_db_error_page(string $userMessage): never
    {
        error_log('Database Error: ' . $userMessage);
        $safeMessage = htmlspecialchars(
            'ডাটাবেজ সংযোগে সমস্যা হয়েছে। অনুগ্রহ করে পরে চেষ্টা করুন।',
            ENT_QUOTES,
            'UTF-8'
        );
        http_response_code(503);
        die('
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Database Connection Error</title>
<style>
body{background:#0f172a;margin:0;display:flex;align-items:center;justify-content:center;height:100vh;font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif}
.error-container{background:#1e293b;border:2px solid #ef4444;border-radius:12px;padding:40px;text-align:center;max-width:500px;width:90%;box-shadow:0 15px 35px rgba(239,68,68,.2)}
.error-icon{font-size:70px;margin-bottom:20px;line-height:1}
.error-title{color:#ef4444;font-size:28px;margin:0 0 15px;font-weight:900}
.error-text{color:#cbd5e1;font-size:16px;line-height:1.6;margin:0 0 25px}
.error-btn{background:#ef4444;color:#fff;padding:12px 25px;border-radius:8px;font-weight:bold;display:inline-block;text-transform:uppercase;font-size:14px;letter-spacing:1px;text-decoration:none}
</style>
</head>
<body>
<div class="error-container">
<div class="error-icon">⚠️</div>
<h1 class="error-title">সিস্টেম ত্রুটি!</h1>
<p class="error-text">' . $safeMessage . '</p>
<a href="/" class="error-btn">হোমপেজে ফিরুন</a>
</div>
</body>
</html>');
    }
}

/* ═══════════════════════════════════════════════════════════════
 *  ৫. Vault (.env) লোড ও ভ্যালিডেশন
 * ═══════════════════════════════════════════════════════════════ */
if (!file_exists(VAULT_PATH)) {
    show_db_error_page('সিস্টেমের সিকিউরিটি ভল্ট (.env) খুঁজে পাওয়া যাচ্ছে না।');
}
$dbConfig = parse_ini_file(VAULT_PATH);
if (!is_array($dbConfig)) {
    show_db_error_page('সিকিউরিটি ভল্ট থেকে কনফিগারেশন ডাটা পড়া যাচ্ছে না।');
}
$requiredKeys = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'CARD_ENC_KEY'];
foreach ($requiredKeys as $key) {
    if (!isset($dbConfig[$key]) || !is_scalar($dbConfig[$key]) || trim((string)$dbConfig[$key]) === '') {
        show_db_error_page("কনফিগারেশনে প্রয়োজনীয় কি অনুপস্থিত: {$key}");
    }
}
$dbHost     = (string)$dbConfig['DB_HOST'];
$dbUser     = (string)$dbConfig['DB_USER'];
$dbPass     = (string)$dbConfig['DB_PASS'];
$dbName     = (string)$dbConfig['DB_NAME'];
$cardEncKey = trim((string)$dbConfig['CARD_ENC_KEY']);
if (!defined('CARD_ENC_KEY')) {
    define('CARD_ENC_KEY', $cardEncKey);
}

/* ═══════════════════════════════════════════════════════════════
 *  ৬. PDO কানেকশন
 * ═══════════════════════════════════════════════════════════════ */
try {
    $conn = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('PDO Connection Error: ' . $e->getMessage());
    show_db_error_page('ডাটাবেজে সংযোগ স্থাপন করা যাচ্ছে না।');
}

/* ═══════════════════════════════════════════════════════════════
 *  ৭. অথেনটিকেশন এনফোর্সমেন্ট (AuthKernel)
 * ═══════════════════════════════════════════════════════════════ */
AuthKernel::enforce($conn);

/* ═══════════════════════════════════════════════════════════════
 *  ৮. CSRF এনফোর্সমেন্ট (POST রিকোয়েস্টে)
 * ═══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!Csrf::verify($token)) {
        http_response_code(403);
        die('
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>CSRF Error</title>
<style>
body{background:#0f172a;display:flex;align-items:center;justify-content:center;height:100vh;font-family:"Segoe UI",sans-serif}
.err{background:#1e293b;border:2px solid #f59e0b;border-radius:12px;padding:40px;text-align:center;max-width:450px;width:90%}
.err h1{color:#f59e0b;font-size:24px;margin:0 0 10px}
.err p{color:#cbd5e1;font-size:15px;margin:0 0 20px}
.err a{background:#f59e0b;color:#000;padding:10px 20px;border-radius:8px;font-weight:bold;text-decoration:none}
</style>
</head>
<body>
<div class="err">
<h1>🚫 CSRF ভেরিফিকেশন ব্যর্থ</h1>
<p>আপনার সেশনের নিরাপত্তা টোকেন সঠিক নয়। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।</p>
<a href="' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES) . '">আবার চেষ্টা করুন</a>
</div>
</body>
</html>');
    }
}

/* ═══════════════════════════════════════════════════════════════
 *  ৯. গ্লোবাল হেল্পার
 * ═══════════════════════════════════════════════════════════════ */
function current_device(): DeviceInfo
{
    static $device = null;
    if ($device === null) {
        $device = DeviceInfo::fromRequest();
    }
    return $device;
}

function login_logger(): LoginLogger
{
    static $logger = null;
    if ($logger === null) {
        $logger = new LoginLogger();
    }
    return $logger;
}

function alert_login_success(string $username, string $email, string $phone = ''): void
{
    LoginEmailAlert::success(accountName: $username, userEmail: $email, phoneNumber: $phone);
}

function alert_login_failed(string $username, string $email = '', string $phone = ''): void
{
    LoginEmailAlert::failed(accountName: $username, userEmail: $email, phoneNumber: $phone);
}

function alert_login_blocked(string $username, string $email = '', string $phone = ''): void
{
    LoginEmailAlert::blocked(accountName: $username, userEmail: $email, phoneNumber: $phone);
}

/**
 * ফর্মে CSRF hidden field আউটপুট করে
 * ব্যবহার: <?php csrf_field(); ?>
 */
function csrf_field(): void
{
    echo Csrf::hiddenField();
}

/**
 * AJAX রিকোয়েস্টে CSRF টোকেন পাঠাতে
 * ব্যবহার: fetch(url, { headers: { 'X-CSRF-TOKEN': csrfToken() } })
 */
function csrf_token(): string
{
    return Csrf::token();
}

/* ───────────────────────────────────────────────────────────────
 *  Bootstrap সম্পন্ন।
 * ─────────────────────────────────────────────────────────────── */