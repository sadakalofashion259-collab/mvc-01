<?php
/**
 * Staff/Services/EmailService.php — Email Notification Service (Unified)
 *
 * লিগ্যাসি Services/staff_attend_email.php + Services/staff_expens_email.php
 * এর সব ফাংশন হুবহু সংরক্ষিত:
 *   - sendAttendanceEmail() → হাজিরার বিস্তারিত HTML Email
 *   - sendExpenseEmail()    → খরচ/অ্যাডভান্সের বিস্তারিত HTML Email
 *
 * প্রেরক (From) পরিবর্তন করতে → Staff/Core/Config.php
 * সব এরর লগ → Staff/Logs/error_log.txt
 */

require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/Logger.php';


// =============================================
// Admin কপি (Bcc) হেডার তৈরি — .env: ADMIN_Mail_TO
// প্রতিটি ইমেইলের একটি কপি অ্যাডমিন ঠিকানায় যায়
// =============================================
if (!function_exists('staff_admin_bcc_header')) {
    /**
     * @param string $recipient মূল প্রাপক (ডুপ্লিকেট এড়াতে)
     */
    function staff_admin_bcc_header(string $recipient): string
    {
        $addr = trim(MAIL_ADMIN_TO);
        if ($addr === ''
            || !filter_var($addr, FILTER_VALIDATE_EMAIL)
            || strcasecmp($addr, $recipient) === 0) {
            return '';
        }
        return "Bcc: {$addr}\r\n";
    }
}


// =============================================
// Attendance Email পাঠানোর ফাংশন
// (শুধু এই ফাংশনটা call করলেই Email যাবে)
// =============================================
if (!function_exists('sendAttendanceEmail')) {
    function sendAttendanceEmail(
        string $staffEmail,
        string $staffName,
        string $status,
        int    $lateMinutes = 0,
        string $leaveNote   = '',
        int    $staffId     = 0   // শুধু লগের জন্য
    ): bool {

        // ✅ অ্যাডমিন টগল চেক — Email বন্ধ থাকলে কিছুই পাঠাবে না
        if (!staff_email_enabled()) {
            staff_log('ATTEND-EMAIL', "Skipped (Email notifications OFF) | Email: {$staffEmail}");
            return false;
        }

        if (empty($staffEmail) || !filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $formattedDate   = date('d/m/Y');
        $safeStaffName   = htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8');
        $safeStatus      = htmlspecialchars($status,    ENT_QUOTES, 'UTF-8');
        $safeLeaveNote   = htmlspecialchars($leaveNote, ENT_QUOTES, 'UTF-8');

        // স্ট্যাটাস অনুযায়ী রং
        $statusColor = '#10b981'; // Present = সবুজ
        if ($status === 'Absent') $statusColor = '#f43f5e';
        elseif ($status === 'Half') $statusColor = '#f97316';
        elseif ($status === 'Leave') $statusColor = '#0ea5e9';

        // লেট টাইম রো
        $lateRow = '';
        if ($lateMinutes > 0) {
            $lateRow = "
                                        <tr>
                                            <td style='padding:10px 0;color:#475569;font-size:14px;
                                                        border-top:1px solid #e2e8f0;font-weight:bold;'>Late Time</td>
                                            <td style='padding:10px 0;color:#f43f5e;text-align:right;
                                                        font-weight:bold;font-size:14px;
                                                        border-top:1px solid #e2e8f0;'>{$lateMinutes} Min</td>
                                        </tr>";
        }

        // নোট রো
        $noteRow = '';
        if (!empty($safeLeaveNote)) {
            $noteRow = "
                                        <tr>
                                            <td style='padding:10px 0;color:#475569;font-size:14px;
                                                        border-top:1px solid #e2e8f0;font-weight:bold;'>Remark/Note</td>
                                            <td style='padding:10px 0;color:#0ea5e9;text-align:right;
                                                        font-weight:bold;font-size:14px;
                                                        border-top:1px solid #e2e8f0;'>{$safeLeaveNote}</td>
                                        </tr>";
        }

        // =======================================
        // Email বিষয় ও HTML টেমপ্লেট
        // =======================================
        $subject = "Attendance Alert - SADA KALO FASHION";

        $body = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color:#f1f5f9;padding:30px 0;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0'
                               style='max-width:600px;width:100%;background:#ffffff;border-radius:12px;
                                      overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);'>

                            <!-- Header -->
                            <tr>
                                <td style='background-color:#0f172a;padding:28px 30px;text-align:center;'>
                                    <h1 style='margin:0;color:#10b981;font-size:22px;
                                               text-transform:uppercase;letter-spacing:2px;'>
                                        SADA KALO FASHION
                                    </h1>
                                    <p style='margin:6px 0 0;color:#94a3b8;font-size:12px;
                                              letter-spacing:1px;text-transform:uppercase;'>
                                        Daily Attendance Alert
                                    </p>
                                </td>
                            </tr>

                            <!-- Body -->
                            <tr>
                                <td style='padding:30px;'>
                                    <p style='color:#334155;font-size:16px;margin:0 0 6px;'>
                                        Hello, <strong>{$safeStaffName}</strong>
                                    </p>
                                    <p style='color:#64748b;font-size:14px;margin:0 0 24px;'>
                                        আপনার <b>{$formattedDate}</b> তারিখের হাজিরা রেকর্ড করা হয়েছে।
                                        নিচে বিস্তারিত তথ্য দেখুন:
                                    </p>

                                    <!-- Details Table -->
                                    <div style='background:#f8fafc;border:1px solid #e2e8f0;
                                                border-radius:8px;padding:15px 20px;'>
                                        <table width='100%' cellpadding='0' cellspacing='0'
                                               style='border-collapse:collapse;font-size:14px;'>
                                            <tr>
                                                <td style='padding:10px 0;color:#475569;font-weight:bold;'>Date</td>
                                                <td style='padding:10px 0;color:#334155;text-align:right;
                                                            font-weight:bold;'>{$formattedDate}</td>
                                            </tr>
                                            <tr>
                                                <td style='padding:10px 0;color:#475569;font-weight:bold;
                                                            border-top:1px solid #e2e8f0;'>Status</td>
                                                <td style='padding:10px 0;text-align:right;font-weight:bold;
                                                            color:{$statusColor};font-size:15px;
                                                            border-top:1px solid #e2e8f0;'>{$safeStatus}</td>
                                            </tr>
                                            {$lateRow}
                                            {$noteRow}
                                        </table>
                                    </div>

                                    <p style='margin-top:24px;font-size:11px;color:#94a3b8;text-align:center;'>
                                        This is an auto-generated email. Please do not reply.
                                    </p>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='background:#f8fafc;padding:18px 30px;
                                            text-align:center;border-top:1px solid #e2e8f0;'>
                                    <p style='margin:0;font-size:11px;color:#94a3b8;'>
                                        &copy; " . date('Y') . " SADA KALO FASHION &mdash; All rights reserved.
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
        // =======================================

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= MAIL_FROM_ATTEND;                      // From ঠিকানা (.env: STAFF_ATTD_FROM)
        $headers .= staff_admin_bcc_header($staffEmail);   // অ্যাডমিন কপি (.env: ADMIN_Mail_TO)
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        $sent = @mail($staffEmail, $subject, $body, $headers);

        if (!$sent) {
            logAttendEmailError("Email failed for Staff ID {$staffId} | Email: {$staffEmail}");
            return false;
        }

        return true;
    }
}


// =============================================
// Expense Email পাঠানোর ফাংশন
// =============================================
if (!function_exists('sendExpenseEmail')) {
    function sendExpenseEmail(
        string $staffEmail,
        string $staffName,
        string $expenseType,
        string $expenseDate,    // Y-m-d ফরম্যাটে পাঠাও
        float  $amount,
        string $details   = '',
        string $entryBy   = 'Admin',
        int    $staffId   = 0   // শুধু লগের জন্য
    ): bool {

        // ✅ অ্যাডমিন টগল চেক — Email বন্ধ থাকলে কিছুই পাঠাবে না
        if (!staff_email_enabled()) {
            staff_log('EMAIL', "Skipped (Email notifications OFF) | Email: {$staffEmail}");
            return false;
        }

        if (empty($staffEmail) || !filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $formattedDate   = date('d/m/Y', strtotime($expenseDate));
        $formattedAmount = number_format($amount, 2);
        $safeStaffName   = htmlspecialchars($staffName,   ENT_QUOTES, 'UTF-8');
        $safeExpenseType = htmlspecialchars($expenseType, ENT_QUOTES, 'UTF-8');
        $safeDetails     = htmlspecialchars($details,     ENT_QUOTES, 'UTF-8');
        $safeEntryBy     = htmlspecialchars($entryBy,     ENT_QUOTES, 'UTF-8');

        $detailsRow = '';
        if (!empty($safeDetails)) {
            $detailsRow = "
            <tr>
                <td style='padding:10px 0;border-bottom:1px solid #e2e8f0;color:#475569;font-size:14px;'>Description</td>
                <td style='text-align:right;font-weight:bold;color:#334155;font-size:14px;'>{$safeDetails}</td>
            </tr>";
        }

        // =======================================
        // Email বিষয় ও HTML টেমপ্লেট
        // =======================================
        $subject = "Expense/Advance Alert - SADA KALO FASHION";

        $body = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color:#f1f5f9;padding:30px 0;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0'
                               style='max-width:600px;width:100%;background:#ffffff;border-radius:12px;
                                      overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);'>

                            <!-- Header -->
                            <tr>
                                <td style='background:linear-gradient(135deg,#ef4444,#b91c1c);
                                            padding:28px 30px;text-align:center;'>
                                    <h1 style='margin:0;color:#ffffff;font-size:22px;
                                               text-transform:uppercase;letter-spacing:2px;'>
                                        SADA KALO FASHION
                                    </h1>
                                    <p style='margin:6px 0 0;color:#fecaca;font-size:12px;
                                              letter-spacing:1px;text-transform:uppercase;'>
                                        Expense &amp; Advance Notification
                                    </p>
                                </td>
                            </tr>

                            <!-- Body -->
                            <tr>
                                <td style='padding:30px;'>
                                    <p style='color:#334155;font-size:16px;margin:0 0 6px;'>
                                        Hello, <strong>{$safeStaffName}</strong>
                                    </p>
                                    <p style='color:#64748b;font-size:14px;margin:0 0 24px;'>
                                        একটি খরচ / অ্যাডভান্স আপনার অ্যাকাউন্টে এন্ট্রি করা হয়েছে।
                                        নিচে বিস্তারিত তথ্য দেখুন:
                                    </p>

                                    <!-- Details Table -->
                                    <table width='100%' cellpadding='0' cellspacing='0'
                                           style='border-collapse:collapse;'>
                                        <tr>
                                            <td style='padding:10px 0;border-bottom:1px solid #e2e8f0;
                                                        color:#475569;font-size:14px;'>Date</td>
                                            <td style='text-align:right;font-weight:bold;color:#334155;
                                                        font-size:14px;'>{$formattedDate}</td>
                                        </tr>
                                        <tr>
                                            <td style='padding:10px 0;border-bottom:1px solid #e2e8f0;
                                                        color:#475569;font-size:14px;'>Type</td>
                                            <td style='text-align:right;font-weight:bold;
                                                        color:#ef4444;font-size:14px;'>{$safeExpenseType}</td>
                                        </tr>
                                        {$detailsRow}
                                        <tr>
                                            <td style='padding:10px 0;border-bottom:1px solid #e2e8f0;
                                                        color:#475569;font-size:14px;'>Amount</td>
                                            <td style='text-align:right;font-weight:900;
                                                        color:#ef4444;font-size:20px;'>
                                                Tk. {$formattedAmount}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding:10px 0;color:#94a3b8;font-size:13px;'>
                                                Entry By
                                            </td>
                                            <td style='text-align:right;color:#94a3b8;font-size:13px;'>
                                                {$safeEntryBy}
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Alert Box -->
                                    <div style='margin-top:24px;background:#fef2f2;border-left:4px solid #ef4444;
                                                border-radius:6px;padding:14px 18px;'>
                                        <p style='margin:0;color:#b91c1c;font-size:13px;font-weight:bold;'>
                                            &#9888;&#65039; যদি এই এন্ট্রি আপনি অনুমোদন না দিয়ে থাকেন,
                                            অনুগ্রহ করে অবিলম্বে HR-এর সাথে যোগাযোগ করুন।
                                        </p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='background:#f8fafc;padding:18px 30px;
                                            text-align:center;border-top:1px solid #e2e8f0;'>
                                    <p style='margin:0;font-size:11px;color:#94a3b8;'>
                                        This is an auto-generated email. Please do not reply.<br>
                                        &copy; " . date('Y') . " SADA KALO FASHION &mdash; All rights reserved.
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
        // =======================================

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= MAIL_FROM_EXPENSE;                     // From ঠিকানা (.env: STAFF_EXPENS_FROM)
        $headers .= staff_admin_bcc_header($staffEmail);   // অ্যাডমিন কপি (.env: ADMIN_Mail_TO)
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        $sent = @mail($staffEmail, $subject, $body, $headers);

        if (!$sent) {
            logEmailError("Email failed for Staff ID {$staffId} | Email: {$staffEmail}");
            return false;
        }

        return true;
    }
}


// =============================================
// Email এরর লগ ফাংশন (Staff/Logs/error_log.txt)
// =============================================
if (!function_exists('logAttendEmailError')) {
    function logAttendEmailError(string $message): void
    {
        staff_log('ATTEND-EMAIL', $message);
    }
}

if (!function_exists('logEmailError')) {
    function logEmailError(string $message): void
    {
        staff_log('EMAIL', $message);
    }
}
