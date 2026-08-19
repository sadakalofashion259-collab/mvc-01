<?php
declare(strict_types=1);

/**
 * ============================================================
 *  ডেটাবেজ কনফিগারেশন — Task/Config/database.php
 * ------------------------------------------------------------
 *  এই ফাইলটি Task মডিউলের জন্য ডেটাবেজ সংযোগ স্থাপন করে।
 *  আপনি মূল প্রজেক্টের db_connect.php ব্যবহার করতে পারেন অথবা
 *  নিচের কনফিগারেশন ব্যবহার করে সরাসরি সংযোগ করতে পারেন।
 * ============================================================
 * 
 * ব্যবহারবিধি:
 *   option 1: মূল প্রজেক্টের db_connect.php ইনক্লুড করুন
 *   option 2: নিচের $DB_CONFIG অ্যারে এডিট করে নিজস্ব কানেকশন ব্যবহার করুন
 * 
 * ডিফল্ট: মূল প্রজেক্টের db_connect.php ব্যবহার করবে
 */

// ──────────────────────────────────────────────────────────
// অপশন ১: মূল প্রজেক্টের db_connect.php ব্যবহার (প্রধান প্রজেক্টের সাথে চললে)
// ──────────────────────────────────────────────────────────
$useMainProjectDb = true; // false দিলে নিচের নিজস্ব কনফিগ ব্যবহার হবে

if ($useMainProjectDb) {
    // মূল db_connect.php থেকে $conn PDO অবজেক্ট পাওয়া যাবে
    $mainDbPath = __DIR__ . '/../../db_connect.php';
    if (file_exists($mainDbPath)) {
        require_once $mainDbPath;
        // $conn variable now available from db_connect.php
        if (!isset($conn) || !($conn instanceof PDO)) {
            die('Error: Main database connection failed.');
        }
        return $conn; // Return the PDO connection
    } else {
        die('Error: Main db_connect.php not found at: ' . $mainDbPath);
    }
}

// ──────────────────────────────────────────────────────────
// অপশন ২: নিজস্ব ডেটাবেজ কনফিগারেশন (স্ট্যান্ডঅ্যালোন মোড)
// ──────────────────────────────────────────────────────────

/**
 * আপনার ডেটাবেজ তথ্য এখানে দিন:
 */
$DB_CONFIG = [
    'host'     => 'localhost',
    'dbname'   => 'your_database_name',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset'  => 'utf8mb4',
];

try {
    $dsn = "mysql:host={$DB_CONFIG['host']};dbname={$DB_CONFIG['dbname']};charset={$DB_CONFIG['charset']}";
    $conn = new PDO($dsn, $DB_CONFIG['username'], $DB_CONFIG['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $conn;
} catch (PDOException $e) {
    // ইউজারকে জেনেরিক মেসেজ দেখান, ডিটেইল লগে রাখুন
    error_log("Task Module DB Error: " . $e->getMessage());
    die('ডেটাবেজ সংযোগ ব্যর্থ হয়েছে। অনুগ্রহ করে Config/database.php ফাইলটি চেক করুন।');
}
