<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    session_unset(); session_destroy();
    echo "<script>alert('Session Expired!'); window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') { echo "<script>window.location.href='../index.php';</script>"; exit; }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];
include '../db_connect.php';
$uid = $_SESSION['user_id'] ?? 0;
date_default_timezone_set('Asia/Dhaka');

if (isset($_POST['ajax_action'])) {
    ob_clean(); header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { echo json_encode(['status'=>'error','message'=>'সিকিউরিটি টোকেন মিসম্যাচ!']); exit; }
    $action = $_POST['ajax_action'];
    $returnId = (int)($_POST['return_id'] ?? 0);
    if ($returnId <= 0) { echo json_encode(['status'=>'error','message'=>'অবৈধ রিকোয়েস্ট!']); exit; }

    if ($action == 'approve_return') {
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("SELECT * FROM inventory_returns WHERE id = ? AND status = 'pending'");
            $stmt->execute([$returnId]); $ret = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ret) {
                $conn->prepare("UPDATE inventory_returns SET status = 'approved' WHERE id = ?")->execute([$returnId]);
                $conn->prepare("UPDATE inventory SET pieces = pieces + ? WHERE product_code = ?")->execute([$ret['return_pieces'], $ret['product_code']]);
                $conn->commit();
                echo json_encode(['status'=>'success','message'=>'রিটার্ন অ্যাকসেপ্ট। স্টক আপডেট।']);
            } else { $conn->rollBack(); echo json_encode(['status'=>'error','message'=>'ইতিমধ্যে প্রসেস করা হয়েছে!']); }
            exit;
        } catch (Exception $e) { $conn->rollBack(); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit; }
    }

    if ($action == 'reject_return') {
        try { $conn->prepare("UPDATE inventory_returns SET status = 'rejected' WHERE id = ? AND status = 'pending'")->execute([$returnId]); echo json_encode(['status'=>'success','message'=>'রিটার্ন ক্যানসেল!']); exit;
        } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit; }
    }

    if ($action == 'delete_return') {
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("SELECT * FROM inventory_returns WHERE id = ?"); $stmt->execute([$returnId]);
            $ret = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ret) {
                $invNo = $ret['invoice_no']; $pCode = $ret['product_code']; $retPcs = (int)$ret['return_pieces'];
                if ($ret['status'] !== 'approved') { $conn->prepare("UPDATE inventory SET pieces = pieces + ? WHERE product_code = ?")->execute([$retPcs, $pCode]); }
                $saleStmt = $conn->prepare("SELECT id FROM inventory_sales WHERE invoice_no = ?"); $saleStmt->execute([$invNo]);
                $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);
                if ($sale) {
                    $saleId = $sale['id'];
                    $itemStmt = $conn->prepare("SELECT id, pieces, sell_price, profit FROM inventory_sale_items WHERE sale_id = ? AND product_code = ? LIMIT 1");
                    $itemStmt->execute([$saleId, $pCode]); $saleItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
                    if ($saleItem) {
                        $deductAmount = (float)$saleItem['sell_price'] * $retPcs;
                        $deductProfit = (float)$saleItem['profit'] * $retPcs;
                        if ((int)$saleItem['pieces'] <= $retPcs) { $conn->prepare("DELETE FROM inventory_sale_items WHERE id = ?")->execute([$saleItem['id']]); }
                        else { $conn->prepare("UPDATE inventory_sale_items SET pieces = pieces - ? WHERE id = ?")->execute([$retPcs, $saleItem['id']]); }
                        $conn->prepare("UPDATE inventory_sales SET total_pieces = total_pieces - ?, total_sell_amount = total_sell_amount - ?, total_profit = total_profit - ? WHERE id = ?")->execute([$retPcs, $deductAmount, $deductProfit, $saleId]);
                        $checkEmpty = $conn->prepare("SELECT total_pieces FROM inventory_sales WHERE id = ?"); $checkEmpty->execute([$saleId]);
                        if ((int)$checkEmpty->fetchColumn() <= 0) $conn->prepare("DELETE FROM inventory_sales WHERE id = ?")->execute([$saleId]);
                    }
                }
                $conn->prepare("DELETE FROM inventory_returns WHERE id = ?")->execute([$returnId]);
                $conn->commit();
                echo json_encode(['status'=>'success','message'=>'ভুল বিক্রি বাতিল! স্টক, সেলস ও প্রফিট অটো-আপডেট।']);
            } else { $conn->rollBack(); echo json_encode(['status'=>'error','message'=>'রেকর্ড পাওয়া যায়নি!']); }
            exit;
        } catch (Exception $e) { $conn->rollBack(); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit; }
    }
}

try {
    $stmt = $conn->query("SELECT r.*, i.name as product_name, u.username as staff_name FROM inventory_returns r LEFT JOIN inventory i ON r.product_code = i.product_code LEFT JOIN users u ON r.returned_by = u.id ORDER BY r.created_at DESC");
    $allReturns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $allReturns = []; }

$totalRefund = 0; $totalItems = 0;
foreach($allReturns as $r) { if ($r['status'] !== 'rejected') { $totalRefund += $r['refund_amount']; $totalItems += $r['return_pieces']; } }
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>রিটার্ন হিস্ট্রি — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme-toggle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button class="sk-iconbtn" onclick="skToggleDrawer()" aria-label="Menu"><i class="fas fa-bars"></i></button>
        <a href="admin_inventory_control.php" class="sk-iconbtn"><i class="fas fa-chevron-left"></i></a>
    </div>
    <div class="sk-appbar__title"><span class="dot"></span> Return History</div>
    <div class="sk-appbar__right">
        <button class="sk-iconbtn" onclick="window.print()" title="Print"><i class="fas fa-print"></i></button>
    </div>
</header>

<div class="sk-overlay" id="skOverlay" onclick="skToggleDrawer()"></div>
<aside class="sk-drawer" id="skDrawer">
    <div class="sk-drawer__head">
        <button class="sk-drawer__close" onclick="skToggleDrawer()"><i class="fas fa-times"></i></button>
        <img src="logo.png" class="sk-drawer__logo" onerror="this.style.display='none'">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">RETURN AUDIT</div>
    </div>
    <div class="sk-drawer__section">Menu</div>
    <nav class="sk-drawer__grid">
        <a href="inventory_dashboard.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-th-large"></i></span><span class="sk-drawer__label">Dashboard</span></a>
        <a href="admin_inventory_control.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-shield-alt"></i></span><span class="sk-drawer__label">Admin</span></a>
        <a href="return_product.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-undo-alt"></i></span><span class="sk-drawer__label">Return</span></a>
        <a href="admin_return_history.php" class="sk-drawer__item active"><span class="sk-drawer__icon"><i class="fas fa-history"></i></span><span class="sk-drawer__label">Returns Log</span></a>
        <a href="inventory_sales_history.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-receipt"></i></span><span class="sk-drawer__label">Sales</span></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></span><span class="sk-drawer__label">POS</span></a>
    </nav>
</aside>

<main class="sk-container">

    <div class="sk-banner"><img src="banner.jpg" onerror="this.style.display='none'"></div>

    <div class="sk-stats sk-stats--2" style="margin-bottom:.875rem;">
        <div class="sk-stat sk-stat--accent">
            <div class="sk-row sk-row--between"><span class="sk-stat__icon"><i class="fas fa-undo-alt"></i></span><span class="sk-pill sk-pill--accent">Returns</span></div>
            <div class="sk-stat__val"><?php echo number_format($totalItems); ?> <small>PCS</small></div>
            <div class="sk-stat__lbl">Total Quantity</div>
        </div>
        <div class="sk-stat sk-stat--success">
            <div class="sk-row sk-row--between"><span class="sk-stat__icon"><i class="fas fa-hand-holding-usd"></i></span><span class="sk-pill sk-pill--success">Refund</span></div>
            <div class="sk-stat__val">৳<?php echo number_format($totalRefund, 0); ?></div>
            <div class="sk-stat__lbl">Total Paid Back</div>
        </div>
    </div>

    <div class="sk-card" style="margin-bottom:.875rem;">
        <div class="sk-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="historySearch" placeholder="ইনভয়েস বা প্রোডাক্ট কোড..." class="sk-input sk-input--icon">
        </div>
    </div>

    <div class="sk-section-title">
        <h2><i class="fas fa-list"></i> Return Records</h2>
        <span class="sk-sub">Full Audit Log</span>
    </div>

    <div id="historyList" style="display:flex; flex-direction:column; gap:.75rem;">
        <?php if(empty($allReturns)): ?>
            <div class="sk-card sk-empty"><i class="fas fa-folder-open"></i><p>No return records found!</p></div>
        <?php endif; ?>
        <?php foreach($allReturns as $row): 
            $statusClr = ($row['status'] == 'approved') ? 'var(--sk-success)' : (($row['status'] == 'rejected') ? 'var(--sk-muted-2)' : 'var(--sk-warn)');
        ?>
        <div class="sk-card search-item" style="position:relative; overflow:hidden; padding-left:1.125rem;">
            <span style="position:absolute; left:0; top:0; bottom:0; width:.25rem; background:<?php echo $statusClr; ?>;"></span>
            <div class="sk-row sk-row--between" style="align-items:flex-start; margin-bottom:.625rem;">
                <div class="sk-row">
                    <div style="background:var(--sk-grad-ink); color:#fff; width:38px; height:38px; border-radius:.5rem; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.7rem;">#<?php echo $row['id']; ?></div>
                    <div>
                        <div style="font-weight:800; font-size:.85rem; color:var(--sk-ink); letter-spacing:.025em;"><?php echo $row['invoice_no']; ?></div>
                        <div style="font-size:.7rem; font-weight:600; color:var(--sk-muted); margin-top:.125rem;"><i class="far fa-calendar-alt"></i> <?php echo date('d M Y | h:i A', strtotime($row['created_at'])); ?></div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:800; font-size:1rem; <?php echo ($row['status'] == 'rejected') ? 'color:var(--sk-muted-2); text-decoration:line-through;' : 'color:var(--sk-success);'; ?>">৳<?php echo number_format($row['refund_amount'], 2); ?></div>
                    <div style="font-size:.65rem; font-weight:700; color:var(--sk-muted); text-transform:uppercase; letter-spacing:.05em;">Refund</div>
                </div>
            </div>
            <div style="background:var(--sk-surface-2); border:1px solid var(--sk-line-2); border-radius:.5rem; padding:.625rem .75rem; margin-bottom:.625rem;">
                <div class="sk-row sk-row--between">
                    <div>
                        <div style="font-size:.65rem; font-weight:700; color:var(--sk-muted); letter-spacing:.05em; text-transform:uppercase;">Product</div>
                        <div style="font-weight:800; font-size:.95rem; color:var(--sk-ink); margin-top:.125rem;"><?php echo $row['product_name'] ?: 'Unknown'; ?></div>
                        <div style="font-size:.75rem; font-weight:700; color:var(--sk-primary); margin-top:.125rem;" class="sk-mono"><?php echo $row['product_code']; ?></div>
                    </div>
                    <div style="text-align:center; background:var(--sk-surface); border:1px solid var(--sk-line); padding:.375rem .875rem; border-radius:.5rem;">
                        <div style="font-weight:800; font-size:1.1rem; color:var(--sk-ink);"><?php echo $row['return_pieces']; ?></div>
                        <div style="font-size:.6rem; font-weight:700; color:var(--sk-muted); letter-spacing:.05em; text-transform:uppercase;">QTY</div>
                    </div>
                </div>
            </div>
            <div class="sk-row sk-row--between" style="margin-bottom:.625rem;">
                <span class="sk-pill sk-pill--ghost"><i class="fas fa-user-tie"></i> <?php echo $row['staff_name'] ?: 'System'; ?></span>
                <?php if(!empty($row['note'])): ?>
                <button type="button" onclick="showNote('<?php echo addslashes($row['note']); ?>')" class="sk-btn sk-btn--ghost sk-btn--sm"><i class="fas fa-comment-dots"></i> Note</button>
                <?php endif; ?>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:.5rem; flex-wrap:wrap; border-top:1px dashed var(--sk-line); padding-top:.625rem;">
                <div style="display:flex; gap:.375rem; flex-wrap:wrap;">
                    <?php if($row['status'] == 'pending'): ?>
                        <button onclick="processReturn(<?php echo $row['id']; ?>, 'approve')" class="sk-btn sk-btn--success sk-btn--sm"><i class="fas fa-check"></i> Accept</button>
                        <button onclick="processReturn(<?php echo $row['id']; ?>, 'reject')" class="sk-btn sk-btn--warn sk-btn--sm"><i class="fas fa-times"></i> Cancel</button>
                    <?php endif; ?>
                    <button onclick="processReturn(<?php echo $row['id']; ?>, 'delete')" class="sk-btn sk-btn--danger sk-btn--sm"><i class="fas fa-trash-alt"></i> Delete</button>
                </div>
                <div>
                    <?php
                    if($row['status'] == 'approved') echo '<span class="sk-pill sk-pill--success"><i class="fas fa-check-circle"></i> Approved</span>';
                    elseif($row['status'] == 'rejected') echo '<span class="sk-pill sk-pill--ghost"><i class="fas fa-times-circle"></i> Canceled</span>';
                    else echo '<span class="sk-pill sk-pill--warn"><i class="fas fa-clock"></i> Pending</span>';
                    ?>
                </div>
            </div>
            <span class="hidden search-data" style="display:none;"><?php echo strtolower($row['invoice_no'].' '.$row['product_code'].' '.$row['product_name']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<script>
function skToggleDrawer() { document.getElementById('skDrawer').classList.toggle('open'); document.getElementById('skOverlay').classList.toggle('active'); }
const userCsrfToken = '<?php echo $csrfToken; ?>';

$(document).ready(function() {
    $("#historySearch").on("keyup", function() {
        let v = $(this).val().toLowerCase();
        $(".search-item").filter(function() { $(this).toggle($(this).find(".search-data").text().toLowerCase().indexOf(v) > -1); });
    });
});

function showNote(note) {
    Swal.fire({ title:'রিটার্ন নোট', text:note, icon:'info', confirmButtonText:'ঠিক আছে', confirmButtonColor:'#0d6efd' });
}

function processReturn(id, action) {
    let title, text, btn, color;
    if (action === 'approve') { title='অ্যাকসেপ্ট করবেন?'; text='স্টক অটো-আপডেট হবে।'; btn='হ্যাঁ, অ্যাকসেপ্ট'; color='#198754'; }
    else if (action === 'reject') { title='ক্যানসেল করবেন?'; text='স্টক/সেলস পরিবর্তন হবে না।'; btn='হ্যাঁ, ক্যানসেল'; color='#d97706'; }
    else { title='ভুলবশত বিক্রি বাতিল?'; text='সেলস থেকে টাকা/প্রফিট মাইনাস হবে, স্টকে ফেরত যাবে।'; btn='হ্যাঁ, ডিলিট'; color='#dc3545'; }
    Swal.fire({ title, text, icon:'warning', showCancelButton:true, confirmButtonColor:color, cancelButtonColor:'#6c757d', confirmButtonText:btn, cancelButtonText:'বাতিল' }).then(result => {
        if (result.isConfirmed) {
            $.ajax({
                url:'admin_return_history.php', type:'POST',
                data:{ ajax_action: action+'_return', csrf_token: userCsrfToken, return_id: id },
                dataType:'json',
                success: function(res) {
                    Swal.fire({ icon: res.status, title: res.status==='success'?'সফল!':'ব্যর্থ!', text:res.message, confirmButtonColor:'#0d6efd' }).then(() => { if(res.status==='success') location.reload(); });
                },
                error: function() { Swal.fire('Error!', 'সার্ভার এরর!', 'error'); }
            });
        }
    });
}
</script>
</body>
</html>
