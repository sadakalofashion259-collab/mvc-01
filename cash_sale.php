<?php
declare(strict_types=1);

require_once 'db_connect.php';
require_once __DIR__ . '/Controllers/DashboardController.php';

$dashboardCoreController = new DashboardController($conn);
$dashboardCoreController->handlePostRequests();
$viewDataArray = $dashboardCoreController->getViewData();

$next_memo    = $viewDataArray['nextMemoNumber'];
$customers    = $viewDataArray['activeCustomersList'];
$notif_badge  = $viewDataArray['notificationBadgeHtml'];
$role         = $viewDataArray['userRole'];
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>ক্যাশ বিক্রি — সাদাকালো ফ্যাশন</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563EB">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="Logo.png">

    <!-- Bootstrap 5.3.8 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;600;700;800&family=DM+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Canvas Confetti -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <style>
        /* ══════════════════════════════════════
           DESIGN TOKENS
        ══════════════════════════════════════ */
        :root {
            --p       : #2563EB;
            --p-d     : #1D4ED8;
            --p-l     : #DBEAFE;
            --accent  : #0EA5E9;
            --ok      : #059669;
            --ok-l    : #D1FAE5;
            --err     : #DC2626;
            --err-l   : #FEE2E2;
            --warn    : #D97706;
            --warn-l  : #FEF3C7;

            --bg      : #F1F5F9;
            --card    : #FFFFFF;
            --nav-bg  : #FFFFFF;
            --inp-bg  : #F8FAFC;
            --muted-bg: #F1F5F9;

            --tx1     : #0F172A;
            --tx2     : #475569;
            --tx3     : #94A3B8;

            --border  : #CBD5E1;
            --r-card  : 14px;
            --r-inp   : 10px;

            --sh-sm   : 0 1px 3px rgba(15,23,42,.08);
            --sh-md   : 0 4px 12px rgba(15,23,42,.10);
            --sh-lg   : 0 10px 30px rgba(15,23,42,.12);
            --sh-p    : 0 4px 14px rgba(37,99,235,.28);
            --sh-ok   : 0 4px 14px rgba(5,150,105,.28);

            --nav-h   : 62px;
            --bar-h   : 70px;
        }

        [data-bs-theme="dark"] {
            --bg      : #0F172A;
            --card    : #1E293B;
            --nav-bg  : #1E293B;
            --inp-bg  : #0F172A;
            --muted-bg: #1E293B;
            --tx1     : #F8FAFC;
            --tx2     : #CBD5E1;
            --tx3     : #64748B;
            --border  : #334155;
            --sh-sm   : 0 1px 3px rgba(0,0,0,.5);
            --sh-md   : 0 4px 12px rgba(0,0,0,.5);
            --sh-lg   : 0 10px 30px rgba(0,0,0,.6);
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust:100%; }
        body {
            font-family: 'DM Sans','Noto Sans Bengali',sans-serif;
            background: var(--bg);
            color: var(--tx1);
            margin: 0;
            overflow-x: hidden;
            padding-bottom: calc(var(--bar-h) + env(safe-area-inset-bottom) + 10px);
            -webkit-font-smoothing: antialiased;
            transition: background .3s, color .3s;
        }

        /* ── TICKER ── */
        @keyframes ticker { to { transform: translateX(-50%); } }
        @keyframes bgCycle {
            0%   { background: #2563EB; }
            33%  { background: #059669; }
            66%  { background: #0EA5E9; }
            100% { background: #2563EB; }
        }
        .ticker-strip { animation: bgCycle 12s infinite; height:26px; overflow:hidden; display:flex; align-items:center; }
        .ticker-inner {
            white-space:nowrap; display:inline-block;
            animation: ticker 22s linear infinite;
            color:#fff; font-size:11px; font-weight:700; letter-spacing:.3px;
        }

        /* ── NAVBAR ── */
        .app-nav {
            position:sticky; top:0; z-index:900;
            background:var(--nav-bg); border-bottom:1px solid var(--border);
            height:var(--nav-h); display:flex; align-items:center;
            padding:0 14px; gap:10px; box-shadow:var(--sh-sm);
            transition:background .3s, border-color .3s;
        }
        .nav-ico {
            width:38px; height:38px; border-radius:50%;
            border:none; background:var(--muted-bg);
            color:var(--tx2); font-size:15px;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; flex-shrink:0; text-decoration:none;
            transition:.2s;
        }
        .nav-ico:hover { background:var(--p-l); color:var(--p); }
        .brand-logo {
            width:36px; height:36px; border-radius:50%;
            border:2px solid var(--p-l); object-fit:cover; flex-shrink:0;
        }
        .brand-h1 {
            font-size:15px; font-weight:900; color:var(--p);
            margin:0; text-transform:uppercase; letter-spacing:.4px; line-height:1.2;
        }
        .brand-sub { font-size:10px; color:var(--tx3); margin:0; font-weight:700; letter-spacing:.4px; }

        /* ── DRAWER ── */
        .dr-overlay {
            position:fixed; inset:0;
            background:rgba(15,23,42,.55); backdrop-filter:blur(3px);
            z-index:1040; opacity:0; visibility:hidden; transition:.3s;
        }
        .dr-overlay.on { opacity:1; visibility:visible; }
        .drawer {
            position:fixed; top:0; left:-290px; width:272px; height:100%;
            background:var(--card); z-index:1050; overflow-y:auto;
            border-right:1px solid var(--border); box-shadow:var(--sh-lg);
            transition:left .35s cubic-bezier(.4,0,.2,1);
        }
        .drawer.on { left:0; }
        .dr-head {
            background:linear-gradient(135deg,var(--p),var(--accent));
            padding:26px 16px 18px; color:#fff; position:relative;
        }
        .dr-close {
            position:absolute; top:12px; right:12px;
            background:rgba(255,255,255,.2); border:none; color:#fff;
            width:30px; height:30px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; font-size:13px;
        }
        .dr-avatar { width:52px; height:52px; border-radius:50%; border:3px solid rgba(255,255,255,.4); object-fit:cover; margin-bottom:8px; }
        .dr-name { font-size:15px; font-weight:800; margin:0; }
        .dr-role { font-size:10px; opacity:.8; text-transform:uppercase; letter-spacing:.5px; }
        .dr-sec-lbl { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:var(--tx3); padding:12px 14px 4px; }
        .dr-item {
            display:flex; align-items:center; gap:12px;
            padding:11px 14px; color:var(--tx2); text-decoration:none;
            font-size:13px; font-weight:600; border-radius:10px;
            margin:2px 8px; transition:.2s; cursor:pointer;
        }
        .dr-item:hover, .dr-item.on { background:var(--p-l); color:var(--p); }
        .dr-item .ic { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
        .dr-theme {
            width:calc(100% - 16px); margin:8px; padding:11px;
            border-radius:10px; border:1.5px solid var(--border);
            background:var(--muted-bg); color:var(--tx1); font-weight:700;
            font-size:13px; cursor:pointer; font-family:inherit;
            display:flex; align-items:center; justify-content:center; gap:8px;
        }
        .dr-theme:hover { background:var(--p-l); border-color:var(--p); color:var(--p); }

        /* ── PAGE WRAP ── */
        .pw { max-width:540px; margin:0 auto; padding:12px 11px; }

        /* ── CLOCK CHIP ── */
        .clock-chip {
            display:flex; align-items:center; gap:7px;
            background:var(--card); border:1px solid var(--border);
            border-radius:20px; padding:6px 14px;
            font-size:12px; font-weight:800; color:var(--tx2);
            margin-bottom:12px; box-shadow:var(--sh-sm);
        }
        @keyframes blink { 0%,100%{opacity:1}50%{opacity:.2} }
        .live-dot { width:7px; height:7px; background:var(--ok); border-radius:50%; animation:blink 1.2s infinite; }

        /* ── STAT PILLS ── */
        .stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:12px; }
        .stat-pill {
            background:var(--card); border:1px solid var(--border);
            border-radius:12px; padding:10px 6px; text-align:center;
            box-shadow:var(--sh-sm);
        }
        .stat-pill .sv { font-size:18px; font-weight:900; line-height:1; margin-bottom:2px; }
        .stat-pill .sl { font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.4px; color:var(--tx3); }
        .sp-bill .sv { color:var(--p); }
        .sp-paid .sv { color:var(--ok); }
        .sp-due  .sv { color:var(--err); }

        /* ── SECTION CARD ── */
        .sec-card {
            background:var(--card); border:1px solid var(--border);
            border-radius:var(--r-card); box-shadow:var(--sh-sm);
            margin-bottom:12px; overflow:hidden;
            transition:background .3s, border-color .3s;
        }
        .sec-head {
            background:linear-gradient(90deg,var(--p),var(--accent));
            color:#fff; padding:12px 15px; font-size:13px;
            font-weight:800; text-transform:uppercase; letter-spacing:.5px;
            display:flex; align-items:center; gap:8px;
        }
        .sec-body { padding:12px 11px; }

        .divider {
            display:flex; align-items:center; gap:8px;
            color:var(--tx3); font-size:10px; font-weight:800;
            text-transform:uppercase; letter-spacing:.6px;
            margin:2px 0 10px;
        }
        .divider::before, .divider::after { content:''; flex:1; height:1px; background:var(--border); }

        /* ── SALE ROW CARD ── */
        @keyframes rowIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .row-card {
            background:var(--card); border:1.5px solid var(--border);
            border-radius:var(--r-card); padding:12px 10px 10px;
            margin-bottom:10px; position:relative; animation:rowIn .25s ease;
            transition:border-color .2s, box-shadow .2s, background .3s;
        }
        .row-card:focus-within { border-color:var(--p); box-shadow:0 0 0 3px rgba(37,99,235,.12); }

        .memo-badge {
            display:inline-flex; align-items:center; gap:5px;
            background:var(--p-l); color:var(--p);
            border-radius:20px; font-size:11px; font-weight:800;
            padding:3px 10px; margin-bottom:10px;
        }
        .del-btn {
            position:absolute; top:10px; right:10px;
            width:28px; height:28px; background:var(--err-l); color:var(--err);
            border:none; border-radius:8px; font-size:11px;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:.2s;
        }
        .del-btn:hover { background:var(--err); color:#fff; }

        /* ── INPUTS ── */
        .lbl {
            font-size:10px; font-weight:800; text-transform:uppercase;
            letter-spacing:.4px; color:var(--tx3); display:block; margin-bottom:3px;
        }
        .ai {
            width:100%; background:var(--inp-bg); color:var(--tx1);
            border:1.5px solid var(--border); border-radius:var(--r-inp);
            padding:9px 10px; font-size:14px; font-weight:700;
            font-family:inherit; text-align:center; outline:none;
            transition:border-color .2s, box-shadow .2s, background .3s;
            -webkit-appearance:none;
        }
        .ai::placeholder { color:var(--tx3); font-weight:500; font-size:12px; }
        .ai:focus { border-color:var(--p); box-shadow:0 0 0 3px rgba(37,99,235,.13); background:var(--card); }
        .ai.ro { background:var(--muted-bg)!important; border-style:dashed!important; color:var(--tx3)!important; cursor:not-allowed; }
        .ai.c-ok   { color:var(--ok)!important;  font-size:14px; font-weight:900; }
        .ai.c-err  { color:var(--err)!important; font-size:14px; font-weight:900; }
        .ai.c-p    { color:var(--p)!important;   font-size:14px; font-weight:900; }

        select.ai {
            cursor:pointer;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2394A3B8'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 9px center; background-size:15px;
            padding-right:30px;
        }
        .cname-box { display:none; margin-top:6px; }

        @keyframes shakeErr { 0%,100%{transform:translateX(0)} 25%,75%{transform:translateX(-5px)} 50%{transform:translateX(5px)} }
        .input-error { border-color:var(--err)!important; box-shadow:0 0 0 3px rgba(220,38,38,.14)!important; animation:shakeErr .4s ease; }

        .g3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:7px; }

        /* ══════════════════════════════════════
           DUE + CAMERA ROW  ← KEY CHANGE
        ══════════════════════════════════════ */
        .due-cam-row {
            display:grid;
            grid-template-columns: 1fr auto;   /* due takes leftover, cam is auto */
            gap:7px;
            align-items:flex-end;
            margin-top:7px;
        }
        .cam-btn {
            display:flex; align-items:center; justify-content:center; gap:5px;
            background:var(--p); color:#fff;
            border:none; border-radius:var(--r-inp);
            padding:9px 12px; font-size:12px; font-weight:800;
            cursor:pointer; white-space:nowrap; font-family:inherit;
            transition:background .2s, transform .1s;
            height:40px; /* match input height */
        }
        .cam-btn:active { transform:scale(.96); }
        .cam-btn.ok { background:var(--ok); }
        .cam-btn.input-error { border:2px solid var(--err)!important; background:var(--err); }

        /* photo thumb inside photo-area */
        .photo-area {
            display:flex; align-items:center; gap:7px;
            margin-top:6px;
            padding:6px 8px;
            background:var(--muted-bg); border-radius:10px;
            border:1.5px dashed var(--border);
        }
        .photo-thumb {
            width:38px; height:38px; border-radius:7px;
            object-fit:cover; display:none;
            border:2px solid var(--ok); flex-shrink:0;
        }

        /* ── ADD ROW ── */
        .add-btn {
            width:100%; background:transparent; color:var(--p);
            border:2px dashed var(--p); border-radius:12px;
            padding:12px; font-size:13px; font-weight:800;
            text-transform:uppercase; letter-spacing:.3px;
            cursor:pointer; display:flex; align-items:center; justify-content:center; gap:7px;
            margin-top:4px; transition:.2s; font-family:inherit;
        }
        .add-btn:hover { background:var(--p-l); }
        .add-btn:active { transform:scale(.98); }

        /* ── FLOATING CAMERA ── */
        #floatCam {
            position:fixed;
            bottom:calc(var(--bar-h) + 12px + env(safe-area-inset-bottom));
            right:13px; z-index:800;
            background:var(--card); border-radius:13px; padding:7px;
            cursor:move; width:120px; touch-action:none;
            box-shadow:var(--sh-lg); border:2px solid var(--p);
        }
        .cam-lbl { color:var(--p); font-size:10px; font-weight:800; text-align:center; margin-bottom:4px; pointer-events:none; text-transform:uppercase; }
        #video { width:100%; height:150px; object-fit:cover; border-radius:7px; display:block; pointer-events:none; border:1px solid var(--border); background:#000; }

        /* ── BOTTOM BAR ── */
        .btm-bar {
            position:fixed; bottom:0; left:0; right:0; z-index:700;
            background:var(--nav-bg); border-top:1px solid var(--border);
            padding:9px 14px; padding-bottom:calc(9px + env(safe-area-inset-bottom));
            display:flex; align-items:center; gap:11px;
            box-shadow:0 -4px 20px rgba(15,23,42,.08);
            transition:background .3s;
        }
        .prep-wrap { flex:1; min-width:0; }
        .prep-wrap label { font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--tx3); display:block; margin-bottom:2px; }
        .prep-inp {
            width:100%; background:var(--muted-bg); border:1.5px dashed var(--border);
            border-radius:8px; padding:7px 10px; font-size:12px; font-weight:700;
            color:var(--tx2); font-family:inherit; text-align:center; cursor:not-allowed; outline:none;
        }
        @keyframes pulseSave { 0%{box-shadow:var(--sh-ok),0 0 0 0 rgba(5,150,105,.5)} 70%{box-shadow:var(--sh-ok),0 0 0 10px rgba(5,150,105,0)} 100%{box-shadow:var(--sh-ok),0 0 0 0 rgba(5,150,105,0)} }
        .save-btn {
            background:linear-gradient(135deg,var(--ok),#047857);
            color:#fff; border:none; border-radius:12px;
            padding:12px 18px; font-size:13px; font-weight:800;
            text-transform:uppercase; letter-spacing:.4px; cursor:pointer;
            display:flex; align-items:center; gap:6px; white-space:nowrap;
            flex-shrink:0; font-family:inherit;
            animation:pulseSave 2s infinite;
        }
        .save-btn:active { transform:scale(.96); animation:none; }
        .save-btn:disabled { background:#94A3B8; cursor:not-allowed; animation:none; box-shadow:none; }

        /* ── SUCCESS ── */
        .success-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(15,23,42,.85); backdrop-filter:blur(10px);
            z-index:9999; align-items:center; justify-content:center;
        }
        .success-overlay.on { display:flex; }
        @keyframes scaleUp { from{transform:scale(.6);opacity:0} to{transform:scale(1);opacity:1} }
        .sbox {
            background:var(--card); border:2px solid var(--ok);
            border-radius:20px; padding:34px 28px; text-align:center;
            box-shadow:var(--sh-lg),0 0 60px rgba(5,150,105,.25);
            animation:scaleUp .35s cubic-bezier(.4,0,.2,1);
            max-width:290px; width:90%;
        }
        .sbox .check { width:68px; height:68px; background:var(--ok-l); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:30px; color:var(--ok); }
        .sbox h2 { font-size:20px; font-weight:900; margin:0 0 5px; }
        .sbox p  { font-size:13px; color:var(--tx2); margin:0; }

        ::-webkit-scrollbar { width:4px; }
        ::-webkit-scrollbar-thumb { background:var(--border); border-radius:2px; }
    </style>
</head>
<body>

<!-- TICKER -->
<div class="ticker-strip">
    <div class="ticker-inner">
        &nbsp;&nbsp;🌿 বিসমিল্লাহির রাহমানির রাহিম — পরম করুণাময় ও অসীম দয়ালু আল্লাহর নামে 🍃 &nbsp;&nbsp;
        🏪 সাদাকালো ফ্যাশন | ক্যাশ বিক্রি সিস্টেম &nbsp;&nbsp;
        🌿 بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ — সাদাকালো ফ্যাশন | ক্যাশ বিক্রি সিস্টেম 🏪 &nbsp;&nbsp;
        🌿 বিসমিল্লাহির রাহমানির রাহিম — পরম করুণাময় ও অসীম দয়ালু আল্লাহর নামে 🍃 &nbsp;&nbsp;
    </div>
</div>

<!-- NAVBAR -->
<nav class="app-nav">
    <button class="nav-ico" onclick="toggleDrawer()" aria-label="মেনু"><i class="fas fa-bars"></i></button>
    <a href="dashboard.php" style="text-decoration:none;display:flex;align-items:center;gap:9px;flex:1;min-width:0;">
        <img src="logo.png" class="brand-logo" alt="Logo" onerror="this.src='https://via.placeholder.com/36/2563EB/fff?text=SK'">
        <div>
            <h1 class="brand-h1">ক্যাশ বিক্রি</h1>
            <p class="brand-sub">সাদাকালো ফ্যাশন</p>
        </div>
    </a>
   
    <a href="dashboard.php" class="nav-ico" title="ড্যাশবোর্ড"><i class="fas fa-arrow-left"></i></a>
</nav>

<!-- DRAWER OVERLAY -->
<div class="dr-overlay" id="drOverlay" onclick="toggleDrawer()"></div>
<aside class="drawer" id="mainDrawer">
    <div class="dr-head">
        <button class="dr-close" onclick="toggleDrawer()"><i class="fas fa-times"></i></button>
        <img src="logo.png" class="dr-avatar" alt="Logo" onerror="this.src='https://via.placeholder.com/52/fff/2563EB?text=SK'">
        <p class="dr-name"><?php echo htmlspecialchars((string)($viewDataArray['userName'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="dr-role"><?php echo htmlspecialchars((string)($role ?? 'Staff'), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="dr-sec-lbl">মূল মেনু</div>
    <a href="dashboard.php"   class="dr-item"><div class="ic" style="background:#DBEAFE;color:#2563EB;"><i class="fas fa-home"></i></div> ড্যাশবোর্ড</a>
    <a href="cash_sale.php"   class="dr-item on"><div class="ic" style="background:#D1FAE5;color:#059669;"><i class="fas fa-shopping-cart"></i></div> ক্যাশ বিক্রি</a>
    <a href="reports.php"     class="dr-item"><div class="ic" style="background:#FEF3C7;color:#D97706;"><i class="fas fa-chart-bar"></i></div> রিপোর্ট</a>
    <a href="Customer/customers.php"   class="dr-item"><div class="ic" style="background:#EDE9FE;color:#7C3AED;"><i class="fas fa-users"></i></div> কাস্টমার</a>
    <div class="dr-sec-lbl">সেটিংস</div>
    <button class="dr-theme" id="themeBtn" onclick="toggleTheme()">
        <i class="fas fa-moon" id="themeIco"></i> <span id="themeTxt">ডার্ক মোড</span>
    </button>
</aside>

<!-- MAIN -->
<main class="pw">

    <!-- Clock -->
    <div class="clock-chip">
        <span class="live-dot"></span>
        <i class="fas fa-clock" style="color:var(--p);font-size:11px;"></i>
        <span id="lClock">--:--:-- --</span>
        <span style="color:var(--tx3);">|</span>
        <span id="lDate" style="font-size:10px;color:var(--tx3);"></span>
    </div>

    <?php if ($role !== 'viewer'): ?>

    <form id="mainForm" autocomplete="off">
        <!-- STATS -->
        <div class="stat-row">
            <div class="stat-pill sp-bill">
                <div class="sv" id="dBill">০</div>
                <div class="sl">মোট বিল ৳</div>
            </div>
            <div class="stat-pill sp-paid">
                <div class="sv" id="dPaid">০</div>
                <div class="sl">মোট জমা ৳</div>
            </div>
            <div class="stat-pill sp-due">
                <div class="sv" id="dDue">০</div>
                <div class="sl">মোট বাকি ৳</div>
            </div>
        </div>
        <input type="hidden" name="summary_sales_cash" id="gPaid">
        <input type="hidden" name="summary_sales_due"  id="gDue">
        <input type="hidden" id="gBill">
        <input type="hidden" id="gQty">

        <!-- SALE SECTION -->
        <div class="sec-card" id="saleSection" style="display:none;">
            <div class="sec-head"><i class="fas fa-shopping-bag"></i> ক্যাশ বিক্রি এন্ট্রি</div>
            <div class="sec-body">
                <div class="divider">বিক্রির তালিকা</div>
                <div id="salesBody"></div>
                <button type="button" class="add-btn" onclick="addSaleRow()">
                    <i class="fas fa-plus-circle"></i> আরও বিক্রি যোগ করুন
                </button>
            </div>
        </div>
    </form>

    <?php else: ?>
    <div style="background:var(--err-l);color:var(--err);border:1.5px solid var(--err);border-radius:14px;padding:24px;text-align:center;font-weight:700;">
        <i class="fas fa-eye" style="font-size:26px;display:block;margin-bottom:8px;"></i>
        ভিউয়ার মোড — এন্ট্রির অনুমতি নেই
    </div>
    <?php endif; ?>

</main>

<!-- FLOATING CAMERA -->
<div id="floatCam">
    <div class="cam-lbl"><i class="fas fa-camera"></i> ক্যামেরা</div>
    <video id="video" autoplay playsinline muted></video>
</div>
<canvas id="canvas" style="display:none;"></canvas>

<!-- BOTTOM BAR -->
<?php if ($role !== 'viewer'): ?>
<div class="btm-bar">
    <div class="prep-wrap">
        <label><i class="fas fa-user-edit" style="font-size:9px;"></i> এন্ট্রি বাই</label>
        <input class="prep-inp" type="text" name="prepared_by" readonly
               value="<?php echo htmlspecialchars((string)($viewDataArray['userName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <button type="submit" form="mainForm" class="save-btn" id="saveBtn">
        <i class="fas fa-save"></i> সেভ
    </button>
</div>
<?php endif; ?>

<!-- SUCCESS OVERLAY -->
<div class="success-overlay" id="successModal">
    <div class="sbox">
        <div class="check"><i class="fas fa-check"></i></div>
        <h2>সফল! 🎉</h2>
        <p>ডাটা সফলভাবে সেভ হয়েছে</p>
    </div>
</div>

<!-- Bootstrap 5.3.8 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── THEME ── */
(function(){
    const t = localStorage.getItem('skTheme')||'light';
    document.documentElement.setAttribute('data-bs-theme',t);
    if(t==='dark'){ const l=document.getElementById('themeTxt'); const i=document.getElementById('themeIco'); if(l)l.textContent='লাইট মোড'; if(i)i.className='fas fa-sun'; }
})();
function toggleTheme(){
    const h=document.documentElement;
    const d=h.getAttribute('data-bs-theme')==='dark';
    h.setAttribute('data-bs-theme',d?'light':'dark');
    localStorage.setItem('skTheme',d?'light':'dark');
    const l=document.getElementById('themeTxt'); const i=document.getElementById('themeIco');
    if(l)l.textContent=d?'ডার্ক মোড':'লাইট মোড';
    if(i)i.className=d?'fas fa-moon':'fas fa-sun';
}

/* ── DRAWER ── */
function toggleDrawer(){
    document.getElementById('mainDrawer').classList.toggle('on');
    document.getElementById('drOverlay').classList.toggle('on');
}

/* ── CLOCK ── */
(function tick(){
    const d=new Date(),h=d.getHours(),m=d.getMinutes(),s=d.getSeconds();
    const ap=h>=12?'PM':'AM',H=h%12||12,f=n=>String(n).padStart(2,'0');
    const cl=document.getElementById('lClock');
    if(cl)cl.textContent=`${f(H)}:${f(m)}:${f(s)} ${ap}`;
    const days=['রবি','সোম','মঙ্গল','বুধ','বৃহস্পতি','শুক্র','শনি'];
    const dl=document.getElementById('lDate');
    if(dl)dl.textContent=`${days[d.getDay()]}, ${d.getDate()}/${d.getMonth()+1}`;
    setTimeout(tick,1000);
})();

/* ── CAMERA ── */
const floatCam=document.getElementById('floatCam');
const vid=document.getElementById('video');
const cnv=document.getElementById('canvas');

async function startCam(){
    try{ vid.srcObject=await navigator.mediaDevices.getUserMedia({video:{facingMode:{exact:'environment'}}});}
    catch{ try{ vid.srcObject=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}); }catch(e){} }
}
window.addEventListener('load',startCam);

function capturePhoto(id){
    const MW=800; let tw=vid.videoWidth, th=vid.videoHeight;
    if(tw>MW){th=Math.round(th*MW/tw);tw=MW;}
    cnv.width=tw; cnv.height=th;
    cnv.getContext('2d').drawImage(vid,0,0,tw,th);
    const data=cnv.toDataURL('image/jpeg',.7);
    document.getElementById('sv'+id).value=data;
    const img=document.getElementById('si'+id);
    img.src=data; img.style.display='block';
    const btn=document.getElementById('sb'+id);
    btn.classList.add('ok'); btn.classList.remove('input-error');
    btn.innerHTML='<i class="fas fa-check"></i> ছবি ✓';
}

/* Draggable float cam */
let drag=false,sx,sy,il,it;
function dStart(e){ drag=true; const c=e.touches?e.touches[0]:e; sx=c.clientX; sy=c.clientY; const r=floatCam.getBoundingClientRect(); il=r.left; it=r.top; floatCam.style.bottom='auto'; floatCam.style.right='auto'; floatCam.style.margin='0'; }
function dMove(e){ if(!drag)return; e.preventDefault(); const c=e.touches?e.touches[0]:e; floatCam.style.left=(il+c.clientX-sx)+'px'; floatCam.style.top=(it+c.clientY-sy)+'px'; }
function dEnd(){ drag=false; }
floatCam.addEventListener('touchstart',dStart,{passive:false}); floatCam.addEventListener('mousedown',dStart);
window.addEventListener('touchmove',dMove,{passive:false}); window.addEventListener('mousemove',dMove);
window.addEventListener('touchend',dEnd); window.addEventListener('mouseup',dEnd);

/* ── SALE ROWS ── */
let baseMemo=<?php echo (int)$next_memo; ?>, rowCnt=0;

let custOpts=`<option value="">-- কাস্টমার বেছে নিন --</option>
<option value="custom" style="color:#2563EB;font-weight:700;">➕ কাস্টম নাম</option>`;
<?php
if(!empty($customers)){
    foreach($customers as $c){
        $nm = !empty($c['shop_name'])
            ? htmlspecialchars((string)$c['shop_name'],ENT_QUOTES,'UTF-8')
            : htmlspecialchars((string)$c['customer_name'],ENT_QUOTES,'UTF-8');
        $id = htmlspecialchars((string)$c['id'],ENT_QUOTES,'UTF-8');
        echo "custOpts+=`<option value='{$id}'>{$nm}</option>`;\n";
    }
}
?>

function updateMemos(){
    let i=0;
    document.querySelectorAll('.mn').forEach(inp=>{ inp.value=baseMemo+(i++); });
    document.querySelectorAll('.mb-num').forEach((sp,j)=>{ sp.textContent=baseMemo+j; });
}

function addSaleRow(){
    var __sec=document.getElementById('saleSection'); if(__sec) __sec.style.display='';
    rowCnt++;
    const mv=baseMemo+document.querySelectorAll('.mn').length;
    const div=document.createElement('div');
    div.className='row-card'; div.id='sr'+rowCnt;
    div.innerHTML=`
        <div class="memo-badge"><i class="fas fa-receipt" style="font-size:10px;"></i> মেমো # <span class="mb-num">${mv}</span></div>
        <button type="button" class="del-btn" onclick="delRow(${rowCnt})"><i class="fas fa-trash"></i></button>
        <input type="number" name="sale_memo[]" class="mn" value="${mv}" style="display:none;" readonly>

        <div style="margin-bottom:7px;">
            <span class="lbl"><i class="fas fa-user" style="font-size:9px;"></i> কাস্টমার</span>
            <select name="customer_id[]" class="ai" onchange="toggleCust(this)">${custOpts}</select>
            <input type="text" name="custom_customer_name[]" class="ai cname-box" placeholder="নাম / বাদ / নষ্ট">
        </div>

        <div class="g3" style="margin-bottom:7px;">
            <div>
                <span class="lbl"><i class="fas fa-boxes" style="font-size:9px;"></i> পিস</span>
                <input type="number" class="ai qty" name="sale_qty[]" placeholder="0" oninput="calcTotals()" min="0">
            </div>
            <div>
                <span class="lbl"><i class="fas fa-tag" style="font-size:9px;"></i> বিল ৳</span>
                <input type="number" class="ai bill" name="sale_bill[]" placeholder="0" oninput="calcTotals()" min="0">
            </div>
            <div>
                <span class="lbl"><i class="fas fa-money-bill" style="font-size:9px;"></i> জমা ৳</span>
                <input type="number" class="ai paid" name="sale_paid[]" placeholder="0" oninput="calcTotals()" min="0">
            </div>
        </div>

        <!-- বাকি + Camera side-by-side -->
        <div class="due-cam-row">
            <div>
                <span class="lbl"><i class="fas fa-hourglass-half" style="font-size:9px;"></i> বাকি ৳</span>
                <input type="number" class="ai c-err due" name="sale_due[]" placeholder="0" readonly style="border-style:dashed;height:40px;">
            </div>
            <div style="display:flex;flex-direction:column;">
                <span class="lbl">&nbsp;</span>
                <input type="hidden" name="sale_photo[]" id="sv${rowCnt}">
                <button type="button" class="cam-btn" id="sb${rowCnt}" onclick="capturePhoto(${rowCnt})">
                    <i class="fas fa-camera"></i> ছবি
                </button>
            </div>
        </div>

        <!-- Thumb preview -->
        <div style="margin-top:6px;display:flex;align-items:center;gap:6px;" id="thumbArea${rowCnt}">
            <img id="si${rowCnt}" class="photo-thumb" alt="">
        </div>
    `;
    document.getElementById('salesBody').appendChild(div);
}

function delRow(id){ const el=document.getElementById('sr'+id); if(el)el.remove(); updateMemos(); calcTotals(); }

function toggleCust(sel){
    const nxt=sel.nextElementSibling;
    nxt.style.display=sel.value==='custom'?'block':'none';
    if(sel.value==='custom') nxt.focus(); else nxt.value='';
}

function calcTotals(){
    let tB=0,tP=0,tD=0,tQ=0;
    document.querySelectorAll('.row-card').forEach(card=>{
        const b=parseFloat(card.querySelector('.bill')?.value)||0;
        const p=parseFloat(card.querySelector('.paid')?.value)||0;
        const q=parseFloat(card.querySelector('.qty')?.value)||0;
        const d=b-p;
        const de=card.querySelector('.due'); if(de) de.value=d||'';
        tB+=b; tP+=p; tD+=d; tQ+=q;
    });
    document.getElementById('gBill').value=tB;
    document.getElementById('gPaid').value=tP;
    document.getElementById('gDue').value=tD;
    document.getElementById('gQty').value=tQ;
    document.getElementById('dBill').textContent=tB.toLocaleString('bn-BD');
    document.getElementById('dPaid').textContent=tP.toLocaleString('bn-BD');
    document.getElementById('dDue').textContent=tD.toLocaleString('bn-BD');
}
/* পেজ খোলার সময় ফর্ম লুকানো থাকবে — শুধু সামারি বক্স দেখাবে;
   মাঝের "+" (নতুন সারি) চাপলে তবেই ফর্ম আসবে */
calcTotals();

/* ── SUBMIT ── */
let submitting=false;
document.getElementById('mainForm').addEventListener('submit',async function(e){
    e.preventDefault(); if(submitting)return;
    let ok=true,msg='',empty=false;
    document.querySelectorAll('.input-error').forEach(el=>el.classList.remove('input-error'));
    document.querySelectorAll('.row-card').forEach(card=>{
        const cs=card.querySelector('select[name="customer_id[]"]');
        const cn=card.querySelector('input[name="custom_customer_name[]"]');
        const ph=card.querySelector('input[name="sale_photo[]"]');
        const pb=card.querySelector('.cam-btn');
        const q=parseFloat(card.querySelector('.qty')?.value)||0;
        const b=parseFloat(card.querySelector('.bill')?.value)||0;
        const p=parseFloat(card.querySelector('.paid')?.value)||0;
        const cv=cs?.value||''; const cnv2=cn?.value.trim()||'';
        if(!cv&&!cnv2&&q===0&&b===0&&p===0){ empty=true; card.querySelectorAll('.ai').forEach(i=>i.classList.add('input-error')); }
        else{
            if(q===0&&b===0&&p===0&&cnv2!=='বাদ'&&cnv2!=='নষ্ট'){ if(cn)cn.classList.add('input-error'); msg+='❌ টাকা ০ হলে "বাদ" বা "নষ্ট" লিখুন!\n'; ok=false; }
            if(!ph?.value){ if(pb)pb.classList.add('input-error'); msg+='❌ ছবি বাধ্যতামূলক!\n'; ok=false; }
        }
    });
    if(empty){ msg+='❌ ফাঁকা সারি সরিয়ে ফেলুন!\n'; ok=false; }
    if(!ok){ alert('তথ্য অসম্পূর্ণ!\n\n'+msg); document.querySelector('.input-error')?.scrollIntoView({behavior:'smooth',block:'center'}); return; }
    submitting=true;
    const btn=document.getElementById('saveBtn');
    if(btn){ btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> সেভ হচ্ছে...'; }
    const fd=new FormData(this); fd.append('action','save');
    try{
        const r=await fetch('save_entry.php',{method:'POST',body:fd});
        if(r.ok){
            const t=await r.text();
            if(t.includes('success')){
                document.getElementById('successModal').classList.add('on');
                if(typeof confetti==='function') confetti({particleCount:120,spread:70,origin:{y:.6},colors:['#2563EB','#059669','#0EA5E9','#FBBF24']});
                setTimeout(()=>window.location.reload(),2600);
            } else throw new Error(t);
        } else throw new Error('নেটওয়ার্ক সমস্যা');
    }catch(err){
        alert('❌ সেভ করতে সমস্যা!\n'+err.message);
        if(btn){ btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> সেভ'; }
        submitting=false;
    }
});
</script>
  
<!-- Bottom Navigation Bar — save-bar-এর নিচে বসে, save-bar-কে উপরে তুলে দেয় -->
<style>
    :root { --cb-h: 60px; }
    /* সেভ-বার নাভবারের উপরে উঠে যাবে */
    .btm-bar { bottom: calc(var(--cb-h) + env(safe-area-inset-bottom)) !important; }
    /* কনটেন্ট যাতে নাভবারে ঢাকা না পড়ে */
    body { padding-bottom: calc(var(--bar-h) + var(--cb-h) + env(safe-area-inset-bottom) + 12px) !important; }
    /* ফ্লোটিং ক্যামেরা প্রিভিউ আরও উপরে + টানলেও সাইজ ফিক্সড থাকবে */
    #floatCam { bottom: calc(var(--bar-h) + var(--cb-h) + 12px + env(safe-area-inset-bottom)); width:120px !important; max-width:120px !important; height:auto !important; max-height:none !important; }
    #floatCam #video { width:100% !important; height:150px !important; object-fit:cover !important; }
</style>
<?php include 'cash_bottom_nav.php'; ?>

</body>
<body>
    <?php 
        $logo_url = "logo.png"; // রুট ফোল্ডারের লোগো
        include 'Helpers/SadakaloLoader.php'; 
    ?>

</html>
