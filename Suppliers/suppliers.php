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

$controller = new SupplierController($conn);
$controller->handleSuppliersListRequests();
$viewData = $controller->getSuppliersListData();

$role              = $viewData['role'];
$username          = $viewData['username'];
$activeSuppliers   = $viewData['activeSuppliers'];
$inactiveSuppliers = $viewData['inactiveSuppliers'];
$total_payable     = $viewData['total_payable'];
$total_active      = $viewData['total_active'];
$total_inactive    = $viewData['total_inactive'];

$totalBillEver = 0;
foreach ($activeSuppliers as $sItem) $totalBillEver += (float)$sItem['total_bill'];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>সাপ্লায়ার — Sada Kalo Fashion</title>
<link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
<link href="/assets/css/sk-supplier.css" rel="stylesheet">
<script>(function(){var t=localStorage.getItem('sk_theme');if(t==='light')document.documentElement.setAttribute('data-theme','light');})()</script>
</head>
<body>

<!-- ═══════════════════════════════════════════
     SIDEBAR (Offcanvas)
═══════════════════════════════════════════ -->
<div class="offcanvas offcanvas-start sk-sidebar" tabindex="-1" id="appSidebar">
  <div class="offcanvas-header sidebar-hdr">
    <div class="sidebar-brand">
      <div class="sidebar-brand-icon"><i class="fas fa-truck"></i></div>
      <div>
        <div class="sidebar-brand-name">Sada Kalo Fashion</div>
        <div class="sidebar-brand-sub">Supplier Manager</div>
      </div>
    </div>
    <button class="btn-sidebar-close" data-bs-dismiss="offcanvas"><i class="fas fa-times"></i></button>
  </div>
  <div class="offcanvas-body d-flex flex-column p-0">
    <div class="sidebar-user">
      <div class="user-ava"><?php echo mb_strtoupper(mb_substr($username,0,1,'UTF-8'),'UTF-8'); ?></div>
      <div>
        <div class="user-nm"><?php echo htmlspecialchars($username); ?></div>
        <div class="user-rl"><?php echo htmlspecialchars($role); ?></div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <a href="/dashboard.php" class="sk-nav-item"><i class="fas fa-home"></i> হোম ড্যাশবোর্ড</a>
      <a href="/Suppliers/suppliers.php" class="sk-nav-item active"><i class="fas fa-truck"></i> সাপ্লায়ার</a>
      <a href="/Customer/customers.php" class="sk-nav-item"><i class="fas fa-users"></i> কাস্টমার</a>
      <a href="/shop_@invantory/inventory_dashboard.php" class="sk-nav-item"><i class="fas fa-boxes"></i> INVENTORY</a>
      <a href="/cash_sale.php" class="sk-nav-item"><i class="fas fa-chart-line"></i> সেলস</a>
      <hr class="sidebar-sep">
      <?php if ($role === 'admin'): ?>
      <a href="/Suppliers/Sms/" class="sk-nav-item"><i class="fas fa-comment-sms"></i> SMS সেটিংস</a>
      <a href="/settings.php"  class="sk-nav-item"><i class="fas fa-cog"></i> সেটিংস</a>
      <?php endif; ?>
      <a href="/logout.php"    class="sk-nav-item" style="color:var(--red)"><i class="fas fa-sign-out-alt"></i> লগআউট</a>
    </nav>
    <div class="sidebar-footer mt-auto">
      <button class="btn-theme-sb" onclick="toggleTheme()">
        <i class="fas fa-moon" id="sbThemeIcon"></i>
        <span id="sbThemeText">ডার্ক মোড চালু</span>
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     TICKER
═══════════════════════════════════════════ -->
<div class="sk-ticker">
  <div class="sk-ticker-inner">
    🌿 بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ &nbsp;•&nbsp; পরম করুণাময় আল্লাহর নামে শুরু করছি &nbsp;•&nbsp; আজ: <?php echo date('d M Y'); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
  </div>
</div>

<!-- ═══════════════════════════════════════════
     NAVBAR
═══════════════════════════════════════════ -->
<div class="sk-navbar">
  <button class="btn-app btn-menu" data-bs-toggle="offcanvas" data-bs-target="#appSidebar">
    <i class="fas fa-bars"></i>
  </button>
  <div class="payable-box">
    <div class="lbl">মোট পরিশোধযোগ্য</div>
    <div class="val">Tk. <?php echo number_format($total_payable); ?></div>
  </div>
  <button class="btn-app btn-theme-nav" onclick="toggleTheme()" title="থিম পরিবর্তন">
    <i class="fas fa-moon" id="navThemeIcon"></i>
  </button>
  <button class="btn-app btn-search" onclick="toggleSearch()" title="খুঁজুন">
    <i class="fas fa-search"></i>
  </button>
  <?php if (in_array($role, ['admin','manager'], true)): ?>
  <button class="btn-app btn-add-nav" onclick="openAddModal()" title="নতুন সাপ্লায়ার">
    <i class="fas fa-plus"></i>
  </button>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════
     DASHBOARD BANNER
═══════════════════════════════════════════ -->
<div class="sk-dashboard">
  <div class="dash-title"><i class="fas fa-truck-fast"></i> Supplier Dashboard</div>
  <div class="dash-stats">
    <div class="dash-stat">
      <div class="sv sv-y"><?php echo $total_active; ?></div>
      <div class="sl">সক্রিয়</div>
    </div>
    <div class="dash-stat">
      <div class="sv sv-r"><?php echo number_format($total_payable); ?></div>
      <div class="sl">পরিশোধযোগ্য</div>
    </div>
    <div class="dash-stat">
      <div class="sv sv-g"><?php echo number_format($totalBillEver); ?></div>
      <div class="sl">মোট বিল</div>
    </div>
    <div class="dash-stat">
      <div class="sv sv-p"><?php echo $total_inactive; ?></div>
      <div class="sl">ইনঅ্যাক্টিভ</div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     VALIDATION ERROR BANNER (from add-supplier redirect)
═══════════════════════════════════════════ -->
<?php if (!empty($_GET['error'])): ?>
<div class="sk-alert sk-alert-err" style="margin:10px;padding:12px 16px;border-radius:10px;background:rgba(239,68,68,.14);color:#ef4444;border:1px solid rgba(239,68,68,.35);font-size:13px;font-weight:700">
  <i class="fas fa-triangle-exclamation"></i>
  <?php echo htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     SEARCH BOX
═══════════════════════════════════════════ -->
<div class="sk-search-wrap" id="srchWrap">
  <div class="sk-search-group">
    <i class="fas fa-search"></i>
    <input type="text" id="srchInp" class="sk-search-inp" placeholder="সাপ্লায়ারের নাম বা দোকান খুঁজুন..." oninput="doSearch()">
  </div>
</div>

<!-- ═══════════════════════════════════════════
     ACTIVE SUPPLIERS
═══════════════════════════════════════════ -->
<div class="sk-sec-hdr">
  <div class="sk-sec-title"><i class="fas fa-truck"></i> সক্রিয় সাপ্লায়ার</div>
  <span class="sk-badge"><?php echo $total_active; ?> জন</span>
</div>

<div class="cards-wrap" id="activeWrap">
  <?php if (empty($activeSuppliers)): ?>
    <div class="sk-empty"><i class="fas fa-truck"></i>কোনো সক্রিয় সাপ্লায়ার নেই।</div>
  <?php endif; ?>

  <?php foreach ($activeSuppliers as $s):
    $due      = ((float)$s['opening_balance'] + (float)$s['total_bill']) - (float)$s['total_pay'];
    $hue      = ($s['id'] * 55) % 360;
    $letter   = mb_substr((string)$s['shop_name'], 0, 1, 'UTF-8');
    $dueClass = $due > 0 ? 'due-red' : ($due < 0 ? 'due-grn' : 'due-zero');
    $lastAct  = !empty($s['last_tr_date']) ? date('d M Y', strtotime((string)$s['last_tr_date'])) : 'এন্ট্রি নেই';
    $cPhone   = preg_replace('/[^0-9]/', '', (string)$s['phone']);
    if (substr($cPhone,0,2) !== '88') $cPhone = '88'.$cPhone;
    $shopEsc  = htmlspecialchars((string)$s['shop_name'], ENT_QUOTES, 'UTF-8');
    $hasPhoto = !empty($s['photo']) && file_exists(SK_ROOT.'/'.$s['photo']);
  ?>
  <div class="sk-card"
       data-search="<?php echo htmlspecialchars(strtolower($s['shop_name'].' '.$s['name']), ENT_QUOTES); ?>"
       data-id="<?php echo (int)$s['id']; ?>"
       data-shop="<?php echo $shopEsc; ?>"
       data-name="<?php echo htmlspecialchars((string)$s['name'], ENT_QUOTES); ?>"
       data-phone="<?php echo htmlspecialchars((string)$s['phone'], ENT_QUOTES); ?>"
       data-email="<?php echo htmlspecialchars((string)($s['email']??''), ENT_QUOTES); ?>"
       data-address="<?php echo htmlspecialchars((string)($s['address']??''), ENT_QUOTES); ?>"
       data-sms="<?php echo (int)($s['sms_enabled'] ?? 1); ?>"
       data-opening="<?php echo (float)$s['opening_balance']; ?>">
    <div class="sk-card-main" onclick="toggleActs(<?php echo (int)$s['id']; ?>)">
      <?php if ($hasPhoto): ?>
        <img src="/<?php echo htmlspecialchars((string)$s['photo'],ENT_QUOTES); ?>" class="sk-photo" onerror="this.style.display='none'">
      <?php else: ?>
        <div class="sk-avatar" style="background:hsl(<?php echo $hue;?>,55%,20%);color:hsl(<?php echo $hue;?>,70%,65%);border-color:hsl(<?php echo $hue;?>,55%,35%)"><?php echo htmlspecialchars($letter,ENT_QUOTES); ?></div>
      <?php endif; ?>
      <div class="sk-card-info">
        <div class="sk-shop"><?php echo $shopEsc; ?></div>
        <div class="sk-name"><?php echo htmlspecialchars((string)$s['name'],ENT_QUOTES); ?> | <?php echo htmlspecialchars((string)$s['phone'],ENT_QUOTES); ?></div>
        <div class="sk-last"><i class="fas fa-clock" style="font-size:8px"></i><?php echo $lastAct; ?></div>
      </div>
      <div class="sk-due <?php echo $dueClass; ?>">Tk. <?php echo number_format($due); ?></div>
    </div>
    <div class="sk-card-acts" id="acts-<?php echo (int)$s['id']; ?>" style="display:none">
      <a href="tel:<?php echo htmlspecialchars($cPhone,ENT_QUOTES); ?>" class="act-btn a-call"><i class="fas fa-phone"></i></a>
      <a href="https://wa.me/<?php echo htmlspecialchars($cPhone,ENT_QUOTES); ?>" target="_blank" class="act-btn a-wa"><i class="fab fa-whatsapp"></i></a>
      <a href="supplier_profile.php?id=<?php echo (int)$s['id']; ?>" class="act-btn a-view"><i class="fas fa-arrow-right"></i></a>
      <?php if (in_array($role, ['admin','manager'], true)):
        $smsOn = (int)($s['sms_enabled'] ?? 1) === 1; ?>
      <button type="button" onclick="toggleSms(<?php echo (int)$s['id']; ?>,this)" class="act-btn a-sms"
              data-on="<?php echo $smsOn ? '1':'0'; ?>"
              style="background:<?php echo $smsOn ? 'rgba(34,197,94,.16)':'rgba(148,163,184,.14)'; ?>;color:<?php echo $smsOn ? '#22c55e':'#94a3b8'; ?>"
              title="SMS <?php echo $smsOn ? 'চালু':'বন্ধ'; ?>"><i class="fas fa-comment-sms"></i></button>
      <?php endif; ?>
      <?php if ($role === 'admin'): ?>
      <button type="button" onclick="openEditModal(this.closest('.sk-card'))" class="act-btn a-edit"><i class="fas fa-edit"></i></button>
      <button type="button" onclick="authToggle(<?php echo (int)$s['id']; ?>)" class="act-btn a-tog"><i class="fas fa-toggle-on"></i></button>
      <button type="button" onclick="authDel(<?php echo (int)$s['id']; ?>)" class="act-btn a-del"><i class="fas fa-trash-alt"></i></button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════
     INACTIVE SUPPLIERS
═══════════════════════════════════════════ -->
<?php if ($role === 'admin' && $total_inactive > 0): ?>
<div style="padding:8px 10px 0">
  <div class="sk-inactive-toggle" onclick="toggleInactive()">
    <div class="it-left">
      <i class="fas fa-user-slash"></i>
      ইনঅ্যাক্টিভ সাপ্লায়ার দেখুন
      <span class="it-badge"><?php echo $total_inactive; ?></span>
    </div>
    <i class="fas fa-chevron-down it-arrow" id="inactiveArrow"></i>
  </div>
</div>
<div class="cards-wrap" id="inactiveWrap" style="display:none;margin-top:6px">
  <?php foreach ($inactiveSuppliers as $s):
    $due = ((float)$s['opening_balance'] + (float)$s['total_bill']) - (float)$s['total_pay'];
    $hasPhotoIn = !empty($s['photo']) && file_exists(SK_ROOT.'/'.$s['photo']);
  ?>
  <div class="sk-card sk-inactive"
       data-id="<?php echo (int)$s['id']; ?>"
       data-shop="<?php echo htmlspecialchars((string)$s['shop_name'],ENT_QUOTES); ?>"
       data-name="<?php echo htmlspecialchars((string)$s['name'],ENT_QUOTES); ?>"
       data-phone="<?php echo htmlspecialchars((string)$s['phone'],ENT_QUOTES); ?>"
       data-email="<?php echo htmlspecialchars((string)($s['email']??''),ENT_QUOTES); ?>"
       data-address="<?php echo htmlspecialchars((string)($s['address']??''),ENT_QUOTES); ?>"
       data-opening="<?php echo (float)$s['opening_balance']; ?>">
    <div class="sk-card-main" onclick="toggleActs('i<?php echo (int)$s['id']; ?>')">
      <?php if ($hasPhotoIn): ?>
        <img src="/<?php echo htmlspecialchars((string)$s['photo'],ENT_QUOTES); ?>" class="sk-photo" style="filter:grayscale(1)" onerror="this.style.display='none'">
      <?php else: ?>
        <div class="sk-avatar" style="background:var(--card2);color:var(--text3);border-color:var(--border);font-size:16px"><i class="fas fa-user-slash"></i></div>
      <?php endif; ?>
      <div class="sk-card-info">
        <div class="sk-shop"><?php echo htmlspecialchars((string)$s['shop_name'],ENT_QUOTES); ?> <span class="inact-badge">INACTIVE</span></div>
        <div class="sk-name"><?php echo htmlspecialchars((string)$s['name'],ENT_QUOTES); ?></div>
      </div>
      <div class="sk-due due-zero">Tk. <?php echo number_format($due); ?></div>
    </div>
    <div class="sk-card-acts" id="acts-i<?php echo (int)$s['id']; ?>" style="display:none">
      <button type="button" onclick="authToggle(<?php echo (int)$s['id']; ?>)" class="act-btn a-unban" style="flex:2"><i class="fas fa-check-circle"></i> Active করুন</button>
      <button type="button" onclick="openEditModal(this.closest('.sk-card'))" class="act-btn a-edit"><i class="fas fa-edit"></i></button>
      <button type="button" onclick="authDel(<?php echo (int)$s['id']; ?>)" class="act-btn a-del"><i class="fas fa-trash-alt"></i></button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- bottom spacing -->
<div style="height:16px"></div>

<!-- ═══════════════════════════════════════════
     MODAL: ADD SUPPLIER
═══════════════════════════════════════════ -->
<div class="sk-modal" id="addModal">
  <div class="sk-modal-box">
    <div class="sk-modal-hdr">
      <div class="sk-modal-title t-accent"><i class="fas fa-plus-circle me-2"></i>নতুন সাপ্লায়ার</div>
      <button class="btn-modal-close" onclick="closeModal('addModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8"); ?>">
      <input type="text"   name="shop_name"      class="sk-inp" placeholder="দোকানের নাম *" required>
      <input type="text"   name="name"            class="sk-inp" placeholder="মালিকের নাম *" required>
      <input type="tel"    name="phone"           class="sk-inp" placeholder="মোবাইল নম্বর *" required>
      <input type="email"  name="email"           class="sk-inp" placeholder="ইমেইল (ঐচ্ছিক)">
      <textarea            name="address"         class="sk-inp" placeholder="ঠিকানা" rows="2"></textarea>
      <input type="number" name="opening_balance" class="sk-inp" placeholder="Opening Balance (টাকা)" step="0.01">
      <div class="cam-btns">
        <button type="button" class="cb cb-cam" onclick="openCam('a')"><i class="fas fa-camera"></i> ক্যামেরা</button>
        <label class="cb cb-gal"><i class="fas fa-image"></i> গ্যালারি<input type="file" accept="image/*" style="display:none" onchange="loadFile(this,'a')"></label>
      </div>
      <div class="cam-area" id="camA">
        <video id="vidA" autoplay playsinline></video>
        <button type="button" class="btn-cap" onclick="capSnap('a')">📸 ক্যাপচার করুন</button>
      </div>
      <img id="prevA" class="prev-img">
      <input type="hidden" name="photo_data" id="photoA">
      <canvas id="canvA" style="display:none"></canvas>
      <button type="submit" name="add_supplier" class="btn-save bs-amber"><i class="fas fa-save"></i> সেভ করুন</button>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL: EDIT SUPPLIER
═══════════════════════════════════════════ -->
<div class="sk-modal" id="editModal">
  <div class="sk-modal-box">
    <div class="sk-modal-hdr">
      <div class="sk-modal-title t-purple"><i class="fas fa-edit me-2"></i>সাপ্লায়ার এডিট</div>
      <button class="btn-modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
    </div>
    <form id="editForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8"); ?>">
      <input type="hidden" name="ajax_edit_supplier" value="1">
      <input type="hidden" name="edit_id" id="editId">
      <input type="text"   name="e_shop_name"       id="eShop"    class="sk-inp" placeholder="দোকানের নাম *" required>
      <input type="text"   name="e_name"             id="eName"    class="sk-inp" placeholder="মালিকের নাম *" required>
      <input type="tel"    name="e_phone"            id="ePhone"   class="sk-inp" placeholder="মোবাইল নম্বর *" required>
      <input type="email"  name="e_email"            id="eEmail"   class="sk-inp" placeholder="ইমেইল">
      <textarea            name="e_address"          id="eAddress" class="sk-inp" placeholder="ঠিকানা" rows="2"></textarea>
      <input type="number" name="e_opening_balance"  id="eOpening" class="sk-inp" placeholder="Opening Balance" step="0.01">
      <div class="cam-btns">
        <button type="button" class="cb cb-cam" onclick="openCam('e')"><i class="fas fa-camera"></i> ক্যামেরা</button>
        <label class="cb cb-gal"><i class="fas fa-image"></i> গ্যালারি<input type="file" accept="image/*" style="display:none" onchange="loadFile(this,'e')"></label>
      </div>
      <div class="cam-area" id="camE">
        <video id="vidE" autoplay playsinline></video>
        <button type="button" class="btn-cap" onclick="capSnap('e')">📸 ক্যাপচার করুন</button>
      </div>
      <img id="prevE" class="prev-img" style="border-color:var(--accent)">
      <input type="hidden" name="e_photo_data" id="photoE">
      <canvas id="canvE" style="display:none"></canvas>
      <button type="button" class="btn-save bs-purple" onclick="saveEdit()"><i class="fas fa-save"></i> আপডেট করুন</button>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     MODAL: DELETE AUTH
═══════════════════════════════════════════ -->
<div class="sk-modal center-modal" id="sAuthModal">
  <div class="sk-modal-box">
    <div class="auth-icon"><i class="fas fa-shield-alt" style="font-size:24px;color:var(--red)"></i></div>
    <div style="text-align:center;margin-bottom:14px">
      <div style="font-size:15px;font-weight:900;color:var(--text)">অ্যাডমিন যাচাই</div>
      <div style="font-size:11px;color:var(--text2);margin-top:4px">পার্মানেন্ট ডিলিটের জন্য পাসওয়ার্ড দিন</div>
    </div>
    <div class="pass-wrap">
      <i class="fas fa-lock"></i>
      <input type="password" id="sPassInput" class="sk-inp pass-inp" placeholder="অ্যাডমিন পাসওয়ার্ড" autocomplete="current-password">
    </div>
    <div id="sPassMsg" class="pm"></div>
    <button type="button" id="sDelBtn" class="btn-save bs-red" onclick="confirmSupDel()"><i class="fas fa-trash-alt"></i> ডিলিট করুন</button>
    <button type="button" class="btn-save bs-purple" style="margin-top:6px;box-shadow:none;background:var(--card2);color:var(--text2)" onclick="closeSAuthModal()">বাতিল</button>
  </div>
</div>

<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>
(function () {
'use strict';

/* ── Theme ── */
var gCsrf = "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8"); ?>";

function applyTheme(t) {
    var body = document.body;
    var ni = document.getElementById('navThemeIcon');
    var si = document.getElementById('sbThemeIcon');
    var st = document.getElementById('sbThemeText');
    if (t === 'light') {
        body.classList.add('light-mode');
        if (ni) { ni.classList.remove('fa-moon'); ni.classList.add('fa-sun'); }
        if (si) { si.classList.remove('fa-moon'); si.classList.add('fa-sun'); }
        if (st) st.textContent = 'লাইট মোড চালু';
    } else {
        body.classList.remove('light-mode');
        if (ni) { ni.classList.remove('fa-sun'); ni.classList.add('fa-moon'); }
        if (si) { si.classList.remove('fa-sun'); si.classList.add('fa-moon'); }
        if (st) st.textContent = 'ডার্ক মোড চালু';
    }
}
function toggleTheme() {
    var t = localStorage.getItem('sk_theme') === 'light' ? 'dark' : 'light';
    localStorage.setItem('sk_theme', t);
    applyTheme(t);
}
window.toggleTheme = toggleTheme;
applyTheme(localStorage.getItem('sk_theme') || 'dark');

/* ── Search ── */
function toggleSearch() {
    var w = document.getElementById('srchWrap');
    if (!w) return;
    w.style.display = w.style.display === 'none' ? 'block' : 'none';
    if (w.style.display === 'block') { var i = document.getElementById('srchInp'); if (i) i.focus(); }
}
window.toggleSearch = toggleSearch;

function doSearch() {
    var q = (document.getElementById('srchInp').value || '').toLowerCase();
    document.querySelectorAll('.sk-card').forEach(function (c) {
        c.style.display = (c.dataset.search || '').includes(q) ? '' : 'none';
    });
}
window.doSearch = doSearch;

/* ── Card Actions ── */
function toggleActs(id) {
    var el = document.getElementById('acts-' + id);
    var card = el ? el.closest('.sk-card') : null;
    var isOpen = el && el.style.display !== 'none';
    document.querySelectorAll('.sk-card-acts').forEach(function (a) { a.style.display = 'none'; });
    document.querySelectorAll('.sk-card').forEach(function (c) { c.classList.remove('expanded'); });
    if (!isOpen && el) { el.style.display = 'flex'; if (card) card.classList.add('expanded'); }
}
window.toggleActs = toggleActs;

/* ── Inactive Toggle ── */
function toggleInactive() {
    var w = document.getElementById('inactiveWrap');
    var a = document.getElementById('inactiveArrow');
    if (!w) return;
    var show = w.style.display === 'none';
    w.style.display = show ? 'flex' : 'none';
    if (show) w.style.flexDirection = 'column';
    if (a) a.classList.toggle('open', show);
}
window.toggleInactive = toggleInactive;

/* ── Auth Toggle ── */
function authToggle(id) {
    if (!confirm('এই সাপ্লায়ারের Status পরিবর্তন করবেন?')) return;
    var fd = new FormData();
    fd.append('ajax_toggle_status', '1');
    fd.append('toggle_id', id);
    fd.append('csrf_token', gCsrf);
    fetch('/Suppliers/Ajax/supplier_actions.php', { method: 'POST', body: fd })
        .then(function (r) { return r.text(); })
        .then(function (t) { if (t === 'error') alert('এরর!'); else location.reload(); })
        .catch(function () { alert('নেটওয়ার্ক সমস্যা!'); });
}
window.authToggle = authToggle;

/* ── SMS on/off Toggle (per supplier) ── */
function toggleSms(id, btn) {
    if (!btn) return;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('ajax_toggle_sms', '1');
    fd.append('sms_id', id);
    fd.append('csrf_token', gCsrf);
    fetch('/Suppliers/Ajax/supplier_actions.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            btn.disabled = false;
            if (!d.ok) { alert(d.msg || 'এরর!'); return; }
            var on = d.enabled === 1;
            btn.dataset.on = on ? '1' : '0';
            btn.style.background = on ? 'rgba(34,197,94,.16)' : 'rgba(148,163,184,.14)';
            btn.style.color = on ? '#22c55e' : '#94a3b8';
            btn.title = 'SMS ' + (on ? 'চালু' : 'বন্ধ');
        })
        .catch(function () { btn.disabled = false; alert('নেটওয়ার্ক সমস্যা!'); });
}
window.toggleSms = toggleSms;

/* ── Delete Auth ── */
var pendingDelSup = null;
function authDel(id) {
    pendingDelSup = id;
    var pi = document.getElementById('sPassInput'), pm = document.getElementById('sPassMsg');
    if (pi) pi.value = ''; if (pm) { pm.textContent = ''; pm.className = 'pm'; }
    var m = document.getElementById('sAuthModal'); if (m) m.style.display = 'flex';
    setTimeout(function () { if (pi) pi.focus(); }, 120);
}
window.authDel = authDel;
function closeSAuthModal() {
    var m = document.getElementById('sAuthModal'); if (m) m.style.display = 'none';
    pendingDelSup = null;
    var pi = document.getElementById('sPassInput'); if (pi) pi.value = '';
}
window.closeSAuthModal = closeSAuthModal;
async function confirmSupDel() {
    if (!pendingDelSup) return;
    var pi = document.getElementById('sPassInput');
    var pass = pi ? pi.value.trim() : '';
    if (!pass) { showSMsg('পাসওয়ার্ড দিন।', 'err'); return; }
    var btn = document.getElementById('sDelBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch spin"></i> যাচাই হচ্ছে...'; }
    try {
        var fd = new FormData();
        fd.append('ajax_action', 'verify_delete');
        fd.append('sid', pendingDelSup);
        fd.append('password', pass);
        fd.append('csrf_token', gCsrf);
        var res = await fetch('/Suppliers/Ajax/supplier_actions.php', { method: 'POST', body: fd });
        var data = await res.json();
        if (data.ok) { showSMsg('✅ ' + data.msg, 'ok'); setTimeout(function () { closeSAuthModal(); location.reload(); }, 1200); }
        else { showSMsg('❌ ' + (data.msg || 'Error'), 'err'); if (pi) { pi.value = ''; pi.focus(); } }
    } catch (e) { showSMsg('নেটওয়ার্ক সমস্যা!', 'err'); }
    finally { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash-alt"></i> ডিলিট করুন'; } }
}
window.confirmSupDel = confirmSupDel;
function showSMsg(msg, type) {
    var el = document.getElementById('sPassMsg'); if (!el) return;
    el.textContent = msg; el.className = 'pm ' + (type === 'ok' ? 'pm-ok' : 'pm-err');
}
document.addEventListener('DOMContentLoaded', function () {
    var pi = document.getElementById('sPassInput');
    if (pi) pi.addEventListener('keydown', function (e) { if (e.key === 'Enter') confirmSupDel(); });
});

/* ── Modals ── */
function openAddModal() { closeAllCams(); var m = document.getElementById('addModal'); if (m) m.style.display = 'flex'; }
window.openAddModal = openAddModal;
function openEditModal(card) {
    if (!card) return; closeAllCams();
    var f = { editId:'edit_id',eShop:'e_shop_name',eName:'e_name',ePhone:'e_phone',eEmail:'e_email',eAddress:'e_address',eOpening:'e_opening_balance' };
    var ids = ['editId','eShop','eName','ePhone','eEmail','eAddress','eOpening'];
    var keys = ['id','shop','name','phone','email','address','opening'];
    ids.forEach(function (id, i) { var el = document.getElementById(id); if (el) el.value = card.dataset[keys[i]] || ''; });
    var pe = document.getElementById('photoE'); if (pe) pe.value = '';
    var pv = document.getElementById('prevE'); if (pv) pv.style.display = 'none';
    var m = document.getElementById('editModal'); if (m) m.style.display = 'flex';
}
window.openEditModal = openEditModal;
function closeModal(id) { var m = document.getElementById(id); if (m) m.style.display = 'none'; closeAllCams(); }
window.closeModal = closeModal;
async function saveEdit() {
    var form = document.getElementById('editForm'); if (!form) return;
    var fd = new FormData(form);
    try {
        var res = await fetch('/Suppliers/Ajax/supplier_actions.php', { method: 'POST', body: fd });
        var text = await res.text();
        if (text.includes('"ok":true') || text.includes('success')) { alert('আপডেট হয়েছে!'); location.reload(); }
        else alert('সমস্যা হয়েছে। আবার চেষ্টা করুন।');
    } catch (e) { alert('নেটওয়ার্ক সমস্যা!'); }
}
window.saveEdit = saveEdit;

/* ── Camera ── */
var camStreams = { a: null, e: null };
async function openCam(k) {
    try {
        var cd = document.getElementById('cam' + k.toUpperCase()); if (cd) cd.style.display = 'block';
        camStreams[k] = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        var vid = document.getElementById('vid' + k.toUpperCase()); if (vid) vid.srcObject = camStreams[k];
    } catch (e) { alert('ক্যামেরা এক্সেস দিন।'); }
}
window.openCam = openCam;
function capSnap(k) {
    var v = document.getElementById('vid' + k.toUpperCase()), c = document.getElementById('canv' + k.toUpperCase()); if (!v || !c) return;
    var mW = 600, w = v.videoWidth, h = v.videoHeight;
    if (w > mW) { h = Math.floor(h * (mW / w)); w = mW; }
    c.width = w; c.height = h; c.getContext('2d').drawImage(v, 0, 0, w, h);
    var b64 = c.toDataURL('image/jpeg', .72);
    var ph = document.getElementById('photo' + k.toUpperCase()), pr = document.getElementById('prev' + k.toUpperCase());
    if (ph) ph.value = b64; if (pr) { pr.src = b64; pr.style.display = 'block'; }
    stopCam(k); var cd = document.getElementById('cam' + k.toUpperCase()); if (cd) cd.style.display = 'none';
}
window.capSnap = capSnap;
function loadFile(input, k) {
    if (!input.files || !input.files[0]) return;
    var r = new FileReader();
    r.onload = function (e) {
        var img = new Image(); img.onload = function () {
            var c = document.getElementById('canv' + k.toUpperCase()); if (!c) return;
            var mW = 600, w = img.width, h = img.height;
            if (w > mW) { h = Math.floor(h * (mW / w)); w = mW; }
            c.width = w; c.height = h; c.getContext('2d').drawImage(img, 0, 0, w, h);
            var b64 = c.toDataURL('image/jpeg', .72);
            var ph = document.getElementById('photo' + k.toUpperCase()), pr = document.getElementById('prev' + k.toUpperCase());
            if (ph) ph.value = b64; if (pr) { pr.src = b64; pr.style.display = 'block'; }
        }; img.src = e.target.result;
    }; r.readAsDataURL(input.files[0]);
}
window.loadFile = loadFile;
function stopCam(k) { if (camStreams[k]) { camStreams[k].getTracks().forEach(function (t) { t.stop(); }); camStreams[k] = null; } }
function closeAllCams() { ['a','e'].forEach(stopCam); }

})();
</script>
</body>
</html>
