<?php
/** @var string $success_msg */
/** @var string $error_msg */
/** @var array $active_cards */
/** @var array $inactive_cards */
/** @var float $g_total_due */
/** @var float $g_total_paid */
/** @var float $g_total_charge */
/** @var float $g_total_used */
/** @var ?array $view_card */
/** @var ?array $view_summary */
/** @var ?array $view_light */
/** @var array $grouped_ledger */
/** @var int $total_pages */
/** @var int $current_page */
/** @var int $vid */
/** @var array $type_labels */
/** @var string $csrf */
/** @var string $moduleUrl */
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
<script>(function(){try{var t=localStorage.getItem('cc_theme');document.documentElement.setAttribute('data-bs-theme',t==='light'?'light':'dark');}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Credit Card Manager — Sada Kalo Fashion</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo htmlspecialchars($moduleUrl); ?>/assets/css/app.css">
</head>
<body>
<div class="app-shell">

<!-- ===== Top App Bar ===== -->
<nav class="appbar">
  <div class="appbar-side">
    <?php if (!empty($view_card)): ?>
      <a href="<?php echo htmlspecialchars($moduleUrl); ?>/index.php" class="ic-btn" title="কার্ড লিস্ট"><i class="fas fa-arrow-left"></i></a>
    <?php else: ?>
      <a href="/dashboard.php" class="ic-btn" title="ড্যাশবোর্ড"><i class="fas fa-house"></i></a>
    <?php endif; ?>
  </div>
  <div class="appbar-center">
    <span class="brand-mark">
      <img src="<?php echo htmlspecialchars($moduleUrl); ?>/assets/img/logo.png" class="brand-logo" alt="SKF" onerror="this.remove()">
      <b>SK</b>
    </span>
    <span class="brand-txt">
      <span class="brand-name">Sada Kalo</span>
      <span class="brand-sub">Credit Card Vault</span>
    </span>
  </div>
  <div class="appbar-side">
    <button type="button" class="ic-btn" onclick="toggleTheme()" title="থিম"><i id="themeIco" class="fas fa-moon"></i></button>
  </div>
</nav>

<!-- ===== Marquee ===== -->
<div class="strip">
  <span class="strip-tag">CARD</span>
  <span class="strip-run">💳 আপনার সব ক্রেডিট কার্ড — এক জায়গায়  •  মাসভিত্তিক পেজিনেশন  •  এডিট অপশন  •  ক্যাশ ইমপ্যাক্ট  •  এনক্রিপ্টেড সিকিউরিটি 🔒</span>
</div>

<div class="wrap">

<?php if (!empty($success_msg)): ?>
<div class="app-alert app-alert-ok"><i class="fas fa-circle-check"></i><span><?php echo $success_msg; ?></span><button type="button" class="btn-close btn-close-sm" onclick="this.parentElement.remove()"></button></div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
<div class="app-alert app-alert-err"><i class="fas fa-triangle-exclamation"></i><span><?php echo $error_msg; ?></span><button type="button" class="btn-close btn-close-sm" onclick="this.parentElement.remove()"></button></div>
<?php endif; ?>

<?php
if ($view_card) {
    require MODULE_ROOT . '/Views/view.php';
} else {
    require MODULE_ROOT . '/Views/list.php';
}
?>

</div><!-- /.wrap -->

<?php require MODULE_ROOT . '/Views/partials/bottom_nav.php'; ?>
<?php require MODULE_ROOT . '/Views/partials/modals.php'; ?>

</div><!-- /.app-shell -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.MODULE_URL = <?php echo json_encode($moduleUrl); ?>;
window.CSRF_TOKEN = <?php echo json_encode($csrf); ?>;
</script>
<script src="<?php echo htmlspecialchars($moduleUrl); ?>/assets/js/app.js"></script>
</body>
</html>
