/* dps-mvc.js — front-end controller লজিক (MVC-এর "View" অংশের behavior) */
'use strict';

let currentAccStatus  = 'active';
let currentDetailId   = null;
let currentDetailPage = 1;
let ledgerPage        = 1;
let ledgerTotalPages  = 1;
let reportMode        = 'monthly';
let reportPage         = 1;
let reportTotalPages   = 1;

/* ════════════════════════════════════════
   🌙 ডার্ক / লাইট মোড টগল
   ════════════════════════════════════════ */
function applyThemeIcon() {
    const icon = document.getElementById('themeToggleIcon');
    if (!icon) return;
    const isDark = document.documentElement.classList.contains('dark-mode');
    icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
}

function toggleDarkMode() {
    const html = document.documentElement;
    html.classList.toggle('dark-mode');
    const isDark = html.classList.contains('dark-mode');
    try { localStorage.setItem('dps_theme', isDark ? 'dark' : 'light'); } catch (e) { /* ignore */ }
    applyThemeIcon();
}

/* ════════════════════════════════════════
   🔔 TOAST
   ════════════════════════════════════════ */
function showNotice(type, msg) {
    const el = document.createElement('div');
    el.className = `dps-toast toast-${type}`;
    el.textContent = msg;
    document.getElementById('toastStack').appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

function jsAttr(s) {
    return String(s ?? '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function money(n) {
    return '৳' + parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
}

/* ════════════════════════════════════════
   📡 API HELPER
   ════════════════════════════════════════ */
function apiPost(data, opts = {}) {
    return $.ajax({
        url: API_URL,
        method: 'POST',
        data,
        dataType: 'json',
        processData: opts.processData !== undefined ? opts.processData : true,
        contentType: opts.contentType !== undefined ? opts.contentType : 'application/x-www-form-urlencoded; charset=UTF-8',
    }).fail(xhr => {
        // সেশন শেষ হয়ে গেলে (401) — সব ভিউ যেন চিরকাল স্পিনার দেখিয়ে আটকে না থাকে, সরাসরি লগইন পেজে পাঠানো হয়
        if (xhr.status === 401) {
            showNotice('error', 'সেশন শেষ হয়ে গেছে — লগইন পেজে পাঠানো হচ্ছে...');
            setTimeout(() => { window.location.href = 'index.php'; }, 1200);
        }
    });
}

/* ════════════════════════════════════════
   🖨️ প্রিন্ট (গ্লোবাল লেজার ও পার-অ্যাকাউন্ট লেজার)
   ════════════════════════════════════════ */
function printSection(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.classList.add('print-area-active');
    document.body.classList.add('printing-ledger');
    window.print();
}
window.addEventListener('afterprint', () => {
    document.querySelectorAll('.print-area-active').forEach(el => el.classList.remove('print-area-active'));
    document.body.classList.remove('printing-ledger');
});
document.getElementById('printLedgerBtn').addEventListener('click', () => printSection('viewLedger'));
document.getElementById('printDetailLedgerBtn').addEventListener('click', () => printSection('sheetAccDetail'));

/* ════════════════════════════════════════
   💰 অ্যাকাউন্ট ডিটেইল থেকে সরাসরি "জমা করুন" শর্টকাট
   ════════════════════════════════════════ */
function openDepositForAccount(id) {
    closeSheet();
    fetchActiveDropdown().then(() => {
        const sel = document.getElementById('depositSelectClient');
        if (sel) sel.value = id;
        openSheet('sheetDeposit');
    });
}

/* ════════════════════════════════════════
   🧭 VIEW SWITCHING (বটম নেভ)
   ════════════════════════════════════════ */
function switchView(view) {
    document.querySelectorAll('.app-view').forEach(v => v.classList.add('d-none'));
    document.getElementById('view' + view.charAt(0).toUpperCase() + view.slice(1)).classList.remove('d-none');
    document.querySelectorAll('.bn-item[data-view]').forEach(b => b.classList.toggle('on', b.dataset.view === view));

    if (view === 'ledger') fetchGlobalLedger();
    if (view === 'reports') fetchReport();
}

document.querySelectorAll('.bn-item[data-view]').forEach(btn => {
    btn.addEventListener('click', () => { closeSpeedDial(); switchView(btn.dataset.view); });
});

/* ════════════════════════════════════════
   📋 বটম শীট খোলা/বন্ধ করা
   ════════════════════════════════════════ */
function openSheet(id) { document.getElementById(id).classList.add('open'); }
function closeSheet() { document.querySelectorAll('.sheet-overlay').forEach(s => s.classList.remove('open')); }
document.querySelectorAll('.sheet-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) closeSheet(); });
});

/* ── স্পিড ডায়াল (FAB) ── */
function setSpeedDial(open) {
    const dial = document.getElementById('speedDial');
    const backdrop = document.getElementById('speedDialBackdrop');
    const fab = document.getElementById('fabAdd');
    dial.classList.toggle('open', open);
    backdrop.classList.toggle('open', open);
    fab.classList.toggle('sd-open', open);
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
}
function closeSpeedDial() { setSpeedDial(false); }

document.getElementById('fabAdd').addEventListener('click', () => {
    setSpeedDial(!document.getElementById('speedDial').classList.contains('open'));
});
document.getElementById('speedDialBackdrop').addEventListener('click', closeSpeedDial);

document.getElementById('fabNewAccount').addEventListener('click', () => { closeSpeedDial(); openSheet('sheetAddAccount'); });
document.getElementById('fabNewDeposit').addEventListener('click', () => { closeSpeedDial(); fetchActiveDropdown(); openSheet('sheetDeposit'); });
document.getElementById('fabNewWithdraw').addEventListener('click', () => { closeSpeedDial(); fetchActiveDropdown(); openSheet('sheetWithdraw'); });

/* ════════════════════════════════════════
   📊 সামারি কার্ড
   ════════════════════════════════════════ */
function fetchSummary() {
    apiPost({ action: 'fetch_dps_summary' }).done(r => {
        if (r.status !== 'success') { showNotice('error', r.message || 'সামারি লোড করা যায়নি।'); return; }
        document.getElementById('sumTotalBalance').textContent  = money(r.totalBalance);
        document.getElementById('sumTodayDeposit').textContent  = money(r.todayDeposit);
        document.getElementById('sumTodayWithdraw').textContent = money(r.todayWithdraw);
        document.getElementById('sumActiveCount').textContent   = r.activeCount;
        const tp = document.getElementById('sumTodayProfit');
        const tpr = document.getElementById('sumTotalProfit');
        const ic = document.getElementById('sumInactiveCount');
        if (tp)  tp.textContent  = money(r.todayProfit);
        if (tpr) tpr.textContent = money(r.totalProfit);
        if (ic)  ic.textContent  = r.inactiveCount;

        // প্রোফাইল পেজের সারসংক্ষেপও একই ডেটা দিয়ে আপডেট হয়
        const pb = document.getElementById('profTotalBalance');
        const pa = document.getElementById('profActiveCount');
        const pp = document.getElementById('profTotalProfit');
        if (pb) pb.textContent = money(r.totalBalance);
        if (pa) pa.textContent = r.activeCount;
        if (pp) pp.textContent = money(r.totalProfit);
    }).fail(() => showNotice('error', 'সামারি লোড করতে ব্যর্থ — ইন্টারনেট/সেশন চেক করুন।'));
}

/* ════════════════════════════════════════
   🔔 কার কিস্তি বকেয়া/আজ — টোস্ট নোটিফিকেশন (উপরে চলে আসে)
   ════════════════════════════════════════ */
function fetchDueSoon() {
    apiPost({ action: 'fetch_due_soon' }).done(r => {
        if (r.status !== 'success' || !r.due_soon.length) return;
        r.due_soon.slice(0, 3).forEach((d, i) => {
            setTimeout(() => {
                const label = d.days_until_due < 0 ? 'বকেয়া!' : (d.days_until_due == 0 ? 'আজ জমা দিন' : `${d.days_until_due} দিন বাকি`);
                const el = document.createElement('div');
                el.className = 'dps-toast toast-due';
                el.textContent = `⏰ ${d.client_name} — ${label}`;
                document.getElementById('toastStack').appendChild(el);
                setTimeout(() => el.remove(), 4500);
            }, i * 700);
        });
    }); // নীরব ব্যর্থতা এখানে গ্রহণযোগ্য — এটা শুধু একটা ঐচ্ছিক রিমাইন্ডার টোস্ট, মূল UI আটকে থাকে না
}

/* ════════════════════════════════════════
   🗂️ অ্যাকাউন্ট লিস্ট (কার্ড)
   ════════════════════════════════════════ */
document.querySelectorAll('.dps-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.dps-tab').forEach(t => t.classList.remove('on'));
        tab.classList.add('on');
        currentAccStatus = tab.dataset.status;
        loadAccounts(currentAccStatus);
    });
});

function loadAccounts(status) {
    const grid = document.getElementById('accountGrid');
    grid.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div>';

    apiPost({ action: 'fetch_dps_accounts', status_filter: status }).done(r => {
        if (r.status !== 'success') {
            grid.innerHTML = `<div class="empty-state text-danger">⚠️ ${jsAttr(r.message || 'অ্যাকাউন্ট লোড করা যায়নি।')}
                <div class="mt-2"><button class="mini-act-wide" onclick="loadAccounts('${status}')"><i class="fas fa-rotate-right"></i> আবার চেষ্টা করুন</button></div></div>`;
            return;
        }
        if (!r.accounts.length) {
            grid.innerHTML = '<div class="empty-state"><i class="fas fa-folder-open"></i> কোনো অ্যাকাউন্ট নেই</div>';
            return;
        }
        grid.innerHTML = r.accounts.map((a, idx) => {
            const accentCls = `accent-${idx % 7}`;
            const dueDays = a.days_until_due;
            let dueBadge = '';
            if (dueDays !== null && a.status === 'active') {
                if (dueDays < 0) dueBadge = '<span class="due-badge">বকেয়া</span>';
                else if (dueDays == 0) dueBadge = '<span class="due-badge">আজ</span>';
                else if (dueDays <= 2) dueBadge = `<span class="due-badge">${dueDays} দিন</span>`;
            }
            const photo = a.photo_url || 'assets/img/avatar-placeholder.svg';
            return `<div class="acc-card ${accentCls} ${a.status === 'inactive' ? 'inactive-card' : ''}" onclick="openAccDetail(${a.id})">
                <img src="${photo}" class="acc-photo" onerror="this.src='assets/img/avatar-placeholder.svg'">
                <div class="acc-body">
                    <div class="acc-top">
                        <div><div class="acc-name">${a.client_name}</div><div class="acc-no">${a.account_number || 'ACC-' + (1000 + a.id)}</div></div>
                        <div class="text-end">${dueBadge}<div class="acc-bal">${money(a.total_balance)}</div></div>
                    </div>
                    <div class="acc-pills">
                        <span class="pill pill-rate">${a.interest_rate}%</span>
                        <span class="pill pill-principal">${a.account_type}</span>
                    </div>
                </div>
            </div>`;
        }).join('');
    }).fail(() => {
        grid.innerHTML = `<div class="empty-state text-danger">⚠️ সার্ভারে সংযোগ করা যায়নি — ইন্টারনেট/সেশন চেক করুন।
            <div class="mt-2"><button class="mini-act-wide" onclick="loadAccounts('${status}')"><i class="fas fa-rotate-right"></i> আবার চেষ্টা করুন</button></div></div>`;
    });
}

/* ════════════════════════════════════════
   🔍 অ্যাকাউন্ট ডিটেইল + নিজস্ব লেজার
   ════════════════════════════════════════ */
function openAccDetail(id) {
    currentDetailId = id;
    currentDetailPage = 1;
    apiPost({ action: 'fetch_account_detail', dps_id: id }).done(r => {
        if (r.status !== 'success') { showNotice('error', r.message); return; }
        const a = r.account;
        document.getElementById('accDetailHeader').innerHTML = `
            <div class="det-name">${a.client_name}</div>
            <div class="det-sub">${a.account_number} · ${a.account_type}</div>`;
        document.getElementById('accDetailStats').innerHTML = `
            <div class="stat3-cell"><div class="s3l">জমাকৃত মূল</div><div class="s3v">${money(r.principal_deposited)}</div></div>
            <div class="stat3-cell"><div class="s3l">মোট মুনাফা</div><div class="s3v text-success">${money(r.total_profit)}</div></div>
            <div class="stat3-cell"><div class="s3l">এন্ট্রি সংখ্যা</div><div class="s3v">${r.total_entries}</div></div>`;
        openSheet('sheetAccDetail');
        fetchAccountLedger(id, 1);
    }).fail(() => showNotice('error', 'অ্যাকাউন্ট বিস্তারিত লোড করা যায়নি — আবার চেষ্টা করুন।'));
}

function fetchAccountLedger(dpsId, page) {
    const tbody = document.getElementById('detailLedgerBody');
    apiPost({ action: 'fetch_account_ledger', dps_id: dpsId, page }).done(r => {
        if (r.status !== 'success') { showNotice('error', r.message || 'লেজার লোড করা যায়নি।'); return; }
        currentDetailPage = r.currentPage;
        document.getElementById('detailPageLabel').textContent = `${r.currentPage} / ${r.totalPages}`;
        renderLedgerRows('detailLedgerBody', r.ledger, true);
        document.getElementById('detailOlderBtn').disabled = r.currentPage === r.totalPages;
        document.getElementById('detailNewerBtn').disabled = r.currentPage === 1;
        document.getElementById('detailOlderBtn').onclick = () => { if (currentDetailPage < r.totalPages) fetchAccountLedger(dpsId, currentDetailPage + 1); };
        document.getElementById('detailNewerBtn').onclick = () => { if (currentDetailPage > 1) fetchAccountLedger(dpsId, currentDetailPage - 1); };
    }).fail(() => {
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="empty-state text-danger">⚠️ লেজার লোড করা যায়নি।</td></tr>';
    });
}

/* ════════════════════════════════════════
   📖 গ্লোবাল লেজার (আলাদা টেবিল সিস্টেম)
   ════════════════════════════════════════ */
function fetchGlobalLedger(page = 1) {
    const dpsId = document.getElementById('ledgerAccFilter').value || 'all';
    const tbody = document.getElementById('globalLedgerBody');
    apiPost({ action: 'fetch_dps_ledger', dps_id: dpsId, page }).done(r => {
        if (r.status !== 'success') { showNotice('error', r.message || 'লেজার লোড করা যায়নি।'); return; }
        ledgerPage = r.currentPage;
        ledgerTotalPages = r.totalPages;
        document.getElementById('pageLabel').textContent = `${r.currentPage} / ${r.totalPages}`;
        document.getElementById('pagerOlderBtn').disabled = r.currentPage === r.totalPages;
        document.getElementById('pagerNewerBtn').disabled = r.currentPage === 1;
        renderLedgerRows('globalLedgerBody', r.ledger, false);
    }).fail(() => {
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="empty-state text-danger">⚠️ লেজার লোড করা যায়নি — আবার চেষ্টা করুন।</td></tr>';
    });
}
document.getElementById('pagerOlderBtn').addEventListener('click', () => { if (ledgerPage < ledgerTotalPages) fetchGlobalLedger(ledgerPage + 1); });
document.getElementById('pagerNewerBtn').addEventListener('click', () => { if (ledgerPage > 1) fetchGlobalLedger(ledgerPage - 1); });
document.getElementById('ledgerAccFilter').addEventListener('change', () => fetchGlobalLedger(1));

function renderLedgerRows(bodyId, rows, fromDetail) {
    const tbody = document.getElementById(bodyId);
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="empty-state"><i class="fas fa-folder-open"></i> কোনো হিসাব পাওয়া যায়নি</td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map(row => {
        const isW = parseFloat(row.withdraw_amount) > 0;
        const isOpen = row.description.includes('Opening');
        const dFmt = new Date(row.txn_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' });
        const accCell = fromDetail ? '' : `<td class="text-nowrap"><span class="amt-bal">${row.account_number || row.acc_id}</span><br><span style="font-size:.58rem;color:var(--muted)">${row.client_name}</span></td>`;
        const editAmt = isW ? row.withdraw_amount : row.deposit_amount;
        const editType = isW ? 'withdraw' : 'deposit';
        const actionBtns = isOpen ? '<span class="text-muted">—</span>' : `
            <div class="act-stack">
                <button onclick="editLedgerEntry(${parseInt(row.id)},'${jsAttr(row.description)}',${editAmt},'${editType}',${fromDetail})" class="mini-act act-edit"><i class="fas fa-pen"></i></button>
                <button onclick="deleteLedgerEntry(${parseInt(row.id)},${fromDetail})" class="mini-act act-del"><i class="fas fa-trash"></i></button>
            </div>`;
        return `<tr>
            <td class="text-nowrap">${dFmt}</td>
            ${accCell}
            <td style="max-width:140px;font-size:.66rem;">${row.description}</td>
            <td class="text-end">${parseFloat(row.deposit_amount) > 0 ? (row.description.includes('মুনাফা') ? `<span class="amt-profit">+${money(row.deposit_amount)}</span>` : `<span class="amt-pos">+${money(row.deposit_amount)}</span>`) : '—'}</td>
            <td class="text-end">${parseFloat(row.withdraw_amount) > 0 ? `<span class="amt-neg">-${money(row.withdraw_amount)}</span>` : '—'}</td>
            <td class="text-end amt-bal">${money(row.current_balance)}</td>
            <td class="text-center">${actionBtns}</td>
        </tr>`;
    }).join('');
}

/* ════════════════════════════════════════
   ✏️ লেজার এডিট / ডিলিট (আলাদা concern)
   ════════════════════════════════════════ */
function editLedgerEntry(id, oldDesc, oldAmt, type, fromDetail) {
    const color = type === 'withdraw' ? '#e11d48' : '#10b981';
    Swal.fire({
        title: 'এন্ট্রি এডিট',
        html: `<input id="sw-desc" class="swal2-input" placeholder="বিবরণ" style="font-size:13px;">
               <input id="sw-amt" type="number" class="swal2-input" value="${oldAmt}" style="color:${color};font-weight:900;">`,
        didOpen: () => { document.getElementById('sw-desc').value = oldDesc; },
        showCancelButton: true,
        confirmButtonText: 'আপডেট',
        preConfirm: () => ({ desc: document.getElementById('sw-desc').value, amount: document.getElementById('sw-amt').value }),
    }).then(r => {
        if (!r.isConfirmed) return;
        apiPost({ action: 'edit_dps_ledger', id, desc: r.value.desc, amount: r.value.amount, csrf_token: CSRF }).done(res => {
            if (res.status === 'success') {
                showNotice('success', res.message);
                fetchSummary();
                fromDetail ? fetchAccountLedger(currentDetailId, currentDetailPage) : fetchGlobalLedger(ledgerPage);
            } else showNotice('error', res.message);
        }).fail(() => showNotice('error', 'সার্ভারে সমস্যা হচ্ছে — আবার চেষ্টা করুন।'));
    });
}

function deleteLedgerEntry(id, fromDetail) {
    Swal.fire({ title: 'এন্ট্রি মুছবেন?', text: 'ব্যালেন্স স্বয়ংক্রিয়ভাবে রিক্যালকুলেট হবে!', icon: 'warning', showCancelButton: true, confirmButtonText: 'হ্যাঁ, মুছুন', confirmButtonColor: '#e11d48' })
        .then(r => {
            if (!r.isConfirmed) return;
            apiPost({ action: 'delete_dps_ledger', id, csrf_token: CSRF }).done(res => {
                if (res.status === 'success') {
                    showNotice('success', res.message);
                    fetchSummary();
                    fromDetail ? fetchAccountLedger(currentDetailId, currentDetailPage) : fetchGlobalLedger(ledgerPage);
                } else showNotice('error', res.message);
            }).fail(() => showNotice('error', 'সার্ভারে সমস্যা হচ্ছে — আবার চেষ্টা করুন।'));
        });
}

function toggleDpsStatus(accId) {
    Swal.fire({ title: 'স্ট্যাটাস পরিবর্তন?', icon: 'question', showCancelButton: true, confirmButtonText: 'পরিবর্তন করুন' }).then(r => {
        if (!r.isConfirmed) return;
        apiPost({ action: 'toggle_dps_status', acc_id: accId, csrf_token: CSRF }).done(res => {
            if (res.status === 'success') { showNotice('success', 'স্ট্যাটাস পরিবর্তন হয়েছে।'); closeSheet(); fetchSummary(); loadAccounts(currentAccStatus); }
            else showNotice('error', res.message);
        }).fail(() => showNotice('error', 'সার্ভারে সমস্যা হচ্ছে — আবার চেষ্টা করুন।'));
    });
}

/* ════════════════════════════════════════
   📋 ড্রপডাউন (জমা/উত্তোলন ফর্মের জন্য)
   ════════════════════════════════════════ */
function fetchActiveDropdown() {
    return apiPost({ action: 'fetch_active_dropdown' }).done(r => {
        if (r.status !== 'success') { showNotice('error', r.message || 'অ্যাকাউন্ট তালিকা লোড করা যায়নি।'); return; }
        let opts = '<option value="">— অ্যাকাউন্ট সিলেক্ট করুন —</option>';
        r.accounts.forEach(a => { opts += `<option value="${a.id}">${a.account_number || ('ACC-' + (1000 + a.id))} : ${a.client_name}</option>`; });
        document.getElementById('depositSelectClient').innerHTML = opts;
        document.getElementById('withdrawSelectClient').innerHTML = opts;

        let filterOpts = '<option value="all">সব অ্যাকাউন্ট</option>';
        r.accounts.forEach(a => { filterOpts += `<option value="${a.id}">${a.account_number || ('ACC-' + (1000 + a.id))} : ${a.client_name}</option>`; });
        document.getElementById('ledgerAccFilter').innerHTML = filterOpts;
        document.getElementById('reportAccFilter').innerHTML = filterOpts;
    }).fail(() => showNotice('error', 'অ্যাকাউন্ট তালিকা লোড করতে ব্যর্থ — আবার চেষ্টা করুন।'));
}

/* ════════════════════════════════════════
   🧾 অ্যাকাউন্ট এডিট শীট খোলা
   ════════════════════════════════════════ */
function openEditAccount(id) {
    apiPost({ action: 'fetch_account_detail', dps_id: id }).done(r => {
        if (r.status !== 'success') { showNotice('error', r.message || 'অ্যাকাউন্ট তথ্য লোড করা যায়নি।'); return; }
        const a = r.account;
        document.getElementById('editAccId').value = a.id;
        document.getElementById('editClientName').value = a.client_name;
        document.getElementById('editAccountNumber').value = a.account_number;
        document.getElementById('editAccountType').value = a.account_type;
        document.getElementById('editFrequency').value = a.frequency;
        document.getElementById('editInstallment').value = a.installment_amount;
        document.getElementById('editRate').value = a.interest_rate;
        const totalMonths = parseInt(a.duration_months) || 1;
        document.getElementById('editDurationYears').value = Math.floor(totalMonths / 12);
        document.getElementById('editDurationMonths').value = totalMonths % 12;
        document.getElementById('editPhotoPreview').src = a.photo_url || 'assets/img/avatar-placeholder.svg';
        openSheet('sheetEditAccount');
    }).fail(() => showNotice('error', 'অ্যাকাউন্ট তথ্য লোড করতে ব্যর্থ — আবার চেষ্টা করুন।'));
}

/* ════════════════════════════════════════
   📷 ছবি প্রিভিউ + আপলোড (গ্যালারি অথবা সরাসরি ক্যামেরা)
   ════════════════════════════════════════ */
document.querySelectorAll('.photo-input').forEach(input => {
    input.addEventListener('change', function () {
        if (!this.files || !this.files.length) return;
        const file = this.files[0];

        if (file.size > 10 * 1024 * 1024) {
            showNotice('error', 'ছবির সাইজ ১০ এমবি-এর বেশি হতে পারবে না।');
            this.value = '';
            return;
        }

        const preview = document.getElementById(this.dataset.preview);
        if (preview) preview.src = URL.createObjectURL(file);

        // add-account ফর্মে দুটো ইনপুট (গ্যালারি/ক্যামেরা) থেকে একটাই canonical ফিল্ডে ফাইল কপি হয়
        const targetName = this.dataset.targetName;
        if (targetName) {
            const canonical = this.closest('form').querySelector(`input[name="${targetName}"]`);
            const dt = new DataTransfer();
            dt.items.add(file);
            canonical.files = dt.files;
        }

        // এডিট শীটে ছবি বদলালে সঙ্গে সঙ্গে আলাদা এন্ডপয়েন্টে আপলোড হয়
        if (this.id === 'editPhotoGallery' || this.id === 'editPhotoCamera') {
            const fd = new FormData();
            fd.append('action', 'upload_account_photo');
            fd.append('csrf_token', CSRF);
            fd.append('acc_id', document.getElementById('editAccId').value);
            fd.append('account_photo', file);
            apiPost(fd, { processData: false, contentType: false }).done(res => {
                if (res.status === 'success') { showNotice('success', res.message); loadAccounts(currentAccStatus); }
                else showNotice('error', res.message);
            }).fail(() => showNotice('error', 'ছবি আপলোড ব্যর্থ হয়েছে — আবার চেষ্টা করুন।'));
        }
    });
});

/* ════════════════════════════════════════
   📬 ফর্ম হ্যান্ডলার (জেনেরিক)
   ════════════════════════════════════════ */
function handleForm(formId, actionName, opts = {}) {
    const form = document.getElementById(formId);
    const isMultipart = !!form.querySelector('input[type="file"]');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        let payload, ajaxOpts;
        if (isMultipart) {
            payload = new FormData(form);
            payload.append('action', actionName);
            ajaxOpts = { processData: false, contentType: false };
        } else {
            payload = $(form).serialize() + '&action=' + actionName;
            ajaxOpts = {};
        }

        apiPost(payload, ajaxOpts).done(res => {
            if (res.status === 'success') {
                showNotice('success', res.message || 'সফলভাবে সম্পন্ন হয়েছে।');
                form.reset();
                closeSheet();
                fetchSummary();
                if (opts.onSuccess) opts.onSuccess(res);
            } else {
                showNotice('error', res.message || 'অনুরোধ ব্যর্থ হয়েছে।');
            }
        }).fail(() => showNotice('error', 'সার্ভারে সমস্যা হচ্ছে — আবার চেষ্টা করুন।'))
          .always(() => { btn.innerHTML = orig; btn.disabled = false; });
    });
}

/* ════════════════════════════════════════
   📈 রিপোর্ট (মাসভিত্তিক / সাপ্তাহিক)
   ════════════════════════════════════════ */
document.querySelectorAll('.report-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.report-tab').forEach(t => t.classList.remove('on'));
        tab.classList.add('on');
        reportMode = tab.dataset.mode;
        document.getElementById('reportPeriodHead').textContent = reportMode === 'monthly' ? 'মাস' : 'সপ্তাহ';
        fetchReport(1);
    });
});
document.getElementById('reportAccFilter').addEventListener('change', () => fetchReport(1));
document.getElementById('reportOlderBtn').addEventListener('click', () => { if (reportPage < reportTotalPages) fetchReport(reportPage + 1); });
document.getElementById('reportNewerBtn').addEventListener('click', () => { if (reportPage > 1) fetchReport(reportPage - 1); });

function fetchReport(page = reportPage) {
    const dpsId = document.getElementById('reportAccFilter').value || 'all';
    const action = reportMode === 'monthly' ? 'fetch_monthly_report' : 'fetch_weekly_report';
    const tbody = document.getElementById('reportBody');
    apiPost({ action, dps_id: dpsId, page }).done(r => {
        if (r.status !== 'success') { tbody.innerHTML = `<tr><td colspan="6" class="empty-state text-danger">⚠️ ${jsAttr(r.message || 'রিপোর্ট লোড করা যায়নি।')}</td></tr>`; return; }
        reportPage = r.currentPage || 1;
        reportTotalPages = r.totalPages || 1;
        document.getElementById('reportPageLabel').textContent = `${reportPage} / ${reportTotalPages}`;
        document.getElementById('reportOlderBtn').disabled = reportPage === reportTotalPages;
        document.getElementById('reportNewerBtn').disabled = reportPage === 1;
        if (!r.report.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="fas fa-folder-open"></i> কোনো ডেটা নেই</td></tr>';
            return;
        }
        tbody.innerHTML = r.report.map(row => `
            <tr>
                <td class="text-nowrap">${row.label}</td>
                <td class="text-nowrap"><span class="amt-bal">${row.account_number || row.dps_id}</span><br><span style="font-size:.58rem;color:var(--muted)">${row.client_name}</span></td>
                <td class="text-end amt-pos">${money(row.deposit_total)}</td>
                <td class="text-end amt-profit">${money(row.profit_total)}</td>
                <td class="text-end amt-neg">${money(row.withdraw_total)}</td>
                <td class="text-center">${row.entry_count}</td>
            </tr>`).join('');
    }).fail(() => {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-state text-danger">⚠️ রিপোর্ট লোড করা যায়নি — আবার চেষ্টা করুন।</td></tr>';
    });
}

/* ════════════════════════════════════════
   🚀 INIT
   ════════════════════════════════════════ */
$(document).ready(function () {
    applyThemeIcon();

    // পিছনের তারিখ টগল — ডিফল্টে লুকানো থাকে, চাইলে দেখিয়ে অতীত তারিখ বেছে নেওয়া যায়
    const depToggle = document.getElementById('depositBackDateToggle');
    if (depToggle) depToggle.addEventListener('click', () => document.getElementById('depositDateWrap').classList.toggle('d-none'));
    const wdToggle = document.getElementById('withdrawBackDateToggle');
    if (wdToggle) wdToggle.addEventListener('click', () => document.getElementById('withdrawDateWrap').classList.toggle('d-none'));

    fetchSummary();
    fetchDueSoon();
    fetchActiveDropdown();
    loadAccounts('active');

    handleForm('dpsAccountForm', 'add_dps_account', { onSuccess: () => { loadAccounts(currentAccStatus); fetchActiveDropdown(); } });
    handleForm('dpsEditAccountForm', 'edit_dps_account', { onSuccess: () => { loadAccounts(currentAccStatus); fetchActiveDropdown(); } });
    handleForm('dpsDepositForm', 'add_dps_deposit', { onSuccess: () => { loadAccounts(currentAccStatus); fetchActiveDropdown(); } });
    handleForm('dpsWithdrawForm', 'add_dps_withdraw', { onSuccess: () => { loadAccounts(currentAccStatus); fetchActiveDropdown(); } });
});
