<?php
declare(strict_types=1);

// ১. কনফিগারেশন কনস্ট্যান্ট
define('SESSION_TIMEOUT', 1200); // 20 minutes in seconds

define('VAULT_PATH','/home/sadakalo/App/.env');

// Relative path instead of absolute

define('TIMEZONE', 'Asia/Dhaka');

// ২. সেশন এবং টাইমজোন সেটআপ
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => true, // HTTPS only
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}
date_default_timezone_set(TIMEZONE);

// Single Active Session গার্ড লোড
require_once __DIR__ . '/Services/SessionGuard.php';

// ৩. প্রফেশনাল ডাটাবেজ এরর পেজ (তথ্য লিক রোধ করা হয়েছে)
if (!function_exists('show_db_error_page')) {
    function show_db_error_page(string $userMessage): never {
        // Log actual error for debugging (don't show to user)
        error_log("Database Error: " . $userMessage);
        
        // Generic user-facing message (no system info leak)
        $safeMessage = htmlspecialchars('ডাটাবেজ সংযোগে সমস্যা হয়েছে। অনুগ্রহ করে পরে চেষ্টা করুন।', ENT_QUOTES, 'UTF-8');
        
        http_response_code(503);
        die('
        <!DOCTYPE html>
        <html lang="bn">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Database Connection Error</title>
            <style>
                body { background-color: #0f172a; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
                .error-container { background: #1e293b; border: 2px solid #ef4444; border-radius: 12px; padding: 40px; text-align: center; max-width: 500px; width: 90%; box-shadow: 0 15px 35px rgba(239, 68, 68, 0.2); }
                .error-icon { font-size: 70px; margin-bottom: 20px; line-height: 1; }
                .error-title { color: #ef4444; font-size: 28px; margin: 0 0 15px 0; font-weight: 900; }
                .error-text { color: #cbd5e1; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0; }
                .error-btn { background: #ef4444; color: #ffffff; padding: 12px 25px; border-radius: 8px; font-weight: bold; display: inline-block; text-transform: uppercase; font-size: 14px; letter-spacing: 1px; text-decoration: none; }
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
        </html>
        ');
    }
}

// ৪. ভল্ট (.env) থেকে সুরক্ষিত ডাটাবেজ পাসওয়ার্ড পড়া
if (!file_exists(VAULT_PATH)) {
    show_db_error_page("সিস্টেমের সিকিউরিটি ভল্ট (.env) খুঁজে পাওয়া যাচ্ছে না।");
}

$dbConfig = parse_ini_file(VAULT_PATH);
if (!is_array($dbConfig)) {
    show_db_error_page("সিকিউরিটি ভল্ট থেকে কনফিগারেশন ডাটা পড়া যাচ্ছে না।");
}

// ৫. কনফিগ ভ্যালিডেশন (নিরাপদ ডিফল্ট মান)
$requiredKeys = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'CARD_ENC_KEY'];
foreach ($requiredKeys as $key) {
    if (!isset($dbConfig[$key]) || !is_scalar($dbConfig[$key]) || trim((string)$dbConfig[$key]) === '') {
        show_db_error_page("কনফিগারেশনে প্রয়োজনীয় কি অনুপস্থিত: {$key}");
    }
}

$dbHost = (string)$dbConfig['DB_HOST'];
$dbUser = (string)$dbConfig['DB_USER'];
$dbPass = (string)$dbConfig['DB_PASS'];
$dbName = (string)$dbConfig['DB_NAME'];
$cardEncKey = trim((string)$dbConfig['CARD_ENC_KEY']);

// এনক্রিপশন কি গ্লোবাল কনস্ট্যান্ট হিসেবে সেট
define('CARD_ENC_KEY', $cardEncKey);

// ৬. PDO ডাটাবেজ কানেকশন
try {
    $conn = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // True prepared statements
        ]
    );

    // বর্তমান পেজ নির্ধারণ (ভ্যালিডেট করা)
    $current_page = basename($_SERVER['PHP_SELF'] ?? 'index.php');

    // ৭. অটো-লগআউট লজিক (২০ মিনিট নিষ্ক্রিয়তা)
    if (isset($_SESSION['loggedin'], $_SESSION['last_action_time']) 
        && $_SESSION['loggedin'] === true 
        && is_int($_SESSION['last_action_time'])) {
        
        $idle_time = time() - $_SESSION['last_action_time'];
        
        if ($idle_time >= SESSION_TIMEOUT) {
            $username = $_SESSION['username'] ?? null;
            
            // লগআউট করার আগে last_active রিসেট
            if (is_string($username) && $username !== '') {
                $stmt = $conn->prepare("UPDATE users SET last_active = '2000-01-01 00:00:00' WHERE username = ?");
                $stmt->execute([$username]);
            }
            
            session_unset();
            session_destroy();
            header("Location: index.php?status=timeout", true, 303);
            exit;
        }
    }
    
    // সেশন টাইমস্ট্যাম্প আপডেট
    $_SESSION['last_action_time'] = time();

    // ৮. Single Active Session চেক
    if (!in_array($current_page, ['index.php', 'logout.php'], true)) {
        SessionGuard::enforce($conn);
    }

    // ৯. লাইভ স্ট্যাটাস ও ব্লক চেক লজিক
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        $username = $_SESSION['username'] ?? '';
        $userRole = $_SESSION['role'] ?? 'viewer';
        
        // ইনপুট ভ্যালিডেশন
        if (!is_string($username) || $username === '') {
            session_destroy();
            header("Location: index.php?status=invalid_session", true, 303);
            exit;
        }
        
        // লাস্ট অ্যাক্টিভ টাইম আপডেট
        if ($current_page !== 'logout.php') {
            $stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE username = ?");
            $stmt->execute([$username]);
        }

        // ব্লক স্ট্যাটাস চেক
        $stmt = $conn->prepare("SELECT status, block_start, block_end FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $userData = $stmt->fetch();
        
        if ($userData !== false) {
            $now = new DateTime('now', new DateTimeZone(TIMEZONE));
            $isBlocked = ($userData['status'] === 'blocked');

            // টাইম-বেসড ব্লক চেক (NULL-safe)
            if (!$isBlocked && $userData['block_start'] !== null && $userData['block_end'] !== null) {
                try {
                    $blockStart = new DateTime($userData['block_start'], new DateTimeZone(TIMEZONE));
                    $blockEnd = new DateTime($userData['block_end'], new DateTimeZone(TIMEZONE));
                    
                    if ($now >= $blockStart && $now <= $blockEnd) {
                        $isBlocked = true;
                    }
                } catch (Exception $e) {
                    error_log("Invalid block date format for user {$username}: " . $e->getMessage());
                }
            }

            // অ্যাডমিন ছাড়া ব্লক করা ইউজারদের জন্য
            if ($isBlocked && $userRole !== 'admin' 
                && !in_array($current_page, ['logout.php', 'index.php'], true)) {
                
                http_response_code(403);
                die('
                <div style="font-family:sans-serif; background:#0f172a; color:white; height:100vh; display:flex; align-items:center; justify-content:center; text-align:center; padding:20px;">
                    <div style="background:white; color:#1e293b; padding:40px; border-radius:15px; max-width:400px; border-top:10px solid #ef4444; box-shadow: 0 15px 35px rgba(239, 68, 68, 0.2);">
                        <h1 style="color:#ef4444; margin:0; font-size:24px;">⛔ Sada Kalo Fashion</h1>
                        <p style="font-size:16px; color:#475569; line-height: 1.6; margin: 20px 0;">
                            আপনার অ্যাকাউন্ট সাময়িকভাবে স্থগিত করা হয়েছে। আরও তথ্যের জন্য অনুগ্রহ করে অ্যাডমিনের সাথে যোগাযোগ করুন।
                        </p>
                        <a href="logout.php" style="display:block; background:#ef4444; color:white; padding:12px 25px; text-decoration:none; border-radius:8px; font-weight:bold; margin-top:20px; text-transform:uppercase; font-size:14px;">লগআউট করুন</a>
                    </div>
                </div>');
            }
        }
    }

} catch(PDOException $e) {
    // লগ করুন কিন্তু ইউজারকে বিস্তারিত দেখাবেন না
    error_log("PDO Connection Error: " . $e->getMessage());
    show_db_error_page("ডাটাবেজে সংযোগ স্থাপন করা যাচ্ছে না🙂🙂স্ক্রিনশট ADMIN - কে কল করে জানান ");
}
?>
