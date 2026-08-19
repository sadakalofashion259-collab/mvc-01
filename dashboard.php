<?php
/**
 * dashboard.php
 * ─────────────────────────────────────────────────────────────
 * MVC Flow:
 *   1. Session secure init
 *   2. DB connect (db_connect.php)
 *   3. DashboardController::handlePostRequests()
 *   4. DashboardController::getViewData()
 *   5. Render Views (partials + dashboard sections)
 * ─────────────────────────────────────────────────────────────
 * File path: public_html/dashboard.php
 */
declare(strict_types=1);

// ── Secure Session Config ─────────────────────────────────────
@ini_set('session.gc_maxlifetime',  '1200');
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
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/Controllers/DashboardController.php';

// ── Controller ────────────────────────────────────────────────
$dashboardController = new DashboardController($conn);
$dashboardController->handlePostRequests();
$viewDataArray = $dashboardController->getViewData();

// ── View Variables (extracted for partials) ───────────────────
$dashBroadcastNotices = $viewDataArray['adminBroadcastNotices'];
$dashCsrfToken        = $viewDataArray['csrfToken'];
$dashCollectionAlerts = $viewDataArray['collectionAlerts'];
$dashNextMemoNumber   = $viewDataArray['nextMemoNumber'];
$dashCustomerCount    = isset($viewDataArray['activeCustomersList'])
                        ? count($viewDataArray['activeCustomersList']) : 0;

// ── Navbar Vars ───────────────────────────────────────────────
$navbarSubTitle     = 'LEDGER &mdash; ' . date('d.M.Y');
$navbarRole         = $viewDataArray['userRole'];
$navbarNotifBadge   = $viewDataArray['notificationBadgeHtml'];
$navbarProfilePic   = $viewDataArray['userProfilePic'];
$navbarUsername     = $viewDataArray['userName'];
$navbarBellHref     = '../admin/admin_panel.php';

// ── Bottom Nav Vars ───────────────────────────────────────────
$bottomNavActivePage = 'dashboard';
$bottomNavNotifBadge = $viewDataArray['notificationBadgeHtml'];

// ── Role for sidebar ──────────────────────────────────────────
$role = $viewDataArray['userRole'];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no,viewport-fit=cover">
    <title>═সাদা-কালো ফ্যাশন═</title>
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#060D1F">
    <?php require __DIR__ . '/Helpers/pwa_assets.php'; ?>

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
                document.documentElement.classList.add('__lm_pending');
            }
        } catch(e) {}
    })();
    </script>

    <style>
    /* ── Dashboard Page-Level CSS ──────────────────────────────
       Classes defined here are dashboard-specific additions
       that complement premium.css globals.
    ────────────────────────────────────────────────────────── */

    /* .dashboard-banner-fallback
       Shown when banner.jpg fails to load */
    .dashboard-banner-fallback {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        gap: 5px;
    }

    /* .stat-pill-row
       2-column grid for quick stat cards */
    .stat-pill-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 14px;
    }

    /* .stat-pill
       Individual quick stat card */
    .stat-pill {
        background: var(--card);
        border: 1px solid var(--b2);
        border-radius: var(--r-lg);
        padding: 14px 15px;
        box-shadow: var(--sh-sm);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* .stat-pill-icon
       Colored icon circle inside stat pill */
    .stat-pill-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    /* .stat-pill-val / .stat-pill-lbl
       Number and label text inside stat pill */
    .stat-pill-val { font-size: 20px; font-weight: 900; color: var(--txt); line-height: 1; }
    .stat-pill-lbl { font-size: 10px; font-weight: 600; color: var(--muted); margin-top: 2px; }
    </style>
</head>

<body>

<?php include __DIR__ . '/Views/partials/ticker_bar.php'; ?>

<?php include __DIR__ . '/Views/partials/top_navbar.php'; ?>

<?php include __DIR__ . '/Views/partials/sidebar.php'; ?>

<!-- ══ PAGE CONTENT ══════════════════════════════════════════ -->
<main class="page-container">
    
 <?php include __DIR__ . '/Views/partials/flash_message.php'; ?>

<?php include __DIR__ . '/Views/dashboard/welcome_banner.php'; ?>

<?php include __DIR__ . '/Views/dashboard/collection_alerts.php'; ?>

    <!-- Today's Routine (unchanged, separate module) -->
    <?php include __DIR__ . '/Task/Views/dashboard_routine.php'; ?>

</main>

<?php include __DIR__ . '/Views/partials/bottom_nav.php'; ?>

<!-- ── Scripts ────────────────────────────────────────────────── -->

<?php include __DIR__ . '/Views/partials/app_scripts.php'; ?>
<?php include __DIR__ . '/Views/partials/collection_scroll_script.php'; ?>

</body><body>
    <?php 
        $logo_url = "logo.png"; 

        include 'Helpers/SadakaloLoader.php'; 
    ?>
</body>



</script>
</html>
