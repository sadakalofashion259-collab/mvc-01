/* =========================================================================
   SADA KALO FASHION — Ledger Report
   Frontend JS (Bootstrap 5.3.8 based)
   ========================================================================= */

(function () {
    'use strict';

    // ─── THEME TOGGLE ──────────────────────────────────────────────────────
    const html   = document.documentElement;
    const tBtn   = document.getElementById('skThemeBtn');
    const tIcon  = tBtn ? tBtn.querySelector('i') : null;

    function applyTheme(t) {
        html.setAttribute('data-bs-theme', t);
        if (tIcon) tIcon.className = (t === 'dark') ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', t === 'dark' ? '#0b1220' : '#eef2f8');
    }
    const saved = localStorage.getItem('skf-theme') || 'dark';
    applyTheme(saved);

    if (tBtn) {
        tBtn.addEventListener('click', () => {
            const now = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(now);
            localStorage.setItem('skf-theme', now);
        });
    }

    // ─── LIVE CLOCK ────────────────────────────────────────────────────────
    const clock = document.getElementById('skClock');
    if (clock) {
        const tick = () => {
            const d = new Date();
            clock.textContent = d.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', hour12: true
            });
        };
        tick(); setInterval(tick, 30000);
    }

    // ─── QUICK DATE FILTERS ────────────────────────────────────────────────
    const fmt = d => d.toISOString().split('T')[0];
    const goto = (from, to) => {
        const u = new URL(window.location.href);
        u.searchParams.set('from_date', from);
        u.searchParams.set('to_date',   to);
        window.location.href = u.pathname + '?' + u.searchParams.toString();
    };
    window.skQuickDate = function (days) {
        const today = new Date();
        const start = new Date();
        start.setDate(today.getDate() - (days - 1));
        goto(fmt(start), fmt(today));
    };
    window.skQuickMonth = function () {
        const today = new Date();
        const start = new Date(today.getFullYear(), today.getMonth(), 1);
        goto(fmt(start), fmt(today));
    };
    window.skQuickAll = function () { goto('2020-01-01', fmt(new Date())); };

    // ─── IMAGE LIGHTBOX ────────────────────────────────────────────────────
    let imgModalInstance = null;
    window.skShowBig = function (src) {
        const el  = document.getElementById('skImgModal');
        const img = document.getElementById('skBigImg');
        if (!el || !img) return;
        img.src = src;
        imgModalInstance = imgModalInstance || new bootstrap.Modal(el);
        imgModalInstance.show();
    };

    // ─── PASSWORD MODAL STATE ──────────────────────────────────────────────
    const state = { type: '', table: '', id: 0, field: '', val: '', mode: '', newVal: null };

    const pwModalEl  = document.getElementById('skPwModal');
    const pwInpEl    = document.getElementById('skPwInput');
    const pwErrEl    = document.getElementById('skPwErr');
    const pwOkEl     = document.getElementById('skPwOk');
    const pwTitleEl  = document.getElementById('skPwTitle');
    const pwSubEl    = document.getElementById('skPwSub');
    let pwModal      = null;

    function openModal() {
        if (!pwModalEl) return;
        if (!pwModal) pwModal = new bootstrap.Modal(pwModalEl);
        pwInpEl.value = '';
        pwErrEl.textContent = '';
        pwOkEl.disabled = false;
        pwModal.show();
        setTimeout(() => pwInpEl.focus(), 250);
    }
    function closeModal() {
        if (pwModal) pwModal.hide();
    }

    // ─── PUBLIC OPENERS (called from row buttons) ──────────────────────────
    window.openPw = function (type, table, id, field, val) {
        state.type = type; state.table = table; state.id = id;
        state.field = field || ''; state.val = val || '';
        state.mode = 'item'; state.newVal = null;
        pwTitleEl.textContent = (type === 'delete') ? 'এন্ট্রি ডিলিট করবেন?' : 'এন্ট্রি এডিট করবেন?';
        pwSubEl.innerHTML = 'Admin পাসওয়ার্ড দিন<br><small class="text-neg">ডাটাবেজ থেকে যাচাই হবে</small>';
        pwOkEl.textContent = (type === 'delete') ? 'ডিলিট করুন' : 'আপডেট করুন';
        openModal();
    };
    window.openPwDps = function (id) {
        Object.assign(state, { type: '', table: '', id, field: '', val: '', mode: 'dps', newVal: null });
        pwTitleEl.textContent = 'DPS এন্ট্রি ডিলিট করবেন?';
        pwSubEl.innerHTML = 'Admin পাসওয়ার্ড দিন<br><small class="text-neg">ডাটাবেজ থেকে যাচাই হবে</small>';
        pwOkEl.textContent = 'ডিলিট করুন';
        openModal();
    };
    window.openPwLoan = function (id) {
        Object.assign(state, { type: '', table: '', id, field: '', val: '', mode: 'loan', newVal: null });
        pwTitleEl.textContent = 'লোন এন্ট্রি ডিলিট করবেন?';
        pwSubEl.innerHTML = 'Admin পাসওয়ার্ড দিন<br><small class="text-neg">ডাটাবেজ থেকে যাচাই হবে</small>';
        pwOkEl.textContent = 'ডিলিট করুন';
        openModal();
    };
    window.openPwCard = function (id) {
        Object.assign(state, { type: '', table: '', id, field: '', val: '', mode: 'card', newVal: null });
        pwTitleEl.textContent = 'কার্ড এন্ট্রি ডিলিট করবেন?';
        pwSubEl.innerHTML = 'Admin পাসওয়ার্ড দিন<br><small class="text-neg">ডাটাবেজ থেকে যাচাই হবে</small>';
        pwOkEl.textContent = 'ডিলিট করুন';
        openModal();
    };

    // ─── CONFIRM + AJAX ────────────────────────────────────────────────────
    function confirmPw() {
        const pass = (pwInpEl.value || '').trim();
        if (!pass) { pwErrEl.textContent = 'পাসওয়ার্ড লিখুন।'; return; }

        if (state.mode === 'item' && state.type === 'edit') {
            const nv = prompt('নতুন মান লিখুন (বর্তমান: ' + state.val + '):', state.val);
            if (nv === null) return;
            state.newVal = nv;
        }

        pwOkEl.disabled = true;
        const original = pwOkEl.textContent;
        pwOkEl.textContent = 'যাচাই হচ্ছে…';

        const fd = new FormData();
        if (state.mode === 'item') {
            fd.append('ajax_action', 'item_action');
            fd.append('type',  state.type);
            fd.append('table', state.table);
            fd.append('id',    state.id);
            fd.append('field', state.field);
            fd.append('val',   (state.newVal !== null) ? state.newVal : state.val);
        } else if (state.mode === 'dps')  { fd.append('ajax_action', 'delete_dps');         fd.append('id', state.id); }
        else if (state.mode === 'loan') { fd.append('ajax_action', 'delete_loan');        fd.append('id', state.id); }
        else if (state.mode === 'card') { fd.append('ajax_action', 'delete_card_ledger'); fd.append('id', state.id); }
        fd.append('pass', pass);

        fetch('historys.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                pwOkEl.disabled = false;
                pwOkEl.textContent = original;
                if (res && res.status === 'success') {
                    closeModal();
                    showToast(res.message, true);
                    setTimeout(() => location.reload(), 900);
                } else {
                    pwErrEl.textContent = (res && res.message) ? res.message : 'যাচাই ব্যর্থ হয়েছে।';
                }
            })
            .catch(() => {
                pwOkEl.disabled = false;
                pwOkEl.textContent = original;
                pwErrEl.textContent = 'সার্ভার সমস্যা হয়েছে।';
            });
    }

    if (pwOkEl) pwOkEl.addEventListener('click', confirmPw);
    if (pwInpEl) {
        pwInpEl.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); confirmPw(); }
        });
    }

    // ─── TOAST ─────────────────────────────────────────────────────────────
    function showToast(msg, ok) {
        const wrap = document.getElementById('skToastWrap') || (() => {
            const w = document.createElement('div'); w.id = 'skToastWrap';
            w.className = 'sk-toast-wrap';
            document.body.appendChild(w); return w;
        })();
        const t = document.createElement('div');
        t.className = 'sk-toast ' + (ok ? 'sk-toast-ok' : 'sk-toast-err');
        t.innerHTML = '<i class="fa-solid fa-' + (ok ? 'circle-check' : 'circle-xmark') + '"></i>' +
                      '<span>' + msg + '</span>';
        wrap.appendChild(t);
        setTimeout(() => {
            t.style.transition = 'opacity .3s, transform .3s';
            t.style.opacity = '0'; t.style.transform = 'translateY(8px)';
            setTimeout(() => t.remove(), 320);
        }, 2400);
    }
    window.skToast = showToast;

    // ─── KEYBOARD ──────────────────────────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (pwModal) pwModal.hide();
            if (imgModalInstance) imgModalInstance.hide();
        }
    });
})();
