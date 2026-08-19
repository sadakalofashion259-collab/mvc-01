<?php

declare(strict_types=1);

/**
 * ============================================================
 *  Sadakalo Loan Management — Front Controller
 *  /Accounts/Loan/ এর সব রিকোয়েস্ট এই ফাইল দিয়ে ঢোকে।
 * ============================================================
 */

define('APP_ROOT', __DIR__);

/*
 * বেস URL স্বয়ংক্রিয়ভাবে বের করা হয় — হার্ডকোড করা নেই। ফলে আপনি
 * ফোল্ডারটি /Accounts/Loan এ রাখুন, /loan এ রাখুন, নাকি সাব-ডোমেইনের
 * রুটে রাখুন — CSS, প্রিন্ট লিংক, নেভিগেশন সবকিছু নিজে থেকেই ঠিক পাথ পাবে।
 *
 * উদাহরণ: স্ক্রিপ্ট যদি /Accounts/Loan/index.php হয়, তাহলে
 * APP_BASE_URL = '/Accounts/Loan'। রুটে হলে খালি স্ট্রিং।
 */
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
define('APP_BASE_URL', $scriptDir === '/' ? '' : $scriptDir);

// এররগুলো লগে যায়, কখনো ব্রাউজারে ছাপা হয় না।
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/*
 * db_connect.php ফাইলটি `?>` দিয়ে শেষ হয়, ফলে তার পরের newline টুকু
 * আউটপুট হিসেবে বেরিয়ে যায়। বাফার চালু না থাকলে AuthKernel বা
 * BaseController এর header() রিডাইরেক্ট "headers already sent"
 * এররে ব্যর্থ হতো। তাই এখানে বাফারিং শুরু করা হচ্ছে।
 */
ob_start();

/*
 * Enums অবশ্যই helper.php এর আগে লোড হতে হবে — DueDate হেল্পার
 * LoanStatus ও LoanFrequency টাইপ হিসেবে ব্যবহার করে।
 */
require_once APP_ROOT . '/Core/Enums.php';
require_once APP_ROOT . '/Helpers/helper.php';

/*
 * এটি সাইটের আসল db_connect.php লোড করে — যা সেশন শুরু করে,
 * টাইমজোন সেট করে এবং .env ভল্ট থেকে $conn তৈরি করে।
 * এরপর AuthKernel::enforce() চালিয়ে single-session, idle timeout
 * ও অ্যাকাউন্ট ব্লক চেক প্রয়োগ করে।
 */
require_once APP_ROOT . '/Config/database.php';
Database::getConnection();

// db_connect.php টাইমজোন সেট করে; না করে থাকলে এখানে নিশ্চিত করা হয়।
if (!defined('TIMEZONE')) {
    date_default_timezone_set('Asia/Dhaka');
}

// সেশন এখানে ইতিমধ্যেই চালু (db_connect.php থেকে)। ব্যাকআপ হিসেবে:
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Security::ensureCsrfToken();

require_once APP_ROOT . '/Core/Router.php';
require_once APP_ROOT . '/Core/BaseModel.php';
require_once APP_ROOT . '/Core/BaseController.php';

// অপ্রত্যাশিত কোনো এরর যেন ইউজারকে না দেখায়।
set_exception_handler(static function (Throwable $e): void {
    Logger::error($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (ob_get_level() > 0) {
        ob_clean();
    }

    if (Request::isAjax()) {
        Response::json(['status' => 'error', 'message' => 'সাময়িক সমস্যা হয়েছে। আবার চেষ্টা করুন।'], 500);
    }

    http_response_code(500);
    echo 'দুঃখিত, একটি সমস্যা হয়েছে। পরে আবার চেষ্টা করুন।';
});

$router = new Router();

$router->register('loan',      'LoanController',      'dashboard');
$router->register('repayment', 'RepaymentController', 'index');
$router->register('report',    'ReportController',    'index');

$router->setDefaultRoute('loan', 'dashboard');
$router->dispatch((string)($_GET['url'] ?? ''));

if (ob_get_level() > 0) {
    ob_end_flush();
}
