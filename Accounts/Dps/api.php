<?php
/**
 * api.php  (module root — "public" ফোল্ডার আর নেই)
 * ─────────────────────────────────────────
 * সব AJAX action এই একটি ফাইলে আসে। index.php-এর মতোই আসল db_connect.php +
 * AuthKernel দিয়ে সেশন/লগইন/ব্লক এনফোর্স করা হয়, তারপর Router action চালায়।
 */
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');

$PUBLIC_HTML_ROOT = realpath(__DIR__ . '/../../');

require_once $PUBLIC_HTML_ROOT . '/db_connect.php'; // $conn + secure session_start()

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/autoload.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['loggedin'])) {
    SecurityHelper::jsonError('সেশন শেষ হয়ে গেছে — পেজ রিফ্রেশ করে আবার লগইন করুন।', 401);
}

require_once $PUBLIC_HTML_ROOT . '/Core/AuthKernel.php';
AuthKernel::enforce($conn);

Database::useConnection($conn);
$csrfToken = SecurityHelper::issueCsrfToken();

if (!isset($_POST['action'])) {
    SecurityHelper::jsonError('action প্যারামিটার নেই।', 400);
}
$action = (string)$_POST['action'];

// ── read-only action (CSRF ছাড়া চলবে) ──
$readOnlyActions = [
    'fetch_dps_summary',
    'fetch_dps_accounts',
    'fetch_dps_ledger',
    'fetch_account_detail',
    'fetch_account_ledger',
    'fetch_active_dropdown',
    'fetch_due_soon',
    'fetch_monthly_report',
    'fetch_weekly_report',
];

if (!in_array($action, $readOnlyActions, true)) {
    if (empty($_POST['csrf_token']) || !SecurityHelper::verifyCsrf((string)$_POST['csrf_token'], $csrfToken)) {
        SecurityHelper::jsonError('Security Error — পেজ রিফ্রেশ করুন।', 403);
    }
}

// ── রাউট ম্যাপ ──
$router = new Router();
$router->map('fetch_dps_summary',      DashboardController::class, 'summary');
$router->map('fetch_dps_accounts',     AccountController::class,   'list');
$router->map('fetch_account_detail',   AccountController::class,   'detail');
$router->map('fetch_active_dropdown',  AccountController::class,   'dropdown');
$router->map('add_dps_account',        AccountController::class,   'create');
$router->map('edit_dps_account',       AccountController::class,   'updateInfo');
$router->map('upload_account_photo',   AccountController::class,   'uploadPhoto');
$router->map('toggle_dps_status',      AccountController::class,   'toggleStatus');
$router->map('update_next_deposit',    AccountController::class,   'updateNextDeposit');
$router->map('fetch_due_soon',         AccountController::class,   'dueSoon');

$router->map('add_dps_deposit',        LedgerController::class,    'deposit');
$router->map('add_dps_withdraw',       LedgerController::class,    'withdraw');
$router->map('edit_dps_ledger',        LedgerController::class,    'editEntry');
$router->map('delete_dps_ledger',      LedgerController::class,    'deleteEntry');
$router->map('fetch_account_ledger',   LedgerController::class,    'accountLedger');
$router->map('fetch_dps_ledger',       LedgerController::class,    'globalLedger');

$router->map('fetch_monthly_report',   ReportController::class,    'monthly');
$router->map('fetch_weekly_report',    ReportController::class,    'weekly');

try {
    $router->dispatch($action, $conn);
} catch (DpsUserException $ue) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    SecurityHelper::jsonError($ue->getMessage(), 422);
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    SecurityHelper::logError('API:' . $action, $e);
    SecurityHelper::jsonError('সিস্টেমে একটি সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।', 500);
}
