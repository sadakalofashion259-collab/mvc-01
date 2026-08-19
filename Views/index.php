<?php
declare(strict_types=1);

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

class BusinessLogicException extends Exception {}

function logSystemError(Throwable $e): void {
    $logDir = __DIR__ . '/../Logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir.'/error_log.txt', "[".date('Y-m-d H:i:s')."] ".$e->getMessage()." | ".$e->getFile()." line ".$e->getLine().PHP_EOL, FILE_APPEND);
}

$isAjax = isset($_POST['ajax_action']);

// ১. সেশন টাইমআউট চেক
$lastActivity = $_SESSION['last_activity'] ?? null;
if ($lastActivity !== null && is_int($lastActivity) && (time() - $lastActivity > 1200)) {
    session_unset(); 
    session_destroy();
    if ($isAjax) { 
        header('Content-Type: application/json'); 
        echo json_encode(['status'=>'session_expired','message'=>'Session Expired!']); 
        exit; 
    }
    echo "<script>alert('Session Expired!'); window.location.href='../index.php';</script>"; 
    exit;
}
$_SESSION['last_activity'] = time();

// ২. লগইন এবং রোল (Admin) চেক
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (!$is_logged_in || !$is_admin) {
    if ($isAjax) { 
        header('Content-Type: application/json'); 
        echo json_encode(['status'=>'access_denied','message'=>'Access Denied!']); 
        exit; 
    }
    ?>
    <!DOCTYPE html>
    <html lang="bn">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>অ্যাক্সেস অস্বীকৃত (Access Denied)</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            body { background: #f8fafc; height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
            
            /* বক্সের চারপাশে হালকা বর্ডার এবং সুন্দর শ্যাডো */
            .error-container { 
                background: #ffffff; 
                border-radius: 16px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
                max-width: 500px; 
                width: 100%; 
                text-align: center; 
                padding: 40px 30px;
                border: 1px solid #e2e8f0; /* হালকা সাইড বর্ডার */
            }
            
            /* গ্রীন থিম আইকন */
            .error-icon {
                font-size: 55px;
                color: #10b981; /* সবুজ রঙ */
                margin-bottom: 15px;
            }
            
            /* মেইন হেডিং সবুজ */
            h2 { color: #10b981; margin-bottom: 20px; font-size: 26px; font-weight: 700; }
            
            /* হ্যাকার মেসেজ বক্স (হালকা সবুজ ব্যাকগ্রাউন্ড ও সবুজ টেক্সট) */
            .hacker-message { 
                color: #047857; 
                font-size: 15px; 
                line-height: 1.8; 
                margin-bottom: 25px; 
                background: #f0fdf4; /* হালকা সবুজ ব্যাকগ্রাউন্ড */
                padding: 25px; 
                border-radius: 12px; 
                border-left: 5px solid #10b981; /* বামে সবুজ বর্ডার */
                text-align: center; 
                white-space: pre-line; 
                font-weight: 500; 
            }
            
            .brand-footer { margin-top: 15px; padding-top: 15px; border-top: 1px dashed #a7f3d0; color: #065f46; font-weight: bold; font-size: 14px; }
            
            /* কন্টাক্ট বক্স */
            .contact-info { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 8px; margin-bottom: 25px; font-size: 15px; color: #334155; }
            .contact-info a { color: #10b981; text-decoration: none; font-weight: bold; }
            
            /* লগইন বাটন সবুজ থিম */
            .login-btn { display: block; background: #10b981; color: white; text-decoration: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: bold; transition: background 0.3s; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
            .login-btn:hover { background: #059669; }
        </style>
    </head>
    <body>
    <div class="error-container">
        <!-- ইমেজ ছাড়া সুন্দর আইকন -->
        <div class="error-icon">🛡️</div>
        <h2>═══সাদা-কালো-ফ্যাশন═══

❌অ্যাক্সেস অস্বীকৃত! ❌</h2>
        
        <div class="hacker-message">
            🥰
        🥰
হ্যাক করার চেষ্টা করছেন? 🤫 চেষ্টাটি ভালো ছিল! 
কিন্তু দুর্ভাগ্যবশত আমাদের দেয়ালটি বেশ শক্ত। 🥰

কষ্ট না করে বরং এক কাপ কফি খান 🥵, 
আর নিজের আসল অ্যাকাউন্ট দিয়ে ভদ্রলোকের মতো লগইন করুন। 

নতুবা আপনার আইপি (IP) অ্যাড্রেসটি আমাদের ব্ল্যাকলিস্টে জায়গা করে নেবে! 🛑
<div class="brand-footer">═════ সাদা-কালো ফ্যাশন ═════<br>Thanks</div></div>
        
        <div class="contact-info">
            যোগাযোগ করুন: <a href="tel:01821-933259">01821-933259</a>
        </div>
        <a href="/index.php" class="login-btn">লগইন পেজে যান</a>
    </div>
    </body>
    </html>
    <?php
    exit;
} else {
    // --- এডমিন ড্যাশবোর্ড অংশ (লগইন করা থাকলে এটি দেখাবে) ---
    ?>
    <!DOCTYPE html>
    <html lang="bn">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>এডমিন প্যানেল - সাদা-কালো ফ্যাশন</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            body { background-color: #f8fafc; color: #333; }
            .navbar { background: #111111; color: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
            .nav-brand span { font-size: 20px; font-weight: bold; letter-spacing: 1px; }
            .logout-btn { color: #ff4d4d; text-decoration: none; font-weight: 600; border: 1px solid #ff4d4d; padding: 8px 16px; border-radius: 6px; transition: all 0.3s; }
            .logout-btn:hover { background: #ff4d4d; color: #fff; }
            
            .dashboard-banner { width: 100%; height: 200px; background: linear-gradient(135deg, #065f46 0%, #10b981 100%); display: flex; justify-content: center; align-items: center; color: white; text-align: center; }
            .banner-text h1 { font-size: 32px; margin-bottom: 10px; }
            .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
            .welcome-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.02); border-top: 5px solid #10b981; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        </style>
    </head>
    <body>

    <nav class="navbar">
        <div class="nav-brand">
            <span>সাদা-কালো ফ্যাশন</span>
        </div>
        <a href="logout.php" class="logout-btn">লগআউট</a>
    </nav>

    <div class="dashboard-banner">
        <div class="banner-text">
            <h1>স্বাগতম এডমিন প্যানেলে! 👑</h1>
            <p>নিয়ন্ত্রণ প্যানেল এবং অ্যাসিস্ট্যান্ট ড্যাশবোর্ড</p>
        </div>
    </div>

    <div class="container">
        <div class="welcome-card">
            <h2 style="color: #10b981;">হ্যালো, এডমিন! 👋</h2>
            <br>
            <p>আপনার লগইন সফল হয়েছে এবং রোল (Role) নিশ্চিত করা হয়েছে। আপনি এখন এখান থেকে আপনার ই-কমার্স সাইটের ইনভেন্টরি, অর্ডার এবং সমস্ত এডমিন কার্যক্রম পরিচালনা করতে পারবেন।</p>
        </div>
    </div>
    <script src="/assets/js/pwa-app.js" defer></script>

 </body>
    
    </html>
    <?php
}
?>
