</div><!-- /.app-shell -->

<?php
/* বর্তমান রুট থেকে কোন ট্যাব active তা বের করা হয় (index.php এর
   APP_BASE_URL ও ?url= প্যারামিটার ব্যবহার করে)। */
$__navBase  = defined('APP_BASE_URL') ? APP_BASE_URL : '';
$__navRoute = trim((string)($_GET['url'] ?? ''), '/');
if ($__navRoute === '') { $__navRoute = 'loan/dashboard'; }
$__isLoan   = str_starts_with($__navRoute, 'loan');
$__isKisti  = str_starts_with($__navRoute, 'repayment');
$__isReport = str_starts_with($__navRoute, 'report');
?>

<!-- ============ নিচের নেভি বার (অ্যাপ-স্টাইল) ============ -->
<div class="bottom-nav-wrap">
    <nav class="bottom-nav" aria-label="প্রধান নেভিগেশন">
        <a class="bn-item<?= $__isLoan ? ' on' : '' ?>" data-nav="home" href="<?= Security::e($__navBase) ?>/loan/dashboard">
            <i class="fas fa-house"></i><span>হোম</span>
        </a>
        <a class="bn-item" data-nav="profiles" href="<?= Security::e($__navBase) ?>/loan/dashboard#profiles">
            <i class="fas fa-id-card"></i><span>লোন</span>
        </a>
        <span class="bn-fab-slot">
            <a class="bn-fab" href="<?= Security::e($__navBase) ?>/repayment" title="কিস্তি শোধ">
                <i class="fas fa-hand-holding-dollar"></i>
            </a>
        </span>
        <a class="bn-item<?= $__isReport ? ' on' : '' ?>" href="<?= Security::e($__navBase) ?>/report">
            <i class="fas fa-chart-column"></i><span>রিপোর্ট</span>
        </a>
        <button type="button" class="bn-item" id="btnOpenSettings">
            <i class="fas fa-users"></i><span>সেটিংস</span>
        </button>
    </nav>
</div>

<!-- ============ সেটিংস শিট ============ -->
<div class="settings-sheet-backdrop" id="settingsSheet">
    <div class="settings-sheet" role="dialog" aria-label="সেটিংস">
        <div class="sheet-grip"></div>
        <div class="sheet-head">সেটিংস</div>
        <button type="button" class="sheet-row" id="sheetTheme" style="width:100%;border:none;background:none;text-align:left;font-family:inherit;">
            <span class="sr-ic" style="background:color-mix(in srgb,var(--c-purple) 15%,var(--surface));color:var(--c-purple);"><i class="fas fa-moon" data-sheet-theme-icon></i></span>
            <span class="sr-tx"><span class="sr-title">থিম পরিবর্তন</span><span class="sr-sub" data-sheet-theme-label>লাইট / ডার্ক টগল</span></span>
            <i class="fas fa-chevron-right sr-chev"></i>
        </button>
        <a class="sheet-row" href="<?= Security::e($__navBase) ?>/report">
            <span class="sr-ic" style="background:color-mix(in srgb,var(--c-green) 15%,var(--surface));color:var(--c-green);"><i class="fas fa-print"></i></span>
            <span class="sr-tx"><span class="sr-title">রিপোর্ট ও প্রিন্ট</span><span class="sr-sub">তারিখভিত্তিক হিসাব ও এক্সপোর্ট</span></span>
            <i class="fas fa-chevron-right sr-chev"></i>
        </a>
        <a class="sheet-row" href="/logout.php">
            <span class="sr-ic" style="background:color-mix(in srgb,var(--c-red) 15%,var(--surface));color:var(--c-red);"><i class="fas fa-sign-out-alt"></i></span>
            <span class="sr-tx"><span class="sr-title">লগআউট</span><span class="sr-sub">নিরাপদে বেরিয়ে যান</span></span>
            <i class="fas fa-chevron-right sr-chev"></i>
        </a>
        <button type="button" class="sheet-row" id="sheetClose" style="width:100%;border:none;background:none;text-align:left;font-family:inherit;justify-content:center;color:var(--muted);">
            <span class="sr-title" style="color:var(--muted);">বন্ধ করুন</span>
        </button>
    </div>
</div>

<script>
/**
 * Shared front-end utilities for every Loan module page.
 * No inline onclick string-building anywhere: all handlers are bound
 * with data-* attributes, so a borrower name or description can never
 * break out into executable JavaScript.
 */
const App = (function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const baseUrl   = document.querySelector('meta[name="base-url"]').getAttribute('content');

    /** Escapes a value for safe insertion into an HTML attribute. */
    function escAttr(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /** Escapes a value for safe insertion as HTML text. */
    function escHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }

    function money(amount, decimals) {
        const n = Number(amount) || 0;
        return '৳' + n.toLocaleString('en-US', {
            minimumFractionDigits: decimals === undefined ? 2 : decimals,
            maximumFractionDigits: decimals === undefined ? 2 : decimals
        });
    }

    function formatDate(value) {
        if (!value) return '—';
        const d = new Date(value);
        if (isNaN(d.getTime())) return escHtml(value);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function toast(type, message) {
        const stack = document.getElementById('toastStack');
        if (!stack) return;
        const el = document.createElement('div');
        el.className = 'toast t-' + (type === 'success' ? 'success' : 'error');
        const icon = document.createElement('span');
        icon.className = 'tc-ic';
        icon.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check' : 'fa-triangle-exclamation') + '"></i>';
        const msg = document.createElement('span');
        msg.className = 'tc-msg';
        msg.textContent = message;
        el.appendChild(icon);
        el.appendChild(msg);
        stack.appendChild(el);
        requestAnimationFrame(() => el.classList.add('show'));
        setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 320); }, 3200);
    }

    /**
     * সেশন শেষ হয়ে গেলে সার্ভার 401 পাঠায় (Config/database.php থেকে)।
     * এখানে একবারই ধরা হয়, যাতে প্রতিটি কলে আলাদা করে লিখতে না হয়।
     */
    let sessionNoticeShown = false;

    $(document).ajaxError(function (event, jqXHR) {
        if (jqXHR.status !== 401 || sessionNoticeShown) return;
        sessionNoticeShown = true;
        Swal.fire({
            title: 'সেশনের মেয়াদ শেষ',
            text: 'নিরাপত্তার জন্য আপনাকে লগআউট করা হয়েছে। আবার লগইন করুন।',
            icon: 'warning',
            confirmButtonText: 'লগইন পেজে যান',
            confirmButtonColor: '#4f46e5',
            allowOutsideClick: false
        }).then(function () {
            window.location.href = '/index.php?status=timeout';
        });
    });

    // মডিউলের POST endpoint সার্ভার থেকে আসে — ব্রাউজারের URL থেকে
    // অনুমান করা হয় না, তাই ট্রেইলিং স্ল্যাশেও AJAX ঠিক জায়গায় যায়।
    const moduleEndpoint = (document.querySelector('meta[name="module-endpoint"]') || {})
        .getAttribute ? document.querySelector('meta[name="module-endpoint"]').getAttribute('content')
        : (baseUrl + '/loan');

    /** POSTs to the current module endpoint with the CSRF token attached. */
    function post(action, payload) {
        const body = Object.assign({}, payload || {}, { action: action, csrf_token: csrfToken });
        return $.ajax({
            url: moduleEndpoint,
            method: 'POST',
            data: body,
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).fail(function (jqXHR, textStatus) {
            // JSON ছাড়া কিছু ফিরলে (HTML redirect, PHP warning ইত্যাদি)
            // dataType:'json' নীরবে ব্যর্থ হতো এবং ইউজার চিরকাল "লোড
            // হচ্ছে..." দেখত। এখন 401 বাদে বাকি সব ব্যর্থতা টোস্টে দেখানো
            // হয়, যাতে আসল সমস্যা চোখে পড়ে।
            if (jqXHR.status === 401) return; // সেশন হ্যান্ডলার আলাদাভাবে দেখে
            const snippet = (jqXHR.responseText || '').replace(/<[^>]*>/g, '').trim().slice(0, 120);
            toast('error', snippet
                ? ('সার্ভার সাড়া দিয়েছে কিন্তু তা সঠিক নয়: ' + snippet)
                : ('সার্ভারে সমস্যা (' + textStatus + ') — আবার চেষ্টা করুন।'));
        });
    }

    /**
     * ছবি সহ ফর্ম পাঠানোর জন্য (multipart/form-data)।
     * $form = একটি FormData অবজেক্ট। CSRF ও action অটো যুক্ত হয়।
     */
    function postForm(action, formData) {
        formData.append('action', action);
        formData.append('csrf_token', csrfToken);
        return $.ajax({
            url: moduleEndpoint,
            method: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,   // FormData নিজেই এনকোড করে
            contentType: false,   // ব্রাউজার boundary সহ ঠিক header বসাবে
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).fail(function (jqXHR, textStatus) {
            if (jqXHR.status === 401) return;
            const snippet = (jqXHR.responseText || '').replace(/<[^>]*>/g, '').trim().slice(0, 120);
            toast('error', snippet
                ? ('সার্ভার সাড়া দিয়েছে কিন্তু তা সঠিক নয়: ' + snippet)
                : ('সার্ভারে সমস্যা (' + textStatus + ') — আবার চেষ্টা করুন।'));
        });
    }

    /** Renders a numbered pager into a container. */
    function pager(containerId, meta, callbackKey) {
        const el = document.getElementById(containerId);
        if (!el) return;
        el.innerHTML = '';
        if (!meta || meta.pages <= 1) return;

        let html = '<div class="pager">';
        html += '<button class="pg-btn" data-page="' + (meta.page - 1) + '" data-cb="' + escAttr(callbackKey) + '"'
             + (meta.page <= 1 ? ' disabled' : '') + '><i class="fas fa-chevron-left"></i></button>';

        for (let i = 1; i <= meta.pages; i++) {
            if (i === 1 || i === meta.pages || Math.abs(i - meta.page) <= 1) {
                html += '<button class="pg-btn' + (i === meta.page ? ' on' : '') + '" data-page="' + i
                     + '" data-cb="' + escAttr(callbackKey) + '">' + i + '</button>';
            } else if (Math.abs(i - meta.page) === 2) {
                html += '<span class="pg-dots">…</span>';
            }
        }

        html += '<button class="pg-btn" data-page="' + (meta.page + 1) + '" data-cb="' + escAttr(callbackKey) + '"'
             + (meta.page >= meta.pages ? ' disabled' : '') + '><i class="fas fa-chevron-right"></i></button>';
        html += '</div><div class="pg-info">পেজ ' + meta.page + ' / ' + meta.pages + ' — মোট ' + meta.total + ' টি এন্ট্রি</div>';
        el.innerHTML = html;
    }

    const pagerHandlers = {};

    function onPage(key, handler) {
        pagerHandlers[key] = handler;
    }

    $(document).on('click', '.pg-btn', function () {
        const page = parseInt($(this).data('page'), 10);
        const key  = $(this).data('cb');
        if (pagerHandlers[key] && !isNaN(page) && page >= 1) {
            pagerHandlers[key](page);
        }
    });

    function dueBadge(badge) {
        if (!badge || !badge.label) return '';
        return '<span class="due-chip ' + escAttr(badge.css) + '">'
             + '<i class="fas fa-calendar-day"></i>' + escHtml(badge.label) + '</span>';
    }

    function showSection(name) {
        document.querySelectorAll('.section-panel').forEach(s => { s.style.display = 'none'; });
        const target = document.getElementById('section-' + name);
        if (target) {
            target.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    return {
        csrfToken: csrfToken,
        baseUrl: baseUrl,
        escAttr: escAttr,
        escHtml: escHtml,
        money: money,
        formatDate: formatDate,
        toast: toast,
        post: post,
        postForm: postForm,
        pager: pager,
        onPage: onPage,
        dueBadge: dueBadge,
        showSection: showSection
    };
})();
</script>

<script>
/* নিচের নেভি বারের ইন্টারঅ্যাকশন — ব্যাকএন্ড API স্পর্শ করে না। */
(function () {
    // সেটিংস শিট খোলা/বন্ধ
    var sheet = document.getElementById('settingsSheet');
    var openBtn = document.getElementById('btnOpenSettings');
    var closeBtn = document.getElementById('sheetClose');
    function openSheet() { if (sheet) sheet.classList.add('open'); }
    function closeSheet() { if (sheet) sheet.classList.remove('open'); }
    if (openBtn) openBtn.addEventListener('click', openSheet);
    if (closeBtn) closeBtn.addEventListener('click', closeSheet);
    if (sheet) sheet.addEventListener('click', function (e) { if (e.target === sheet) closeSheet(); });

    // শিটের ভিতরের থিম টগল — header.php এর toggleTheme() পুনর্ব্যবহার
    var sheetTheme = document.getElementById('sheetTheme');
    if (sheetTheme) sheetTheme.addEventListener('click', function () {
        if (typeof toggleTheme === 'function') toggleTheme();
        syncSheetTheme();
    });
    function syncSheetTheme() {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        var ic = document.querySelector('[data-sheet-theme-icon]');
        var lb = document.querySelector('[data-sheet-theme-label]');
        if (ic) ic.className = (dark ? 'fas fa-sun' : 'fas fa-moon');
        if (lb) lb.textContent = (dark ? 'এখন: ডার্ক মোড' : 'এখন: লাইট মোড');
    }
    syncSheetTheme();

    // ড্যাশবোর্ডে "লোন" ট্যাব চাপলে সরাসরি প্রোফাইল সেকশন খোলে
    var navHome = document.querySelector('.bn-item[data-nav="home"]');
    var navProfiles = document.querySelector('.bn-item[data-nav="profiles"]');
    var onDashboard = !!document.getElementById('section-profiles');

    function applyDashboardActive() {
        if (!onDashboard || !navHome || !navProfiles) return;
        var wantProfiles = location.hash === '#profiles';
        navProfiles.classList.toggle('on', wantProfiles);
        navHome.classList.toggle('on', !wantProfiles);
        if (wantProfiles && window.App && typeof App.showSection === 'function') {
            App.showSection('profiles');
        }
    }
    if (onDashboard && navProfiles) {
        navProfiles.addEventListener('click', function (e) {
            e.preventDefault();
            if (location.hash !== '#profiles') location.hash = '#profiles';
            applyDashboardActive();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        navHome.addEventListener('click', function (e) {
            e.preventDefault();
            if (location.hash) history.replaceState(null, '', location.pathname + location.search);
            applyDashboardActive();
            if (window.App && typeof App.showSection === 'function') App.showSection('profiles');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        window.addEventListener('hashchange', applyDashboardActive);
        applyDashboardActive();
    }
})();
</script>

</body>
</html>
