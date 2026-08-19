<!-- views/ledger/deposit_form.php — জমা এন্ট্রি ফর্ম (ledger concern, account concern থেকে আলাদা) -->
<div class="sheet-overlay" id="sheetDeposit">
    <div class="bottom-sheet">
        <div class="sheet-handle"></div>
        <h6 class="sheet-title text-success"><i class="fas fa-arrow-down"></i> জমা এন্ট্রি</h6>
        <form id="dpsDepositForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= SecurityHelper::safeOut($csrfToken) ?>">

            <div class="mb-2">
                <label class="form-label">অ্যাকাউন্ট *</label>
                <select name="deposit_dps_id" id="depositSelectClient" class="form-select" required>
                    <option value="">— অ্যাকাউন্ট সিলেক্ট করুন —</option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">জমার পরিমাণ *</label>
                <input type="number" step="0.01" name="dps_deposit_amount" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">পরবর্তী জমার তারিখ (ঐচ্ছিক)</label>
                <input type="date" name="next_deposit_date" class="form-control">
            </div>

            <a href="javascript:void(0)" class="back-date-toggle" id="depositBackDateToggle">
                <i class="fas fa-rotate-left"></i> পিছনের তারিখে জমা দিতে চান?
            </a>
            <div class="mb-3 d-none" id="depositDateWrap">
                <label class="form-label">জমার তারিখ</label>
                <input type="date" name="txn_date" id="depositTxnDate" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
            </div>

            <button type="submit" class="btn btn-success w-100 compact-btn">
                <i class="fas fa-check"></i> জমা নিশ্চিত করুন
            </button>
        </form>
    </div>
</div>
