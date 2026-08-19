<?php
declare(strict_types=1);

/**
 * customer_profile.php — Customer ledger/profile page (view).
 * Session, auth, CSRF, headers and the PDO connection ($conn)
 * all come from config/bootstrap.php.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/Controllers/CustomerController.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: customers.php"); exit; }

$controller = new CustomerController($conn);
$controller->handleCustomerProfileRequests($id, $csrf_token);
$viewData = $controller->getCustomerProfileData($id);
if (!$viewData['customer']) { header("Location: customers.php"); exit; }

$c           = $viewData['customer'];
$role        = $viewData['role'];
$username    = $viewData['username'];
$trans       = $viewData['transactions'];
$period_bill = $viewData['period_bill'];
$period_rec  = $viewData['period_rec'];
$net_due     = $viewData['net_due'];
$limit_alert = $viewData['limit_alert'];
$hue         = $viewData['hue'];
$theme       = $viewData['theme'];
$is_locked   = (int)($c['bill_locked'] ?? 0);
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

<meta name="theme-color" content="#0E0E10">
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8'); ?>">
<title>Profile — <?php echo htmlspecialchars($c['shop_name']); ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800;900&display=swap" rel="stylesheet">

<style>
/* ===== Tokens ===== */
:root{
  --brand-1:#E5242A; --brand-2:#C8161C;
  --grad-brand:linear-gradient(135deg,#E5242A,#C8161C,#A50F14);
  --grad-hero:linear-gradient(150deg,#2A2A2E 0%,#1A1A1D 60%,#0E0E10 130%);
  --card-bg:#fff; --soft-bg:#F1F1F3; --soft-bd:#E6E7EA; --txt-mut:#71717A;
}
[data-bs-theme="dark"]{
  --card-bg:#0E0E10; --soft-bg:#1A1A1D; --soft-bd:#2A2A2E; --txt-mut:#A1A1AA;
  --bs-body-bg:#000000; --bs-body-color:#E6E7EA;
}
*,*::before,*::after{box-sizing:border-box;}
html,body{max-width:100vw;overflow-x:hidden;-webkit-overflow-scrolling:touch;}
body{font-family:'Plus Jakarta Sans','Hind Siliguri',sans-serif;background:var(--bs-body-bg,#F7F7F8);color:var(--bs-body-color,#1A1A1D);margin:0;padding-bottom:92px;font-size:14px}
.bn{font-family:'Hind Siliguri',sans-serif}
.num{font-variant-numeric:tabular-nums}

/* ===== Navbar ===== */
.navbar{display:flex;align-items:center;gap:10px;background:linear-gradient(135deg,#0E0E10 0%,#1A1A1D 45%,#2A2A2E 100%);padding:10px 12px;position:sticky;top:0;z-index:100;box-shadow:0 4px 14px rgba(0,0,0,.25)}
.btn-ic{width:38px;height:38px;border-radius:11px;border:1.5px solid rgba(255,255,255,.2);background:rgba(255,255,255,.12);color:#fff;font-size:15px;cursor:pointer;display:grid;place-items:center;flex-shrink:0;text-decoration:none;transition:transform .12s}
.btn-ic:active{transform:scale(.91)}
.logo-img{width:42px;height:42px;border-radius:50%;object-fit:cover;border:2.5px solid rgba(255,255,255,.4);flex-shrink:0;box-shadow:0 4px 10px -3px rgba(0,0,0,.5);background:#fff}
.logo-fallback{width:42px;height:42px;border-radius:50%;border:2.5px solid rgba(255,255,255,.4);flex-shrink:0;background:linear-gradient(135deg,#E5242A,#52525B);display:grid;place-items:center;font-size:16px;font-weight:900;color:#fff}
.due-box{flex:1;min-width:0;background:rgba(0,0,0,.28);border:1px solid rgba(229,36,42,.28);border-radius:12px;padding:6px 12px;display:flex;align-items:center;gap:9px}
.due-box .ic{width:28px;height:28px;border-radius:9px;background:rgba(229,36,42,.18);color:#F6ABAD;display:grid;place-items:center;font-size:12px;flex-shrink:0}
.due-lbl{font-size:8.5px;font-weight:700;color:#D5D6DA;text-transform:uppercase;letter-spacing:.4px;line-height:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.due-val{font-size:16px;font-weight:900;color:#F6ABAD;line-height:1.1}

/* ===== Hero ===== */
.prof-hdr{background:var(--grad-hero);padding:22px 16px 18px;text-align:center;border-radius:0 0 28px 28px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 30px -15px rgba(0,0,0,.4)}
.prof-hdr::before{content:"";position:absolute;top:-40px;right:-40px;width:160px;height:160px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.16),transparent 70%)}
.prof-hdr::after{content:"";position:absolute;bottom:-30px;left:-20px;width:120px;height:120px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.1),transparent 70%)}
.prof-hdr>*{position:relative;z-index:1}
.p-img-wrap{position:relative;display:inline-block}
.p-img{width:88px;height:88px;border-radius:26px;border:3px solid rgba(255,255,255,.45);object-fit:cover;box-shadow:0 10px 24px -10px rgba(0,0,0,.55);background:linear-gradient(135deg,#E5242A,#A50F14);display:grid;place-items:center;font-size:36px;color:#fff;font-weight:900}
.p-cam-btn{position:absolute;bottom:-4px;right:-6px;background:#F6ABAD;color:#000;border:3px solid #fff;border-radius:50%;width:30px;height:30px;cursor:pointer;font-size:12px;display:grid;place-items:center;box-shadow:0 4px 10px -3px rgba(0,0,0,.4)}
.prof-name{margin:10px 0 3px;font-size:20px;font-weight:900;text-transform:uppercase;letter-spacing:.4px;line-height:1.15}
.prof-sub{font-size:12px;opacity:.85;margin:0 0 10px;font-weight:500}
.coll-badge{background:rgba(255,255,255,.22);display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid rgba(255,255,255,.2);backdrop-filter:blur(6px)}

/* ===== Limit Alert ===== */
.limit-alert{background:linear-gradient(135deg,#FEECEC,#FBD5D6);color:#7d0d11;padding:11px 14px;border-radius:14px;font-weight:700;font-size:12px;border:1px solid #F6ABAD;margin:12px;display:flex;align-items:center;gap:10px;box-shadow:0 6px 14px -8px rgba(229,36,42,.45)}
.limit-alert .ico{width:30px;height:30px;border-radius:9px;background:#fff;color:#C8161C;display:grid;place-items:center;font-size:14px;flex-shrink:0}
[data-bs-theme="dark"] .limit-alert{background:rgba(229,36,42,.15);color:#F6ABAD;border-color:rgba(229,36,42,.4)}
[data-bs-theme="dark"] .limit-alert .ico{background:rgba(229,36,42,.25)}

/* ===== Button Strip ===== */
.btn-row{display:flex;gap:6px;background:var(--card-bg);padding:14px 8px;border-radius:18px;margin:12px;border:1px solid var(--soft-bd);justify-content:space-between;box-shadow:0 8px 18px -14px rgba(0,0,0,.3)}
.btn-col{display:flex;flex-direction:column;align-items:center;gap:6px;flex:1}
.btn-col span{font-size:9.5px;font-weight:800;color:var(--txt-mut);text-transform:uppercase;letter-spacing:.4px}
.btn-3d{width:46px;height:46px;border-radius:14px;border:none;color:#fff;font-size:18px;cursor:pointer;display:grid;place-items:center;transition:transform .12s,filter .2s;text-decoration:none}
.btn-3d:active{transform:translateY(2px);filter:brightness(.9)}
.b-print {background:linear-gradient(135deg,#71717A,#52525B);box-shadow:0 4px 0 #2A2A2E}
.b-lock  {background:linear-gradient(135deg,#E5242A,#C8161C);box-shadow:0 4px 0 #A50F14}
.b-unlock{background:linear-gradient(135deg,#1F8A4C,#166e3c);box-shadow:0 4px 0 #0f5a2e}
.b-sms   {background:linear-gradient(135deg,#D9930B,#b67807);box-shadow:0 4px 0 #8a5a05}
.b-hist  {background:linear-gradient(135deg,#2A2A2E,#1A1A1D);box-shadow:0 4px 0 #0E0E10}
.b-add   {background:linear-gradient(135deg,#1F8A4C,#166e3c);box-shadow:0 4px 0 #0f5a2e}

/* ===== Collapsible Panels ===== */
.coll{display:none;background:var(--card-bg);border-radius:18px;border:1px solid var(--soft-bd);overflow:hidden;margin:0 12px 14px;box-shadow:0 8px 18px -14px rgba(0,0,0,.3);animation:slideD .25s ease}
.coll.open{display:block}
@keyframes slideD{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.panel-hdr{padding:10px 14px;background:var(--soft-bg);font-size:11px;font-weight:800;color:var(--txt-mut);text-transform:uppercase;border-bottom:1px solid var(--soft-bd);letter-spacing:.5px;display:flex;align-items:center;gap:7px}
.form-body{padding:14px}

/* ===== Form Inputs ===== */
.tk-row{display:flex;gap:8px;margin-bottom:9px}
.tk-inp{flex:1;padding:12px 14px;border-radius:11px;border:1.5px solid var(--soft-bd);background:var(--card-bg);color:var(--bs-body-color);font-size:13.5px;outline:none;font-weight:600;width:100%;font-family:inherit;transition:border-color .2s,box-shadow .2s}
.tk-inp:focus{border-color:var(--brand-1);box-shadow:0 0 0 4px rgba(229,36,42,.13)}
.tk-bill{border-left:4px solid #1F8A4C}
.tk-bill:focus{border-color:#1F8A4C;box-shadow:0 0 0 4px rgba(31,138,76,.13)}
.tk-pay{border-left:4px solid #E5242A}
.tk-pay:focus{border-color:#E5242A;box-shadow:0 0 0 4px rgba(229,36,42,.13)}
.tk-locked{background:var(--soft-bg)!important;color:#E5242A!important;cursor:not-allowed;border-color:#F6ABAD!important;font-size:12px}

.btn-cam-sm{background:var(--card-bg);border:1.5px solid var(--soft-bd);border-radius:11px;padding:0 14px;height:47px;cursor:pointer;font-size:18px;color:var(--brand-1);display:grid;place-items:center;flex-shrink:0;transition:background .2s}
.btn-cam-sm:active{background:var(--soft-bg)}

.tk-action{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:var(--soft-bg);border-radius:11px;margin-bottom:11px;border:1px dashed var(--soft-bd)}

.tk-switch{position:relative;width:36px;height:20px;flex-shrink:0}
.tk-switch input{opacity:0;width:0;height:0}
.tk-slider{position:absolute;cursor:pointer;inset:0;background:#D5D6DA;transition:.3s;border-radius:20px}
.tk-slider::before{position:absolute;content:'';height:14px;width:14px;left:3px;bottom:3px;background:#fff;transition:.3s;border-radius:50%}
input:checked+.tk-slider{background:#1F8A4C}
input:checked+.tk-slider::before{transform:translateX(16px)}

.cam-area{display:none;border:2px dashed var(--brand-1);border-radius:14px;padding:10px;margin-bottom:10px;background:var(--soft-bg);position:relative}
.cam-area video{width:100%;border-radius:10px;background:#000;display:block;max-height:240px;object-fit:cover}

.save-btn{width:100%;padding:14px;background:var(--grad-brand);border:none;border-radius:13px;color:#fff;font-weight:900;font-size:14.5px;cursor:pointer;letter-spacing:.3px;box-shadow:0 8px 20px -8px rgba(229,36,42,.65);transition:transform .12s}
.save-btn:active{transform:translateY(2px);box-shadow:0 4px 12px -8px rgba(229,36,42,.5)}
.save-btn.green{background:linear-gradient(135deg,#1F8A4C,#166e3c);box-shadow:0 8px 20px -8px rgba(31,138,76,.6)}

/* ===== History Table ===== */
.hist-hdr{padding:12px 14px;background:var(--soft-bg);font-size:11px;font-weight:800;color:var(--txt-mut);text-transform:uppercase;border-bottom:1px solid var(--soft-bd);letter-spacing:.5px;display:flex;align-items:center;gap:7px}
.hist-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;font-size:12px}
th{background:linear-gradient(135deg,#0E0E10,#1A1A1D);padding:11px 6px;color:#fff;text-align:center;font-weight:800;font-size:10px;text-transform:uppercase;letter-spacing:.4px}
td{padding:11px 6px;border-bottom:1px solid var(--soft-bd);text-align:center;font-weight:700;vertical-align:middle;color:var(--bs-body-color)}
tbody tr:hover{background:var(--soft-bg)}
.row-bill{background:rgba(31,138,76,.08)!important;border-left:3px solid #1F8A4C}
.row-pay {background:rgba(229,36,42,.08)!important;border-left:3px solid #E5242A}
.by-chip{font-size:8.5px;font-weight:800;color:var(--txt-mut);background:var(--soft-bg);padding:2px 6px;border-radius:4px;display:block;margin-top:3px}

/* ===== Bottom Summary Bar ===== */
.slim-sum{position:fixed;bottom:0;left:0;width:100vw;background:var(--card-bg);display:flex;padding:8px 10px max(8px,env(safe-area-inset-bottom));gap:6px;border-top:1px solid var(--soft-bd);z-index:100;box-shadow:0 -6px 18px -8px rgba(0,0,0,.12)}
.slim-box{flex:1;padding:8px 4px;border-radius:11px;text-align:center;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.4px}
.slim-val{display:block;font-size:14px;font-weight:900;margin-top:2px;letter-spacing:.2px}
.sb-bill{background:linear-gradient(135deg,rgba(31,138,76,.12),rgba(31,138,76,.05));color:#166e3c;border:1px solid rgba(31,138,76,.3)}
.sb-rec {background:linear-gradient(135deg,rgba(229,36,42,.12),rgba(229,36,42,.05));color:#C8161C;border:1px solid rgba(229,36,42,.3)}
.sb-due {background:linear-gradient(135deg,rgba(217,147,11,.14),rgba(217,147,11,.06));color:#8a5a05;border:1px solid rgba(217,147,11,.3)}
[data-bs-theme="dark"] .sb-bill{color:#3fb273}
[data-bs-theme="dark"] .sb-rec {color:#F6868A}
[data-bs-theme="dark"] .sb-due {color:#F6ABAD}

/* ===== Modals ===== */
.full-modal{display:none;position:fixed;inset:0;z-index:9999;background:rgba(2,6,23,.7);align-items:center;justify-content:center;backdrop-filter:blur(4px);padding:16px}
.modal-box-sm{background:var(--card-bg);border-radius:22px;width:100%;max-width:360px;padding:22px 18px;animation:popIn .3s cubic-bezier(.2,.9,.3,1.3)}
@keyframes popIn{from{transform:scale(.85);opacity:0}to{transform:scale(1);opacity:1}}
.modal-hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--soft-bd)}
.modal-title{font-weight:900;font-size:15px;color:var(--bs-body-color)}
.modal-close{background:var(--soft-bg);border:none;font-size:14px;color:var(--bs-body-color);cursor:pointer;width:30px;height:30px;border-radius:10px;display:grid;place-items:center}

.auth-modal{display:none;position:fixed;inset:0;background:rgba(2,6,23,.7);z-index:10000;align-items:center;justify-content:center;backdrop-filter:blur(4px);padding:16px}
.auth-box{background:var(--card-bg);border-radius:22px;width:100%;max-width:330px;padding:24px 20px 18px;text-align:center;animation:popIn .3s cubic-bezier(.2,.9,.3,1.3)}
.auth-icon{width:56px;height:56px;background:linear-gradient(135deg,#FEECEC,#FBD5D6);border-radius:18px;display:grid;place-items:center;margin:0 auto 12px;color:#C8161C;font-size:24px;box-shadow:0 8px 18px -8px rgba(229,36,42,.45)}
[data-bs-theme="dark"] .auth-icon{background:rgba(229,36,42,.2);color:#F6ABAD}
.pass-wrap{position:relative;margin:12px 0 6px}
.pass-wrap i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--txt-mut);font-size:14px}
.pass-inp{width:100%;padding:12px 14px 12px 38px;border:1.5px solid var(--soft-bd);border-radius:11px;background:var(--card-bg);color:var(--bs-body-color);font-size:14px;font-weight:600;outline:none;font-family:inherit}
.pass-inp:focus{border-color:#E5242A;box-shadow:0 0 0 4px rgba(229,36,42,.15)}
.pm{font-size:11px;font-weight:700;min-height:18px;margin:5px 0;text-align:center}
.pm-ok{color:#1F8A4C}.pm-err{color:#E5242A}
.del-btn{width:100%;padding:13px;background:linear-gradient(135deg,#E5242A,#C8161C);color:#fff;border:none;border-radius:12px;font-weight:900;font-size:13.5px;cursor:pointer;box-shadow:0 4px 0 #A50F14;display:flex;align-items:center;justify-content:center;gap:7px;margin-top:6px}
.del-btn:active{transform:translateY(2px);box-shadow:0 2px 0 #A50F14}
.cancel-btn{width:100%;padding:12px;background:var(--soft-bg);color:var(--bs-body-color);border:none;border-radius:12px;font-weight:800;font-size:13px;cursor:pointer;margin-top:7px}

.img-viewer{display:none;position:fixed;inset:0;z-index:20000;background:rgba(0,0,0,.9);align-items:center;justify-content:center;backdrop-filter:blur(6px)}
.img-viewer img{max-width:92%;max-height:82vh;border-radius:12px;border:3px solid rgba(255,255,255,.2);object-fit:contain}

@keyframes spin{to{transform:rotate(360deg)}}
.spin{display:inline-block;animation:spin 1s linear infinite}

/* ===== Toast ===== */
#toastWrap{position:fixed;left:0;right:0;bottom:18px;z-index:99999;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none;padding:0 14px}
.toast-sk{display:flex;align-items:center;gap:10px;min-width:240px;max-width:380px;padding:13px 16px;border-radius:14px;color:#fff;font-family:'Hind Siliguri',sans-serif;font-size:13.5px;font-weight:700;box-shadow:0 12px 30px -8px rgba(14,14,16,.45);transform:translateY(20px);opacity:0;transition:transform .3s cubic-bezier(.2,.8,.3,1),opacity .3s;pointer-events:auto}
.toast-sk.show{transform:translateY(0);opacity:1}
.toast-sk .ti{width:26px;height:26px;border-radius:8px;display:grid;place-items:center;font-size:14px;background:rgba(255,255,255,.22);flex-shrink:0}
.toast-sk.ok{background:linear-gradient(135deg,#1F8A4C,#166e3c)}
.toast-sk.err{background:linear-gradient(135deg,#E5242A,#C8161C)}
.toast-sk.info{background:linear-gradient(135deg,#2A2A2E,#0E0E10)}
.toast-sk.warn{background:linear-gradient(135deg,#D9930B,#b67807)}

/* ===== PRINT ===== */
.print-header{display:none}
.print-footer{display:none}
@media print{
  *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  .no-print{display:none!important}
  body{background:#fff!important;color:#000!important;padding:0!important;font-family:'Hind Siliguri',sans-serif!important}

  .print-header{display:block!important}
  .ph-band{background:linear-gradient(135deg,#0E0E10,#1A1A1D,#2A2A2E)!important;color:#fff!important;padding:18px 20px 14px;display:flex;align-items:center;gap:14px}
  .ph-logo{width:64px;height:64px;border-radius:50%;border:3px solid rgba(255,255,255,.4);background:#fff}
  .ph-co h1{font-size:26px;font-weight:900;margin:0;color:#fff!important}
  .ph-co p{font-size:12px;margin:3px 0 0;color:rgba(255,255,255,.85)!important;font-weight:600}
  .ph-due-wrap{margin-left:auto;text-align:right}
  .ph-due-lbl{font-size:9px;color:rgba(255,255,255,.75)!important;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
  .ph-due-val{font-size:26px;font-weight:900;color:#F6ABAD!important;line-height:1.1}

  .ph-cust{background:#F7F7F8!important;border:1px solid #E6E7EA;border-top:none;border-bottom:3px solid #E5242A;padding:14px 20px;display:flex;justify-content:space-between;gap:16px;margin-bottom:16px}
  .ph-cust .l{font-size:13px;line-height:1.7;color:#000!important}
  .ph-cust .l strong{font-weight:900;color:#0E0E10!important}
  .ph-cust .r{text-align:right;font-size:11.5px;color:#52525B!important}
  .ph-cust .r strong{display:block;font-size:13px;font-weight:900;color:#0E0E10!important;margin-top:2px}

  .coll{display:block!important;border:none!important;box-shadow:none!important;margin:0!important;padding:0!important;border-radius:0!important;animation:none!important}
  #sec-entry{display:none!important}
  .hist-hdr{display:none!important}
  .panel-hdr{display:none!important}
  .by-chip{background:#F1F1F3!important;color:#52525B!important}

  table{width:calc(100% - 40px);margin:0 20px;border-collapse:collapse}
  thead{display:table-header-group}
  .hist-scroll{max-height:none!important;overflow:visible!important}
  th{position:static!important}
  th{background:#1A1A1D!important;color:#fff!important;border:1px solid #000;padding:9px 6px;font-size:11px}
  td{border:1px solid #E6E7EA;color:#000!important;padding:8px 6px;font-size:12px}
  .row-bill{background:#E6F4EC!important}
  .row-pay {background:#FEECEC!important}

  .slim-sum{position:relative!important;bottom:auto!important;width:calc(100% - 40px)!important;margin:16px 20px 0;border:2px solid #000;border-radius:8px!important;overflow:hidden;box-shadow:none!important;padding:0!important;gap:0!important}
  .slim-box{padding:11px 6px!important;border-radius:0!important;border:none!important;border-right:1px solid #000!important}
  .slim-box:last-child{border-right:none!important}
  .sb-bill{background:#E6F4EC!important;color:#166e3c!important}
  .sb-rec {background:#FEECEC!important;color:#C8161C!important}
  .sb-due {background:#FBF1DD!important;color:#8a5a05!important}
  .slim-val{color:inherit!important}

  .print-footer{display:block!important;text-align:center;font-size:11px;color:#A1A1AA!important;margin:14px 20px 0;padding-top:10px;border-top:1px dashed #E6E7EA;font-weight:600}
  .print-footer strong{color:#52525B!important;font-weight:900}
  #sec-history{display:block!important}
}
</style>
</head>
<body>
<div id="toastWrap"></div>

<!-- ===== Navbar ===== -->
<div class="navbar no-print">
  <a href="customers.php" class="btn-ic" title="Back"><i class="bi bi-arrow-left"></i></a>
  <?php if (file_exists('assets/logo.png')): ?>
  <img src="assets/logo.png" alt="SK" class="logo-img">
  <?php else: ?>
  <div class="logo-fallback">SK</div>
  <?php endif; ?>
  <div class="due-box">
    <div class="ic"><i class="bi bi-wallet2"></i></div>
    <div style="min-width:0;flex:1">
      <div class="due-lbl bn">মোট বাকি · <?php echo htmlspecialchars(mb_substr($c['shop_name'],0,16,'UTF-8')); ?></div>
      <div class="due-val num">৳<?php echo number_format((float)$net_due); ?></div>
    </div>
  </div>
  <button class="btn-ic" id="themeBtn" onclick="toggleTheme()" title="Theme"><i class="bi bi-moon-stars-fill" id="themeIcon"></i></button>
  <a href="/dashboard.php" class="btn-ic" title="Home"><i class="bi bi-house-fill"></i></a>
</div>

<!-- ===== Print Header ===== -->
<div class="print-header">
  <div class="ph-band">
    <img src="assets/logo.png" class="ph-logo" alt="Sada Kalo">
    <div class="ph-co">
      <h1 class="bn">⚫ সাদা কালো ফ্যাশন</h1>
      <p class="bn">কাস্টমার লেজার রিপোর্ট · Customer Ledger Report</p>
    </div>
    <div class="ph-due-wrap">
      <div class="ph-due-lbl bn">সর্বমোট বাকি</div>
      <div class="ph-due-val num">৳<?php echo number_format((float)$net_due); ?></div>
    </div>
  </div>
  <div class="ph-cust">
    <div class="l">
      <div><strong class="bn">দোকানের নাম:</strong> <?php echo htmlspecialchars($c['shop_name']); ?></div>
      <div><strong class="bn">মালিকের নাম:</strong> <?php echo htmlspecialchars($c['customer_name']); ?></div>
      <div><strong class="bn">মোবাইল:</strong> <?php echo htmlspecialchars($c['phone']); ?></div>
      <?php if (!empty($c['address'])): ?><div><strong class="bn">ঠিকানা:</strong> <?php echo htmlspecialchars($c['address']); ?></div><?php endif; ?>
    </div>
    <div class="r">
      <div class="bn">রিপোর্ট তৈরির তারিখ</div>
      <strong><?php echo date('d M Y, h:i A'); ?></strong>
      <?php if (!empty($c['credit_limit'])): ?>
      <div style="margin-top:6px"><span class="bn">ক্রেডিট লিমিট:</span> ৳<?php echo number_format((float)$c['credit_limit']); ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ===== Hero Profile ===== -->
<div class="prof-hdr no-print">
  <div class="p-img-wrap">
    <?php if (!empty($c['profile_pic']) && file_exists($c['profile_pic'])): ?>
      <img src="<?php echo htmlspecialchars($c['profile_pic']); ?>" class="p-img" style="font-size:0">
    <?php else: ?>
      <div class="p-img"><?php echo htmlspecialchars(mb_substr($c['shop_name'],0,1,'UTF-8')); ?></div>
    <?php endif; ?>
    <?php if (in_array($role,['admin','manager'])): ?>
    <button class="p-cam-btn" onclick="getEl('profileModal').style.display='flex'"><i class="fas fa-camera"></i></button>
    <?php endif; ?>
  </div>
  <h2 class="prof-name bn"><?php echo htmlspecialchars($c['shop_name']); ?></h2>
  <p class="prof-sub bn"><?php echo htmlspecialchars($c['customer_name']); ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($c['phone']); ?></p>
  <div class="coll-badge bn">
    <i class="far fa-calendar-alt" style="color:#F6ABAD"></i>
    পরের কালেকশন: <?php echo !empty($c['next_collection_date']) ? date('d M Y',strtotime($c['next_collection_date'])) : 'সেট করা নেই'; ?>
    <?php if ($role==='admin'): ?>
    <i class="fas fa-edit" style="cursor:pointer;margin-left:5px;opacity:.8" onclick="getEl('dateModal').style.display='flex'"></i>
    <?php endif; ?>
  </div>
</div>

<?php if ($limit_alert): ?>
<div class="limit-alert no-print">
  <div class="ico"><i class="fas fa-exclamation-triangle"></i></div>
  <span class="bn">সতর্কীকরণ: ক্রেডিট লিমিট (৳<?php echo number_format((float)$c['credit_limit']); ?>) অতিক্রম করেছে!</span>
</div>
<?php endif; ?>

<!-- ===== Button Strip ===== -->
<div class="btn-row no-print">
  <div class="btn-col">
    <button onclick="window.print()" class="btn-3d b-print"><i class="fas fa-print"></i></button>
    <span>Print</span>
  </div>
  <div class="btn-col">
    <button onclick="toggleLock(<?php echo $is_locked; ?>)" class="btn-3d <?php echo $is_locked ? 'b-lock' : 'b-unlock'; ?>">
      <i class="fas <?php echo $is_locked ? 'fa-lock' : 'fa-unlock'; ?>"></i>
    </button>
    <span class="bn"><?php echo $is_locked ? 'লক্ড' : 'ওপেন'; ?></span>
  </div>
  <div class="btn-col">
    <button onclick="sendCollectionSms(this)" class="btn-3d b-sms"><i class="fas fa-comment-dots"></i></button>
    <span>SMS</span>
  </div>
  <div class="btn-col">
    <button onclick="toggleSection('history')" class="btn-3d b-hist"><i class="fas fa-history"></i></button>
    <span class="bn">হিস্ট্রি</span>
  </div>
  <div class="btn-col">
    <button onclick="toggleSection('entry')" class="btn-3d b-add"><i class="fas fa-plus-circle"></i></button>
    <span class="bn">এন্ট্রি</span>
  </div>
</div>

<!-- ===== Entry Panel ===== -->
<div class="coll no-print" id="sec-entry">
  <div class="panel-hdr bn"><i class="fas fa-plus-circle" style="color:#1F8A4C"></i> নতুন এন্ট্রি</div>
  <div class="form-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div class="tk-row">
        <input type="number" name="bill_amount" step="0.01" class="tk-inp tk-bill <?php echo $is_locked ? 'tk-locked' : ''; ?>"
               placeholder="<?php echo $is_locked ? 'বিল লক (Admin Locked)' : 'বিল দিলাম (+)'; ?>"
               <?php if ($is_locked) echo 'readonly title="অ্যাডমিন দ্বারা বিল এন্ট্রি বন্ধ"'; ?>>
        <input type="number" name="rec_amount" step="0.01" class="tk-inp tk-pay" placeholder="জমা পেলাম (−)">
      </div>
      <div class="tk-row">
        <input type="text" name="desc" class="tk-inp" placeholder="বিবরণ / মেমো নম্বর">
        <button type="button" class="btn-cam-sm" onclick="startCam()" title="ছবি তুলুন"><i class="fas fa-camera"></i></button>
      </div>

      <!-- Camera area -->
      <div class="cam-area" id="camArea">
        <button type="button" onclick="closeCam()" style="position:absolute;top:14px;right:14px;z-index:5;width:28px;height:28px;border-radius:50%;background:#E5242A;border:none;color:#fff;font-size:12px;cursor:pointer;display:grid;place-items:center;box-shadow:0 3px 8px rgba(0,0,0,.3)"><i class="fas fa-times"></i></button>
        <video id="camVid" autoplay playsinline></video>
        <img id="camThumb" style="width:100%;border-radius:9px;display:none;border:2px solid #1F8A4C;object-fit:cover;max-height:240px">
        <div style="display:flex;gap:8px;margin-top:8px">
          <button type="button" id="capBtn" onclick="capSnap()" style="flex:1;padding:11px;background:linear-gradient(135deg,#1F8A4C,#166e3c);color:#fff;border:none;border-radius:10px;font-weight:800;cursor:pointer;box-shadow:0 4px 0 #0f5a2e"><i class="fas fa-camera"></i> <span class="bn">তুলুন</span></button>
          <button type="button" id="retakeBtn" onclick="retake()" style="flex:1;padding:11px;background:linear-gradient(135deg,#D9930B,#b67807);color:#fff;border:none;border-radius:10px;font-weight:800;cursor:pointer;display:none;box-shadow:0 4px 0 #8a5a05"><i class="fas fa-redo"></i> <span class="bn">আবার</span></button>
        </div>
        <canvas id="camCanvas" style="display:none"></canvas>
        <input type="hidden" name="captured_image" id="capImgData">
      </div>

      <div class="tk-action">
        <input type="date" name="tr_date" value="<?php echo date('Y-m-d'); ?>" class="tk-inp" style="border:none;padding:0;background:transparent;width:135px;font-weight:700" <?php if ($role==='manager') echo 'readonly'; ?>>
        <div style="display:flex;align-items:center;gap:8px">
          <span class="bn" style="font-size:11px;font-weight:800;color:var(--txt-mut)">SMS পাঠান</span>
          <label class="tk-switch"><input type="checkbox" name="send_sms_after" value="1"><span class="tk-slider"></span></label>
        </div>
      </div>
      <button type="submit" name="save_tr" class="save-btn green bn"><i class="fas fa-cloud-upload-alt" style="margin-right:6px"></i>নিশ্চিত করুন</button>
    </form>
  </div>
</div>

<!-- ===== History Panel ===== -->
<div class="coll" id="sec-history">
  <div class="hist-hdr bn"><i class="fas fa-history" style="color:#2A2A2E"></i> লেনদেন হিস্ট্রি — <?php echo count($trans); ?> টি এন্ট্রি</div>
  <div class="hist-scroll">
    <table>
      <thead><tr>
        <th class="bn">তারিখ</th>
        <th class="bn">মেমো/বিবরণ</th>
        <th class="bn">বিল (+)</th>
        <th class="bn">জমা (−)</th>
        <th class="no-print bn">কাজ</th>
      </tr></thead>
      <tbody>
      <?php
        $running_due = $net_due;
        foreach ($trans as $t):
            $t_date  = date('d/m/Y', strtotime($t['tr_date']));
            $t_memo  = htmlspecialchars($t['description'] ?? 'N/A');
            $t_prev  = $running_due - $t['bill_amount'] + $t['received_amount'];
            $rowCls  = $t['bill_amount'] > 0 ? 'row-bill' : ($t['received_amount'] > 0 ? 'row-pay' : '');
            $row_msg = $t['bill_amount'] > 0
                ? "দোকানের নাম: ".$c['shop_name']."\nতারিখ: $t_date\nমেমো: $t_memo\nআজকের বিল: ".$t['bill_amount']."\nমোট বাকী: $running_due\nসাদা কালো ফ্যাশন"
                : "দোকানের নাম: ".$c['shop_name']."\nতারিখ: $t_date\nমেমো: $t_memo\nজমা: ".$t['received_amount']."\nবর্তমান বাকী: $running_due\nসাদা কালো ফ্যাশন";
      ?>
      <tr id="tr-row-<?php echo $t['id']; ?>" class="<?php echo $rowCls; ?>">
        <td style="color:var(--txt-mut);font-size:11px"><?php echo date('d M y',strtotime($t['tr_date'])); ?></td>
        <td style="font-size:11.5px"><?php echo $t_memo; ?></td>
        <td class="num" style="color:#1F8A4C"><?php echo $t['bill_amount']>0 ? '৳'.number_format((float)$t['bill_amount']) : '—'; ?></td>
        <td class="num" style="color:#E5242A"><?php echo $t['received_amount']>0 ? '৳'.number_format((float)$t['received_amount']) : '—'; ?></td>
        <td class="no-print">
          <?php if (!empty($t['image_path']) && file_exists($t['image_path'])): ?>
          <img src="<?php echo htmlspecialchars($t['image_path']); ?>" onclick="viewImg('<?php echo htmlspecialchars($t['image_path']); ?>')"
               style="width:32px;height:32px;border-radius:7px;object-fit:cover;border:1px solid var(--soft-bd);cursor:pointer;display:block;margin:0 auto 4px">
          <?php endif; ?>
          <span class="by-chip"><?php echo htmlspecialchars($t['entry_by']??'—'); ?></span>
          <div style="display:flex;gap:6px;justify-content:center;margin-top:4px">
            <button onclick="sendCollectionSms(this)" style="background:linear-gradient(135deg,#D9930B,#b67807);border:none;color:#fff;font-size:12px;cursor:pointer;padding:5px 8px;border-radius:7px;box-shadow:0 2px 0 #8a5a05"><i class="fas fa-sms"></i></button>
            <?php if ($role==='admin'): ?>
            <button onclick='askEdit(<?php echo (int)$t["id"]; ?>,<?php echo (float)$t["bill_amount"]; ?>,<?php echo (float)$t["received_amount"]; ?>,<?php echo json_encode($t["description"] ?? "", JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT); ?>)' style="background:none;border:1.5px solid #9bd3b1;color:#1F8A4C;font-size:13px;cursor:pointer;padding:4px 7px;border-radius:7px"><i class="fas fa-pen"></i></button>
            <button onclick="askDel(<?php echo $t['id']; ?>)" style="background:none;border:1.5px solid #F6ABAD;color:#E5242A;font-size:13px;cursor:pointer;padding:4px 7px;border-radius:7px"><i class="fas fa-trash-alt"></i></button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php $running_due = $t_prev; endforeach; ?>
      <!-- Opening row -->
      <tr style="background:#FBF1DD;border-top:2px solid #f0c75a">
        <td style="color:#b67807;font-size:11px;font-weight:800" class="bn">শুরু</td>
        <td style="font-weight:900;color:#8a5a05;font-size:11px" class="bn">সাবেক বাকি (Opening)</td>
        <td class="num" style="color:#1F8A4C;font-weight:800"><?php echo $c['opening_balance']>0 ? '৳'.$c['opening_balance'] : '—'; ?></td>
        <td class="num" style="color:#E5242A;font-weight:800"><?php echo $c['opening_balance']<0 ? '৳'.abs($c['opening_balance']) : '—'; ?></td>
        <td class="no-print"><span style="font-size:8.5px;background:#D9930B;color:#fff;padding:2px 6px;border-radius:4px;font-weight:800">System</span></td>
      </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== Bottom Summary ===== -->
<div class="slim-sum">
  <div class="slim-box sb-bill bn">মোট বিল<span class="slim-val num">৳<?php echo number_format((float)$period_bill); ?></span></div>
  <div class="slim-box sb-rec  bn">মোট জমা<span class="slim-val num">৳<?php echo number_format((float)$period_rec); ?></span></div>
  <div class="slim-box sb-due  bn">নিট বাকি<span class="slim-val num">৳<?php echo number_format((float)$net_due); ?></span></div>
</div>

<!-- ===== Image Viewer ===== -->
<div class="img-viewer no-print" id="imgViewer" onclick="this.style.display='none'">
  <img id="imgFull" src="" alt="">
</div>

<!-- ===== Profile Photo Modal ===== -->
<?php if (in_array($role,['admin','manager'])): ?>
<div class="full-modal no-print" id="profileModal">
  <div class="modal-box-sm">
    <div class="modal-hdr">
      <div class="modal-title bn"><i class="fas fa-camera" style="color:#2A2A2E;margin-right:6px"></i>প্রোফাইল ছবি</div>
      <button class="modal-close" onclick="closeProfileModal()"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div style="display:flex;gap:8px;margin-bottom:11px">
        <button type="button" onclick="startProfCam()" style="flex:1;padding:11px;background:linear-gradient(135deg,#2A2A2E,#1A1A1D);color:#fff;border:none;border-radius:11px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 4px 0 #0E0E10"><i class="fas fa-camera"></i><span class="bn">ক্যামেরা</span></button>
        <label style="flex:1;padding:11px;background:linear-gradient(135deg,#52525B,#3F3F46);color:#fff;border:none;border-radius:11px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 4px 0 #2A2A2E"><i class="fas fa-image"></i><span class="bn">গ্যালারি</span><input type="file" accept="image/*" style="display:none" onchange="previewProfFile(this)"></label>
      </div>
      <div id="profCamArea" style="display:none;border:2px dashed #2A2A2E;border-radius:11px;padding:8px;margin-bottom:10px;background:var(--soft-bg)">
        <video id="profVid" autoplay playsinline style="width:100%;border-radius:9px;background:#000;display:block"></video>
        <button type="button" onclick="capProfPic()" style="width:100%;padding:11px;background:linear-gradient(135deg,#1F8A4C,#166e3c);color:#fff;border:none;border-radius:10px;font-weight:800;cursor:pointer;margin-top:8px;box-shadow:0 4px 0 #0f5a2e"><i class="fas fa-camera"></i> <span class="bn">ক্যাপচার</span></button>
      </div>
      <img id="profPrev" style="width:110px;height:110px;border-radius:50%;object-fit:cover;display:none;margin:0 auto 12px;border:4px solid #1F8A4C">
      <input type="hidden" name="new_profile_pic_data" id="profPicData">
      <canvas id="profCanvas" style="display:none"></canvas>
      <button type="submit" name="update_profile_pic" class="save-btn bn"><i class="fas fa-cloud-upload-alt" style="margin-right:6px"></i>ছবি সেভ করুন</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ===== Collection Date Modal ===== -->
<?php if ($role==='admin'): ?>
<div class="full-modal no-print" id="dateModal">
  <div class="modal-box-sm">
    <div class="modal-hdr">
      <div class="modal-title bn"><i class="fas fa-calendar-alt" style="color:#2A2A2E;margin-right:6px"></i>কালেকশন তারিখ</div>
      <button class="modal-close" onclick="getEl('dateModal').style.display='none'"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <label class="bn" style="font-size:10px;font-weight:800;color:var(--txt-mut);text-transform:uppercase;letter-spacing:.4px">প্রথম তারিখ</label>
      <input type="date" name="next_date"   value="<?php echo htmlspecialchars($c['next_collection_date']??''); ?>" class="tk-inp" style="margin:5px 0 12px">
      <label class="bn" style="font-size:10px;font-weight:800;color:var(--txt-mut);text-transform:uppercase;letter-spacing:.4px">দ্বিতীয় তারিখ (ঐচ্ছিক)</label>
      <input type="date" name="next_date_2" value="<?php echo htmlspecialchars($c['next_collection_date_2']??''); ?>" class="tk-inp" style="margin:5px 0 14px">
      <button type="submit" name="set_coll_date" class="save-btn bn"><i class="fas fa-check-circle" style="margin-right:6px"></i>তারিখ সেভ করুন</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ===== Delete Auth Modal ===== -->
<?php if ($role==='admin'): ?>
<div class="auth-modal" id="authModal">
  <div class="auth-box">
    <div class="auth-icon"><i class="fas fa-shield-alt"></i></div>
    <div style="font-size:16px;font-weight:900;color:var(--bs-body-color)" class="bn">অ্যাডমিন যাচাই</div>
    <div style="font-size:11px;color:var(--txt-mut);margin-top:3px" class="bn">এন্ট্রি পার্মানেন্টলি ডিলিট হবে</div>
    <input type="hidden" id="delTrId">
    <div class="pass-wrap">
      <i class="fas fa-lock"></i>
      <input type="password" id="passInp" class="pass-inp bn" placeholder="অ্যাডমিন পাসওয়ার্ড" autocomplete="current-password">
    </div>
    <div id="passMsg" class="pm bn"></div>
    <button id="delBtn" class="del-btn bn" onclick="confirmDelTr()"><i class="fas fa-trash-alt"></i> ডিলিট করুন</button>
    <button class="cancel-btn bn" onclick="closeAuth()">বাতিল</button>
  </div>
</div>
<?php endif; ?>

<!-- ===== Edit Transaction Modal (Admin only) ===== -->
<?php if ($role==='admin'): ?>
<div class="auth-modal" id="editModal">
  <div class="auth-box">
    <div class="auth-icon" style="background:linear-gradient(135deg,#1F8A4C,#166e3c)"><i class="fas fa-pen"></i></div>
    <div style="font-size:16px;font-weight:900;color:var(--bs-body-color)" class="bn">লেনদেন এডিট</div>
    <div style="font-size:11px;color:var(--txt-mut);margin-top:3px" class="bn">বিল, জমা ও বিবরণ পরিবর্তন করুন</div>
    <input type="hidden" id="editTrId">
    <div style="display:flex;gap:8px;margin-top:14px">
      <input type="number" id="editBill" step="0.01" min="0" class="pass-inp bn" style="border-left:4px solid #1F8A4C;padding-left:12px" placeholder="বিল (+)">
      <input type="number" id="editRecv" step="0.01" min="0" class="pass-inp bn" style="border-left:4px solid #E5242A;padding-left:12px" placeholder="জমা (−)">
    </div>
    <input type="text" id="editDesc" class="pass-inp bn" style="margin-top:8px" placeholder="বিবরণ / মেমো নম্বর">
    <div class="pass-wrap" style="margin-top:8px">
      <i class="fas fa-lock"></i>
      <input type="password" id="editPass" class="pass-inp bn" placeholder="অ্যাডমিন পাসওয়ার্ড" autocomplete="current-password">
    </div>
    <div id="editMsg" class="pm bn"></div>
    <button id="editBtn" class="del-btn bn" style="background:linear-gradient(135deg,#1F8A4C,#166e3c)" onclick="confirmEditTr()"><i class="fas fa-check"></i> আপডেট করুন</button>
    <button class="cancel-btn bn" onclick="closeEdit()">বাতিল</button>
  </div>
</div>
<?php endif; ?>

<!-- ===== Print Footer ===== -->
<div class="print-footer">
  <strong>সাদা কালো ফ্যাশন</strong> · Customer Ledger System &nbsp;·&nbsp; মুদ্রণ তারিখ: <?php echo date('d M Y, h:i A'); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
const getEl = id => document.getElementById(id);

/* ===== Toast ===== */
function showToast(msg, type, ms){
  type = type || 'info'; ms = ms || 3200;
  const ic = {ok:'fa-circle-check', err:'fa-circle-xmark', info:'fa-circle-info', warn:'fa-triangle-exclamation'}[type] || 'fa-circle-info';
  const wrap = getEl('toastWrap');
  const el = document.createElement('div');
  el.className = 'toast-sk ' + type;
  el.innerHTML = '<span class="ti"><i class="fas '+ic+'"></i></span><span>'+msg+'</span>';
  wrap.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 320); }, ms);
}
/* stash a toast to show after a full page reload */
function toastAfterReload(msg, type){ try{ sessionStorage.setItem('_skToast', JSON.stringify({msg:msg, type:type||'ok'})); }catch(e){} }
/* on load: show stashed toast + any ?msg= server flash */
(function(){
  const FLASH = {
    tr_saved:['✅ নতুন এন্ট্রি সেভ হয়েছে।','ok'],
    lock_toggled:['বিল লক/আনলক আপডেট হয়েছে।','info'],
    pic_saved:['✅ ছবি আপডেট হয়েছে।','ok'],
    date_saved:['✅ কালেকশন তারিখ সেট হয়েছে।','ok']
  };
  window.addEventListener('DOMContentLoaded', () => {
    try{ const s = sessionStorage.getItem('_skToast'); if(s){ const d=JSON.parse(s); sessionStorage.removeItem('_skToast'); showToast(d.msg, d.type); } }catch(e){}
    const m = new URLSearchParams(location.search).get('msg');
    if(m && FLASH[m]){ showToast(FLASH[m][0], FLASH[m][1]); }
    if(m){ try{ const u=new URL(location.href); u.searchParams.delete('msg'); history.replaceState(null,'',u); }catch(e){} }
  });
})();

/* ===== Theme ===== */
function applyTheme(t){
  document.documentElement.setAttribute('data-bs-theme', t);
  const ic = getEl('themeIcon');
  if (ic) ic.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
  try{ localStorage.setItem('skTheme', t); }catch(e){}
}
function toggleTheme(){
  const cur = document.documentElement.getAttribute('data-bs-theme') || 'light';
  applyTheme(cur === 'dark' ? 'light' : 'dark');
}
(function(){ let s='light'; try{s=localStorage.getItem('skTheme')||'light';}catch(e){} applyTheme(s); })();

/* ===== SMS ===== */
function sendCollectionSms(btnEl) {
    if (!confirm('এই কাস্টমারকে কালেকশন SMS পাঠাবেন?')) return;
    const origHtml = btnEl.innerHTML;
    btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btnEl.disabled  = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('customer_profile.php?id=<?php echo $id; ?>', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'ajax_action=send_collection_sms_profile&csrf_token=' + encodeURIComponent(csrf)
    })
    .then(r => r.json())
    .then(data => {
        btnEl.innerHTML = origHtml; btnEl.disabled = false;
        if (data.ok) { showToast('✅ ' + data.msg, 'ok'); }
        else         { showToast('❌ ' + data.msg, 'err'); }
    })
    .catch(() => { btnEl.innerHTML = origHtml; btnEl.disabled = false; showToast('❌ নেটওয়ার্ক সমস্যা।', 'err'); });
}

/* ===== Panel Toggle ===== */
function toggleSection(key) {
    const el = getEl('sec-' + key);
    if (!el) return;
    const open = el.classList.contains('open');
    document.querySelectorAll('.coll').forEach(c => c.classList.remove('open'));
    if (!open) el.classList.add('open');
}

/* ===== Lock/Unlock (secure POST AJAX) ===== */
function toggleLock(cur) {
    <?php if ($role==='admin'): ?>
    if (!confirm('বিলের এন্ট্রি ' + (cur ? 'চালু (Unlock)' : 'বন্ধ (Lock)') + ' করবেন?')) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const fd = new FormData();
    fd.append('ajax_action','toggle_bill');
    fd.append('csrf_token', csrf);
    fetch('customer_profile.php?id=<?php echo $id; ?>', { method:'POST', body:fd })
      .then(r => r.json())
      .then(d => {
        if (d.ok){ toastAfterReload(d.msg, 'info'); location.reload(); }
        else { showToast('❌ ' + d.msg, 'err'); }
      })
      .catch(() => showToast('❌ নেটওয়ার্ক সমস্যা।', 'err'));
    <?php else: ?>
    alert('শুধুমাত্র অ্যাডমিন লক/আনলক করতে পারবেন।');
    <?php endif; ?>
}

/* ===== Image Viewer ===== */
function viewImg(src) { getEl('imgFull').src = src; getEl('imgViewer').style.display = 'flex'; }

/* ===== Entry Camera ===== */
let camStream = null;
async function startCam() {
    try {
        if (camStream) camStream.getTracks().forEach(t => t.stop());
        getEl('camArea').style.display   = 'block';
        getEl('camThumb').style.display  = 'none';
        getEl('camVid').style.display    = 'block';
        getEl('capBtn').style.display    = 'block';
        getEl('retakeBtn').style.display = 'none';
        camStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        getEl('camVid').srcObject = camStream;
        getEl('camVid').play();
    } catch(e) {
        getEl('camArea').style.display = 'none';
        alert('❌ ক্যামেরা পারমিশন দিন!\n' + (e.name === 'NotAllowedError' ? 'ব্রাউজার সেটিংস থেকে Camera Allow করুন।' : e.message));
    }
}
function closeCam() {
    if (camStream) { camStream.getTracks().forEach(t => t.stop()); camStream = null; }
    getEl('camArea').style.display  = 'none';
    getEl('camThumb').style.display = 'none';
    getEl('capImgData').value       = '';
}
function capSnap() {
    const v = getEl('camVid'), c = getEl('camCanvas');
    const sf = Math.min(800 / (v.videoWidth || 640), 1);
    c.width = (v.videoWidth || 640) * sf; c.height = (v.videoHeight || 480) * sf;
    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
    const b64 = c.toDataURL('image/jpeg', 0.82);
    getEl('capImgData').value        = b64;
    getEl('camThumb').src            = b64;
    getEl('camThumb').style.display  = 'block';
    getEl('camVid').style.display    = 'none';
    getEl('capBtn').style.display    = 'none';
    getEl('retakeBtn').style.display = 'block';
    if (camStream) { camStream.getTracks().forEach(t => t.stop()); camStream = null; }
}
function retake() {
    getEl('camThumb').style.display  = 'none';
    getEl('capImgData').value        = '';
    getEl('retakeBtn').style.display = 'none';
    startCam();
}

/* ===== Profile Camera ===== */
let profStream = null;
async function startProfCam() {
    try {
        getEl('profCamArea').style.display = 'block';
        getEl('profPrev').style.display    = 'none';
        profStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        getEl('profVid').srcObject = profStream;
    } catch(e) { alert('❌ ক্যামেরা পারমিশন দিন!'); }
}
function capProfPic() {
    const v = getEl('profVid'), c = getEl('profCanvas');
    const sf = Math.min(800 / (v.videoWidth || 640), 1);
    c.width = (v.videoWidth || 640) * sf; c.height = (v.videoHeight || 480) * sf;
    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
    const b64 = c.toDataURL('image/jpeg', 0.82);
    getEl('profPicData').value         = b64;
    getEl('profPrev').src              = b64;
    getEl('profPrev').style.display    = 'block';
    getEl('profCamArea').style.display = 'none';
    if (profStream) { profStream.getTracks().forEach(t => t.stop()); profStream = null; }
}
function previewProfFile(input) {
    if (!input.files[0]) return;
    const r = new FileReader();
    r.onload = e => {
        const img = new Image();
        img.onload = () => {
            const c = getEl('profCanvas'), sf = Math.min(800/img.width, 1);
            c.width = img.width*sf; c.height = img.height*sf;
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            const b64 = c.toDataURL('image/jpeg', 0.82);
            getEl('profPicData').value      = b64;
            getEl('profPrev').src           = b64;
            getEl('profPrev').style.display = 'block';
        };
        img.src = e.target.result;
    };
    r.readAsDataURL(input.files[0]);
    if (profStream) { profStream.getTracks().forEach(t => t.stop()); profStream = null; }
    getEl('profCamArea').style.display = 'none';
}
function closeProfileModal() {
    getEl('profileModal').style.display = 'none';
    if (profStream) { profStream.getTracks().forEach(t => t.stop()); profStream = null; }
}

/* ===== Delete TR ===== */
let _pendingTrId = null;
function askDel(trId) {
    _pendingTrId = trId;
    getEl('passInp').value = ''; getEl('passMsg').textContent = ''; getEl('passMsg').className = 'pm bn';
    getEl('authModal').style.display = 'flex';
    setTimeout(() => getEl('passInp').focus(), 100);
}
function closeAuth() { getEl('authModal').style.display = 'none'; _pendingTrId = null; }
async function confirmDelTr() {
    if (!_pendingTrId) return;
    const pass = getEl('passInp').value.trim();
    if (!pass) { showPm('পাসওয়ার্ড দিন।','pm-err'); return; }
    const btn = getEl('delBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch spin"></i> যাচাই...';
    try {
        const fd = new FormData();
        fd.append('ajax_action','verify_delete_tr'); fd.append('tr_id',_pendingTrId);
        fd.append('password',pass); fd.append('csrf_token','<?php echo htmlspecialchars($csrf_token); ?>');
        const res = await fetch('customer_profile.php?id=<?php echo $id; ?>', {method:'POST',body:fd});
        const data = await res.json();
        if (data.ok) {
            showPm('✅ ' + data.msg,'pm-ok');
            toastAfterReload('✅ ' + data.msg, 'ok');
            setTimeout(() => { const row = getEl('tr-row-'+_pendingTrId); if(row) row.remove(); closeAuth(); location.reload(); }, 900);
        } else { showPm('❌ ' + data.msg,'pm-err'); getEl('passInp').value=''; getEl('passInp').focus(); }
    } catch(err) { showPm('নেটওয়ার্ক সমস্যা!','pm-err'); }
    finally { btn.disabled=false; btn.innerHTML='<i class="fas fa-trash-alt"></i> ডিলিট করুন'; }
}
function showPm(msg,cls){ const el=getEl('passMsg'); el.textContent=msg; el.className='pm bn '+cls; }

/* ===== Edit Transaction (Admin) ===== */
let _pendingEditId = null;
function askEdit(trId, bill, recv, desc) {
    _pendingEditId = trId;
    getEl('editTrId').value = trId;
    getEl('editBill').value = bill > 0 ? bill : '';
    getEl('editRecv').value = recv > 0 ? recv : '';
    getEl('editDesc').value = desc || '';
    getEl('editPass').value = '';
    getEl('editMsg').textContent = ''; getEl('editMsg').className = 'pm bn';
    getEl('editModal').style.display = 'flex';
    setTimeout(() => getEl('editBill').focus(), 100);
}
function closeEdit() { getEl('editModal').style.display = 'none'; _pendingEditId = null; }
function showEm(msg,cls){ const el=getEl('editMsg'); el.textContent=msg; el.className='pm bn '+cls; }
async function confirmEditTr() {
    if (!_pendingEditId) return;
    const bill = parseFloat(getEl('editBill').value) || 0;
    const recv = parseFloat(getEl('editRecv').value) || 0;
    const desc = getEl('editDesc').value.trim();
    const pass = getEl('editPass').value.trim();
    if (bill <= 0 && recv <= 0) { showEm('বিল অথবা জমা — অন্তত একটি দিন।','pm-err'); return; }
    if (!pass) { showEm('পাসওয়ার্ড দিন।','pm-err'); return; }
    const btn = getEl('editBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch spin"></i> যাচাই...';
    try {
        const fd = new FormData();
        fd.append('ajax_action','verify_edit_tr');
        fd.append('tr_id',_pendingEditId);
        fd.append('bill_amount',bill);
        fd.append('received_amount',recv);
        fd.append('description',desc);
        fd.append('password',pass);
        fd.append('csrf_token','<?php echo htmlspecialchars($csrf_token); ?>');
        const res = await fetch('customer_profile.php?id=<?php echo $id; ?>', {method:'POST',body:fd});
        const data = await res.json();
        if (data.ok) {
            showEm('✅ ' + data.msg,'pm-ok');
            toastAfterReload('✅ ' + data.msg, 'ok');
            setTimeout(() => { closeEdit(); location.reload(); }, 800);
        } else { showEm('❌ ' + data.msg,'pm-err'); }
    } catch(err) { showEm('নেটওয়ার্ক সমস্যা!','pm-err'); }
    finally { btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> আপডেট করুন'; }
}
document.addEventListener('DOMContentLoaded', () => {
    getEl('passInp')?.addEventListener('keydown', e => { if(e.key==='Enter') confirmDelTr(); });
    getEl('editPass')?.addEventListener('keydown', e => { if(e.key==='Enter') confirmEditTr(); });
});
</script>
</body>
</html>
