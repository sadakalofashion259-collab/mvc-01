<?php
/**
 * VIEW: profile/profile_hero.php
 * ─────────────────────────────────────────────────────────────
 * CSS Classes Used:
 *   .profile-hero     → centered hero section wrapper
 *   .hero-avatar      → 80px circular profile image
 *   .hero-name        → username heading below avatar
 *   .hero-badges      → row of badge chips
 *   .hero-badge       → individual badge chip
 *   .badge-id         → fingerprint ID badge style
 *   .badge-admin      → gold admin badge style
 *   .badge-staff      → blue staff badge style
 *   .badge-active     → green active status badge
 *   .badge-inactive   → red inactive status badge
 *   .hero-joined      → joining date row with calendar icon
 * ─────────────────────────────────────────────────────────────
 * Required vars:
 *   $profileUId            string  — user ID (escaped)
 *   $profileUUsername      string  — username (escaped)
 *   $profileUPic           string  — profile pic path (escaped)
 *   $profileRoleLabel      string  — "👑 ADMIN" | "👤 STAFF"
 *   $profileRoleBadgeClass string  — 'badge-admin' | 'badge-staff'
 *   $profileStatusClass    string  — 'badge-active' | 'badge-inactive'
 *   $profileUStatus        string  — 'active' | other
 *   $profileUJoined        string  — formatted joining date
 */
?>

<!-- ══ PROFILE HERO SECTION ═════════════════════════════════════
     CSS: .profile-hero | .hero-avatar | .hero-name | .hero-badges
          .hero-badge | .badge-id | .badge-admin | .badge-staff
          .badge-active | .badge-inactive | .hero-joined
     File: assets/style_css/premium.css
══════════════════════════════════════════════════════════════ -->
<div class="profile-hero">

    <!-- Avatar with camera edit button -->
    <div style="position:relative;display:inline-block;margin-bottom:4px;">
        <img id="heroAvatar"
             src="<?php echo $profileUPic ?? 'default_user.png'; ?>"
             alt="<?php echo $profileUUsername ?? 'User'; ?>"
             class="hero-avatar"
             onerror="this.src='https://placehold.co/80x80/5B8EFF/fff?text=U'">
        <button onclick="switchTab(2)"
                type="button"
                style="position:absolute;bottom:2px;right:2px;width:26px;height:26px;
                       border-radius:50%;background:var(--primary);color:#fff;
                       border:2px solid var(--bg);font-size:10px;
                       display:flex;align-items:center;justify-content:center;cursor:pointer;"
                title="ছবি পরিবর্তন"
                aria-label="প্রোফাইল ছবি পরিবর্তন">
            <i class="fas fa-camera"></i>
        </button>
    </div>

    <!-- Username -->
    <div class="hero-name"><?php echo $profileUUsername ?? ''; ?></div>

    <!-- Badge Row -->
    <div class="hero-badges">
        <span class="hero-badge badge-id">
            <i class="fas fa-fingerprint"></i> #<?php echo $profileUId ?? ''; ?>
        </span>
        <span class="hero-badge <?php echo $profileRoleBadgeClass ?? 'badge-staff'; ?>">
            <?php echo $profileRoleLabel ?? '👤 STAFF'; ?>
        </span>
        <span class="hero-badge <?php echo $profileStatusClass ?? 'badge-active'; ?>">
            <i class="fas fa-circle" style="font-size:5px;"></i>
            <?php echo ($profileUStatus ?? '') === 'active' ? 'Active' : 'Inactive'; ?>
        </span>
    </div>

    <!-- Joining Date -->
    <div class="hero-joined">
        <i class="fas fa-calendar-alt"></i> যোগদান: <?php echo $profileUJoined ?? 'N/A'; ?>
    </div>

</div>
