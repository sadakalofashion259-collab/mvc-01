<?php
/**
 * App Shell — auto-included by every page (via inject script).
 * Outputs: viewport meta, premium.css link with correct relative path,
 * dark-mode preference loader, and an optional bottom nav.
 *
 * Use $APP_SHELL_BASE to override the relative prefix to project root
 * (e.g. set to "../" before including from a sub-directory).
 */
if (!isset($APP_SHELL_BASE)) {
    $APP_SHELL_BASE = '';
}
?>
<link rel="stylesheet" href="<?= htmlspecialchars($APP_SHELL_BASE) ?>assets/style_css/premium.css">
<script>
(function(){
    try {
        var saved = localStorage.getItem('sk_theme');
        if (saved === 'light') document.documentElement.classList.add('pre-light');
        document.addEventListener('DOMContentLoaded', function(){
            if (saved === 'light') document.body.classList.add('light-mode');
        });
    } catch(e) {}
})();
</script>
