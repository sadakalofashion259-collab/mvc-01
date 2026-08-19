<?php
declare(strict_types=1);

// ── Production Error Handling ─────────────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors',     '1');

session_start();

// ── Auth Guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/auto_block_engine.php';

// ── Lazy Cron: run auto-block engine on every admin page load ─────────────────
runAutoBlockEngine($conn);

// ── Auto-blocked user count (for badge) ──────────────────────────────────────
$autoBlockedCount = 0;
try {
    $autoBlockedCount = (int) $conn->query(
        "SELECT COUNT(*) FROM `users` WHERE `auto_blocked` = 1"
    )->fetchColumn();
} catch (Throwable) { /* table may not exist yet */ }

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>মাস্টার হাব | SADA KALO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Hind Siliguri', sans-serif; }
        .hub-card { transition: transform .18s ease, box-shadow .18s ease; }
        .hub-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.12); }
    </style>
</head>
<body class="bg-slate-100 min-h-screen pb-16">

    <!-- Header -->
    <div class="bg-blue-900 text-white p-5 flex justify-between items-center shadow-lg border-b-4 border-blue-500">
        <h1 class="font-black text-lg uppercase tracking-widest flex items-center gap-3">
            <i class="fas fa-shield-halved text-blue-300"></i> মাস্টার হাব
        </h1>
        <a href="../dashboard.php"
           class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg font-bold text-sm transition flex items-center gap-2">
            <i class="fas fa-home"></i> হোম
        </a>
    </div>

    <!-- Admin name -->
    <p class="text-center text-slate-500 text-sm font-semibold mt-6">
        স্বাগতম, <strong class="text-blue-800"><?= e($_SESSION['username'] ?? 'Admin') ?></strong>
    </p>

    <!-- Hub cards -->
    <div class="max-w-3xl mx-auto p-6 grid grid-cols-2 md:grid-cols-4 gap-5 mt-4">

        <!-- ইউজার কন্ট্রোল -->
        <a href="manage_users.php"
           class="hub-card bg-white p-7 rounded-2xl shadow-md text-center border-t-4 border-blue-500 block">
            <i class="fas fa-users-cog text-4xl text-blue-500 mb-3 block"></i>
            <h3 class="font-bold text-slate-700 text-sm">ইউজার কন্ট্রোল</h3>
            <p class="text-[10px] text-slate-400 mt-1 font-medium">ব্লক / আনব্লক</p>
        </a>

        <!-- নতুন ইউজার -->
        <a href="admin_panel.php"
           class="hub-card bg-white p-7 rounded-2xl shadow-md text-center border-t-4 border-emerald-500 block">
            <i class="fas fa-user-plus text-4xl text-emerald-500 mb-3 block"></i>
            <h3 class="font-bold text-slate-700 text-sm">নতুন ইউজার</h3>
            <p class="text-[10px] text-slate-400 mt-1 font-medium">যোগ / রোল পরিবর্তন</p>
        </a>

        <!-- অটো-ব্লক সেটিংস -->
        <a href="auto_block_settings.php"
           class="hub-card relative bg-white p-7 rounded-2xl shadow-md text-center border-t-4 border-red-500 block">
            <?php if ($autoBlockedCount > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full ring-2 ring-white">
                    <?= $autoBlockedCount ?>
                </span>
            <?php endif; ?>
            <i class="fas fa-clock text-4xl text-red-500 mb-3 block <?= $autoBlockedCount > 0 ? 'animate-pulse' : '' ?>"></i>
            <h3 class="font-bold text-slate-700 text-sm">অটো-ব্লক</h3>
            <p class="text-[10px] text-slate-400 mt-1 font-medium">শিডিউল সেটিংস</p>
        </a>

        <!-- অ্যাকশন পাসওয়ার্ড (OTP) -->
        <a href="https://sadakalohisabsystem.com/auth/otp_auth.php"
           target="_blank" rel="noopener noreferrer"
           class="hub-card bg-white p-7 rounded-2xl shadow-md text-center border-t-4 border-amber-500 block">
            <i class="fas fa-key text-4xl text-amber-500 mb-3 block"></i>
            <h3 class="font-bold text-slate-700 text-sm">অ্যাকশন পাসওয়ার্ড</h3>
            <p class="text-[10px] text-slate-400 mt-1 font-medium">OTP ভেরিফিকেশন</p>
        </a>

    </div>

    <!-- Auto-block live alert -->
    <?php if ($autoBlockedCount > 0): ?>
    <div class="max-w-3xl mx-auto px-6">
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4 flex items-center gap-4">
            <i class="fas fa-circle-exclamation text-red-500 text-xl flex-shrink-0 animate-pulse"></i>
            <div>
                <p class="font-bold text-red-700 text-sm">অটো-ব্লক সক্রিয়!</p>
                <p class="text-xs text-red-500 font-medium">
                    বর্তমানে <?= $autoBlockedCount ?> জন ইউজার শিডিউল অনুযায়ী ব্লকড আছেন।
                    <a href="auto_block_settings.php" class="underline font-bold">সেটিংস দেখুন</a>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
