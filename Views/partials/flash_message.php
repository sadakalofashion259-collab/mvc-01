<?php
/**
 * PARTIAL: flash_message.php
 * ─────────────────────────────────────────────────────────────
 * CSS Classes Used:
 *   .alert-box       → flash message container
 *   .alert-success   → green success variant
 *   .alert-error     → red error variant
 *   .alert-close     → × dismiss button inside alert
 * ─────────────────────────────────────────────────────────────
 * Required vars:
 *   $flashSuccess    bool|null  — true=success, false=error, null=no flash
 *   $flashMessage    string     — message text (NOT pre-escaped; escaped here)
 *   $flashAlertId    string     — optional HTML id for the element (default: flashAlert)
 */

// ── Session-based flash (dashboard-style) ─────────────────────
$_flashIsSuccess = $flashSuccess ?? null;
$_flashText      = $flashMessage ?? '';
$_flashId        = $flashAlertId ?? 'flashAlert';

// Also handle session-based flash from DashboardController
if ($_flashIsSuccess === null && isset($_SESSION['success_msg'])) {
    $_flashIsSuccess = true;
    $_flashText      = (string) $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
} elseif ($_flashIsSuccess === null && isset($_SESSION['error_msg'])) {
    $_flashIsSuccess = false;
    $_flashText      = (string) $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

if ($_flashIsSuccess === null || $_flashText === '') return;
?>

<!-- ══ FLASH MESSAGE ══════════════════════════════════════════
     CSS: .alert-box | .alert-success | .alert-error | .alert-close
     File: assets/style_css/premium.css
══════════════════════════════════════════════════════════════ -->
<div class="alert-box <?php echo $_flashIsSuccess ? 'alert-success' : 'alert-error'; ?>"
     id="<?php echo htmlspecialchars($_flashId, ENT_QUOTES, 'UTF-8'); ?>">
    <i class="fas <?php echo $_flashIsSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
    <span><?php echo htmlspecialchars($_flashText, ENT_QUOTES, 'UTF-8'); ?></span>
    <button class="alert-close"
            onclick="this.parentElement.style.display='none'"
            type="button"
            aria-label="বন্ধ করুন">&times;</button>
</div>
