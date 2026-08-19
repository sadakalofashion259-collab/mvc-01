<?php
// সেশন চালু করা (যদি আপনার loader.php তে সেশন চালু করা না থাকে)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helpers/loader.php ফাইলটি যুক্ত করা হলো
$loader_path = $_SERVER['DOCUMENT_ROOT'] . '/Helpers/loader.php';
if (file_exists($loader_path)) {
    require_once($loader_path);
}

// লগইন চেক করার শর্ত (Condition)
// আপনার সিস্টেমে লগইন চেক করার জন্য যে Session ভেরিয়েবল ব্যবহার করা হয়, 'user_logged_in' এর জায়গায় সেটি বসাবেন।
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    // যদি লগইন করা না থাকে, তবে এলার্ট দেখাবে এবং মূল পেজে পাঠিয়ে দেবে
    echo '<!DOCTYPE html>
    <html lang="bn">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
    </head>
    <body style="background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh;">
        <div style="background-color: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center;">
            <h2 style="color: #d9534f;">অ্যাক্সেস সংরক্ষিত!</h2>
            <p style="color: #555;">এই ফোল্ডারে প্রবেশ করতে অনুগ্রহ করে আগে লগইন করুন।</p>
        </div>
        <script>
            alert("লগইন করার পর অ্যাক্সেস করুন");
            window.location.href = "/index.php";
        </script>
    </body>
    </html>';
    
    exit(); // যারা লগইন করেনি, তাদের জন্য কোড এখানেই থেমে যাবে
}

// যদি ব্যবহারকারী লগইন করা থাকে, তবে নিচের কোডগুলো কাজ করবে
// এবং loader.php-এর ফাংশনগুলো ঠিকঠাকমতো লোড হবে।

// আপনি চাইলে এখানে লগইন করা ইউজারদের জন্য কিছু দেখাতে পারেন, অথবা ফাঁকা রাখতে পারেন।
?>
