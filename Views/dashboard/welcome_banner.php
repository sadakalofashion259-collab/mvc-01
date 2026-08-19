<?php
/**
 * VIEW: dashboard/welcome_banner.php
 * ─────────────────────────────────────────────────────────────
 * CSS Classes Used:
 *   .dashboard-banner          → banner image container
 *   .dashboard-banner-fallback → fallback flex container when img fails
 *   .welcome-card              → bottom welcome message card
 *   .welcome-hint              → hint text inside welcome card
 *   .shimmer-text              → animated shimmer text effect
 * ─────────────────────────────────────────────────────────────
 * No required vars — static welcome section.
 */
?>

<!-- ══ DASHBOARD BANNER ═════════════════════════════════════════
     CSS: .dashboard-banner | .dashboard-banner-fallback
          .shimmer-text | .welcome-card | .welcome-hint
     File: assets/style_css/premium.css
══════════════════════════════════════════════════════════════ -->

<!-- Banner Image -->
<div class="dashboard-banner" style="margin-top:4px;">
    <img src="banner.jpg"
         alt="Sada Kalo Fashion Banner"
         onerror="this.style.display='none';
                  this.parentElement.innerHTML='<div class=\'dashboard-banner-fallback\'><span class=\'shimmer-text\'>SADA KALO FASHION</span><span style=\'font-size:11px;color:var(--muted);font-weight:700;\'>LEDGER MANAGEMENT SYSTEM</span></div>'">
</div>

