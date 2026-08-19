                &bull; সর্বোচ্চ ২০MB পর্যন্ত আপলোড করা যাবে।<br>                    JPG &bull; PNG &bull; GIF &bull; WEBP &bull; সর্বোচ্চ ২০MB<?php
/**
 * VIEW: profile/profile_tabs.php
 * ─────────────────────────────────────────────────────────────
 * CSS Classes Used:
 *   .sk-tabs          → horizontal tab bar wrapper
 *   .sk-tab-btn       → individual tab button
 *   .active           → active tab modifier
 *   .sk-tab-panel     → tab content panel (hidden/shown)
 *   .sk-card          → white card inside each panel
 *   .sk-card-title    → card section heading
 *   .info-grid-2      → 2-column info grid
 *   .info-cell        → individual info cell
 *   .info-cell-label  → label inside info cell
 *   .info-cell-value  → value inside info cell
 *   .sk-field         → form field wrapper
 *   .sk-label         → field label
 *   .sk-input-wrap    → input + icon row
 *   .sk-input-icon    → absolute left icon
 *   .sk-input         → styled input/textarea
 *   .sk-pass-toggle   → eye icon toggle button
 *   .sk-field-note    → small note below field group
 *   .strength-bar     → password strength bar track
 *   .strength-fill    → animated strength fill
 *   .strength-txt     → strength label text
 *   .btn-prem         → premium button base
 *   .btn-green        → green save button
 *   .btn-red          → red password button
 *   .btn-blue         → blue upload button
 *   .pass-info-box    → info box in password tab
 *   .current-pic-wrap → current picture preview wrapper
 *   .current-pic-label → "বর্তমান ছবি" caption
 *   .pic-drop-zone    → drag-and-drop upload zone
 *   .pic-file-info    → file name info row
 *   .pic-guide-box    → yellow guide notes box
 * ─────────────────────────────────────────────────────────────
 * Required vars:
 *   $profileActiveTab   int     — 0|1|2 which tab to open
 *   $profileCsrfToken   string  — CSRF token (escaped)
 *   $profileUEmail      string  — email (escaped)
 *   $profileUPhone      string  — phone (escaped)
 *   $profileUMobile     string  — mobile (escaped)
 *   $profileUAddress    string  — address (escaped)
 *   $profileUId         string  — user id (escaped)
 *   $profileUUsername   string  — username (escaped)
 *   $profileRoleLabel   string  — role label
 *   $profileUJoined     string  — joining date
 *   $profileUPic        string  — profile pic path (escaped)
 */
?>

<!-- ══ PROFILE TABS ═════════════════════════════════════════════
     CSS: .sk-tabs | .sk-tab-btn | .active
          .sk-tab-panel | .sk-card | .sk-card-title
          .info-grid-2 | .info-cell | .info-cell-label | .info-cell-value
          .sk-field | .sk-label | .sk-input-wrap | .sk-input-icon | .sk-input
          .sk-pass-toggle | .sk-field-note
          .strength-bar | .strength-fill | .strength-txt
          .btn-prem | .btn-green | .btn-red | .btn-blue
          .pass-info-box | .current-pic-wrap | .pic-drop-zone
          .pic-file-info | .pic-guide-box
     File: assets/style_css/premium.css
══════════════════════════════════════════════════════════════ -->

<!-- Tab Navigation Bar -->
<div class="sk-tabs" role="tablist">
    <button class="sk-tab-btn <?php echo ($profileActiveTab ?? 0) === 0 ? 'active' : ''; ?>"
            onclick="switchTab(0)"
            role="tab"
            type="button"
            aria-selected="<?php echo ($profileActiveTab ?? 0) === 0 ? 'true' : 'false'; ?>">
        <i class="fas fa-user-edit"></i> তথ্য
    </button>
    <button class="sk-tab-btn <?php echo ($profileActiveTab ?? 0) === 1 ? 'active' : ''; ?>"
            onclick="switchTab(1)"
            role="tab"
            type="button"
            aria-selected="<?php echo ($profileActiveTab ?? 0) === 1 ? 'true' : 'false'; ?>">
        <i class="fas fa-lock"></i> পাসওয়ার্ড
    </button>
    <button class="sk-tab-btn <?php echo ($profileActiveTab ?? 0) === 2 ? 'active' : ''; ?>"
            onclick="switchTab(2)"
            role="tab"
            type="button"
            aria-selected="<?php echo ($profileActiveTab ?? 0) === 2 ? 'true' : 'false'; ?>">
        <i class="fas fa-camera"></i> ছবি
    </button>
</div>

<!-- ── TAB PANEL 0: Basic Info ────────────────────────────── -->
<div class="sk-tab-panel <?php echo ($profileActiveTab ?? 0) === 0 ? 'active' : ''; ?>"
     id="panel-0" role="tabpanel">

    <!-- Read-only Info Card -->
    <div class="sk-card">
        <div class="sk-card-title"><i class="fas fa-id-badge"></i> অপরিবর্তনীয় তথ্য</div>
        <div class="info-grid-2">
            <div class="info-cell">
                <div class="info-cell-label"><i class="fas fa-fingerprint"></i> ইউজার আইডি</div>
                <div class="info-cell-value">#<?php echo $profileUId ?? ''; ?></div>
            </div>
            <div class="info-cell">
                <div class="info-cell-label"><i class="fas fa-user"></i> ইউজার নেম</div>
                <div class="info-cell-value"><?php echo $profileUUsername ?? ''; ?></div>
            </div>
            <div class="info-cell">
                <div class="info-cell-label"><i class="fas fa-crown"></i> রোল</div>
                <div class="info-cell-value"><?php echo $profileRoleLabel ?? ''; ?></div>
            </div>
            <div class="info-cell">
                <div class="info-cell-label"><i class="fas fa-calendar"></i> যোগদান</div>
                <div class="info-cell-value"><?php echo $profileUJoined ?? 'N/A'; ?></div>
            </div>
        </div>
        <p class="sk-field-note"><i class="fas fa-lock"></i> ইউজার আইডি ও নেম পরিবর্তন করা যাবে না।</p>
    </div>

    <!-- Editable Info Form Card -->
    <div class="sk-card">
        <div class="sk-card-title"><i class="fas fa-edit"></i> তথ্য সম্পাদনা</div>
        <form method="POST" action="Profile/index.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $profileCsrfToken ?? ''; ?>">
            <input type="hidden" name="action" value="update_basic">

            <div class="sk-field">
                <label class="sk-label" for="profileEmail">
                    <i class="fas fa-envelope"></i> ইমেইল ঠিকানা
                </label>
                <div class="sk-input-wrap">
                    <i class="fas fa-envelope sk-input-icon"></i>
                    <input type="email"
                           id="profileEmail"
                           name="email"
                           class="sk-input"
                           value="<?php echo $profileUEmail ?? ''; ?>"
                           placeholder="ইমেইল ঠিকানা"
                           maxlength="100">
                </div>
            </div>

            <div class="sk-field">
                <label class="sk-label" for="profilePhone">
                    <i class="fas fa-phone"></i> ফোন নম্বর
                </label>
                <div class="sk-input-wrap">
                    <i class="fas fa-phone sk-input-icon"></i>
                    <input type="tel"
                           id="profilePhone"
                           name="phone"
                           class="sk-input"
                           value="<?php echo $profileUPhone ?? ''; ?>"
                           placeholder="ফোন নম্বর"
                           maxlength="20">
                </div>
            </div>

            <div class="sk-field">
                <label class="sk-label" for="profileMobile">
                    <i class="fas fa-mobile-alt"></i> মোবাইল নম্বর
                </label>
                <div class="sk-input-wrap">
                    <i class="fas fa-mobile-alt sk-input-icon"></i>
                    <input type="tel"
                           id="profileMobile"
                           name="mobile"
                           class="sk-input"
                           value="<?php echo $profileUMobile ?? ''; ?>"
                           placeholder="মোবাইল নম্বর"
                           maxlength="20">
                </div>
            </div>

            <div class="sk-field">
                <label class="sk-label" for="profileAddress">
                    <i class="fas fa-map-marker-alt"></i> ঠিকানা
                </label>
                <div class="sk-input-wrap">
                    <i class="fas fa-map-marker-alt sk-input-icon" style="top:14px;transform:none;"></i>
                    <textarea id="profileAddress"
                              name="address"
                              class="sk-input"
                              placeholder="আপনার ঠিকানা"
                              maxlength="500"><?php echo $profileUAddress ?? ''; ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn-prem btn-green">
                <i class="fas fa-save"></i> তথ্য সংরক্ষণ করুন
            </button>
        </form>
    </div>

</div>

<!-- ── TAB PANEL 1: Password ──────────────────────────────── -->
<div class="sk-tab-panel <?php echo ($profileActiveTab ?? 0) === 1 ? 'active' : ''; ?>"
     id="panel-1" role="tabpanel">

    <div class="sk-card">
        <div class="sk-card-title"><i class="fas fa-key"></i> পাসওয়ার্ড পরিবর্তন</div>
        <form method="POST" action="Profile/index.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $profileCsrfToken ?? ''; ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="sk-field">
                <label class="sk-label"><i class="fas fa-lock"></i> বর্তমান পাসওয়ার্ড</label>
                <div class="sk-input-wrap">
                    <i class="fas fa-lock sk-input-icon"></i>
                    <input type="password"
                           id="cur_pass"
                           name="current_password"
                           class="sk-input"
                           placeholder="বর্তমান পাসওয়ার্ড"
                           required
                           autocomplete="current-password">
                    <button type="button" class="sk-pass-toggle" onclick="togglePass('cur_pass',this)" aria-label="দেখুন">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="sk-field">
                <label class="sk-label"><i class="fas fa-key"></i> নতুন পাসওয়ার্ড</label>
                <div class="sk-input-wrap">
                    <i class="fas fa-key sk-input-icon"></i>
                    <input type="password"
                           id="new_pass"
                           name="new_password"
                           class="sk-input"
                           placeholder="নতুন পাসওয়ার্ড (কমপক্ষে ৬ অক্ষর)"
                           required
                           autocomplete="new-password"
                           oninput="checkPasswordStrength(this.value)">
                    <button type="button" class="sk-pass-toggle" onclick="togglePass('new_pass',this)" aria-label="দেখুন">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-txt" id="strengthLabel"></div>
            </div>

            <div class="sk-field">
                <label class="sk-label"><i class="fas fa-check-double"></i> পাসওয়ার্ড নিশ্চিত</label>
                <div class="sk-input-wrap">
                    <i class="fas fa-check-double sk-input-icon"></i>
                    <input type="password"
                           id="conf_pass"
                           name="confirm_password"
                           class="sk-input"
                           placeholder="পাসওয়ার্ড আবার লিখুন"
                           required
                           autocomplete="new-password"
                           oninput="checkPasswordMatch()">
                    <button type="button" class="sk-pass-toggle" onclick="togglePass('conf_pass',this)" aria-label="দেখুন">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div id="matchMsg" style="font-size:11px;font-weight:800;margin-top:5px;"></div>
            </div>

            <div class="pass-info-box">
                <p>
                    <i class="fas fa-info-circle"></i>
                    পাসওয়ার্ড কমপক্ষে ৬ অক্ষর হতে হবে।<br>
                    বর্তমান পাসওয়ার্ডের মতো নতুন পাসওয়ার্ড দেওয়া যাবে না।
                </p>
            </div>

            <button type="submit" class="btn-prem btn-red">
                <i class="fas fa-lock"></i> পাসওয়ার্ড পরিবর্তন করুন
            </button>
        </form>
    </div>

</div>

<!-- ── TAB PANEL 2: Profile Picture ──────────────────────── -->
<div class="sk-tab-panel <?php echo ($profileActiveTab ?? 0) === 2 ? 'active' : ''; ?>"
     id="panel-2" role="tabpanel">

    <div class="sk-card">
        <div class="sk-card-title"><i class="fas fa-image"></i> প্রোফাইল ছবি</div>

        <!-- Current Picture Preview -->
        <div class="current-pic-wrap">
            <span class="current-pic-label"><i class="fas fa-image"></i> বর্তমান ছবি</span>
            <img src="<?php echo $profileUPic ?? 'default_user.png'; ?>"
                 alt="বর্তমান ছবি"
                 onerror="this.src='https://placehold.co/76x76/5B8EFF/fff?text=U'">
        </div>

        <!-- Upload Form -->
        <form method="POST" action="Profile/index.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $profileCsrfToken ?? ''; ?>">
            <input type="hidden" name="action" value="update_picture">
            <input type="hidden" name="MAX_FILE_SIZE" value="20971520">

            <input type="file"
                   id="picInput"
                   name="profile_pic"
                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                   style="display:none;"
                   aria-label="ছবি নির্বাচন করুন">

            <!-- Drag & Drop Zone -->
            <div class="pic-drop-zone"
                 id="picUploadArea"
                 onclick="document.getElementById('picInput').click()"
                 ondragover="handlePicDragOver(event)"
                 ondragleave="handlePicDragLeave(event)"
                 ondrop="handlePicDrop(event)"
                 role="button"
                 tabindex="0"
                 aria-label="ছবি নির্বাচন বা ড্র্যাগ করুন">
                <i class="fas fa-cloud-upload-alt"
                   style="font-size:36px;color:var(--muted);display:block;margin-bottom:9px;"></i>
                <p style="font-size:13px;font-weight:700;color:var(--muted);margin:0 0 4px;">
                    ছবি নির্বাচন বা ড্র্যাগ করুন
                </p>
                <p style="font-size:10px;color:var(--dim);margin:0;">
                    JPG &bull; PNG &bull; GIF &bull; WEBP &bull; সর্বোচ্চ ২০MB
                </p>
            </div>

            <!-- Preview -->
            <div id="picPreviewWrap" style="display:none;text-align:center;margin-bottom:14px;position:relative;">
                <img id="picPreviewImg"
                     src=""
                     alt="প্রিভিউ"
                     style="width:84px;height:84px;border-radius:50%;border:3px solid var(--primary);
                            object-fit:cover;box-shadow:0 0 18px var(--pglow);display:inline-block;">
                <button type="button"
                        onclick="removePicSelection()"
                        style="position:absolute;top:50%;left:calc(50% + 32px);transform:translateY(-50%);
                               width:24px;height:24px;border-radius:50%;background:var(--red);color:#fff;
                               display:flex;align-items:center;justify-content:center;font-size:10px;cursor:pointer;"
                        aria-label="ছবি সরান">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- File Info Row -->
            <div class="pic-file-info" id="picFileInfo">
                <i class="fas fa-file-image"></i> <span id="picFileName"></span>
            </div>

            <!-- Upload Submit Button -->
            <button type="submit" id="picSubmitBtn" class="btn-prem btn-blue" style="display:none;">
                <i class="fas fa-upload"></i> ছবি আপলোড করুন
            </button>
        </form>

        <!-- Upload Guidelines -->
        <div class="pic-guide-box">
            <p>
                <i class="fas fa-lightbulb"></i> নির্দেশিকা:<br>
                &bull; সর্বোচ্চ ২০MB পর্যন্ত আপলোড করা যাবে।<br>
                &bull; JPG, PNG, GIF, WEBP সব ফরম্যাট সাপোর্ট করে।<br>
                &bull; ছবি স্বয়ংক্রিয়ভাবে ৮০০×৮০০px এ resize হবে।<br>
                &bull; বর্গাকার (1:1) ছবি সবচেয়ে ভালো দেখায়।<br>
                &bull; পুরোনো ছবি স্বয়ংক্রিয়ভাবে মুছে যাবে।
            </p>
        </div>
    </div>

</div>
