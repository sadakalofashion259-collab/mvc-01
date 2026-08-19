<?php
declare(strict_types=1);
session_start();

// সাদা পেজ বন্ধ করে সরাসরি এরর দেখানোর লজিক
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../Controllers/AuthController.php';

date_default_timezone_set('Asia/Dhaka');

// CSRF Token Generation for Form Security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$msg = '';
// ✅ Refresh হলেও session থেকে step রিস্টোর হবে
$step = $_SESSION['otp_step'] ?? 1;
$is_setup = isset($_GET['setup']) && isset($_SESSION['setup_id']);
$setup_id = $is_setup ? (int)$_SESSION['setup_id'] : null;

$authController = new AuthController($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = "<div class='alert error'>❌ সিকিউরিটি টোকেন ইনভ্যালিড! পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।</div>";
    } else {
        if (isset($_POST['send_otp'])) {
            $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
            $response   = $authController->processOtpRequest($identifier, $is_setup, $setup_id);
            $msg  = $response['message'];
            if ($response['success']) {
                $step = 2;
            }
        } elseif (isset($_POST['verify_otp'])) {
            $email = $_SESSION['auth_email'] ?? '';
            $otp = trim($_POST['otp']);
            // PHP 8.1+ standard, removed trailing comma
            $response = $authController->verifyAndReset($email, $otp);
            if (strpos($response['message'], 'ভুল') === false) { 
                $step = 3; 
                $msg = "<div class='alert success'>✅ ভেরিফিকেশন সফল! নতুন পাসওয়ার্ড দিন।</div>"; 
            } else { 
                $step = 2; 
                $msg = $response['message']; 
            }
        } elseif (isset($_POST['reset_password'])) {
            $email = $_SESSION['auth_email'] ?? '';
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];
            
            if ($new_pass === $confirm_pass && strlen($new_pass) >= 4 && strlen($new_pass) <= 8) {
                $response = $authController->verifyAndReset($email, 'forced_skip', $new_pass);
                unset($_SESSION['auth_email'], $_SESSION['setup_id']);
                $step = 1;
                $msg = "<div class='alert success'>✅ পাসওয়ার্ড সফলভাবে আপডেট হয়েছে! <br><br> <a href='../index.php' style='color:#fff; text-decoration:underline;'>লগইন করতে এখানে ক্লিক করুন</a></div>";
            } else { 
                $step = 3; 
                $msg = "<div class='alert error'>❌ পাসওয়ার্ড দুটি মিলেনি অথবা ৪-৮ অক্ষরের মধ্যে নেই!</div>"; 
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title> Verification - SADA KALO FASHION</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #0f172a; color: #f8fafc; font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .auth-container { background: #1e293b; padding: 30px; border-radius: 20px; width: 90%; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #334155; text-align: center; }
        .logo { width: 80px; height: 80px; border-radius: 50%; border: 4px solid #D4AF37; margin-bottom: 10px; }
        h2 { margin: 0 0 5px 0; color: #f8fafc; font-size: 20px; font-weight: 900; text-transform: uppercase; }
        p.subtitle { color: #38bdf8; font-size: 12px; margin-bottom: 20px; font-weight: bold; }
        .input-group { position: relative; margin-bottom: 15px; text-align: left; }
        .input-group i { position: absolute; left: 12px; top: 12px; color: #64748b; }
        .input-group input { width: 100%; padding: 12px 12px 12px 35px; background: #0f172a; border: 1px solid #334155; color: #fff; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; }
        .input-group input:focus { border-color: #38bdf8; }
        .btn-primary { background: linear-gradient(135deg, #10b981, #059669); color: white; width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 14px; font-weight: 900; cursor: pointer; text-transform: uppercase; transition: 0.2s; margin-bottom: 10px; }
        .btn-primary:active { transform: translateY(2px); }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 12px; font-weight: bold; text-align: left; }
        .success { background: #064e3b; color: #34d399; border: 1px solid #059669; }
        .error { background: #7f1d1d; color: #fca5a5; border: 1px solid #ef4444; }
        .back-link { color: #94a3b8; text-decoration: none; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
<div class="auth-container">
    <img src="../logo.png" class="logo" alt="Logo">
    <h2>SADA KALO FASHION</h2>
    <p class="subtitle"><?php echo $is_setup ? "Account Security Setup" : "Verification & Password Reset"; ?></p>
    <?= $msg ?>
    <?php if ($step == 1 && strpos($msg, 'সফলভাবে') === false): ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="input-group"><i class="fas fa-user"></i><input type="text" name="identifier" placeholder="মোবাইল নম্বর / ইমেইল / ইউজারনেম" required autocomplete="off"></div>
            <button type="submit" name="send_otp" class="btn-primary">কোড পাঠান</button>
            <a href="../index.php" class="back-link"><i class="fas fa-arrow-left"></i> 🏠লগইন পেজে ফিরে যান</a>
        </form>
    <?php elseif ($step == 2): ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <p style="color:#38bdf8; font-size:12px; font-weight:bold; margin:-10px 0 15px 0;">Email: <?= htmlspecialchars($_SESSION['auth_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <div class="input-group"><i class="fas fa-key"></i><input type="text" name="otp" placeholder="৬-ডিজিটের Otp কোড" maxlength="6" pattern="\d{6}" required autocomplete="off" style="text-align:center; font-size:18px; letter-spacing:3px;"></div>
            <button type="submit" name="verify_otp" class="btn-primary"><i class="fas fa-check-circle"></i> Otpকোড যাচাই করুন</button>
        </form>
    <?php elseif ($step == 3): ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="input-group"><i class="fas fa-lock"></i><input type="password" name="new_password" placeholder="নতুন পাসওয়ার্ড (৪-৮ অক্ষর)" required minlength="4" maxlength="8"></div>
            <div class="input-group"><i class="fas fa-check-double"></i><input type="password" name="confirm_password" placeholder="পুনরায় নতুন পাসওয়ার্ড দিন" required minlength="4" maxlength="8"></div>
            <button type="submit" name="reset_password" class="btn-primary">পাসওয়ার্ড সেভ করুন</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>