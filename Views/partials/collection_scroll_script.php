<?php
/**
 * PARTIAL: collection_scroll_script.php
 * ─────────────────────────────────────────────────────────────
 * Auto-scroll JavaScript for .coll-scroll (#collectionTrack)
 * Pauses on touch/hover, resumes on leave.
 * ─────────────────────────────────────────────────────────────
 * Required vars: none
 * Only renders if #collectionTrack exists in DOM.
 */
?>

<!-- ══ COLLECTION ALERTS AUTO-SCROLL SCRIPT ════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var scrollTrack = document.getElementById('collectionTrack');
    if (!scrollTrack) return;

    var scrollDirection = 1;
    var autoScrollTimer;

    function startAutoScroll() {
        autoScrollTimer = setInterval(function() {
            scrollTrack.scrollLeft += scrollDirection;
            if (scrollTrack.scrollLeft >= scrollTrack.scrollWidth - scrollTrack.clientWidth) {
                scrollDirection = -1;
            } else if (scrollTrack.scrollLeft <= 0) {
                scrollDirection = 1;
            }
        }, 14);
    }

    startAutoScroll();

    /* Pause on touch */
    scrollTrack.addEventListener('touchstart', function() { clearInterval(autoScrollTimer); });
    scrollTrack.addEventListener('touchend',   function() { startAutoScroll(); });

    /* Pause on hover */
    scrollTrack.addEventListener('mouseover',  function() { clearInterval(autoScrollTimer); });
    scrollTrack.addEventListener('mouseleave', function() { startAutoScroll(); });
});
</script>
