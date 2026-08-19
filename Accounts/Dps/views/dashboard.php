<?php
/**
 * views/dashboard.php
 * ─────────────────────────────────────────
 * মূল শেল — সব partial view এখানে assemble হয়। কোনো business logic নেই,
 * শুধু layout + include।
 */
declare(strict_types=1);
?>
<?php require __DIR__ . '/layout/header.php'; ?>

<!-- ── হেডার / ব্যানার ── -->
<div class="hero-banner">
    <div class="hero-top">
        <button type="button" class="hero-icon-btn" onclick="switchView('accounts')" title="হোম" aria-label="হোম"><i class="fas fa-house"></i></button>
        <img class="hero-logo" src="<?= SecurityHelper::safeOut('assets/img/' . ($branding['banner_logo'] ?: 'logo.png')) ?>"
             alt="logo" onerror="this.src='assets/img/logo.png'">
        <div class="hero-titles">
            <div class="hero-title"><?= SecurityHelper::safeOut($branding['banner_title']) ?></div>
            <div class="hero-sub"><?= SecurityHelper::safeOut($branding['banner_sub']) ?></div>
        </div>
        <button type="button" id="themeToggleBtn" class="hero-icon-btn" onclick="toggleDarkMode()" title="ডার্ক/লাইট মোড" aria-label="ডার্ক/লাইট মোড"><i class="fas fa-moon" id="themeToggleIcon"></i></button>
    </div>

<?php
    $bannerImageFile = !empty($branding['banner_image']) ? DPS_ROOT . '/assets/img/' . $branding['banner_image'] : null;
    $bannerImageOk   = $bannerImageFile && is_file($bannerImageFile);
?>
    <div class="hero-showcase"<?php if ($bannerImageOk): ?> style="background-image:linear-gradient(rgba(0,0,0,.35),rgba(0,0,0,.15)),url('<?= SecurityHelper::safeOut('assets/img/' . $branding['banner_image']) ?>')"<?php endif; ?>>
        <div class="hero-showcase-fx">
            <span class="fx-ring fx-1"></span><span class="fx-ring fx-2"></span><span class="fx-ring fx-3"></span>
        </div>
        <div class="hero-showcase-card">
            <img class="hero-showcase-logo" src="<?= SecurityHelper::safeOut('assets/img/' . ($branding['banner_logo'] ?: 'avatar-placeholder.svg')) ?>"
                 alt="" onerror="this.src='assets/img/avatar-placeholder.svg'">
            <div class="hero-showcase-text">
                <div class="hero-showcase-title"><?= SecurityHelper::safeOut($branding['banner_title']) ?></div>
                <div class="hero-showcase-sub"><?= SecurityHelper::safeOut($branding['banner_sub']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ── সামারি কার্ড (কম্প্যাক্ট, আইকন-ব্যাজ) ── -->
<div id="summaryCards" class="summary-grid">
    <div class="summary-cell">
        <span class="s-icon s-icon-green"><i class="fas fa-arrow-down"></i></span>
        <div class="s-value text-success" id="sumTodayDeposit">৳০</div>
        <div class="s-label">আজ জমা</div>
    </div>
    <div class="summary-cell">
        <span class="s-icon s-icon-blue"><i class="fas fa-coins"></i></span>
        <div class="s-value" id="sumTodayProfit">৳০</div>
        <div class="s-label">আজকের মুনাফা</div>
    </div>
    <div class="summary-cell">
        <span class="s-icon s-icon-red"><i class="fas fa-arrow-up"></i></span>
        <div class="s-value text-danger" id="sumTodayWithdraw">৳০</div>
        <div class="s-label">আজ উত্তোলন</div>
    </div>
    <div class="summary-cell">
        <span class="s-icon s-icon-amber"><i class="fas fa-wallet"></i></span>
        <div class="s-value" id="sumTotalBalance">৳০</div>
        <div class="s-label">মোট তহবিল (সক্রিয়)</div>
    </div>
</div>

<div class="summary-strip">
    <div class="strip-left">
        <div class="strip-label">মোট মুনাফা (সব অ্যাকাউন্ট)</div>
        <div class="strip-value" id="sumTotalProfit">৳০</div>
    </div>
    <div class="strip-right">
        <div class="strip-pill"><span class="strip-num text-success" id="sumActiveCount">০</span><span class="strip-cap">সক্রিয়</span></div>
        <div class="strip-pill"><span class="strip-num text-danger" id="sumInactiveCount">০</span><span class="strip-cap">নিষ্ক্রিয়</span></div>
    </div>
</div>

<div class="app-content">
    <?php require __DIR__ . '/accounts/list.php'; ?>
    <?php require __DIR__ . '/ledger/table.php'; ?>
    <?php require __DIR__ . '/reports/monthly.php'; ?>
    <?php require __DIR__ . '/profile/profile.php'; ?>
</div>

<!-- ── ফর্ম শীট (আলাদা আলাদা concern অনুযায়ী) ── -->
<?php require __DIR__ . '/accounts/form_add.php'; ?>
<?php require __DIR__ . '/accounts/form_edit.php'; ?>
<?php require __DIR__ . '/ledger/deposit_form.php'; ?>
<?php require __DIR__ . '/ledger/withdraw_form.php'; ?>

<!-- ── বটম নেভিগেশন (ফ্লোটিং, স্টিকি) ── -->
<nav class="bottom-nav">
    <button class="bn-item on" data-view="accounts"><i class="fas fa-wallet"></i><span>একাউন্ট</span></button>
    <button class="bn-item" data-view="ledger"><i class="fas fa-book"></i><span>লেজার</span></button>
    <button class="bn-item bn-fab" id="fabAdd" aria-label="নতুন এন্ট্রি" aria-expanded="false"><span class="fab-orb"><i class="fas fa-plus"></i></span><span>নতুন</span></button>
    <button class="bn-item" data-view="reports"><i class="fas fa-chart-column"></i><span>রিপোর্ট</span></button>
    <button class="bn-item" data-view="profile"><i class="fas fa-user"></i><span>প্রোফাইল</span></button>
</nav>

<!-- স্পিড ডায়াল: FAB চাপলে উপরের দিকে খুলে যায় (আগের bottom-sheet এর বদলে) -->
<div class="speed-dial-backdrop" id="speedDialBackdrop"></div>
<div class="speed-dial" id="speedDial">
    <button class="sd-item sd-withdraw" id="fabNewWithdraw">
        <span class="sd-label">উত্তোলন এন্ট্রি</span>
        <span class="sd-orb"><i class="fas fa-arrow-up"></i></span>
    </button>
    <button class="sd-item sd-deposit" id="fabNewDeposit">
        <span class="sd-label">জমা এন্ট্রি</span>
        <span class="sd-orb"><i class="fas fa-arrow-down"></i></span>
    </button>
    <button class="sd-item sd-account" id="fabNewAccount">
        <span class="sd-label">নতুন অ্যাকাউন্ট</span>
        <span class="sd-orb"><i class="fas fa-user-plus"></i></span>
    </button>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
