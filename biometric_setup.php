<?php
/**
 * biometric_setup.php — বায়োমেট্রিক চালু/পরিচালনা পেজ।
 * পাসওয়ার্ড দিয়ে লগইন করা অবস্থায় এই পেজে এসে ফিঙ্গারপ্রিন্ট/Face চালু করুন।
 * (dashboard থেকে এই পেজে একটি লিংক/বাটন রাখুন।)
 */
declare(strict_types=1);
ob_start();
@ini_set('session.use_only_cookies', '1');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/db_connect.php';

// লগইন না থাকলে — ID+পাসওয়ার্ড দিয়ে ভেরিফাই করার জন্য লগইনে পাঠাও (সেটআপে ফিরে আসবে)
if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
    header('Location: index.php?next=bio_setup'); exit;
}

// সেটআপ শেষ → লগআউট করে লগইন পেজে ফিরিয়ে আনো (ফিঙ্গারপ্রিন্ট দিয়ে লগইন টেস্ট করার জন্য)
if (isset($_GET['finish'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: index.php?enrolled=1'); exit;
}
$username = htmlspecialchars((string)($_SESSION['username'] ?? 'User'), ENT_QUOTES, 'UTF-8');
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>বায়োমেট্রিক সেটআপ — Sada Kalo Fashion</title>
<link rel="icon" href="logo.png" type="image/png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
  *,*::before,*::after{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
  body{margin:0;font-family:'Segoe UI',sans-serif;background:#f1f5f9;color:#0f172a;
       min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:18px}
  .card{background:#fff;width:100%;max-width:420px;border-radius:20px;
        box-shadow:0 12px 32px -12px rgba(15,23,42,.25);overflow:hidden;margin-top:8px}
  .hd{background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;padding:22px 18px;text-align:center;position:relative;overflow:hidden}
  .hd::after{content:"";position:absolute;top:-40px;right:-30px;width:150px;height:150px;border-radius:50%;background:radial-gradient(circle,rgba(96,165,250,.3),transparent 70%)}
  .hd .fp{font-size:42px;color:#60a5fa;position:relative}
  .hd h1{margin:8px 0 2px;font-size:19px;font-weight:900;position:relative}
  .hd p{margin:0;font-size:12px;opacity:.8;position:relative}
  .bd{padding:18px}
  .enroll{width:100%;display:flex;align-items:center;justify-content:center;gap:9px;
          padding:15px;border:none;border-radius:14px;cursor:pointer;-webkit-appearance:none;
          background:linear-gradient(to bottom,#1d4ed8,#1e3a8a);color:#fff;
          font-size:15px;font-weight:900;letter-spacing:.3px;
          box-shadow:0 6px 0 #0b1120,0 8px 14px rgba(0,0,0,.18);transition:transform .1s,box-shadow .1s}
  .enroll:active{transform:translateY(6px);box-shadow:0 0 0 #0b1120}
  .enroll i{font-size:1.3em;color:#bfdbfe}
  .enroll[disabled]{opacity:.6;cursor:progress}
  .hint{font-size:11.5px;color:#64748b;text-align:center;margin:12px 4px 4px;line-height:1.7}
  .sec{margin-top:18px;font-size:12px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.05em}
  .dev{display:flex;align-items:center;gap:11px;background:#f8fafc;border:1px solid #e2e8f0;
       border-radius:13px;padding:11px 12px;margin-top:9px}
  .dev .ic{width:38px;height:38px;border-radius:11px;background:#e0e7ff;color:#1d4ed8;
           display:grid;place-items:center;font-size:16px;flex-shrink:0}
  .dev .meta{flex:1;min-width:0}
  .dev .nm{font-size:13.5px;font-weight:800}
  .dev .dt{font-size:10.5px;color:#94a3b8;margin-top:2px}
  .dev .del{background:none;border:1.5px solid #fca5a5;color:#dc2626;border-radius:9px;
            width:34px;height:34px;font-size:14px;cursor:pointer;flex-shrink:0}
  .empty{font-size:12.5px;color:#94a3b8;text-align:center;padding:14px}
  .back{margin-top:16px;color:#1e40af;font-size:13px;font-weight:800;text-decoration:none;
        background:#eff6ff;border:1px solid #bfdbfe;padding:9px 18px;border-radius:20px;box-shadow:0 3px 0 #bfdbfe}
  .toast{position:fixed;left:50%;bottom:22px;transform:translateX(-50%) translateY(20px);
         opacity:0;transition:.3s;background:#0f172a;color:#fff;padding:12px 18px;border-radius:12px;
         font-size:13px;font-weight:700;box-shadow:0 12px 30px -8px rgba(0,0,0,.5);z-index:99;max-width:90%}
  .toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
  .toast.ok{background:linear-gradient(135deg,#15803d,#166534)}
  .toast.err{background:linear-gradient(135deg,#dc2626,#991b1b)}
</style>
</head>
<body>
<div class="card">
  <div class="hd">
    <div class="fp"><i class="fas fa-fingerprint"></i></div>
    <h1>বায়োমেট্রিক লগইন</h1>
    <p><?php echo $username; ?> — ফিঙ্গারপ্রিন্ট / Face দিয়ে দ্রুত লগইন</p>
  </div>
  <div class="bd">
    <button type="button" id="enrollBtn" class="enroll">
      <i class="fas fa-plus-circle"></i> এই ডিভাইসে চালু করুন
    </button>
    <div class="hint">
      <i class="fas fa-shield-halved"></i> আপনার ফিঙ্গারপ্রিন্ট কখনো সার্ভারে যায় না —
      শুধু একটি নিরাপদ ক্রিপ্টো-কি সংরক্ষিত হয়। পাসওয়ার্ড লগইন আগের মতোই চালু থাকবে।
    </div>

    <div class="sec"><i class="fas fa-mobile-screen-button"></i> রেজিস্টার করা ডিভাইস</div>
    <div id="devList"><div class="empty">লোড হচ্ছে...</div></div>
  </div>
</div>
<a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> ড্যাশবোর্ডে ফিরুন</a>
<div class="toast" id="toast"></div>

<script>
function b64uToBuf(s){s=s.replace(/-/g,'+').replace(/_/g,'/');var p=s.length%4;if(p)s+='===='.slice(p);var b=atob(s),u=new Uint8Array(b.length);for(var i=0;i<b.length;i++)u[i]=b.charCodeAt(i);return u.buffer;}
function bufToB64u(buf){var by=new Uint8Array(buf),s='';for(var i=0;i<by.length;i++)s+=String.fromCharCode(by[i]);return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');}
function post(p){return fetch('index.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(p).toString()}).then(function(r){return r.json();});}
function toast(m,t){var el=document.getElementById('toast');el.textContent=m;el.className='toast show '+(t||'');setTimeout(function(){el.className='toast '+(t||'');},3000);}

function loadDevices(){
  fetch('index.php?webauthn=list').then(function(r){return r.json();}).then(function(d){
    var box=document.getElementById('devList');
    if(!d.ok||!d.devices||!d.devices.length){box.innerHTML='<div class="empty">এখনো কোনো ডিভাইস চালু করা হয়নি।</div>';return;}
    box.innerHTML=d.devices.map(function(c){
      var dt=c.last_used_at?('শেষ ব্যবহার: '+c.last_used_at):('চালু: '+c.created_at);
      return '<div class="dev"><div class="ic"><i class="fas fa-fingerprint"></i></div>'+
        '<div class="meta"><div class="nm">'+(c.device_label||'ডিভাইস')+'</div><div class="dt">'+dt+'</div></div>'+
        '<button class="del" onclick="delDev('+c.id+')"><i class="fas fa-trash-alt"></i></button></div>';
    }).join('');
  });
}
function delDev(id){
  if(!confirm('এই ডিভাইস মুছে ফেলবেন?'))return;
  post({webauthn:'delete',id:id}).then(function(d){toast(d.msg,d.ok?'ok':'err');loadDevices();});
}

document.getElementById('enrollBtn').addEventListener('click',function(){
  if(!window.PublicKeyCredential){alert('এই ডিভাইসে বায়োমেট্রিক সাপোর্ট নেই।');return;}
  var btn=this,orig=btn.innerHTML;btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> অপেক্ষা করুন...';
  var label=prompt('এই ডিভাইসের নাম দিন:','আমার ফোন')||'আমার ডিভাইস';
  post({webauthn:'reg_options'})
  .then(function(opt){
    if(!opt.ok)throw new Error(opt.msg||'অপশন এরর');
    var pk={
      challenge:b64uToBuf(opt.challenge),
      rp:opt.rp,
      user:{id:b64uToBuf(opt.user.id),name:opt.user.name,displayName:opt.user.displayName},
      pubKeyCredParams:opt.pubKeyCredParams,
      authenticatorSelection:opt.authenticatorSelection,
      timeout:opt.timeout,
      attestation:opt.attestation||'none',
      excludeCredentials:(opt.excludeCredentials||[]).map(function(c){return{type:'public-key',id:b64uToBuf(c.id)};})
    };
    return navigator.credentials.create({publicKey:pk});
  })
  .then(function(cred){
    var r=cred.response;
    return post({webauthn:'reg_verify',label:label,clientDataJSON:bufToB64u(r.clientDataJSON),attestationObject:bufToB64u(r.attestationObject)});
  })
  .then(function(res){
    btn.disabled=false;btn.innerHTML=orig;
    toast(res.msg,res.ok?'ok':'err');
    if(res.ok){
      // সেটআপ সফল → লগইন পেজে ফিরিয়ে নিয়ে যাও (ফিঙ্গারপ্রিন্ট দিয়ে লগইন করার জন্য)
      setTimeout(function(){ window.location.href='biometric_setup.php?finish=1'; }, 1400);
    }
  })
  .catch(function(err){
    btn.disabled=false;btn.innerHTML=orig;
    var m=(err&&err.name==='NotAllowedError')?'বাতিল করা হয়েছে বা সময় শেষ।':(err&&err.message?err.message:'রেজিস্ট্রেশন ব্যর্থ।');
    toast('❌ '+m,'err');
  });
});

loadDevices();
</script>
</body>
</html>
