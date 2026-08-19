<?php
declare(strict_types=1);

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function logSystemError(Exception $e) {
    $logDir = __DIR__ . '/../Logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
    $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . PHP_EOL;
    file_put_contents($logDir . '/error_log.txt', $logMessage, FILE_APPEND);
}

$last_activity = $_SESSION['last_activity'] ?? null;
if ($last_activity !== null && is_int($last_activity) && (time() - $last_activity > 1200)) {
    session_unset(); session_destroy();
    echo "<script>window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo "<script>window.location.href='../index.php';</script>"; exit;
}

if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32)); }
}
$csrfToken = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

$db_path = __DIR__ . '/../db_connect.php';
if (file_exists($db_path)) { require_once $db_path; }

/** @var PDO $conn */
$uid  = isset($_SESSION['user_id']) && is_scalar($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$roleRaw = isset($_SESSION['role']) && is_string($_SESSION['role']) ? $_SESSION['role'] : 'user';
$role = strtolower(trim($roleRaw));
$isAdminEarly = ($role === 'admin');
date_default_timezone_set('Asia/Dhaka');

// Audit Logger (Helper) — Audit/ না থাকলে নীরবে স্কিপ
require_once __DIR__ . '/Helpers/AuditInit.php';
AuditInit::boot($conn);

/** upload lock — Inventory/lock.json */
function inv_lock_path(): string {
    return __DIR__ . DIRECTORY_SEPARATOR . 'lock.json';
}
function inv_is_upload_locked(): bool {
    $f = inv_lock_path();
    if (!is_file($f)) {
        return false;
    }
    $raw = @file_get_contents($f);
    if (!is_string($raw) || $raw === '') {
        return false;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return false;
    }
    return !empty($data['upload_locked']);
}
function inv_set_upload_locked(bool $locked): array {
    $path = inv_lock_path();
    $dir  = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $payload = json_encode([
        'upload_locked' => $locked,
        'updated_at'    => date('c'),
        'updated_by'    => (string)($_SESSION['user_id'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($payload === false) {
        return ['ok' => false, 'error' => 'JSON encode ব্যর্থ'];
    }
    // clearstatcache so next read is fresh
    $written = @file_put_contents($path, $payload, LOCK_EX);
    clearstatcache(true, $path);
    if ($written === false) {
        $perm = is_dir($dir) ? substr(sprintf('%o', fileperms($dir)), -4) : '----';
        return [
            'ok' => false,
            'error' => 'lock.json লিখা যায়নি। ফোল্ডার পারমিশন চেক করুন (এখন: ' . $perm . '). path=' . $path,
        ];
    }
    // verify read-back
    $check = inv_is_upload_locked();
    if ($check !== $locked) {
        return ['ok' => false, 'error' => 'lock.json লেখা হয়েছে কিন্তু রিড-ব্যাক মিলেনি'];
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * Backend resize + JPEG save. Filename = product serial/code.
 * @return string relative path e.g. uploads/SKF-451.jpg
 */
function inv_resize_save_image(string $imgData, string $productCode): string {
    $dir = __DIR__ . '/uploads/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $productCode);
    if ($safe === null || $safe === '') {
        $safe = 'product_' . time();
    }

    if (!function_exists('imagecreatefromstring')) {
        $fileName = $safe . '.jpg';
        $path = $dir . $fileName;
        $i = 2;
        while (file_exists($path)) {
            $fileName = $safe . '_' . $i . '.jpg';
            $path = $dir . $fileName;
            $i++;
        }
        if (file_put_contents($path, $imgData) === false) {
            throw new Exception('ছবি সেভ করা যায়নি!');
        }
        return 'uploads/' . $fileName;
    }

    $src = @imagecreatefromstring($imgData);
    if ($src === false) {
        $fileName = $safe . '.jpg';
        $path = $dir . $fileName;
        $i = 2;
        while (file_exists($path)) {
            $fileName = $safe . '_' . $i . '.jpg';
            $path = $dir . $fileName;
            $i++;
        }
        if (file_put_contents($path, $imgData) === false) {
            throw new Exception('ছবি সেভ করা যায়নি!');
        }
        return 'uploads/' . $fileName;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $max = 900;
    if ($w > $max || $h > $max) {
        $ratio = min($max / max(1, $w), $max / max(1, $h));
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst !== false) {
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }
    }

    $fileName = $safe . '.jpg';
    $path = $dir . $fileName;
    $i = 2;
    while (file_exists($path)) {
        $fileName = $safe . '_' . $i . '.jpg';
        $path = $dir . $fileName;
        $i++;
    }
    imagejpeg($src, $path, 82);
    imagedestroy($src);
    return 'uploads/' . $fileName;
}

$ajax_action = isset($_POST['ajax_action']) && is_string($_POST['ajax_action']) ? $_POST['ajax_action'] : '';

if ($ajax_action !== '') {
    ob_clean();
    header('Content-Type: application/json');

    if ($ajax_action !== 'check_duplicate' && $ajax_action !== 'get_upload_lock') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['status'=>'error', 'message'=>'সিকিউরিটি টোকেন মিসম্যাচ! পেজ রিলোড করুন।']); exit;
        }
    }

    if ($ajax_action === 'get_upload_lock') {
        echo json_encode(['status'=>'success', 'upload_locked' => inv_is_upload_locked()]);
        exit;
    }

    if ($ajax_action === 'toggle_upload_lock') {
        if ($role !== 'admin') {
            echo json_encode([
                'status'  => 'error',
                'message' => 'শুধু এডমিন লক পরিবর্তন করতে পারেন! (role=' . $role . ')',
            ]);
            exit;
        }
        $newState = !inv_is_upload_locked();
        $result = inv_set_upload_locked($newState);
        if (empty($result['ok'])) {
            echo json_encode([
                'status'  => 'error',
                'message' => $result['error'] ?? 'lock.json লিখা যায়নি!',
            ]);
            exit;
        }
        echo json_encode([
            'status' => 'success',
            'upload_locked' => $newState,
            'message' => $newState
                ? 'আপলোড লক ON — শুধু লাইভ ক্যামেরা'
                : 'আপলোড লক OFF — গ্যালারি + ক্যামেরা',
        ]);
        exit;
    }

    if ($ajax_action === 'check_duplicate') {
        try {
            $code = isset($_POST['product_code']) && is_string($_POST['product_code']) ? trim($_POST['product_code']) : '';
            $checkDup = $conn->prepare("SELECT id FROM inventory WHERE product_code = ? LIMIT 1");
            $checkDup->execute([$code]);
            echo json_encode(['status' => ($checkDup->rowCount() > 0) ? 'exists' : 'clear']); exit;
        } catch (PDOException $e) { logSystemError($e); echo json_encode(['status'=>'error','message'=>'ডাটাবেস এরর!']); exit; }
    }

    if ($ajax_action === 'add_category') {
        try {
            if ($role !== 'admin') throw new Exception("অনুমতি নেই!");
            $catName = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
            if ($catName === '') throw new Exception("নাম প্রয়োজন!");
            $conn->beginTransaction();
            $insertCat = $conn->prepare("INSERT INTO categories (name, status) VALUES (?, 'active')");
            $insertCat->execute([$catName]);
            $newCatId = (int)$conn->lastInsertId();
            $conn->commit();

            if (class_exists('AuditLogger') && $newCatId > 0) {
                AuditLogger::create(
                    'categories',
                    $newCatId,
                    null,
                    ['name' => $catName, 'status' => 'active'],
                    "ক্যাটাগরি অ্যাড — {$catName}"
                );
            }

            echo json_encode(['status'=>'success', 'id'=>$newCatId, 'name'=>htmlspecialchars($catName, ENT_QUOTES, 'UTF-8')]); exit;
        } catch (PDOException $e) { if ($conn->inTransaction()) $conn->rollBack(); logSystemError($e); echo json_encode(['status'=>'error','message'=>'ডাটাবেস এরর!']); exit;
        } catch (Exception $e)    { if ($conn->inTransaction()) $conn->rollBack(); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit; }
    }

    if ($ajax_action === 'add_product') {
        try {
            $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? $_POST['category_id'] : '';
            if ($categoryId === '') throw new Exception("সতর্কবার্তা: ক্যাটাগরি সিলেক্ট করা বাধ্যতামূলক!");

            // পণ্যের নাম বাদ — ক্যাটাগরি নামই name
            $catNameStmt = $conn->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
            $catNameStmt->execute([(int)$categoryId]);
            $productName = (string)($catNameStmt->fetchColumn() ?: '');
            if ($productName === '') throw new Exception('ক্যাটাগরি পাওয়া যায়নি!');

            $pieces      = isset($_POST['pieces'])    ? (int)$_POST['pieces']    : 0;
            $buyPrice    = isset($_POST['buy_price']) ? (float)$_POST['buy_price'] : 0.0;
            $cashSell    = isset($_POST['cash_sell']) ? (float)$_POST['cash_sell'] : 0.0;
            $imageSource = isset($_POST['image_source']) ? trim((string)$_POST['image_source']) : 'camera';

            $inputCost = isset($_POST['cost']) ? (float)$_POST['cost'] : 15.0;
            $finalCost = ($role === 'admin') ? $inputCost : 15.0;

            if ($role !== 'admin') {
                $minPrice = $buyPrice + $finalCost;
                if ($cashSell < $minPrice) {
                    throw new Exception("সতর্কবার্তা: বিক্রি মূল্য অবশ্যই ক্রয় মূল্য (৳" . htmlspecialchars((string)$buyPrice) . ") ও খরচের (৳" . htmlspecialchars((string)$finalCost) . ") যোগফলের সমান বা বেশি!");
                }
            }

            $conn->beginTransaction();

            $newProductCode = isset($_POST['product_code']) && trim($_POST['product_code']) !== '' ? trim($_POST['product_code']) : '';
            if ($newProductCode === '') {
                $stmtSeq = $conn->query("SELECT product_code FROM inventory WHERE product_code LIKE 'SKF-%' ORDER BY CAST(SUBSTRING_INDEX(product_code, '-', -1) AS UNSIGNED) DESC LIMIT 1");
                $lastItem = $stmtSeq->fetchColumn();
                $newNum = $lastItem ? (int)str_replace('SKF-', '', $lastItem) + 1 : 1;
                $newProductCode = 'SKF-' . str_pad((string)$newNum, 2, '0', STR_PAD_LEFT);
            }

            $checkDup = $conn->prepare("SELECT id FROM inventory WHERE product_code = ? LIMIT 1");
            $checkDup->execute([$newProductCode]);
            if ($checkDup->rowCount() > 0) throw new Exception("এই বারকোডটি ইতিমধ্যে রয়েছে!");

            $uploadedImagePath = '';
            $hasImage = isset($_POST['base64_image']) && is_string($_POST['base64_image']) && $_POST['base64_image'] !== '';

            // লক অন → গ্যালারি আপলোড ব্লক (শুধু লাইভ ক্যামেরা)
            if ($hasImage && inv_is_upload_locked() && $imageSource === 'upload') {
                throw new Exception('আপলোড লক করা আছে! শুধু লাইভ ক্যামেরা দিয়ে ছবি তুলুন।');
            }

            if ($hasImage) {
                $rawB64  = preg_replace('#^data:image/\w+;base64,#i', '', $_POST['base64_image']);
                $imgData = base64_decode($rawB64, true);
                if ($imgData === false) throw new Exception('ছবির ডাটা পড়া যায়নি!');
                if (strlen($imgData) > 6 * 1024 * 1024) throw new Exception('ছবির সাইজ ৬ MB-এর বেশি!');
                $finfo   = new finfo(FILEINFO_MIME_TYPE);
                $mime    = $finfo->buffer($imgData);
                $allowed = ['image/jpeg'=>true, 'image/png'=>true, 'image/webp'=>true, 'image/gif'=>true];
                if (!isset($allowed[$mime])) throw new Exception('ছবির ফরম্যাট অনুমোদিত নয় (JPG/PNG/WebP)!');

                // অটো রিসাইজ + ফাইলনেম = পণ্য সিরিয়াল/কোড
                $uploadedImagePath = inv_resize_save_image($imgData, $newProductCode);
            }

            $insert = $conn->prepare("INSERT INTO inventory (product_code, category_id, name, image_path, pieces, buy_price, cost, cash_sell, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$newProductCode, $categoryId, $productName, $uploadedImagePath, $pieces, $buyPrice, $finalCost, $cashSell, $uid]);
            $newId = (int)$conn->lastInsertId();
            $conn->commit();

            // Audit log — Audit/ না থাকলে class_exists false → স্কিপ
            if (class_exists('AuditLogger') && $newId > 0) {
                AuditLogger::create(
                    'inventory',
                    $newId,
                    null,
                    [
                        'product_code' => $newProductCode,
                        'category_id'  => $categoryId,
                        'name'         => $productName,
                        'image_path'   => $uploadedImagePath,
                        'pieces'       => $pieces,
                        'buy_price'    => $buyPrice,
                        'cost'         => $finalCost,
                        'cash_sell'    => $cashSell,
                        'added_by'     => $uid,
                    ],
                    "পণ্য অ্যাড — {$newProductCode} ({$productName})"
                );
            }

            echo json_encode(['status'=>'success', 'message'=>'পণ্যটি সফলভাবে যুক্ত হয়েছে!', 'product_code'=>$newProductCode]); exit;
        } catch (PDOException $e) { if ($conn->inTransaction()) $conn->rollBack(); logSystemError($e); echo json_encode(['status'=>'error','message'=>'ডাটাবেস এরর!']); exit;
        } catch (Exception $e)    { if ($conn->inTransaction()) $conn->rollBack(); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit; }
    }
}

$categoryList = [];
try { $categoryList = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); }
catch (PDOException $e) { logSystemError($e); }

$displayCode = 'SKF-01';
try {
    $stmtSeq  = $conn->query("SELECT product_code FROM inventory WHERE product_code LIKE 'SKF-%' ORDER BY CAST(SUBSTRING_INDEX(product_code, '-', -1) AS UNSIGNED) DESC LIMIT 1");
    $lastCode = $stmtSeq->fetchColumn();
    if ($lastCode) { $displayCode = 'SKF-' . str_pad((string)((int)str_replace('SKF-', '', $lastCode) + 1), 2, '0', STR_PAD_LEFT); }
} catch (PDOException $e) { logSystemError($e); }

$isAdmin = ($role === 'admin');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>পণ্য এড — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- theme-toggle disabled on add page -->
    <script>try{localStorage.setItem("sk-theme","light");document.documentElement.setAttribute("data-theme","light");}catch(e){}</script>
    <style>
        #cameraModal { display:none; position:fixed; inset:0; z-index:10001; background:rgba(0,0,0,.6); align-items:center; justify-content:center; padding:1rem; }
        #cameraModal.cam-open { display:flex; }
        #webcam { width:100%; max-height:320px; object-fit:cover; border-radius:.5rem; background:#000; }
        #camLoadingMsg { display:none; text-align:center; padding:1.25rem; color:var(--sk-muted); font-size:.85rem; font-weight:600; }
        .calc-panel { background:var(--sk-surface-2); border:1px solid var(--sk-line); border-radius:.5rem; padding:.625rem .875rem; margin-bottom:.875rem; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; justify-content:space-between; }
        .calc-item { font-size:.75rem; font-weight:600; color:var(--sk-muted); }
        .calc-item span { color:var(--sk-ink); margin-left:.25rem; font-weight:700; }
        #profit_display { font-size:.85rem; font-weight:800; }
        .profit-pos { color:var(--sk-success) !important; }
        .profit-neg { color:var(--sk-danger) !important; }
        .profit-zero { color:var(--sk-muted) !important; }
        .grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:.625rem; }
        .grid-2 { display:grid; grid-template-columns:repeat(2,1fr); gap:.625rem; }

        /* এই পেজে থিম/ডার্ক বাটন লুকাও — শুধু লগআউট রাখো */
        body.inventory-add-page .sk-appbar__right .sk-iconbtn:not(.sk-iconbtn--danger) {
            display: none !important;
        }
        body.inventory-add-page .sk-appbar__right a.sk-iconbtn--danger {
            display: inline-flex !important;
        }

        .il-page-title { text-align: center; margin: .15rem 0 .9rem; }
        .il-page-title h1 {
            margin: 0; font-size: 1.5rem; font-weight: 900;
            letter-spacing: -.02em; color: var(--sk-ink); line-height: 1.15;
        }
        .il-page-title p {
            margin: .3rem 0 0; font-size: .78rem; font-weight: 600;
            color: var(--sk-muted); letter-spacing: .03em;
        }

        .il-lock-row {
            display: flex; align-items: center; gap: 8px; margin-top: 2px;
        }
        .il-lock-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 10px; border-radius: 999px;
            border: 1px solid var(--sk-line); background: var(--sk-surface-2);
            color: var(--sk-ink-2); font-size: 11px; font-weight: 800;
            cursor: pointer; line-height: 1;
        }
        .il-lock-btn.is-locked {
            border-color: var(--sk-danger); background: var(--sk-danger-soft);
            color: var(--sk-danger);
        }
        .il-lock-btn i { font-size: 10px; }
        .il-lock-hint {
            font-size: .68rem; font-weight: 600; color: var(--sk-muted); line-height: 1.2;
        }
        .sk-appbar__title { font-size: .95rem; font-weight: 800; }
        .sk-appbar { min-height: 52px; }
    </style>
</head>
<body class="inventory-add-page">

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button type="button" class="sk-iconbtn" onclick="skToggleDrawer()" aria-label="Menu"><i class="fas fa-bars"></i></button>
        <a href="inventory_dashboard.php" class="sk-iconbtn" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title">পণ্য অ্যাড</div>
    <div class="sk-appbar__right">
        <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger" aria-label="Logout"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<div class="sk-overlay" id="skOverlay" onclick="skToggleDrawer()"></div>
<aside class="sk-drawer" id="skDrawer">
    <div class="sk-drawer__head">
        <button type="button" class="sk-drawer__close" onclick="skToggleDrawer()"><i class="fas fa-times"></i></button>
        <img src="logo.png" onerror="this.style.display='none'" class="sk-drawer__logo" alt="logo">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">FASHION</div>
    </div>
    <div class="sk-drawer__section">Main</div>
    <div class="sk-drawer__grid">
        <a href="../dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-home"></i></div><div class="sk-drawer__label">হোম</div></a>
        <a href="inventory_dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-th-large"></i></div><div class="sk-drawer__label">ড্যাশবোর্ড</div></a>
        <a href="inventory.php" class="sk-drawer__item active"><div class="sk-drawer__icon"><i class="fas fa-plus"></i></div><div class="sk-drawer__label">Add Item</div></a>
        <a href="Invantory_Items.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-box-open"></i></div><div class="sk-drawer__label">Item List</div></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></div><div class="sk-drawer__label">POS</div></a>
        <a href="inventory_sales_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-receipt"></i></div><div class="sk-drawer__label">History</div></a>
        <a href="return_product.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-undo-alt"></i></div><div class="sk-drawer__label">Return</div></a>
        <a href="out_of_stock.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-exclamation-triangle"></i></div><div class="sk-drawer__label">Out Stock</div></a>
    </div>
    <?php if ($isAdmin): ?>
    <div class="sk-drawer__section">Admin</div>
    <div class="sk-drawer__grid">
        <a href="admin_inventory_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-cogs"></i></div><div class="sk-drawer__label">Inv Ctrl</div></a>
        <a href="admin_category_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-tags"></i></div><div class="sk-drawer__label">Category</div></a>
        <a href="admin_return_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-receipt"></i></div><div class="sk-drawer__label">Returns</div></a>
        <a href="daily_activity.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-clipboard-check"></i></div><div class="sk-drawer__label">Daily Act.</div></a>
        <a href="product_edit_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-pen-to-square"></i></div><div class="sk-drawer__label">Edits</div></a>
    </div>
    <?php endif; ?>
</aside>

<main class="sk-container" style="max-width:520px;">

    <div class="il-page-title">
        <h1>পণ্য অ্যাড পেজ</h1>
        <p>নতুন পণ্য যোগ করুন</p>
    </div>

    <div class="sk-card sk-card--pad-lg">
        <form id="productForm" autocomplete="off">
            <input type="hidden" name="ajax_action" value="add_product">
            <input type="hidden" name="csrf_token" id="csrf_token_field" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="product_code" id="product_code_input" value="<?php echo htmlspecialchars($displayCode, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="base64_image" id="base64_img">

            <div style="text-align:center; margin-bottom:1rem;">
                <span id="display_serial" class="sk-pill sk-pill--ink" style="font-size:.85rem; padding:.5rem 1rem; letter-spacing:.15em;">
                    <i class="fas fa-barcode"></i> &nbsp; সিরিয়াল: <?php echo htmlspecialchars($displayCode, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>

            <div class="grid-2" style="margin-bottom:.625rem;">
                <div class="sk-field" style="margin-bottom:0;">
                    <label class="sk-label">ক্যাটাগরি <span style="color:var(--sk-danger)">*</span></label>
                    <div style="display:flex; gap:.375rem;">
                        <select name="category_id" id="categoryDropdown" class="sk-select" required>
                            <option value="" disabled selected>সিলেক্ট করুন</option>
                            <?php foreach ($categoryList as $cat): ?>
                                <option value="<?php echo htmlspecialchars((string)$cat['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isAdmin): ?>
                        <button type="button" id="addCatBtn" onclick="addCat()" class="sk-btn sk-btn--ink sk-btn--sm" style="flex-shrink:0;" title="নতুন ক্যাটাগরি যোগ করুন">
                            <i class="fas fa-plus"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sk-field" style="margin-bottom:0;">
                    <label class="sk-label">বারকোড স্ক্যান</label>
                    <button type="button" onclick="toggleScanner()" id="scanBtn" class="sk-btn sk-btn--accent sk-btn--block">
                        <i class="fas fa-qrcode"></i> স্ক্যান করুন
                    </button>
                </div>
            </div>

            <div id="inlineScannerArea" class="hidden" style="margin-bottom:.875rem; position:relative;">
                <div id="reader" class="sk-scanner" style="min-height:200px;"></div>
                <button type="button" onclick="stopScanner()" class="sk-iconbtn sk-iconbtn--danger" style="position:absolute; top:-10px; right:-10px; z-index:10;"><i class="fas fa-times"></i></button>
                <p style="text-align:center; font-size:.75rem; font-weight:600; color:var(--sk-muted); margin-top:.5rem;">ক্যামেরার সামনে QR/Barcode ধরুন</p>
            </div>

            <div class="grid-3">
                <div class="sk-field">
                    <label class="sk-label">পিস</label>
                    <input type="number" name="pieces" id="pieces_p" class="sk-input" required min="0" placeholder="0">
                </div>
                <div class="sk-field">
                    <label class="sk-label">ক্রয় মূল্য</label>
                    <input type="number" step="0.01" name="buy_price" id="buy_p" class="sk-input" required min="0" placeholder="0.00" oninput="calcPrices()">
                </div>
                <div class="sk-field">
                    <label class="sk-label">Cost <?php echo (!$isAdmin) ? '<i class="fas fa-lock" style="color:var(--sk-danger); font-size:.65rem;"></i>' : ''; ?></label>
                    <input type="number" step="0.01" name="cost" id="cost_p" value="15" class="sk-input <?php echo (!$isAdmin) ? '' : ''; ?>" style="<?php echo (!$isAdmin) ? 'color:var(--sk-danger); background:var(--sk-danger-soft);' : ''; ?>" <?php echo (!$isAdmin) ? 'readonly' : ''; ?> oninput="calcPrices()">
                </div>
            </div>

            <div class="grid-2">
                <div class="sk-field">
                    <label class="sk-label">ক্যাশ বিক্রি রেট <span style="color:var(--sk-danger)">*</span></label>
                    <input type="number" step="0.01" name="cash_sell" id="cash_sell_p" class="sk-input" required min="0" placeholder="0.00" style="border-color:var(--sk-primary);" oninput="calcPrices()">
                    <small id="min_price_hint" style="font-size:.65rem; font-weight:600; color:var(--sk-muted); display:block; margin-top:.25rem;"></small>
                </div>
                <div class="sk-field">
                    <label class="sk-label">পণ্যের ছবি</label>
                    <div style="display:flex; flex-direction:column; gap:.375rem;">
                        <button type="button" id="camBtn" onclick="openCam()" class="sk-btn sk-btn--info sk-btn--block">
                            <i class="fas fa-camera"></i> লাইভ ছবি
                        </button>
                        <button type="button" id="uploadBtn" onclick="document.getElementById('fileUpload').click()" class="sk-btn sk-btn--ghost sk-btn--block">
                            <i class="fas fa-image"></i> গ্যালারি আপলোড
                        </button>
                        <input type="file" id="fileUpload" accept="image/*" style="display:none;" onchange="onFilePicked(this)">
                        <input type="hidden" name="image_source" id="image_source" value="">
                        <?php if ($isAdmin): ?>
                        <div class="il-lock-row">
                            <button type="button" id="lockToggleBtn" class="il-lock-btn" onclick="toggleUploadLock(); return false;">
                                <i class="fas fa-lock-open" id="lockToggleIcon"></i>
                                <span id="lockBtnText">আনলক</span>
                            </button>
                            <span id="lockHint" class="il-lock-hint"></span>
                        </div>
                        <?php else: ?>
                        <div id="lockHint" class="il-lock-hint"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="calc-panel" id="calcPanel" style="display:none;">
                <div class="calc-item">ক্রয়: <span id="cp_buy">৳০</span></div>
                <div class="calc-item">Cost: <span id="cp_cost">৳০</span></div>
                <div class="calc-item">বিক্রি: <span id="cp_sell">৳০</span></div>
                <div class="calc-item">লাভ/ক্ষতি: <span id="profit_display" class="profit-zero">—</span></div>
            </div>

            <div style="display:flex; justify-content:center; margin:.875rem 0;" id="previewWrap">
                <div style="position:relative; display:none;" id="previewBox">
                    <img id="preview" src="" style="width:120px; height:120px; object-fit:cover; border-radius:.75rem; border:3px solid var(--sk-primary); box-shadow:var(--sk-shadow);">
                    <button type="button" onclick="removeImage()" style="position:absolute; top:-8px; right:-8px; width:26px; height:26px; border-radius:50%; background:var(--sk-danger); color:#fff; border:0; cursor:pointer; font-size:.75rem; display:flex; align-items:center; justify-content:center;"><i class="fas fa-times"></i></button>
                </div>
            </div>

            <button type="submit" id="saveBtn" class="sk-btn sk-btn--success sk-btn--block sk-btn--lg" style="margin-top:.5rem;">
                <i class="fas fa-save"></i> সেভ করুন
            </button>
        </form>
    </div>

    <p style="text-align:center; margin-top:.875rem; font-size:.7rem; font-weight:600; color:var(--sk-muted); letter-spacing:.12em; text-transform:uppercase;">
        &copy; SADA KALO FASHION
    </p>
</main>

<div id="cameraModal">
    <div class="sk-modal__sheet" style="max-width:400px;">
        <div class="sk-modal__head">
            <div class="sk-modal__title"><i class="fas fa-camera"></i> ছবি ক্যাপচার</div>
            <button type="button" onclick="closeCam()" class="sk-modal__close"><i class="fas fa-times"></i></button>
        </div>
        <div id="camLoadingMsg" style="display:block;">
            <i class="fas fa-spinner fa-spin" style="font-size:1.5rem; color:var(--sk-primary);"></i>
            <p style="margin-top:.5rem;">ক্যামেরা চালু হচ্ছে...</p>
        </div>
        <video id="webcam" autoplay playsinline muted style="display:none; margin-bottom:.625rem;"></video>
        <canvas id="canvas" style="display:none;"></canvas>
        <div style="display:flex; gap:.5rem; margin-top:.5rem;">
            <button type="button" id="snapBtn" onclick="takeSnapshot()" class="sk-btn sk-btn--success sk-grow" disabled style="opacity:.5;">
                <i class="fas fa-camera"></i> ক্লিক
            </button>
            <button type="button" onclick="closeCam()" class="sk-btn sk-btn--ghost sk-grow">বাতিল</button>
        </div>
    </div>
</div>

<script>
function skToggleDrawer() {
    document.getElementById('skDrawer').classList.toggle('open');
    document.getElementById('skOverlay').classList.toggle('active');
}

var camStream = null, camReady = false;

function openCam() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('এই ডিভাইসে বা ব্রাউজারে ক্যামেরা সাপোর্ট নেই।'); return;
    }
    var modal = document.getElementById('cameraModal');
    var video = document.getElementById('webcam');
    var loading = document.getElementById('camLoadingMsg');
    var snapBtn = document.getElementById('snapBtn');
    modal.classList.add('cam-open');
    video.style.display = 'none';
    loading.style.display = 'block';
    snapBtn.disabled = true;
    snapBtn.style.opacity = '0.5';
    camReady = false;
    _stopCamStream();
    navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false
    }).then(function(stream) {
        camStream = stream; video.srcObject = stream;
        video.onloadedmetadata = function() {
            video.play().then(function() {
                loading.style.display = 'none'; video.style.display = 'block';
                camReady = true; snapBtn.disabled = false; snapBtn.style.opacity = '1';
            }).catch(function(err) { _camError('ভিডিও চালু করা যায়নি'); });
        };
        video.onerror = function() { _camError('ভিডিও লোড হয়নি'); };
    }).catch(function(err) {
        var msg = 'ক্যামেরা অ্যাকসেস করা যায়নি।';
        if (err.name === 'NotAllowedError') msg = 'ক্যামেরার অনুমতি দেওয়া হয়নি!';
        if (err.name === 'NotFoundError') msg = 'কোনো ক্যামেরা পাওয়া যায়নি।';
        if (err.name === 'NotReadableError') msg = 'ক্যামেরা অন্য অ্যাপ ব্যবহার করছে।';
        if (err.name === 'OverconstrainedError') {
            navigator.mediaDevices.getUserMedia({ video: true, audio: false }).then(function(stream) {
                camStream = stream; video.srcObject = stream;
                video.onloadedmetadata = function() {
                    video.play().then(function() {
                        loading.style.display = 'none'; video.style.display = 'block';
                        camReady = true; snapBtn.disabled = false; snapBtn.style.opacity = '1';
                    });
                };
            }).catch(function() { _camError(msg); });
            return;
        }
        _camError(msg);
    });
}
function _camError(msg){ document.getElementById('cameraModal').classList.remove('cam-open'); document.getElementById('camLoadingMsg').style.display='none'; _stopCamStream(); alert(msg); }
function _stopCamStream(){ if(camStream){ camStream.getTracks().forEach(function(t){t.stop();}); camStream = null; } camReady=false; var v=document.getElementById('webcam'); if(v){v.pause(); v.srcObject=null;} }
function closeCam(){ document.getElementById('cameraModal').classList.remove('cam-open'); document.getElementById('webcam').style.display='none'; document.getElementById('camLoadingMsg').style.display='none'; _stopCamStream(); }

function takeSnapshot() {
    if (!camReady) { alert('ক্যামেরা এখনো প্রস্তুত নয়।'); return; }
    var video = document.getElementById('webcam');
    var canvas = document.getElementById('canvas');
    if (!video.videoWidth) { alert('ক্যামেরা এখনো তৈরি হয়নি।'); return; }
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    var dataUrl = canvas.toDataURL('image/jpeg', 0.85);
    setPreviewFromDataUrl(dataUrl, 'camera');
    closeCam();
}
function removeImage() {
    document.getElementById('base64_img').value = '';
    document.getElementById('preview').src = '';
    document.getElementById('previewBox').style.display = 'none';
    var src = document.getElementById('image_source');
    if (src) src.value = '';
    var fu = document.getElementById('fileUpload');
    if (fu) fu.value = '';
}

var uploadLocked = false;
var isAdminUser = <?php echo $isAdmin ? 'true' : 'false'; ?>;

function applyLockUI() {
    var upBtn = document.getElementById('uploadBtn');
    var hint = document.getElementById('lockHint');
    var icon = document.getElementById('lockToggleIcon');
    var btn = document.getElementById('lockToggleBtn');
    var btnText = document.getElementById('lockBtnText');
    if (upBtn) {
        upBtn.style.display = uploadLocked ? 'none' : '';
        upBtn.disabled = !!uploadLocked;
    }
    if (hint) {
        hint.innerHTML = uploadLocked
            ? '<span style="color:var(--sk-danger)">শুধু লাইভ ক্যামেরা</span>'
            : '<span style="color:var(--sk-success)">গ্যালারি + ক্যামেরা</span>';
    }
    if (icon) icon.className = uploadLocked ? 'fas fa-lock' : 'fas fa-lock-open';
    if (btnText) btnText.textContent = uploadLocked ? 'লক' : 'আনলক';
    if (btn) {
        btn.title = uploadLocked ? 'ক্লিক করে আনলক' : 'ক্লিক করে লক';
        if (uploadLocked) btn.classList.add('is-locked');
        else btn.classList.remove('is-locked');
    }
}

function refreshUploadLock() {
    $.post('inventory.php', { ajax_action: 'get_upload_lock' }, function (res) {
        if (res && res.status === 'success') {
            uploadLocked = !!res.upload_locked;
            applyLockUI();
        }
    }, 'json');
}

var _lockBusy = false;
function toggleUploadLock() {
    if (!isAdminUser) {
        alert('শুধু এডমিন লক ব্যবহার করতে পারেন');
        return;
    }
    if (_lockBusy) return;
    _lockBusy = true;
    var icon = document.getElementById('lockToggleIcon');
    if (icon) icon.className = 'fas fa-spinner fa-spin';
    var token = $('#csrf_token_field').val() || '';
    $.ajax({
        url: 'inventory.php',
        type: 'POST',
        dataType: 'json',
        data: {
            ajax_action: 'toggle_upload_lock',
            csrf_token: token
        },
        success: function (res) {
            if (res && res.status === 'success') {
                uploadLocked = !!res.upload_locked;
                applyLockUI();
                if (res.message) alert(res.message);
            } else {
                alert((res && res.message) ? res.message : 'লক পরিবর্তন ব্যর্থ');
                refreshUploadLock();
            }
        },
        error: function (xhr) {
            var msg = 'সার্ভার এরর';
            if (xhr && xhr.responseText) {
                try {
                    var j = JSON.parse(xhr.responseText);
                    if (j && j.message) msg = j.message;
                } catch (e) {
                    msg = 'সার্ভার এরর (HTTP ' + (xhr.status || '?') + ')';
                }
            }
            alert(msg);
            refreshUploadLock();
        },
        complete: function () {
            _lockBusy = false;
            applyLockUI();
        }
    });
}

function setPreviewFromDataUrl(dataUrl, source) {
    document.getElementById('base64_img').value = dataUrl;
    var src = document.getElementById('image_source');
    if (src) src.value = source || 'camera';
    document.getElementById('preview').src = dataUrl;
    document.getElementById('previewBox').style.display = 'block';
}

function onFilePicked(input) {
    if (uploadLocked) {
        alert('আপলোড লক করা আছে! শুধু লাইভ ক্যামেরা ব্যবহার করুন।');
        input.value = '';
        return;
    }
    var file = input.files && input.files[0];
    if (!file) return;
    if (!file.type || file.type.indexOf('image/') !== 0) {
        alert('শুধু ছবি ফাইল নির্বাচন করুন!');
        input.value = '';
        return;
    }
    if (file.size > 6 * 1024 * 1024) {
        alert('ছবি ৬ MB-এর কম হতে হবে!');
        input.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function (e) {
        setPreviewFromDataUrl(String(e.target.result || ''), 'upload');
    };
    reader.readAsDataURL(file);
}

$(function () { refreshUploadLock(); });

function addCat() {
    var cat = prompt('নতুন ক্যাটাগরির নাম:');
    if (!cat || cat.trim() === '') return;
    $.post('inventory.php', { ajax_action:'add_category', csrf_token: $('#csrf_token_field').val(), category_name: cat.trim() }, function(res) {
        if (res.status === 'success') {
            var opt = new Option(res.name, res.id, true, true);
            $('#categoryDropdown').append(opt);
        } else { alert(res.message); }
    }, 'json').fail(function(){ alert('সার্ভার সমস্যা।'); });
}

var qrScanner = null, qrScannerRunning = false;
function toggleScanner(){ if(qrScannerRunning) stopScanner(); else startScanner(); }
function startScanner() {
    var area = document.getElementById('inlineScannerArea');
    area.classList.remove('hidden');
    if (qrScanner) { try { qrScanner.clear(); } catch(e){} qrScanner = null; }
    document.getElementById('reader').innerHTML = '';
    qrScanner = new Html5Qrcode('reader');
    qrScanner.start({ facingMode: 'environment' }, { fps:10, qrbox:{ width:200, height:200 }, aspectRatio:1.0 },
        function(decodedText) {
            $.post('inventory.php', { ajax_action:'check_duplicate', product_code: decodedText }, function(res) {
                if (res.status === 'exists') { alert('⚠️ এই বারকোডটি ইতিমধ্যে ব্যবহৃত!'); }
                else {
                    document.getElementById('display_serial').innerHTML = '<i class="fas fa-check-circle" style="color:var(--sk-success)"></i> &nbsp; স্ক্যানড: ' + decodedText;
                    document.getElementById('product_code_input').value = decodedText;
                    stopScanner();
                }
            }, 'json').fail(function(){
                document.getElementById('product_code_input').value = decodedText;
                document.getElementById('display_serial').innerHTML = '<i class="fas fa-barcode"></i> স্ক্যানড: ' + decodedText;
                stopScanner();
            });
        },
        function() {}
    ).then(function() {
        qrScannerRunning = true;
        document.getElementById('scanBtn').innerHTML = '<i class="fas fa-stop-circle"></i> বন্ধ করুন';
        document.getElementById('scanBtn').classList.remove('sk-btn--accent');
        document.getElementById('scanBtn').classList.add('sk-btn--danger');
    }).catch(function(err) {
        area.classList.add('hidden'); qrScannerRunning = false; qrScanner = null;
        alert('স্ক্যানার চালু করা যায়নি।' + (err && err.name === 'NotAllowedError' ? ' (অনুমতি দেওয়া হয়নি)' : ''));
    });
}
function stopScanner() {
    if (qrScanner && qrScannerRunning) {
        qrScanner.stop().then(_cleanupScanner).catch(_cleanupScanner);
    } else { _cleanupScanner(); }
}
function _cleanupScanner() {
    qrScannerRunning = false;
    if (qrScanner) { try { qrScanner.clear(); } catch(e){} qrScanner = null; }
    document.getElementById('inlineScannerArea').classList.add('hidden');
    var btn = document.getElementById('scanBtn');
    btn.innerHTML = '<i class="fas fa-qrcode"></i> স্ক্যান করুন';
    btn.classList.remove('sk-btn--danger'); btn.classList.add('sk-btn--accent');
}

function calcPrices() {
    var buyPrice = parseFloat(document.getElementById('buy_p').value) || 0;
    var cost = parseFloat(document.getElementById('cost_p').value) || 0;
    var cashSell = parseFloat(document.getElementById('cash_sell_p').value) || 0;
    var minSell = buyPrice + cost;
    var panel = document.getElementById('calcPanel');
    if (buyPrice > 0 || cashSell > 0) {
        panel.style.display = 'flex';
        document.getElementById('cp_buy').textContent = '৳' + buyPrice.toFixed(2);
        document.getElementById('cp_cost').textContent = '৳' + cost.toFixed(2);
        document.getElementById('cp_sell').textContent = '৳' + cashSell.toFixed(2);
        var profit = cashSell - minSell;
        var profitEl = document.getElementById('profit_display');
        if (cashSell === 0) { profitEl.textContent='—'; profitEl.className='profit-zero'; }
        else if (profit > 0) { profitEl.textContent = '+৳' + profit.toFixed(2) + ' লাভ'; profitEl.className='profit-pos'; }
        else if (profit === 0) { profitEl.textContent = '৳০ (সমান)'; profitEl.className='profit-zero'; }
        else { profitEl.textContent = '৳' + profit.toFixed(2) + ' ক্ষতি'; profitEl.className='profit-neg'; }
    } else { panel.style.display = 'none'; }
    var hint = document.getElementById('min_price_hint');
    hint.textContent = buyPrice > 0 ? 'সর্বনিম্ন বিক্রি মূল্য: ৳' + minSell.toFixed(2) : '';
    <?php if (!$isAdmin): ?>
    var cashInput = document.getElementById('cash_sell_p');
    if (cashSell > 0 && cashSell < minSell) { cashInput.style.borderColor='var(--sk-danger)'; cashInput.style.background='var(--sk-danger-soft)'; }
    else { cashInput.style.borderColor=''; cashInput.style.background=''; }
    <?php endif; ?>
}

$('#productForm').on('submit', function(e) {
    e.preventDefault();
    if (!$('#categoryDropdown').val()) { alert('ক্যাটাগরি সিলেক্ট করুন!'); return; }
    var btn = $('#saveBtn'); var orig = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> প্রসেসিং...');
    if (qrScannerRunning) stopScanner();
    closeCam();
    $.ajax({
        url: 'inventory.php', type: 'POST',
        data: new FormData(this), contentType: false, processData: false, dataType: 'json',
        success: function(res) { alert(res.message); if(res.status === 'success') location.reload(); },
        error: function() { alert('সার্ভার সমস্যা।'); },
        complete: function() { btn.prop('disabled', false).html(orig); }
    });
});

calcPrices();
</script>
<style>body{padding-bottom:76px;}</style>
<script>
// থিম বাটন যদি কোথাও থাকে সরাও
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.sk-appbar__right .sk-iconbtn').forEach(function (el) {
    if (el.classList.contains('sk-iconbtn--danger')) return;
    if (el.id === 'lockToggleBtn') return;
    // moon / sun icons = theme
    var ic = el.querySelector('i');
    if (ic && (ic.classList.contains('fa-moon') || ic.classList.contains('fa-sun') || ic.classList.contains('fa-circle-half-stroke'))) {
      el.remove();
    }
  });
});
</script>
<?php include 'inventory_bottom_nav.php'; ?>
</body>
</html>
