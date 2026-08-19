<?php
/**
 * cron_dps_interest.php
 * ─────────────────────────────────────────
 * প্রতিদিন একবার সক্রিয় একাউন্টে মুনাফা যোগ করার জন্য।
 * এটা লগইন/ব্রাউজার ছাড়াই cPanel-এর "Cron Job" থেকে সরাসরি চলে।
 * বারবার চললেও সমস্যা নেই — daily_cron_log টেবিল দিয়ে duplicate-safe।
 */
declare(strict_types=1);

// ★ নিজের একটা গোপন কোড বসান (নিচের 'change-this-secret-key' বদলে দিন),
//   যাতে অন্য কেউ URL অনুমান করে বারবার হিট করতে না পারে।
const CRON_SECRET = 'change-this-secret-key';

$suppliedKey = (string)($_GET['key'] ?? '');
if (!hash_equals(CRON_SECRET, $suppliedKey)) {
    http_response_code(403);
    exit('Forbidden');
}

$PUBLIC_HTML_ROOT = realpath(__DIR__ . '/../../');

require_once $PUBLIC_HTML_ROOT . '/db_connect.php'; // $conn সেট করে
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/autoload.php';

try {
    (new DashboardController($conn))->runDailyInterestCron();
    echo "OK: " . date('Y-m-d H:i:s');
} catch (Throwable $e) {
    SecurityHelper::logError('CRON_STANDALONE', $e);
    http_response_code(500);
    echo "FAILED";
}
