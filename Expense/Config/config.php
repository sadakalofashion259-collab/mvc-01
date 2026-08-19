<?php
declare(strict_types=1);

/**
 * Expense Module – Configuration
 * ─────────────────────────────────────────────────────────────
 * অবস্থান: Expense/Config/config.php
 * কাজ: মডিউলের সব কনফিগ সেটিংস (ডাটাবেস নয়, অ্যাপ্লিকেশন লেভেল)
 *
 * নোট: SESSION_TIMEOUT রুট db_connect.php-এ আগে থেকেই defined —
 * তাই এখানে আর define করা হয় না (double-define warning এড়াতে)।
 */

// ===== নিরাপত্তা (লগইন বাধ্যতামূলক) =====
if (!defined('ENFORCE_LOGIN')) {
    define('ENFORCE_LOGIN', true);          // true = লগইন ছাড়া কিছুই দেখাবে না
}
if (!defined('ALLOW_GUEST_ENTRY')) {
    define('ALLOW_GUEST_ENTRY', false);     // অতিথি এন্ট্রি সম্পূর্ণ বন্ধ
}
if (!defined('DEFAULT_USERNAME')) {
    define('DEFAULT_USERNAME', 'অতিথি');   // (শুধু ENFORCE_LOGIN = false হলে কাজ করবে)
}

// ===== অন্যান্য সেটিংস =====
// SESSION_TIMEOUT → শুধু db_connect.php (গ্লোবাল) — এখানে নয়
if (!defined('MAX_AMOUNT')) {
    define('MAX_AMOUNT', 99999999);         // সর্বোচ্চ টাকার পরিমাণ
}
if (!defined('MAX_NOTE_LENGTH')) {
    define('MAX_NOTE_LENGTH', 500);         // নোটের সর্বোচ্চ ক্যারেক্টার
}
if (!defined('IMAGE_MAX_WIDTH')) {
    define('IMAGE_MAX_WIDTH', 800);         // ছবির সর্বোচ্চ প্রস্থ
}
if (!defined('IMAGE_QUALITY')) {
    define('IMAGE_QUALITY', 85);            // ছবির কোয়ালিটি (JPEG)
}

return true;
