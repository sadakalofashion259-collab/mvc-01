<?php
/**
 * Shared layout header.
 * Variables available: $pageTitle, $csrfToken, $baseUrl, $userRole
 * Your existing colour scheme, fonts and JS assets are untouched.
 */
$pageTitle = $pageTitle ?? 'লোন ম্যানেজমেন্ট';

/* CSS/JS ক্যাশ-বাস্টিং: ফাইল বদলালে ব্রাউজার নতুন করে লোড করবে।
   আপলোডের পর পুরনো ক্যাশড স্টাইল আর দেখাবে না। */
$__assetDir = dirname(__DIR__, 2) . '/assets';
$__ver = static function (string $rel) use ($__assetDir): string {
    $p = $__assetDir . '/' . ltrim($rel, '/');
    $m = @filemtime($p);
    return $m ? ('?v=' . $m) : '';
};
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= Security::e($csrfToken) ?>">
    <meta name="base-url" content="<?= Security::e($baseUrl) ?>">
    <meta name="module-endpoint" content="<?= Security::e($moduleEndpoint ?? ($baseUrl . '/loan')) ?>">
    <title><?= Security::e($pageTitle) ?> — Sadakalo Enterprise</title>

    <!-- সব CSS/JS লোকাল — Loan/assets ফোল্ডারের ভিতরেই। ইন্টারনেট বা
         CDN ছাড়াই সম্পূর্ণ চলে। -->
    <link rel="stylesheet" href="<?= Security::e($baseUrl) ?>/assets/vendor/fonts.css">
    <link rel="stylesheet" href="<?= Security::e($baseUrl) ?>/assets/vendor/icons.css">
    <link rel="stylesheet" href="<?= Security::e($baseUrl) ?>/assets/vendor/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= Security::e($baseUrl) ?>/assets/loan-theme.css<?= $__ver('loan-theme.css') ?>">
    <link rel="stylesheet" href="<?= Security::e($baseUrl) ?>/assets/app-shell.css<?= $__ver('app-shell.css') ?>">

    <script src="<?= Security::e($baseUrl) ?>/assets/vendor/jquery.min.js"></script>
    <script src="<?= Security::e($baseUrl) ?>/assets/vendor/sweetalert2.min.js"></script>

    <script>
        // থিম টগল — আগে বাইরের sadakalo-app.js এ ছিল, এখন মডিউলের
        // ভিতরেই স্বয়ংসম্পূর্ণ। পছন্দ localStorage এ সংরক্ষিত থাকে।
        (function () {
            try {
                var saved = localStorage.getItem('loan_theme');
                if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
            } catch (e) { /* localStorage বন্ধ থাকলেও চলবে */ }
        })();
        function toggleTheme() {
            var root = document.documentElement;
            var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-bs-theme', next);
            try { localStorage.setItem('loan_theme', next); } catch (e) {}
            var icon = document.querySelector('[data-theme-icon]');
            if (icon) icon.className = (next === 'dark' ? 'fas fa-sun' : 'fas fa-moon');
        }
        document.addEventListener('DOMContentLoaded', function () {
            var icon = document.querySelector('[data-theme-icon]');
            if (icon && document.documentElement.getAttribute('data-bs-theme') === 'dark') {
                icon.className = 'fas fa-sun';
            }
        });
    </script>

</head>
<body>

<div id="toastStack" class="toast-stack" aria-live="polite"></div>

<header class="app-header">
    <div class="hdr-row">
        <div class="d-flex align-items-center gap-2">
            <a href="/" class="back-btn"><i class="fas fa-house"></i></a>
            <div class="brand-badge"><i class="fas fa-landmark"></i></div>
            <div>
                <div class="brand-title">Sadakalo Loan Panel</div>
                <div class="brand-sub">Central Control</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="icon-pill" onclick="toggleTheme()" title="Theme"><i data-theme-icon class="fas fa-moon"></i></div>
            <a href="/logout.php" class="icon-pill danger" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
    <nav class="module-nav">
        <a href="<?= Security::e($baseUrl) ?>/loan/dashboard"><i class="fas fa-landmark"></i> লোন</a>
        <a href="<?= Security::e($baseUrl) ?>/repayment"><i class="fas fa-hand-holding-dollar"></i> কিস্তি</a>
        <a href="<?= Security::e($baseUrl) ?>/report"><i class="fas fa-chart-column"></i> রিপোর্ট</a>
    </nav>
</header>

<div class="app-shell px-3">
