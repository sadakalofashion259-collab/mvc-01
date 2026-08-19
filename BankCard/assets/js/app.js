// ── থিম (Bootstrap 5.3 data-bs-theme) ──
function applyTheme(t){
  document.documentElement.setAttribute('data-bs-theme', t);
  const i=document.getElementById('themeIco');
  if(i) i.className = (t==='dark') ? 'fas fa-moon' : 'fas fa-sun';
}
function toggleTheme(){
  const cur=document.documentElement.getAttribute('data-bs-theme');
  const next=(cur==='dark')?'light':'dark';
  localStorage.setItem('cc_theme', next);
  applyTheme(next);
}
(function(){ applyTheme(localStorage.getItem('cc_theme')==='light'?'light':'dark'); })();

// ── Bootstrap helpers ──
const _modal = id => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
function closeModal(id){ const el=document.getElementById(id); if(el){ const m=bootstrap.Modal.getInstance(el); if(m) m.hide(); } }

// ── More sheet (offcanvas) ──
function _sheet(){ const el=document.getElementById('moreSheet'); return el?bootstrap.Offcanvas.getOrCreateInstance(el):null; }
function toggleMoreMenu(){ const s=_sheet(); if(s) s.toggle(); }
function closeMoreMenu(){ const s=_sheet(); if(s) s.hide(); }

// ── ছবি জুম ──
function showBig(src){ document.getElementById('bigImg').src=src; _modal('imgModal').show(); }

// ── কার্ড মডাল ──
function openAddCardModal(){ _modal('addCardModal').show(); }
function openEditCardModal(){ _modal('editCardModal').show(); }

// ── ট্রানজেকশন মডাল (Add) ──
const TXN_TYPES = {
  'purchase':     { title:'🛍️ কার্ড দিয়ে কেনাকাটা', desc:'<b style="color:var(--c-red)">কার্ডের বকেয়া বাড়বে</b> · ক্যাশ বক্সে কোনো টাকা যোগ হবে না।', hideCharge:false },
  'cash_advance': { title:'💰 কার্ড থেকে ক্যাশ অ্যাড', desc:'<b style="color:var(--c-red)">কার্ডের বকেয়া বাড়বে</b> · ক্যাশ বক্সে মূল টাকা যোগ হবে।', hideCharge:false },
  'bill_pay':     { title:'💳 বিল পরিশোধ', desc:'<b style="color:var(--c-green)">কার্ডের বকেয়া কমবে</b> · ক্যাশ বক্স থেকে টাকা মাইনাস হবে।', hideCharge:false },
  'min_pay':      { title:'📋 মিনিমাম বিল পরিশোধ', desc:'<b style="color:var(--c-green)">কার্ডের বকেয়া কমবে</b> · ক্যাশ বক্স থেকে টাকা মাইনাস হবে।', hideCharge:false },
  'full_pay':     { title:'✅ ফুল আউটস্ট্যান্ডিং পরিশোধ', desc:'<b style="color:var(--c-green)">কার্ডের বকেয়া কমবে</b> · ক্যাশ বক্স থেকে টাকা মাইনাস হবে।', hideCharge:false },
  'charge_pay':   { title:'⚡ চার্জ পরিশোধ', desc:'কার্ডের বকেয়া পরিবর্তন হবে না · ক্যাশ বক্স থেকে টাকা মাইনাস হবে।', hideCharge:true }
};
function openTxnModal(type){
  closeMoreMenu();
  const cfg=TXN_TYPES[type]; if(!cfg) return;
  document.getElementById('txnType').value=type;
  document.getElementById('txnModalTitle').innerHTML=cfg.title;
  document.getElementById('txnDescBadge').innerHTML=cfg.desc;
  document.getElementById('chargeGrp').style.display=cfg.hideCharge?'none':'block';
  _modal('txnModal').show();
}
function openEditTxnModal(id, type, date, amount, charge, desc){
  document.getElementById('editTxnId').value=id;
  document.getElementById('editTxnType').value=type;
  document.getElementById('editTxnDate').value=date;
  document.getElementById('editTxnAmt').value=amount;
  document.getElementById('editTxnCharge').value=charge;
  document.getElementById('editTxnDesc').value=desc;
  _modal('editTxnModal').show();
}

// ── পাসওয়ার্ড মডাল ──
let pwState={action:'',data:{}};
function _openPw(title,sub,btnTxt){
  document.getElementById('pwTitle').textContent=title;
  document.getElementById('pwSub').innerHTML=sub;
  document.getElementById('pwOkBtn').innerHTML=btnTxt;
  document.getElementById('pwInp').value='';
  document.getElementById('pwErr').textContent='';
  _modal('pwModal').show();
}
function closePwModal(){ closeModal('pwModal'); }
const _pwEl=document.getElementById('pwModal');
if(_pwEl) _pwEl.addEventListener('shown.bs.modal', ()=>{ const i=document.getElementById('pwInp'); if(i) i.focus(); });

function openUnmaskModal(cid){ closeMoreMenu(); pwState={action:'unmask_card',data:{card_id:cid}}; _openPw('🔓 কার্ড নাম্বার দেখুন','Admin পাসওয়ার্ড দিন<br><small style="color:var(--c-red)">শুধু আপনিই দেখতে পারবেন</small>','দেখুন'); }
function openToggleStatus(cid){ closeMoreMenu(); pwState={action:'toggle_status',data:{card_id:cid}}; _openPw('⚡ স্ট্যাটাস পরিবর্তন','Admin পাসওয়ার্ড দিন<br><small style="color:var(--c-red)">কার্ড সক্রিয়/নিষ্ক্রিয় হবে</small>','পরিবর্তন করুন'); }
function openDeleteCard(cid){ closeMoreMenu(); pwState={action:'delete_card',data:{card_id:cid}}; _openPw('⚠️ কার্ড ডিলিট','Admin পাসওয়ার্ড দিন<br><small style="color:var(--c-red)">পুরো কার্ড ও সব ট্রানজেকশন মুছে যাবে</small>','ডিলিট করুন'); }
function openDeleteLedger(lid){ pwState={action:'delete_ledger',data:{ledger_id:lid}}; _openPw('🗑️ এন্ট্রি ডিলিট','Admin পাসওয়ার্ড দিন<br><small style="color:var(--c-red)">এই ট্রানজেকশন মুছে যাবে</small>','ডিলিট করুন'); }

function pwConfirm(){
  const pass=document.getElementById('pwInp').value.trim();
  const btn=document.getElementById('pwOkBtn');
  const err=document.getElementById('pwErr');
  if(!pass){ err.textContent='পাসওয়ার্ড লিখুন।'; return; }
  btn.textContent='যাচাই হচ্ছে…'; btn.disabled=true;
  const fd=new FormData();
  fd.append('pass',pass);
  fd.append('ajax_action', pwState.action);
  fd.append('csrf_token', window.CSRF_TOKEN || '');
  for(let k in pwState.data) fd.append(k, pwState.data[k]);

  fetch((window.MODULE_URL || '') + '/index.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(res=>{
      btn.textContent='নিশ্চিত করুন'; btn.disabled=false;
      if(res.status==='success'){
        if(pwState.action==='unmask_card'){
          closePwModal();
          document.getElementById('unmaskNum').textContent=(res.card_number||'').replace(/(.{4})/g,'$1 ').trim();
          document.getElementById('unmaskPin').textContent=res.card_pin||'(সেট করা নেই)';
          setTimeout(()=>_modal('unmaskModal').show(),350);
        } else if(pwState.action==='delete_card'){
          closePwModal(); showToast(res.message,true);
          setTimeout(()=>location.href=(window.MODULE_URL || '') + '/index.php',1200);
        } else {
          closePwModal(); showToast(res.message,true);
          setTimeout(()=>location.reload(),1000);
        }
      } else { err.textContent=res.message; }
    })
    .catch(()=>{ btn.textContent='নিশ্চিত করুন'; btn.disabled=false; err.textContent='সার্ভার সমস্যা।'; });
}

document.addEventListener('keydown',e=>{
  if(e.key==='Enter'){
    const pw=document.getElementById('pwModal');
    if(pw && pw.classList.contains('show')){ e.preventDefault(); pwConfirm(); }
  }
});

function showToast(msg,ok){
  const t=document.createElement('div');
  t.className='toast-pop'; t.textContent=msg;
  t.style.background=ok?'linear-gradient(135deg,#10b981,#047857)':'linear-gradient(135deg,#f43f5e,#be123c)';
  document.body.appendChild(t);
  setTimeout(()=>t.remove(),2500);
}
