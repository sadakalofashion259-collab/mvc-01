<?php
declare(strict_types=1);

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// System Error Logger Function
function logSystemError(Throwable $e): void {
    $logDir = __DIR__ . '/../Logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $errorMessage = "[{$timestamp}] Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine() . PHP_EOL;
    @file_put_contents($logDir . '/error_log.txt', $errorMessage, FILE_APPEND);
}

$isAjax = isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'load_dashboard_data';

// Session Timeout (20 Min)
$lastActivity = $_SESSION['last_activity'] ?? null;
if ($lastActivity !== null && is_int($lastActivity) && (time() - $lastActivity > 1200)) {
    session_unset(); session_destroy();
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error'=>'session_expired']); exit; }
    header("Location: ../index.php"); exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error'=>'session_expired']); exit; }
    header("Location: ../index.php"); exit;
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

$db_path = '../db_connect.php';
if (file_exists($db_path)) include $db_path;

/** @var PDO $conn */
$role = isset($_SESSION['role']) && is_string($_SESSION['role']) ? $_SESSION['role'] : 'user';
$uid = isset($_SESSION['user_id']) && is_scalar($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
date_default_timezone_set('Asia/Dhaka');

if ($isAjax) {
    ob_clean(); header('Content-Type: application/json');
    $postCsrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($postCsrf === '' || !hash_equals($csrfToken, $postCsrf)) {
        echo json_encode(['error' => 'Security token mismatch!']); exit;
    }

    $data = [];
    $data['pendingReturns'] = 0;
    if ($role === 'admin') {
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM inventory_returns WHERE status = 'pending'");
            $val = $stmt !== false ? $stmt->fetchColumn() : 0;
            $data['pendingReturns'] = is_numeric($val) ? (int)$val : 0;
        } catch(Throwable $e){ logSystemError($e); }
    }

    // Out of Stock
    $outOfStock = [];
    try {
        $stmt = $conn->query("SELECT product_code, name, image_path FROM inventory WHERE pieces <= 0 AND status = 'active' ORDER BY id DESC");
        if ($stmt !== false) $outOfStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { logSystemError($e); }

    $data['outOfStockCount'] = count($outOfStock);
    $data['html_outOfStock'] = '';
    if (count($outOfStock) > 0) {
        foreach ($outOfStock as $out) {
            if (!is_array($out)) continue;
            $imgPath = isset($out['image_path']) && is_string($out['image_path']) ? $out['image_path'] : '';
            $img = $imgPath !== '' ? htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8') : '';
            $codeStr = isset($out['product_code']) && is_string($out['product_code']) ? $out['product_code'] : '';
            $code = htmlspecialchars($codeStr, ENT_QUOTES, 'UTF-8');
            $imgTag = $img !== '' ? "<img src='{$img}' alt='Img'>" : "<div class='sk-pc__noimg'><i class='fas fa-image'></i></div>";
            $data['html_outOfStock'] .= "<div class='sk-pc sk-pc--out' onclick=\"openImageModal('{$img}', '{$code}')\">{$imgTag}<div class='sk-pc__code'>{$code}</div><div class='sk-pc__tag sk-pc__tag--out'>স্টক শেষ</div></div>";
        }
    } else {
        $data['html_outOfStock'] = '<div class="sk-empty" style="padding:24px;"><i class="fas fa-check-circle" style="color:var(--sk-success);"></i><p style="color:var(--sk-success);">আলহামদুলিল্লাহ! কোনো পণ্যের স্টক শূন্য নেই।</p></div>';
    }

    // Stagnant
    $st15 = []; $st30 = []; $st45 = [];
    try {
        $stagnantStmt = $conn->query("
            SELECT i.product_code, i.name, i.image_path, i.pieces,
                   COALESCE((SELECT MAX(s.created_at) FROM inventory_sales s JOIN inventory_sale_items isi ON s.id = isi.sale_id WHERE isi.product_code = i.product_code), i.created_at) as last_active
            FROM inventory i WHERE i.pieces > 0 AND i.status = 'active'
        ");
        $stagnantItems = $stagnantStmt !== false ? $stagnantStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $now = time();
        foreach ($stagnantItems as $item) {
            if (!is_array($item)) continue;
            $lastActiveStr = isset($item['last_active']) && is_string($item['last_active']) ? $item['last_active'] : '';
            $lastActiveTime = $lastActiveStr !== '' ? strtotime($lastActiveStr) : false;
            if ($lastActiveTime !== false) {
                $days = (int)floor(($now - $lastActiveTime) / 86400);
                if ($days >= 45)      $st45[] = ['item'=>$item, 'days'=>$days];
                elseif ($days >= 30)  $st30[] = ['item'=>$item, 'days'=>$days];
                elseif ($days >= 15)  $st15[] = ['item'=>$item, 'days'=>$days];
            }
        }
    } catch (Throwable $e) { logSystemError($e); }

    /**
     * @param array<int, array{item: array<string, mixed>, days: int}> $itemsArray
     */
    function generateStagnantHtml(array $itemsArray, string $tone): string {
        $html = '';
        foreach ($itemsArray as $data) {
            if (!is_array($data) || !isset($data['item']) || !is_array($data['item'])) continue;
            $item = $data['item'];
            $days = isset($data['days']) && is_int($data['days']) ? $data['days'] : 0;
            $imgPath = isset($item['image_path']) && is_string($item['image_path']) ? $item['image_path'] : '';
            $img = $imgPath !== '' ? htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8') : '';
            $codeStr = isset($item['product_code']) && is_string($item['product_code']) ? $item['product_code'] : '';
            $code = htmlspecialchars($codeStr, ENT_QUOTES, 'UTF-8');
            $imgTag = $img !== '' ? "<img src='{$img}' alt='Img'>" : "<div class='sk-pc__noimg'><i class='fas fa-image'></i></div>";
            $html .= "<div class='sk-pc sk-pc--{$tone}' onclick=\"openImageModal('{$img}', '{$code}')\">{$imgTag}<div class='sk-pc__code'>{$code}</div><div class='sk-pc__tag sk-pc__tag--{$tone}'>{$days} দিন</div></div>";
        }
        return $html;
    }

    $data['count15'] = count($st15); $data['html_15'] = generateStagnantHtml($st15, 'warn');
    $data['count30'] = count($st30); $data['html_30'] = generateStagnantHtml($st30, 'orange');
    $data['count45'] = count($st45); $data['html_45'] = generateStagnantHtml($st45, 'danger');

    // Admin stats
    if ($role === 'admin') {
        $curStock = ['pcs'=>0, 'val'=>0];
        try {
            $stmt = $conn->query("SELECT SUM(pieces) as pcs, SUM(pieces * (buy_price + cost)) as val FROM inventory");
            if ($stmt !== false) { $res = $stmt->fetch(PDO::FETCH_ASSOC); if (is_array($res)) $curStock = $res; }
        } catch(Throwable $e){ logSystemError($e); }
        $curPcs = isset($curStock['pcs']) && is_numeric($curStock['pcs']) ? (int)$curStock['pcs'] : 0;
        $curVal = isset($curStock['val']) && is_numeric($curStock['val']) ? (float)$curStock['val'] : 0.0;

        $everSold = ['pcs'=>0, 'val'=>0];
        try {
            $stmt = $conn->query("SELECT SUM(pieces) as pcs, SUM(pieces * (buy_price + cost)) as val FROM inventory_sale_items");
            if ($stmt !== false) { $res = $stmt->fetch(PDO::FETCH_ASSOC); if (is_array($res)) $everSold = $res; }
        } catch(Throwable $e){ logSystemError($e); }

        $everAddedPcs = $curPcs + (isset($everSold['pcs']) && is_numeric($everSold['pcs']) ? (int)$everSold['pcs'] : 0);
        $everAddedVal = $curVal + (isset($everSold['val']) && is_numeric($everSold['val']) ? (float)$everSold['val'] : 0.0);

        $todayAdded = ['pcs'=>0, 'val'=>0];
        $adjInc = ['pcs'=>0, 'val'=>0];
        try {
            $stmt1 = $conn->query("SELECT SUM(pieces) as pcs, SUM(pieces * (buy_price + cost)) as val FROM inventory WHERE DATE(created_at) = CURRENT_DATE");
            if ($stmt1 !== false) { $res1 = $stmt1->fetch(PDO::FETCH_ASSOC); if (is_array($res1)) $todayAdded = $res1; }
            $stmt2 = $conn->query("SELECT SUM(a.pieces) as pcs, SUM(a.pieces * (i.buy_price + i.cost)) as val FROM inventory_adjustments a JOIN inventory i ON a.product_code = i.product_code WHERE DATE(a.created_at) = CURRENT_DATE AND a.adjustment_type = 'increase'");
            if ($stmt2 !== false) { $res2 = $stmt2->fetch(PDO::FETCH_ASSOC); if (is_array($res2)) $adjInc = $res2; }
        } catch(Throwable $e){ logSystemError($e); }

        $todayPcs = (isset($todayAdded['pcs']) && is_numeric($todayAdded['pcs']) ? (int)$todayAdded['pcs'] : 0)
                  + (isset($adjInc['pcs']) && is_numeric($adjInc['pcs']) ? (int)$adjInc['pcs'] : 0);
        $todayVal = (isset($todayAdded['val']) && is_numeric($todayAdded['val']) ? (float)$todayAdded['val'] : 0.0)
                  + (isset($adjInc['val']) && is_numeric($adjInc['val']) ? (float)$adjInc['val'] : 0.0);

        $data['adminStats'] = [
            'everAddedPcs' => number_format($everAddedPcs),
            'everAddedVal' => '৳ ' . number_format($everAddedVal, 2),
            'curPcs'       => number_format($curPcs),
            'curVal'       => '৳ ' . number_format($curVal, 2),
            'todayAddedPcs'=> number_format($todayPcs),
            'todayAddedVal'=> '৳ ' . number_format($todayVal, 2)
        ];
    }
    echo json_encode($data); exit;
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ইনভেন্টরি হাব — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=Hind+Siliguri:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme-toggle.js"></script>
    <style>
        /* dashboard-specific */
        .sk-pc-row { display:flex; gap:10px; overflow-x:auto; padding:8px 4px 12px; }
        .sk-pc-row::-webkit-scrollbar { display:none; }
        .sk-pc { flex-shrink:0; width:120px; background:var(--sk-surface); border:1px solid var(--sk-line); border-radius:14px; padding:10px; box-shadow:var(--sk-shadow-sm); cursor:pointer; transition:.22s; text-align:center; }
        .sk-pc:hover { transform:translateY(-3px); box-shadow:var(--sk-shadow); border-color:var(--sk-brand); }
        .sk-pc img, .sk-pc__noimg { width:80px; height:80px; object-fit:cover; border-radius:12px; margin:0 auto 8px; display:flex; align-items:center; justify-content:center; background:var(--sk-surface-3); color:var(--sk-muted); font-size:24px; border:1px solid var(--sk-line-2); }
        .sk-pc__code { font-size:11px; font-weight:900; color:var(--sk-ink); line-height:1.2; }
        .sk-pc__tag { font-size:9px; font-weight:800; padding:3px 9px; border-radius:999px; margin-top:6px; display:inline-block; text-transform:uppercase; letter-spacing:.5px; }
        .sk-pc__tag--out, .sk-pc__tag--danger { background:var(--sk-danger); color:#fff; }
        .sk-pc__tag--orange { background:var(--sk-warn); color:#fff; }
        .sk-pc__tag--warn { background:#fbbf24; color:#78350f; }
        .sk-pc--out img { filter:grayscale(.9); opacity:.85; }

        /* Quick menu chips */
        .qchip-row { display:flex; gap:8px; overflow-x:auto; padding:4px 0 8px; }
        .qchip-row::-webkit-scrollbar { display:none; }
        .qchip { flex-shrink:0; min-width:88px; display:flex; flex-direction:column; align-items:center; gap:6px; padding:12px 8px; background:var(--sk-surface); border:1px solid var(--sk-line); border-radius:16px; text-decoration:none; color:var(--sk-ink); transition:.18s; box-shadow:var(--sk-shadow-sm); }
        .qchip:hover { transform:translateY(-2px); border-color:var(--sk-brand); box-shadow:var(--sk-shadow); }
        .qchip__ic { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:var(--sk-surface-3); color:var(--sk-brand); font-size:16px; }
        .qchip__label { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.4px; text-align:center; }
        .qchip--pos { background:var(--sk-grad-ink); color:#fff; border-color:transparent; }
        .qchip--pos .qchip__ic { background:var(--sk-brand); color:#fff; }
        .qchip--brand { background:var(--sk-grad-brand); color:#fff; border-color:transparent; }
        .qchip--brand .qchip__ic { background:rgba(255,255,255,.18); color:#fff; }

        /* Speed dial FAB */
        .skd-fab-container { position:fixed; right:18px; bottom:96px; z-index:950; }
        .skd-fab-menu { position:absolute; right:8px; bottom:8px; width:0; height:0; pointer-events:none; }
        .skd-fab-item { position:absolute; width:48px; height:48px; border-radius:16px; color:#fff; display:flex; align-items:center; justify-content:center; font-size:17px; text-decoration:none; box-shadow:var(--sk-shadow); opacity:0; right:0; bottom:0; transform:scale(.4); pointer-events:none; transition:all .35s cubic-bezier(.4,0,.2,1); background:var(--sk-grad-ink); border:1px solid rgba(255,255,255,.12); }
        .skd-fab-item:nth-child(3){ background:var(--sk-grad-brand); }
        .skd-fab-menu.active .skd-fab-item { opacity:1; pointer-events:auto; }
        .skd-fab-menu.active .skd-fab-item:nth-child(1){ transform:translate(0,-72px) scale(1); transition-delay:.0s; }
        .skd-fab-menu.active .skd-fab-item:nth-child(2){ transform:translate(-52px,-52px) scale(1); transition-delay:.04s; }
        .skd-fab-menu.active .skd-fab-item:nth-child(3){ transform:translate(-72px,0) scale(1); transition-delay:.08s; }
        .skd-fab-menu.active .skd-fab-item:nth-child(4){ transform:translate(-52px,52px) scale(1); transition-delay:.12s; }
        .skd-fab-menu.active .skd-fab-item:nth-child(5){ transform:translate(0,72px) scale(1); transition-delay:.16s; }
        .skd-main-fab { width:62px; height:62px; border-radius:22px; background:var(--sk-grad-brand); color:#fff; display:flex; align-items:center; justify-content:center; font-size:22px; border:4px solid var(--sk-surface); cursor:pointer; box-shadow:var(--sk-shadow-brand); transition:.35s; position:relative; z-index:10; }
        .skd-main-fab.active { transform:rotate(135deg); background:var(--sk-grad-ink); box-shadow:var(--sk-shadow-ink); }

        /* Lightbox */
        #imageLightbox { display:none; position:fixed; z-index:100000; inset:0; background:rgba(9,9,11,.95); align-items:center; justify-content:center; flex-direction:column; backdrop-filter:blur(8px); }
        #lightboxImg { max-width:90%; max-height:78vh; border-radius:18px; border:3px solid #fff; object-fit:contain; box-shadow:var(--sk-shadow-lg); }
        .close-lightbox { position:absolute; top:18px; right:24px; color:#fff; font-size:36px; font-weight:900; cursor:pointer; }

        @keyframes pulse-brand { 0%{box-shadow:0 0 0 0 rgba(225,29,72,.4);} 70%{box-shadow:0 0 0 14px rgba(225,29,72,0);} 100%{box-shadow:0 0 0 0 rgba(225,29,72,0);} }

        /* notification bell */
        .nf-bell { position:relative; }
        .nf-bell.ringing i { transform-origin:50% 0; animation:nf-ring .9s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes nf-ring { 0%,100%{transform:rotate(0);} 10%{transform:rotate(22deg);} 20%{transform:rotate(-18deg);} 30%{transform:rotate(16deg);} 40%{transform:rotate(-12deg);} 50%{transform:rotate(8deg);} 60%{transform:rotate(-5deg);} 70%{transform:rotate(3deg);} }
        .nf-bell__badge { position:absolute; top:-4px; right:-4px; min-width:18px; height:18px; padding:0 5px; background:var(--sk-brand); color:#fff; font-size:10px; font-weight:900; line-height:18px; text-align:center; border-radius:999px; border:2px solid var(--sk-surface); display:none; animation:nf-badge-pulse 1.8s infinite; }
        .nf-bell__badge.show { display:block; }
        @keyframes nf-badge-pulse { 0%{box-shadow:0 0 0 0 rgba(220,53,69,.5);} 70%{box-shadow:0 0 0 9px rgba(220,53,69,0);} 100%{box-shadow:0 0 0 0 rgba(220,53,69,0);} }

        /* bottom nav breathing room */
        body { padding-bottom: 76px; }
    </style>
</head>
<body>

<!-- App Bar -->
<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button class="sk-iconbtn" onclick="toggleSidebar()" aria-label="Menu"><i class="fas fa-bars"></i></button>
    </div>
    <div class="sk-appbar__title"><span class="dot"></span> ইনভেন্টরি হাব</div>
    <div class="sk-appbar__right" style="display:flex; gap:8px; align-items:center;">
        <a href="notification_dashboard.php" class="sk-iconbtn nf-bell" id="notifBell" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <span class="nf-bell__badge" id="notifBellBadge">0</span>
        </a>
        <a href="../logout.php" onclick="localStorage.removeItem('sk-cache');" class="sk-iconbtn sk-iconbtn--danger" aria-label="Logout"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<!-- Drawer -->
<div class="sk-overlay" id="myOverlay" onclick="toggleSidebar()"></div>
<aside class="sk-drawer" id="mySidebar">
    <div class="sk-drawer__head">
        <button class="sk-drawer__close" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        <img src="logo.png" alt="Logo" onerror="this.style.display='none'" class="sk-drawer__logo">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">FASHION</div>
    </div>
    <div class="sk-drawer__section">Quick Menu</div>
    <div class="sk-drawer__grid">
        <a href="../dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-home"></i></div><span class="sk-drawer__label">হোমপেজ</span></a>
        <a href="inventory_dashboard.php" class="sk-drawer__item active"><div class="sk-drawer__icon"><i class="fas fa-th-large"></i></div><span class="sk-drawer__label">ড্যাশবোর্ড</span></a>
        <a href="inventory.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-plus"></i></div><span class="sk-drawer__label">Add Item</span></a>
        <a href="Invantory_Items.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-box-open"></i></div><span class="sk-drawer__label">Item List</span></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></div><span class="sk-drawer__label">POS Sell</span></a>
        <a href="inventory_sales_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-receipt"></i></div><span class="sk-drawer__label">History</span></a>
        <a href="return_product.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-undo-alt"></i></div><span class="sk-drawer__label">Return</span></a>
        <a href="out_of_stock.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-exclamation-triangle"></i></div><span class="sk-drawer__label">Out Stock</span></a>
        <a href="category_mange.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-folder-tree"></i></div><span class="sk-drawer__label">ক্যাটাগরি</span></a>
        <?php if($role === 'admin'): ?>
        <a href="admin_inventory_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-cogs"></i></div><span class="sk-drawer__label">Admin</span></a>
        <a href="admin_category_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-tags"></i></div><span class="sk-drawer__label">Category Ctrl</span></a>
        <a href="daily_activity.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-clipboard-check"></i></div><span class="sk-drawer__label">Daily Act.</span></a>
        <a href="product_edit_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-pen-to-square"></i></div><span class="sk-drawer__label">Edit Log</span></a>
        <?php endif; ?>
    </div>
</aside>

<main class="sk-container">

    <?php if($role === 'admin'): ?>
    <div id="adminAlertBox" class="sk-card sk-card--accent" style="display:none; margin-bottom:14px; animation:pulse-brand 2s infinite;">
        <div class="sk-row sk-row--between" style="gap:12px;">
            <div class="sk-row" style="gap:12px;">
                <span class="sk-stat__icon" style="background:var(--sk-grad-brand);"><i class="fas fa-bell"></i></span>
                <div>
                    <div style="font-size:10px; font-weight:900; letter-spacing:1.5px; text-transform:uppercase; color:var(--sk-brand-2);">অ্যাডমিন অ্যাকশন প্রয়োজন!</div>
                    <div style="font-size:11px; font-weight:700; margin-top:3px; color:var(--sk-ink-2);">স্টাফদের থেকে <strong class="sk-pill sk-pill--brand" id="alertPendingCount">0</strong> টি রিটার্ন রিকোয়েস্ট পেন্ডিং।</div>
                </div>
            </div>
            <a href="admin_return_history.php" class="sk-btn sk-btn--ink sk-btn--sm">যাচাই <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Banner -->
    <div class="sk-banner">
        <img src="banner.jpg" alt="Banner" onerror="this.style.display='none'">
    </div>

    <!-- Quick menu -->
    <div class="sk-card" style="margin-bottom:14px;">
        <div class="sk-section-title" style="margin:0 0 8px;">
            <h2><i class="fas fa-bolt"></i> কুইক মেনু</h2>
            <span class="sk-sub"><i class="fas fa-arrows-alt-h"></i> Swipe</span>
        </div>
        <div class="qchip-row">
            <a href="inventory.php" class="qchip"><span class="qchip__ic"><i class="fas fa-plus"></i></span><span class="qchip__label">Add Item</span></a>
            <a href="Invantory_Items.php" class="qchip"><span class="qchip__ic"><i class="fas fa-box-open"></i></span><span class="qchip__label">Item List</span></a>
            <a href="inventory_pos.php" class="qchip qchip--brand"><span class="qchip__ic"><i class="fas fa-shopping-cart"></i></span><span class="qchip__label">POS Sell</span></a>
            <a href="inventory_sales_history.php" class="qchip"><span class="qchip__ic"><i class="fas fa-receipt"></i></span><span class="qchip__label">History</span></a>
            <a href="return_product.php" class="qchip"><span class="qchip__ic"><i class="fas fa-undo-alt"></i></span><span class="qchip__label">Return</span></a>
            <a href="out_of_stock.php" class="qchip"><span class="qchip__ic"><i class="fas fa-exclamation-triangle"></i></span><span class="qchip__label">Out Stock</span></a>
            <a href="category_mange.php" class="qchip"><span class="qchip__ic"><i class="fas fa-folder-tree"></i></span><span class="qchip__label">ক্যাটাগরি</span></a>
            <?php if($role === 'admin'): ?>
            <a href="admin_inventory_control.php" class="qchip qchip--pos"><span class="qchip__ic"><i class="fas fa-cogs"></i></span><span class="qchip__label">Admin</span></a>
            <a href="daily_activity.php" class="qchip"><span class="qchip__ic"><i class="fas fa-clipboard-check"></i></span><span class="qchip__label">Daily Act</span></a>
            <?php endif; ?>
        </div>
    </div>

    <?php if($role === 'admin'): ?>
    <!-- Admin Summary -->
    <div class="sk-section-title">
        <h2><i class="fas fa-chart-line"></i> ইনভেন্টরি সামারি</h2>
        <span class="sk-sub">Admin View</span>
    </div>
    <div class="sk-stats sk-stats--2" style="margin-bottom:12px;">
        <div class="sk-stat sk-stat--accent">
            <div class="sk-row sk-row--between">
                <span class="sk-stat__icon" style="background:var(--sk-grad-brand);"><i class="fas fa-warehouse"></i></span>
                <span class="sk-pill sk-pill--brand">All-time</span>
            </div>
            <div class="sk-stat__lbl">শুরু থেকে অ্যাড করা</div>
            <div class="sk-stat__val" id="val_everAddedPcs"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="sk-pill sk-pill--brand" style="margin-top:6px;" id="val_everAddedVal"></div>
        </div>
        <div class="sk-stat sk-stat--success">
            <div class="sk-row sk-row--between">
                <span class="sk-stat__icon"><i class="fas fa-boxes-stacked"></i></span>
                <span class="sk-pill sk-pill--success">Live</span>
            </div>
            <div class="sk-stat__lbl">বর্তমান স্টক</div>
            <div class="sk-stat__val" id="val_curPcs"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="sk-pill sk-pill--success" style="margin-top:6px;" id="val_curVal"></div>
        </div>
    </div>

    <!-- Today added -->
    <div class="sk-card sk-card--ink" style="margin-bottom:14px;">
        <div style="font-size:10px; font-weight:800; letter-spacing:1.8px; text-transform:uppercase; color:var(--sk-brand-ink); margin-bottom:10px;">
            <i class="fas fa-calendar-day"></i> আজকে নতুন করে অ্যাড করা
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div>
                <div style="font-size:10px; font-weight:700; text-transform:uppercase; color:rgba(255,255,255,.65);">পরিমাণ</div>
                <div style="font-size:22px; font-weight:900; color:#fff; margin-top:4px;" id="val_todayAddedPcs"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
            <div style="border-left:1px solid rgba(255,255,255,.12); padding-left:14px;">
                <div style="font-size:10px; font-weight:700; text-transform:uppercase; color:rgba(255,255,255,.65);">মোট ভ্যালু</div>
                <div style="font-size:18px; font-weight:900; color:var(--sk-brand-ink); margin-top:4px;" id="val_todayAddedVal"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stagnant 15 -->
    <div class="sk-card" id="section_stagnant15" style="display:none; margin-bottom:14px;">
        <div class="sk-section-title" style="margin:0 0 4px;">
            <h2 style="color:var(--sk-warn);"><i class="fas fa-clock"></i> ১৫ দিন অবিক্রিত</h2>
            <span class="sk-pill sk-pill--warn" id="badge_15">0</span>
        </div>
        <div class="sk-pc-row" id="track15"></div>
    </div>

    <!-- Stagnant 30 -->
    <div class="sk-card" id="section_stagnant30" style="display:none; margin-bottom:14px;">
        <div class="sk-section-title" style="margin:0 0 4px;">
            <h2 style="color:var(--sk-warn);"><i class="fas fa-hourglass-half"></i> ৩০ দিন অবিক্রিত</h2>
            <span class="sk-pill" style="background:#fed7aa;color:#7c2d12;" id="badge_30">0</span>
        </div>
        <div class="sk-pc-row" id="track30"></div>
    </div>

    <!-- Stagnant 45 -->
    <div class="sk-card" id="section_stagnant45" style="display:none; margin-bottom:14px;">
        <div class="sk-section-title" style="margin:0 0 4px;">
            <h2 style="color:var(--sk-danger);"><i class="fas fa-skull-crossbones"></i> ৪৫+ দিন (ডেড স্টক)</h2>
            <span class="sk-pill sk-pill--danger" id="badge_45">0</span>
        </div>
        <div class="sk-pc-row" id="track45"></div>
    </div>

    <!-- Out of Stock -->
    <div class="sk-card sk-card--ink" style="margin-bottom:24px;">
        <div class="sk-row sk-row--between" style="margin-bottom:10px;">
            <h2 style="font-size:13px; font-weight:900; color:#fff; margin:0; display:flex; align-items:center; gap:8px;"><i class="fas fa-ban" style="color:var(--sk-brand-ink);"></i> স্টক শেষ (Out of Stock)</h2>
            <span class="sk-pill sk-pill--brand" id="badge_out"><i class="fas fa-spinner fa-spin"></i></span>
        </div>
        <div style="background:var(--sk-surface); border-radius:14px; padding:4px;">
            <div class="sk-pc-row" id="trackOutStock"></div>
        </div>
    </div>

</main>

<!-- Image Lightbox -->
<div id="imageLightbox" onclick="closeImageModal()">
    <span class="close-lightbox" onclick="closeImageModal()">&times;</span>
    <img id="lightboxImg" src="" alt="">
    <div id="lightboxText" style="margin-top:14px; font-weight:900; letter-spacing:2.5px; font-size:14px; color:#fff; background:#09090b; border:1px solid var(--sk-brand); padding:8px 18px; border-radius:14px;"></div>
</div>

<!-- Bottom Navigation Bar (dashboard only) -->
<?php include 'inventory_bottom_nav.php'; ?>

<script>
const userCsrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';

function toggleSidebar() {
    document.getElementById("mySidebar").classList.toggle("open");
    document.getElementById("myOverlay").classList.toggle("active");
}
function openImageModal(imgSrc, textLabel) {
    if(!imgSrc) return;
    document.getElementById('lightboxImg').src = imgSrc;
    document.getElementById('lightboxText').innerText = textLabel || '';
    document.getElementById('imageLightbox').style.display = 'flex';
}
function closeImageModal() { document.getElementById('imageLightbox').style.display = 'none'; }
function toggleFabMenu() {
    document.getElementById('fabMenu').classList.toggle('active');
    document.getElementById('mainFabBtn').classList.toggle('active');
}
document.addEventListener('click', function(e) {
    let c = document.querySelector('.skd-fab-container');
    if (c && !c.contains(e.target)) {
        document.getElementById('fabMenu').classList.remove('active');
        document.getElementById('mainFabBtn').classList.remove('active');
    }
});

$(document).ready(function() {
    $.ajax({
        url: 'inventory_dashboard.php',
        type: 'POST',
        data: { ajax_action:'load_dashboard_data', csrf_token: userCsrfToken },
        dataType: 'json',
        success: function(response) {
            if (response.error === 'session_expired') { window.location.href = '../index.php'; return; }
            if (response.error) return;

            if(response.pendingReturns > 0) {
                $('#alertPendingCount').text(response.pendingReturns);
                $('#adminAlertBox').show();
            }

            $('#badge_out').text(response.outOfStockCount + ' টি');
            $('#trackOutStock').html(response.html_outOfStock);

            if(response.count15 > 0) { $('#badge_15').text(response.count15+' টি'); $('#track15').html(response.html_15); $('#section_stagnant15').show(); }
            if(response.count30 > 0) { $('#badge_30').text(response.count30+' টি'); $('#track30').html(response.html_30); $('#section_stagnant30').show(); }
            if(response.count45 > 0) { $('#badge_45').text(response.count45+' টি'); $('#track45').html(response.html_45); $('#section_stagnant45').show(); }

            if(response.adminStats) {
                $('#val_everAddedPcs').text(response.adminStats.everAddedPcs + ' পিস');
                $('#val_everAddedVal').text(response.adminStats.everAddedVal);
                $('#val_curPcs').text(response.adminStats.curPcs + ' পিস');
                $('#val_curVal').text(response.adminStats.curVal);
                $('#val_todayAddedPcs').text(response.adminStats.todayAddedPcs + ' পিস');
                $('#val_todayAddedVal').text(response.adminStats.todayAddedVal);
            }
        }
    });
});

/* ---- Notification bell (unseen count + ring) ---- */
(function () {
    function getSeen() { var v = parseInt(localStorage.getItem('sk-notif-seen') || '0', 10); return isNaN(v) ? 0 : v; }
    function pollBell(ring) {
        $.ajax({
            url: 'notification_dashboard.php', type: 'POST', dataType: 'json',
            data: { ajax_action: 'load_notifications', csrf_token: userCsrfToken },
            success: function (res) {
                if (!res || res.error || !Array.isArray(res.items)) return;
                var seen = getSeen();
                var unseen = res.items.filter(function (it) { return it.epoch > seen; }).length;
                var badge = document.getElementById('notifBellBadge');
                if (!badge) return;
                if (unseen > 0) {
                    badge.textContent = unseen > 99 ? '99+' : String(unseen);
                    badge.classList.add('show');
                    if (ring) { var b = document.getElementById('notifBell'); b.classList.remove('ringing'); void b.offsetWidth; b.classList.add('ringing'); }
                } else {
                    badge.classList.remove('show');
                }
            }
        });
    }
    pollBell(false);
    setInterval(function () { pollBell(true); }, 30000);
})();
</script>
</body>
</html>
