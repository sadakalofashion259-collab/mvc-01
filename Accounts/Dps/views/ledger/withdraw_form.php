<!-- views/ledger/withdraw_form.php — উত্তোলন এন্ট্রি ফর্ম -->
<div class="sheet-overlay" id="sheetWithdraw">
    <div class="bottom-sheet">
        <div class="sheet-handle"></div>
        <h6 class="sheet-title text-danger"><i class="fas fa-arrow-up"></i> উত্তোলন এন্ট্রি</h6>
        <form id="dpsWithdrawForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= SecurityHelper::safeOut($csrfToken) ?>">

            <div class="mb-2">
                <label class="form-label">অ্যাকাউন্ট *</label>
                <select name="withdraw_dps_id" id="withdrawSelectClient" class="form-select" required>
                    <option value="">— অ্যাকাউন্ট সিলেক্ট করুন —</option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">উত্তোলনের পরিমাণ *</label>
                <input type="number" step="0.01" name="dps_withdraw_amount" class="form-control" required>
            </div>
            <a href="javascript:void(0)" class="back-date-toggle" id="withdrawBackDateToggle">
                <i class="fas fa-rotate-left"></i> পিছনের তারিখে উত্তোলন দেখাতে চান?
            </a>
            <div class="mb-3 d-none" id="withdrawDateWrap">
                <label class="form-label">উত্তোলনের তারিখ</label>
                <input type="date" name="txn_date" id="withdrawTxnDate" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
            </div>

            <button type="submit" class="btn btn-danger w-100 compact-btn">
                <i class="fas fa-check"></i> উত্তোলন নিশ্চিত করুন
            </button>
        </form>
    </div>
</div>
