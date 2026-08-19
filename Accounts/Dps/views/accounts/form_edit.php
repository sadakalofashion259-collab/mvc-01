<!-- views/accounts/form_edit.php — অ্যাকাউন্ট তথ্য এডিট (আলাদা shet, add form থেকে সম্পূর্ণ আলাদা) -->
<div class="sheet-overlay" id="sheetEditAccount">
    <div class="bottom-sheet">
        <div class="sheet-handle"></div>
        <h6 class="sheet-title"><i class="fas fa-pen"></i> অ্যাকাউন্ট তথ্য এডিট</h6>
        <form id="dpsEditAccountForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= SecurityHelper::safeOut($csrfToken) ?>">
            <input type="hidden" name="acc_id" id="editAccId">

            <div class="mb-2">
                <label class="form-label">গ্রাহকের নাম *</label>
                <input type="text" name="client_name" id="editClientName" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">অ্যাকাউন্ট নম্বর *</label>
                <input type="text" name="account_number" id="editAccountNumber" class="form-control" required>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label">ধরন</label>
                    <select name="account_type" id="editAccountType" class="form-select">
                        <option value="DPS">DPS</option>
                        <option value="FDR">FDR</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">ফ্রিকোয়েন্সি</label>
                    <select name="frequency" id="editFrequency" class="form-select">
                        <option value="monthly">মাসিক</option>
                        <option value="weekly">সাপ্তাহিক</option>
                        <option value="daily">দৈনিক</option>
                    </select>
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label">কিস্তির পরিমাণ</label>
                    <input type="number" step="0.01" name="installment_amount" id="editInstallment" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label">মুনাফার হার (%)</label>
                    <input type="number" step="0.01" name="interest_rate" id="editRate" class="form-control">
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label">মেয়াদ (বছর)</label>
                    <input type="number" name="duration_years" id="editDurationYears" class="form-control" value="0" min="0">
                </div>
                <div class="col-6">
                    <label class="form-label">মেয়াদ (মাস)</label>
                    <input type="number" name="duration_extra_months" id="editDurationMonths" class="form-control" value="1" min="0" max="11">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 compact-btn">
                <i class="fas fa-save"></i> তথ্য আপডেট করুন
            </button>
        </form>

        <hr>

        <!-- ছবি পরিবর্তন — এডিট ফর্ম থেকে সম্পূর্ণ আলাদা এন্ডপয়েন্ট (upload_account_photo) -->
        <div class="photo-pick-row mt-2">
            <img id="editPhotoPreview" class="photo-preview" src="assets/img/avatar-placeholder.svg" alt="">
            <div class="photo-pick-btns">
                <label class="btn btn-sm btn-soft-primary">
                    <i class="fas fa-image"></i> গ্যালারি থেকে বদলান
                    <input type="file" id="editPhotoGallery" accept="image/*" class="photo-input" data-preview="editPhotoPreview" hidden>
                </label>
                <label class="btn btn-sm btn-soft-primary">
                    <i class="fas fa-camera"></i> ক্যামেরা
                    <input type="file" id="editPhotoCamera" accept="image/*" capture="environment" class="photo-input" data-preview="editPhotoPreview" hidden>
                </label>
            </div>
        </div>
    </div>
</div>
