<?php
/**
 * VIEW: login/notice_bar.php
 * CSS: .notice-bar | .notice-content | @keyframes marquee
 * Vars: $loginSiteNoticeText
 */
?>
<div class="notice-bar">
    <div class="notice-content">
        <span>🔔 ═════ সাদা-কালো ফ্যাশন ═════ <?php echo htmlspecialchars($loginSiteNoticeText ?? 'সিস্টেমে স্বাগতম', ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
</div>
