<?php
/**
 * index.php  (module root এন্ট্রি — "public" ফোল্ডার আর নেই)
 * ─────────────────────────────────────────
 * এন্ট্রি পয়েন্ট। আপনার আসল db_connect.php + AuthKernel দিয়ে লগইন/সেশন/ব্লক
 * এনফোর্স করা হয়, তারপর dashboard view render হয়।
 *
 * ফোল্ডার ধরে নেওয়া হয়েছে: public_html/Accounts/Dps/index.php
 * তাই public_html রুট = এই ফাইল থেকে ২ ধাপ উপরে (../../ )
 */
declare(strict_types=1);

ob_start();

// ── নিরাপত্তা হেডার — session_start()-এর আগে ──
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');
header(
    // দ্রষ্টব্য: এই অ্যাপ পুরোটাই onclick="..." / onerror="..." ইনলাইন অ্যাট্রিবিউট হ্যান্ডলারের উপর
    // নির্ভর করে (একাউন্ট কার্ড, এডিট/ডিলিট বাটন, ছবি ফলব্যাক ইত্যাদি)। CSP nonce শুধু <script>
    // ট্যাগ কভার করে, ইনলাইন অ্যাট্রিবিউট নয় — তাই script-src-এ 'unsafe-inline' রাখা আবশ্যক,
    // নাহলে পুরো অ্যাপের সব বাটন ব্রাউজারে নীরবে ব্লক হয়ে যাবে। ভবিষ্যতে সব onclick/onerror
    // addEventListener()-এ রিফ্যাক্টর করলে তখন 'unsafe-inline' সরিয়ে nonce-only করা যাবে।
    "Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net; " .
    "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; " .
    "img-src 'self' data: blob:; " .
    "connect-src 'self'; " .
    "base-uri 'self'; " .
    "form-action 'self'; " .
    "frame-ancestors 'self';"
);

// ★ public_html-এর প্রকৃত রুট — অন্য কোথাও বসালে এই একটা লাইন বদলালেই যথেষ্ট
$PUBLIC_HTML_ROOT = realpath(__DIR__ . '/../../');

// ── আসল db_connect.php: সেশন এখানেই secure flags সহ শুরু হয়, ভল্ট (.env) থেকে
//    DB কানেক্ট হয়, এবং রুট-লেভেল idle-timeout/ব্লক লজিক চলে ──
require_once $PUBLIC_HTML_ROOT . '/db_connect.php'; // এই ফাইলটি $conn (PDO) সেট করে

$branding = require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/autoload.php';

// বেসিক লগইন গার্ড — লগইন না থাকলে সরাসরি লগইন পেজে
SecurityHelper::requireLogin('/index.php');

// ★ মডিউল-লেভেল এনফোর্সমেন্ট (single-active-session, ব্লক-চেক, idle-timeout) —
//   root db_connect.php-এর নিজস্ব চেক module সাব-ফোল্ডারে সঠিকভাবে কাজ করে না
//   বলেই AuthKernel ব্যবহার করা হয় (docblock অনুযায়ী)।
require_once $PUBLIC_HTML_ROOT . '/Core/AuthKernel.php';
AuthKernel::enforce($conn);

Database::useConnection($conn);
$csrfToken = SecurityHelper::issueCsrfToken();

// দৈনিক অটো-মুনাফা cron (duplicate-safe) — production-এ crontab-এ সরানো ভালো
(new DashboardController($conn))->runDailyInterestCron();

ob_end_flush();
require __DIR__ . '/views/dashboard.php';

