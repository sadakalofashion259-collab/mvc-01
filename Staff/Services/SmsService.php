<?php
/**
 * Staff/Services/SmsService.php — SMS Notification Service (Unified)
 *
 * লিগ্যাসি Services/staff_attend_sms.php + Services/staff_expens_sms.php
 * এর সব ফাংশন হুবহু সংরক্ষিত:
 *   - sendSMS()                → জেনেরিক SMS প্রেরণ (Expense-এ ব্যবহৃত)
 *   - sendExpenseSMS()         → খরচের SMS (শর্টকাট ফরম্যাট)
 *   - sendAttendanceSMS_Core() → Attendance SMS কোর
 *   - sendAttendanceSMS()      → হাজিরার SMS (শর্টকাট ফরম্যাট)
 *
 * API কনফিগ পরিবর্তন করতে → Staff/Core/Config.php
 * সব এরর লগ → Staff/Logs/error_log.txt
 */

require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/Logger.php';


// =============================================
// SMS পাঠানোর মূল ফাংশন (Expense/জেনেরিক)
// =============================================
if (!function_exists('sendSMS')) {
    function sendSMS(string $mobileNumber, string $message): array
    {
        // ✅ অ্যাডমিন টগল চেক — SMS বন্ধ থাকলে কিছুই পাঠাবে না
        if (!staff_sms_enabled()) {
            staff_log('SMS', "Skipped (SMS notifications OFF) | Mobile: {$mobileNumber}");
            return ['success' => false, 'skipped' => true, 'error' => 'SMS notifications disabled'];
        }

        // ✅ .env কনফিগ চেক — SMS কী অনুপস্থিত থাকলে নিরাপদে বন্ধ
        if (!staff_sms_configured()) {
            staff_log('SMS', "Skipped (SMS credentials missing in .env: S_USERNAME/SMS_APIKEY/SMS_SENDER/SMS_API_URL) | Mobile: {$mobileNumber}");
            return ['success' => false, 'skipped' => true, 'error' => 'SMS credentials not configured'];
        }

        $mobile = preg_replace('/\D/', '', $mobileNumber);

        // নম্বর ফরম্যাট ঠিক করা
        if (strlen($mobile) === 11 && str_starts_with($mobile, '0')) {
            $mobile = '88' . $mobile;
        } elseif (strlen($mobile) === 10) {
            $mobile = '880' . $mobile;
        }

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
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            logSmsError("SMS cURL Error: {$curlError} | Mobile: {$mobile}");
            return ['success' => false, 'error' => $curlError];
        }

        $decoded = json_decode($response, true);
        if ($httpCode !== 200 || empty($decoded)) {
            logSmsError("SMS API Error: HTTP {$httpCode} | Response: {$response} | Mobile: {$mobile}");
            return ['success' => false, 'error' => "HTTP {$httpCode}: {$response}"];
        }

        return ['success' => true, 'response' => $decoded];
    }
}


// =============================================
// Expense SMS পাঠানোর ফাংশন
// (শুধু এই ফাংশনটা কল করলেই SMS যাবে)
// =============================================
if (!function_exists('sendExpenseSMS')) {
    function sendExpenseSMS(
        string $staffPhone,
        string $expenseDate,   // Y-m-d ফরম্যাটে পাঠাও
        float  $amount,
        int    $staffId = 0    // শুধু লগের জন্য
    ): bool {

        if (empty($staffPhone)) {
            return false;
        }

        // =======================================
        // SMS ম্যাসেজ ফরম্যাট — শর্টকাট
        // শুধু কত টাকা নিল সেটাই যাবে
        // =======================================
        $formattedDate   = date('d/m/y', strtotime($expenseDate));
        $formattedAmount = number_format($amount, 2);

        $message  = "SADA KALO FASHION\n";
        $message .= "{$formattedDate}\n";
        $message .= "Payment Tk.{$formattedAmount}";
        // =======================================

        $result = sendSMS($staffPhone, $message);

        if (!$result['success']) {
            if (empty($result['skipped'])) {
                logSmsError("Expense SMS failed for Staff ID {$staffId}: " . ($result['error'] ?? 'Unknown'));
            }
            return false;
        }

        return true;
    }
}


// =============================================
// Attendance SMS পাঠানোর কোর ফাংশন
// =============================================
if (!function_exists('sendAttendanceSMS_Core')) {
    function sendAttendanceSMS_Core(string $mobileNumber, string $message): array
    {
        // ✅ অ্যাডমিন টগল চেক — SMS বন্ধ থাকলে কিছুই পাঠাবে না
        if (!staff_sms_enabled()) {
            staff_log('ATTEND-SMS', "Skipped (SMS notifications OFF) | Mobile: {$mobileNumber}");
            return ['success' => false, 'skipped' => true, 'error' => 'SMS notifications disabled'];
        }

        // ✅ .env কনফিগ চেক — SMS কী অনুপস্থিত থাকলে নিরাপদে বন্ধ
        if (!staff_sms_configured()) {
            staff_log('ATTEND-SMS', "Skipped (SMS credentials missing in .env) | Mobile: {$mobileNumber}");
            return ['success' => false, 'skipped' => true, 'error' => 'SMS credentials not configured'];
        }

        $mobile = preg_replace('/\D/', '', $mobileNumber);

        if (strlen($mobile) === 11 && str_starts_with($mobile, '0')) {
            $mobile = '88' . $mobile;
        } elseif (strlen($mobile) === 10) {
            $mobile = '880' . $mobile;
        }

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
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            logAttendSmsError("cURL Error: {$curlError} | Mobile: {$mobile}");
            return ['success' => false, 'error' => $curlError];
        }

        $decoded = json_decode($response, true);
        if ($httpCode !== 200 || empty($decoded)) {
            logAttendSmsError("API Error: HTTP {$httpCode} | Response: {$response} | Mobile: {$mobile}");
            return ['success' => false, 'error' => "HTTP {$httpCode}: {$response}"];
        }

        return ['success' => true, 'response' => $decoded];
    }
}


// =============================================
// Attendance SMS পাঠানোর ফাংশন
// (শুধু এই ফাংশনটা call করলেই SMS যাবে)
// =============================================
if (!function_exists('sendAttendanceSMS')) {
    function sendAttendanceSMS(
        string $staffPhone,
        string $status,
        int    $lateMinutes = 0,
        int    $staffId     = 0   // শুধু লগের জন্য
    ): bool {

        if (empty($staffPhone)) {
            return false;
        }

        // =======================================
        // SMS ম্যাসেজ ফরম্যাট — শর্টকাট
        // শুধু এই ব্লকটা বদলালেই হবে পরবর্তীতে
        // =======================================
        $statusMap = [
            'Present' => 'Present',
            'Absent'  => 'Absent',
            'Half'    => 'Half Day',
            'Leave'   => 'Leave',
        ];
        $smsStatus     = $statusMap[$status] ?? $status;
        $formattedDate = date('d/m/y');

        $message  = "SADA KALO FASHION\n";
        $message .= "{$formattedDate}\n";
        $message .= "Status:{$smsStatus}";
        if ($lateMinutes > 0) {
            $message .= "\nLate:{$lateMinutes}Min";
        }
        // =======================================

        $result = sendAttendanceSMS_Core($staffPhone, $message);

        if (!$result['success']) {
            if (empty($result['skipped'])) {
                logAttendSmsError("SMS failed for Staff ID {$staffId}: " . ($result['error'] ?? 'Unknown'));
            }
            return false;
        }

        return true;
    }
}


// =============================================
// SMS এরর লগ ফাংশন (Staff/Logs/error_log.txt)
// =============================================
if (!function_exists('logSmsError')) {
    function logSmsError(string $message): void
    {
        staff_log('SMS', $message);
    }
}

if (!function_exists('logAttendSmsError')) {
    function logAttendSmsError(string $message): void
    {
        staff_log('ATTEND-SMS', $message);
    }
}
