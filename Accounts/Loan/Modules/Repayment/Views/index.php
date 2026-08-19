<?php
/**
 * কিস্তি শোধের বোর্ড — তারিখভিত্তিক টাইমলাইন (রঙ-কোডেড)।
 * Available: $pageTitle, $csrfToken, $baseUrl, $userRole
 *
 * ব্যাকএন্ড API অপরিবর্তিত: fetch_board, quick_pay (Repayment),
 * এবং লেজার ড্রয়ারের জন্য fetch_profile (Loan মডিউল)।
 */
?>

<div class="hero">
    <div class="hero-scrim"></div>
    <div class="hero-cap">
        <div class="hero-logo"><i class="fas fa-hand-holding-dollar"></i></div>
        <div class="hero-text">
            <div class="hero-title">কিস্তি শোধ</div>
            <div class="hero-sub">কোন লোন কবে — তারিখ অনুযায়ী</div>
        </div>
    </div>
</div>

<!-- তারিখ ও সেই দিনের মোট শোধ -->
<div class="app-card mt-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <span class="field-label mb-0">নির্বাচিত তারিখে মোট শোধ</span>
            <div id="paidTotal" style="font-weight:900;font-size:1.1rem;color:var(--c-green);">৳0.00</div>
        </div>
        <input type="date" id="boardDate" class="app-input" style="width:auto;" value="<?= date('Y-m-d') ?>">
    </div>
</div>

<!-- রঙের অর্থ -->
<div class="app-card mt-3">
    <div class="card-body">
        <div class="kb-legend">
            <span class="kb-leg"><span class="kb-dot d-red"></span> বকেয়া (পার হয়েছে)</span>
            <span class="kb-leg"><span class="kb-dot d-yellow"></span> আজকের</span>
            <span class="kb-leg"><span class="kb-dot d-orange"></span> ৩ দিনের মধ্যে</span>
            <span class="kb-leg"><span class="kb-dot d-green"></span> ৭ দিনের মধ্যে</span>
        </div>
    </div>
</div>

<!-- তারিখভিত্তিক টাইমলাইন (আগের তারিখ উপরে) -->
<div class="mt-3 mb-4">
    <div id="kbTimeline" class="kb-list">
        <div class="loading"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
    <div id="kbEmpty" class="empty-state" style="display:none;">🎉 আপাতত কোনো কিস্তি বাকি নেই।</div>
</div>

<!-- লেজার ড্রয়ার -->
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
            <div style="margin-top:12px;position:relative;z-index:1;">
                <div class="ld-bar"><span id="ldBar" style="width:0%"></span></div>
                <div style="display:flex;justify-content:space-between;margin-top:5px;font-size:.56rem;font-weight:800;opacity:.92;">
                    <span id="ldPct">0% শোধ</span><span id="ldPayable">মোট ৳0</span>
                </div>
            </div>
        </div>
        <div class="ld-body" id="ldBody">
            <div class="loading"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
        <div class="ld-foot">
            <a class="pdf" id="ldPrint" href="#" target="_blank" rel="noopener"><i class="fas fa-print"></i> রিপোর্ট / ডাউনলোড</a>
            <button type="button" class="pay" id="ldPay"><i class="fas fa-money-bill-wave"></i> শোধ</button>
        </div>
    </div>
</div>

<script>
$(function () {
    const BASE = App.baseUrl || '<?= Security::e($baseUrl ?? '') ?>';

    // লেজার/শোধ Loan মডিউলের endpoint-এ যায় (এই পেজ /repayment-এ POST করে)
    function loanPost(action, payload) {
        return $.ajax({ url: BASE + '/loan', method: 'POST',
            data: Object.assign({}, payload || {}, { action: action, csrf_token: App.csrfToken }), dataType: 'json' });
    }
    function loanPostForm(action, fd) {
        fd.append('action', action); fd.append('csrf_token', App.csrfToken);
        return $.ajax({ url: BASE + '/loan', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' });
    }

    // আজকের তারিখ (লোকাল, সময় বাদ)
    function today0() { const d = new Date(); d.setHours(0, 0, 0, 0); return d; }
    function daysFrom(dateStr) {
        if (!dateStr) return 9999;
        const d = new Date(dateStr + 'T00:00:00');
        return Math.round((d - today0()) / 86400000);
    }
    // টিয়ার: রঙ ও লেবেল
    function tier(diff) {
        if (diff < 0)  return { cls: 't-overdue', icon: 'fa-triangle-exclamation', badge: Math.abs(diff) + ' দিন পার', bcls: '' };
        if (diff === 0) return { cls: 't-today',   icon: 'fa-calendar-day',        badge: 'আজ কিস্তি', bcls: '' };
        if (diff <= 3) return { cls: 't-orange',   icon: 'fa-clock',               badge: diff + ' দিন বাকি', bcls: '' };
        return           { cls: 't-green',    icon: 'fa-calendar-week',       badge: diff + ' দিন বাকি', bcls: '' };
    }
    function fmtDate(s) {
        if (!s) return '—';
        const d = new Date(s + 'T00:00:00');
        return d.toLocaleDateString('bn-BD', { day: 'numeric', month: 'short' });
    }
    function dayNum(s) {
        if (!s) return '—';
        const d = new Date(s + 'T00:00:00');
        return d.toLocaleDateString('bn-BD', { day: 'numeric' });
    }
    function monName(s) {
        if (!s) return '';
        const d = new Date(s + 'T00:00:00');
        return d.toLocaleDateString('bn-BD', { month: 'short' });
    }

    function rowHtml(it) {
        const diff = (typeof it._diff === 'number') ? it._diff : daysFrom(it.due_date);
        const t = tier(diff);
        return '<div class="kb-row ' + t.cls + '">' +
            '<div class="kb-tint"></div>' +
            '<div class="kb-date"><div class="kb-day">' + dayNum(it.due_date) + '</div><div class="kb-mon">' + monName(it.due_date) + '</div></div>' +
            '<div class="kb-div"></div>' +
            '<div class="kb-mid">' +
                '<div class="kb-name">' + App.escHtml(it.borrower_name) + '</div>' +
                '<div class="kb-tags"><span class="kb-badge">' + t.badge + '</span>' +
                    '<span class="kb-emi">' + App.money(it.installment_amount) + '</span></div>' +
            '</div>' +
            '<div class="kb-right">' +
                '<div class="kb-bal">' + App.money(it.current_balance) + '</div>' +
                '<div class="kb-actions">' +
                    '<button type="button" class="kb-mini m-ledger view-ledger" title="লেজার" data-id="' + App.escAttr(it.id) + '"><i class="fas fa-file-invoice-dollar"></i></button>' +
                    '<button type="button" class="kb-mini m-pay quick-pay" title="শোধ" ' +
                        'data-id="' + App.escAttr(it.id) + '" data-name="' + App.escAttr(it.borrower_name) + '" ' +
                        'data-emi="' + App.escAttr(it.installment_amount) + '" data-bal="' + App.escAttr(it.current_balance) + '">' +
                        '<i class="fas fa-money-bill-wave"></i></button>' +
                '</div>' +
            '</div>' +
        '</div>';
    }

    function loadBoard() {
        App.post('fetch_board', { date: $('#boardDate').val() }).done(function (res) {
            if (res.status !== 'success') return;
            $('#paidTotal').text(App.money(res.paid.total) + ' (' + res.paid.count + ' টি)');

            // তিন তালিকা একত্র → তারিখ অনুযায়ী সাজানো (আগের তারিখ উপরে)
            const all = []
                .concat(res.overdue || [], res.dueToday || [], res.upcoming || [])
                .map(function (it) { it._diff = daysFrom(it.due_date); return it; })
                .sort(function (a, b) { return a._diff - b._diff; });

            const box = $('#kbTimeline').empty();
            if (!all.length) { box.hide(); $('#kbEmpty').show(); return; }
            $('#kbEmpty').hide(); box.show();

            let lastGroup = null;
            all.forEach(function (it) {
                const g = it._diff < 0 ? 'overdue' : it._diff === 0 ? 'today' : it._diff <= 3 ? 'soon' : 'week';
                if (g !== lastGroup) {
                    const label = g === 'overdue' ? '<i class="fas fa-triangle-exclamation" style="color:var(--c-red)"></i> বকেয়া'
                        : g === 'today' ? '<i class="fas fa-calendar-day" style="color:#eab308"></i> আজকের কিস্তি'
                        : g === 'soon' ? '<i class="fas fa-clock" style="color:var(--c-orange)"></i> ৩ দিনের মধ্যে'
                        : '<i class="fas fa-calendar-week" style="color:var(--c-green)"></i> এই সপ্তাহে';
                    box.append('<div class="kb-group-label">' + label + '</div>');
                    lastGroup = g;
                }
                box.append(rowHtml(it));
            });
        }).fail(function () {
            $('#kbTimeline').html('<div class="empty-state">লোড করা যায়নি।</div>');
        });
    }
    $('#boardDate').on('change', loadBoard);

    // ---------- দ্রুত শোধ ----------
    $(document).on('click', '.quick-pay', function () {
        const b = $(this);
        const emi = parseFloat(b.attr('data-emi')) || 0;
        const bal = parseFloat(b.attr('data-bal')) || 0;
        const suggest = Math.min(emi, bal);
        Swal.fire({
            title: b.attr('data-name'),
            html: '<input id="qpAmt" type="number" step="0.01" min="0.01" class="swal2-input" placeholder="পরিমাণ (৳)" value="' + (suggest > 0 ? suggest.toFixed(2) : '') + '" style="font-weight:900;font-size:18px;text-align:center;">' +
                  '<input id="qpDate" type="date" class="swal2-input" style="font-size:13px;">' +
                  '<input id="qpNote" class="swal2-input" placeholder="কমেন্ট (ঐচ্ছিক)" style="font-size:13px;">' +
                  '<input id="qpPhoto" type="file" accept="image/*" capture="environment" class="swal2-file" style="font-size:12px;">',
            didOpen: function () { document.getElementById('qpDate').value = $('#boardDate').val() || new Date().toISOString().slice(0,10); },
            showCancelButton: true, confirmButtonText: 'শোধ করুন', confirmButtonColor: '#10b981', cancelButtonColor: '#475569',
            preConfirm: function () {
                return {
                    amount: document.getElementById('qpAmt').value,
                    date: document.getElementById('qpDate').value,
                    note: document.getElementById('qpNote').value,
                    photo: document.getElementById('qpPhoto').files[0] || null
                };
            }
        }).then(function (r) {
            if (!r.isConfirmed) return;
            const fd = new FormData();
            fd.append('loan_id', b.attr('data-id'));
            fd.append('amount', r.value.amount);
            fd.append('txn_date', r.value.date);
            fd.append('note', r.value.note);
            if (r.value.photo) fd.append('photo', r.value.photo);
            App.postForm('quick_pay', fd).done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { loadBoard(); if (currentLoanId) openLedger(currentLoanId); }
            });
        });
    });

    // ---------- লেজার ড্রয়ার (প্রতি প্রোফাইল আলাদা, ডকুমেন্ট সহ) ----------
    const drawer = document.getElementById('ledgerDrawer');
    let currentLoanId = null, currentEmi = 0, currentBal = 0, currentName = '';

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
            doc = '<div class="ld-doc"><img src="' + App.escAttr(row.photo_url) + '" alt="ডকুমেন্ট" ' +
                  'onclick="window.open(this.src,\'_blank\')"></div>';
        }
        const note = row.note ? '<div class="ld-enote">“' + App.escHtml(row.note) + '”</div>' : '';
        return '<div class="ld-entry">' +
            '<div class="ld-eic" style="color:' + col + ';background:' + tint + ';"><i class="fas ' + ic + '"></i></div>' +
            '<div class="ld-etx">' +
                '<div class="ld-edesc">' + App.escHtml(row.description) + '</div>' +
                '<div class="ld-edate">' + App.formatDate(row.txn_date) + '</div>' +
                note + doc +
            '</div>' +
            '<div class="ld-eamt"><div class="a" style="color:' + col + '">' + sign + App.money(amt).replace('৳','৳') + '</div>' +
                '<div class="b">ব্যালেন্স ' + App.money(row.balance) + '</div></div>' +
        '</div>';
    }

    function openLedger(loanId) {
        currentLoanId = loanId;
        drawer.classList.add('open');
        $('#ldBody').html('<div class="loading"><i class="fas fa-spinner fa-spin"></i></div>');
        document.getElementById('ldPrint').setAttribute('href', BASE + '/loan/print/' + loanId);

        loanPost('fetch_profile', { id: loanId, page: 1 }).done(function (res) {
            if (res.status !== 'success') { $('#ldBody').html('<div class="empty-state">লোড করা যায়নি।</div>'); return; }
            const info = res.info, tot = res.totals;
            const payable = parseFloat(info.total_payable) || 0;
            const bal = parseFloat(info.current_balance) || 0;
            const paid = parseFloat(tot.totalPaid) || 0;
            const pct = payable > 0 ? Math.min(100, Math.round(paid / payable * 100)) : 0;

            currentEmi = parseFloat(info.installment_amount) || 0;
            currentBal = bal; currentName = info.borrower_name;

            $('#ldName').text(info.borrower_name);
            $('#ldAcc').text(info.account_number);
            $('#ldBal').text(App.money(bal));
            $('#ldPaid').text(App.money(paid));
            $('#ldInt').text(App.money(tot.interestPaid));
            $('#ldBar').css('width', pct + '%');
            $('#ldPct').text(pct + '% শোধ');
            $('#ldPayable').text('মোট ' + App.money(payable));

            const rows = res.ledger || [];
            if (!rows.length) { $('#ldBody').html('<div class="empty-state">কোনো এন্ট্রি নেই।</div>'); return; }
            $('#ldBody').html(rows.map(entryHtml).join(''));
        }).fail(function () {
            $('#ldBody').html('<div class="empty-state">লোড করা যায়নি।</div>');
        });
    }
    $(document).on('click', '.view-ledger', function () { openLedger($(this).attr('data-id')); });

    // ড্রয়ার থেকে শোধ
    document.getElementById('ldPay').addEventListener('click', function () {
        if (!currentLoanId) return;
        const suggest = Math.min(currentEmi, currentBal);
        Swal.fire({
            title: currentName,
            html: '<input id="qpAmt" type="number" step="0.01" min="0.01" class="swal2-input" placeholder="পরিমাণ (৳)" value="' + (suggest > 0 ? suggest.toFixed(2) : '') + '" style="font-weight:900;font-size:18px;text-align:center;">' +
                  '<input id="qpDate" type="date" class="swal2-input" style="font-size:13px;">' +
                  '<input id="qpNote" class="swal2-input" placeholder="কমেন্ট (ঐচ্ছিক)" style="font-size:13px;">' +
                  '<input id="qpPhoto" type="file" accept="image/*" capture="environment" class="swal2-file" style="font-size:12px;">',
            didOpen: function () { document.getElementById('qpDate').value = $('#boardDate').val() || new Date().toISOString().slice(0,10); },
            showCancelButton: true, confirmButtonText: 'শোধ করুন', confirmButtonColor: '#10b981', cancelButtonColor: '#475569',
            preConfirm: function () {
                return {
                    amount: document.getElementById('qpAmt').value,
                    date: document.getElementById('qpDate').value,
                    note: document.getElementById('qpNote').value,
                    photo: document.getElementById('qpPhoto').files[0] || null
                };
            }
        }).then(function (r) {
            if (!r.isConfirmed) return;
            const fd = new FormData();
            fd.append('loan_id', currentLoanId);
            fd.append('amount', r.value.amount);
            fd.append('txn_date', r.value.date);
            fd.append('note', r.value.note);
            if (r.value.photo) fd.append('photo', r.value.photo);
            loanPostForm('add_payment', fd).done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { openLedger(currentLoanId); loadBoard(); }
            });
        });
    });

    loadBoard();
});
</script>
