<?php
/**
 * VIEW: login/image_slider.php
 * CSS: .slider-container | .slide | .slide.active | .caption
 * Vars: $loginSliderPosts (array)
 */
?>
<?php if (!empty($loginSliderPosts)): ?>
<div class="slider-container">
    <?php foreach ($loginSliderPosts as $si => $sp): ?>
        <div class="slide <?php echo $si === 0 ? 'active' : ''; ?>">
            <img src="<?php echo htmlspecialchars((string)$sp['image_path'], ENT_QUOTES, 'UTF-8'); ?>"
                 alt="Slide <?php echo $si + 1; ?>">
            <?php if (!empty($sp['title'])): ?>
                <div class="caption"><?php echo htmlspecialchars((string)$sp['title'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="slider-container">
    <div style="width:100%;height:100%;background:#111;display:flex;justify-content:center;align-items:center;color:#fff;font-weight:bold;font-size:14px;">
        <i class="fas fa-image" style="margin-right:8px;opacity:.5;"></i> No Image
    </div>
</div>
<?php endif; ?>
