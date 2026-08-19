<?php
/**
 * PARTIAL: bottom_nav.php
 * ─────────────────────────────────────────────────────────────
 * Premium bottom navigation bar.
 *   • Every item sits inside its own 3D round circle.
 *   • The center item (Inventory) is an elevated, raised
 *     purple-gradient "speed-dial" button.
 *   • All styling is self-contained in this file — no edits to
 *     premium.css are required. Scoped under ".skf-bnav" so it
 *     overrides Bootstrap / premium.css without side effects.
 * ─────────────────────────────────────────────────────────────
 * Required vars:
 *   $bottomNavActivePage  string  — 'dashboard'|'customers'|'inventory'|'menu'|'profile'
 *   $bottomNavNotifBadge  string  — HTML badge string or ''
 */

declare(strict_types=1);

$bnActivePage = $bottomNavActivePage ?? 'dashboard';
$bnNotifBadge = $bottomNavNotifBadge ?? '';

// Extract a clean numeric count from whatever badge string was passed in.
$bnBadgeCount = '';
if ($bnNotifBadge !== '' && preg_match('/(\d+)/', $bnNotifBadge, $bnBadgeMatch) === 1) {
    $bnBadgeCount = $bnBadgeMatch[1];
}

/** Returns ' active' when the supplied page key is the current page. */
$bnActiveClass = static fn (string $pageKey): string => $bnActivePage === $pageKey ? ' active' : '';
?>

<?php if (!defined('SKF_BOTTOM_NAV_STYLE_RENDERED')): define('SKF_BOTTOM_NAV_STYLE_RENDERED', true); ?>
<style>
/* ══ SADA KALO — PREMIUM 3D BOTTOM NAV ══════════════════════════
   Scoped under .skf-bnav so it cannot leak into other components.
══════════════════════════════════════════════════════════════ */
.skf-bnav.bottom-nav {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1040;
    display: flex !important;
    align-items: flex-end;
    justify-content: space-around;
    gap: 4px;
    overflow: visible !important;
    padding: 12px 8px calc(10px + env(safe-area-inset-bottom, 0px));
    background: #141416;
    border-top-left-radius: 22px;
    border-top-right-radius: 22px;
    box-shadow: 0 -6px 22px rgba(0, 0, 0, 0.45);
}

.skf-bnav .bn-item {
    flex: 1 1 0;
    min-width: 0;
    display: flex !important;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 5px;
    position: relative;
    padding: 0;
    background: transparent !important;
    border: none !important;
    text-decoration: none !important;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

/* ── Flat icon on dark bar ────────────────────────────────────── */
.skf-bnav .bn-icon {
    width: 44px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.55rem;
    color: #b8bcc4;
    background: transparent;
    transition: transform .18s ease, color .18s ease;
}

.skf-bnav .bn-item:active .bn-icon {
    transform: translateY(1px);
}

.skf-bnav .bn-label {
    font-size: .78rem;
    font-weight: 700;
    line-height: 1;
    color: #c7cbd2;
    white-space: nowrap;
}

/* ── Active item highlight (red) ──────────────────────────────── */
.skf-bnav .bn-item.active .bn-icon { color: #e30b13; }
.skf-bnav .bn-item.active .bn-label { color: #e30b13; }

/* ── Elevated center "speed-dial" button (colour keeps changing) ── */
@keyframes skfCenterHue { to { filter: hue-rotate(360deg); } }
.skf-bnav .bn-center .bn-icon {
    width: 66px;
    height: 66px;
    margin-top: -38px;
    border-radius: 22px;
    font-size: 1.7rem;
    color: #ffffff !important;
    background: linear-gradient(160deg, #ff2a30, #d5060d);
    border: 4px solid #141416;
    box-shadow:
        0 10px 22px rgba(227, 11, 19, 0.55),
        0 0 0 4px rgba(227, 11, 19, 0.14);
    animation: skfCenterHue 6s linear infinite;
}

.skf-bnav .bn-center:active .bn-icon,
.skf-bnav .bn-center.active .bn-icon {
    color: #ffffff !important;
    transform: translateY(-2px) scale(1.04);
    box-shadow:
        0 12px 26px rgba(227, 11, 19, 0.65),
        0 0 0 5px rgba(227, 11, 19, 0.22);
}

.skf-bnav .bn-center .bn-label { color: #e30b13; }

/* ── Notification badge on the menu icon ──────────────────────── */
.skf-bnav .nav-badge {
    position: absolute;
    top: -2px;
    right: 20px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .68rem;
    font-weight: 700;
    color: #ffffff;
    background: #ef4444;
    border-radius: 9px;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.5);
}

/* ── Small-screen fine-tuning ─────────────────────────────────── */
@media (max-width: 360px) {
    .skf-bnav .bn-icon { width: 40px; height: 38px; font-size: 1.4rem; }
    .skf-bnav .bn-center .bn-icon { width: 60px; height: 60px; margin-top: -34px; font-size: 1.5rem; }
    .skf-bnav .bn-label { font-size: .72rem; }
}
</style>
<?php endif; ?>

<!-- ══ BOTTOM NAV ══════════════════════════════════════════════ -->
<nav class="skf-bnav bottom-nav" aria-label="Bottom navigation">

    <a href="dashboard.php"
       class="bn-item<?php echo $bnActiveClass('dashboard'); ?>">
        <span class="bn-icon"><i class="fas fa-home"></i></span>
        <span class="bn-label">হোম</span>
    </a>

    <a href="/Customer/customers.php"
       class="bn-item<?php echo $bnActiveClass('customers'); ?>">
        <span class="bn-icon"><i class="fas fa-users"></i></span>
        <span class="bn-label">কাস্টমার</span>
    </a>

    <!-- ── Elevated center speed-dial button → Inventory ────────── -->
    <a href="../Inventory/inventory_dashboard.php"
       class="bn-item bn-center<?php echo $bnActiveClass('inventory'); ?>"
       aria-label="ইনভেন্টরি">
        <span class="bn-icon"><i class="fas fa-cart-plus"></i></span>
        <span class="bn-label">ইনভেন্টরি</span>
    </a>

    <button type="button"
            class="bn-item<?php echo $bnActiveClass('menu'); ?>"
            onclick="toggleSidebar()"
            aria-label="মেনু">
        <span class="bn-icon"><i class="fas fa-th"></i></span>
        <span class="bn-label">মেনু</span>
        <?php if ($bnBadgeCount !== ''): ?>
            <span class="nav-badge"><?php echo htmlspecialchars($bnBadgeCount, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </button>
 <!-- ── এডমিন লগইন করা থাকলে নোটিফিকেশন এবং স্টাফ রিপোর্ট দেখাবে ── -->
    <?php if (isset($_sbUserRole) && $_sbUserRole === 'admin'): ?>
        <a href="../Staff/index.php" class="bn-item">
            <span class="bn-icon"><i class="fas fa-users"></i></span>
            <span class="bn-label">স্টাফ রিপোর্ট</span>
        </a>
    <?php endif; ?>

    <!-- ── সাধারণ ইউজার লগইন করা থাকলে প্রোফাইল দেখতে পাবেন ── -->
    <?php if (isset($_sbUserRole) && $_sbUserRole !== 'admin'): ?>
        <a href="Profile/"
           class="bn-item<?php echo $bnActiveClass('profile'); ?>">
            <span class="bn-icon"><i class="fas fa-user"></i></span>
            <span class="bn-label">প্রোফাইল</span>
        </a>
    <?php endif; ?>

</nav>

</nav>
