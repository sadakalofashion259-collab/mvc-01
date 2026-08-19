<?php
declare(strict_types=1);
ob_start();

define('SK_APP', true);
require_once __DIR__ . '/config.php';

Security::boot();
Security::requireLogin();

$conn = Database::connect();
Security::enforceSession($conn);

$csrfToken = Security::csrfToken();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /Suppliers/suppliers.php'); exit; }

$controller = new SupplierController($conn);
$controller->handleSupplierProfileRequests($id);
$viewData = $controller->getSupplierProfileData($id);

if (!$viewData['supplier']) { header('Location: /Suppliers/suppliers.php'); exit; }

$s           = $viewData['supplier'];
$role        = $viewData['role'];
$username    = $viewData['username'];
$list        = $viewData['transactions'];
$period_bill = $viewData['period_bill'];
$period_pay  = $viewData['period_pay'];
$net_due     = $viewData['net_due'];
$hue         = $viewData['hue'];
$cln_phn     = $viewData['cln_phn'];
$canRecordTr = in_array($role, ['admin','manager','viewer'], true); // লেনদেন এন্ট্রি (বিল/জমা) — সবাই
$canSms      = in_array($role, ['admin','manager'], true);          // SMS টগল/পাঠানো — admin+manager
$smsOn       = (int)$viewData['sms_enabled'] === 1;

/* ── WhatsApp-এ শেয়ার করার জন্য পুরো লেনদেন হিস্ট্রির টেক্সট তৈরি ── */
$waLines   = [];
$waLines[] = '🏪 *' . (string)$s['shop_name'] . '*';
if (!empty($s['name']))  $waLines[] = '👤 ' . (string)$s['name'];
if (!empty($s['phone'])) $waLines[] = '📞 ' . (string)$s['phone'];
$waLines[] = '━━━━━━━━━━━━━━';
$waLines[] = '📋 *লেনদেন হিস্ট্রি — ' . count($list) . ' টি*';
$waLines[] = '';
foreach ($list as $t) {
    $d   = date('d M y', strtotime((string)$t['tr_date']));
    $row = '📅 ' . $d;
    if (trim((string)$t['memo_no']) !== '' && (string)$t['memo_no'] !== '-') $row .= '  |  মেমো: ' . (string)$t['memo_no'];
    $waLines[] = $row;
    $sub = [];
    if ((int)$t['pcs'] > 0)             $sub[] = 'পিস: ' . (int)$t['pcs'];
    if ((float)$t['bill_received'] > 0) $sub[] = 'বিল: ৳' . number_format((float)$t['bill_received']);
    if ((float)$t['payment_given'] > 0) $sub[] = 'জমা: ৳' . number_format((float)$t['payment_given']);
    if ($sub) $waLines[] = '   ' . implode('  |  ', $sub);
    $waLines[] = '';
}
$waLines[] = '━━━━━━━━━━━━━━';
$obVal = (float)$s['opening_balance'];
$waLines[] = 'শুরুর ব্যালেন্স: ৳' . number_format($obVal);
$waLines[] = '➕ মোট বিল: ৳' . number_format((float)$period_bill);
$waLines[] = '➖ মোট জমা: ৳' . number_format((float)$period_pay);
$waLines[] = '🧾 *নিট বাকি: ৳' . number_format((float)$net_due) . '*';
$waLines[] = '━━━━━━━━━━━━━━';
$waLines[] = '— Sada Kalo Fashion';
$waShareText = implode("\n", $waLines);

$supPhoto = !empty($s['photo']) && file_exists(SK_ROOT.'/'.$s['photo'])
    ? '/' . htmlspecialchars((string)$s['photo'], ENT_QUOTES, 'UTF-8')
    : '/assets/img/avatar.svg';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>Ledger — <?php echo htmlspecialchars((string)$s['shop_name'],ENT_QUOTES,'UTF-8'); ?></title>
<link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
<link href="/assets/css/sk-supplier.css" rel="stylesheet">
<script>(function(){var t=localStorage.getItem('sk_theme');if(t==='light')document.documentElement.setAttribute('data-theme','light');})()</script>
</head>
<body>

<!-- ═══════════════════════════════════════════
     PROFILE HEADER
═══════════════════════════════════════════ -->
<div class="prof-hdr">
  <a href="suppliers.php" class="prof-back"><i class="fas fa-arrow-left"></i></a>
  <button class="prof-theme-btn" onclick="toggleTheme()" title="থিম পরিবর্তন">
    <i class="fas fa-moon" id="prokfThemeIcon"></i>
  </button>
  <img src="<?php echo $supPhoto; ?>" class="prof-photo" onerror="this.src='/assets/img/avatar.svg'">
  <h2 class="prof-name"><?php echo htmlspecialchars((string)$s['shop_name'],ENT_QUOTES,'UTF-8'); ?></h2>
  <p class="prof-sub">
    <i class="fas fa-phone-alt"></i>
    <?php echo htmlspecialchars((string)$s['phone'],ENT_QUOTES,'UTF-8'); ?>
    &nbsp;|&nbsp;
    <?php echo htmlspecialchars((string)$s['name'],ENT_QUOTES,'UTF-8'); ?>
  </p>
</div>

<!-- ═══════════════════════════════════════════
     ACTION BUTTONS
═══════════════════════════════════════════ -->
<div class="prof-actions">
  <div class="prof-btn-wrap">
    <button type="button" class="prof-btn pb-wa" onclick="shareHistoryWA()" title="হিস্ট্রি WhatsApp-এ পাঠান">
      <i class="fab fa-whatsapp"></i>
    </button>
    <span>WhatsApp</span>
  </div>
  <div class="prof-btn-wrap">
    <a href="tel:<?php echo htmlspecialchars($cln_phn,ENT_QUOTES); ?>" class="prof-btn pb-call">
      <i class="fas fa-phone"></i>
    </a>
    <span>কল</span>
  </div>
  <div class="prof-btn-wrap">
    <button type="button" class="prof-btn pb-hist" onclick="toggleSection('history')">
      <i class="fas fa-history"></i>
    </button>
    <span>হিস্ট্রি</span>
  </div>
  <?php if ($canSms): ?>
  <div class="prof-btn-wrap">
    <button type="button" id="smsToggleBtn" class="prof-btn"
            onclick="toggleSupSms(this)" data-on="<?php echo $smsOn ? '1':'0'; ?>"
            style="background:<?php echo $smsOn ? 'linear-gradient(135deg,#16a34a,#22c55e)':'#3a4252'; ?>;color:#fff"
            title="SMS চালু/বন্ধ">
      <i class="fas fa-comment-sms" id="smsToggleIcon"></i>
    </button>
    <span id="smsToggleLbl">SMS <?php echo $smsOn ? 'চালু':'বন্ধ'; ?></span>
  </div>
  <?php endif; ?>
  <?php if ($canRecordTr): ?>
  <div class="prof-btn-wrap">
    <button type="button" class="prof-btn pb-add" onclick="setEntryMode('add'); toggleSection('entry')">
      <i class="fas fa-plus"></i>
    </button>
    <span>এন্ট্রি</span>
  </div>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════
     ENTRY FORM (Collapsible)
═══════════════════════════════════════════ -->
<?php if ($canRecordTr): ?>
<div class="sk-coll" id="sec-entry">
  <div class="sk-coll-hdr" id="entryHdr"><i class="fas fa-plus-circle" style="color:var(--green)"></i> <span id="entryHdrTxt">নতুন এন্ট্রি</span></div>
  <div class="coll-body">
    <form id="trForm" onsubmit="submitTr(event)">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8"); ?>">
      <input type="hidden" name="ajax_save_tr" id="fAjaxSave" value="1">
      <input type="hidden" name="ajax_action" id="fAjaxAction" value="" disabled>
      <input type="hidden" name="tr_id" id="fEditTrId" value="" disabled>
      <input type="hidden" name="id" value="<?php echo $id; ?>">
      <input type="hidden" name="t_photo" id="tPhoto">

      <div class="sk-row">
        <input type="text"   name="memo" id="fMemo" class="sk-inp" placeholder="মেমো নম্বর *" required style="flex:1">
        <input type="number" name="pcs"  id="fPcs"  class="sk-inp" placeholder="পিস"         style="flex:1" min="0">
      </div>
      <div class="sk-row">
        <input type="number" step="0.01" name="bill" id="fBill" class="sk-inp sk-inp-bill" placeholder="বিল (+) টাকা" style="flex:1">
        <input type="number" step="0.01" name="pay"  id="fPay"  class="sk-inp sk-inp-pay"  placeholder="জমা (−) টাকা" style="flex:1">
      </div>
      <div class="sk-row" style="align-items:center">
        <input type="date" name="dt" id="fDt"
               value="<?php echo date('Y-m-d'); ?>"
               class="sk-inp"
               style="flex:1;font-size:13px"
               <?php if ($role !== 'admin') echo 'readonly'; ?> required>
        <button type="button" class="cb cb-cam" style="flex:0 0 auto;width:auto;padding:10px 14px" onclick="startCam()">
          <i class="fas fa-camera"></i> ছবি
        </button>
      </div>

      <div class="cam-area" id="camArea">
        <video id="camVid" autoplay playsinline></video>
        <img id="camThumb" style="width:100%;border-radius:9px;display:none;border:2px solid var(--green);object-fit:cover">
        <div style="display:flex;gap:8px;margin-top:8px">
          <button type="button" id="capBtn" onclick="capSnapEntry()" class="btn-cap" style="flex:1">📸 ক্যাপচার</button>
          <button type="button" id="retakeBtn" onclick="retake()" class="btn-cap" style="flex:1;background:var(--amber);box-shadow:0 3px 0 var(--amber2);display:none">🔄 আবার</button>
        </div>
        <canvas id="camCanvas" style="display:none"></canvas>
      </div>

      <div class="stock-badge" id="stockBadge"><i class="fas fa-check-circle"></i> স্টক পেজেও স্বয়ংক্রিয়ভাবে যোগ হয়েছে</div>

      <button type="submit" id="btnSave" class="btn-save bs-green">
        <i class="fas fa-check-circle"></i> <span id="btnSaveTxt">সেভ করুন</span>
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     HISTORY TABLE (Collapsible)
═══════════════════════════════════════════ -->
<div class="sk-coll" id="sec-history">
  <div class="sk-coll-hdr">
    <i class="fas fa-history" style="color:var(--amber)"></i>
    লেনদেন হিস্ট্রি &mdash; <?php echo count($list); ?> টি
  </div>

  <?php if (empty($list)): ?>
    <div class="sk-empty"><i class="fas fa-inbox"></i>এখনো কোনো লেনদেন নেই।</div>
  <?php else: ?>
  <div class="tbl-wrap">
    <table class="sk-table">
      <thead>
        <tr>
          <th>তারিখ</th>
          <th>মেমো</th>
          <th>পিস</th>
          <th>বিল (+)</th>
          <th>জমা (−)</th>
          <th>ACT</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($list as $t):
        $tPhoto   = (string)($t['photo'] ?? '');
        $tPhotoOk = $tPhoto !== '' && file_exists(SK_ROOT.'/'.$tPhoto);
      ?>
      <tr id="tr-row-<?php echo (int)$t['id']; ?>">
        <td class="td-date"><?php echo date('d M y',strtotime((string)$t['tr_date'])); ?></td>
        <td class="td-memo"><?php echo htmlspecialchars((string)$t['memo_no'],ENT_QUOTES,'UTF-8'); ?></td>
        <td class="td-pcs"><?php echo (int)$t['pcs'] > 0 ? (int)$t['pcs'] : '—'; ?></td>
        <td class="td-bill"><?php echo (float)$t['bill_received'] > 0 ? '৳'.number_format((float)$t['bill_received']) : '—'; ?></td>
        <td class="td-pay"><?php echo (float)$t['payment_given'] > 0 ? '৳'.number_format((float)$t['payment_given']) : '—'; ?></td>
        <td>
          <?php if ($tPhotoOk): ?>
            <img src="/<?php echo htmlspecialchars($tPhoto,ENT_QUOTES,'UTF-8'); ?>" class="memo-img" onclick="viewImg('/<?php echo htmlspecialchars($tPhoto,ENT_QUOTES,'UTF-8'); ?>')">
          <?php endif; ?>
          <span class="by-chip"><?php echo htmlspecialchars((string)($t['entry_by']??'—'),ENT_QUOTES,'UTF-8'); ?></span>
          <div class="tr-acts">
            <?php if ($canSms): ?>
            <button type="button" onclick="sendSms(<?php echo (int)$t['id']; ?>,this)" class="tr-sms-send" title="SMS পাঠান" style="background:rgba(34,197,94,.16);color:#22c55e;border:none;border-radius:7px;width:30px;height:30px;cursor:pointer"><i class="fas fa-paper-plane"></i></button>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
            <button type="button"
                    onclick="openEditTr(this)"
                    data-id="<?php echo (int)$t['id']; ?>"
                    data-memo="<?php echo htmlspecialchars((string)$t['memo_no'],ENT_QUOTES,'UTF-8'); ?>"
                    data-pcs="<?php echo (int)$t['pcs']; ?>"
                    data-bill="<?php echo (float)$t['bill_received']; ?>"
                    data-pay="<?php echo (float)$t['payment_given']; ?>"
                    data-dt="<?php echo htmlspecialchars(date('Y-m-d',strtotime((string)$t['tr_date'])),ENT_QUOTES); ?>"
                    class="tr-edit-btn" title="এডিট করুন"
                    style="background:rgba(245,158,11,.16);color:#f59e0b;border:none;border-radius:7px;width:30px;height:30px;cursor:pointer">
              <i class="fas fa-pen"></i>
            </button>
            <button type="button" onclick="askDel(<?php echo (int)$t['id']; ?>)"><i class="fas fa-trash-alt"></i></button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr class="ob-row">
        <td colspan="3" class="ob-lbl">Opening Balance:</td>
        <td class="td-bill"><?php echo (float)$s['opening_balance'] >= 0 ? '৳'.number_format((float)$s['opening_balance']) : '—'; ?></td>
        <td class="td-pay"><?php echo (float)$s['opening_balance'] < 0 ? '৳'.number_format(abs((float)$s['opening_balance'])) : '—'; ?></td>
        <td><span class="sys-tag">SYS</span></td>
      </tr>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- bottom spacing -->
<div style="height:10px"></div>

<!-- ═══════════════════════════════════════════
     BOTTOM SUMMARY BAR
═══════════════════════════════════════════ -->
<div class="slim-sum">
  <div class="slim-box slim-bill">
    মোট বিল
    <span class="slim-val">৳<?php echo number_format($period_bill); ?></span>
  </div>
  <div class="slim-box slim-pay">
    মোট জমা
    <span class="slim-val">৳<?php echo number_format($period_pay); ?></span>
  </div>
  <div class="slim-box slim-due">
    নিট বাকি
    <span class="slim-val">৳<?php echo number_format($net_due); ?></span>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     IMAGE VIEWER MODAL
═══════════════════════════════════════════ -->
<div class="img-modal" id="imgModal" onclick="this.style.display='none'">
  <button class="img-modal-close" onclick="document.getElementById('imgModal').style.display='none'">
    <i class="fas fa-times"></i>
  </button>
  <img id="imgFull" src="" alt="">
</div>

<!-- ═══════════════════════════════════════════
     DELETE TRANSACTION MODAL
═══════════════════════════════════════════ -->
<?php if ($role === 'admin'): ?>
<div class="sk-modal center-modal" id="authModal">
  <div class="sk-modal-box">
    <div class="auth-icon"><i class="fas fa-shield-alt" style="font-size:24px;color:var(--red)"></i></div>
    <div style="text-align:center;margin-bottom:14px">
      <div style="font-size:15px;font-weight:900;color:var(--text)">অ্যাডমিন যাচাই</div>
      <div style="font-size:11px;color:var(--text2);margin-top:4px">এন্ট্রি পার্মানেন্টলি ডিলিট হবে</div>
    </div>
    <input type="hidden" id="delTrId">
    <div class="pass-wrap">
      <i class="fas fa-lock"></i>
      <input type="password" id="passInp" class="sk-inp pass-inp" placeholder="অ্যাডমিন পাসওয়ার্ড" autocomplete="current-password">
    </div>
    <div id="passMsg" class="pm"></div>
    <button type="button" id="delBtn" class="btn-save bs-red" onclick="confirmDelTr()">
      <i class="fas fa-trash-alt"></i> ডিলিট করুন
    </button>
    <button type="button" class="btn-save" style="margin-top:6px;background:var(--card2);color:var(--text2);box-shadow:none" onclick="closeAuth()">বাতিল</button>
  </div>
</div>
<?php endif; ?>

<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>
(function () {
'use strict';

var gCsrf      = "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8"); ?>";
var supplierId = <?php echo $id; ?>;
var isAdmin    = <?php echo ($role === 'admin') ? 'true' : 'false'; ?>;
var canSendSms = <?php echo $canSms ? 'true' : 'false'; ?>;
var role       = "<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>";

/* ── Theme ── */
function applyTheme(t) {
    var ic = document.getElementById('profThemeIcon');
    if (t === 'light') {
        document.body.classList.add('light-mode');
        if (ic) { ic.classList.remove('fa-moon'); ic.classList.add('fa-sun'); }
    } else {
        document.body.classList.remove('light-mode');
        if (ic) { ic.classList.remove('fa-sun'); ic.classList.add('fa-moon'); }
    }
}
function toggleTheme() {
    var t = localStorage.getItem('sk_theme') === 'light' ? 'dark' : 'light';
    localStorage.setItem('sk_theme', t);
    applyTheme(t);
}
window.toggleTheme = toggleTheme;
applyTheme(localStorage.getItem('sk_theme') || 'dark');

/* ── Section Toggle ── */
function toggleSection(key) {
    var el = document.getElementById('sec-' + key); if (!el) return;
    var open = el.classList.contains('open');
    document.querySelectorAll('.sk-coll').forEach(function (c) { c.classList.remove('open'); });
    if (!open) {
        el.classList.add('open');
        setTimeout(function () {
            var top = el.getBoundingClientRect().top + window.pageYOffset - 10;
            window.scrollTo({ top: top, behavior: 'smooth' });
        }, 60);
    }
}
window.toggleSection = toggleSection;

/* ── হিস্ট্রি WhatsApp-এ শেয়ার ── */
var waPhone = "<?php echo htmlspecialchars($cln_phn, ENT_QUOTES); ?>";
var waText  = <?php echo json_encode($waShareText, JSON_UNESCAPED_UNICODE); ?>;
function shareHistoryWA() {
    var base = waPhone ? ('https://wa.me/' + waPhone) : 'https://wa.me/';
    window.open(base + '?text=' + encodeURIComponent(waText), '_blank');
}
window.shareHistoryWA = shareHistoryWA;

/* ── Toast ── */
function skToast(msg, ok) {
    var t = document.getElementById('skToast');
    if (!t) { t = document.createElement('div'); t.id = 'skToast'; document.body.appendChild(t); }
    t.textContent = msg;
    t.style.cssText = 'position:fixed;left:50%;bottom:26px;transform:translateX(-50%) translateY(0);z-index:99999;'
        + 'padding:12px 20px;border-radius:12px;font-size:13px;font-weight:800;max-width:88%;text-align:center;'
        + 'box-shadow:0 10px 30px rgba(0,0,0,.4);transition:opacity .3s,transform .3s;opacity:1;'
        + 'color:#fff;background:' + (ok ? 'linear-gradient(135deg,#16a34a,#22c55e)' : 'linear-gradient(135deg,#dc2626,#ef4444)');
    clearTimeout(window.__skT);
    window.__skT = setTimeout(function () { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(10px)'; }, 3200);
}
window.skToast = skToast;

/* ── Send SMS for a transaction (MiMSMS API) ── */
async function sendSms(trId, btn) {
    if (!canSendSms || !btn) return;
    if (!confirm('এই লেনদেনের তথ্য সাপ্লায়ারকে SMS করবেন?')) return;
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch spin"></i>';
    try {
        var fd = new FormData();
        fd.append('ajax_action', 'send_sms');
        fd.append('tr_id', trId);
        fd.append('id', supplierId);
        fd.append('csrf_token', gCsrf);
        var res  = await fetch('/Suppliers/Ajax/profile_actions.php?id=' + supplierId, { method: 'POST', body: fd });
        var data = await res.json();
        if (data.ok) {
            btn.innerHTML = '<i class="fas fa-check"></i>';
            btn.style.background = 'rgba(34,197,94,.28)';
            skToast('✅ SMS পাঠানো হয়েছে', true);
            setTimeout(function () { btn.innerHTML = orig; btn.disabled = false; }, 2600);
        } else {
            btn.innerHTML = orig; btn.disabled = false;
            skToast('❌ ' + (data.msg || 'SMS পাঠানো যায়নি'), false);
        }
    } catch (e) {
        btn.innerHTML = orig; btn.disabled = false;
        skToast('নেটওয়ার্ক সমস্যা!', false);
    }
}
window.sendSms = sendSms;

/* ── Per-supplier SMS on/off toggle ── */
function toggleSupSms(btn) {
    if (!btn) return;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('ajax_toggle_sms', '1');
    fd.append('sms_id', supplierId);
    fd.append('csrf_token', gCsrf);
    fetch('/Suppliers/Ajax/supplier_actions.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            btn.disabled = false;
            if (!d.ok) { skToast(d.msg || 'এরর!', false); return; }
            var on  = d.enabled === 1;
            var lbl = document.getElementById('smsToggleLbl');
            btn.dataset.on = on ? '1' : '0';
            btn.style.background = on ? 'linear-gradient(135deg,#16a34a,#22c55e)' : '#3a4252';
            if (lbl) lbl.textContent = 'SMS ' + (on ? 'চালু' : 'বন্ধ');
            skToast(on ? 'SMS চালু করা হলো' : 'SMS বন্ধ করা হলো', on);
        })
        .catch(function () { btn.disabled = false; skToast('নেটওয়ার্ক সমস্যা!', false); });
}
window.toggleSupSms = toggleSupSms;

/* ── Image Viewer ── */
function viewImg(src) {
    var m = document.getElementById('imgModal'), img = document.getElementById('imgFull');
    if (!m || !img) return; img.src = src; m.style.display = 'flex';
}
window.viewImg = viewImg;

/* ── Camera (Entry Form) ── */
var camStream = null;
async function startCam() {
    try {
        if (camStream) camStream.getTracks().forEach(function (t) { t.stop(); });
        var area = document.getElementById('camArea'), vid = document.getElementById('camVid');
        var thumb = document.getElementById('camThumb'), cap = document.getElementById('capBtn'), retk = document.getElementById('retakeBtn');
        if (!area) return;
        area.style.display = 'block';
        if (thumb) thumb.style.display = 'none';
        if (vid)   vid.style.display   = 'block';
        if (cap)   cap.style.display   = 'block';
        if (retk)  retk.style.display  = 'none';
        camStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        if (vid) { vid.srcObject = camStream; vid.play(); }
    } catch (e) { alert('ক্যামেরা পারমিশন দিন!'); }
}
window.startCam = startCam;

function capSnapEntry() {
    var v = document.getElementById('camVid'), c = document.getElementById('camCanvas'); if (!v || !c) return;
    var mW = 800, w = v.videoWidth, h = v.videoHeight;
    if (w > mW) { h = Math.floor(h * (mW / w)); w = mW; }
    c.width = w; c.height = h; c.getContext('2d').drawImage(v, 0, 0, w, h);
    var b64    = c.toDataURL('image/jpeg', .72);
    var photo  = document.getElementById('tPhoto');
    var thumb  = document.getElementById('camThumb');
    var capBtn = document.getElementById('capBtn');
    var retk   = document.getElementById('retakeBtn');
    if (photo) photo.value = b64;
    if (thumb) { thumb.src = b64; thumb.style.display = 'block'; }
    if (document.getElementById('camVid')) document.getElementById('camVid').style.display = 'none';
    if (capBtn) capBtn.style.display = 'none';
    if (retk)   retk.style.display  = 'block';
    if (camStream) camStream.getTracks().forEach(function (t) { t.stop(); });
}
window.capSnapEntry = capSnapEntry;

function retake() {
    var thumb = document.getElementById('camThumb'), photo = document.getElementById('tPhoto'), retk = document.getElementById('retakeBtn');
    if (thumb) thumb.style.display = 'none';
    if (photo) photo.value = '';
    if (retk)  retk.style.display = 'none';
    startCam();
}
window.retake = retake;

/* ── Submit Transaction ── */
async function submitTr(e) {
    e.preventDefault();
    var bill = parseFloat(document.getElementById('fBill').value) || 0;
    var pay  = parseFloat(document.getElementById('fPay').value)  || 0;
    var pcs  = parseInt(document.getElementById('fPcs').value)    || 0;
    var memo = (document.getElementById('fMemo').value || '').trim();

    if (bill <= 0 && pay <= 0) { alert('বিল অথবা জমা — যেকোনো একটি লিখুন!'); return; }
    if (bill > 0 && pcs <= 0)  { alert('বিলের ক্ষেত্রে পিস বাধ্যতামূলক।');    return; }
    if (bill > 0 && !memo)     { alert('মেমো নম্বর দিন।');                      return; }

    var btn = document.getElementById('btnSave'); if (!btn) return;
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-circle-notch spin"></i> সেভ হচ্ছে...';
    btn.disabled  = true;

    try {
        var fd  = new FormData(document.getElementById('trForm'));
        var res = await fetch('/Suppliers/Ajax/profile_actions.php?id=' + supplierId, { method: 'POST', body: fd });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        var data = await res.json();
        if (data.ok) {
            var badge = document.getElementById('stockBadge');
            if (data.stock_updated && badge) { badge.classList.add('show'); setTimeout(function () { badge.classList.remove('show'); }, 3000); }
            setTimeout(function () { location.reload(); }, 1100);
        } else {
            alert('সমস্যা: ' + (data.msg || 'Unknown error'));
            btn.innerHTML = orig; btn.disabled = false;
        }
    } catch (err) {
        alert('নেটওয়ার্ক সমস্যা! আবার চেষ্টা করুন।');
        btn.innerHTML = orig; btn.disabled = false;
    }
}
window.submitTr = submitTr;

/* ── এন্ট্রি ফর্ম: Add ↔ Edit মোড ── */
function setEntryMode(mode, tr) {
    var save  = document.getElementById('ajaxSaveFlag') || document.querySelector('#trForm [name="ajax_save_tr"]');
    var actEl = document.getElementById('fAjaxAction');
    var idEl  = document.getElementById('fEditTrId');
    var hdr   = document.getElementById('entryHdrTxt');
    var btnTx = document.getElementById('btnSaveTxt');
    var saveFlag = document.querySelector('#trForm [name="ajax_save_tr"]');

    if (mode === 'edit' && tr) {
        if (saveFlag) saveFlag.disabled = true;       // add ফ্ল্যাগ বন্ধ
        if (actEl) { actEl.disabled = false; actEl.value = 'edit_tr'; }
        if (idEl)  { idEl.disabled = false;  idEl.value = String(tr.id); }
        if (hdr)   hdr.textContent = 'এন্ট্রি এডিট (মেমো ' + (tr.memo || '-') + ')';
        if (btnTx) btnTx.textContent = 'আপডেট করুন';
    } else {
        if (saveFlag) saveFlag.disabled = false;
        if (actEl) { actEl.disabled = true; actEl.value = ''; }
        if (idEl)  { idEl.disabled = true;  idEl.value = ''; }
        if (hdr)   hdr.textContent = 'নতুন এন্ট্রি';
        if (btnTx) btnTx.textContent = 'সেভ করুন';
    }
}

function openEditTr(btn) {
    if (!isAdmin) return;
    var tr = {
        id:   btn.getAttribute('data-id'),
        memo: btn.getAttribute('data-memo'),
        pcs:  btn.getAttribute('data-pcs'),
        bill: parseFloat(btn.getAttribute('data-bill')) || 0,
        pay:  parseFloat(btn.getAttribute('data-pay'))  || 0,
        dt:   btn.getAttribute('data-dt')
    };
    // ফর্ম পূরণ
    document.getElementById('fMemo').value = tr.memo || '';
    document.getElementById('fPcs').value  = parseInt(tr.pcs) > 0 ? tr.pcs : '';
    document.getElementById('fBill').value = tr.bill > 0 ? tr.bill : '';
    document.getElementById('fPay').value  = tr.pay  > 0 ? tr.pay  : '';
    var dtEl = document.getElementById('fDt'); if (dtEl && tr.dt) dtEl.value = tr.dt;
    var ph = document.getElementById('tPhoto'); if (ph) ph.value = '';   // নতুন ছবি না দিলে আগেরটাই থাকবে

    setEntryMode('edit', tr);

    // এন্ট্রি সেকশন খুলে উপরে স্ক্রল
    var sec = document.getElementById('sec-entry');
    document.querySelectorAll('.sk-coll').forEach(function (c) { c.classList.remove('open'); });
    if (sec) {
        sec.classList.add('open');
        setTimeout(function () {
            var top = sec.getBoundingClientRect().top + window.pageYOffset - 10;
            window.scrollTo({ top: top, behavior: 'smooth' });
        }, 60);
    }
}
window.openEditTr = openEditTr;
window.setEntryMode = setEntryMode;

/* ── Delete Transaction ── */
var pendingTrId = null;
function askDel(trId) {
    if (!isAdmin) return;
    pendingTrId = trId;
    var pi = document.getElementById('passInp'), pm = document.getElementById('passMsg'), m = document.getElementById('authModal');
    if (!m) return;
    if (pi) pi.value = ''; if (pm) { pm.textContent = ''; pm.className = 'pm'; }
    m.style.display = 'flex';
    setTimeout(function () { if (pi) pi.focus(); }, 120);
}
window.askDel = askDel;
function closeAuth() { var m = document.getElementById('authModal'); if (m) m.style.display = 'none'; pendingTrId = null; }
window.closeAuth = closeAuth;
async function confirmDelTr() {
    if (!pendingTrId) return;
    var pi = document.getElementById('passInp'), pm = document.getElementById('passMsg'), btn = document.getElementById('delBtn');
    if (!pi) return;
    var pass = pi.value.trim();
    if (!pass) { showPm('পাসওয়ার্ড দিন।', 'err'); return; }
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch spin"></i> যাচাই হচ্ছে...'; }
    try {
        var fd = new FormData();
        fd.append('ajax_action', 'verify_delete_tr');
        fd.append('tr_id', pendingTrId);
        fd.append('id', supplierId);
        fd.append('password', pass);
        fd.append('csrf_token', gCsrf);
        var res = await fetch('/Suppliers/Ajax/profile_actions.php?id=' + supplierId, { method: 'POST', body: fd });
        var data = await res.json();
        if (data.ok) {
            showPm('✅ ' + data.msg, 'ok');
            setTimeout(function () { var row = document.getElementById('tr-row-' + pendingTrId); if (row) row.remove(); closeAuth(); location.reload(); }, 1000);
        } else {
            showPm('❌ ' + (data.msg || 'Error'), 'err');
            if (pi) { pi.value = ''; pi.focus(); }
        }
    } catch (err) { showPm('নেটওয়ার্ক সমস্যা!', 'err'); }
    finally { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash-alt"></i> ডিলিট করুন'; } }
}
window.confirmDelTr = confirmDelTr;
function showPm(msg, type) {
    var el = document.getElementById('passMsg'); if (!el) return;
    el.textContent = msg; el.className = 'pm ' + (type === 'ok' ? 'pm-ok' : 'pm-err');
}
document.addEventListener('DOMContentLoaded', function () {
    var pi = document.getElementById('passInp');
    if (pi) pi.addEventListener('keydown', function (e) { if (e.key === 'Enter') confirmDelTr(); });
});

})();
</script>

</body>
</html>
