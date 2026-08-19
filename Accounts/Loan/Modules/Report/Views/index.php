<?php
/**
 * রিপোর্ট — তারিখ অনুযায়ী।
 * Available: $pageTitle, $fromDate, $toDate, $csrfToken, $baseUrl
 */
?>

<!-- প্রিন্ট আউটের উপরে শুধু প্রিন্টেই দেখা যাবে -->
<div class="print-head">
    <div class="ph-brand">সাদা কালো ফ্যাশন</div>
    <div class="ph-sub">লোন রিপোর্ট — <?= Security::e($fromDate) ?> থেকে <?= Security::e($toDate) ?></div>
</div>

<div class="hero">
    <div class="hero-scrim"></div>
    <div class="hero-cap">
        <div class="hero-logo"><i class="fas fa-chart-column"></i></div>
        <div class="hero-text">
            <div class="hero-title">রিপোর্ট</div>
            <div class="hero-sub">তারিখ অনুযায়ী হিসাব</div>
        </div>
    </div>
</div>

<div class="app-card mt-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-6"><label class="field-label">শুরুর তারিখ</label>
                <input type="date" id="fromDate" class="app-input" value="<?= Security::e($fromDate) ?>"></div>
            <div class="col-6"><label class="field-label">শেষ তারিখ</label>
                <input type="date" id="toDate" class="app-input" value="<?= Security::e($toDate) ?>"></div>
        </div>
        <button type="button" id="btnRun" class="app-btn btn-blue mt-3" style="width:100%;"><i class="fas fa-magnifying-glass"></i> রিপোর্ট দেখুন</button>
    </div>
</div>

<!-- কম্প্যাক্ট সামারি — পাশাপাশি তিনটা চিকন কার্ড -->
<div class="rpt-summary mt-3">
    <div class="rpt-mini t-blue"><div class="mi"><i class="fas fa-arrow-down"></i></div><div class="mv" id="rBorrowed">৳0.00</div><div class="ml">নিয়েছি</div></div>
    <div class="rpt-mini t-green"><div class="mi"><i class="fas fa-money-bill-wave"></i></div><div class="mv" id="rRepaid">৳0.00</div><div class="ml">শোধ</div></div>
    <div class="rpt-mini t-purple"><div class="mi"><i class="fas fa-coins"></i></div><div class="mv" id="rInterest">৳0.00</div><div class="ml">সুদ</div></div>
</div>
<!-- মোট বাকি — চিকন হাইলাইট স্ট্রিপ -->
<div class="rpt-baki mt-2">
    <div class="rb-left"><span class="rb-ic"><i class="fas fa-file-invoice-dollar"></i></span> মোট বাকি</div>
    <div class="rb-amt" id="rOutstanding">৳0.00</div>
</div>

<!-- লোনের অগ্রগতি — কার্ড ভিত্তিক, পাশে হালকা ছায়া -->
<div class="mt-4 mb-4">
    <div class="sec-title"><i class="fas fa-file-invoice-dollar" style="color:var(--brand-1)"></i> লোনভিত্তিক রিপোর্ট</div>
    <div id="progressCards" class="prog-list">
        <div class="loading"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
    <div class="text-center mt-3">
        <button type="button" onclick="window.print()" class="app-btn btn-dark" style="font-size:.72rem;"><i class="fas fa-print"></i> প্রিন্ট / ডাউনলোড</button>
    </div>
</div>

<!-- লেজার ড্রয়ার — রিপোর্ট থেকে যেকোনো লোনের হিসাব চেক -->
<div class="ledger-drawer-backdrop" id="ledgerDrawer">
    <div class="ledger-drawer" role="dialog" aria-label="লেজার">
        <div class="ld-head">
            <div class="ld-head-row">
                <button type="button" class="ld-close" id="ldClose"><i class="fas fa-arrow-right"></i></button>
                <div style="flex:1;min-width:0;">
                    <div class="ld-title" id="ldName">—</div>
                    <div class="ld-acc" id="ldAcc">—</div>
                </div>
            </div>
            <div class="ld-stats">
                <div class="ld-stat"><div class="v" id="ldBal">৳0</div><div class="l">বাকি</div></div>
                <div class="ld-stat"><div class="v" id="ldPaid">৳0</div><div class="l">শোধ</div></div>
                <div class="ld-stat"><div class="v" id="ldInt">৳0</div><div class="l">সুদ</div></div>
            </div>
        </div>
        <div class="ld-body" id="ldBody"><div class="loading"><i class="fas fa-spinner fa-spin"></i></div></div>
        <div class="ld-foot">
            <a class="pdf" id="ldPrint" href="#" target="_blank" rel="noopener"><i class="fas fa-print"></i> প্রিন্ট / ডাউনলোড</a>
        </div>
    </div>
</div>

<script>
$(function () {
    function run() {
        App.post('fetch_report', { from_date: $('#fromDate').val(), to_date: $('#toDate').val() }).done(function (res) {
            if (res.status !== 'success') { App.toast('error', res.message); return; }
            $('#rBorrowed').text(App.money(res.overview.borrowed));
            $('#rRepaid').text(App.money(res.overview.repaid));
            $('#rInterest').text(App.money(res.overview.interestPaid));
            $('#rOutstanding').text(App.money(res.overview.outstanding));

            const box = $('#progressCards').empty();
            if (!res.byLoan.length) { box.html('<div class="empty-state">কোনো তথ্য নেই।</div>'); return; }
            res.byLoan.forEach(function (l) {
                const payable = parseFloat(l.total_payable) || 0;
                const bal = parseFloat(l.current_balance) || 0;
                const paid = parseFloat(l.total_paid) || 0;
                const pct = payable > 0 ? Math.min(100, Math.round(paid / payable * 100)) : 0;
                const inRange = parseFloat(l.paid_in_range) || 0;
                box.append(
                    '<div class="prog-card view-ledger" data-id="' + App.escAttr(l.id) + '" style="cursor:pointer;">' +
                        '<div class="pc-top">' +
                            '<div class="pc-id"><span class="pc-orb"><i class="fas fa-landmark"></i></span>' +
                                '<div class="pc-tx"><div class="pc-name">' + App.escHtml(l.borrower_name) + '</div>' +
                                '<div class="pc-acc">' + App.escHtml(l.account_number) + ' · লেজার দেখুন <i class="fas fa-arrow-right" style="font-size:.6rem"></i></div></div></div>' +
                            '<div class="pc-pct">' + pct + '%</div>' +
                        '</div>' +
                        '<div class="pc-range"><span>এই সময়ে শোধ</span><b class="amt-pos">' + App.money(inRange) + '</b></div>' +
                        '<div class="pc-bar"><span style="width:' + pct + '%"></span></div>' +
                        '<div class="pc-meta"><span>মোট শোধ <b class="amt-pos">' + App.money(paid) + '</b></span>' +
                            '<span>বাকি <b class="amt-neg">' + App.money(bal) + '</b></span></div>' +
                    '</div>'
                );
            });
        }).fail(function () {
            $('#progressCards').html('<div class="empty-state">লোড করা যায়নি।</div>');
        });
    }
    $('#btnRun').on('click', run);
    run();

    // ---------- লেজার ড্রয়ার (Loan মডিউলের endpoint) ----------
    const BASE = App.baseUrl || '<?= Security::e($baseUrl ?? '') ?>';
    function loanPost(action, payload) {
        return $.ajax({ url: BASE + '/loan', method: 'POST',
            data: Object.assign({}, payload || {}, { action: action, csrf_token: App.csrfToken }), dataType: 'json' });
    }
    const drawer = document.getElementById('ledgerDrawer');
    function closeDrawer() { drawer.classList.remove('open'); }
    document.getElementById('ldClose').addEventListener('click', closeDrawer);
    drawer.addEventListener('click', function (e) { if (e.target === drawer) closeDrawer(); });

    function entryHtml(row) {
        const isDebit = parseFloat(row.debit_amount) > 0;
        const amt = isDebit ? row.debit_amount : row.credit_amount;
        const col = isDebit ? 'var(--c-red)' : 'var(--c-green)';
        const tint = isDebit ? 'color-mix(in srgb,var(--c-red) 14%,var(--surface))' : 'color-mix(in srgb,var(--c-green) 14%,var(--surface))';
        const ic = isDebit ? 'fa-arrow-up-long' : 'fa-arrow-down-long';
        const sign = isDebit ? '+' : '−';
        let doc = '';
        if (row.photo_url) {
            doc = '<div class="ld-doc"><img src="' + App.escAttr(row.photo_url) + '" alt="ডকুমেন্ট" onclick="window.open(this.src,\'_blank\')"></div>';
        }
        const note = row.note ? '<div class="ld-enote">“' + App.escHtml(row.note) + '”</div>' : '';
        return '<div class="ld-entry">' +
            '<div class="ld-eic" style="color:' + col + ';background:' + tint + ';"><i class="fas ' + ic + '"></i></div>' +
            '<div class="ld-etx"><div class="ld-edesc">' + App.escHtml(row.description) + '</div>' +
                '<div class="ld-edate">' + App.formatDate(row.txn_date) + '</div>' + note + doc + '</div>' +
            '<div class="ld-eamt"><div class="a" style="color:' + col + '">' + sign + App.money(amt).replace('৳','৳') + '</div>' +
                '<div class="b">ব্যালেন্স ' + App.money(row.balance) + '</div></div>' +
        '</div>';
    }

    function openLedger(loanId) {
        drawer.classList.add('open');
        $('#ldBody').html('<div class="loading"><i class="fas fa-spinner fa-spin"></i></div>');
        document.getElementById('ldPrint').setAttribute('href', BASE + '/loan/print/' + loanId);
        loanPost('fetch_profile', { id: loanId, page: 1 }).done(function (res) {
            if (res.status !== 'success') { $('#ldBody').html('<div class="empty-state">লোড করা যায়নি।</div>'); return; }
            const info = res.info, tot = res.totals;
            $('#ldName').text(info.borrower_name);
            $('#ldAcc').text(info.account_number);
            $('#ldBal').text(App.money(info.current_balance));
            $('#ldPaid').text(App.money(tot.totalPaid));
            $('#ldInt').text(App.money(tot.interestPaid));
            const rows = res.ledger || [];
            $('#ldBody').html(rows.length ? rows.map(entryHtml).join('') : '<div class="empty-state">কোনো এন্ট্রি নেই।</div>');
        }).fail(function () { $('#ldBody').html('<div class="empty-state">লোড করা যায়নি।</div>'); });
    }
    $(document).on('click', '.view-ledger', function () { openLedger($(this).attr('data-id')); });
});
</script>
