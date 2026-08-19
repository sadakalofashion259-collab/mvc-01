<?php
/**
 * index.php — Login Page Entry Point
 * ─────────────────────────────────────────────────────────────
 * MVC Flow:
 *   1. Session secure init
 *   2. DB connect  (db_connect.php)
 *   3. LoginController::redirectIfLoggedIn()
 *   4. LoginController::handlePost()  ← CSRF + CAPTCHA inside
 *   5. Redirect on success / setup
 *   6. LoginController::getViewData() → view vars
 *   7. Render Views/login/* partials
 * ─────────────────────────────────────────────────────────────
 * FIXES applied in this version:
 *   [1] লোগো বড় → clamp(80px, 20vw, 110px)
 *   [2] বাটন layout ঠিক → .btn-wrapper flex:1 + min-width:0
 *   [3] Download বাটন → <a.btn-circle-3d.btn-dl> directly (no nested div)
 *   [4] .btn-dl নিজের CSS class (.btn-yt ছিল inline style এ)
 *   [5] cloudfiare_reCAPTCHA.php require সরানো → CaptchaService handle করে
 *   [6] #recaptcha-stage / #login-stage CSS class এ নেওয়া → inline style কম
 *   [7] .captcha-welcome-text class
 *   [8] CAPTCHA pass হলে first input auto-focus
 */
declare(strict_types=1);
ob_start();

/* ── Session Secure Init ──────────────────────────────────── */
@ini_set('session.gc_maxlifetime',   '1200');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.cookie_httponly',  '1');
@ini_set('session.use_strict_mode',  '1');
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

/* ── Bootstrap ────────────────────────────────────────────── */
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/Controllers/LoginController.php';
date_default_timezone_set('Asia/Dhaka');

/* ── Controller ───────────────────────────────────────────── */
$loginController = new LoginController($conn);

/* ── WebAuthn (বায়োমেট্রিক) AJAX রাউটিং — render/redirect এর আগে ── */
require_once __DIR__ . '/Controllers/WebAuthnController.php';
if ((new WebAuthnController($conn))->handle()) { exit; }

/* ── বায়োমেট্রিক সেটআপ গেট: setup বাটন → ID+পাসওয়ার্ড ভেরিফাই → সেটআপ পেজ ── */
if (isset($_GET['next']) && $_GET['next'] === 'bio_setup') {
    if (!empty($_SESSION['loggedin'])) {
        ob_end_clean(); header('Location: biometric_setup.php'); exit;   // আগেই লগইন থাকলে সরাসরি
    }
    $_SESSION['login_next'] = 'biometric_setup.php';                      // লগইনের পর এখানে ফেরত
}

$loginController->redirectIfLoggedIn();

/* ── Handle POST ──────────────────────────────────────────── */
$loginResult     = $loginController->handlePost();
$loginResultHtml = '═══ সাদা-কালো ফ্যাশন ═══';

if ($loginResult !== null) {
    if ($loginResult->isSuccess()) {
        $dest = !empty($_SESSION['login_next']) ? (string)$_SESSION['login_next'] : 'dashboard.php';
        unset($_SESSION['login_next']);
        ob_end_clean(); header('Location: ' . $dest); exit;
    }
    if ($loginResult->isSetupRequired()) {
        ob_end_clean(); header('Location: auth/otp_auth.php?setup=1'); exit;
    }
    $loginResultHtml = $loginResult->htmlMessage;
}

/* ── বায়োমেট্রিক সেটআপ শেষে সফল বার্তা ── */
if (isset($_GET['enrolled'])) {
    $loginResultHtml = "<div style='padding:12px 14px;background:#f0fdf4;border:1px solid #86efac;border-radius:12px;color:#15803d;font-weight:bold;font-size:13px;text-align:center;'>✅ বায়োমেট্রিক চালু হয়েছে! এখন ফিঙ্গারপ্রিন্ট দিয়ে লগইন করুন।</div>";
}

/* ── View Data ────────────────────────────────────────────── */
$viewDataArray       = $loginController->getViewData();
$loginSiteNoticeText = $viewDataArray['siteNoticeText'];
$loginSliderPosts    = $viewDataArray['sliderPosts'];
$loginCsrfToken      = $viewDataArray['csrfToken'];

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>লগইন — Sada Kalo Fashion</title>
<link rel="icon" href="logo.png" type="image/png">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#1e3a8a">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/Helpers/pwa_assets.php'; ?>
<style>
/* ══════════════════════════════════════════════════════════════
   LOGIN PAGE — Full CSS
   ══════════════════════════════════════════════════════════════
   Class Index:
   ─ GLOBAL        : * html body
   ─ NOTICE BAR    : .notice-bar  .notice-content  @keyframes marquee
   ─ WRAPPER       : .content-wrapper
   ─ SLIDER        : .slider-container  .slide  .slide.active  .caption
   ─ LOGIN BOX     : .login-box  .logo-wrapper  .logo
                     .title-group  .main-title  .sub-title
                     .msg-container
                     .captcha-welcome-text
                     #recaptcha-stage  #login-stage
                     form  input  input:focus
                     #loginBtn  #loginBtn:active
                     .forgot-pass
   ─ ACTION BTNs   : .action-buttons-row  .btn-wrapper  .btn-circle-3d
                     .btn-privacy  .btn-gallery  .btn-fb
                     .btn-wa  .btn-dl  .btn-yt  .btn-text-3d
══════════════════════════════════════════════════════════════ */

/* ── Global ────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html { margin: 0; padding: 0; height: 100%; }
body {
    margin: 0; padding: 0; width: 100%;
    height: 100vh; height: 100dvh;
    overflow: hidden; overscroll-behavior: none;
    font-family: 'Segoe UI', sans-serif;
    background: #fff;
    display: flex; flex-direction: column;
}

/* ── .notice-bar ───────────────────────────────────────────── */
.notice-bar {
    width: 100%; height: 30px; min-height: 30px;
    background: #dc2626; color: #fff; font-weight: bold;
    display: flex; align-items: center;
    box-shadow: 0 3px 8px rgba(0,0,0,.3);
    flex-shrink: 0; z-index: 1000; overflow: hidden;
}
.notice-content {
    width: 100%; white-space: nowrap; overflow: hidden;
    font-size: clamp(11px, 3vw, 13px);
}
.notice-content span {
    display: inline-block; padding-left: 100%;
    animation: marquee 18s linear infinite;
}
@keyframes marquee {
    0%   { transform: translate(0, 0); }
    100% { transform: translate(-100%, 0); }
}

/* ── .content-wrapper ──────────────────────────────────────── */
/* FIX: overflow:hidden → overflow-y:auto — জায়গা কম পড়লে পুরো এলাকা
   একসাথে স্ক্রল হবে, নিচের সোশ্যাল বাটন আর হঠাৎ হাইড/কাটা পড়বে না। */
.content-wrapper {
    flex: 1; min-height: 0; width: 100%;
    display: flex; flex-direction: column;
    overflow-y: auto; overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}

/* ── .slider-container / .slide / .caption ────────────────── */
.slider-container {
    width: 100%; height: 30%;
    min-height: 130px; max-height: 230px;
    position: relative; overflow: hidden;
    background: #111; flex-shrink: 0;
}
.slide {
    position: absolute; inset: 0;
    opacity: 0; transition: opacity .8s ease-in-out;
}
.slide.active { opacity: 1; z-index: 1; }
.slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
.caption {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: linear-gradient(to top, rgba(0,0,0,.75), transparent);
    color: #fff; padding: 20px 10px 8px;
    text-align: center;
    font-size: clamp(11px, 3vw, 14px); font-weight: bold;
}

/* ── .login-box ────────────────────────────────────────────── */
/* FIX: flex:1 → flex:1 0 auto (কন্টেন্ট অনুযায়ী উচ্চতা, দরকারে grow)
   justify-content: space-evenly → flex-start (ইনপুট বক্স উপরে উঠে আসে)
   overflow: hidden → visible (কিছু কাটা পড়ে না; ভাই-বাটন নিচে স্ক্রল হয়) */
.login-box {
    background: #fff; flex: 1 0 auto; width: 100%;
    padding: clamp(6px, 1.6vh, 12px) clamp(14px, 4vw, 22px) clamp(4px, 1vh, 8px);
    text-align: center;
    display: flex; flex-direction: column;
    align-items: center; justify-content: flex-start;
    gap: clamp(5px, 1.4vh, 11px);
    overflow: visible;
}

/* ── .logo-wrapper / .logo ─────────────────────────────────── */
/* FIX [1]: লোগো বড় — min 80px → max 110px */
.logo-wrapper {
    display: flex; justify-content: center;
    flex-shrink: 0; margin-bottom: 2px;
}
.logo {
    width:  clamp(100px, 25vw, 132px);
    height: clamp(100px, 25vw, 132px);
    border-radius: 50%;
    border: 3px solid #D4AF37;
    background: #fff; padding: 2px;
    box-shadow: 0 4px 16px rgba(0,0,0,.22),
                0 0 0 5px rgba(212,175,55,.14);
    object-fit: cover;
}

/* ── .title-group / .main-title / .sub-title ───────────────── */
.title-group { flex-shrink: 0; }
.main-title {
    color: #1e3a8a; margin: 0;
    font-size: clamp(15px, 5vw, 22px); font-weight: 900;
    letter-spacing: .5px; text-transform: uppercase;
    text-shadow: 1px 1px 0 #cbd5e1, 2px 2px 3px rgba(0,0,0,.15);
}
.sub-title {
    color: #dc2626; margin: 4px 0 6px;
    font-size: clamp(10px, 3vw, 13px); font-weight: bold;
    background: #fee2e2; display: inline-block;
    padding: 3px 14px; border-radius: 20px;
    border: 1px solid #fca5a5; box-shadow: 0 3px 0 #f87171;
}

/* ── .msg-container ────────────────────────────────────────── */
.msg-container { width: 100%; max-width: 340px; flex-shrink: 0; }

/* ── form / input ──────────────────────────────────────────── */
form {
    width: 100%; max-width: 340px; flex-shrink: 0;
    display: flex; flex-direction: column; align-items: center;
}

/* ── #recaptcha-stage ──────────────────────────────────────── */
/* FIX [6]: inline style → class */
#recaptcha-stage {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    width: 100%; margin-bottom: 10px;
}
/* FIX [7]: captcha welcome pill */
.captcha-welcome-text {
    font-size: 13px; font-weight: bold; color: #ef4444;
    margin: 0 0 12px;
    background: #fee2e2; padding: 5px 14px;
    border-radius: 20px; border: 1px solid #fca5a5;
}

/* ── #login-stage ──────────────────────────────────────────── */
/* FIX [6]: inline style → class (display toggled by JS) */
#login-stage {
    display: none; width: 100%;
    flex-direction: column; align-items: center;
}

input {
    width: 100%;
    padding: clamp(9px, 2.2vh, 13px) 18px;
    margin-bottom: 8px;
    border: 2px solid #cbd5e1; border-radius: 30px;
    font-size: clamp(13px, 3.5vw, 15px); outline: none;
    background: #f8fafc; font-weight: bold; color: #333;
    box-shadow: inset 0 3px 6px rgba(0,0,0,.1),
                0 2px 4px rgba(0,0,0,.04);
    transition: border-color .25s, box-shadow .25s;
    -webkit-appearance: none;
}
input:focus {
    border-color: #1e3a8a; background: #fff;
    box-shadow: inset 0 2px 4px rgba(0,0,0,.07),
                0 0 0 3px rgba(30,58,138,.12);
}

/* ── #loginBtn ──────────────────────────────────────────────── */
#loginBtn {
    width: 62%; padding: clamp(9px, 2.2vh, 13px);
    background: linear-gradient(to bottom, #3b82f6, #1d4ed8);
    color: #fff; border: none; font-weight: 900;
    font-size: clamp(13px, 3.5vw, 16px); cursor: pointer;
    border-radius: 30px;
    box-shadow: 0 5px 0 #1e3a8a, 0 7px 12px rgba(0,0,0,.2);
    text-transform: uppercase;
    transition: transform .1s, box-shadow .1s;
    letter-spacing: 1px; -webkit-appearance: none;
    margin-bottom: 10px;
}
#loginBtn:active { transform: translateY(5px); box-shadow: 0 0 0 #1e3a8a; }

/* ── .forgot-pass ───────────────────────────────────────────── */
.forgot-pass {
    display: inline-block; color: #1e40af;
    font-size: clamp(10px, 3vw, 13px); font-weight: bold;
    text-decoration: none; padding: 5px 16px; border-radius: 20px;
    background: #eff6ff; border: 1px solid #bfdbfe;
    box-shadow: 0 3px 0 #bfdbfe; flex-shrink: 0;
    transition: transform .1s; margin-bottom: 10px;
}
.forgot-pass:active { transform: translateY(2px); }

/* ── .action-buttons-row ────────────────────────────────────── */
/* FIX [2]: max-width ও padding বাড়ানো — সব বাটন সমান জায়গা পায় */
.action-buttons-row {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    gap: clamp(4px, 2vw, 10px);
    width: 100%; max-width: 380px;
    margin: clamp(6px, 1.6vh, 12px) auto 0;
    padding: 0 6px calc(env(safe-area-inset-bottom, 0px) + 12px);
    flex-shrink: 0;
}

/* ── .btn-wrapper ───────────────────────────────────────────── */
/* FIX [2]: flex:1 + min-width:0 → সব wrapper সমান প্রস্থ, overflow clip */
.btn-wrapper {
    display: flex; flex-direction: column;
    align-items: center; gap: 3px;
    flex: 1; min-width: 0;
}

/* ── .btn-circle-3d ─────────────────────────────────────────── */
.btn-circle-3d {
    width:  clamp(36px, 10vw, 46px);
    height: clamp(36px, 10vw, 46px);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; text-decoration: none;
    font-size: clamp(14px, 4vw, 18px);
    flex-shrink: 0;
    transition: transform .1s, box-shadow .1s;
}
.btn-circle-3d:active { transform: translateY(4px); box-shadow: none !important; }

/* ── Button colour variants ──────────────────────────────────── */
.btn-privacy { background: linear-gradient(to bottom,#64748b,#475569); box-shadow: 0 4px 0 #334155,0 6px 8px rgba(0,0,0,.2); }
.btn-gallery { background: linear-gradient(to bottom,#f97316,#ea580c); box-shadow: 0 4px 0 #c2410c,0 6px 8px rgba(0,0,0,.2); }
.btn-fb      { background: linear-gradient(to bottom,#3b82f6,#2563eb); box-shadow: 0 4px 0 #1d4ed8,0 6px 8px rgba(0,0,0,.2); }
.btn-wa      { background: linear-gradient(to bottom,#22c55e,#16a34a); box-shadow: 0 4px 0 #15803d,0 6px 8px rgba(0,0,0,.2); }
/* FIX [3][4]: Download এ নিজের class, nested <div> নেই */
.btn-dl      { background: linear-gradient(to bottom,#9333ea,#7c3aed); box-shadow: 0 4px 0 #5b21b6,0 6px 8px rgba(0,0,0,.2); }
.btn-yt      { background: linear-gradient(to bottom,#f43f5e,#dc2626); box-shadow: 0 4px 0 #991b1b,0 6px 8px rgba(0,0,0,.2); }

/* ── .btn-text-3d ───────────────────────────────────────────── */
.btn-text-3d {
    font-size: clamp(8px, 2.3vw, 11px); font-weight: bold;
    color: #1e3a8a; text-shadow: 1px 1px 0 #fff;
    text-align: center; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; width: 100%;
}
</style>
</head>
<body>

<?php include __DIR__ . '/Views/login/notice_bar.php'; ?>

<div class="content-wrapper">

    <?php include __DIR__ . '/Views/login/image_slider.php'; ?>

    <?php include __DIR__ . '/Views/login/login_form.php'; ?>

    <?php include __DIR__ . '/Views/login/action_buttons.php'; ?>

</div>

<?php include __DIR__ . '/Views/login/login_scripts.php'; ?>

</body>
</html>
