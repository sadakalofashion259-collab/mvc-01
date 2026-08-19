<?php
/**
 * VIEW: dashboard/notification_modal.php
 * ─────────────────────────────────────────────────────────────
 * CSS Classes Used:
 *   .sk-modal          → full-screen modal backdrop
 *   .sk-modal-box      → centered modal content box
 *   .sk-modal-head     → modal title + close button row
 *   .sk-modal-title    → "ব্রডকাস্ট নোটিস" heading
 *   .sk-modal-close    → × close button
 *   .sk-modal-body     → scrollable notice list area
 *   .sk-modal-foot     → send-message form area
 *   .notif-item        → individual notice row
 *   .notif-time        → time stamp under notice
 *   .sk-field          → form field wrapper
 *   .sk-label          → field label
 *   .sk-input-wrap     → input + icon wrapper
 *   .sk-input-icon     → left icon inside input
 *   .sk-input          → styled textarea/input
 *   .btn-prem          → premium button base class
 *   .btn-blue          → blue variant button
 * CSS Variables Used:
 *   var(--muted)       → muted text color
 *   var(--gold)        → gold accent
 * ─────────────────────────────────────────────────────────────
 * Required vars:
 *   $dashBroadcastNotices  array   — broadcast notices list
 *   $dashCsrfToken         string  — CSRF token value
 */
?>

<!-- ══ NOTIFICATION MODAL (Bottom Sheet) ══════════════════════
     CSS: .sk-modal | .sk-modal-box | .sk-modal-head | .sk-modal-body
          .sk-modal-foot | .notif-item | .notif-time
          .sk-field | .sk-label | .sk-input-wrap | .sk-input-icon
          .sk-input | .btn-prem | .btn-blue
     File: assets/style_css/premium.css
══════════════════════════════════════════════════════════════ -->
<div id="msgModal" class="sk-modal" style="display:none;" role="dialog" aria-modal="true" aria-label="নোটিফিকেশন">
    <div class="sk-modal-box">

        <!-- Modal Header -->
        <div class="sk-modal-head">
            <div class="sk-modal-title">
                <i class="fas fa-bell"></i> ব্রডকাস্ট নোটিস
            </div>
            <button class="sk-modal-close"
                    onclick="document.getElementById('msgModal').style.display='none'"
                    type="button"
                    aria-label="বন্ধ করুন">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body — Notices List -->
        <div class="sk-modal-body">
            <p style="font-size:10px;font-weight:800;color:var(--muted);margin:0 0 10px;text-transform:uppercase;letter-spacing:.8px;">
                সর্বশেষ আলার্ট
            </p>

            <?php if (!empty($dashBroadcastNotices)): ?>
                <?php foreach ($dashBroadcastNotices as $broadcastNotice): ?>
                <div class="notif-item">
                    <i class="fas fa-bullhorn" style="color:var(--gold);margin-right:7px;"></i>
                    <?php echo nl2br(htmlspecialchars((string) $broadcastNotice['message'], ENT_QUOTES, 'UTF-8')); ?>
                    <div class="notif-time">
                        <i class="fas fa-clock"></i>
                        <?php echo date('d M &bull; h:i A', strtotime((string) $broadcastNotice['created_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center;padding:24px 10px;color:var(--muted);">
                    <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;opacity:.4;"></i>
                    <span style="font-size:12px;font-weight:600;">কোনো নতুন আলার্ট নেই</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Modal Footer — Send Message Form -->
        <div class="sk-modal-foot">
            <form method="POST" action="dashboard.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($dashCsrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="sk-field">
                    <label class="sk-label">
                        <i class="fas fa-pen"></i> অ্যাডমিনকে বার্তা পাঠান
                    </label>
                    <div class="sk-input-wrap">
                        <i class="fas fa-pen sk-input-icon" style="top:16px;transform:none;"></i>
                        <textarea name="msg_text"
                                  class="sk-input"
                                  placeholder="আপনার বার্তা লিখুন..."
                                  required
                                  maxlength="500"
                                  aria-label="বার্তা"></textarea>
                    </div>
                </div>
                <button type="submit" name="send_msg_to_admin" class="btn-prem btn-blue">
                    <i class="fas fa-paper-plane"></i> বার্তা পাঠান
                </button>
            </form>
        </div>

    </div>
</div>
