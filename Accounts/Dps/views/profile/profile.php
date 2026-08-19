<!-- views/profile/profile.php — প্রোফাইল ভিউ (সহজ, ব্যবসা-বান্ধব কন্টেন্ট) -->
<div id="viewProfile" class="app-view d-none">
    <div class="section-head"><h6><i class="fas fa-user"></i> প্রোফাইল</h6></div>

    <div class="profile-card">
        <img src="<?= SecurityHelper::safeOut('assets/img/' . ($branding['banner_logo'] ?: 'avatar-placeholder.svg')) ?>"
             class="profile-logo" alt="logo"
             onerror="this.src='assets/img/avatar-placeholder.svg'">
        <div class="profile-name"><?= SecurityHelper::safeOut($branding['banner_sub']) ?></div>
        <div class="profile-sub"><?= SecurityHelper::safeOut($branding['banner_title']) ?></div>
    </div>

    <div class="acc-card mt-3">
        <div class="acc-top">
            <div class="acc-id-col">
                <div class="acc-name">হিসাবের সারসংক্ষেপ</div>
                <div class="acc-no">সব একাউন্ট মিলিয়ে</div>
            </div>
        </div>
        <div class="acc-pills mt-2">
            <span class="pill pill-principal"><i class="fas fa-wallet"></i> মোট ব্যালেন্স: <span id="profTotalBalance">—</span></span>
            <span class="pill pill-rate"><i class="fas fa-user-check"></i> সক্রিয় একাউন্ট: <span id="profActiveCount">—</span></span>
            <span class="pill pill-profit"><i class="fas fa-chart-line"></i> মোট মুনাফা: <span id="profTotalProfit">—</span></span>
        </div>
    </div>
</div>
