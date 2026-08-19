<?php
/**
 * config/config.php
 * ─────────────────────────────────────────
 * কেন্দ্রীয় কনফিগারেশন — path, upload limit, timezone।
 * এই ফাইলটি webroot-এর বাইরে রাখাই ভালো, বা .htaccess দিয়ে ব্লক করুন।
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Dhaka');

// ── বেস পাথ ──────────────────────────────────────────
define('DPS_ROOT', dirname(__DIR__));                         // .../dps_mvc
define('DPS_UPLOAD_DIR', DPS_ROOT . '/uploads/accounts');     // ফিজিক্যাল ফোল্ডার

// পাবলিক আপলোড URL — এখন request path থেকে স্বয়ংক্রিয়ভাবে বের করা হয়, হার্ডকোড করা
// দরকার নেই। এটাই একাউন্ট প্রোফাইল ছবি "দেখা যাচ্ছে না" সমস্যার মূল কারণ ছিল: আগে
// '/Accounts/Dps/uploads/accounts' হার্ডকোড করা ছিল, যেটা প্রকৃত সার্ভার পাথের সাথে না
// মিললে ছবির URL ভুল হয়ে যেত এবং onerror দিয়ে চুপচাপ placeholder-এ ফলব্যাক হতো।
if (!empty($_SERVER['SCRIPT_NAME'])) {
    $moduleBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('DPS_UPLOAD_URL', $moduleBase . '/uploads/accounts');
} else {
    // CLI/cron কনটেক্সটে SCRIPT_NAME থাকে না — ছবি URL এখানে ব্যবহৃতও হয় না, তাই ফলব্যাক যথেষ্ট।
    define('DPS_UPLOAD_URL', '/uploads/accounts');
}

define('DPS_LOG_DIR', DPS_ROOT . '/../../../Logs');            // webroot-এর বাইরে

// ── আপলোড নীতিমালা (ছবি) ────────────────────────────
define('DPS_MAX_UPLOAD_BYTES', 10 * 1024 * 1024);   // ১০ এমবি হার্ড লিমিট
define('DPS_MAX_IMAGE_DIMENSION', 1280);             // resize করার পর সর্বোচ্চ প্রস্থ/উচ্চতা (px)
define('DPS_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp']);

// ── ব্র্যান্ডিং (dashboard.php থেকে সরানো) ───────────
return [
    'banner_title' => 'ডিপিএস ও এফডিআর',
    'banner_sub'   => 'সাদা কালো এন্টারপ্রাইজ',
    'banner_logo'  => 'sada_kalo_fashion.png',
    'banner_image' => 'sada_kalo_fashion_banner.jpg',
    'banner_link'  => '',
];
