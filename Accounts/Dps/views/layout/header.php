<!DOCTYPE html>
<html lang="bn" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= SecurityHelper::safeOut($branding['banner_title']) ?> — সাদাকালো</title>

    <!-- সেভ করা থিম প্রেফারেন্স স্টাইলশিট লোড হওয়ার আগেই বসিয়ে দেওয়া হয়, যাতে ডার্ক মোডে
         পেজ রিফ্রেশ/নেভিগেশনের সময় এক মুহূর্তের জন্যও সাদা ফ্ল্যাশ (FOUC) না হয় -->
    <script>
        try {
            if (localStorage.getItem('dps_theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
            }
        } catch (e) { /* localStorage ব্লকড থাকলে চুপচাপ লাইট মোডেই থাকবে */ }
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">

    <?php $cssVer = @filemtime(DPS_ROOT . '/assets/css/dps-mvc.css') ?: time(); ?>
    <link rel="stylesheet" href="assets/css/dps-mvc.css?v=<?= (int)$cssVer ?>">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <!-- ভার্সন পিন করা হয়েছে (শুধু "@11" নয়) — যাতে CDN-এ ভবিষ্যতে নতুন রিলিজ চুপচাপ লোড না হয়ে যায় -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js"></script>
</head>
<body>
<div id="toastStack" class="toast-stack"></div>
