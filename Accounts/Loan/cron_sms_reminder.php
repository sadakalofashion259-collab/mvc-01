<?php
/**
 * কিস্তি due date — অটো SMS রিমাইন্ডার (Cron)
 *
 * Crontab / cPanel Cron:
 *   /usr/bin/php /home/sadakalo/public_html/Accounts/Loan/cron_sms_reminder.php
 *
 * Terminal test:
 *   php /home/sadakalo/public_html/Accounts/Loan/cron_sms_reminder.php
 */

declare(strict_types=1);

// ---- Access control ----
// cPanel sometimes reports SAPI as cgi-fcgi even in Terminal.
$sapi = PHP_SAPI;
$isCli = in_array($sapi, ['cli', 'cli-server', 'phpdbg'], true)
    || (defined('STDIN') && is_resource(STDIN))
    || (empty($_SERVER['REQUEST_METHOD']) && empty($_SERVER['HTTP_HOST']));

$cronSecret = getenv('SMS_CRON_SECRET') ?: 'sadakalo_sms_cron_2026';
$givenKey   = (string)($_GET['key'] ?? '');

if (!$isCli) {
    if ($givenKey === '' || !hash_equals($cronSecret, $givenKey)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit(1);
    }
}

define('APP_ROOT', __DIR__);

$helper = APP_ROOT . '/Helpers/helper.php';
$sms    = APP_ROOT . '/Helpers/SmsService.php';

if (!is_file($helper) || !is_file($sms)) {
    fwrite(STDERR, "Missing helper or SmsService under " . APP_ROOT . "\n");
    exit(1);
}

require_once $helper;
require_once $sms;

if (is_file(APP_ROOT . '/Core/Enums.php')) {
    require_once APP_ROOT . '/Core/Enums.php';
}

$conn = null;
$dbCandidates = [
    dirname(APP_ROOT, 2) . '/db_connect.php',
    '/home/sadakalo/public_html/db_connect.php',
    APP_ROOT . '/../../db_connect.php',
    APP_ROOT . '/../db_connect.php',
];

foreach ($dbCandidates as $dbFile) {
    if (!is_file($dbFile)) {
        continue;
    }
    try {
        require_once $dbFile;
    } catch (Throwable $e) {
        // ignore
    }
    if (isset($conn) && $conn instanceof PDO) {
        break;
    }
}

if (!isset($conn) || !($conn instanceof PDO)) {
    fwrite(STDERR, "DB \$conn not found. Check db_connect.php path.\n");
    exit(1);
}

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo '[' . date('Y-m-d H:i:s') . '] SMS reminder cron started (SAPI=' . $sapi . ")\n";

$sql = "SELECT id, borrower_name, mobile, account_number, installment_amount,
               current_balance, due_date, frequency
        FROM sys_loans
        WHERE status = 'active'
          AND due_date IS NOT NULL
          AND due_date <= CURDATE()
          AND mobile IS NOT NULL
          AND TRIM(mobile) != ''
        ORDER BY due_date ASC";

try {
    $stmt = $conn->query($sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    echo 'SQL error: ' . $e->getMessage() . "\n";
    echo "Hint: mobile column missing? Run migration_sms.sql\n";
    exit(1);
}

$sent = 0;
$fail = 0;

if (count($rows) === 0) {
    echo "No due/overdue loans with mobile. Nothing to send.\n";
}

foreach ($rows as $loan) {
    $label = ($loan['borrower_name'] ?? '?') . ' / ' . ($loan['mobile'] ?? '') . ' / due=' . ($loan['due_date'] ?? '');
    try {
        $r = SmsService::sendDueReminder($loan);
        if (!empty($r['success'])) {
            $sent++;
            echo "  OK   {$label}\n";
        } else {
            $fail++;
            echo '  FAIL ' . $label . ' — ' . ($r['error'] ?? 'unknown') . "\n";
        }
    } catch (Throwable $e) {
        $fail++;
        echo '  ERR  ' . $label . ' — ' . $e->getMessage() . "\n";
    }
    usleep(200000);
}

echo '[' . date('Y-m-d H:i:s') . "] Done. sent={$sent} failed={$fail} total=" . count($rows) . "\n";
exit($fail > 0 ? 1 : 0);
