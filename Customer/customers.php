<?php
declare(strict_types=1);

/**
 * customers.php — Customer list page (view).
 * Session, auth, CSRF, headers and the PDO connection ($conn)
 * all come from config/bootstrap.php.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/Controllers/CustomerController.php';

$controller = new CustomerController($conn);
$controller->handleCustomersListRequests($csrf_token);
$viewData = $controller->getCustomersListData();

$role = $viewData['role'];
$username = $viewData['username'];
$sms_enabled = (bool)($viewData['sms_enabled'] ?? true);
$activeCustomers = $viewData['activeCustomers'];
$inactiveCustomers = $viewData['inactiveCustomers'];

/* Sort active customers: most recently active first */
usort($activeCustomers, function($a, $b) {
    $da = $a['last_tr_date'] ?? '0000-00-00';
    $db = $b['last_tr_date'] ?? '0000-00-00';
    return strcmp($db, $da); // descending
});
$total_market_due = $viewData['total_market_due'];
$total_active = $viewData['total_active'];
$total_inactive = $viewData['total_inactive'];
$no_entry_10days = $viewData['no_entry_10days'];
$limit_crossed = $viewData['limit_crossed'];
$collection_alerts = $viewData['collection_alerts'];
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#0E0E10">
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
<title>Customers═ সাদা-কালো ফ্যাশন═</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
:root{
  --brand-1:#E5242A; --brand-2:#C8161C; --brand-3:#A50F14;
  --grad-brand: linear-gradient(135deg,#E5242A 0%,#C8161C 50%,#A50F14 100%);
  --grad-due: linear-gradient(135deg,#0E0E10 0%,#1A1A1D 60%,#2A2A2E 100%);
  --grad-active:linear-gradient(135deg,#1F8A4C,#166e3c);
  --grad-warn: linear-gradient(135deg,#D9930B,#b67807);
  --grad-danger:linear-gradient(135deg,#E5242A,#C8161C);
  --grad-info: linear-gradient(135deg,#2A2A2E,#1A1A1D);
  --ring: 0 0 0 4px rgba(229,36,42,.15);
  --card-bg:#ffffff;
  --soft-bg:#F1F1F3;
  --soft-bd:#E6E7EA;
  --txt-mut:#71717A;
}
[data-bs-theme="dark"]{
  --grad-due: linear-gradient(135deg,#1A1A1D 0%,#2A2A2E 60%,#2A2A2E 100%);
  --card-bg:#0E0E10;
  --soft-bg:#1A1A1D;
  --soft-bd:#2A2A2E;
  --txt-mut:#A1A1AA;
  --bs-body-bg:#000000;
  --bs-body-color:#E6E7EA;
}
*{ -webkit-tap-highlight-color: transparent; }
html,body{ overscroll-behavior-y:none; }
body{
  font-family:'Plus Jakarta Sans','Hind Siliguri',system-ui,sans-serif;
  background:var(--bs-body-bg,#F7F7F8);
  padding-bottom:90px;
  font-size:14px;
}
.bn{ font-family:'Hind Siliguri',sans-serif; }
.num{ font-family:'Plus Jakarta Sans',sans-serif; font-variant-numeric:tabular-nums; }

/* ===== Ticker ===== */
.ticker{
  background:linear-gradient(90deg,#0E0E10,#1A1A1D);
  color:#F6ABAD;
  font-size:11px;font-weight:600;letter-spacing:.3px;
  padding:6px 0;overflow:hidden;white-space:nowrap;
}
.ticker > span{display:inline-block;padding-left:100%;animation:tk 28s linear infinite;}
@keyframes tk{from{transform:translateX(0)}to{transform:translateX(-100%)}}

/* ===== Stat grid ===== */
.stat-wrap{ padding:14px 12px 6px; }
.stat-grid{
  display:grid;grid-template-columns:repeat(4,1fr);gap:8px;
}
.stat-card{
  position:relative;
  background:var(--card-bg);
  border:1px solid var(--soft-bd);
  border-radius:16px;
  padding:11px 8px 9px;
  overflow:hidden;
  transition:transform .15s;
}
.stat-card:active{ transform:translateY(2px); }
.stat-card::before{
  content:"";position:absolute;inset:0 0 auto 0;height:3px;
  background:var(--bar);
}
.stat-card .ico{
  width:30px;height:30px;border-radius:10px;
  background:var(--ico-bg);color:var(--ico-fg);
  display:grid;place-items:center;font-size:14px;
  margin-bottom:6px;
}
.stat-card .v{font-size:16px;font-weight:800;line-height:1.05;letter-spacing:-.3px}
.stat-card .l{font-size:9px;font-weight:600;color:var(--txt-mut);text-transform:uppercase;letter-spacing:.4px;margin-top:3px}

.s-act{ --bar:linear-gradient(90deg,#1F8A4C,#166e3c); --ico-bg:#E6F4EC; --ico-fg:#166e3c; }
.s-due{ --bar:linear-gradient(90deg,#D9930B,#b67807); --ico-bg:#FBF1DD; --ico-fg:#8a5a05; }
.s-noe{ --bar:linear-gradient(90deg,#E5242A,#C8161C); --ico-bg:#FEECEC; --ico-fg:#C8161C; }
.s-inac{--bar:linear-gradient(90deg,#52525B,#3F3F46); --ico-bg:#F1F1F3; --ico-fg:#3F3F46; }
[data-bs-theme="dark"] .s-act{--ico-bg:rgba(31,138,76,.15)}
[data-bs-theme="dark"] .s-due{--ico-bg:rgba(217,147,11,.15)}
[data-bs-theme="dark"] .s-noe{--ico-bg:rgba(229,36,42,.15)}
[data-bs-theme="dark"] .s-inac{--ico-bg:rgba(113,113,122,.15)}

/* ===== Alerts ===== */
.alerts{ padding:8px 12px 0; display:flex; flex-direction:column; gap:6px; }
.alert-row{
  display:flex;align-items:center;gap:10px;
  background:var(--card-bg);
  border:1px solid var(--soft-bd);
  border-left:4px solid var(--bar-c);
  border-radius:12px;
  padding:8px 12px;
  font-size:11px;font-weight:600;
  overflow:hidden;
}
.alert-row .ico{
  width:28px;height:28px;border-radius:9px;
  background:var(--bar-bg);color:var(--bar-c);
  display:grid;place-items:center;font-size:13px;flex-shrink:0;
}
.alert-row .scroll{flex:1;overflow:hidden;white-space:nowrap}
.alert-row .scroll > span{display:inline-block;padding-left:100%;animation:tk 22s linear infinite;color:var(--bs-body-color)}
.a-coll{--bar-c:#D9930B;--bar-bg:#FBF1DD}
.a-noe {--bar-c:#E5242A;--bar-bg:#FEECEC}
.a-lim {--bar-c:#ea580c;--bar-bg:#ffedd5}
[data-bs-theme="dark"] .a-coll{--bar-bg:rgba(217,147,11,.15)}
[data-bs-theme="dark"] .a-noe {--bar-bg:rgba(229,36,42,.15)}
[data-bs-theme="dark"] .a-lim {--bar-bg:rgba(234,88,12,.15)}

/* ===== Section header ===== */
.sec-hdr{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 14px 6px;
}
.sec-hdr .ttl{
  font-size:11px;font-weight:700;
  text-transform:uppercase;letter-spacing:.8px;
  color:var(--txt-mut);
  display:flex;align-items:center;gap:6px;
}
.sec-hdr .ttl::before{
  content:"";width:4px;height:14px;border-radius:3px;
  background:var(--grad-brand);
}
.sec-hdr .cnt{
  font-size:10px;font-weight:700;
  background:var(--soft-bg);color:var(--bs-body-color);
  padding:3px 10px;border-radius:999px;
}

/* ===== Search (always visible) ===== */
.srch-field { position:relative; }
.srch-field .bi-search{
  position:absolute;left:14px;top:50%;transform:translateY(-50%);
  color:var(--txt-mut);font-size:14px;pointer-events:none;
}
.srch-field input{
  width:100%;
  padding:13px 14px 13px 42px;
  border:1.5px solid var(--soft-bd);
  border-radius:14px;
  background:var(--card-bg);
  color:var(--bs-body-color);
  font-size:13px;font-weight:600;outline:none;
  transition:border-color .2s, box-shadow .2s;
  font-family:inherit;
  box-shadow:0 2px 8px -4px rgba(0,0,0,.08);
}
.srch-field input:focus{ border-color:var(--brand-1); box-shadow:var(--ring); }

/* ===== Customer cards ===== */
.cards{ padding:0 12px; display:flex; flex-direction:column; gap:8px; }
.cc{
  background:var(--card-bg);
  border:1px solid var(--soft-bd);
  border-radius:16px;
  overflow:hidden;
  transition:transform .15s, box-shadow .2s;
}
.cc:active{ transform:scale(.99); }
.cc.inactive{ opacity:.78; }
.cc .row1{
  display:flex;align-items:center;gap:11px;
  padding:11px 12px;cursor:pointer;
}
.av{
  width:48px;height:48px;border-radius:14px;flex-shrink:0;
  display:grid;place-items:center;
  font-size:18px;font-weight:800;
  color:#fff;overflow:hidden;
  box-shadow:0 4px 10px -4px rgba(0,0,0,.25);
}
.av img{width:100%;height:100%;object-fit:cover}
.cc-info{ flex:1; min-width:0; }
.cc-shop{
  font-size:13.5px;font-weight:800;line-height:1.2;
  color:var(--bs-body-color);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  text-transform:uppercase;letter-spacing:.2px;
}
.cc-name{ font-size:11px; color:var(--txt-mut); margin-top:2px; font-weight:500;}
.cc-last{ font-size:9.5px; color:var(--txt-mut); margin-top:3px; display:flex; align-items:center; gap:4px; opacity:.85; }
.cc-due{
  font-size:12px;font-weight:800;
  padding:5px 10px;border-radius:10px;
  white-space:nowrap;flex-shrink:0;
  letter-spacing:.2px;
}
.cc-due.red  { color:#C8161C; background:#FEECEC; border:1px solid #F6ABAD;}
.cc-due.green{ color:#166e3c; background:#E6F4EC; border:1px solid #9bd3b1;}
.cc-due.zero { color:#71717A; background:var(--soft-bg); border:1px solid var(--soft-bd);}
[data-bs-theme="dark"] .cc-due.red{ background:rgba(229,36,42,.15); border-color:rgba(229,36,42,.4);}
[data-bs-theme="dark"] .cc-due.green{ background:rgba(31,138,76,.15); border-color:rgba(31,138,76,.4);}

.tag-inac{
  display:inline-block;
  font-size:8.5px;font-weight:700;
  background:#E5242A;color:#fff;
  padding:2px 7px;border-radius:5px;margin-left:5px;
  letter-spacing:.4px;
}

.cc-acts{
  display:none;
  padding:9px 10px 10px;
  border-top:1px dashed var(--soft-bd);
  gap:7px;
  animation:slideD .25s ease;
}
.cc-acts.open{display:flex}
.act{
  flex:1;height:38px;
  border-radius:11px;border:none;color:#fff;
  font-size:14px;cursor:pointer;
  display:grid;place-items:center;
  text-decoration:none;
  transition:transform .1s, filter .2s;
}
.act:active{ transform:translateY(2px); filter:brightness(.92); }
.a-call  { background:linear-gradient(135deg,#1F8A4C,#166e3c); box-shadow:0 4px 0 #0f5a2e;}
.a-sms   { background:linear-gradient(135deg,#D9930B,#b67807); box-shadow:0 4px 0 #8a5a05;}
.a-view  { background:linear-gradient(135deg,#2A2A2E,#1A1A1D); box-shadow:0 4px 0 #0E0E10;}
.a-edit  { background:linear-gradient(135deg,#52525B,#3F3F46); box-shadow:0 4px 0 #2A2A2E;}
.a-ban   { background:linear-gradient(135deg,#E5242A,#C8161C); box-shadow:0 4px 0 #A50F14;}
.a-del   { background:linear-gradient(135deg,#E5242A,#C8161C); box-shadow:0 4px 0 #A50F14;}
.a-unban { background:linear-gradient(135deg,#1F8A4C,#0f5a2e); box-shadow:0 4px 0 #0a3d22;}

/* ===== Inactive toggle ===== */
.inactive-toggle{
  margin:14px 12px 6px;
  background:var(--card-bg);
  border:1.5px dashed var(--soft-bd);
  border-radius:14px;
  padding:11px 14px;
  display:flex;align-items:center;justify-content:space-between;
  cursor:pointer; user-select:none;
  transition:background .2s, border-color .2s;
}
.inactive-toggle:hover{ border-color:#52525B; background:var(--soft-bg); }
.inactive-toggle .lft{display:flex;align-items:center;gap:9px;font-size:12px;font-weight:700;color:var(--bs-body-color)}
.inactive-toggle .lft .ico{
  width:30px;height:30px;border-radius:9px;
  background:#F1F1F3;color:#3F3F46;
  display:grid;place-items:center;font-size:14px;
}
[data-bs-theme="dark"] .inactive-toggle .lft .ico{ background:rgba(113,113,122,.2); }
.inactive-toggle .badge-num{
  background:#E5242A;color:#fff;
  font-size:9.5px;font-weight:800;
  padding:2px 8px;border-radius:999px;
}
.inactive-toggle .chev{ transition:transform .25s; color:var(--txt-mut); font-size:14px;}
.inactive-toggle.open .chev{ transform:rotate(180deg); }

/* ===== Empty state ===== */
.empty{
  text-align:center;
  padding:40px 20px;
  color:var(--txt-mut);
}
.empty .bi{font-size:36px;opacity:.4;display:block;margin-bottom:8px}
.empty .t{font-size:13px;font-weight:700}

/* ===== Bottom sheet modal (Bootstrap offcanvas style) ===== */
.bsheet{
  position:fixed;inset:0;z-index:2000;
  display:none;align-items:flex-end;justify-content:center;
  background:rgba(2,6,23,.55);
  backdrop-filter:blur(4px);
  animation:fadeBg .25s ease;
}
.bsheet.show{display:flex}
@keyframes fadeBg{from{opacity:0}to{opacity:1}}
.bsheet-box{
  background:var(--card-bg);
  width:100%;max-width:520px;
  border-radius:24px 24px 0 0;
  padding:18px 16px max(16px, env(safe-area-inset-bottom));
  max-height:92vh; overflow-y:auto;
  animation:rise .3s cubic-bezier(.2,.9,.3,1.2);
  box-shadow:0 -20px 40px -10px rgba(0,0,0,.35);
}
@keyframes rise{from{transform:translateY(100%)}to{transform:translateY(0)}}
.bsheet-handle{
  width:42px;height:5px;border-radius:99px;
  background:var(--soft-bd);
  margin:-4px auto 12px;
}
.bsheet-hdr{
  display:flex;align-items:center;justify-content:space-between;
  padding-bottom:14px;margin-bottom:14px;
  border-bottom:1px solid var(--soft-bd);
}
.bsheet-title{
  display:flex;align-items:center;gap:9px;
  font-size:15px;font-weight:800;
  color:var(--bs-body-color);
}
.bsheet-title .ico{
  width:34px;height:34px;border-radius:11px;
  background:var(--grad-brand);color:#fff;
  display:grid;place-items:center;font-size:15px;
}
.bsheet-close{
  width:32px;height:32px;border-radius:10px;
  border:none;background:var(--soft-bg);
  color:var(--bs-body-color);font-size:16px;cursor:pointer;
}

.fld{
  position:relative;
  margin-bottom:9px;
}
.fld .bi{
  position:absolute;left:12px;top:14px;
  color:var(--txt-mut);font-size:14px;
  pointer-events:none;
}
.fld .inp, .fld textarea{
  width:100%;
  padding:12px 14px 12px 38px;
  border:1.5px solid var(--soft-bd);
  border-radius:12px;
  background:var(--card-bg);
  color:var(--bs-body-color);
  font-size:13px;font-weight:600;outline:none;
  font-family:inherit;
  transition:border-color .2s, box-shadow .2s;
}
.fld textarea{ resize:vertical; min-height:64px; }
.fld .inp:focus, .fld textarea:focus{ border-color:var(--brand-1); box-shadow:var(--ring); }

.fld-row{ display:grid; grid-template-columns:1fr 1fr; gap:8px; }

.pic-card{
  border:1.5px dashed var(--soft-bd);
  border-radius:14px;
  padding:12px;
  background:var(--soft-bg);
  margin-top:6px;
}
.pic-card .ttl{
  font-size:10px;font-weight:700;text-transform:uppercase;
  color:var(--txt-mut);letter-spacing:.5px;
  display:flex;align-items:center;gap:5px;
  margin-bottom:9px;
}
.pic-btns{ display:flex; gap:8px; margin-bottom:8px;}
.pb{
  flex:1;padding:10px;border:none;
  border-radius:10px;color:#fff;font-weight:700;font-size:12px;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;
  transition:filter .2s;
}
.pb:active{ filter:brightness(.92); }
.pb-cam{ background:linear-gradient(135deg,#2A2A2E,#1A1A1D); box-shadow:0 4px 0 #0E0E10;}
.pb-gal{ background:linear-gradient(135deg,#52525B,#3F3F46); box-shadow:0 4px 0 #2A2A2E;}

#camArea{ display:none; border:2px dashed var(--brand-1); border-radius:12px; padding:8px; text-align:center;}
#camArea video{ width:100%; border-radius:9px; background:#000;}
.btn-cap{
  width:100%;padding:10px;
  background:linear-gradient(135deg,#E5242A,#C8161C);
  color:#fff;border:none;border-radius:10px;
  font-weight:800;font-size:13px;cursor:pointer;
  margin-top:8px;box-shadow:0 4px 0 #A50F14;
}
#picPrev{
  width:84px;height:84px;border-radius:50%;object-fit:cover;
  display:none;margin:10px auto 0;
  border:3px solid var(--brand-1);
}

.btn-save{
  width:100%;padding:13px;
  background:var(--grad-brand);
  border:none;border-radius:13px;
  color:#fff;font-weight:800;font-size:14px;
  cursor:pointer;letter-spacing:.4px;
  margin-top:14px;
  box-shadow:0 8px 20px -8px rgba(229,36,42,.7);
  transition:transform .1s, box-shadow .2s;
}
.btn-save:active{ transform:translateY(2px); box-shadow:0 4px 12px -8px rgba(229,36,42,.6); }

/* ===== Auth Modal ===== */
.auth-modal{
  position:fixed;inset:0;z-index:2100;
  display:none;align-items:center;justify-content:center;
  background:rgba(2,6,23,.6);
  backdrop-filter:blur(5px);
  padding:18px;
}
.auth-modal.show{display:flex}
.auth-box{
  background:var(--card-bg);
  border-radius:22px;
  width:100%; max-width:340px;
  padding:26px 22px 20px;
  text-align:center;
  animation:popIn .3s cubic-bezier(.2,.9,.3,1.4);
}
@keyframes popIn{from{transform:scale(.85);opacity:0}to{transform:scale(1);opacity:1}}
.auth-icon{
  width:60px;height:60px;border-radius:18px;
  background:linear-gradient(135deg,#FEECEC,#FBD5D6);
  display:grid;place-items:center;margin:0 auto 12px;
  color:#C8161C;font-size:26px;
  box-shadow:0 8px 18px -8px rgba(229,36,42,.6);
}
[data-bs-theme="dark"] .auth-icon{ background:linear-gradient(135deg,rgba(229,36,42,.25),rgba(229,36,42,.15));}
.auth-title{font-size:16px;font-weight:800}
.auth-sub{font-size:11px;color:var(--txt-mut);margin-top:3px;margin-bottom:14px}

.pass-fld{ position:relative; margin-bottom:6px;}
.pass-fld .bi{ position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--txt-mut);font-size:14px;}
.pass-fld input{
  width:100%;padding:12px 14px 12px 38px;
  border:1.5px solid var(--soft-bd);border-radius:11px;
  background:var(--card-bg);color:var(--bs-body-color);
  font-size:14px;font-weight:600;outline:none;
  font-family:inherit;
}
.pass-fld input:focus{ border-color:#E5242A; box-shadow:0 0 0 4px rgba(229,36,42,.15);}

#passMsg{ font-size:11px;font-weight:700;min-height:18px;margin:6px 0; }
.pm-ok{color:#1F8A4C!important}
.pm-err{color:#E5242A!important}

.auth-row{display:flex;gap:8px;margin-top:6px}
.auth-btn{
  flex:1;height:44px;border:none;border-radius:12px;
  font-weight:800;font-size:13px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:6px;
  transition:transform .1s, filter .2s;
}
.auth-btn:active{ transform:translateY(2px); }
.auth-btn-del{ background:linear-gradient(135deg,#E5242A,#C8161C); color:#fff; box-shadow:0 4px 0 #A50F14;}
.auth-btn-can{ background:var(--soft-bg); color:var(--bs-body-color);}

@keyframes spin{to{transform:rotate(360deg)}}
.spin{animation:spin 1s linear infinite;display:inline-block}

/* ===== Toast ===== */
#toastWrap{position:fixed;left:0;right:0;bottom:18px;z-index:99999;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none;padding:0 14px}
.toast-sk{display:flex;align-items:center;gap:10px;min-width:240px;max-width:380px;padding:13px 16px;border-radius:14px;color:#fff;font-family:'Hind Siliguri',sans-serif;font-size:13.5px;font-weight:700;box-shadow:0 12px 30px -8px rgba(14,14,16,.45);transform:translateY(20px);opacity:0;transition:transform .3s cubic-bezier(.2,.8,.3,1),opacity .3s;pointer-events:auto}
.toast-sk.show{transform:translateY(0);opacity:1}
.toast-sk .ti{width:26px;height:26px;border-radius:8px;display:grid;place-items:center;font-size:14px;background:rgba(255,255,255,.22);flex-shrink:0}
.toast-sk.ok{background:linear-gradient(135deg,#1F8A4C,#166e3c)}
.toast-sk.err{background:linear-gradient(135deg,#E5242A,#C8161C)}
.toast-sk.info{background:linear-gradient(135deg,#2A2A2E,#0E0E10)}
.toast-sk.warn{background:linear-gradient(135deg,#D9930B,#b67807)}

/* Float Action Button (theme toggle on mobile) */
@media (max-width:380px){
  .due-pill .val{font-size:14px}
  .stat-grid{grid-template-columns:repeat(2,1fr)}
}

/* ===== Group SMS — selection mode ===== */
.grp-btn{
  display:flex;align-items:center;gap:5px;
  background:linear-gradient(135deg,#D9930B,#b67807);
  color:#fff;border:none;border-radius:10px;
  padding:6px 11px;font-size:11px;font-weight:800;cursor:pointer;
  box-shadow:0 3px 10px -4px rgba(217,147,11,.7);
  transition:transform .1s, filter .2s;
}
.grp-btn:active{ transform:translateY(1px); }
.grp-btn.active{
  background:linear-gradient(135deg,#E5242A,#C8161C);
  box-shadow:0 3px 10px -4px rgba(229,36,42,.7);
}
.selbox{
  display:none;width:26px;height:26px;border-radius:8px;
  border:2px solid var(--soft-bd);background:var(--card-bg);
  flex-shrink:0;color:transparent;font-size:14px;
  align-items:center;justify-content:center;
  transition:background .15s, border-color .15s;
}
body.selmode .selbox{ display:grid;place-items:center; }
body.selmode .cc-due{ display:none; }
.cc.sel{ outline:2px solid #E5242A;outline-offset:-2px;background:#FEF5F5; }
[data-bs-theme="dark"] .cc.sel{ background:rgba(229,36,42,.08); }
.cc.sel .selbox{ background:#E5242A;border-color:#E5242A;color:#fff; }

.sel-bar{
  position:fixed;left:0;right:0;bottom:0;z-index:1500;
  display:none;align-items:center;gap:10px;
  padding:12px 14px max(12px,env(safe-area-inset-bottom));
  background:var(--card-bg);
  border-top:1px solid var(--soft-bd);
  box-shadow:0 -8px 24px -10px rgba(14,14,16,.25);
}
.sel-bar.show{ display:flex; }
.sel-all{
  background:var(--soft-bg);color:var(--bs-body-color);
  border:1px solid var(--soft-bd);border-radius:10px;
  padding:9px 11px;font-size:11.5px;font-weight:800;cursor:pointer;
  display:flex;align-items:center;gap:5px;flex-shrink:0;
}
.sel-info{ flex:1;font-size:12px;font-weight:700;color:var(--txt-mut);text-align:center;white-space:nowrap; }
.sel-info .num{ font-size:17px;font-weight:900;color:#E5242A; }
.sel-send{
  background:linear-gradient(135deg,#1F8A4C,#166e3c);
  color:#fff;border:none;border-radius:11px;
  padding:11px 15px;font-size:13px;font-weight:800;cursor:pointer;
  display:flex;align-items:center;gap:6px;flex-shrink:0;
  box-shadow:0 6px 16px -6px rgba(31,138,76,.6);
  transition:transform .1s;
}
.sel-send:active{ transform:translateY(2px); }
.sel-send:disabled{ opacity:.45;filter:grayscale(.5);cursor:not-allowed;box-shadow:none; }
.sel-cancel{
  background:var(--soft-bg);color:var(--bs-body-color);border:none;
  width:40px;height:40px;border-radius:11px;font-size:15px;cursor:pointer;
  flex-shrink:0;display:grid;place-items:center;
}
</style>
</head>
<body>
<div id="toastWrap"></div>

<!-- Page title band (Header Banner) -->
<div style="background:linear-gradient(135deg,#0E0E10 0%,#1A1A1D 40%,#2A2A2E 100%);padding:14px 16px 14px;display:flex;align-items:center;gap:12px;position:relative;overflow:hidden">
  <div style="position:absolute;top:-40px;right:-40px;width:160px;height:160px;border-radius:50%;background:radial-gradient(circle,rgba(229,36,42,.25),transparent 70%);pointer-events:none"></div>
  <div style="position:absolute;bottom:-30px;left:80px;width:100px;height:100px;border-radius:50%;background:radial-gradient(circle,rgba(236,72,153,.12),transparent 70%);pointer-events:none"></div>

  <!-- Logo -->
  <a href="/dashboard.php" style="text-decoration:none;flex-shrink:0">
    <img src="assets/logo.png" alt="Sada Kalo Fashion"
         style="width:58px;height:58px;border-radius:50%;object-fit:cover;border:2.5px solid rgba(255,255,255,.35);box-shadow:0 6px 18px -6px rgba(0,0,0,.6);background:#fff">
  </a>

  <!-- Title -->
  <div style="flex:1;min-width:0">
    <div style="font-size:20px;font-weight:900;color:#fff;letter-spacing:.3px;line-height:1.15" class="bn">═════ সাদা-কালো ফ্যাশন ═════</div>
    <div style="font-size:10.5px;color:rgba(255,255,255,.7);font-weight:600;margin-top:2px;letter-spacing:.2px" class="bn">কাস্টমার লেজার · Customer List</div>
    <div style="margin-top:5px;display:flex;align-items:center;gap:5px">
      <span style="font-size:8.5px;font-weight:800;background:rgba(229,36,42,.2);color:#F6ABAD;padding:2px 8px;border-radius:999px;border:1px solid rgba(229,36,42,.3);text-transform:uppercase;letter-spacing:.5px">মোট বাকি</span>
      <span style="font-size:17px;font-weight:900;color:#F6ABAD;letter-spacing:.2px" class="num">৳<?php echo number_format((float)$total_market_due); ?></span>
    </div>
  </div>

  <!-- Dark mode + Add button -->
  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0">
    <div style="display:flex;gap:8px">
    <?php if ($role === 'admin'): ?>
    
    <!-- Global SMS on/off (admin only) -->
    <button onclick="toggleSmsGlobal(this)" id="smsToggleBtn" data-state="<?php echo $sms_enabled ? 1 : 0; ?>"
            title="SMS <?php echo $sms_enabled ? 'চালু' : 'বন্ধ'; ?>"
            style="width:38px;height:38px;border-radius:12px;border:1.5px solid rgba(255,255,255,.25);background:<?php echo $sms_enabled ? 'linear-gradient(135deg,#1F8A4C,#166e3c)' : 'rgba(229,36,42,.35)'; ?>;color:#fff;font-size:15px;cursor:pointer;display:grid;place-items:center;backdrop-filter:blur(6px);transition:background .2s">
      <i class="bi <?php echo $sms_enabled ? 'bi-chat-dots-fill' : 'bi-chat-left-dots'; ?>" id="smsToggleIcon"></i>
    </button>
    <?php endif; ?>
    <!-- Dark mode toggle -->
    <button onclick="toggleTheme()" id="themeBtn"
            style="width:38px;height:38px;border-radius:12px;border:1.5px solid rgba(255,255,255,.25);background:rgba(255,255,255,.12);color:#fff;font-size:16px;cursor:pointer;display:grid;place-items:center;backdrop-filter:blur(6px);transition:background .2s">
      <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
    </button>
    </div>
    <?php if ($role !== 'viewer'): ?>
    
    <!-- Add customer -->
    <button onclick="openSheet('add')"
            style="display:flex;align-items:center;gap:6px;padding:7px 13px;background:linear-gradient(135deg,#1F8A4C,#166e3c);border:none;border-radius:11px;color:#fff;font-weight:800;font-size:12px;cursor:pointer;box-shadow:0 4px 12px -4px rgba(31,138,76,.7);white-space:nowrap">
      <i class="bi bi-person-plus-fill"></i><span class="bn">যোগ করুন</span>
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Ticker -->
<div class="ticker bn"><span>🌙 بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ &nbsp;|&nbsp; পরম করুণাময় আল্লাহর নামে শুরু করছি &nbsp;🌙 &nbsp;&nbsp;&nbsp;⚫ সাদা কালো ফ্যাশন · Customer Ledger System</span></div>

<!-- Search Bar (always visible) -->
<div style="padding:10px 12px 4px;background:var(--bs-body-bg)">
  <div class="srch-field">
    <i class="bi bi-search"></i>
    <input type="text" id="srchInp" placeholder="দোকানের নাম, মালিক বা ফোন নম্বর..." class="bn" oninput="doSearch(this.value)" autocomplete="off" spellcheck="false">
  </div>
</div>

<!-- Stats -->
<div class="stat-wrap">
  <div class="stat-grid">
    <div class="stat-card s-act">
      <div class="ico"><i class="bi bi-people-fill"></i></div>
      <div class="v num"><?php echo $total_active; ?></div>
      <div class="l bn">সক্রিয়</div>
    </div>
    <div class="stat-card s-due">
      <div class="ico"><i class="bi bi-currency-exchange"></i></div>
      <div class="v num">৳<?php echo number_format((float)$total_market_due); ?></div>
      <div class="l bn">মোট বাকি</div>
    </div>
    <div class="stat-card s-noe">
      <div class="ico"><i class="bi bi-hourglass-split"></i></div>
      <div class="v num"><?php echo count($no_entry_10days); ?></div>
      <div class="l bn">এন্ট্রি নেই</div>
    </div>
    <div class="stat-card s-inac">
      <div class="ico"><i class="bi bi-person-dash-fill"></i></div>
      <div class="v num"><?php echo $total_inactive; ?></div>
      <div class="l bn">ইনঅ্যাক্টিভ</div>
    </div>
  </div>
</div>

<?php if (!empty($collection_alerts) || !empty($no_entry_10days) || !empty($limit_crossed)): ?>
<div class="alerts">
  <?php if (!empty($collection_alerts)): ?>
  <div class="alert-row a-coll">
    <div class="ico"><i class="bi bi-bell-fill"></i></div>
    <div class="scroll bn"><span>📅 আজ কালেকশন: <?php echo implode(' · ', $collection_alerts); ?></span></div>
  </div>
  <?php endif; ?>
  <?php if (!empty($no_entry_10days)): ?>
  <div class="alert-row a-noe">
    <div class="ico"><i class="bi bi-clock-history"></i></div>
    <div class="scroll bn"><span>⚠ ১০ দিন এন্ট্রি নেই: <?php echo implode(' · ', array_slice($no_entry_10days,0,12)); ?></span></div>
  </div>
  <?php endif; ?>
  <?php if (!empty($limit_crossed)): ?>
  <div class="alert-row a-lim">
    <div class="ico"><i class="bi bi-exclamation-triangle-fill"></i></div>
    <div class="scroll bn"><span>🚨 ক্রেডিট লিমিট ওভার: <?php echo implode(' · ', array_slice($limit_crossed,0,10)); ?></span></div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Active customers -->
<div class="sec-hdr">
  <div class="ttl bn"><i class="bi bi-people-fill" style="color:#1F8A4C"></i> সক্রিয় কাস্টমার</div>
  <div style="display:flex;align-items:center;gap:8px">
    <button id="selModeBtn" class="grp-btn bn" onclick="toggleSelMode()"><i class="bi bi-check2-square"></i> গ্রুপ SMS</button>
    <span class="cnt num" id="activeCnt"><?php echo $total_active; ?> জন</span>
  </div>
</div>

<div class="cards" id="activeWrap">
<?php if (empty($activeCustomers)): ?>
<div class="empty bn"><i class="bi bi-person-x"></i><div class="t">কোনো সক্রিয় কাস্টমার নেই</div></div>
<?php endif; ?>
<?php foreach ($activeCustomers as $c):
    $due     = $c['opening_balance'] + ($c['total_bill'] - $c['total_rec']);
    $hue     = ($c['id'] * 55) % 360;
    $letter  = mb_substr($c['shop_name'], 0, 1, 'UTF-8');
    $dueClass= $due > 0 ? 'red' : ($due < 0 ? 'green' : 'zero');
    $lastAct = !empty($c['last_tr_date']) ? date('d M Y', strtotime($c['last_tr_date'])) : 'কোনো এন্ট্রি নেই';
    $phone   = htmlspecialchars($c['phone'], ENT_QUOTES, 'UTF-8');
    $dataStr = strtolower($c['shop_name'] . ' ' . $c['customer_name'] . ' ' . $c['phone']);
?>
<div class="cc" data-cid="<?php echo $c['id']; ?>" data-search="<?php echo htmlspecialchars($dataStr, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="row1" onclick="toggleActs(<?php echo $c['id']; ?>)">
    <div class="selbox"><i class="bi bi-check-lg"></i></div>
    <?php if (!empty($c['profile_pic']) && file_exists($c['profile_pic'])): ?>
      <div class="av"><img src="<?php echo htmlspecialchars($c['profile_pic']); ?>"></div>
    <?php else: ?>
      <div class="av" style="background:linear-gradient(135deg,<?php echo ['#1A1A1D','#E5242A','#1F8A4C','#2A2A2E'][$c['id']%4];?>,<?php echo ['#2A2A2E','#A50F14','#0f5a2e','#0E0E10'][$c['id']%4];?>)"><?php echo htmlspecialchars($letter); ?></div>
    <?php endif; ?>
    <div class="cc-info">
      <div class="cc-shop bn"><?php echo htmlspecialchars($c['shop_name'], ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="cc-name bn"><?php echo htmlspecialchars($c['customer_name'], ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="cc-last bn"><i class="bi bi-clock"></i> <?php echo $lastAct; ?></div>
    </div>
    <div class="cc-due <?php echo $dueClass; ?> num">৳<?php echo number_format((float)$due); ?></div>
  </div>
  <div class="cc-acts" id="acts-<?php echo $c['id']; ?>">
    <a href="tel:<?php echo $phone; ?>" class="act a-call"><i class="bi bi-telephone-fill"></i></a>
    <button onclick="sendSMS(<?php echo $c['id']; ?>, this)" class="act a-sms"><i class="bi bi-chat-dots-fill"></i></button>
    <a href="customer_profile.php?id=<?php echo $c['id']; ?>" class="act a-view"><i class="bi bi-box-arrow-up-right"></i></a>
    <?php if ($role === 'admin'): ?>
    <button onclick="authAction('edit',<?php echo htmlspecialchars(json_encode($c, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)" class="act a-edit"><i class="bi bi-pencil-fill"></i></button>
    <button onclick="authAction('ban',<?php echo $c['id']; ?>)" class="act a-ban"><i class="bi bi-slash-circle-fill"></i></button>
    <button onclick="authAction('del',<?php echo $c['id']; ?>)" class="act a-del"><i class="bi bi-trash3-fill"></i></button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php if ($role === 'admin' && $total_inactive > 0): ?>
<div class="inactive-toggle" id="inactiveToggleBtn" onclick="toggleInactive(this)">
  <div class="lft bn">
    <span class="ico"><i class="bi bi-person-dash-fill"></i></span>
    <span>ইনঅ্যাক্টিভ কাস্টমার</span>
    <span class="badge-num num"><?php echo $total_inactive; ?></span>
  </div>
  <i class="bi bi-chevron-down chev"></i>
</div>

<div class="cards" id="inactiveWrap" style="display:none;margin-top:4px">
<?php foreach ($inactiveCustomers as $c):
    $due    = $c['opening_balance'] + ($c['total_bill'] - $c['total_rec']);
    $letter = mb_substr($c['shop_name'], 0, 1, 'UTF-8');
?>
<div class="cc inactive" data-search="<?php echo htmlspecialchars(strtolower($c['shop_name'].' '.$c['customer_name']), ENT_QUOTES, 'UTF-8'); ?>">
  <div class="row1" onclick="toggleActs('i<?php echo $c['id']; ?>')">
    <?php if (!empty($c['profile_pic']) && file_exists($c['profile_pic'])): ?>
      <div class="av" style="filter:grayscale(1)"><img src="<?php echo htmlspecialchars($c['profile_pic']); ?>"></div>
    <?php else: ?>
      <div class="av" style="background:linear-gradient(135deg,#A1A1AA,#71717A)"><i class="bi bi-person-x"></i></div>
    <?php endif; ?>
    <div class="cc-info">
      <div class="cc-shop bn"><?php echo htmlspecialchars($c['shop_name'], ENT_QUOTES, 'UTF-8'); ?><span class="tag-inac">INACTIVE</span></div>
      <div class="cc-name bn"><?php echo htmlspecialchars($c['customer_name'], ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="cc-due zero num">৳<?php echo number_format((float)$due); ?></div>
  </div>
  <div class="cc-acts" id="acts-i<?php echo $c['id']; ?>">
    <button onclick="toggleActive(<?php echo $c['id']; ?>)" class="act a-unban" style="flex:2"><i class="bi bi-check-circle-fill"></i> <span class="bn" style="font-size:12px;margin-left:6px">Active করুন</span></button>
    <button onclick="authAction('edit',<?php echo htmlspecialchars(json_encode($c, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)" class="act a-edit"><i class="bi bi-pencil-fill"></i></button>
    <button onclick="authAction('del',<?php echo $c['id']; ?>)" class="act a-del"><i class="bi bi-trash3-fill"></i></button>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Group SMS selection bar -->
<div class="sel-bar" id="selBar">
  <button class="sel-all bn" onclick="selectAllVisible()"><i class="bi bi-check-all"></i> সবাই</button>
  <div class="sel-info bn"><span class="num" id="selCount">0</span> জন নির্বাচিত</div>
  <button class="sel-send bn" id="selSendBtn" onclick="sendBulkSms()" disabled><i class="bi bi-send-fill"></i> SMS পাঠান</button>
  <button class="sel-cancel" onclick="toggleSelMode()" title="বাতিল"><i class="bi bi-x-lg"></i></button>
</div>

<!-- Customer Add/Edit Sheet -->
<div class="bsheet" id="custModal">
  <div class="bsheet-box">
    <div class="bsheet-handle"></div>
    <div class="bsheet-hdr">
      <div class="bsheet-title bn">
        <span class="ico"><i class="bi bi-person-plus-fill"></i></span>
        <span id="modalTitle">নতুন কাস্টমার</span>
      </div>
      <button class="bsheet-close" onclick="closeSheet()"><i class="bi bi-x-lg"></i></button>
    </div>

    <form method="POST" id="custForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <input type="hidden" name="action" id="formAction" value="save">
      <input type="hidden" name="cust_id" id="editId">
      <input type="hidden" name="profile_pic" id="picData">

      <div class="fld"><i class="bi bi-shop"></i>
        <input type="text" name="shop_name" id="fShop" class="inp bn" placeholder="দোকানের নাম *" required>
      </div>
      <div class="fld"><i class="bi bi-person-fill"></i>
        <input type="text" name="customer_name" id="fName" class="inp bn" placeholder="মালিকের নাম *" required>
      </div>
      <div class="fld"><i class="bi bi-telephone-fill"></i>
        <input type="tel" name="phone" id="fPhone" class="inp bn" placeholder="মোবাইল নম্বর *" required>
      </div>
      <div class="fld"><i class="bi bi-geo-alt-fill"></i>
        <textarea name="address" id="fAddr" class="bn" placeholder="ঠিকানা" rows="2" style="padding-left:38px"></textarea>
      </div>
      <div class="fld-row">
        <div class="fld" style="margin-bottom:0"><i class="bi bi-clipboard-data-fill"></i>
          <input type="number" name="opening_balance" id="fOpen" class="inp bn" placeholder="Opening" step="0.01">
        </div>
        <div class="fld" style="margin-bottom:0"><i class="bi bi-shield-lock-fill"></i>
          <input type="number" name="credit_limit" id="fLimit" class="inp bn" placeholder="লিমিট" step="0.01">
        </div>
      </div>

      <div class="pic-card">
        <div class="ttl bn"><i class="bi bi-camera-fill"></i> প্রোফাইল ছবি (ঐচ্ছিক)</div>
        <div class="pic-btns">
          <button type="button" class="pb pb-cam" onclick="startCam()"><i class="bi bi-camera-fill"></i> <span class="bn">ক্যামেরা</span></button>
          <label class="pb pb-gal"><i class="bi bi-images"></i> <span class="bn">গ্যালারি</span><input type="file" accept="image/*" style="display:none" onchange="loadFile(this)"></label>
        </div>
        <div id="camArea">
          <video id="camVid" autoplay playsinline></video>
          <button type="button" class="btn-cap" onclick="capSnap()"><i class="bi bi-camera-fill"></i> <span class="bn">ক্যাপচার করুন</span></button>
        </div>
        <img id="picPrev" alt="">
        <canvas id="picCanvas" style="display:none"></canvas>
      </div>

      <button type="submit" class="btn-save"><i class="bi bi-cloud-arrow-up-fill" style="margin-right:6px"></i><span class="bn">সেভ করুন</span></button>
    </form>
  </div>
</div>

<!-- Auth Modal -->
<div class="auth-modal" id="authModal">
  <div class="auth-box">
    <div class="auth-icon"><i class="bi bi-shield-lock-fill"></i></div>
    <div class="auth-title bn">অ্যাডমিন যাচাই</div>
    <div class="auth-sub bn">পার্মানেন্ট ডিলিটের জন্য পাসওয়ার্ড দিন</div>

    <div class="pass-fld">
      <i class="bi bi-key-fill"></i>
      <input type="password" id="passInput" placeholder="অ্যাডমিন পাসওয়ার্ড" class="bn" autocomplete="current-password">
    </div>
    <div id="passMsg"></div>

    <div class="auth-row">
      <button id="delConfirmBtn" class="auth-btn auth-btn-del" onclick="confirmDelete()"><i class="bi bi-trash3-fill"></i> <span class="bn">ডিলিট করুন</span></button>
      <button class="auth-btn auth-btn-can bn" onclick="closeAuthModal()">বাতিল</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
const _ = id => document.getElementById(id);

/* ===== Toast ===== */
function showToast(msg, type, ms){
  type = type || 'info'; ms = ms || 3200;
  const ic = {ok:'fa-circle-check', err:'fa-circle-xmark', info:'fa-circle-info', warn:'fa-triangle-exclamation'}[type] || 'fa-circle-info';
  const wrap = _('toastWrap'); if(!wrap) return;
  const el = document.createElement('div');
  el.className = 'toast-sk ' + type;
  el.innerHTML = '<span class="ti"><i class="fas '+ic+'"></i></span><span>'+msg+'</span>';
  wrap.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 320); }, ms);
}
function toastAfterReload(msg, type){ try{ sessionStorage.setItem('_skToast', JSON.stringify({msg:msg, type:type||'ok'})); }catch(e){} }

/* ===== Global SMS on/off (admin only) ===== */
async function toggleSmsGlobal(btn){
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  btn.disabled = true;
  try{
    const fd = new FormData();
    fd.append('ajax_action', 'toggle_sms');
    fd.append('csrf_token', csrf);
    const res = await fetch('customers.php', {method:'POST', body:fd});
    const raw = await res.text();
    let d = null;
    try { d = JSON.parse(raw); } catch(_) {}
    if(!d){
      showToast('❌ সার্ভার JSON দেয়নি (HTTP ' + res.status + ')। Controllers ও Models ফোল্ডারের নতুন ফাইল আপলোড হয়েছে কিনা চেক করুন।', 'err');
    } else if(d.ok){
      const on = d.state === 1;
      btn.dataset.state = on ? '1' : '0';
      btn.title = 'SMS ' + (on ? 'চালু' : 'বন্ধ');
      btn.style.background = on ? 'linear-gradient(135deg,#1F8A4C,#166e3c)' : 'rgba(229,36,42,.35)';
      const ic = _('smsToggleIcon');
      if(ic) ic.className = 'bi ' + (on ? 'bi-chat-dots-fill' : 'bi-chat-left-dots');
      showToast((on ? '✅ ' : '🔕 ') + d.msg, on ? 'ok' : 'warn');
    } else {
      showToast('❌ ' + d.msg, 'err');
    }
  }catch(e){ showToast('❌ নেটওয়ার্ক সমস্যা।', 'err'); }
  btn.disabled = false;
}
(function(){
  const FLASH = {
    cust_saved:['✅ কাস্টমার সেভ হয়েছে।','ok'],
    status_toggled:['কাস্টমার স্ট্যাটাস আপডেট হয়েছে।','info']
  };
  window.addEventListener('DOMContentLoaded', () => {
    try{ const s = sessionStorage.getItem('_skToast'); if(s){ const d=JSON.parse(s); sessionStorage.removeItem('_skToast'); showToast(d.msg, d.type); } }catch(e){}
    const m = new URLSearchParams(location.search).get('msg');
    if(m && FLASH[m]){ showToast(FLASH[m][0], FLASH[m][1]); }
    if(m){ try{ const u=new URL(location.href); u.searchParams.delete('msg'); history.replaceState(null,'',u); }catch(e){} }
  });
})();

/* ===== Theme Toggle ===== */
function applyTheme(t){
  document.documentElement.setAttribute('data-bs-theme', t);
  const icon = _('themeIcon');
  if (icon) icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
  try{ localStorage.setItem('skTheme', t); }catch(e){}
}
function toggleTheme(){
  const cur = document.documentElement.getAttribute('data-bs-theme') || 'light';
  applyTheme(cur === 'dark' ? 'light' : 'dark');
}
(function(){ let s='light'; try{s=localStorage.getItem('skTheme')||'light';}catch(e){} applyTheme(s); })();

/* ===== Fast Search (debounced, phone+name+shop) ===== */
let _srchTimer = null;
function doSearch(q){
  clearTimeout(_srchTimer);
  _srchTimer = setTimeout(() => {
    const term = (q || '').toLowerCase().trim();
    let visible = 0;
    document.querySelectorAll('.cc').forEach(c => {
      const match = !term || (c.dataset.search || '').includes(term);
      c.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    const cntEl = document.getElementById('activeCnt');
    if (cntEl && term) cntEl.textContent = visible + ' টি ফলাফল';
    else if (cntEl) cntEl.textContent = '<?php echo $total_active; ?> জন';
  }, 120);
}
/* Keyboard shortcut: / key focuses search */
document.addEventListener('keydown', e => {
  if (e.key === '/' && document.activeElement !== _('srchInp')) {
    e.preventDefault(); _('srchInp').focus();
  }
  if (e.key === 'Escape') { _('srchInp').value=''; doSearch(''); _('srchInp').blur(); }
});
/* Clear search on X button */
_('srchInp')?.addEventListener('input', function(){
  this.style.paddingRight = this.value ? '38px' : '';
});

/* ===== Card actions expand ===== */
function toggleActs(id){
  if (selMode) { toggleSel(id); return; }
  const el = _('acts-' + id);
  const isOpen = el && el.classList.contains('open');
  document.querySelectorAll('.cc-acts').forEach(a => a.classList.remove('open'));
  if (!isOpen && el) el.classList.add('open');
}

/* ===== Toggle Active/Inactive (secure POST AJAX) ===== */
async function toggleActive(cid){
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  try{
    const fd = new FormData();
    fd.append('ajax_action','toggle_active');
    fd.append('cid', cid);
    fd.append('csrf_token', csrf);
    const res = await fetch('customers.php', { method:'POST', body:fd });
    const d = await res.json();
    if (d.ok){ toastAfterReload(d.msg, 'info'); location.reload(); }
    else { showToast('❌ ' + d.msg, 'err'); }
  }catch(e){ showToast('❌ নেটওয়ার্ক সমস্যা।', 'err'); }
}

/* ===== Group / Bulk Collection SMS ===== */
let selMode = false;
const selected = new Set();
function toggleSelMode(){
  selMode = !selMode;
  document.body.classList.toggle('selmode', selMode);
  _('selModeBtn').classList.toggle('active', selMode);
  if (!selMode){
    selected.clear();
    document.querySelectorAll('.cc.sel').forEach(c => c.classList.remove('sel'));
  }
  document.querySelectorAll('.cc-acts').forEach(a => a.classList.remove('open'));
  updateSelBar();
}
function toggleSel(cid){
  const card = document.querySelector('.cc[data-cid="' + cid + '"]');
  if (!card) return;
  const key = String(cid);
  if (selected.has(key)){ selected.delete(key); card.classList.remove('sel'); }
  else { selected.add(key); card.classList.add('sel'); }
  updateSelBar();
}
function selectAllVisible(){
  const cards = [...document.querySelectorAll('.cc[data-cid]')].filter(c => c.style.display !== 'none');
  const allSel = cards.length > 0 && cards.every(c => selected.has(c.dataset.cid));
  cards.forEach(c => {
    if (allSel){ selected.delete(c.dataset.cid); c.classList.remove('sel'); }
    else { selected.add(c.dataset.cid); c.classList.add('sel'); }
  });
  updateSelBar();
}
function updateSelBar(){
  const n = selected.size;
  _('selCount').textContent = n;
  _('selBar').classList.toggle('show', selMode);
  _('selSendBtn').disabled = n === 0;
}
async function sendBulkSms(){
  if (selected.size === 0) return;
  const ids = [...selected];
  if (!confirm(ids.length + ' জন কাস্টমারকে কালেকশন SMS পাঠাবেন?')) return;
  const btn = _('selSendBtn'); const orig = btn.innerHTML;
  btn.disabled = true;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const CHUNK = 25;                 // প্রতি রিকোয়েস্টে সর্বোচ্চ ২৫ — টাইমআউট নিরাপদ
  let sent = 0, fail = 0, done = 0;
  try{
    for (let i = 0; i < ids.length; i += CHUNK){
      const batch = ids.slice(i, i + CHUNK);
      btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> ' + done + '/' + ids.length;
      const body = new URLSearchParams();
      body.append('ajax_action', 'send_bulk_collection_sms');
      body.append('csrf_token', csrf);
      batch.forEach(id => body.append('cids[]', id));
      const res = await fetch('customers.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: body.toString()
      });
      const d = await res.json();
      if (d.ok){ sent += (d.sent||0); fail += (d.fail||0); }
      else { fail += batch.length; }
      done += batch.length;
    }
    showToast('✅ গ্রুপ SMS সম্পন্ন — পাঠানো: ' + sent + ' জন' + (fail ? (', ব্যর্থ: ' + fail + ' জন') : ''), fail ? 'warn' : 'ok', 4600);
    toggleSelMode();
  }catch(e){ showToast('❌ নেটওয়ার্ক সমস্যা — কিছু SMS পাঠানো হয়নি।', 'err'); }
  finally{ btn.disabled = false; btn.innerHTML = orig; }
}

/* ===== Inactive toggle ===== */
function toggleInactive(btn){
  const wrap = _('inactiveWrap');
  const open = wrap.style.display === 'none';
  wrap.style.display = open ? 'flex' : 'none';
  wrap.style.flexDirection = 'column';
  btn.classList.toggle('open', open);
}

/* ===== Send Collection SMS (AJAX — backend identical) ===== */
function sendSMS(cid, btnEl){
  if (!confirm('এই কাস্টমারকে কালেকশন SMS পাঠাবেন?')) return;
  const orig = btnEl.innerHTML;
  btnEl.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
  btnEl.disabled = true;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  fetch('customers.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'ajax_action=send_collection_sms&cid=' + cid + '&csrf_token=' + encodeURIComponent(csrf)
  })
  .then(r => r.json())
  .then(d => {
    btnEl.innerHTML = orig; btnEl.disabled = false;
    if (d.ok){ showToast('✅ ' + d.msg, 'ok'); }
    else     { showToast('❌ ' + d.msg, 'err'); }
  })
  .catch(() => { btnEl.innerHTML = orig; btnEl.disabled = false; showToast('❌ নেটওয়ার্ক সমস্যা।', 'err'); });
}

/* ===== Admin actions (edit/ban/del) ===== */
let _pendingDelId = null;
function authAction(type, data){
  <?php if ($role !== 'admin'): ?>
  alert('শুধুমাত্র অ্যাডমিন এই কাজ করতে পারবেন।'); return;
  <?php endif; ?>

  if (type === 'edit') { openSheet('edit', data); return; }
  if (type === 'ban')  { if (confirm('Inactive করবেন?')) toggleActive(data); return; }
  if (type === 'del'){
    _pendingDelId = data;
    _('passInput').value = '';
    _('passMsg').textContent = '';
    _('passMsg').className = '';
    _('authModal').classList.add('show');
    setTimeout(() => _('passInput').focus(), 100);
  }
}
function closeAuthModal(){
  _('authModal').classList.remove('show');
  _pendingDelId = null;
  _('passInput').value = '';
}

async function confirmDelete(){
  if (!_pendingDelId) return;
  const pass = _('passInput').value.trim();
  if (!pass){ showPassMsg('পাসওয়ার্ড দিন।', 'err'); return; }

  const btn = _('delConfirmBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> <span class="bn">যাচাই হচ্ছে...</span>';

  try{
    const fd = new FormData();
    fd.append('ajax_action', 'verify_delete');
    fd.append('cid', _pendingDelId);
    fd.append('password', pass);
    fd.append('csrf_token', '<?php echo htmlspecialchars($csrf_token); ?>');
    const res = await fetch('customers.php', { method:'POST', body:fd });
    const d = await res.json();
    if (d.ok){
      showPassMsg('✅ ' + d.msg, 'ok');
      toastAfterReload('✅ ' + d.msg, 'ok');
      setTimeout(() => { closeAuthModal(); location.reload(); }, 1100);
    } else {
      showPassMsg('❌ ' + d.msg, 'err');
      _('passInput').value = ''; _('passInput').focus();
    }
  }catch(e){
    showPassMsg('নেটওয়ার্ক সমস্যা!', 'err');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-trash3-fill"></i> <span class="bn">ডিলিট করুন</span>';
  }
}
function showPassMsg(msg, type){
  const el = _('passMsg');
  el.textContent = msg;
  el.className = type === 'ok' ? 'pm-ok' : 'pm-err';
}
document.addEventListener('DOMContentLoaded', () => {
  _('passInput')?.addEventListener('keydown', e => { if (e.key === 'Enter') confirmDelete(); });
});

/* ===== Customer Sheet (add/edit) ===== */
/* ===== Camera helpers ===== */
function stopStream(s){ if(s) s.getTracks().forEach(t=>t.stop()); }
function camError(e){
  const msg = e.name==='NotAllowedError'    ? 'ক্যামেরা পারমিশন দেননি। ব্রাউজার সেটিংস থেকে Allow করুন।'
            : e.name==='NotFoundError'       ? 'কোনো ক্যামেরা পাওয়া যায়নি।'
            : e.name==='NotReadableError'    ? 'ক্যামেরা অন্য অ্যাপ ব্যবহার করছে।'
            : 'ক্যামেরা সমস্যা: '+(e.message||e.name);
  alert('❌ '+msg);
}
async function openCamera(videoEl, facingMode){
  if(!navigator.mediaDevices?.getUserMedia){
    alert('এই ব্রাউজারে ক্যামেরা সাপোর্ট নেই (HTTPS দরকার)।'); return null;
  }
  if(videoEl.srcObject){ stopStream(videoEl.srcObject); videoEl.srcObject=null; }
  let stream=null;
  try{ stream=await navigator.mediaDevices.getUserMedia({video:{facingMode,width:{ideal:1280},height:{ideal:720}}}); }
  catch(e1){ try{ stream=await navigator.mediaDevices.getUserMedia({video:true}); }catch(e2){ camError(e2); return null; } }
  videoEl.srcObject=stream;
  await new Promise(res=>{ videoEl.onloadedmetadata=res; });
  try{ await videoEl.play(); }catch(e){}
  return stream;
}
function snapCanvas(videoEl, canvasEl, maxW=1024){
  const w=videoEl.videoWidth||videoEl.offsetWidth||640;
  const h=videoEl.videoHeight||videoEl.offsetHeight||480;
  const sf=Math.min(maxW/w,1);
  canvasEl.width=Math.round(w*sf); canvasEl.height=Math.round(h*sf);
  canvasEl.getContext('2d').drawImage(videoEl,0,0,canvasEl.width,canvasEl.height);
  return canvasEl.toDataURL('image/jpeg',.82);
}

/* ===== Customer sheet camera ===== */
let camStream = null;
async function startCam(){
  _('camArea').style.display='block';
  _('picPrev').style.display='none';
  camStream = await openCamera(_('camVid'), 'user');
  if(!camStream) _('camArea').style.display='none';
}
function capSnap(){
  const b64 = snapCanvas(_('camVid'), _('picCanvas'));
  _('picData').value=b64;
  _('picPrev').src=b64; _('picPrev').style.display='block';
  stopStream(camStream); camStream=null;
  _('camArea').style.display='none';
}
function loadFile(input){
  if(!input.files[0]) return;
  const r=new FileReader();
  r.onload=e=>{
    const img=new Image();
    img.onload=()=>{
      const c=_('picCanvas'), sf=Math.min(800/img.width,1);
      c.width=Math.round(img.width*sf); c.height=Math.round(img.height*sf);
      c.getContext('2d').drawImage(img,0,0,c.width,c.height);
      const b64=c.toDataURL('image/jpeg',.82);
      _('picData').value=b64; _('picPrev').src=b64; _('picPrev').style.display='block';
    };
    img.src=e.target.result;
  };
  r.readAsDataURL(input.files[0]);
}
function stopCam(){ stopStream(camStream); camStream=null; }
document.addEventListener('visibilitychange',()=>{ if(document.hidden){ stopCam(); } });

/* ===== Customer Sheet (add/edit) ===== */
function openSheet(type, data = null){
  stopCam();
  _('custForm').reset();
  _('picData').value = '';
  _('picPrev').style.display = 'none';
  _('camArea').style.display = 'none';

  if (type === 'edit' && data){
    _('modalTitle').textContent = 'কাস্টমার এডিট';
    _('formAction').value = 'update';
    _('editId').value = data.id;
    _('fShop').value  = data.shop_name || '';
    _('fName').value  = data.customer_name || '';
    _('fPhone').value = data.phone || '';
    _('fAddr').value  = data.address || '';
    _('fOpen').value  = data.opening_balance || '';
    _('fLimit').value = data.credit_limit || '';
  } else {
    _('modalTitle').textContent = 'নতুন কাস্টমার';
    _('formAction').value = 'save';
    _('editId').value = '';
  }
  _('custModal').classList.add('show');
}

function closeSheet(){
  _('custModal').classList.remove('show');
  stopCam();
}
_('custModal').addEventListener('click', e => { if (e.target.id === 'custModal') closeSheet(); });
</script>

<!-- Bottom Navigation Bar -->
<?php include 'customer_bottom_nav.php'; ?>

</body>
</html>
