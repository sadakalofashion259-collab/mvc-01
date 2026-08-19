<?php
/**
 * VIEW: dashboard/collection_alerts.php
 * ─────────────────────────────────────────────────────────────
 * CSS Classes Used:
 *   .coll-wrap         → section container card
 *   .coll-header       → title + badge row
 *   .coll-title        → bell icon + "কালেকশন আলার্ট" heading
 *   .coll-count-badge  → alert count pill badge
 *   .coll-scroll       → horizontal scroll track
 *   .coll-card         → individual alert card
 *   .coll-avatar       → 34px customer avatar circle
 *   .coll-shop-name    → shop name text
 *   .coll-contact      → customer name sub-text
 *   .coll-call-btn     → green call button
 * ─────────────────────────────────────────────────────────────
 * Required vars:
 *   $dashCollectionAlerts   array   — from DashboardController::getViewData()
 *     Each item: id, shop_name, customer_name, phone, profile_pic
 */

if (empty($dashCollectionAlerts)) return;
?>

<!-- ══ COLLECTION ALERTS ════════════════════════════════════════
     CSS: .coll-wrap | .coll-header | .coll-title | .coll-count-badge
          .coll-scroll | .coll-card | .coll-avatar
          .coll-shop-name | .coll-contact | .coll-call-btn
     File: assets/style_css/premium.css
══════════════════════════════════════════════════════════════ -->
<div class="coll-wrap">

    <div class="coll-header">
        <div class="coll-title">
            <i class="fas fa-bell"></i> কালেকশন আলার্ট
        </div>
        <span class="coll-count-badge"><?php echo count($dashCollectionAlerts); ?></span>
    </div>

    <div class="coll-scroll" id="collectionTrack">
        <?php foreach ($dashCollectionAlerts as $collectionAlert):
            $cleanPhoneNumber = preg_replace('/[^0-9]/', '', (string) $collectionAlert['phone']);
            $customerAvatarSrc = (!empty($collectionAlert['profile_pic']) && file_exists((string) $collectionAlert['profile_pic']))
                ? htmlspecialchars((string) $collectionAlert['profile_pic'], ENT_QUOTES, 'UTF-8')
                : 'https://placehold.co/34x34/F59E0B/fff?text=C';
        ?>
        <div class="coll-card">
            <a href="customer_profile.php?id=<?php echo (int) $collectionAlert['id']; ?>"
               style="display:flex;align-items:center;gap:8px;text-decoration:none;">
                <img src="<?php echo $customerAvatarSrc; ?>"
                     alt="ছবি"
                     class="coll-avatar"
                     onerror="this.src='https://placehold.co/34x34/F59E0B/fff?text=C'">
                <div>
                    <div class="coll-shop-name">
                        <?php echo htmlspecialchars((string) $collectionAlert['shop_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="coll-contact">
                        <?php echo htmlspecialchars((string) $collectionAlert['customer_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </a>
            <a href="tel:<?php echo $cleanPhoneNumber; ?>" class="coll-call-btn">
                <i class="fas fa-phone-alt"></i> কল করুন
            </a>
        </div>
        <?php endforeach; ?>
    </div>

</div>
