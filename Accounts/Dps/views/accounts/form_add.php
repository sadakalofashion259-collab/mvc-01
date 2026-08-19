<!-- views/accounts/form_add.php — নতুন অ্যাকাউন্ট খোলার ফর্ম (আলাদা shet) -->
<div class="sheet-overlay" id="sheetAddAccount">
    <div class="bottom-sheet">
        <div class="sheet-handle"></div>
        <h6 class="sheet-title"><i class="fas fa-user-plus"></i> নতুন অ্যাকাউন্ট</h6>
        <form id="dpsAccountForm" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= SecurityHelper::safeOut($csrfToken) ?>">

            <div class="mb-2">
                <label class="form-label">গ্রাহকের নাম *</label>
                <input type="text" name="dps_client_name" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">অ্যাকাউন্ট নম্বর *</label>
                <input type="text" name="dps_account_number" class="form-control" required>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label">ধরন</label>
                    <select name="dps_account_type" class="form-select">
                        <option value="DPS">DPS</option>
                        <option value="FDR">FDR</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">ফ্রিকোয়েন্সি</label>
                    <select name="dps_frequency" class="form-select">
                        <option value="monthly">মাসিক</option>
                        <option value="weekly">সাপ্তাহিক</option>
                        <option value="daily">দৈনিক</option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label">কিস্তির পরিমাণ</label>
                    <input type="number" step="0.01" name="dps_installment_amount" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label">মুনাফার হার (%)</label>
                    <input type="number" step="0.01" name="dps_interest_rate" class="form-control">
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label">মেয়াদ (বছর)</label>
                    <input type="number" name="dps_duration_years" class="form-control" value="0" min="0">
                </div>
                <div class="col-6">
                    <label class="form-label">মেয়াদ (মাস)</label>
                    <input type="number" name="dps_duration_months" class="form-control" value="1" min="0" max="11">
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label">প্রারম্ভিক জমা</label>
                <input type="number" step="0.01" name="dps_opening_balance" class="form-control" value="0">
            </div>

            <div class="mb-2">
                <label class="form-label">খোলার তারিখ</label>
                <input type="date" name="txn_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <!-- ★ প্রোফাইল ছবি — গ্যালারি বা সরাসরি ক্যামেরা -->
            <div class="mb-3">
                <label class="form-label">প্রোফাইল ছবি (ঐচ্ছিক, সর্বোচ্চ ১০ এমবি)</label>
                <div class="photo-pick-row">
                    <img id="addPhotoPreview" class="photo-preview" src="assets/img/avatar-placeholder.svg" alt="">
                    <div class="photo-pick-btns">
                        <label class="btn btn-sm btn-soft-primary">
                            <i class="fas fa-image"></i> গ্যালারি
                            <input type="file" name="account_photo" accept="image/*" class="photo-input" data-preview="addPhotoPreview" hidden>
                        </label>
                        <label class="btn btn-sm btn-soft-primary">
                            <i class="fas fa-camera"></i> ক্যামেরা
                            <input type="file" name="account_photo_cam" accept="image/*" capture="environment" class="photo-input" data-preview="addPhotoPreview" data-target-name="account_photo" hidden>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 compact-btn">
                <i class="fas fa-check"></i> অ্যাকাউন্ট তৈরি করুন
            </button>
        </form>
    </div>
</div>
