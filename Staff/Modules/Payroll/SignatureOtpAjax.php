<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require __DIR__ . '/../../db_connect.php';

// =============================================
// MiMSMS API কনফিগারেশন → Staff/Core/Config.php থেকে
// টগল ও Admin Bcc হেল্পার → Services থেকে
// =============================================
require_once __DIR__ . '/../../Core/Config.php';
require_once __DIR__ . '/../../Services/EmailService.php';

// =============================================
// এরর লগ ফাংশন
// =============================================
function logError(string $message): void
{
    $logDir  = __DIR__ . '/../../Logs';
    $logFile = $logDir . '/error_log.txt';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] OTP_ERROR: {$message}" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// =============================================
// SMS পাঠানোর ফাংশন
// =============================================
function sendOtpSMS(string $phone, string $otp, string $name): bool
{
    // ✅ অ্যাডমিন টগল চেক — SMS বন্ধ থাকলে কিছুই পাঠাবে না
    if (!staff_sms_enabled()) {
        logError("OTP SMS skipped (SMS notifications OFF) | Phone: {$phone}");
        return false;
    }
    // ✅ .env কনফিগ চেক — SMS কী অনুপস্থিত থাকলে নিরাপদে বন্ধ
    if (!staff_sms_configured()) {
        logError("OTP SMS skipped (SMS credentials missing in .env) | Phone: {$phone}");
        return false;
    }

    $mobile = preg_replace('/\D/', '', $phone);
    if (strlen($mobile) === 11 && str_starts_with($mobile, '0')) {
        $mobile = '88' . $mobile;
    } elseif (strlen($mobile) === 10) {
        $mobile = '880' . $mobile;
    }
    if (strlen($mobile) < 13) {
        logError("Invalid phone for OTP SMS: {$phone}");
        return false;
    }

    $message = "SADA KALO FASHION\nPayslip Signature OTP\nName: {$name}\nOTP: {$otp}\nDo not share this code.";

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

    if ($curlError) {
        logError("cURL Error: {$curlError} | Mobile: {$mobile}");
        return false;
    }
    if ($httpCode !== 200) {
        logError("API HTTP {$httpCode} | Response: {$response} | Mobile: {$mobile}");
        return false;
    }

    $decoded = json_decode($response, true);
    logError("SMS API Response for {$mobile}: " . json_encode($decoded)); // debug log
    return true;
}

// =============================================
// মেইন লজিক
// =============================================
$action   = $_POST['action']   ?? '';
$staff_id = $_POST['staff_id'] ?? '';

if (empty($staff_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Staff ID is missing.']);
    exit;
}

// স্টাফ তথ্য আনা (phone সহ)
$stmt = $conn->prepare("SELECT staff_name, staff_email, staff_phone, otp_code FROM staff_info WHERE id = ?");
$stmt->execute([$staff_id]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    echo json_encode(['status' => 'error', 'message' => 'Staff not found.']);
    exit;
}

// =============================================
// OTP পাঠানো — Email + SMS
// =============================================
if ($action === 'send_otp') {
    $otp = rand(100000, 999999);

    // DB তে সেভ
    $upd = $conn->prepare("UPDATE staff_info SET otp_code = ? WHERE id = ?");
    $upd->execute([$otp, $staff_id]);

    // Email পাঠানো
    $to      = $staff['staff_email'];
    $subject = "Digital Signature Verification - SADA KALO FASHION";
    $headers  = MAIL_FROM_PAYSLIP;                        // From ঠিকানা (.env: PAY_SLIP_FROM)
    $headers .= staff_admin_bcc_header((string)$to);      // অ্যাডমিন কপি (.env: ADMIN_Mail_TO)
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $emailMsg = "
    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; text-align: center;'>
        <h2 style='color: #0ea5e9;'>Payslip Settlement Signature</h2>
        <p>Dear <b>{$staff['staff_name']}</b>,</p>
        <p>Please use the following OTP to digitally sign your final settlement/payslip:</p>
        <h1 style='background: #0f172a; color: #fff; padding: 15px; border-radius: 8px; letter-spacing: 5px; font-size: 24px;'>{$otp}</h1>
        <p style='color: #ef4444; font-size: 12px;'>Do not share this code with anyone.</p>
    </div>";

    // ✅ অ্যাডমিন টগল চেক — Email বন্ধ থাকলে কিছুই পাঠাবে না
    $emailSent = false;
    if (staff_email_enabled()) {
        $emailSent = @mail($to, $subject, $emailMsg, $headers);
    } else {
        logError("OTP Email skipped (Email notifications OFF) | Email: {$to}");
    }

    // SMS পাঠানো
    $smsSent = false;
    if (!empty($staff['staff_phone'])) {
        $smsSent = sendOtpSMS($staff['staff_phone'], (string)$otp, $staff['staff_name']);
        if (!$smsSent) {
            logError("SMS OTP failed for Staff ID: {$staff_id} | Phone: {$staff['staff_phone']}");
        }
    } else {
        logError("No phone number for Staff ID: {$staff_id}");
    }

    // সবসময় success দেখাবে যদি DB save হয়
    $channel = ($emailSent && $smsSent) ? 'Email ও SMS-এ'
             : ($smsSent  ? 'SMS-এ'
             : ($emailSent ? 'Email-এ'
             : 'পাঠানো হয়েছে (SMS/Email check করুন)'));

    echo json_encode([
        'status'   => 'success',
        'message'  => "OTP {$channel} পাঠানো হয়েছে।",
        'sms_sent' => $smsSent,
        'email_sent' => $emailSent,
    ]);

// =============================================
// OTP ভেরিফাই করা
// =============================================
} elseif ($action === 'verify_otp') {
    $user_otp = trim($_POST['otp'] ?? '');

    if ($staff['otp_code'] == $user_otp && !empty($user_otp)) {
        $upd = $conn->prepare("UPDATE staff_info SET otp_code = NULL WHERE id = ?");
        $upd->execute([$staff_id]);
        echo json_encode(['status' => 'success', 'message' => 'Signature Verified!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid OTP Code.']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
}
?>
