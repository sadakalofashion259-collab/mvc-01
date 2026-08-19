<?php
/**
 * =====================================================================
 *  সাদা কালো ফ্যাশন — PWA UI Assets (pwa_assets.php)
 *  ---------------------------------------------------------------------
 *  সমস্ত PWA-সম্পর্কিত CSS, JS, Meta Tags একত্রে লোড করে।
 *  Manifest.json আগে থেকে <head>-এ থাকতে হবে।
 *
 *  ব্যবহার: আপনার মূল লেআউটের <head> এর ভেতরে একবার যোগ করুন:
 *
 *      <?php require $_SERVER['DOCUMENT_ROOT'] . '/Helpers/pwa_assets.php'; ?>
 *
 *  (গুরুত্বপূর্ণ: এর সাথে নতুন কোনো <link rel="manifest"> যোগ করবেন না —
 *   আপনার head-এ যেটা আছে সেটাই থাকবে।)
 * =====================================================================
 */

declare(strict_types=1);
?>
<!-- ===== সাদা কালো ফ্যাশন: PWA Meta ===== -->
<meta name="application-name" content="সাদাকালো ফ্যাশন">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Sadakalo">
<meta name="mobile-web-app-capable" content="yes">
<meta name="format-detection" content="telephone=no">

<!-- iOS Splash Screen / Startup Image -->
<link rel="apple-touch-startup-image" href="/assets/icon/android/launchericon-512x512.logo.png" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3)">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/icon/android/launchericon-192x192.logo.png">
<link rel="apple-touch-icon" sizes="152x152" href="/assets/icon/android/launchericon-144x144.logo.png">
<link rel="apple-touch-icon" sizes="120x120" href="/assets/icon/android/launchericon-96x96.logo.png">

<!-- Windows / IE pinned icon -->
<meta name="msapplication-TileColor" content="#000000">
<meta name="msapplication-TileImage" content="/assets/icon/android/launchericon-144x144.logo.png">

<!-- ===== PWA CSS + JS ===== -->
<link rel="stylesheet" href="/assets/css/pwa.css">
<script src="/assets/js/pwa-app.js" defer></script>
<!-- ===== PWA UI শেষ ===== -->
