<?php
/**
 * Staff/Core/Config.php — SMS/Email কনফিগারেশন (100% Dynamic .env)
 *
 * ⚠️ এই ফাইলে কোনো ক্রেডেনশিয়াল, API কী, সেন্ডার আইডি বা ইমেইল ঠিকানা
 *    hardcode করা নেই। সবকিছু রুট .env ভল্ট থেকে রানটাইমে লোড হয়।
 *
 * .env লোকেশন: /home/sadakalo/App/.env (root db_connect.php-এর VAULT_PATH)
 *
 * প্রত্যাশিত .env কী (হুবহু এই নামে):
 *
 *   [SMS Configurations]
 *   S_USERNAME="..."        → SMS API ইউজারনেম
 *   SMS_APIKEY="..."        → SMS API কী
 *   SMS_SENDER="..."        → সেন্ডার আইডি
 *   SMS_API_URL="..."       → API এন্ডপয়েন্ট URL
 *
 *   [Email Configurations]
 *   STAFF_ATTD_FROM="..."   → হাজিরার ইমেইলের From ঠিকানা
 *   PAY_SLIP_FROM="..."     → পে-স্লিপ/OTP ইমেইলের From ঠিকানা
 *   STAFF_EXPENS_FROM="..." → খরচের ইমেইলের From ঠিকানা
 *   ADMIN_Mail_TO="..."     → প্রতিটি ইমেইলের অ্যাডমিন কপি (Bcc) প্রাপক
 *
 * কোনো কী অনুপস্থিত থাকলে সংশ্লিষ্ট ফিচার নিরাপদে বন্ধ থাকে
 * (SMS পাঠানো হয় না + লগ হয়; ইমেইলে From/Bcc হেডার বাদ পড়ে)।
 *
 * SMS/Email ON-OFF টগল → Staff/Core/settings.json
 * (ড্যাশবোর্ডের Notification Settings প্যানেল থেকে অ্যাডমিন নিয়ন্ত্রণ করেন)
 */

// =============================================
// .env ভল্ট লোড করা
// =============================================
if (!defined('VAULT_PATH')) {
    define('VAULT_PATH', '/home/sadakalo/App/.env');
}

if (!function_exists('staff_env')) {
    /** .env থেকে একটি কী পড়া (না পেলে $default — ডিফল্ট: খালি স্ট্রিং) */
    function staff_env(string $key, string $default = ''): string
    {
        static $env = null;
        if ($env === null) {
            $env = [];
            if (is_readable(VAULT_PATH)) {
                $parsed = @parse_ini_file(VAULT_PATH, false, INI_SCANNER_RAW);
                if (is_array($parsed)) {
                    $env = $parsed;
                }
            }
        }
        $val = isset($env[$key]) ? trim((string)$env[$key], " \t\"'") : '';
        return $val !== '' ? $val : $default;
    }
}

// =============================================
// [SMS Configurations] — .env থেকে, কোনো hardcoded ফলব্যাক নেই
// (S_USERNAME প্রাইমারি; পুরনো SMS_USERNAME কী থাকলে সেটিও .env থেকেই পড়া হয়)
// =============================================
if (!defined('SMS_API_URL'))      define('SMS_API_URL',      staff_env('SMS_API_URL'));
if (!defined('SMS_API_USERNAME')) define('SMS_API_USERNAME', staff_env('S_USERNAME', staff_env('SMS_USERNAME')));
if (!defined('SMS_API_KEY'))      define('SMS_API_KEY',      staff_env('SMS_APIKEY'));
if (!defined('SMS_SENDER_NAME'))  define('SMS_SENDER_NAME',  staff_env('SMS_SENDER'));
if (!defined('SMS_TYPE'))         define('SMS_TYPE',         'T'); // T = Transactional (ধ্রুবক, ক্রেডেনশিয়াল নয়)

if (!function_exists('staff_sms_configured')) {
    /** SMS পাঠানোর জন্য প্রয়োজনীয় সব .env কী আছে কি না */
    function staff_sms_configured(): bool
    {
        return SMS_API_URL !== '' && SMS_API_USERNAME !== ''
            && SMS_API_KEY !== '' && SMS_SENDER_NAME !== '';
    }
}

// =============================================
// [Email Configurations] — .env থেকে, কোনো hardcoded ঠিকানা নেই
// =============================================
if (!defined('MAIL_ADMIN_TO'))          define('MAIL_ADMIN_TO',          staff_env('ADMIN_Mail_TO', staff_env('ADMIN_Mail')));
if (!defined('MAIL_ATTEND_FROM_ADDR'))  define('MAIL_ATTEND_FROM_ADDR',  staff_env('STAFF_ATTD_FROM'));
if (!defined('MAIL_PAYSLIP_FROM_ADDR')) define('MAIL_PAYSLIP_FROM_ADDR', staff_env('PAY_SLIP_FROM'));
if (!defined('MAIL_EXPENSE_FROM_ADDR')) define('MAIL_EXPENSE_FROM_ADDR', staff_env('STAFF_EXPENS_FROM'));

if (!function_exists('staff_mail_from_header')) {
    /**
     * "From: <display name> <address>\r\n" হেডার তৈরি —
     * .env-এ ঠিকানা না থাকলে/অবৈধ হলে খালি স্ট্রিং (সার্ভার ডিফল্ট ব্যবহৃত হবে)।
     */
    function staff_mail_from_header(string $displayName, string $address): string
    {
        $address = trim($address);
        if ($address === '' || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        return "From: {$displayName} <{$address}>\r\n";
    }
}

// প্রস্তুত From হেডার (ডিসপ্লে-নাম ব্র্যান্ডিং, ঠিকানা .env থেকে)
if (!defined('MAIL_FROM_ATTEND'))  define('MAIL_FROM_ATTEND',  staff_mail_from_header('SADA KALO Attendance Alert', MAIL_ATTEND_FROM_ADDR));
if (!defined('MAIL_FROM_EXPENSE')) define('MAIL_FROM_EXPENSE', staff_mail_from_header('SADA KALO HR', MAIL_EXPENSE_FROM_ADDR));
if (!defined('MAIL_FROM_PAYSLIP')) define('MAIL_FROM_PAYSLIP', staff_mail_from_header('SADA KALO Payroll', MAIL_PAYSLIP_FROM_ADDR));
if (!defined('MAIL_FROM_DEFAULT')) define('MAIL_FROM_DEFAULT', staff_mail_from_header('SADA KALO FASHION', MAIL_ADMIN_TO));

// =============================================
// সাধারণ সেটিং
// =============================================
if (!defined('STAFF_TIMEZONE'))        define('STAFF_TIMEZONE', 'Asia/Dhaka');
if (!defined('STAFF_SESSION_TIMEOUT')) define('STAFF_SESSION_TIMEOUT', 1200); // ২০ মিনিট
if (!defined('STAFF_SETTINGS_FILE'))   define('STAFF_SETTINGS_FILE', __DIR__ . '/settings.json');

// =============================================
// SMS / Email ON-OFF টগল (Staff/Core/settings.json)
// =============================================
if (!function_exists('staff_notification_settings')) {
    /** @return array{sms_enabled:bool,email_enabled:bool} */
    function staff_notification_settings(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $defaults = ['sms_enabled' => true, 'email_enabled' => true];
        if (is_readable(STAFF_SETTINGS_FILE)) {
            $data = json_decode((string)@file_get_contents(STAFF_SETTINGS_FILE), true);
            if (is_array($data)) {
                $defaults['sms_enabled']   = !empty($data['sms_enabled']);
                $defaults['email_enabled'] = !empty($data['email_enabled']);
            }
        }
        return $cache = $defaults;
    }
}

if (!function_exists('staff_sms_enabled')) {
    function staff_sms_enabled(): bool
    {
        return staff_notification_settings()['sms_enabled'];
    }
}

if (!function_exists('staff_email_enabled')) {
    function staff_email_enabled(): bool
    {
        return staff_notification_settings()['email_enabled'];
    }
}

if (!function_exists('staff_save_notification_settings')) {
    /** ড্যাশবোর্ড টগল থেকে সেটিং সেভ করা */
    function staff_save_notification_settings(bool $sms, bool $email, string $updatedBy = 'Admin'): bool
    {
        $payload = json_encode([
            'sms_enabled'   => $sms,
            'email_enabled' => $email,
            'updated_by'    => $updatedBy,
            'updated_at'    => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return @file_put_contents(STAFF_SETTINGS_FILE, $payload, LOCK_EX) !== false;
    }
}
