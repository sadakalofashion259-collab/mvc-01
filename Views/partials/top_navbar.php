<?php
/**
 * PARTIAL: top_navbar.php
 * ─────────────────────────────────────────────────────────────
 * CSS Classes Used:
 *   .top-navbar          → fixed sticky navbar wrapper
 *   .nav-menu-btn        → hamburger icon button (left)
 *   .nav-brand           → brand logo + text block
 *   .nav-logo            → brand image tag
 *   .nav-brand-texts     → brand name + sub text wrapper
 *   .nav-brand-name      → "SADA KALO FASHION" heading
 *   .nav-brand-sub       → sub-heading (date / page name)
 *   .nav-actions         → right-side action buttons group
 *   .nav-theme-btn       → theme toggle icon button
 *   .nav-icon-btn        → bell / home icon button
 *   .nav-badge           → red count badge on bell
 *   .nav-profile-wrap    → profile avatar + dropdown container
 *   .nav-profile-btn     → clickable profile avatar
 *   .nav-profile-menu    → dropdown menu list
 * ─────────────────────────────────────────────────────────────
 * Required vars (passed from view):
 *   $navbarSubTitle      string  — sub-heading text
 *   $navbarRole          string  — 'admin' | 'staff'
 *   $navbarNotifBadge    string  — HTML badge string or ''
 *   $navbarProfilePic    string  — safe escaped profile pic path
 *   $navbarUsername      string  — safe escaped username
 *   $navbarBellHref      string  — bell link href
 */
?>
<!-- ══ TOP NAVBAR ══════════════════════════════════════════════
     CSS: .top-navbar | .nav-menu-btn | .nav-brand | .nav-actions
          .nav-theme-btn | .nav-icon-btn | .nav-badge
          .nav-profile-wrap | .nav-profile-btn | .nav-profile-menu
     File: assets/style_css/premium.css
══════════════════════════════════════════════════════════════ -->
<header class="top-navbar">

    <!-- Hamburger -->
    <button class="nav-menu-btn" onclick="toggleSidebar()" aria-label="মেনু খুলুন" type="button">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Brand -->
    <div class="nav-brand">
        <img src="logo.png" class="nav-logo" alt="Logo"
             onerror="this.src='https://placehold.co/34x34/5B8EFF/fff?text=SK'">
        <div class="nav-brand-texts">
            <div class="nav-brand-name">SADA KALO FASHION</div>
            <div class="nav-brand-sub"><?php echo htmlspecialchars($navbarSubTitle ?? ('LEDGER — ' . date('d.M.Y')), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </div>

    <!-- Right Actions -->
    <div class="nav-actions">

        <!-- Theme Toggle -->
        <button class="nav-theme-btn" onclick="toggleTheme()" title="থিম পরিবর্তন" aria-label="থিম পরিবর্তন" type="button">
            <i class="fas fa-moon" id="navThemeIcon"></i>
        </button>

        <!-- Bell / Notification -->
        <?php if (($navbarRole ?? '') === 'admin'): ?>
            <a href="<?php echo htmlspecialchars($navbarBellHref ?? '../admin/admin_panel.php', ENT_QUOTES, 'UTF-8'); ?>"
               class="nav-icon-btn" title="অ্যাডমিন">
                <i class="fas fa-bell"></i>
                <?php echo $navbarNotifBadge ?? ''; ?>
            </a>
        <?php else: ?>
            <button type="button" class="nav-icon-btn" title="নোটিস"
                    onclick="document.getElementById('msgModal').style.display='flex'"
                    aria-label="নোটিফিকেশন দেখুন">
                <i class="fas fa-bell"></i>
                <?php echo $navbarNotifBadge ?? ''; ?>
            </button>
        <?php endif; ?>

        <!-- Profile Avatar + Dropdown -->
        <div class="nav-profile-wrap" id="profileDropdownContainer">
            <div class="nav-profile-btn"
                 onclick="toggleProfileDropdown(event)"
                 role="button"
                 tabindex="0"
                 aria-label="প্রোফাইল মেনু">
                <img src="<?php echo $navbarProfilePic ?? 'default_user.png'; ?>"
                     alt="<?php echo $navbarUsername ?? 'User'; ?>"
                     onerror="this.src='https://placehold.co/34x34/5B8EFF/fff?text=U'">
            </div>
            <div id="profileMenu" class="nav-profile-menu" role="menu">
                <a href="Profile/" role="menuitem"><i class="fas fa-user"></i> আমার প্রোফাইল</a>
                <a href="Profile/" role="menuitem"><i class="fas fa-cog"></i> সেটিংস</a>
                <a href="logout.php" role="menuitem"><i class="fas fa-sign-out-alt"></i> লগ আউট</a>
            </div>
        </div>

    </div>
</header>
