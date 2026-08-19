<?php
/**
 * ড্যাশবোর্ড — আপনি যেসব লোন নিয়েছেন।
 * Available: $bannerTitle, $bannerSub, $canDelete, $csrfToken, $baseUrl, $userRole
 */
?>

<div class="hero">
    <img class="hero-img" src="<?= Security::e($baseUrl) ?>/assets/banner.jpg" alt="ব্যানার" onerror="this.style.display='none'">
    <div class="hero-scrim"></div>
    <div class="hero-cap">
        <div class="hero-logo"><i class="fas fa-landmark"></i></div>
        <div class="hero-text">
            <div class="hero-title"><?= Security::e($bannerTitle) ?></div>
            <div class="hero-sub"><?= Security::e($bannerSub) ?></div>
        </div>
    </div>
</div>

<!-- সারাংশ (আপনি ঋণগ্রহীতা) -->
<div class="stat-grid mt-3" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card tone-green">
        <div class="si"><i class="fas fa-coins"></i></div>
        <div class="sv" id="sumTodayInterest">৳0.00</div>
        <div class="sl">আজ সুদ শোধ</div>
    </div>
    <div class="stat-card tone-purple">
        <div class="si"><i class="fas fa-chart-line"></i></div>
        <div class="sv" id="sumTotalInterest">৳0.00</div>
        <div class="sl">মোট সুদ</div>
    </div>
    <div class="stat-card tone-red">
        <div class="si"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="sv" id="sumTotalOut">৳0.00</div>
        <div class="sl">মোট বাকি</div>
    </div>
</div>

<div class="app-card mt-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <span class="field-label mb-0">লোন সংখ্যা</span>
        <div class="d-flex gap-3">
            <span><strong id="sumActive" style="color:var(--c-green)">0</strong> <small class="text-muted">চলমান</small></span>
            <span><strong id="sumClosed" style="color:var(--c-red)">0</strong> <small class="text-muted">পরিশোধিত</small></span>
        </div>
    </div>
</div>

<!-- নেভিগেশন -->
<div class="app-nav mt-4" style="grid-template-columns: repeat(4, 1fr);">
    <div class="nav-cell" data-section="addloan"><div class="nav-orb g-bank"><i class="fas fa-circle-plus"></i></div><span class="nav-label">নতুন লোন</span></div>
    <div class="nav-cell" data-section="payment"><div class="nav-orb g-pay"><i class="fas fa-money-bill-wave"></i></div><span class="nav-label">কিস্তি শোধ</span></div>
    <div class="nav-cell" data-section="profiles"><div class="nav-orb g-profile"><i class="fas fa-id-card"></i></div><span class="nav-label">প্রোফাইল</span></div>
    <div class="nav-cell" data-section="ledger"><div class="nav-orb g-ledger"><i class="fas fa-clock-rotate-left"></i></div><span class="nav-label">লেজার</span></div>
</div>

<div class="mt-4 d-flex flex-column gap-3 pb-4">

    <!-- নতুন লোন (নেওয়া) -->
    <div id="section-addloan" class="section-panel app-card" style="display:none;">
        <div class="card-head"><span class="chi" style="background:var(--c-purple)"><i class="fas fa-circle-plus"></i></span> নতুন লোন যোগ করুন</div>
        <form id="loanForm" class="card-body d-flex flex-column gap-3">
            <div>
                <label class="field-label">পাওনাদারের নাম (যার কাছ থেকে নিয়েছেন)</label>
                <input type="text" name="borrower_name" class="app-input" maxlength="150" placeholder="যেমন: ঢাকা ব্যাংক / করিম মহাজন" required>
            </div>
            <div>
                <label class="field-label">মোবাইল (SMS নোটিফিকেশন — ঐচ্ছিক)</label>
                <input type="tel" name="mobile" class="app-input" maxlength="11" placeholder="01XXXXXXXXX">
            </div>
            <div class="row g-2">
                <div class="col-6"><label class="field-label">মূল টাকা (৳)</label>
                    <input type="number" step="0.01" min="0.01" name="principal_amount" id="fPrincipal" class="app-input" required></div>
                <div class="col-6"><label class="field-label">সুদের হার (বার্ষিক %)</label>
                    <input type="number" step="0.01" min="0" max="500" name="interest_rate" id="fRate" class="app-input" value="0"></div>
            </div>
            <div class="row g-2">
                <div class="col-6"><label class="field-label">কিস্তির সংখ্যা</label>
                    <input type="number" step="1" min="1" max="1000" name="total_installments" id="fInstallments" class="app-input" required></div>
                <div class="col-6"><label class="field-label">কিস্তির ধরন</label>
                    <select name="frequency" id="fFrequency" class="app-input">
                        <option value="monthly">মাসিক</option>
                        <option value="weekly">সাপ্তাহিক</option>
                        <option value="daily">দৈনিক</option>
                    </select></div>
            </div>
            <div class="row g-2">
                <div class="col-6"><label class="field-label">প্রতি কিস্তির টাকা (ঐচ্ছিক)</label>
                    <input type="number" step="0.01" min="0" name="installment_amount" id="fEmi" class="app-input" placeholder="খালি রাখলে অটো হিসাব"></div>
                <div class="col-6"><label class="field-label">লোন নেওয়ার তারিখ</label>
                    <input type="date" name="open_date" class="app-input" value="<?= date('Y-m-d') ?>" required></div>
            </div>
            <div>
                <label class="field-label">প্রথম কিস্তির তারিখ</label>
                <input type="date" name="due_date" class="app-input" required>
            </div>
            <div class="app-card" style="background:color-mix(in srgb,var(--c-blue) 8%,var(--surface));padding:10px;">
                <div style="font-size:.66rem;font-weight:800;color:var(--muted);">সম্ভাব্য হিসাব</div>
                <div id="emiPreview" style="font-size:.78rem;font-weight:800;color:var(--c-blue);margin-top:3px;">—</div>
            </div>
            <button type="submit" class="app-btn btn-blue"><i class="fas fa-check"></i> লোন যোগ করুন</button>
        </form>
    </div>

    <!-- কিস্তি শোধ (ছবি সহ) -->
    <div id="section-payment" class="section-panel app-card" style="display:none;">
        <div class="card-head"><span class="chi" style="background:var(--c-green)"><i class="fas fa-money-bill-wave"></i></span> কিস্তি শোধ করুন</div>
        <form id="paymentForm" class="card-body d-flex flex-column gap-3">
            <div>
                <label class="field-label">কোন লোন</label>
                <select name="loan_id" id="paymentLoanSelect" class="app-input" required>
                    <option value="">— পাওনাদার নির্বাচন করুন —</option>
                </select>
            </div>
            <div class="row g-2">
                <div class="col-6"><label class="field-label">শোধের পরিমাণ (৳)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="app-input" required></div>
                <div class="col-6"><label class="field-label">তারিখ</label>
                    <input type="date" name="txn_date" class="app-input" value="<?= date('Y-m-d') ?>" required></div>
            </div>
            <div>
                <label class="field-label">কমেন্ট (ঐচ্ছিক)</label>
                <input type="text" name="note" class="app-input" maxlength="500" placeholder="যেমন: বিকাশে পাঠানো হয়েছে">
            </div>
            <div>
                <label class="field-label">রসিদ/প্রমাণের ছবি (ঐচ্ছিক)</label>
                <input type="file" name="photo" id="payPhoto" class="app-input" accept="image/*" capture="environment">
                <div id="payPhotoPreview" style="margin-top:8px;"></div>
            </div>
            <button type="submit" class="app-btn btn-green"><i class="fas fa-check"></i> শোধ করুন</button>
        </form>
    </div>

    <!-- প্রোফাইল তালিকা -->
    <div id="section-profiles" class="section-panel">
        <div class="app-card mb-3">
            <div class="card-head justify-content-between">
                <span class="d-flex align-items-center gap-2"><span class="chi" style="background:var(--c-purple)"><i class="fas fa-users"></i></span> লোন প্রোফাইল</span>
                <div class="seg">
                    <button type="button" class="on" data-status="active">চলমান</button>
                    <button type="button" data-status="inactive">পরিশোধিত</button>
                </div>
            </div>
        </div>
        <div id="profileGrid" class="d-grid gap-3" style="grid-template-columns: 1fr;">
            <div class="loading"><i class="fas fa-spinner fa-spin me-1"></i> লোড হচ্ছে...</div>
        </div>
        <div id="profilePager"></div>
    </div>

    <!-- প্রোফাইল বিস্তারিত -->
    <div id="section-profile-detail" class="section-panel app-card" style="display:none;">
        <div class="card-body" style="background:var(--header-grad);color:#fff;display:flex;justify-content:space-between;align-items:flex-start;border-radius:0;">
            <div>
                <h2 id="detName" class="mb-2" style="font-weight:800;font-size:1.2rem;">লোড হচ্ছে...</h2>
                <div class="d-flex flex-wrap gap-1 align-items-center">
                    <span id="detAcc" class="chip" style="background:rgba(255,255,255,.18);color:#fff;border:none;">—</span>
                    <span id="detStatus" class="chip green">চলমান</span>
                    <span id="detDueBadge"></span>
                </div>
            </div>
            <button type="button" class="icon-pill" data-section="profiles"><i class="fas fa-times"></i></button>
        </div>

        <div class="card-body border-bottom" style="border-color:var(--border)!important;">
            <div class="field-label mb-2"><i class="fas fa-circle-info" style="color:var(--c-blue)"></i> লোনের তথ্য</div>
            <div class="det-tiles">
                <div class="det-tile"><span class="dl">মূল টাকা</span><span class="dv" id="detPrincipal">৳0.00</span></div>
                <div class="det-tile"><span class="dl">সুদের হার</span><span class="dv" id="detRate">0%</span></div>
                <div class="det-tile"><span class="dl">প্রতি কিস্তি</span><span class="dv" id="detInstAmt">৳0.00</span></div>
                <div class="det-tile"><span class="dl">মোট শোধ করতে হবে</span><span class="dv" id="detTotalPayable">৳0.00</span></div>
                <div class="det-tile"><span class="dl">কিস্তি সংখ্যা</span><span class="dv" id="detDuration">0</span></div>
                <div class="det-tile"><span class="dl">কিস্তির ধরন</span><span class="dv" id="detFreq">—</span></div>
                <div class="det-tile"><span class="dl">পরবর্তী কিস্তির তারিখ</span><span class="dv" id="detDueDate">—</span></div>
            </div>
        </div>

        <div class="det-tiles border-bottom" style="grid-template-columns:repeat(3,1fr);gap:0;border-color:var(--border)!important;">
            <div class="text-center p-3" style="border-right:1px solid var(--border);">
                <p class="dl mb-1" style="font-size:.54rem;font-weight:800;text-transform:uppercase;color:var(--muted);">এখনো বাকি</p>
                <p id="detDue" class="mb-0" style="font-weight:800;color:var(--c-red);">৳0.00</p></div>
            <div class="text-center p-3" style="border-right:1px solid var(--border);">
                <p class="dl mb-1" style="font-size:.54rem;font-weight:800;text-transform:uppercase;color:var(--muted);">শোধ করেছি</p>
                <p id="detPaid" class="mb-0" style="font-weight:800;color:var(--c-green);">৳0.00</p></div>
            <div class="text-center p-3">
                <p class="dl mb-1" style="font-size:.54rem;font-weight:800;text-transform:uppercase;color:var(--muted);">সুদ দিয়েছি</p>
                <p id="detInterest" class="mb-0" style="font-weight:800;color:var(--c-purple);">৳0.00</p></div>
        </div>

        <div class="card-body border-bottom" style="border-color:var(--border)!important;">
            <div class="field-label mb-1"><i class="fas fa-phone" style="color:var(--c-blue)"></i> মোবাইল (SMS)</div>
            <div id="detMobile" style="font-weight:800;">—</div>
        </div>

        <div class="card-body d-flex flex-wrap gap-2 border-bottom" style="border-color:var(--border)!important;">
            <button type="button" id="btnEditName" class="app-btn btn-blue" style="font-size:.66rem;padding:9px;"><i class="fas fa-pen"></i> নাম</button>
            <button type="button" id="btnEditMobile" class="app-btn btn-blue" style="font-size:.66rem;padding:9px;"><i class="fas fa-mobile-screen"></i> মোবাইল</button>
            <button type="button" id="btnEditLoan" class="app-btn btn-violet" style="font-size:.66rem;padding:9px;"><i class="fas fa-pen"></i> বিস্তারিত এডিট</button>
            <button type="button" id="btnEditDue" class="app-btn btn-orange" style="font-size:.66rem;padding:9px;"><i class="fas fa-calendar-days"></i> কিস্তির তারিখ</button>
            <button type="button" id="btnAddInterest" class="app-btn btn-violet" style="font-size:.66rem;padding:9px;"><i class="fas fa-plus"></i> সুদ যোগ</button>
            <button type="button" id="btnToggleStatus" class="app-btn btn-dark" style="font-size:.66rem;padding:9px;"><i class="fas fa-power-off"></i> স্ট্যাটাস</button>
            <button type="button" id="btnPrint" class="app-btn btn-dark" style="font-size:.66rem;padding:9px;"><i class="fas fa-print"></i> প্রিন্ট</button>
        </div>

        <div class="toolbar"><span class="field-label mb-0"><i class="fas fa-list" style="color:var(--muted)"></i> সম্পূর্ণ লেজার</span></div>
        <div class="ledger-wrap">
            <table class="app-table">
                <thead>
                    <tr><th>তারিখ</th><th>বিবরণ</th><th class="text-end">দায় (+)</th><th class="text-end">শোধ (−)</th><th class="text-end">বাকি</th><th class="text-center">রসিদ</th><th class="text-center">অ্যাকশন</th></tr>
                </thead>
                <tbody id="detLedgerBody"></tbody>
            </table>
        </div>
        <div id="detLedgerPager"></div>
    </div>

    <!-- সম্পূর্ণ লেজার -->
    <div id="section-ledger" class="section-panel app-card" style="display:none;">
        <div class="card-head justify-content-between">
            <span class="d-flex align-items-center gap-2"><span class="chi" style="background:var(--c-blue)"><i class="fas fa-clock-rotate-left"></i></span> লেনদেনের ইতিহাস</span>
            <select id="loanFilter" class="app-input" style="width:auto;font-size:.7rem;">
                <option value="all">সব লোন</option>
            </select>
        </div>
        <div class="ledger-wrap">
            <table class="app-table">
                <thead>
                    <tr><th>তারিখ</th><th>পাওনাদার</th><th>বিবরণ</th><th class="text-end">দায় (+)</th><th class="text-end">শোধ (−)</th><th class="text-end">বাকি</th><th class="text-center">রসিদ</th></tr>
                </thead>
                <tbody id="ledgerBody"></tbody>
            </table>
        </div>
        <div id="ledgerPager"></div>
    </div>

</div>

<script>
$(function () {
    const CAN_DELETE = <?= !empty($canDelete) ? 'true' : 'false' ?>;
    let currentLoanId = null;
    let currentStatusFilter = 'active';

    function loadSummary() {
        App.post('fetch_summary', {}).done(function (res) {
            if (res.status !== 'success') return;
            $('#sumTodayInterest').text(App.money(res.todayInterest));
            $('#sumTotalInterest').text(App.money(res.totalInterest));
            $('#sumTotalOut').text(App.money(res.totalOutstanding));
            $('#sumActive').text(res.activeCount);
            $('#sumClosed').text(res.closedCount);
        });
    }

    function loadLoanOptions() {
        App.post('fetch_active_loans', {}).done(function (res) {
            if (res.status !== 'success') return;
            const paySel = $('#paymentLoanSelect').empty().append('<option value="">— পাওনাদার নির্বাচন করুন —</option>');
            const filSel = $('#loanFilter').empty().append('<option value="all">সব লোন</option>');
            res.loans.forEach(function (loan) {
                paySel.append($('<option>').val(loan.id).text(loan.borrower_name + ' — বাকি ' + App.money(loan.current_balance)));
                filSel.append($('<option>').val(loan.id).text(loan.borrower_name));
            });
        });
    }

    function loadProfiles(status, page) {
        currentStatusFilter = status || 'active';
        $('.seg button').removeClass('on').filter('[data-status="' + currentStatusFilter + '"]').addClass('on');
        App.post('fetch_profiles', { status: currentStatusFilter, page: page || 1 }).done(function (res) {
            const grid = $('#profileGrid').empty();
            if (res.status !== 'success' || !res.profiles.length) {
                grid.html('<div class="empty-state">কোনো লোন পাওয়া যায়নি।</div>');
                $('#profilePager').empty();
                return;
            }
            res.profiles.forEach(function (p) {
                const payable = parseFloat(p.total_payable) || 0;
                const bal = parseFloat(p.current_balance) || 0;
                const paid = parseFloat(p.total_paid) || 0;
                const pct = payable > 0 ? Math.min(100, Math.round(paid / payable * 100)) : 0;
                const card = $('<div class="app-card profile-card"></div>').attr('data-loan-id', p.id);
                card.html(
                    '<div class="profile-row">' +
                        '<div>' +
                            '<div class="profile-name">' + App.escHtml(p.borrower_name) + '</div>' +
                            '<div class="profile-acc">' + App.escHtml(p.account_number) + '</div>' +
                        '</div>' +
                        '<div class="text-end">' +
                            '<div class="profile-amt" style="color:var(--c-red)">' + App.money(p.current_balance) + '</div>' +
                            App.dueBadge(p.due_badge) +
                        '</div>' +
                    '</div>' +
                    '<div class="pcard-prog">' +
                        '<div class="pcard-bar"><span style="width:' + pct + '%"></span></div>' +
                        '<div class="pcard-progmeta"><span>শোধ <b style="color:var(--c-green)">' + App.money(paid) + '</b></span>' +
                            '<span class="pcard-pct">' + pct + '%</span></div>' +
                    '</div>' +
                    '<div class="profile-meta">' +
                        '<span class="chip">' + App.escHtml(p.interest_rate) + '%</span>' +
                        '<span class="chip blue">' + App.escHtml(p.frequency) + '</span>' +
                        '<span class="chip">কিস্তি ' + App.money(p.installment_amount) + '</span>' +
                        '<button type="button" class="chip pcard-date" data-id="' + App.escAttr(p.id) + '" data-due="' + App.escAttr(p.due_date || '') + '"><i class="fas fa-calendar-days"></i> তারিখ</button>' +
                    '</div>'
                );
                grid.append(card);
            });
            App.pager('profilePager', res.meta, 'profilesList');
        }).fail(function () {
            $('#profileGrid').html('<div class="empty-state">লোড করা যায়নি। রিফ্রেশ করে আবার চেষ্টা করুন।</div>');
        });
    }
    App.onPage('profilesList', function (page) { loadProfiles(currentStatusFilter, page); });

    $(document).on('click', '.profile-card', function () {
        openProfile(parseInt($(this).attr('data-loan-id'), 10), 1, true);
    });
    $(document).on('click', '.pcard-date', function (e) {
        e.stopPropagation();
        promptEditDue($(this).attr('data-id'), $(this).attr('data-due') || '');
    });
    $(document).on('click', '.seg button[data-status]', function () {
        loadProfiles($(this).data('status'));
    });

    function photoCell(url) {
        if (!url) return '<span style="color:var(--muted)">—</span>';
        return '<a href="' + App.escAttr(url) + '" target="_blank" class="receipt-thumb">' +
               '<img src="' + App.escAttr(url) + '" alt="রসিদ" loading="lazy"></a>';
    }

    function openProfile(loanId, page, doOpen) {
        currentLoanId = loanId;
        App.post('fetch_profile', { id: loanId, page: page || 1 }).done(function (res) {
            if (res.status !== 'success') { App.toast('error', res.message); return; }
            const info = res.info;
            $('#detName').text(info.borrower_name);
            $('#detAcc').text(info.account_number);
            $('#detStatus').text(info.status === 'active' ? 'চলমান' : 'পরিশোধিত')
                .removeClass('green red').addClass(info.status === 'active' ? 'green' : 'red');
            $('#detDueBadge').html(App.dueBadge(info.due_badge));
            $('#detPrincipal').text(App.money(info.principal_amount));
            $('#detRate').text(info.interest_rate + '%');
            $('#detInstAmt').text(App.money(info.installment_amount));
            $('#detTotalPayable').text(App.money(info.total_payable));
            $('#detDuration').text(info.total_installments);
            $('#detFreq').text(info.frequency);
            $('#detDueDate').text(App.formatDate(info.due_date));
            $('#detDue').text(App.money(info.current_balance));
            $('#detPaid').text(App.money(res.totals.totalPaid));
            $('#detInterest').text(App.money(res.totals.interestPaid));

            $('#btnEditName').off('click').on('click', function () { promptEditName(info.id, info.borrower_name); });
            $('#btnEditMobile').off('click').on('click', function () { promptEditMobile(info.id, info.mobile || ''); });
            $('#btnEditLoan').off('click').on('click', function () { promptEditLoan(info); });
            $('#btnEditDue').off('click').on('click', function () { promptEditDue(info.id, info.due_date || ''); });
            $('#btnAddInterest').off('click').on('click', function () { promptAddInterest(info.id, info.borrower_name); });
            $('#btnToggleStatus').off('click').on('click', function () { promptToggleStatus(info.id); });
            $('#btnPrint').off('click').on('click', function () {
                window.open(App.baseUrl + '/loan/print/' + encodeURIComponent(info.id), '_blank');
            });
            $('#detMobile').text(info.mobile || '—');

            renderDetailLedger(res.ledger);
            App.pager('detLedgerPager', res.meta, 'detailLedger');
            if (doOpen !== false) App.showSection('profile-detail');
        });
    }
    App.onPage('detailLedger', function (page) { openProfile(currentLoanId, page, false); });

    function renderDetailLedger(rows) {
        const body = $('#detLedgerBody').empty();
        if (!rows.length) { body.html('<tr><td colspan="7" class="empty-state">কোনো লেনদেন নেই।</td></tr>'); return; }
        rows.forEach(function (r) {
            const isDebit = parseFloat(r.debit_amount) > 0;
            const editAmt = isDebit ? r.debit_amount : r.credit_amount;
            const tr = $('<tr></tr>');
            tr.html(
                '<td class="text-nowrap">' + App.formatDate(r.txn_date) + '</td>' +
                '<td style="white-space:normal;font-size:.66rem;">' + App.escHtml(r.description) +
                    (r.note ? '<div style="font-size:.58rem;color:var(--muted);margin-top:2px;">' + App.escHtml(r.note) + '</div>' : '') + '</td>' +
                '<td class="text-end text-nowrap">' + (isDebit ? '<span class="amt-neg">+' + App.money(r.debit_amount) + '</span>' : '<span style="color:var(--muted)">—</span>') + '</td>' +
                '<td class="text-end text-nowrap">' + (parseFloat(r.credit_amount) > 0 ? '<span class="amt-pos">−' + App.money(r.credit_amount) + '</span>' : '<span style="color:var(--muted)">—</span>') + '</td>' +
                '<td class="text-end text-nowrap amt-bal">' + App.money(r.balance) + '</td>' +
                '<td class="text-center">' + photoCell(r.photo_url) + '</td>' +
                '<td class="text-center text-nowrap">' +
                    '<button type="button" class="mini-act act-edit" data-id="' + App.escAttr(r.id) + '" data-desc="' + App.escAttr(r.description) + '" data-amt="' + App.escAttr(editAmt) + '" data-date="' + App.escAttr(r.txn_date) + '" data-note="' + App.escAttr(r.note || '') + '"><i class="fas fa-pen"></i></button>' +
                    (CAN_DELETE ? ' <button type="button" class="mini-act act-del" data-id="' + App.escAttr(r.id) + '"><i class="fas fa-trash"></i></button>' : '') +
                '</td>'
            );
            body.append(tr);
        });
    }

    function loadLedger(page) {
        App.post('fetch_ledger', { loan_id: $('#loanFilter').val() || 'all', page: page || 1 }).done(function (res) {
            if (res.status !== 'success') return;
            const body = $('#ledgerBody').empty();
            if (!res.ledger.length) { body.html('<tr><td colspan="7" class="empty-state">কোনো রেকর্ড নেই।</td></tr>'); }
            else {
                res.ledger.forEach(function (r) {
                    const isDebit = parseFloat(r.debit_amount) > 0;
                    let rowClass = '';
                    if (/সুদ|মুনাফা|Interest|Profit/.test(r.description)) rowClass = 'row-profit';
                    else if (parseFloat(r.credit_amount) > 0) rowClass = 'row-payment';
                    body.append($('<tr class="' + rowClass + '"></tr>').html(
                        '<td class="text-nowrap">' + App.formatDate(r.txn_date) + '</td>' +
                        '<td class="text-nowrap" style="font-weight:800;">' + App.escHtml(r.borrower_name) + '</td>' +
                        '<td style="white-space:normal;font-size:.66rem;">' + App.escHtml(r.description) + '</td>' +
                        '<td class="text-end text-nowrap">' + (isDebit ? '<span class="amt-neg">+' + App.money(r.debit_amount) + '</span>' : '<span style="color:var(--muted)">—</span>') + '</td>' +
                        '<td class="text-end text-nowrap">' + (parseFloat(r.credit_amount) > 0 ? '<span class="amt-pos">−' + App.money(r.credit_amount) + '</span>' : '<span style="color:var(--muted)">—</span>') + '</td>' +
                        '<td class="text-end text-nowrap amt-bal">' + App.money(r.balance) + '</td>' +
                        '<td class="text-center">' + photoCell(r.photo_url) + '</td>'
                    ));
                });
            }
            App.pager('ledgerPager', res.meta, 'historyLedger');
        });
    }
    App.onPage('historyLedger', loadLedger);
    $('#loanFilter').on('change', function () { loadLedger(1); });

    // ---------- এডিট / ডিলিট ----------
    $(document).on('click', '.act-edit', function () {
        const btn = $(this);
        Swal.fire({
            title: 'এন্ট্রি সম্পাদনা',
            html: '<input id="swalDesc" class="swal2-input" placeholder="বিবরণ" style="font-size:13px;">' +
                  '<input id="swalAmt" type="number" step="0.01" class="swal2-input" placeholder="পরিমাণ" style="font-weight:900;font-size:18px;text-align:center;">' +
                  '<input id="swalDate" type="date" class="swal2-input" style="font-size:13px;">' +
                  '<input id="swalNote" class="swal2-input" placeholder="কমেন্ট (ঐচ্ছিক)" style="font-size:13px;">' +
                  '<input id="swalPhoto" type="file" accept="image/*" class="swal2-file" style="font-size:12px;">',
            didOpen: function () {
                document.getElementById('swalDesc').value = btn.attr('data-desc');
                document.getElementById('swalAmt').value  = btn.attr('data-amt');
                document.getElementById('swalDate').value = btn.attr('data-date');
                document.getElementById('swalNote').value = btn.attr('data-note') || '';
            },
            showCancelButton: true, confirmButtonText: 'আপডেট',
            confirmButtonColor: '#6366f1', cancelButtonColor: '#475569',
            preConfirm: function () {
                return {
                    desc: document.getElementById('swalDesc').value,
                    amount: document.getElementById('swalAmt').value,
                    date: document.getElementById('swalDate').value,
                    note: document.getElementById('swalNote').value,
                    photo: document.getElementById('swalPhoto').files[0] || null
                };
            }
        }).then(function (result) {
            if (!result.isConfirmed) return;
            const fd = new FormData();
            fd.append('id', btn.attr('data-id'));
            fd.append('description', result.value.desc);
            fd.append('amount', result.value.amount);
            fd.append('txn_date', result.value.date);
            fd.append('note', result.value.note);
            if (result.value.photo) fd.append('photo', result.value.photo);
            App.postForm('update_ledger', fd).done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { loadSummary(); openProfile(currentLoanId, 1, false); }
            });
        });
    });

    $(document).on('click', '.act-del', function () {
        const ledgerId = $(this).attr('data-id');
        Swal.fire({
            title: 'নিশ্চিত?', text: 'ব্যালেন্স স্বয়ংক্রিয়ভাবে আবার হিসাব হবে।', icon: 'warning',
            showCancelButton: true, confirmButtonText: 'হ্যাঁ, মুছুন', confirmButtonColor: '#e11d48', cancelButtonColor: '#475569'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            App.post('delete_ledger', { id: ledgerId }).done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { loadSummary(); openProfile(currentLoanId, 1, false); }
            });
        });
    });

    function promptEditName(loanId, oldName) {
        Swal.fire({ title: 'পাওনাদারের নাম', input: 'text', inputValue: oldName,
            showCancelButton: true, confirmButtonText: 'আপডেট', confirmButtonColor: '#6366f1', cancelButtonColor: '#475569'
        }).then(function (r) {
            if (!r.isConfirmed || !r.value) return;
            App.post('update_name', { id: loanId, name: r.value }).done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { openProfile(loanId, 1, false); loadProfiles(currentStatusFilter); }
            });
        });
    }
    function promptEditMobile(loanId, oldMobile) {
        Swal.fire({ title: 'মোবাইল নম্বর (SMS)', input: 'tel', inputValue: oldMobile,
            inputPlaceholder: '01XXXXXXXXX',
            showCancelButton: true, confirmButtonText: 'আপডেট', confirmButtonColor: '#6366f1', cancelButtonColor: '#475569'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            App.post('update_mobile', { id: loanId, mobile: r.value || '' }).done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { openProfile(loanId, 1, false); }
            });
        });
    }
    function promptEditLoan(info) {
        Swal.fire({
            title: 'লোনের তথ্য এডিট',
            html:
                '<input id="elPrincipal" type="number" step="0.01" min="0" class="swal2-input" placeholder="মূল টাকা (৳)" value="' + (info.principal_amount || '') + '">' +
                '<input id="elRate" type="number" step="0.01" min="0" class="swal2-input" placeholder="সুদের হার (%)" value="' + (info.interest_rate || '') + '">' +
                '<input id="elInst" type="number" step="1" min="1" class="swal2-input" placeholder="কিস্তির সংখ্যা" value="' + (info.total_installments || '') + '">' +
                '<select id="elFreq" class="swal2-input">' +
                    '<option value="daily">দৈনিক</option><option value="weekly">সাপ্তাহিক</option><option value="monthly">মাসিক</option>' +
                '</select>' +
                '<input id="elEmi" type="number" step="0.01" min="0" class="swal2-input" placeholder="প্রতি কিস্তি (খালি=অটো)" value="' + (info.installment_amount || '') + '">',
            didOpen: function () { document.getElementById('elFreq').value = info.frequency || 'monthly'; },
            showCancelButton: true, confirmButtonText: 'আপডেট', confirmButtonColor: '#8b5cf6', cancelButtonColor: '#475569',
            preConfirm: function () {
                return {
                    principal_amount: document.getElementById('elPrincipal').value,
                    interest_rate: document.getElementById('elRate').value,
                    total_installments: document.getElementById('elInst').value,
                    frequency: document.getElementById('elFreq').value,
                    installment_amount: document.getElementById('elEmi').value || 0
                };
            }
        }).then(function (r) {
            if (!r.isConfirmed) return;
            App.post('update_loan', Object.assign({ id: info.id }, r.value)).done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { openProfile(info.id, 1, false); loadProfiles(currentStatusFilter); loadSummary(); }
            });
        });
    }
    function promptEditDue(loanId, oldDate) {
        Swal.fire({ title: 'পরবর্তী কিস্তির তারিখ', input: 'date', inputValue: oldDate,
            showCancelButton: true, confirmButtonText: 'আপডেট', confirmButtonColor: '#f59e0b', cancelButtonColor: '#475569'
        }).then(function (r) {
            if (!r.isConfirmed || !r.value) return;
            App.post('update_due_date', { id: loanId, due_date: r.value }).done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { openProfile(loanId, 1, false); loadProfiles(currentStatusFilter); }
            });
        });
    }
    function promptAddInterest(loanId, name) {
        Swal.fire({
            title: name,
            html: '<input id="inDesc" class="swal2-input" placeholder="বিবরণ (যেমন: সুদ)" value="সুদ" style="font-size:13px;">' +
                  '<input id="inAmt" type="number" step="0.01" min="0.01" class="swal2-input" placeholder="পরিমাণ (৳)" style="font-weight:900;font-size:18px;text-align:center;">' +
                  '<input id="inDate" type="date" class="swal2-input" style="font-size:13px;">',
            didOpen: function () { document.getElementById('inDate').value = new Date().toISOString().slice(0,10); },
            showCancelButton: true, confirmButtonText: 'যোগ করুন', confirmButtonColor: '#a855f7', cancelButtonColor: '#475569',
            preConfirm: function () { return { desc: document.getElementById('inDesc').value, amount: document.getElementById('inAmt').value, date: document.getElementById('inDate').value }; }
        }).then(function (r) {
            if (!r.isConfirmed) return;
            App.post('add_interest', { loan_id: loanId, amount: r.value.amount, description: r.value.desc, txn_date: r.value.date }).done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { loadSummary(); openProfile(loanId, 1, false); }
            });
        });
    }
    function promptToggleStatus(loanId) {
        Swal.fire({ title: 'স্ট্যাটাস পরিবর্তন?', text: 'লোনটি চলমান/পরিশোধিত হবে।', icon: 'warning',
            showCancelButton: true, confirmButtonText: 'হ্যাঁ', confirmButtonColor: '#334155', cancelButtonColor: '#475569'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            App.post('toggle_status', { id: loanId }).done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { openProfile(loanId, 1, false); loadSummary(); }
            });
        });
    }
    // ---------- EMI প্রিভিউ ----------
    function updateEmiPreview() {
        const p = parseFloat($('#fPrincipal').val()) || 0;
        const n = parseInt($('#fInstallments').val(), 10) || 0;
        const rate = parseFloat($('#fRate').val()) || 0;
        const manual = parseFloat($('#fEmi').val()) || 0;
        const freq = $('#fFrequency').val();
        if (p <= 0 || n <= 0) { $('#emiPreview').text('—'); return; }
        let emi;
        if (manual > 0) emi = manual;
        else {
            const per = freq === 'daily' ? 365 : (freq === 'weekly' ? 52 : 12);
            const pr = (rate / 100) / per;
            emi = pr <= 0 ? p / n : (p * pr * Math.pow(1+pr, n)) / (Math.pow(1+pr, n) - 1);
        }
        const total = emi * n;
        $('#emiPreview').text('প্রতি কিস্তি ' + App.money(emi) + ' × ' + n + ' = ' + App.money(total) + ' (সুদ ' + App.money(total - p) + ')');
    }
    $('#fPrincipal, #fInstallments, #fRate, #fEmi, #fFrequency').on('input change', updateEmiPreview);

    // ---------- ছবি প্রিভিউ ----------
    $('#payPhoto').on('change', function () {
        const f = this.files[0];
        const box = $('#payPhotoPreview').empty();
        if (f) box.html('<img src="' + URL.createObjectURL(f) + '" style="max-width:120px;border-radius:8px;border:1px solid var(--border);">');
    });

    // ---------- ফর্ম সাবমিট ----------
    $('#loanForm').on('submit', function (e) {
        e.preventDefault();
        const form = $(this), btn = form.find('button[type="submit"]'), orig = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin me-1"></i> প্রসেস হচ্ছে...').prop('disabled', true);
        const payload = {};
        form.serializeArray().forEach(function (f) { payload[f.name] = f.value; });
        App.post('create_loan', payload)
            .done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { form[0].reset(); updateEmiPreview(); loadSummary(); loadLoanOptions(); loadProfiles('active'); App.showSection('profiles'); }
            })
            .always(function () { btn.html(orig).prop('disabled', false); });
    });

    $('#paymentForm').on('submit', function (e) {
        e.preventDefault();
        const form = $(this), btn = form.find('button[type="submit"]'), orig = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin me-1"></i> প্রসেস হচ্ছে...').prop('disabled', true);
        const fd = new FormData(this);   // ছবি সহ সব ফিল্ড
        App.postForm('add_payment', fd)
            .done(function (res) {
                App.toast(res.status, res.message);
                if (res.status === 'success') { form[0].reset(); $('#payPhotoPreview').empty(); loadSummary(); loadLoanOptions(); loadProfiles('active'); App.showSection('profiles'); }
            })
            .always(function () { btn.html(orig).prop('disabled', false); });
    });

    // ---------- নেভিগেশন ----------
    $(document).on('click', '[data-section]', function () {
        const name = $(this).data('section');
        App.showSection(name);
        if (name === 'profiles') loadProfiles(currentStatusFilter);
        if (name === 'ledger') loadLedger(1);
    });

    // ---------- শুরু ----------
    loadSummary();
    loadLoanOptions();
    loadProfiles('active');
    App.showSection('profiles');
});
</script>
