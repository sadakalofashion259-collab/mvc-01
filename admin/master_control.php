<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') { header("Location: ../index.php"); exit; }
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>মাস্টার হাব | SADA KALO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 min-h-screen">
    <div class="bg-blue-900 text-white p-5 flex justify-between items-center shadow-lg">
        <h1 class="font-bold uppercase tracking-widest"><i class="fas fa-shield-alt"></i> মাস্টার হাব</h1>
        <a href="../dashboard.php" class="bg-white/20 px-4 py-2 rounded-lg font-bold">হোম</a>
    </div>
    <div class="max-w-4xl mx-auto p-6 grid grid-cols-2 md:grid-cols-3 gap-6 mt-10">
        <a href="manage_users.php" class="bg-white p-8 rounded-2xl shadow-xl text-center border-t-4 border-blue-500 hover:scale-105 transition">
            <i class="fas fa-users-cog text-4xl text-blue-500 mb-4"></i>
            <h3 class="font-bold">ইউজার কন্ট্রোল</h3>
        </a>
        <a href="admin_panel.php" class="bg-white p-8 rounded-2xl shadow-xl text-center border-t-4 border-emerald-500 hover:scale-105 transition">
            <i class="fas fa-user-plus text-4xl text-emerald-500 mb-4"></i>
            <h3 class="font-bold">নতুন ইউজার</h3>
        </a>
        <a href="https://sadakalohisabsystem.com/auth/otp_auth.php" class="bg-white p-8 rounded-2xl shadow-xl text-center border-t-4 border-amber-500 hover:scale-105 transition">
            <i class="fas fa-key text-4xl text-amber-500 mb-4"></i>
            <h3 class="font-bold">অ্যাকশন পাসওয়ার্ড</h3>
        </a>
    </div>
</body>
</html>
