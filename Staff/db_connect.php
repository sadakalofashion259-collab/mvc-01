<?php
declare(strict_types=1);

/**
 * Staff/db_connect.php — Common Database Connection + AuthKernel Enforcement
 * ────────────────────────────────────────────────────────────────────────────
 * Staff/-এর প্রতিটি পেজ, AJAX এন্ডপয়েন্ট ও সার্ভিস রুট এই একটি ফাইল include
 * করে — তাই পুরো মডিউল এক জায়গা থেকেই AuthKernel দিয়ে অথেন্টিকেটেড হয়।
 * Suppliers / Customer / Sales মডিউলের সাথে সম্পূর্ণ একই সিকিউরিটি প্যাটার্ন।
 *
 * ধাপ ০ (প্রি-চেক): নেস্টেড Modules/<X>/ ফোল্ডার থেকে মূল db_connect.php-এর
 *   relative রিডাইরেক্ট (Location: index.php) ৪০৪/লুপ তৈরি করত। তাই টাইমআউট
 *   হলে মূল ফাইল চলার আগেই এখানে absolute /index.php-এ পাঠানো হয়।
 *
 * ধাপ ১: মূল public_html/db_connect.php লোড হয় (অপরিবর্তিত) —
 *   - PDO $conn কানেকশন (.env ভল্ট থেকে)
 *   - লিগ্যাসি সেশন/টাইমআউট/SessionGuard/ব্লক স্ট্যাক
 *
 * ধাপ ২: Core/AuthKernel::enforce($conn) — ইউনিফাইড সিকিউরিটি গার্ড:
 *   - ২০-মিনিট idle টাইমআউট (last_action_time + last_activity সিঙ্ক)
 *   - Single Active Session (users.active_session_token যাচাই)
 *   - users.last_active হার্টবিট
 *   - অ্যাকাউন্ট ব্লক চেক (নন-অ্যাডমিন → 403)
 *   সব রিডাইরেক্ট absolute — যেকোনো সাব-ফোল্ডার থেকে নিরাপদ।
 *
 * Staff/ এর যেকোনো নেস্টেড ফাইল থেকে ব্যবহার:
 *   include __DIR__ . '/../../db_connect.php';   // Modules/<X>/ থেকে
 *   include __DIR__ . '/db_connect.php';         // Staff/ রুট থেকে
 */

// ── ধাপ ০: idle-টাইমআউট প্রি-চেক (absolute redirect, লুপ/৪০৪ প্রতিরোধ) ──
if (session_status() === PHP_SESSION_ACTIVE
    && !empty($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {

    $staffLastSeen = max(
        (int)($_SESSION['last_action_time'] ?? 0),
        (int)($_SESSION['last_activity']    ?? 0)
    );

    if ($staffLastSeen > 0 && (time() - $staffLastSeen) >= 1200) {
        session_unset();
        session_destroy();
        header('Location: /index.php?status=timeout', true, 303);
        exit;
    }
}

// ── ধাপ ১: মূল কানেকশন + লিগ্যাসি অথ স্ট্যাক (মূল ফাইল অপরিবর্তিত) ──
require_once dirname(__DIR__) . '/db_connect.php';

// ── ধাপ ২: ইউনিফাইড AuthKernel এনফোর্সমেন্ট (লগইন করা সেশনের জন্য) ──
require_once dirname(__DIR__) . '/Core/AuthKernel.php';

if (isset($conn) && $conn instanceof \PDO
    && !empty($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    \AuthKernel::enforce($conn);
}
