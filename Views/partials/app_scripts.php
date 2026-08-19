<?php
/**
 * PARTIAL: app_scripts.php
 * ─────────────────────────────────────────────────────────────
 * Shared JavaScript for all pages.
 * Covers:
 *   - toggleSidebar()        → .prem-sidebar.open, .prem-overlay.active
 *   - toggleAdminPanel()     → #adminCorePanel.show, #adminPanelIcon
 *   - _applyTheme(isLight)   → body.light-mode + icon/label sync
 *   - toggleTheme()          → localStorage 'sk_theme'
 *   - updateLiveClock()      → #liveClock
 *   - toggleProfileDropdown()→ #profileMenu
 *   - theme init IIFE        → no flash on load
 * ─────────────────────────────────────────────────────────────
 * Required vars: none
 * Usage: include BEFORE </body>, after Bootstrap JS
 */
?>

<!-- ══ APP SHARED SCRIPTS ═══════════════════════════════════════
     Covers: sidebar, theme, clock, profile dropdown
══════════════════════════════════════════════════════════════ -->
<script>
/* ── Sidebar Toggle ──────────────────────────────────────────── */
function toggleSidebar() {
    var sidebar = document.getElementById('mySidebar');
    var overlay = document.getElementById('myOverlay');
    if (!sidebar || !overlay) return;
    var isOpen = sidebar.classList.toggle('open');
    overlay.classList.toggle('active', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}

/* ── Admin Panel Folder ──────────────────────────────────────── */
function toggleAdminPanel() {
    var adminPanel  = document.getElementById('adminCorePanel');
    var toggleIcon  = document.getElementById('adminPanelIcon');
    if (!adminPanel) return;
    var isShowing = adminPanel.classList.toggle('show');
    if (toggleIcon) {
        toggleIcon.className = isShowing ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    }
}

/* ── Theme Apply ─────────────────────────────────────────────── */
function _applyTheme(isLight) {
    document.body.classList.toggle('light-mode', isLight);

    /* Navbar icon */
    var navThemeIcon = document.getElementById('navThemeIcon');
    if (navThemeIcon) navThemeIcon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';

    /* Sidebar icon + label */
    var sidebarThemeIcon  = document.getElementById('themeIcon');
    var sidebarThemeLabel = document.getElementById('themeLabel');
    if (sidebarThemeIcon)  sidebarThemeIcon.className  = isLight ? 'fas fa-sun' : 'fas fa-moon';
    if (sidebarThemeLabel) sidebarThemeLabel.textContent = isLight ? 'লাইট মোড' : 'ডার্ক মোড';

    /* Section pill */
    var sectionThemeBtn   = document.getElementById('sectionThemeBtn');
    var sectionThemeIcon  = document.getElementById('sectionThemeIcon');
    var sectionThemeLabel = document.getElementById('sectionThemeLabel');
    if (sectionThemeBtn) {
        sectionThemeBtn.className = 'section-theme-pill ' + (isLight ? 'is-light' : 'is-dark');
    }
    if (sectionThemeIcon)  sectionThemeIcon.className   = isLight ? 'fas fa-sun' : 'fas fa-moon';
    if (sectionThemeLabel) sectionThemeLabel.textContent = isLight ? 'লাইট মোড' : 'ডার্ক মোড';
}

/* ── Theme Toggle ────────────────────────────────────────────── */
function toggleTheme() {
    var isLight = !document.body.classList.contains('light-mode');
    try { localStorage.setItem('sk_theme', isLight ? 'light' : 'dark'); } catch(e) {}
    _applyTheme(isLight);
}

/* ── Theme Init (no flash) ───────────────────────────────────── */
(function() {
    var savedIsLight = false;
    try { savedIsLight = localStorage.getItem('sk_theme') === 'light'; } catch(e) {}
    if (savedIsLight) document.body.classList.add('light-mode');
    document.addEventListener('DOMContentLoaded', function() {
        _applyTheme(savedIsLight);
    });
})();

/* ── Live Clock ──────────────────────────────────────────────── */
function updateLiveClock() {
    var now         = new Date();
    var hours       = now.getHours();
    var minutes     = now.getMinutes();
    var seconds     = now.getSeconds();
    var amPm        = hours >= 12 ? 'PM' : 'AM';
    hours           = hours % 12 || 12;
    var clockElement = document.getElementById('liveClock');
    if (clockElement) {
        clockElement.textContent =
            String(hours).padStart(2, '0')   + ' : ' +
            String(minutes).padStart(2, '0') + ' : ' +
            String(seconds).padStart(2, '0') + ' ' + amPm;
    }
}
setInterval(updateLiveClock, 1000);
updateLiveClock();

/* ── Profile Dropdown ────────────────────────────────────────── */
function toggleProfileDropdown(e) {
    e.stopPropagation();
    var dropdownMenu = document.getElementById('profileMenu');
    if (dropdownMenu) {
        dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
    }
}
document.addEventListener('click', function(e) {
    var dropdownContainer = document.getElementById('profileDropdownContainer');
    var dropdownMenu      = document.getElementById('profileMenu');
    if (dropdownContainer && !dropdownContainer.contains(e.target) && dropdownMenu) {
        dropdownMenu.style.display = 'none';
    }
});

/* ── Flash Auto-Dismiss ──────────────────────────────────────── */
setTimeout(function() {
    var flashElement = document.getElementById('flashAlert') || document.getElementById('flashMsg');
    if (flashElement) {
        flashElement.style.transition = 'opacity .3s';
        flashElement.style.opacity = '0';
    }
}, 3500);
</script>
