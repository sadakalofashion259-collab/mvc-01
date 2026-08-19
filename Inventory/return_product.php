<?php
declare(strict_types=1);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

class BusinessLogicException extends Exception {}
function logSystemError(Throwable $e): void {
    $logDir = __DIR__ . '/../../Logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir.'/error_log.txt', "[".date('Y-m-d H:i:s')."] ".$e->getMessage().PHP_EOL, FILE_APPEND);
}

$isAjax = isset($_POST['ajax_action']);
$lastActivity = $_SESSION['last_activity'] ?? null;
if ($lastActivity !== null && is_int($lastActivity) && (time() - $lastActivity > 1200)) {
    session_unset(); session_destroy();
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'session_expired']); exit; }
    echo "<script>window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'session_expired']); exit; }
    header("Location: ../index.php"); exit;
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
$db_path = '../db_connect.php';
if (file_exists($db_path)) include $db_path;

/** @var PDO $conn */
$role = isset($_SESSION['role']) && is_string($_SESSION['role']) ? $_SESSION['role'] : 'user';
$uid = isset($_SESSION['user_id']) && is_scalar($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
date_default_timezone_set('Asia/Dhaka');

if ($isAjax) {
    ob_clean(); header('Content-Type: application/json');
    $postCsrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($postCsrf === '' || !hash_equals($csrfToken, $postCsrf)) {
        echo json_encode(['status'=>'error','message'=>'সিকিউরিটি টোকেন মিসম্যাচ!']); exit;
    }
    $action = $_POST['ajax_action'];

    if ($action === 'check_invoice') {
        try {
            $invoiceNo = isset($_POST['invoice_no']) && is_string($_POST['invoice_no']) ? trim($_POST['invoice_no']) : '';
            $prodCode = isset($_POST['product_code']) && is_string($_POST['product_code']) ? trim($_POST['product_code']) : '';
            $checkSale = $conn->prepare("SELECT i.pieces, i.sell_price, s.invoice_no, inv.name as product_name FROM inventory_sale_items i JOIN inventory_sales s ON i.sale_id = s.id LEFT JOIN inventory inv ON i.product_code = inv.product_code WHERE s.invoice_no = ? AND i.product_code = ?");
            $checkSale->execute([$invoiceNo, $prodCode]);
            $saleData = $checkSale->fetch(PDO::FETCH_ASSOC);
            if(!$saleData || !is_array($saleData)) throw new BusinessLogicException('এই ইনভয়েস ও প্রোডাক্ট কোডের কোনো সেলস রেকর্ড পাওয়া যায়নি!');
            $checkRet = $conn->prepare("SELECT SUM(return_pieces) FROM inventory_returns WHERE invoice_no = ? AND product_code = ? AND status != 'rejected'");
            $checkRet->execute([$invoiceNo, $prodCode]);
            $alreadyReturned = (int)$checkRet->fetchColumn();
            $soldPieces = (int)$saleData['pieces'];
            $maxCanReturn = $soldPieces - $alreadyReturned;
            if ($maxCanReturn <= 0) throw new BusinessLogicException('এই ইনভয়েসের সব পণ্য ইতিমধ্যে রিটার্ন!');
            $sellPrice = (float)$saleData['sell_price'];
            $productName = (string)$saleData['product_name'];
            echo json_encode(['status'=>'success', 'max_return'=>$maxCanReturn, 'unit_price'=>$sellPrice, 'product_name'=>htmlspecialchars($productName, ENT_QUOTES, 'UTF-8')]); exit;
        } catch (BusinessLogicException $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) { logSystemError($e); echo json_encode(['status'=>'error','message'=>'সিস্টেমে ত্রুটি!']); exit; }
    }

    if ($action === 'process_return') {
        try {
            $invoiceNo = isset($_POST['invoice_no']) && is_string($_POST['invoice_no']) ? trim($_POST['invoice_no']) : '';
            $prodCode = isset($_POST['product_code']) && is_string($_POST['product_code']) ? trim($_POST['product_code']) : '';
            $returnPieces = isset($_POST['return_pieces']) && is_numeric($_POST['return_pieces']) ? (int)$_POST['return_pieces'] : 0;
            $note = isset($_POST['return_note']) && is_string($_POST['return_note']) ? trim($_POST['return_note']) : '';
            if ($returnPieces <= 0 || empty($note) || empty($invoiceNo) || empty($prodCode)) throw new BusinessLogicException('সব তথ্য পূরণ করুন!');
            $conn->beginTransaction();
            $checkSale = $conn->prepare("SELECT i.pieces, i.sell_price, i.profit FROM inventory_sale_items i JOIN inventory_sales s ON i.sale_id = s.id WHERE s.invoice_no = ? AND i.product_code = ?");
            $checkSale->execute([$invoiceNo, $prodCode]);
            $saleItemData = $checkSale->fetch(PDO::FETCH_ASSOC);
            if (!$saleItemData) throw new BusinessLogicException("সিস্টেমে রেকর্ড পাওয়া যায়নি!");
            $soldPieces = (int)$saleItemData['pieces'];
            $unitSellPrice = (float)$saleItemData['sell_price'];
            $unitProfit = (float)$saleItemData['profit'];
            $exactRefundAmount = $unitSellPrice * $returnPieces;
            $exactReturnProfit = $unitProfit * $returnPieces;
            $checkRet = $conn->prepare("SELECT SUM(return_pieces) FROM inventory_returns WHERE invoice_no = ? AND product_code = ? AND status != 'rejected'");
            $checkRet->execute([$invoiceNo, $prodCode]);
            $alreadyReturned = (int)$checkRet->fetchColumn();
            if ($returnPieces > ($soldPieces - $alreadyReturned)) throw new BusinessLogicException("এত পিস রিটার্ন নিতে পারবেন না!");
            $status = ($role === 'admin') ? 'approved' : 'pending';
            $retIns = $conn->prepare("INSERT INTO inventory_returns (invoice_no, product_code, return_pieces, refund_amount, return_profit, note, returned_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $retIns->execute([$invoiceNo, $prodCode, $returnPieces, $exactRefundAmount, $exactReturnProfit, $note, $uid, $status]);
            if ($status === 'approved') {
                $stkUpd = $conn->prepare("UPDATE inventory SET pieces = pieces + ? WHERE product_code = ?");
                $stkUpd->execute([$returnPieces, $prodCode]);
                $msg = 'রিটার্ন গৃহীত! স্টকে অটো-অ্যাডজাস্ট হয়েছে।';
            } else {
                $msg = 'রিটার্ন রিকোয়েস্ট অ্যাডমিনের কাছে পাঠানো হয়েছে।';
            }
            $conn->commit();
            echo json_encode(['status'=>'success','message'=>$msg]); exit;
        } catch (BusinessLogicException $e) { if ($conn->inTransaction()) $conn->rollBack(); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); logSystemError($e); echo json_encode(['status'=>'error','message'=>'সার্ভার ত্রুটি!']); exit; }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>পণ্য রিটার্ন — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme-toggle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .sk-modal.active { display:flex; }
        #reader { width:100%; height:180px; }
    </style>
</head>
<body>

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button type="button" class="sk-iconbtn" onclick="toggleDrawer()" aria-label="Menu"><i class="fas fa-bars"></i></button>
        <a href="inventory_dashboard.php" class="sk-iconbtn" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title"><span class="dot"></span> পণ্য রিটার্ন</div>
    <div class="sk-appbar__right">
        <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger" aria-label="Logout"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<div class="sk-overlay" id="skOverlay" onclick="toggleDrawer()"></div>
<aside class="sk-drawer" id="skDrawer">
    <div class="sk-drawer__head">
        <button type="button" class="sk-drawer__close" onclick="toggleDrawer()"><i class="fas fa-times"></i></button>
        <img src="logo.png" alt="Logo" class="sk-drawer__logo" onerror="this.style.display='none'">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">PRODUCT RETURN</div>
    </div>
    <div class="sk-drawer__section">Quick Menu</div>
    <div class="sk-drawer__grid">
        <a href="../dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-home"></i></div><span class="sk-drawer__label">হোম</span></a>
        <a href="inventory_dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-th-large"></i></div><span class="sk-drawer__label">Dashboard</span></a>
        <a href="inventory.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-plus"></i></div><span class="sk-drawer__label">Add Item</span></a>
        <a href="Invantory_Items.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-box-open"></i></div><span class="sk-drawer__label">Item List</span></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></div><span class="sk-drawer__label">POS</span></a>
        <a href="inventory_sales_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-receipt"></i></div><span class="sk-drawer__label">History</span></a>
        <a href="return_product.php" class="sk-drawer__item active"><div class="sk-drawer__icon"><i class="fas fa-undo-alt"></i></div><span class="sk-drawer__label">Return</span></a>
        <a href="out_of_stock.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-exclamation-triangle"></i></div><span class="sk-drawer__label">Out Stock</span></a>
        <?php if($role === 'admin'): ?>
        <a href="admin_inventory_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-cogs"></i></div><span class="sk-drawer__label">Admin</span></a>
        <a href="admin_return_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-clipboard-list"></i></div><span class="sk-drawer__label">Returns Log</span></a>
        <?php endif; ?>
    </div>
</aside>

<main class="sk-container">

    <div class="sk-banner">
        <img src="banner.jpg" alt="Banner" onerror="this.style.display='none'">
    </div>

    <div class="sk-card sk-card--accent" style="text-align:center; margin-bottom:.875rem;">
        <i class="fas fa-box-open" style="font-size:2.25rem; color:var(--sk-primary);"></i>
        <h2 style="font-weight:700; font-size:1rem; color:var(--sk-ink); margin:.5rem 0 .25rem;">"সাদা কালো ফ্যাশন"</h2>
        <p style="font-size:.75rem; font-weight:600; color:var(--sk-muted); margin:0;">বিক্রিত মাল ফেরত নেওয়া সুন্নাহ</p>
        <?php if($role !== 'admin'): ?>
            <p style="margin-top:.625rem; font-size:.7rem; font-weight:600; color:var(--sk-info-ink); background:var(--sk-info-soft); padding:.5rem .625rem; border-radius:.5rem;">
                <i class="fas fa-info-circle"></i> স্টাফদের রিটার্নগুলো অ্যাডমিন অ্যাপ্রুভ হলে স্টকে যোগ হবে।
            </p>
        <?php endif; ?>
    </div>

    <div class="sk-card sk-card--pad-lg" style="max-width:520px; margin:0 auto;">
        <div class="sk-section-title">
            <h2><i class="fas fa-search"></i> ইনভয়েস যাচাই</h2>
            <span class="sk-sub">Step 1</span>
        </div>

        <form id="verifyForm" onsubmit="verifyInvoice(event)">
            <div class="sk-field">
                <label class="sk-label">ইনভয়েস নং</label>
                <div class="sk-input-wrap">
                    <i class="fas fa-file-invoice"></i>
                    <input type="text" id="invoice_no" value="SKF-INV-" class="sk-input sk-input--icon" required>
                </div>
            </div>
            <div class="sk-field">
                <label class="sk-label">প্রোডাক্ট কোড বা বারকোড</label>
                <div class="sk-search">
                    <div class="sk-input-wrap sk-grow">
                        <i class="fas fa-barcode"></i>
                        <input type="text" id="product_code" value="SKF-" class="sk-input sk-input--icon" required>
                    </div>
                    <button type="button" onclick="toggleScanner()" id="scanBtn" class="sk-btn sk-btn--ink"><i class="fas fa-qrcode"></i></button>
                </div>
            </div>

            <div id="inlineScannerArea" class="hidden" style="margin:.25rem 0 .875rem;">
                <div style="position:relative;">
                    <button type="button" onclick="stopScanner()" class="sk-iconbtn sk-iconbtn--danger" style="position:absolute; top:-10px; right:-10px; z-index:10;"><i class="fas fa-times"></i></button>
                    <div class="sk-scanner"><div id="reader"></div></div>
                </div>
                <div style="text-align:center; margin-top:.5rem;">
                    <span class="sk-scanner__hint"><i class="fas fa-crosshairs"></i> বারকোড ক্যামেরার সামনে ধরুন</span>
                </div>
            </div>

            <button type="submit" id="verifyBtn" class="sk-btn sk-btn--accent sk-btn--block sk-btn--lg">
                ইনভয়েস যাচাই করুন <i class="fas fa-search"></i>
            </button>
        </form>
    </div>
</main>

<div id="returnModalOverlay" class="sk-modal">
    <div class="sk-modal__sheet">
        <div class="sk-modal__head">
            <div class="sk-modal__title"><i class="fas fa-undo"></i> রিটার্ন কনফার্মেশন</div>
            <button type="button" class="sk-modal__close" onclick="closeReturnModal()">&times;</button>
        </div>
        <form id="returnForm" onsubmit="submitReturn(event)">
            <div class="sk-card sk-card--accent" style="padding:.75rem; margin-bottom:.875rem; text-align:center;">
                <p id="productNameDisplay" style="font-size:.85rem; font-weight:700; color:var(--sk-ink); margin:0 0 .25rem;"></p>
                <p style="font-size:.75rem; font-weight:600; color:var(--sk-muted); margin:0;">
                    সর্বোচ্চ রিটার্ন: <span id="maxPcsDisplay" class="sk-pill sk-pill--accent"></span> পিস
                </p>
            </div>
            <div class="sk-field">
                <label class="sk-label">কয় পিস ফেরত দিচ্ছেন?</label>
                <input type="number" id="return_pieces" min="1" oninput="calcRefund()" class="sk-input" style="text-align:center; font-size:1.15rem; font-weight:800;" required>
            </div>
            <div class="sk-field">
                <label class="sk-label">সিস্টেম নির্ধারিত রিফান্ড (৳)</label>
                <input type="number" step="0.01" id="refund_amount" class="sk-input" style="text-align:center; font-size:1.15rem; font-weight:800; color:var(--sk-danger); background:var(--sk-danger-soft); cursor:not-allowed;" readonly>
                <span style="font-size:.65rem; font-weight:600; color:var(--sk-muted);"><i class="fas fa-shield-alt"></i> ব্যাকএন্ড থেকে অটো ক্যালকুলেট।</span>
            </div>
            <div class="sk-field">
                <label class="sk-label">কারণ / নোট (আবশ্যিক)</label>
                <textarea id="return_note" class="sk-textarea" rows="3" placeholder="যেমন: কালার পছন্দ হয়নি..." required></textarea>
            </div>
            <button type="submit" id="submitBtn" class="sk-btn sk-btn--accent sk-btn--block sk-btn--lg">
                <i class="fas fa-paper-plane"></i> রিকোয়েস্ট পাঠান
            </button>
        </form>
    </div>
</div>

<script>
const userCsrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
let maxReturnLimit = 0, unitSellPrice = 0;

function toggleDrawer(){ document.getElementById('skDrawer').classList.toggle('open'); document.getElementById('skOverlay').classList.toggle('active'); }
function closeReturnModal() { document.getElementById('returnModalOverlay').classList.remove('open'); $('#return_pieces').val(''); $('#refund_amount').val(''); $('#return_note').val(''); }
$('#invoice_no').on('input', function() { let p='SKF-INV-'; if(!$(this).val().startsWith(p)) $(this).val(p); });
$('#product_code').on('input', function() { let p='SKF-'; if(!$(this).val().startsWith(p)) $(this).val(p); });

let html5QrReturn; let isScannerRunning = false;
function toggleScanner() {
    let area = document.getElementById('inlineScannerArea');
    let btn = document.getElementById('scanBtn');
    if (area.classList.contains('hidden')) {
        area.classList.remove('hidden');
        btn.innerHTML = '<i class="fas fa-times"></i>';
        setTimeout(() => {
            if (!html5QrReturn) html5QrReturn = new Html5Qrcode("reader");
            html5QrReturn.start({ facingMode:"environment" }, { fps:15, qrbox:{ width:180, height:60 }, aspectRatio:2.0 },
                (text) => { if (isScannerRunning) { $('#product_code').val(text); try{new Audio('https://www.soundjay.com/buttons/sounds/button-09.mp3').play().catch(e=>{});}catch(e){} stopScanner(); } },
                () => {}
            ).then(() => { isScannerRunning = true; }).catch(err => { Swal.fire({icon:'error', title:'ক্যামেরা এরর', text:'ক্যামেরা এক্সেস পাওয়া যায়নি!', confirmButtonColor:'#0d6efd'}); stopScanner(); });
        }, 100);
    } else stopScanner();
}
function stopScanner() {
    let area = document.getElementById('inlineScannerArea');
    let btn = document.getElementById('scanBtn');
    btn.innerHTML = '<i class="fas fa-qrcode"></i>';
    if (html5QrReturn && isScannerRunning) {
        html5QrReturn.stop().then(() => { isScannerRunning = false; area.classList.add('hidden'); }).catch(e => { area.classList.add('hidden'); isScannerRunning = false; });
    } else area.classList.add('hidden');
}

function verifyInvoice(e) {
    e.preventDefault();
    let inv = $('#invoice_no').val().trim();
    let code = $('#product_code').val().trim();
    if (inv === 'SKF-INV-' || code === 'SKF-') { Swal.fire({icon:'warning', title:'অসম্পূর্ণ', text:'সম্পূর্ণ ইনভয়েস ও কোড লিখুন!', confirmButtonColor:'#0d6efd'}); return; }
    let btn = $('#verifyBtn'); let origTxt = btn.html();
    btn.html('<i class="fas fa-circle-notch fa-spin"></i> যাচাই হচ্ছে...').prop('disabled', true);
    $.ajax({
        url:'return_product.php', type:'POST',
        data:{ ajax_action:'check_invoice', csrf_token: userCsrfToken, invoice_no:inv, product_code:code },
        dataType:'json',
        success: function(res) {
            btn.html(origTxt).prop('disabled', false);
            if (res.status === 'session_expired') { window.location.href='../index.php'; return; }
            if (res.status === 'success') {
                maxReturnLimit = parseInt(res.max_return);
                unitSellPrice = parseFloat(res.unit_price);
                $('#productNameDisplay').text(res.product_name);
                $('#maxPcsDisplay').text(maxReturnLimit);
                $('#return_pieces').attr('max', maxReturnLimit).val('');
                $('#refund_amount').val('');
                document.getElementById('returnModalOverlay').classList.add('open');
            } else { Swal.fire({icon:'error', title:'পাওয়া যায়নি', text:res.message, confirmButtonColor:'#dc3545'}); }
        },
        error: function() { btn.html(origTxt).prop('disabled', false); Swal.fire({icon:'error', title:'সার্ভার এরর', text:'ডাটাবেসের সাথে কানেক্ট করা যাচ্ছে না!', confirmButtonColor:'#dc3545'}); }
    });
}

function calcRefund() {
    let pcs = parseInt($('#return_pieces').val()) || 0;
    if (pcs > maxReturnLimit) {
        Swal.fire({icon:'warning', title:'লিমিট ক্রস', text:`সর্বোচ্চ ${maxReturnLimit} পিস!`, confirmButtonColor:'#0d6efd'});
        pcs = maxReturnLimit; $('#return_pieces').val(pcs);
    }
    $('#refund_amount').val((pcs * unitSellPrice).toFixed(2));
}

function submitReturn(e) {
    e.preventDefault();
    let btn = $('#submitBtn'); let origTxt = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin"></i> প্রসেসিং...').prop('disabled', true);
    $.ajax({
        url:'return_product.php', type:'POST',
        data:{ ajax_action:'process_return', csrf_token: userCsrfToken, invoice_no: $('#invoice_no').val(), product_code: $('#product_code').val(), return_pieces: $('#return_pieces').val(), return_note: $('#return_note').val() },
        dataType:'json',
        success: function(res) {
            if (res.status === 'session_expired') { window.location.href='../index.php'; return; }
            if (res.status === 'success') {
                closeReturnModal();
                Swal.fire({icon:'success', title:'সফল!', text:res.message, timer:4000, showConfirmButton:false, confirmButtonColor:'#198754'}).then(() => location.reload());
            } else { Swal.fire({icon:'error', title:'সমস্যা', text:res.message, confirmButtonColor:'#dc3545'}); btn.html(origTxt).prop('disabled', false); }
        },
        error: function() { Swal.fire({icon:'error', title:'সিস্টেম এরর', text:'ফর্ম সাবমিট ব্যর্থ!', confirmButtonColor:'#dc3545'}); btn.html(origTxt).prop('disabled', false); }
    });
}
</script>
</body>
</html>
