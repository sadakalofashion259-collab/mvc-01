<?php
declare(strict_types=1);

// এটি সাব-ফোল্ডারের জন্য তৈরি করা ডাটাবেজ ব্রিজ ফাইল
// এটি স্বয়ংক্রিয়ভাবে মেইন (রুট) ফোল্ডার থেকে আসল db_connect.php ফাইলটিকে কানেক্ট করবে

// স্ট্রিক্ট টাইপ চেকিং: DOCUMENT_ROOT আছে কি না এবং সেটি স্ট্রিং কি না, তা ভ্যালিডেট করা হলো
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT']) 
    ? $_SERVER['DOCUMENT_ROOT'] 
    : '';

$mainDbFile = $docRoot . '/db_connect.php';

// যদি docRoot ফাঁকা না হয় এবং ফাইলটি থাকে, তবেই রিকোয়ার করবে
if ($docRoot !== '' && file_exists($mainDbFile)) {
    // মেইন ফাইলটি পাওয়া গেলে তার সাথে নিরবচ্ছিন্ন কানেকশন তৈরি করবে
    require_once $mainDbFile;
} else {
    // কোনো কারণে মেইন ফাইলটি ডিলিট বা মিসিং হয়ে গেলে এই এরর মেসেজটি দেখাবে
    die("সিস্টেম এরর: মেইন ফোল্ডারের আসল db_connect.php ফাইলটি খুঁজে পাওয়া যাচ্ছে না।");
}
?>