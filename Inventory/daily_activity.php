<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: text/html; charset=utf-8');

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    session_unset(); session_destroy(); header("Location: ../index.php"); exit;
}
$_SESSION['last_activity'] = time();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../index.php"); exit; }
include '../db_connect.php';
$role = $_SESSION['role'] ?? 'user';
if ($role !== 'admin') { header("Location: inventory_dashboard.php"); exit; }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
$conn->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
date_default_timezone_set('Asia/Dhaka');

if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'load_data') {
    ob_clean(); header('Content-Type: application/json; charset=utf-8');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { echo json_encode(['error'=>'টোকেন মিসম্যাচ!']); exit; }

    $today = date('Y-m-d');
    $out = [];

    $stC = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE DATE(created_at)=?"); $stC->execute([$today]); $out['new_count']=(int)$stC->fetchColumn();
    $stC = $conn->prepare("SELECT COALESCE(SUM(pieces),0) FROM inventory WHERE DATE(created_at)=?"); $stC->execute([$today]); $out['new_pcs']=(int)$stC->fetchColumn();
    $stC = $conn->prepare("SELECT COUNT(DISTINCT sale_id) FROM inventory_sale_items isi JOIN inventory_sales s ON isi.sale_id = s.id WHERE DATE(s.created_at)=?"); $stC->execute([$today]); $out['sale_count']=(int)$stC->fetchColumn();
    $stC = $conn->prepare("SELECT COALESCE(SUM(isi.pieces),0) FROM inventory_sale_items isi JOIN inventory_sales s ON isi.sale_id = s.id WHERE DATE(s.created_at)=?"); $stC->execute([$today]); $out['sale_pcs']=(int)$stC->fetchColumn();
    $stC = $conn->prepare("SELECT COUNT(*) FROM inventory_returns WHERE DATE(created_at)=?"); $stC->execute([$today]); $out['ret_count']=(int)$stC->fetchColumn();
    try {
        $stC = $conn->prepare("SELECT COUNT(*) FROM inventory_adjustments WHERE DATE(created_at)=? AND adjustment_type='increase'"); $stC->execute([$today]); $out['inc_count']=(int)$stC->fetchColumn();
        $stC = $conn->prepare("SELECT COUNT(*) FROM inventory_adjustments WHERE DATE(created_at)=? AND adjustment_type='decrease'"); $stC->execute([$today]); $out['dec_count']=(int)$stC->fetchColumn();
    } catch(Exception $e) { $out['inc_count']=0; $out['dec_count']=0; }

    function _imgHtml($path, $classes='') {
        $p = !empty($path) ? htmlspecialchars((string)$path, ENT_QUOTES, 'UTF-8') : '';
        if ($p !== '') return "<img src='{$p}' class='ar-img {$classes}' onclick=\"si('{$p}')\">";
        return "<div class='ar-img' style='background:var(--sk-surface-3); display:inline-flex; align-items:center; justify-content:center; color:var(--sk-muted);'><i class='fas fa-image'></i></div>";
    }

    // New items
    $h1 = '';
    try {
        $st = $conn->prepare("SELECT i.*, u.username as eu FROM inventory i LEFT JOIN users u ON i.added_by=u.id WHERE DATE(i.created_at)=? ORDER BY i.id DESC");
        $st->execute([$today]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $code=htmlspecialchars($r['product_code']??'',ENT_QUOTES,'UTF-8');
            $name=htmlspecialchars($r['name']??'',ENT_QUOTES,'UTF-8');
            $eu=htmlspecialchars($r['eu']??'Admin',ENT_QUOTES,'UTF-8');
            $t=date('h:i A', strtotime($r['created_at']));
            $bc=(float)$r['buy_price']+(float)$r['cost']; $cash=(float)$r['cash_sell']; $pcs=(int)$r['pieces'];
            $h1.="<div class='arow'>"._imgHtml($r['image_path']??'')."<div class='ar-body'><div class='ar-code'>{$code}</div><div class='ar-name'>{$name}</div><div class='ar-meta'><i class='fas fa-user-plus'></i> এন্ট্রি: {$eu} &bull; {$t}</div><div class='ar-tags'><span class='atag t-buy'>ইনভেস্ট: ৳{$bc}</span><span class='atag t-cash'>বিক্রি: ৳{$cash}</span></div></div><div class='ar-pcs ar-pcs-add'>+{$pcs}<br><span>পিস</span></div></div>";
        }
        if (!$rows) $h1 = "<div class='empty-row'>আজকে কোনো নতুন পণ্য অ্যাড হয়নি।</div>";
    } catch(Exception $e) { $h1="<div class='empty-row'>ডাটা লোড হয়নি।</div>"; }
    $out['html_new'] = $h1;

    // Sales
    $hs = '';
    try {
        $st = $conn->prepare("SELECT isi.*, s.invoice_no, s.created_at, i.name as pn, i.image_path, u.username as eu FROM inventory_sale_items isi JOIN inventory_sales s ON isi.sale_id = s.id LEFT JOIN inventory i ON isi.product_code = i.product_code LEFT JOIN users u ON s.sold_by = u.id WHERE DATE(s.created_at) = ? ORDER BY s.created_at DESC");
        $st->execute([$today]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $code=htmlspecialchars($r['product_code']??'',ENT_QUOTES,'UTF-8');
            $pn=htmlspecialchars($r['pn']??'—',ENT_QUOTES,'UTF-8');
            $inv=htmlspecialchars($r['invoice_no']??'',ENT_QUOTES,'UTF-8');
            $eu=htmlspecialchars($r['eu']??'Staff',ENT_QUOTES,'UTF-8');
            $t=date('h:i A', strtotime($r['created_at']));
            $sp=(float)$r['sell_price']; $pcs=(int)$r['pieces']; $tb=$sp*$pcs;
            $hs.="<div class='arow'>"._imgHtml($r['image_path']??'')."<div class='ar-body'><div class='ar-code'>{$code}</div><div class='ar-name'>{$pn}</div><div class='ar-inv'>{$inv}</div><div class='ar-meta'><i class='fas fa-user-tag'></i> {$eu} &bull; {$t}</div><div class='ar-tags'><span class='atag t-cash'>রেট: ৳{$sp}</span><span class='atag t-buy'>বিল: ৳{$tb}</span></div></div><div class='ar-pcs ar-pcs-add'>{$pcs}<br><span>পিস</span></div></div>";
        }
        if (!$rows) $hs="<div class='empty-row'>আজকে কোনো বিক্রি হয়নি।</div>";
    } catch(Exception $e) { $hs="<div class='empty-row'>ডাটা লোড হয়নি।</div>"; }
    $out['html_sale'] = $hs;

    // Returns
    $h2 = '';
    try {
        $st = $conn->prepare("SELECT r.*, i.name as pn, i.image_path, u.username as eu FROM inventory_returns r LEFT JOIN inventory i ON r.product_code=i.product_code LEFT JOIN users u ON r.returned_by=u.id WHERE DATE(r.created_at)=? ORDER BY r.id DESC");
        $st->execute([$today]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $code=htmlspecialchars($r['product_code']??'',ENT_QUOTES,'UTF-8');
            $pn=htmlspecialchars($r['pn']??'—',ENT_QUOTES,'UTF-8');
            $inv=htmlspecialchars($r['invoice_no']??'—',ENT_QUOTES,'UTF-8');
            $eu=htmlspecialchars($r['eu']??'Staff',ENT_QUOTES,'UTF-8');
            $note=htmlspecialchars($r['note']??'',ENT_QUOTES,'UTF-8');
            $refund=(float)$r['refund_amount']; $retPcs=(int)$r['return_pieces'];
            $t=date('h:i A', strtotime($r['created_at']));
            $st2 = $r['status']==='approved' ? "<span class='stag st-ok'>✅ অনুমোদিত</span>" : ($r['status']==='rejected' ? "<span class='stag st-rej'>❌ রিজেক্ট</span>" : "<span class='stag st-pend'>⏳ অপেক্ষায়</span>");
            $h2.="<div class='arow'>"._imgHtml($r['image_path']??'')."<div class='ar-body'><div class='ar-code'>{$code}</div><div class='ar-name'>{$pn}</div><div class='ar-inv'>INV: {$inv}</div><div class='ar-meta'><i class='fas fa-user-edit'></i> {$eu} &bull; {$t}</div><div class='ar-meta'>রিফান্ড: <strong>৳{$refund}</strong> &bull; {$note}</div></div><div class='ar-right'><div class='ar-pcs ar-pcs-ret'>-{$retPcs}<br><span>পিস</span></div><div class='mt1'>{$st2}</div></div></div>";
        }
        if (!$rows) $h2 = "<div class='empty-row'>আজকে কোনো রিটার্ন আসেনি।</div>";
    } catch(Exception $e) { $h2="<div class='empty-row'>ডাটা লোড হয়নি।</div>"; }
    $out['html_ret'] = $h2;

    // Increase
    $h3 = '';
    try {
        $st = $conn->prepare("SELECT a.*, i.name as pn, i.image_path, u.username as eu FROM inventory_adjustments a LEFT JOIN inventory i ON a.product_code=i.product_code LEFT JOIN users u ON a.adjusted_by=u.id WHERE DATE(a.created_at)=? AND a.adjustment_type='increase' ORDER BY a.id DESC");
        $st->execute([$today]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $code=htmlspecialchars($r['product_code']??'',ENT_QUOTES,'UTF-8');
            $pn=htmlspecialchars($r['pn']??'—',ENT_QUOTES,'UTF-8');
            $eu=htmlspecialchars($r['eu']??'Staff',ENT_QUOTES,'UTF-8');
            $note=htmlspecialchars($r['note']??'',ENT_QUOTES,'UTF-8');
            $adjPcs=(int)$r['pieces']; $t=date('h:i A', strtotime($r['created_at']));
            $h3.="<div class='arow'>"._imgHtml($r['image_path']??'')."<div class='ar-body'><div class='ar-code'>{$code}</div><div class='ar-name'>{$pn}</div><div class='ar-meta'><i class='fas fa-user-cog'></i> {$eu} &bull; {$t}</div><div class='ar-note'>{$note}</div></div><div class='ar-pcs ar-pcs-inc'>+{$adjPcs}<br><span>পিস</span></div></div>";
        }
        if (!$rows) $h3="<div class='empty-row'>আজকে কোনো স্টক ম্যানুয়ালি বাড়ানো হয়নি।</div>";
    } catch(Exception $e) { $h3="<div class='empty-row'>ডাটা লোড হয়নি।</div>"; }
    $out['html_inc'] = $h3;

    // Decrease
    $h4 = '';
    try {
        $st = $conn->prepare("SELECT a.*, i.name as pn, i.image_path, u.username as eu FROM inventory_adjustments a LEFT JOIN inventory i ON a.product_code=i.product_code LEFT JOIN users u ON a.adjusted_by=u.id WHERE DATE(a.created_at)=? AND a.adjustment_type='decrease' ORDER BY a.id DESC");
        $st->execute([$today]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $code=htmlspecialchars($r['product_code']??'',ENT_QUOTES,'UTF-8');
            $pn=htmlspecialchars($r['pn']??'—',ENT_QUOTES,'UTF-8');
            $eu=htmlspecialchars($r['eu']??'Staff',ENT_QUOTES,'UTF-8');
            $note=htmlspecialchars($r['note']??'',ENT_QUOTES,'UTF-8');
            $adjPcs=(int)$r['pieces']; $t=date('h:i A', strtotime($r['created_at']));
            $h4.="<div class='arow'>"._imgHtml($r['image_path']??'')."<div class='ar-body'><div class='ar-code'>{$code}</div><div class='ar-name'>{$pn}</div><div class='ar-meta'><i class='fas fa-user-cog'></i> {$eu} &bull; {$t}</div><div class='ar-note'>{$note}</div></div><div class='ar-pcs ar-pcs-dec'>-{$adjPcs}<br><span>পিস</span></div></div>";
        }
        if (!$rows) $h4="<div class='empty-row'>আজকে কোনো স্টক ম্যানুয়ালি কমানো হয়নি।</div>";
    } catch(Exception $e) { $h4="<div class='empty-row'>ডাটা লোড হয়নি।</div>"; }
    $out['html_dec'] = $h4;

    echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>আজকের কার্যকলাপ — SADA KALO</title>
<meta name="theme-color" content="#ffffff">
<script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script defer src="theme-toggle.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
  .arow { display:flex; align-items:center; gap:.625rem; padding:.75rem .875rem; border-bottom:1px solid var(--sk-line-2); }
  .arow:last-child { border-bottom:0; }
  .arow:hover { background:var(--sk-surface-2); }
  .ar-img { width:48px; height:48px; object-fit:cover; border-radius:.5rem; border:1px solid var(--sk-line); cursor:pointer; flex-shrink:0; transition:.15s; font-size:1.1rem; }
  .ar-img:hover { opacity:.85; transform:scale(1.05); }
  .ar-body { flex:1; min-width:0; }
  .ar-code { font-size:.8rem; font-weight:800; color:var(--sk-ink); }
  .ar-name { font-size:.75rem; font-weight:600; color:var(--sk-ink-2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:.125rem; }
  .ar-inv { font-size:.7rem; font-weight:800; color:var(--sk-primary); margin-top:.125rem; }
  .ar-meta { font-size:.65rem; color:var(--sk-muted); font-weight:600; margin-top:.125rem; display:flex; align-items:center; gap:.25rem; flex-wrap:wrap; }
  .ar-meta strong { color:var(--sk-ink); }
  .ar-note { font-size:.65rem; color:var(--sk-muted); font-style:italic; margin-top:.25rem; font-weight:500; background:var(--sk-surface-2); padding:.2rem .375rem; border-radius:.25rem; display:inline-block; border:1px solid var(--sk-line-2); }
  .ar-tags { display:flex; flex-wrap:wrap; gap:.25rem; margin-top:.375rem; }
  .atag { font-size:.6rem; font-weight:700; padding:.2rem .5rem; border-radius:.25rem; border:1px solid; }
  .t-buy { color:var(--sk-danger-ink); background:var(--sk-danger-soft); border-color:var(--sk-danger-soft); }
  .t-cash { color:var(--sk-success-ink); background:var(--sk-success-soft); border-color:var(--sk-success-soft); }
  .ar-pcs { text-align:center; flex-shrink:0; font-size:1rem; font-weight:800; line-height:1.1; min-width:48px; }
  .ar-pcs span { display:block; font-size:.55rem; font-weight:700; opacity:.7; margin-top:.125rem; }
  .ar-pcs-add { color:var(--sk-primary); }
  .ar-pcs-ret { color:var(--sk-warn); }
  .ar-pcs-inc { color:var(--sk-success); }
  .ar-pcs-dec { color:var(--sk-danger); }
  .ar-right { display:flex; flex-direction:column; align-items:flex-end; gap:.25rem; flex-shrink:0; }
  .mt1 { margin-top:.25rem; }
  .stag { font-size:.6rem; font-weight:800; padding:.2rem .5rem; border-radius:.25rem; border:1px solid; white-space:nowrap; }
  .st-ok { color:var(--sk-success-ink); background:var(--sk-success-soft); border-color:var(--sk-success-soft); }
  .st-rej { color:var(--sk-danger-ink); background:var(--sk-danger-soft); border-color:var(--sk-danger-soft); }
  .st-pend { color:var(--sk-warn-ink); background:var(--sk-warn-soft); border-color:var(--sk-warn-soft); animation:blink 1.5s infinite; }
  @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.5} }
  .empty-row { padding:2rem 1rem; text-align:center; font-size:.8rem; font-weight:700; color:var(--sk-muted); }
  .loader-row { padding:2rem; text-align:center; color:var(--sk-muted); font-weight:700; font-size:.8rem; }
  .spin { display:inline-block; animation:sp 1s linear infinite; margin-right:.375rem; }
  @keyframes sp { to { transform:rotate(360deg); } }

  .sk-audit { background:var(--sk-surface); border:1px solid var(--sk-line); border-radius:var(--sk-radius-lg); overflow:hidden; box-shadow:var(--sk-shadow-sm); margin-bottom:.75rem; }
  .sk-audit__head { display:flex; align-items:center; justify-content:space-between; padding:.75rem .875rem; border-bottom:1px solid var(--sk-line-2); background:var(--sk-surface-2); }
  .sk-audit__head-left { display:flex; align-items:center; gap:.625rem; }
  .sk-audit__icon { width:36px; height:36px; border-radius:.5rem; display:inline-flex; align-items:center; justify-content:center; font-size:.95rem; color:#fff; background:var(--sk-primary); }
  .sk-audit__title { font-size:.8rem; font-weight:700; color:var(--sk-ink); }
  .sk-audit__badge { font-size:.7rem; font-weight:700; padding:.25rem .625rem; border-radius:50rem; background:var(--sk-primary-soft); color:var(--sk-primary-ink); min-width:30px; text-align:center; }
  .sk-audit__body { max-height:340px; overflow-y:auto; }
  .sk-audit__body::-webkit-scrollbar { width:5px; }
  .sk-audit__body::-webkit-scrollbar-thumb { background:var(--sk-line); border-radius:10px; }
  .sk-audit--sale .sk-audit__icon { background:var(--sk-primary); }
  .sk-audit--new .sk-audit__icon { background:var(--sk-info); color:var(--sk-info-ink); }
  .sk-audit--ret .sk-audit__icon { background:var(--sk-warn); color:var(--sk-warn-ink); }
  .sk-audit--inc .sk-audit__icon { background:var(--sk-success); }
  .sk-audit--dec .sk-audit__icon { background:var(--sk-danger); }

  #lb { display:none; position:fixed; inset:0; z-index:10050; background:rgba(0,0,0,.92); align-items:center; justify-content:center; }
  #lb img { max-width:92%; max-height:82vh; border-radius:.75rem; border:3px solid #fff; object-fit:contain; box-shadow:0 18px 40px rgba(0,0,0,.6); }
  #lb-close { position:absolute; top:1rem; right:1.25rem; color:#fff; font-size:2rem; cursor:pointer; background:rgba(255,255,255,.12); border:0; border-radius:.5rem; width:44px; height:44px; line-height:1; }

  .sk-datepill { display:inline-flex; align-items:center; gap:.5rem; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); border-radius:50rem; padding:.5rem 1rem; font-size:.75rem; font-weight:700; color:#fff; }
  .sk-datepill i { color:#ffd54f; }
</style>
</head>
<body>

<header class="sk-appbar">
  <div class="sk-appbar__left">
    <button class="sk-iconbtn" onclick="document.getElementById('skDrawer').classList.add('open');document.getElementById('skOverlay').classList.add('active');" aria-label="Menu"><i class="fas fa-bars"></i></button>
    <a href="inventory_dashboard.php" class="sk-iconbtn" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
  </div>
  <div class="sk-appbar__title"><span class="dot"></span> আজকের কার্যকলাপ</div>
  <div class="sk-appbar__right">
    <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger" aria-label="Logout"><i class="fas fa-power-off"></i></a>
  </div>
</header>

<div class="sk-overlay" id="skOverlay" onclick="document.getElementById('skDrawer').classList.remove('open');this.classList.remove('active');"></div>
<aside class="sk-drawer" id="skDrawer">
  <div class="sk-drawer__head">
    <button class="sk-drawer__close" onclick="document.getElementById('skDrawer').classList.remove('open');document.getElementById('skOverlay').classList.remove('active');"><i class="fas fa-times"></i></button>
    <img src="logo.png" class="sk-drawer__logo" onerror="this.style.display='none'">
    <div class="sk-drawer__brand">SADA KALO</div>
    <div class="sk-drawer__sub">DAILY AUDIT</div>
  </div>
  <div class="sk-drawer__section">Main</div>
  <div class="sk-drawer__grid">
    <a href="inventory_dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-th-large"></i></div><div class="sk-drawer__label">Dashboard</div></a>
    <a href="inventory.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-plus"></i></div><div class="sk-drawer__label">Add Item</div></a>
    <a href="Invantory_Items.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-box-open"></i></div><div class="sk-drawer__label">Item List</div></a>
    <a href="inventory_pos.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></div><div class="sk-drawer__label">POS</div></a>
    <a href="inventory_sales_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-receipt"></i></div><div class="sk-drawer__label">History</div></a>
    <a href="return_product.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-undo-alt"></i></div><div class="sk-drawer__label">Return</div></a>
    <a href="out_of_stock.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-exclamation-triangle"></i></div><div class="sk-drawer__label">Out Stock</div></a>
  </div>
  <div class="sk-drawer__section">Admin</div>
  <div class="sk-drawer__grid">
    <a href="admin_inventory_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-cogs"></i></div><div class="sk-drawer__label">Inv Ctrl</div></a>
    <a href="admin_category_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-tags"></i></div><div class="sk-drawer__label">Category</div></a>
    <a href="admin_return_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-clipboard-list"></i></div><div class="sk-drawer__label">Returns Log</div></a>
    <a href="daily_activity.php" class="sk-drawer__item active"><div class="sk-drawer__icon"><i class="fas fa-clipboard-check"></i></div><div class="sk-drawer__label">Daily Act.</div></a>
    <a href="product_edit_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-pen-to-square"></i></div><div class="sk-drawer__label">Edit Log</div></a>
  </div>
</aside>

<main class="sk-container">

  <div class="sk-card sk-card--ink" style="display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.875rem;">
    <div>
      <div style="font-size:.7rem; font-weight:700; letter-spacing:.15em; color:rgba(255,255,255,.85); text-transform:uppercase;">Today's Audit</div>
      <div style="font-size:1.25rem; font-weight:800; margin-top:.25rem; color:#fff;">আজকের কার্যকলাপ</div>
      <div style="font-size:.7rem; color:rgba(255,255,255,.75); margin-top:.25rem; font-weight:500;">সকল ইনভেন্টরি অ্যাকশনের লগ</div>
    </div>
    <div class="sk-datepill"><i class="fas fa-calendar-day"></i><span><?php echo date('d M Y'); ?></span></div>
  </div>

  <div class="sk-stats sk-stats--4" style="margin-bottom:.875rem;">
    <div class="sk-stat sk-stat--info">
      <div class="sk-stat__icon"><i class="fas fa-plus-circle"></i></div>
      <div class="sk-stat__lbl">নতুন অ্যাড</div>
      <div class="sk-stat__val" id="s-newp">—</div>
    </div>
    <div class="sk-stat sk-stat--accent">
      <div class="sk-stat__icon"><i class="fas fa-shopping-cart"></i></div>
      <div class="sk-stat__lbl">মোট বিক্রি</div>
      <div class="sk-stat__val" id="s-salep">—</div>
    </div>
    <div class="sk-stat sk-stat--warn">
      <div class="sk-stat__icon"><i class="fas fa-undo-alt"></i></div>
      <div class="sk-stat__lbl">রিটার্ন রিকু.</div>
      <div class="sk-stat__val" id="s-ret">—</div>
    </div>
    <div class="sk-stat sk-stat--success">
      <div class="sk-stat__icon"><i class="fas fa-arrow-up"></i></div>
      <div class="sk-stat__lbl">স্টক ইন</div>
      <div class="sk-stat__val" id="s-inc">—</div>
    </div>
    <div class="sk-stat sk-stat--danger" style="grid-column:span 2">
      <div class="sk-stat__icon"><i class="fas fa-arrow-down"></i></div>
      <div class="sk-stat__lbl">স্টক আউট (edit)</div>
      <div class="sk-stat__val" id="s-dec">—</div>
    </div>
  </div>

  <section class="sk-audit sk-audit--sale">
    <div class="sk-audit__head"><div class="sk-audit__head-left"><div class="sk-audit__icon"><i class="fas fa-shopping-cart"></i></div><div class="sk-audit__title">আজকের সেলস অডিট</div></div><div class="sk-audit__badge" id="b-sale">—</div></div>
    <div class="sk-audit__body" id="c-sale"><div class="loader-row"><i class="fas fa-circle-notch spin"></i>লোড হচ্ছে...</div></div>
  </section>

  <section class="sk-audit sk-audit--new">
    <div class="sk-audit__head"><div class="sk-audit__head-left"><div class="sk-audit__icon"><i class="fas fa-plus-circle"></i></div><div class="sk-audit__title">নতুন অ্যাড হওয়া পণ্য</div></div><div class="sk-audit__badge" id="b-new">—</div></div>
    <div class="sk-audit__body" id="c-new"><div class="loader-row"><i class="fas fa-circle-notch spin"></i>লোড হচ্ছে...</div></div>
  </section>

  <section class="sk-audit sk-audit--ret">
    <div class="sk-audit__head"><div class="sk-audit__head-left"><div class="sk-audit__icon"><i class="fas fa-undo-alt"></i></div><div class="sk-audit__title">আজকের রিটার্ন</div></div><div class="sk-audit__badge" id="b-ret">—</div></div>
    <div class="sk-audit__body" id="c-ret"><div class="loader-row"><i class="fas fa-circle-notch spin"></i>লোড হচ্ছে...</div></div>
  </section>

  <section class="sk-audit sk-audit--inc">
    <div class="sk-audit__head"><div class="sk-audit__head-left"><div class="sk-audit__icon"><i class="fas fa-arrow-up"></i></div><div class="sk-audit__title">স্টক ম্যানুয়ালি বাড়ানো</div></div><div class="sk-audit__badge" id="b-inc">—</div></div>
    <div class="sk-audit__body" id="c-inc"><div class="loader-row"><i class="fas fa-circle-notch spin"></i>লোড হচ্ছে...</div></div>
  </section>

  <section class="sk-audit sk-audit--dec">
    <div class="sk-audit__head"><div class="sk-audit__head-left"><div class="sk-audit__icon"><i class="fas fa-arrow-down"></i></div><div class="sk-audit__title">স্টক ম্যানুয়ালি কমানো</div></div><div class="sk-audit__badge" id="b-dec">—</div></div>
    <div class="sk-audit__body" id="c-dec"><div class="loader-row"><i class="fas fa-circle-notch spin"></i>লোড হচ্ছে...</div></div>
  </section>

</main>

<div id="lb" onclick="cl()">
  <button id="lb-close" onclick="cl()">✕</button>
  <img id="lb-img" src="" alt="">
</div>

<script>
const _ = id => document.getElementById(id);
function cl() { _('lb').style.display = 'none'; }
function si(s) { _('lb-img').src = s; _('lb').style.display = 'flex'; }
const userCsrfToken = "<?php echo $csrfToken; ?>";

async function loadData() {
    try {
        const fd = new FormData();
        fd.append('ajax_action','load_data'); fd.append('csrf_token', userCsrfToken);
        const res = await fetch('daily_activity.php', { method:'POST', body:fd });
        const d = await res.json();
        if (d.error) { alert(d.error); return; }
        _('s-newp').textContent = d.new_pcs + ' পিস';
        _('s-salep').textContent = d.sale_pcs + ' পিস';
        _('s-ret').textContent = d.ret_count + ' টি';
        _('s-inc').textContent = d.inc_count + ' টি';
        _('s-dec').textContent = d.dec_count + ' টি';
        _('b-new').textContent = d.new_count;
        _('b-sale').textContent = d.sale_count;
        _('b-ret').textContent = d.ret_count;
        _('b-inc').textContent = d.inc_count;
        _('b-dec').textContent = d.dec_count;
        _('c-new').innerHTML = d.html_new;
        _('c-sale').innerHTML = d.html_sale;
        _('c-ret').innerHTML = d.html_ret;
        _('c-inc').innerHTML = d.html_inc;
        _('c-dec').innerHTML = d.html_dec;
    } catch(e) {
        ['c-new','c-sale','c-ret','c-inc','c-dec'].forEach(id => _(id).innerHTML = "<div class='empty-row' style='color:var(--sk-danger)'>ডাটা লোড করতে সমস্যা।</div>");
    }
}
loadData();
</script>
</body>
</html>
