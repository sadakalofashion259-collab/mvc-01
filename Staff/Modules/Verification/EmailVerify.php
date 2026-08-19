<?php
session_start();
include __DIR__ . '/../../db_connect.php';

// =============================================
// MiMSMS API কনফিগারেশন → Staff/Core/Config.php থেকে
// টগল ও Admin Bcc হেল্পার → Services থেকে
// =============================================
require_once __DIR__ . '/../../Core/Config.php';
require_once __DIR__ . '/../../Services/EmailService.php';

// =============================================
// SMS পাঠানোর ফাংশন
// =============================================
function sendOtpSMS(string $phone, string $otp, string $name): bool
{
    // ✅ অ্যাডমিন টগল চেক — SMS বন্ধ থাকলে কিছুই পাঠাবে না
    if (!staff_sms_enabled()) return false;
    // ✅ .env কনফিগ চেক — SMS কী অনুপস্থিত থাকলে নিরাপদে বন্ধ
    if (!staff_sms_configured()) return false;

    $mobile = preg_replace('/\D/', '', $phone);
    if (strlen($mobile) === 11 && str_starts_with($mobile, '0')) {
        $mobile = '88' . $mobile;
    } elseif (strlen($mobile) === 10) {
        $mobile = '880' . $mobile;
    }
    if (strlen($mobile) < 13) return false;

    $message = "SADA KALO FASHION\nProfile Verification OTP\nName: {$name}\nOTP: {$otp}\nDo not share this code.";

    $payload = json_encode([
        'UserName'        => SMS_API_USERNAME,
        'Apikey'          => SMS_API_KEY,
        'MobileNumber'    => $mobile,
        'SenderName'      => SMS_SENDER_NAME,
        'TransactionType' => SMS_TYPE,
        'CampaignId'      => 'null',
        'Message'         => $message,
    ]);

    $ch = curl_init(SMS_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: bearer',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) return false;
    return true;
}

// =============================================
// মেইন লজিক
// =============================================
$token       = isset($_GET['token']) ? $_GET['token'] : '';
$is_verified = false;
$error_msg   = "";
$success_msg = "";

// ১. টোকেন চেক করা
$stmt = $conn->prepare("SELECT * FROM staff_info WHERE verify_token = ? AND email_verified = 0");
$stmt->execute([$token]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff && !isset($_POST['verify_now'])) {
    echo "<script>alert('এই লিংকটি মেয়াদোত্তীর্ণ অথবা আপনার প্রোফাইল আগেই ভেরিফাই করা হয়েছে!'); window.location.href='../../../index.php';</script>";
    exit;
}

// ২. OTP পাঠানো — Email + SMS
if (isset($_POST['send_otp'])) {
    $otp = rand(100000, 999999);

    $upd = $conn->prepare("UPDATE staff_info SET otp_code = ? WHERE id = ?");
    $upd->execute([$otp, $staff['id']]);

    // Email
    $to      = $staff['staff_email'];
    $subject = "Your Verification OTP - SADA KALO FASHION";
    $headers  = MAIL_FROM_DEFAULT;                        // From ঠিকানা (.env: ADMIN_Mail_TO)
    $headers .= staff_admin_bcc_header((string)$to);      // অ্যাডমিন কপি (.env: ADMIN_Mail_TO)
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $emailMsg = "
    <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; text-align: center;'>
        <h2 style='color: #10b981;'>Profile Verification</h2>
        <p>Dear <b>{$staff['staff_name']}</b>,</p>
        <p>আপনার প্রোফাইল ভেরিফাই করার জন্য নিচের OTP কোডটি ব্যবহার করুন:</p>
        <h1 style='background: #0f172a; color: #fff; padding: 15px; border-radius: 8px; letter-spacing: 5px;'>{$otp}</h1>
        <p style='color: #777; font-size: 12px;'>এই কোডটি কারো সাথে শেয়ার করবেন না।</p>
    </div>";

    // ✅ অ্যাডমিন টগল চেক — Email বন্ধ থাকলে কিছুই পাঠাবে না
    if (staff_email_enabled()) {
        @mail($to, $subject, $emailMsg, $headers);
    }

    // SMS
    $smsSent = false;
    if (!empty($staff['staff_phone'])) {
        $smsSent = sendOtpSMS($staff['staff_phone'], (string)$otp, $staff['staff_name']);
    }

    $channel = ($emailSent && $smsSent) ? 'ইমেইল ও মোবাইলে'
             : ($smsSent  ? 'মোবাইলে'
             : 'ইমেইলে');

    $otp_sent    = true;
    $success_msg = "আপনার {$channel} ৬ ডিজিটের OTP পাঠানো হয়েছে!";
}

// ৩. OTP চেক করে ভেরিফাই করা
if (isset($_POST['verify_now'])) {
    $user_otp = trim($_POST['user_otp']);

    $stmt2 = $conn->prepare("SELECT * FROM staff_info WHERE verify_token = ?");
    $stmt2->execute([$token]);
    $current_staff = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($current_staff && $current_staff['otp_code'] == $user_otp) {
        $upd = $conn->prepare("UPDATE staff_info SET email_verified = 1, verify_token = NULL, otp_code = NULL WHERE id = ?");
        $upd->execute([$current_staff['id']]);
        $is_verified = true;
    } else {
        $error_msg = "❌ ভুল OTP কোড! অনুগ্রহ করে সঠিক কোড দিন।";
        $otp_sent  = true;
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Verification - SADA KALO FASHION</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 15px; }
        .verification-card { background: #1e293b; padding: 30px; border-radius: 20px; width: 100%; max-width: 450px; text-align: center; border: 1px solid #334155; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .brand { color: #10b981; font-size: 20px; font-weight: 900; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #334155; padding-bottom: 15px; }
        .profile-pic { width: 120px; height: 120px; border-radius: 50%; border: 4px solid #0ea5e9; margin: 0 auto 15px; object-fit: cover; background: #020617; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #334155; }
        .staff-name { margin: 0 0 5px 0; font-size: 22px; color: #fff; font-weight: 800; }
        .staff-info { color: #94a3b8; font-size: 14px; margin: 5px 0; font-weight: 600; }
        .info-box { background: #020617; border: 1px solid #334155; padding: 15px; border-radius: 10px; margin: 20px 0; text-align: left; }
        .info-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 13px; color: #cbd5e1; }
        .info-row i { color: #0ea5e9; width: 20px; text-align: center; }
        .info-row:last-child { margin-bottom: 0; }
        .otp-note { background: rgba(14,165,233,0.08); border: 1px solid #0ea5e9; border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #7dd3fc; margin-bottom: 5px; text-align: left; }
        .otp-note i { margin-right: 6px; }
        input { width: 100%; padding: 15px; margin-top: 15px; border-radius: 10px; border: 2px solid #334155; background: #020617; color: #fff; font-size: 18px; text-align: center; font-weight: bold; outline: none; letter-spacing: 3px; transition: 0.3s; }
        input:focus { border-color: #10b981; box-shadow: 0 0 10px rgba(16,185,129,0.2); }
        .btn { display: block; width: 100%; padding: 15px; background: linear-gradient(135deg, #0ea5e9, #0284c7); color: #fff; border: none; border-radius: 10px; font-weight: 900; font-size: 15px; cursor: pointer; margin-top: 20px; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }
        .btn:hover { box-shadow: 0 8px 20px rgba(14,165,233,0.4); transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-success:hover { box-shadow: 0 8px 20px rgba(16,185,129,0.4); }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; font-weight: bold; }
        .alert-error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid #ef4444; }
        .alert-success { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid #10b981; }
        .verified-icon { font-size: 70px; color: #10b981; margin-bottom: 15px; text-shadow: 0 0 20px rgba(16,185,129,0.5); }
    </style>
</head>
<body>
<div class="verification-card">
    <div class="brand"><i class="fas fa-user-check"></i> SADA KALO HR</div>

    <?php if ($is_verified): ?>
        <i class="fas fa-check-circle verified-icon"></i>
        <h2 style="color:#fff;margin-bottom:10px;">Verification Complete!</h2>
        <p style="color:#94a3b8;font-size:14px;margin-bottom:25px;">আপনার প্রোফাইলটি সফলভাবে ভেরিফাই করা হয়েছে এবং ব্লু-টিক যুক্ত করা হয়েছে।</p>
        <a href="https://www.sadakalohisabsystem.com" class="btn btn-success"><i class="fas fa-home"></i> হোমপেজে যান</a>

    <?php else: ?>
        <?php if ($error_msg): ?><div class="alert alert-error"><?php echo $error_msg; ?></div><?php endif; ?>
        <?php if ($success_msg): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>

        <?php
            $pic = (!empty($staff['profile_pic']) && $staff['profile_pic'] !== 'default.png')
                ? "../../Uploads/profiles/" . $staff['profile_pic'] : "";
        ?>
        <?php if ($pic): ?>
            <img src="<?php echo $pic; ?>" class="profile-pic" alt="Staff Profile">
        <?php else: ?>
            <div class="profile-pic"><i class="fas fa-user"></i></div>
        <?php endif; ?>

        <h3 class="staff-name"><?php echo htmlspecialchars($staff['staff_name'] ?? ''); ?></h3>
        <p class="staff-info">Staff Account Verification</p>

        <div class="info-box">
            <div class="info-row"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['staff_email'] ?? ''); ?></div>
            <div class="info-row"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($staff['staff_phone'] ?? ''); ?></div>
            <div class="info-row"><i class="fas fa-calendar-check"></i> Joining: <?php echo htmlspecialchars($staff['staff_join_date'] ?? ''); ?></div>
        </div>

        <p style="font-size:12px;color:#cbd5e1;margin-bottom:10px;">এই তথ্যগুলো যদি আপনার হয়, তবে ভেরিফাই করতে নিচের বাটনে ক্লিক করুন।</p>

        <form method="POST">
            <?php if (!isset($otp_sent)): ?>
                <button type="submit" name="send_otp" class="btn">
                    <i class="fas fa-paper-plane"></i> Send OTP (Email + SMS)
                </button>
            <?php else: ?>
                <div class="otp-note">
                    <i class="fas fa-info-circle"></i>
                    OTP আপনার <b>Email</b> এবং <b>মোবাইল নম্বরে</b> পাঠানো হয়েছে।
                </div>
                <input type="text" name="user_otp" placeholder="6-digit OTP লিখুন" required maxlength="6" autocomplete="off">
                <button type="submit" name="verify_now" class="btn btn-success">
                    <i class="fas fa-shield-alt"></i> Verify Now
                </button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
