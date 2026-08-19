<?php
declare(strict_types=1);
ob_start();
session_start();
require_once '../db_connect.php';
require_once '../track_activity.php';
require_once '../Models/MailService.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../index.php"); exit; 
}

$adminUsername = $_SESSION['username'];
$alertMessage = "";
$alertType = "";
$currentProcessStep = $_SESSION['reset_step'] ?? 1;

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

if (isset($_POST['send_otp_email'])) {
    $submittedEmail = filter_var($_POST['admin_email'], FILTER_SANITIZE_EMAIL);
    try {
        $stmt = $conn->prepare("SELECT email FROM users WHERE username = ? AND role = 'admin'");
        $stmt->execute([$adminUsername]);
        $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($adminData && $adminData['email'] === $submittedEmail) {
            $generatedOtpCode = random_int(100000, 999999);
            
            // নতুন MailService এর মাধ্যমে লোকাল SMTP রুটে মেইল পাঠানো হচ্ছে
            $mailService = new MailService();
            $isMailSent = $mailService->sendAdminOtpEmail($submittedEmail, (string)$generatedOtpCode);
            
            if ($isMailSent) {
                $_SESSION['action_otp_code'] = $generatedOtpCode;
                $_SESSION['action_otp_time'] = time();
                $_SESSION['reset_step'] = 2;
                $currentProcessStep = 2;
                $alertMessage = "আপনার ইমেইলে ওটিপি পাঠানো হয়েছে।";
                $alertType = "success";
            } else {
                $alertMessage = "ইমেইল পাঠাতে সমস্যা হচ্ছে! সার্ভার মেইল কিউ চেক করুন।";
                $alertType = "error";
            }
        } else {
            $alertMessage = "এই ইমেইলটি অ্যাডমিন অ্যাকাউন্টের সাথে মিলছে না!";
            $alertType = "error";
        }
    } catch (PDOException $e) { 
        $logMessage = "[" . date('Y-m-d H:i:s') . "] DB Admin OTP Error: " . $e->getMessage() . PHP_EOL;
        file_put_contents(__DIR__ . '/../Logs/error_log.txt', $logMessage, FILE_APPEND);
        $alertMessage = "ডাটাবেজ কানেকশন সমস্যা!"; 
        $alertType = "error"; 
    }
}

if (isset($_POST['verify_otp_code'])) {
    $enteredOtpCode = $_POST['otp_code'];
    if (isset($_SESSION['action_otp_code']) && (string)$_SESSION['action_otp_code'] === $enteredOtpCode) {
        if ((time() - $_SESSION['action_otp_time']) <= 300) {
            $_SESSION['reset_step'] = 3; $currentProcessStep = 3;
            $alertMessage = "ওটিপি মিলেছে! নতুন পাসওয়ার্ড সেট করুন।"; $alertType = "success";
        } else {
            $alertMessage = "ওটিপি কোডের মেয়াদ শেষ হয়ে গেছে!"; $alertType = "error";
            $_SESSION['reset_step'] = 1; $currentProcessStep = 1;
        }
    } else { $alertMessage = "ভুল ওটিপি কোড দিয়েছেন!"; $alertType = "error"; }
}

if (isset($_POST['set_new_action_pass'])) {
    $newActionPass = $_POST['new_pass'];
    $confirmActionPass = $_POST['confirm_pass'];
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $alertMessage = "সিকিউরিটি ত্রুটি!"; $alertType = "error";
    } elseif ($newActionPass !== $confirmActionPass) {
        $alertMessage = "নতুন পাসওয়ার্ড দুটি মিলেনি!"; $alertType = "error";
    } else {
        $hashedActionPass = password_hash($newActionPass, PASSWORD_BCRYPT);
        $conn->prepare("UPDATE users SET action_pass = ? WHERE username = ? AND role = 'admin'")->execute([$hashedActionPass, $adminUsername]);
        unset($_SESSION['action_otp_code'], $_SESSION['action_otp_time'], $_SESSION['reset_step']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $currentProcessStep = 4;
        $alertMessage = "অ্যাকশন পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে!"; $alertType = "success";
    }
}

if (isset($_GET['cancel_reset'])) {
    unset($_SESSION['action_otp_code'], $_SESSION['action_otp_time'], $_SESSION['reset_step']);
    header("Location: change_action_password.php"); exit;
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অ্যাকশন পাসওয়ার্ড রিকভারি</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #e2e8f0; color: #1e293b; margin: 0; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; width: 100%; max-width: 400px; padding: 30px 20px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 2px solid #cbd5e1; }
        .header-icon { width: 60px; height: 60px; background: #fef2f2; color: #ef4444; font-size: 25px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 15px; border: 2px solid #fca5a5; }
        h2 { text-align: center; margin: 0 0 5px; font-size: 20px; font-weight: 900; color: #1e293b; text-transform: uppercase; }
        p.subtitle { text-align: center; font-size: 12px; color: #64748b; margin-bottom: 25px; font-weight: 600; line-height: 1.5;}
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 800; color: #475569; margin-bottom: 5px; text-transform: uppercase; }
        .inp { width: 100%; padding: 14px; border: 2px solid #e2e8f0; background: #f8fafc; color: #1e293b; border-radius: 10px; font-size: 15px; outline: none; font-weight: bold; transition: 0.2s; text-align: center; letter-spacing: 1px; }
        .inp:focus { border-color: #ef4444; background: #fff; }
        .btn-action { width: 100%; background: #ef4444; box-shadow: 0 4px 0 #b91c1c; color: #fff; border: none; padding: 15px; border-radius: 12px; font-size: 16px; font-weight: 900; cursor: pointer; text-transform: uppercase; margin-top: 10px; transition: 0.1s; }
        .btn-action:active { transform: translateY(4px); box-shadow: none; }
        .btn-success { background: #10b981; box-shadow: 0 4px 0 #047857;}
        .btn-success:active { box-shadow: none;}
        .back-link { display: block; text-align: center; margin-top: 20px; color: #3b82f6; font-size: 14px; font-weight: bold; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header-icon"><i class="fas fa-shield-alt"></i></div>
        <?php if ($currentProcessStep == 1): ?>
            <h2>অ্যাকাউন্ট ভেরিফিকেশন</h2>
            <p class="subtitle">অ্যাকশন পাসওয়ার্ড রিসেট করতে আপনার অ্যাডমিন ইমেইলটি দিন</p>
            <form method="POST" action="">
                <div class="form-group"><label>আপনার ইমেইল ঠিকানা</label><input type="email" name="admin_email" class="inp" placeholder="Email" required></div>
                <button type="submit" name="send_otp_email" class="btn-action"><i class="fas fa-paper-plane"></i> ওটিপি পাঠান</button>
            </form>
        <?php elseif ($currentProcessStep == 2): ?>
            <h2>ওটিপি ভেরিফিকেশন</h2>
            <p class="subtitle">আপনার ইমেইলে পাঠানো ৬ ডিজিটের কোডটি এখানে দিন</p>
            <form method="POST" action="">
                <div class="form-group"><label>ওটিপি কোড</label><input type="number" name="otp_code" class="inp" placeholder="933259" required></div>
                <button type="submit" name="verify_otp_code" class="btn-action btn-success"><i class="fas fa-check-circle"></i> কোড যাচাই করুন</button>
            </form>
        <?php elseif ($currentProcessStep == 3): ?>
            <h2>নতুন পাসওয়ার্ড সেট করুন</h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="form-group"><label>নতুন অ্যাকশন পাসওয়ার্ড</label><input type="password" name="new_pass" class="inp" placeholder="••••••••" required></div>
                <div class="form-group"><label>পুনরায় নতুন পাসওয়ার্ড দিন</label><input type="password" name="confirm_pass" class="inp" placeholder="••••••••" required></div>
                <button type="submit" name="set_new_action_pass" class="btn-action"><i class="fas fa-save"></i> সেভ করুন</button>
            </form>
        <?php elseif ($currentProcessStep == 4): ?>
            <h2>পাসওয়ার্ড পরিবর্তিত!</h2>
            <p class="subtitle">আপনার অ্যাকশন পাসওয়ার্ড সফলভাবে সেট করা হয়েছে।</p>
            <a href="change_action_password.php" class="btn-action btn-success" style="display: block; text-align: center; text-decoration: none;"><i class="fas fa-check"></i> সম্পূর্ণ হয়েছে</a>
        <?php endif; ?>
        <?php if ($currentProcessStep != 4): ?>
            <a href="?cancel_reset=true" class="back-link"><i class="fas fa-times"></i> বাতিল করুন ও ফিরে যান</a>
        <?php endif; ?>
    </div>
    <?php if ($alertMessage !== ""): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({ icon: '<?= $alertType ?>', title: '<?= $alertType === "success" ? "সফল!" : "ত্রুটি!" ?>', text: '<?= $alertMessage ?>', confirmButtonColor: '<?= $alertType === "success" ? "#10b981" : "#ef4444" ?>' });
            });
        </script>
    <?php endif; ?>
</body>
</html>