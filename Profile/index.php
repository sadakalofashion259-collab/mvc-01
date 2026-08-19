<?php
/**
 * Profile/index.php — Entry Point (Module Renderer)
 * ─────────────────────────────────────────────────────────────
 * MVC Flow:
 *   1. Session secure init (upload size overrides)
 *   2. DB connect (../db_connect.php)
 *   3. ProfileController::handlePost()   ← CSRF verify first
 *   4. ProfileController::generateCsrfToken() ← new token for next submit
 *   5. Flash result via PRG (Post-Redirect-Get)
 *   6. Render Views (partials + profile sections)
 * ─────────────────────────────────────────────────────────────
 * ⚠️  CSRF Order is critical:
 *   handlePost() BEFORE generateCsrfToken()
 *   — POST verifies old token ✅
 *   — GET generates fresh token for the form ✅
 * ─────────────────────────────────────────────────────────────
 * <base href="/"> — সাবডিরেক্টরি থেকে রেন্ডার হলেও সব relative
 * লিংক (logo.png, dashboard.php, uploads/...) রুটে রেজলভ হয়।
 * File path: public_html/Profile/index.php
 */
declare(strict_types=1);

// ── Upload Size Overrides (20MB ইমেজ সাপোর্ট) ────────────────
@ini_set('upload_max_filesize',    '24M');
@ini_set('post_max_size',          '28M');
@ini_set('session.gc_maxlifetime', '1800');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.cookie_httponly',  '1');
@ini_set('session.use_strict_mode',  '1');
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Bootstrap ─────────────────────────────────────────────────
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/Models/Interfaces/ProfileModelInterface.php';
require_once __DIR__ . '/Models/ProfileModel.php';
require_once __DIR__ . '/Services/ImageResizer.php';
require_once __DIR__ . '/Controllers/ProfileController.php';
require_once __DIR__ . '/Services/AuditInit.php';

// Audit Logger (একবার)
AuditInit::boot($conn);

// ── Controller ────────────────────────────────────────────────
$profileCtrl = new ProfileController($conn);

// ── PRG Pattern — CSRF Order is Critical ──────────────────────
$actionResult  = $profileCtrl->handlePost();
$profileCsrfToken = $profileCtrl->generateCsrfToken();

if ($actionResult !== null) {
    $_SESSION['profile_result_success'] = $actionResult->success;
    $_SESSION['profile_result_message'] = $actionResult->message;
    $_SESSION['profile_result_tab']     = $actionResult->section->tabIndex();
    header('Location: index.php');
    exit;
}

// ── Flash From Session ────────────────────────────────────────
$flashSuccess = null;
$flashMessage = '';
$profileActiveTab = 0;
if (isset($_SESSION['profile_result_message'])) {
    $flashSuccess     = (bool)   $_SESSION['profile_result_success'];
    $flashMessage     = (string) $_SESSION['profile_result_message'];
    $profileActiveTab = (int)    $_SESSION['profile_result_tab'];
    unset(
        $_SESSION['profile_result_success'],
        $_SESSION['profile_result_message'],
        $_SESSION['profile_result_tab']
    );
}

// ── User Data ─────────────────────────────────────────────────
$userData = $profileCtrl->getUserData();
if ($userData === null) {
    session_unset();
    session_destroy();
    header('Location: index.php?reason=nouser');
    exit;
}

// ── Safe XSS Helper ──────────────────────────────────────────
function xss(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// ── Prepare View Variables ────────────────────────────────────
$profileUId       = xss($userData['id']);
$profileUUsername = xss($userData['username']);
$profileUEmail    = xss($userData['email']);
$profileUPhone    = xss($userData['phone']);
$profileUMobile   = xss($userData['mobile']);
$profileUAddress  = xss($userData['address']);
$profileURole     = xss($userData['role']);
$profileUStatus   = xss($userData['status']);
$profileUJoined   = !empty($userData['joining_date'])
                    ? date('d M Y', strtotime((string) $userData['joining_date']))
                    : 'N/A';
$profileUPic      = xss((string) ($userData['profile_pic'] ?? 'default_user.png'));

$profileRoleLabel = match($profileURole) {
    'admin'      => '👑 ADMIN',
    'superadmin' => '🔱 SUPER ADMIN',
    default      => '👤 STAFF',
};
$profileRoleBadgeClass = in_array($profileURole, ['admin', 'superadmin'])
                         ? 'badge-admin' : 'badge-staff';
$profileStatusClass    = $profileUStatus === 'active' ? 'badge-active' : 'badge-inactive';

// ── Navbar / Sidebar Vars ─────────────────────────────────────
$navbarSubTitle   = 'প্রোফাইল সেটিংস';
$navbarRole       = $profileCtrl->userRole;
$navbarNotifBadge = '';
$navbarProfilePic = $profileUPic;
$navbarUsername   = $profileUUsername;
$navbarBellHref   = 'admin/admin_panel.php';

$bottomNavActivePage = 'profile';
$bottomNavNotifBadge = '';

$role = $profileCtrl->userRole;

// ── viewDataArray for sidebar quick info (no stats on profile) ─
$viewDataArray = [];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<base href="/">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no,viewport-fit=cover">
<title>প্রোফাইল — SADA KALO FASHION</title>

<!-- Bootstrap 5.3.8 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome 6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- App Premium CSS -->
<link rel="stylesheet" href="assets/style_css/premium.css">

<!-- Theme Init (no flash) -->
<script>
(function() {
    try {
        if (localStorage.getItem('sk_theme') === 'light') {
            document.body && document.body.classList.add('light-mode');
        }
    } catch(e) {}
})();
</script>

<style>
/* ── Profile Page-Level CSS ────────────────────────────────────
   Classes defined here are profile-specific additions
   that complement premium.css globals.
──────────────────────────────────────────────────────────── */

/* .pass-info-box
   Blue info box inside password tab */
.pass-info-box {
    background: var(--psoft);
    border: 1px solid rgba(91, 142, 255, .18);
    border-radius: var(--r-sm);
    padding: 11px 13px;
    margin-bottom: 14px;
}
.pass-info-box p {
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
    line-height: 1.8;
    margin: 0;
}
.pass-info-box i { color: var(--primary); }

/* .current-pic-wrap
   Centered current picture preview container */
.current-pic-wrap { text-align: center; margin-bottom: 16px; }
.current-pic-wrap img {
    width: 76px;
    height: 76px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary);
    box-shadow: 0 0 20px var(--pglow);
    display: inline-block;
}
.current-pic-label {
    display: block;
    font-size: 10px;
    font-weight: 800;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .07em;
    margin-bottom: 9px;
}

/* .pic-file-info
   Green file name info row (shown after selection) */
.pic-file-info {
    display: none;
    margin-bottom: 12px;
    padding: 10px 13px;
    background: var(--greensoft);
    border: 1px solid rgba(34, 197, 94, .25);
    border-radius: var(--r-sm);
    font-size: 12px;
    font-weight: 700;
    color: var(--green);
}

/* .pic-guide-box
   Gold guidelines box below upload form */
.pic-guide-box {
    margin-top: 13px;
    padding: 11px 13px;
    background: var(--goldsoft);
    border: 1px solid rgba(245, 158, 11, .20);
    border-radius: var(--r-sm);
}
.pic-guide-box p {
    font-size: 11px;
    font-weight: 700;
    color: var(--gold);
    line-height: 1.8;
    margin: 0;
}
</style>
</head>
<body>

<?php include __DIR__ . '/../Views/partials/ticker_bar.php'; ?>

<?php include __DIR__ . '/../Views/partials/top_navbar.php'; ?>

<?php include __DIR__ . '/../Views/partials/sidebar.php'; ?>

<!-- ══ PAGE CONTENT ══════════════════════════════════════════ -->
<main class="page-container">

    <?php include __DIR__ . '/../Views/partials/section_theme_toggle.php'; ?>

    <?php
        // Flash message — uses $flashSuccess + $flashMessage from above
        $flashAlertId = 'flashMsg';
        include __DIR__ . '/../Views/partials/flash_message.php';
    ?>

    <?php include __DIR__ . '/Views/profile_hero.php'; ?>

    <?php include __DIR__ . '/Views/profile_tabs.php'; ?>

</main>

<?php include __DIR__ . '/../Views/partials/bottom_nav.php'; ?>

<!-- ── Scripts ─────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<?php include __DIR__ . '/../Views/partials/app_scripts.php'; ?>
<?php include __DIR__ . '/Views/profile_scripts.php'; ?>

</body>
</html>
