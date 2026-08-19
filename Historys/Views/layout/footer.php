</div><!-- /wrap -->

<!-- Bottom Navigation Bar -->
<div class="bottom-nav no-print">
    <div class="bn-row">
        <a href="../../dashboard.php" class="bn-btn bn-home">
            <div class="bn-icon"><i class="fas fa-home"></i></div>
            <span class="bn-lbl">ড্যাশবোর্ড</span>
        </a>
       
        <button class="bn-btn bn-filt" onclick="toggleFilter()">
            <div class="bn-icon"><i class="fas fa-sliders-h"></i></div>
            <span class="bn-lbl">ফিল্টার</span>
        </button>
        <button class="bn-btn bn-prnt" onclick="window.print()">
            <div class="bn-icon"><i class="fas fa-print"></i></div>
            <span class="bn-lbl">প্রিন্ট</span>
        </button>
    </div>
</div>

<script>
// ── Theme ────────────────────────────────────────────────
function toggleTheme(){
    document.body.classList.toggle('light-mode');
    const d = !document.body.classList.contains('light-mode');
    localStorage.setItem('theme', d ? 'dark' : 'light');
    document.getElementById('themeIco').className = d ? 'fas fa-sun' : 'fas fa-moon';
}
(function(){
    if(localStorage.getItem('theme') === 'light'){
        document.body.classList.add('light-mode');
        const i = document.getElementById('themeIco');
        if(i) i.className = 'fas fa-moon';
    }
})();

// ── Image Zoom ───────────────────────────────────────────
function showBig(s){
    document.getElementById('bigImg').src = s;
    document.getElementById('imgModal').classList.add('show');
}

// ── Filter Toggle ────────────────────────────────────────
function toggleFilter(){
    const b = document.getElementById('filterBox');
    if(!b) return;
    b.style.display = b.style.display === 'block' ? 'none' : 'block';
}

// ── Date Quick Filter ────────────────────────────────────
function qDate(d){
    const t = new Date(), f = new Date();
    f.setDate(t.getDate() - (d - 1));
    location.href = 'index.php?from_date=' + fmt(f) + '&to_date=' + fmt(t);
}
function qMonth(){
    const t = new Date(), f = new Date(t.getFullYear(), t.getMonth(), 1);
    location.href = 'index.php?from_date=' + fmt(f) + '&to_date=' + fmt(t);
}
function qAll(){
    location.href = 'index.php?from_date=2020-01-01&to_date=' + fmt(new Date());
}
function fmt(d){ return d.toISOString().split('T')[0]; }

// ── Password Modal State ─────────────────────────────────
let pwState = {type:'', table:'', id:0, field:'', val:'', mode:''};

function openPw(type, table, id, field, val){
    pwState = {type, table, id, field: field||'', val: val||'', mode:'item'};
    document.getElementById('pwTitle').textContent  = type === 'delete' ? 'এন্ট্রি ডিলিট করবেন?' : 'এন্ট্রি এডিট করবেন?';
    document.getElementById('pwSub').innerHTML      = 'Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent  = type === 'delete' ? 'ডিলিট করুন' : 'আপডেট করুন';
    _openModal();
}
function openPwDps(id){
    pwState = {type:'', table:'', id, field:'', val:'', mode:'dps'};
    document.getElementById('pwTitle').textContent  = 'DPS এন্ট্রি ডিলিট করবেন?';
    document.getElementById('pwSub').innerHTML      = 'Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent  = 'ডিলিট করুন';
    _openModal();
}
function openPwLoan(id){
    pwState = {type:'', table:'', id, field:'', val:'', mode:'loan'};
    document.getElementById('pwTitle').textContent  = 'লোন এন্ট্রি ডিলিট করবেন?';
    document.getElementById('pwSub').innerHTML      = 'Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent  = 'ডিলিট করুন';
    _openModal();
}
function openPwCard(id){
    pwState = {type:'', table:'', id, field:'', val:'', mode:'card'};
    document.getElementById('pwTitle').textContent  = 'কার্ড এন্ট্রি ডিলিট করবেন?';
    document.getElementById('pwSub').innerHTML      = 'Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent  = 'ডিলিট করুন';
    _openModal();
}
function _openModal(){
    document.getElementById('pwInp').value       = '';
    document.getElementById('pwErr').textContent = '';
    document.getElementById('pwModal').classList.add('show');
    setTimeout(() => document.getElementById('pwInp').focus(), 200);
}
function closePwModal(){ document.getElementById('pwModal').classList.remove('show'); }

// ── Password Confirm & AJAX ──────────────────────────────
function pwConfirm(){
    const pass = document.getElementById('pwInp').value.trim();
    const btn  = document.getElementById('pwOkBtn');
    const err  = document.getElementById('pwErr');
    if(!pass){ err.textContent = 'পাসওয়ার্ড লিখুন।'; return; }

    if(pwState.mode === 'item' && pwState.type === 'edit'){
        const nv = prompt('নতুন মান লিখুন (বর্তমান: ' + pwState.val + '):', pwState.val);
        if(nv === null) return;
        pwState.newVal = nv;
    }

    btn.textContent = 'যাচাই হচ্ছে…'; btn.disabled = true;

    const fd = new FormData();
    if(pwState.mode === 'item'){
        fd.append('ajax_action','item_action');
        fd.append('type',  pwState.type);
        fd.append('table', pwState.table);
        fd.append('id',    pwState.id);
        fd.append('field', pwState.field);
        fd.append('val',   pwState.newVal || pwState.val);
    } else if(pwState.mode === 'dps'){
        fd.append('ajax_action','delete_dps');
        fd.append('id', pwState.id);
    } else if(pwState.mode === 'loan'){
        fd.append('ajax_action','delete_loan');
        fd.append('id', pwState.id);
    } else if(pwState.mode === 'card'){
        fd.append('ajax_action','delete_card_ledger');
        fd.append('id', pwState.id);
    }
    fd.append('pass', pass);

    fetch('index.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(res => {
            btn.textContent = 'নিশ্চিত করুন'; btn.disabled = false;
            if(res.status === 'success'){
                closePwModal();
                showToast(res.message, true);
                setTimeout(() => location.reload(), 1200);
            } else {
                err.textContent = res.message;
            }
        })
        .catch(() => {
            btn.textContent = 'নিশ্চিত করুন'; btn.disabled = false;
            err.textContent = 'সার্ভার সমস্যা হয়েছে।';
        });
}

// ── Keyboard Support ─────────────────────────────────────
document.addEventListener('keydown', e => {
    const modal = document.getElementById('pwModal');
    if(e.key === 'Enter'  && modal.classList.contains('show')) pwConfirm();
    if(e.key === 'Escape' && modal.classList.contains('show')) closePwModal();
    if(e.key === 'Escape') document.getElementById('imgModal').classList.remove('show');
});

// ── Toast ────────────────────────────────────────────────
function showToast(msg, ok){
    const t = document.createElement('div');
    t.className       = 'toast-el';
    t.textContent     = msg;
    t.style.background = ok ? '#0369a1' : '#dc2626';
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2500);
}
</script>

</body>
</html>
