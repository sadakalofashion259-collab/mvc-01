<?php
declare(strict_types=1);

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

class BusinessLogicException extends Exception {}
function logSystemError(Throwable $e): void {
    $logDir = __DIR__ . '/../Logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir.'/error_log.txt', "[".date('Y-m-d H:i:s')."] ".$e->getMessage()." | ".$e->getFile()." line ".$e->getLine().PHP_EOL, FILE_APPEND);
}

$isAjax = isset($_POST['ajax_action']);

$lastActivity = $_SESSION['last_activity'] ?? null;
if ($lastActivity !== null && is_int($lastActivity) && (time() - $lastActivity > 1200)) {
    session_unset(); session_destroy();
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'session_expired','message'=>'Session Expired!']); exit; }
    echo "<script>alert('Session Expired!'); window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'session_expired','message'=>'Access Denied!']); exit; }
    echo "<script>alert('Access Denied! Admin Only.'); window.location.href='../dashboard.php';</script>"; exit;
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

include '../db_connect.php';
/** @var PDO $conn */
$adminUserId = isset($_SESSION['user_id']) && is_scalar($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
date_default_timezone_set('Asia/Dhaka');

interface AdminRepositoryInterface {
    public function getPendingReturnsCount(): int;
    public function getEverNetProfit(): float;
    public function getPeriodicStats(string $startDate, string $endDate): array;
    public function getCurrentInventoryStats(): array;
    public function deleteInvoice(string $invoiceNo): void;
    public function updateSaleItemPrice(int $saleId, string $productCode, float $newSellPrice): void;
    public function updateProduct(string $code, string $name, float $buy, float $cost, float $cash): void;
    public function adjustStock(string $code, string $type, int $pieces, string $note, int $adminId): void;
    public function approveReturn(int $returnId): void;
    public function rejectReturn(int $returnId): void;
    public function toggleProductStatus(string $code, string $status): void;
    public function getPnlLedger(string $search, string $monthFilter, int $limit, int $offset): array;
    public function getPnlLedgerCount(string $search, string $monthFilter): int;
}

class AdminRepository implements AdminRepositoryInterface {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function getPendingReturnsCount(): int { $s = $this->db->prepare("SELECT COUNT(*) FROM inventory_returns WHERE status='pending'"); $s->execute(); return (int)$s->fetchColumn(); }
    public function getEverNetProfit(): float {
        $s = $this->db->prepare("SELECT SUM(total_profit) FROM inventory_sales"); $s->execute(); $a = (float)$s->fetchColumn();
        $s = $this->db->prepare("SELECT SUM(return_profit) FROM inventory_returns WHERE status='approved'"); $s->execute(); $b = (float)$s->fetchColumn();
        return $a - $b;
    }
    public function getPeriodicStats(string $startDate, string $endDate): array {
        $stats = ['net_profit'=>0.0,'ret_pcs'=>0,'ret_amount'=>0.0,'ret_profit_ded'=>0.0,'sales_pcs'=>0,'sales_buy_val'=>0.0,'sales_cost_val'=>0.0,'sales_total_bill'=>0.0];
        $s = $this->db->prepare("SELECT SUM(i.pieces) as pcs, SUM(i.buy_price*i.pieces) as buy_val, SUM(i.cost*i.pieces) as cost_val, SUM(i.sell_price*i.pieces) as sell_val, SUM(i.profit*i.pieces) as prof FROM inventory_sale_items i JOIN inventory_sales s ON i.sale_id=s.id WHERE s.created_at>=? AND s.created_at<=?");
        $s->execute([$startDate, $endDate]); $sData = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        $s = $this->db->prepare("SELECT SUM(return_pieces) as pcs, SUM(refund_amount) as val, SUM(return_profit) as prof FROM inventory_returns WHERE created_at>=? AND created_at<=? AND status='approved'");
        $s->execute([$startDate, $endDate]); $rData = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        $stats['ret_pcs'] = (int)($rData['pcs'] ?? 0);
        $stats['ret_amount'] = (float)($rData['val'] ?? 0);
        $stats['ret_profit_ded'] = (float)($rData['prof'] ?? 0);
        $stats['sales_pcs'] = (int)($sData['pcs'] ?? 0) - $stats['ret_pcs'];
        $stats['sales_buy_val'] = (float)($sData['buy_val'] ?? 0);
        $stats['sales_cost_val'] = (float)($sData['cost_val'] ?? 0);
        $stats['sales_total_bill'] = (float)($sData['sell_val'] ?? 0) - $stats['ret_amount'];
        $stats['net_profit'] = (float)($sData['prof'] ?? 0) - $stats['ret_profit_ded'];
        return $stats;
    }
    public function getCurrentInventoryStats(): array {
        $s = $this->db->prepare("SELECT COUNT(id) as items, SUM(pieces) as pcs, SUM(buy_price*pieces) as b_val, SUM(cost*pieces) as c_val, SUM((buy_price+cost)*pieces) as t_val FROM inventory");
        $s->execute(); return $s->fetch(PDO::FETCH_ASSOC) ?: ['items'=>0,'pcs'=>0,'b_val'=>0.0,'c_val'=>0.0,'t_val'=>0.0];
    }
    public function deleteInvoice(string $invoiceNo): void {
        $c = $this->db->prepare("SELECT COUNT(*) FROM inventory_returns WHERE invoice_no=? AND status!='rejected'");
        $c->execute([$invoiceNo]);
        if ($c->fetchColumn() > 0) throw new BusinessLogicException("এই ইনভয়েস থেকে পণ্য রিটার্ন নেওয়া হয়েছে! শুধুমাত্র রেট এডিট করতে পারবেন।");
        $s = $this->db->prepare("SELECT id FROM inventory_sales WHERE invoice_no=?"); $s->execute([$invoiceNo]); $sale = $s->fetch(PDO::FETCH_ASSOC);
        if(!$sale) throw new BusinessLogicException("ইনভয়েস পাওয়া যায়নি!");
        $saleId = $sale['id'];
        $i = $this->db->prepare("SELECT product_code, pieces FROM inventory_sale_items WHERE sale_id=?"); $i->execute([$saleId]);
        $items = $i->fetchAll(PDO::FETCH_ASSOC);
        $u = $this->db->prepare("UPDATE inventory SET pieces=pieces+? WHERE product_code=?");
        foreach($items as $it) if ($it['pieces'] > 0) $u->execute([$it['pieces'], $it['product_code']]);
        $this->db->prepare("DELETE FROM inventory_sale_items WHERE sale_id=?")->execute([$saleId]);
        $this->db->prepare("DELETE FROM inventory_sales WHERE id=?")->execute([$saleId]);
    }
    public function updateSaleItemPrice(int $saleId, string $productCode, float $newSellPrice): void {
        $s = $this->db->prepare("SELECT buy_price, cost FROM inventory_sale_items WHERE sale_id=? AND product_code=? LIMIT 1");
        $s->execute([$saleId, $productCode]); $item = $s->fetch(PDO::FETCH_ASSOC);
        if (!$item) throw new BusinessLogicException("পণ্যটি পাওয়া যায়নি!");
        $minPrice = (float)$item['buy_price'] + (float)$item['cost'];
        if ($newSellPrice < $minPrice) throw new BusinessLogicException("বিক্রয় মূল্য (৳$minPrice) এর সমান বা বেশি হতে হবে!");
        $newProfit = $newSellPrice - $minPrice;
        $this->db->prepare("UPDATE inventory_sale_items SET sell_price=?, profit=? WHERE sale_id=? AND product_code=?")->execute([$newSellPrice, $newProfit, $saleId, $productCode]);
        $c = $this->db->prepare("SELECT SUM(sell_price*pieces) as t_sell, SUM(profit*pieces) as t_prof FROM inventory_sale_items WHERE sale_id=?"); $c->execute([$saleId]);
        $t = $c->fetch(PDO::FETCH_ASSOC);
        $this->db->prepare("UPDATE inventory_sales SET total_sell_amount=?, total_profit=? WHERE id=?")->execute([$t['t_sell'], $t['t_prof'], $saleId]);
    }
    public function updateProduct(string $code, string $name, float $buy, float $cost, float $cash): void {
        $this->db->prepare("UPDATE inventory SET name=?, buy_price=?, cost=?, cash_sell=? WHERE product_code=?")->execute([$name, $buy, $cost, $cash, $code]);
    }
    public function adjustStock(string $code, string $type, int $pieces, string $note, int $adminId): void {
        if ($type === 'increase') $u = $this->db->prepare("UPDATE inventory SET pieces=pieces+? WHERE product_code=?");
        else {
            $c = $this->db->prepare("SELECT pieces FROM inventory WHERE product_code=?"); $c->execute([$code]);
            if ((int)$c->fetchColumn() < $pieces) throw new BusinessLogicException("স্টকে পর্যাপ্ত পণ্য নেই!");
            $u = $this->db->prepare("UPDATE inventory SET pieces=pieces-? WHERE product_code=?");
        }
        $u->execute([$pieces, $code]);
        $this->db->prepare("INSERT INTO inventory_adjustments (product_code, adjustment_type, pieces, note, adjusted_by) VALUES (?, ?, ?, ?, ?)")->execute([$code, $type, $pieces, $note, $adminId]);
    }
    public function approveReturn(int $returnId): void {
        $c = $this->db->prepare("SELECT * FROM inventory_returns WHERE id=? AND status='pending'"); $c->execute([$returnId]);
        $r = $c->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw new BusinessLogicException("ইতিমধ্যে প্রসেস করা হয়েছে!");
        $this->db->prepare("UPDATE inventory_returns SET status='approved' WHERE id=?")->execute([$returnId]);
        $this->db->prepare("UPDATE inventory SET pieces=pieces+? WHERE product_code=?")->execute([(int)$r['return_pieces'], $r['product_code']]);
    }
    public function rejectReturn(int $returnId): void {
        $s = $this->db->prepare("UPDATE inventory_returns SET status='rejected' WHERE id=? AND status='pending'");
        if (!$s->execute([$returnId]) || $s->rowCount() === 0) throw new BusinessLogicException('রিটার্ন বাতিল করা যায়নি!');
    }
    public function toggleProductStatus(string $code, string $status): void {
        $s = $this->db->prepare("UPDATE inventory SET status=? WHERE product_code=?");
        if (!$s->execute([$status, $code])) throw new BusinessLogicException('স্ট্যাটাস আপডেট করা যায়নি!');
    }
    public function getPnlLedgerCount(string $search, string $monthFilter): int {
        $sql = "SELECT COUNT(DISTINCT s.id) FROM inventory_sales s LEFT JOIN inventory_sale_items i ON s.id=i.sale_id WHERE 1=1"; $params = [];
        if ($monthFilter) { $sql .= " AND s.created_at>=? AND s.created_at<=?"; $params[] = $monthFilter.'-01 00:00:00'; $params[] = date('Y-m-t 23:59:59', strtotime($monthFilter.'-01')); }
        if ($search) { $sql .= " AND (s.invoice_no LIKE ? OR i.product_code LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $s = $this->db->prepare($sql); $s->execute($params); return (int)$s->fetchColumn();
    }
    public function getPnlLedger(string $search, string $monthFilter, int $limit, int $offset): array {
        $sql = "SELECT s.invoice_no, s.created_at, i.product_code, i.sell_price, i.buy_price, i.cost, i.pieces, i.profit as unit_profit, inv.name as product_name, inv.image_path, s.id as sid, u.username as sold_by_user,
            COALESCE(ret_qty.returned_qty, 0) as returned_qty,
            COALESCE(ret_flag.has_return, 0) as invoice_has_return
            FROM inventory_sale_items i
            JOIN inventory_sales s ON i.sale_id=s.id
            LEFT JOIN inventory inv ON i.product_code=inv.product_code
            LEFT JOIN users u ON s.sold_by=u.id
            LEFT JOIN (SELECT invoice_no, product_code, SUM(return_pieces) as returned_qty FROM inventory_returns WHERE status='approved' GROUP BY invoice_no, product_code) ret_qty ON ret_qty.invoice_no=s.invoice_no AND ret_qty.product_code=i.product_code
            LEFT JOIN (SELECT invoice_no, COUNT(id) as has_return FROM inventory_returns WHERE status IN ('pending','approved') GROUP BY invoice_no) ret_flag ON ret_flag.invoice_no=s.invoice_no
            WHERE 1=1";
        $params = [];
        if ($monthFilter) { $sql .= " AND s.created_at>=? AND s.created_at<=?"; $params[] = $monthFilter.'-01 00:00:00'; $params[] = date('Y-m-t 23:59:59', strtotime($monthFilter.'-01')); }
        if ($search) { $sql .= " AND (s.invoice_no LIKE ? OR i.product_code LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $sql .= " ORDER BY s.created_at DESC LIMIT ".(int)$limit." OFFSET ".(int)$offset;
        $s = $this->db->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}

$repo = new AdminRepository($conn);
$todayStart = date('Y-m-d 00:00:00'); $todayEnd = date('Y-m-d 23:59:59'); $monthStart = date('Y-m-01 00:00:00');
$pendingReturnsCount = 0; $everNetProfit = 0.0; $monthStats = []; $todayStats = []; $curStock = [];
try {
    $pendingReturnsCount = $repo->getPendingReturnsCount();
    $everNetProfit = $repo->getEverNetProfit();
    $monthStats = $repo->getPeriodicStats($monthStart, $todayEnd);
    $todayStats = $repo->getPeriodicStats($todayStart, $todayEnd);
    $curStock = $repo->getCurrentInventoryStats();
} catch (Throwable $e) { logSystemError($e); }

$monthNetProfit = (float)($monthStats['net_profit'] ?? 0);
$monthRetPcs = (int)($monthStats['ret_pcs'] ?? 0);
$monthRetProfitDed = (float)($monthStats['ret_profit_ded'] ?? 0);
$todayNetProfit = (float)($todayStats['net_profit'] ?? 0);
$todayRetPcs = (int)($todayStats['ret_pcs'] ?? 0);
$todayRetProfitDed = (float)($todayStats['ret_profit_ded'] ?? 0);
$todaySalesPcs = (int)($todayStats['sales_pcs'] ?? 0);
$todaySalesBuyVal = (float)($todayStats['sales_buy_val'] ?? 0);
$todaySalesCostVal = (float)($todayStats['sales_cost_val'] ?? 0);
$todaySalesTotalBill = (float)($todayStats['sales_total_bill'] ?? 0);
$curItems = (int)($curStock['items'] ?? 0);
$curPcs = (int)($curStock['pcs'] ?? 0);
$curBuyVal = (float)($curStock['b_val'] ?? 0);
$curCostVal = (float)($curStock['c_val'] ?? 0);
$curTotalVal = (float)($curStock['t_val'] ?? 0);

if ($isAjax) {
    ob_clean(); header('Content-Type: application/json');
    $postCsrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($postCsrf === '' || !hash_equals($csrfToken, $postCsrf)) { echo json_encode(['status'=>'error','message'=>'টোকেন মিসম্যাচ!']); exit; }
    $action = $_POST['ajax_action'];

    if ($action === 'delete_invoice') {
        try {
            $inv = trim((string)($_POST['invoice_no'] ?? '')); if(empty($inv)) throw new BusinessLogicException('ইনভয়েস নাম্বার নেই!');
            $conn->beginTransaction(); $repo->deleteInvoice($inv); $conn->commit();
            echo json_encode(['status'=>'success','message'=>'মেমো ডিলিট এবং পণ্য স্টকে ফেরত!']); exit;
        } catch (BusinessLogicException $e) { if ($conn->inTransaction()) $conn->rollBack(); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); logSystemError($e); echo json_encode(['status'=>'error','message'=>'সিস্টেম ত্রুটি!']); exit; }
    }

    if ($action === 'update_sale_item_price') {
        try {
            $saleId = (int)($_POST['sale_id'] ?? 0); $pCode = trim((string)($_POST['product_code'] ?? '')); $nsp = (float)($_POST['new_sell_price'] ?? 0);
            if ($saleId <= 0 || empty($pCode) || $nsp < 0) throw new BusinessLogicException('সঠিক তথ্য দিন!');
            $conn->beginTransaction(); $repo->updateSaleItemPrice($saleId, $pCode, $nsp); $conn->commit();
            echo json_encode(['status'=>'success','message'=>'বিক্রয় মূল্য আপডেট ও লেজার রি-ক্যালকুলেট!']); exit;
        } catch (BusinessLogicException $e) { if ($conn->inTransaction()) $conn->rollBack(); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); logSystemError($e); echo json_encode(['status'=>'error','message'=>'সিস্টেম ত্রুটি!']); exit; }
    }

    if ($action === 'update_product') {
        try {
            $en = trim((string)($_POST['edit_name'] ?? '')); $eb = (float)($_POST['edit_buy'] ?? 0); $ec = (float)($_POST['edit_cost'] ?? 0); $eca = (float)($_POST['edit_cash'] ?? 0); $ecd = trim((string)($_POST['edit_code'] ?? ''));
            if (empty($en) || empty($ecd)) throw new BusinessLogicException('নাম ও কোড দিন!');
            $repo->updateProduct($ecd, $en, $eb, $ec, $eca);
            echo json_encode(['status'=>'success','message'=>'পণ্য আপডেট!']); exit;
        } catch (BusinessLogicException $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) { logSystemError($e); echo json_encode(['status'=>'error','message'=>'সিস্টেম ত্রুটি!']); exit; }
    }

    if ($action === 'adjust_stock') {
        try {
            $apc = trim((string)($_POST['product_code'] ?? '')); $at = trim((string)($_POST['adj_type'] ?? '')); $ap = (int)($_POST['pieces'] ?? 0); $an = trim((string)($_POST['note'] ?? ''));
            if (!in_array($at, ['increase','decrease']) || $ap <= 0 || empty($an) || empty($apc)) throw new BusinessLogicException('সঠিক পরিমাণ ও কারণ দিন!');
            $conn->beginTransaction(); $repo->adjustStock($apc, $at, $ap, $an, $adminUserId); $conn->commit();
            echo json_encode(['status'=>'success','message'=>'স্টক আপডেট!']); exit;
        } catch (BusinessLogicException $e) { if ($conn->inTransaction()) $conn->rollBack(); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); logSystemError($e); echo json_encode(['status'=>'error','message'=>'সিস্টেম ত্রুটি!']); exit; }
    }

    if ($action === 'approve_return') {
        try {
            $rid = (int)($_POST['return_id'] ?? 0); if ($rid <= 0) throw new BusinessLogicException('Invalid');
            $conn->beginTransaction(); $repo->approveReturn($rid); $conn->commit();
            echo json_encode(['status'=>'success','message'=>'অ্যাপ্রুভ! স্টক যোগ হয়েছে।']); exit;
        } catch (BusinessLogicException $e) { if ($conn->inTransaction()) $conn->rollBack(); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); logSystemError($e); echo json_encode(['status'=>'error','message'=>'সিস্টেম ত্রুটি!']); exit; }
    }
    if ($action === 'reject_return') {
        try {
            $rid = (int)($_POST['return_id'] ?? 0); if ($rid <= 0) throw new BusinessLogicException('Invalid');
            $repo->rejectReturn($rid);
            echo json_encode(['status'=>'success','message'=>'রিটার্ন বাতিল!']); exit;
        } catch (BusinessLogicException $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) { logSystemError($e); echo json_encode(['status'=>'error','message'=>'সিস্টেম ত্রুটি!']); exit; }
    }
    if ($action === 'toggle_product_status') {
        try {
            $pc = trim((string)($_POST['product_code'] ?? '')); $ns = trim((string)($_POST['new_status'] ?? ''));
            if (empty($pc) || !in_array($ns, ['active','inactive'])) throw new BusinessLogicException('সঠিক ডেটা দিন!');
            $repo->toggleProductStatus($pc, $ns);
            echo json_encode(['status'=>'success','message'=>$ns==='inactive'?'পণ্যটি ইনঅ্যাকটিভ।':'পণ্যটি অ্যাকটিভ।']); exit;
        } catch (BusinessLogicException $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) { logSystemError($e); echo json_encode(['status'=>'error','message'=>'সিস্টেম ত্রুটি!']); exit; }
    }

    if ($action === 'load_tab_data') {
        try {
            $tab = trim((string)($_POST['tab'] ?? '')); $search = trim((string)($_POST['search'] ?? '')); $month = trim((string)($_POST['month_filter'] ?? ''));
            $page = max(1, (int)($_POST['page'] ?? 1));
            $viewMode = isset($_POST['product_view_mode']) && in_array($_POST['product_view_mode'], ['active','inactive']) ? $_POST['product_view_mode'] : 'active';
            $per = 30; $off = ($page-1)*$per; $h = '';

            // ── PNL LEDGER ──
            if ($tab === 'pnl_ledger') {
                $cnt = $repo->getPnlLedgerCount($search, $month);
                $tp = (int)ceil($cnt / $per);
                $rows = $repo->getPnlLedger($search, $month, $per, $off);
                $byDate = [];
                foreach($rows as $r) { $k = date('Y-m-d', strtotime($r['created_at'])); $byDate[$k][] = $r; }
                if(count($byDate) > 0) {
                    foreach($byDate as $date => $items) {
                        $dPcs=0; $dSales=0.0; $dProfit=0.0;
                        foreach($items as $it) { $np = (int)$it['pieces'] - (int)$it['returned_qty']; if($np > 0) { $dPcs += $np; $dSales += (float)$it['sell_price']*$np; $dProfit += (float)$it['unit_profit']*$np; } }
                        $rs = $conn->prepare("SELECT SUM(refund_amount) FROM inventory_returns WHERE DATE(created_at)=? AND status='approved'"); $rs->execute([$date]); $dR = (float)$rs->fetchColumn();
                        $retChip = $dR > 0 ? "<span class='sk-pill sk-pill--danger'>রিটার্ন: ৳".number_format($dR)."</span>" : "";
                        $h .= "<div class='sk-card sk-card--accent' style='margin-top:.875rem; margin-bottom:.5rem; padding:.625rem .875rem;'>";
                        $h .= "  <div style='display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:.5rem;'>";
                        $h .= "    <div style='font-weight:800; font-size:.85rem; color:var(--sk-primary-ink); display:flex; align-items:center; gap:.5rem;'><i class='far fa-calendar-alt'></i> ".date('d M Y · l', strtotime($date))."</div>";
                        $h .= "    <div style='display:flex; flex-wrap:wrap; gap:.25rem;'>";
                        $h .= "      <span class='sk-pill sk-pill--info'>{$dPcs} পিস</span>";
                        $h .= "      <span class='sk-pill sk-pill--success'>৳".number_format($dSales)."</span>";
                        $h .= "      <span class='sk-pill sk-pill--warn'>মুনাফা: ৳".number_format($dProfit)."</span>";
                        $h .= "      {$retChip}";
                        $h .= "    </div>";
                        $h .= "  </div>";
                        $h .= "</div>";
                        $h .= "<div class='sk-table-wrap' style='margin-bottom:.875rem;'><table class='sk-table'><thead><tr><th>তথ্য ও পণ্য</th><th>পরিমাণ ও বিল</th><th style='text-align:center;'>অ্যাকশন</th></tr></thead><tbody>";
                        foreach($items as $it) {
                            $imgRaw = (string)($it['image_path'] ?? '');
                            $img = !empty($imgRaw) ? htmlspecialchars($imgRaw, ENT_QUOTES, 'UTF-8') : '';
                            $imgHtml = $img !== '' ? "<img src='{$img}' style='width:36px; height:36px; object-fit:cover; border-radius:.375rem; border:1px solid var(--sk-line); cursor:pointer;' onclick=\"openImageModal('{$img}')\">" : "<div style='width:36px; height:36px; border-radius:.375rem; background:var(--sk-surface-3); display:inline-flex; align-items:center; justify-content:center; color:var(--sk-muted);'><i class='fas fa-image'></i></div>";
                            $netPcs = (int)$it['pieces'] - (int)$it['returned_qty'];
                            $seller = htmlspecialchars((string)($it['sold_by_user'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');
                            $itAmt = (float)$it['sell_price'] * $netPcs;
                            $itCost = ((float)$it['buy_price'] + (float)$it['cost']) * $netPcs;
                            $itProf = (float)$it['unit_profit'] * $netPcs;
                            $profHtml = $itProf > 0 ? "<span class='sk-pill sk-pill--success'>+৳".number_format($itProf)."</span><div style='font-size:.65rem; color:var(--sk-muted); margin-top:.125rem;'>প্রফিট</div>"
                                : ($itProf < 0 ? "<span class='sk-pill sk-pill--danger'>-৳".number_format(abs($itProf))."</span><div style='font-size:.65rem; color:var(--sk-muted); margin-top:.125rem;'>লস</div>"
                                : "<span class='sk-pill sk-pill--ghost'>৳0</span>");
                            $qtyHtml = (int)$it['returned_qty'] > 0
                                ? "<div style='font-size:.65rem; color:var(--sk-danger); font-weight:700;'>রিটার্ন: {$it['returned_qty']}</div><div><span class='sk-pill sk-pill--ink' style='margin-top:.125rem;'>নিট: {$netPcs} পিস</span></div>"
                                : "<span class='sk-pill sk-pill--info'>{$it['pieces']} পিস</span>";
                            $pcSafe = htmlspecialchars((string)$it['product_code'], ENT_QUOTES, 'UTF-8');
                            $invSafe = htmlspecialchars((string)$it['invoice_no'], ENT_QUOTES, 'UTF-8');
                            $editBtn = "<button onclick=\"openSalePriceEditModal({$it['sid']}, '{$invSafe}', '{$pcSafe}', {$it['sell_price']})\" class='sk-btn sk-btn--accent sk-btn--sm' style='margin-top:.375rem;'><i class='fas fa-edit'></i> প্রাইস</button>";
                            $delBtn = (int)$it['invoice_has_return'] > 0
                                ? "<button onclick=\"alert('রিটার্ন আছে, ডিলিট লকড!')\" class='sk-btn sk-btn--ghost sk-btn--sm' title='Locked' style='margin-left:.25rem;'><i class='fas fa-lock' style='color:var(--sk-danger);'></i></button>"
                                : "<button onclick=\"deleteInvoice('{$invSafe}')\" class='sk-btn sk-btn--danger sk-btn--sm' style='margin-left:.25rem;'><i class='fas fa-trash-alt'></i></button>";
                            $h .= "<tr>";
                            $h .= "<td style='vertical-align:top;'><div style='font-weight:800; color:var(--sk-primary); font-size:.8rem; margin-bottom:.25rem;'>{$invSafe} {$delBtn}</div><div style='display:flex; align-items:flex-start; gap:.5rem;'>{$imgHtml}<div><div style='color:var(--sk-success-ink); font-weight:700; font-size:.75rem;'>{$pcSafe}</div><div style='font-size:.65rem;'><span class='sk-pill sk-pill--ghost'><i class='fas fa-user'></i> {$seller}</span></div></div></div></td>";
                            $h .= "<td style='vertical-align:top;'>{$qtyHtml}<div style='font-size:.65rem; color:var(--sk-danger); font-weight:600; margin-top:.375rem;'>কেনা বিল: ৳".number_format($itCost)."</div><div style='font-size:.75rem; color:var(--sk-primary); font-weight:800;'>বিক্রি বিল: ৳".number_format($itAmt)."</div></td>";
                            $h .= "<td style='text-align:center; vertical-align:top;'>{$profHtml}{$editBtn}</td>";
                            $h .= "</tr>";
                        }
                        $h .= "</tbody></table></div>";
                    }
                } else $h .= "<div class='sk-empty'><i class='fas fa-folder-open'></i><p>কোনো প্রফিট লেজার ডেটা নেই।</p></div>";
                if ($tp > 1) {
                    $h .= "<div class='sk-card sk-row sk-row--between' style='margin-top:.75rem; padding:.5rem;'>";
                    $h .= "<button ".($page>1?"onclick='loadAdminData(".($page-1).")'":"disabled")." class='sk-btn sk-btn--ink sk-btn--sm'><i class='fas fa-chevron-left'></i> Prev</button>";
                    $h .= "<span class='sk-pager__info'>Page {$page} of {$tp}</span>";
                    $h .= "<button ".($page<$tp?"onclick='loadAdminData(".($page+1).")'":"disabled")." class='sk-btn sk-btn--ink sk-btn--sm'>Next <i class='fas fa-chevron-right'></i></button>";
                    $h .= "</div>";
                }
            }
            // ── RETURNS ──
            elseif ($tab === 'returns') {
                $tc = $conn->prepare("SELECT COUNT(*) FROM inventory_returns WHERE status!='rejected'"); $tc->execute(); $ti = $tc->fetchColumn(); $tp = (int)ceil($ti / $per);
                $rs = $conn->prepare("SELECT r.* FROM inventory_returns r WHERE r.status!='rejected' ORDER BY r.status DESC, r.created_at DESC LIMIT ".(int)$per." OFFSET ".(int)$off);
                $rs->execute(); $list = $rs->fetchAll(PDO::FETCH_ASSOC);

                $h .= '<div style="margin-bottom:.625rem; text-align:right;"><a href="admin_return_history.php" class="sk-btn sk-btn--ink sk-btn--sm"><i class="fas fa-history"></i> ফুল রিটার্ন ও অডিট লগ</a></div>';
                $h .= '<div class="sk-table-wrap"><table class="sk-table"><thead><tr><th>তারিখ ও INV</th><th>কোড ও পিস</th><th>রিফান্ড</th><th style="text-align:center;">অ্যাকশন</th></tr></thead><tbody>';
                if(count($list) > 0) {
                    foreach($list as $r) {
                        if ($r['status'] === 'pending') {
                            $sb = "<span class='sk-pill sk-pill--warn' style='margin-top:.25rem;'><i class='fas fa-clock'></i> Pending</span>";
                            $ab = "<div style='display:flex; flex-direction:column; gap:.25rem;'><button onclick=\"approveReturnRequest({$r['id']})\" class='sk-btn sk-btn--success sk-btn--sm'><i class='fas fa-check'></i> Approve</button><button onclick=\"rejectReturnRequest({$r['id']})\" class='sk-btn sk-btn--danger sk-btn--sm'><i class='fas fa-times'></i> Reject</button></div>";
                        } else {
                            $sb = "<span class='sk-pill sk-pill--success' style='margin-top:.25rem;'><i class='fas fa-check-circle'></i> Approved</span>";
                            $ab = "<span class='sk-pill sk-pill--ghost'><i class='fas fa-lock'></i> Locked</span>";
                        }
                        $bgClass = $r['status'] === 'pending' ? 'style="background:var(--sk-warn-soft);"' : '';
                        $inv = htmlspecialchars($r['invoice_no'], ENT_QUOTES, 'UTF-8');
                        $cd = htmlspecialchars($r['product_code'], ENT_QUOTES, 'UTF-8');
                        $nt = htmlspecialchars($r['note'], ENT_QUOTES, 'UTF-8');
                        $h .= "<tr {$bgClass}>";
                        $h .= "<td><div style='font-size:.65rem; color:var(--sk-muted);'>".date('d-M-y h:i A', strtotime($r['created_at']))."</div><div style='color:var(--sk-primary); font-weight:800; font-size:.8rem;'>{$inv}</div>{$sb}</td>";
                        $h .= "<td><div style='color:var(--sk-success-ink); font-weight:700; font-size:.8rem;'>{$cd}</div><div style='color:var(--sk-danger); font-weight:800; font-size:.75rem;'>রিটার্ন: {$r['return_pieces']} পিস</div></td>";
                        $h .= "<td><div style='color:var(--sk-warn-ink); font-weight:700; font-size:.75rem;'>৳".number_format((float)$r['refund_amount'])."</div><div style='font-size:.65rem; color:var(--sk-muted); margin-top:.125rem;'>{$nt}</div></td>";
                        $h .= "<td style='text-align:center;'>{$ab}</td>";
                        $h .= "</tr>";
                    }
                } else $h .= "<tr><td colspan='4'><div class='sk-empty'><i class='fas fa-inbox'></i><p>কোনো রিটার্ন নেই।</p></div></td></tr>";
                $h .= '</tbody></table></div>';
                if ($tp > 1) {
                    $h .= "<div class='sk-card sk-row sk-row--between' style='margin-top:.75rem; padding:.5rem;'>";
                    $h .= "<button ".($page>1?"onclick='loadAdminData(".($page-1).")'":"disabled")." class='sk-btn sk-btn--ink sk-btn--sm'><i class='fas fa-chevron-left'></i> Prev</button>";
                    $h .= "<span class='sk-pager__info'>Page {$page} of {$tp}</span>";
                    $h .= "<button ".($page<$tp?"onclick='loadAdminData(".($page+1).")'":"disabled")." class='sk-btn sk-btn--ink sk-btn--sm'>Next <i class='fas fa-chevron-right'></i></button>";
                    $h .= "</div>";
                }
            }
            // ── PRODUCTS ──
            elseif ($tab === 'products') {
                $is = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE status='inactive'"); $is->execute(); $inactCount = (int)$is->fetchColumn();
                $cs = "SELECT COUNT(*) FROM inventory i WHERE i.status=?";
                $bs = "SELECT i.*, c.name as cat_name, u.username as entry_by FROM inventory i LEFT JOIN categories c ON i.category_id=c.id LEFT JOIN users u ON i.added_by=u.id WHERE i.status=?";
                $qp = [$viewMode];
                if ($search) { $sc = " AND (i.product_code LIKE ? OR i.name LIKE ?)"; $bs .= $sc; $cs .= $sc; $qp[] = "%$search%"; $qp[] = "%$search%"; }
                $trs = $conn->prepare($cs); $trs->execute($qp); $ti = $trs->fetchColumn(); $tp = (int)ceil($ti / $per);
                $bs .= " ORDER BY i.id DESC LIMIT ".(int)$per." OFFSET ".(int)$off;
                $pr = $conn->prepare($bs); $pr->execute($qp); $plist = $pr->fetchAll(PDO::FETCH_ASSOC);

                $aBtnCls = $viewMode === 'active' ? 'sk-btn--accent' : 'sk-btn--ghost';
                $iBtnCls = $viewMode === 'inactive' ? 'sk-btn--danger' : 'sk-btn--ghost';
                $h .= "<div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:.625rem;'>";
                $h .= "  <div style='font-size:.7rem; font-weight:700; text-transform:uppercase; color:var(--sk-muted); letter-spacing:.05em;'>প্রোডাক্ট ভিউ:</div>";
                $h .= "  <div style='display:flex; gap:.375rem;'>";
                $h .= "    <button onclick='setProductView(\"active\")' class='sk-btn {$aBtnCls} sk-btn--sm'><i class='fas fa-box-open'></i> রানিং</button>";
                $h .= "    <button onclick='setProductView(\"inactive\")' class='sk-btn {$iBtnCls} sk-btn--sm'><i class='fas fa-ban'></i> ইনঅ্যাকটিভ <span class='sk-pill sk-pill--danger' style='margin-left:.25rem;'>{$inactCount}</span></button>";
                $h .= "  </div>";
                $h .= "</div>";

                $h .= '<div class="sk-table-wrap"><table class="sk-table"><thead><tr><th width="22%">ছবি ও এন্ট্রি</th><th width="36%">বিবরণ ও স্টক</th><th width="22%">মূল্য</th><th width="20%">অ্যাকশন</th></tr></thead><tbody>';
                if (count($plist) > 0) {
                    foreach($plist as $p) {
                        $imgRaw = (string)($p['image_path'] ?? '');
                        $img = !empty($imgRaw) ? htmlspecialchars($imgRaw, ENT_QUOTES, 'UTF-8') : '';
                        $imgHtml = $img !== '' ? "<img src='{$img}' style='width:48px; height:48px; object-fit:cover; border-radius:.375rem; border:1px solid var(--sk-line); cursor:pointer;' onclick=\"openImageModal('{$img}')\">" : "<div style='width:48px; height:48px; border-radius:.375rem; background:var(--sk-surface-3); display:inline-flex; align-items:center; justify-content:center; color:var(--sk-muted); margin:0 auto;'><i class='fas fa-image'></i></div>";
                        $enc = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                        $entry = htmlspecialchars((string)($p['entry_by'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
                        $pcSafe = htmlspecialchars((string)$p['product_code'], ENT_QUOTES, 'UTF-8');
                        $pnSafe = htmlspecialchars((string)$p['name'], ENT_QUOTES, 'UTF-8');
                        $pcatSafe = htmlspecialchars((string)($p['cat_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                        $statusBtn = $viewMode === 'active'
                            ? "<button onclick=\"toggleProdStatus('{$pcSafe}', 'inactive')\" class='sk-btn sk-btn--danger sk-btn--sm sk-btn--block'><i class='fas fa-ban'></i> Inactive</button>"
                            : "<button onclick=\"toggleProdStatus('{$pcSafe}', 'active')\" class='sk-btn sk-btn--success sk-btn--sm sk-btn--block'><i class='fas fa-check'></i> Active</button>";
                        $h .= "<tr>";
                        $h .= "<td style='text-align:center; vertical-align:top;'>{$imgHtml}<div style='margin-top:.25rem;'><span class='sk-pill sk-pill--ghost' style='font-size:.6rem;'><i class='fas fa-user'></i> {$entry}</span></div></td>";
                        $h .= "<td style='vertical-align:top;'><div style='color:var(--sk-success-ink); font-weight:800; font-size:.85rem;'>{$pcSafe}</div><div style='color:var(--sk-ink-2); font-size:.75rem; margin-top:.125rem;'>{$pnSafe}</div><div style='color:var(--sk-muted); font-size:.7rem;'>{$pcatSafe}</div><div style='margin-top:.375rem;'><span class='sk-pill sk-pill--info'>স্টক: {$p['pieces']}</span></div></td>";
                        $h .= "<td style='vertical-align:top;'><div style='display:flex; flex-direction:column; gap:.25rem;'>";
                        $h .= "<span class='sk-pill sk-pill--danger'>কেনা: ৳".number_format((float)$p['buy_price'])."</span>";
                        $h .= "<span class='sk-pill sk-pill--warn'>Cost: ৳".number_format((float)$p['cost'])."</span>";
                        $h .= "<span class='sk-pill sk-pill--accent'>ক্যাশ: ৳".number_format((float)$p['cash_sell'])."</span>";
                        $h .= "</div></td>";
                        $h .= "<td style='text-align:center; vertical-align:middle;'><div style='display:flex; flex-direction:column; gap:.25rem;'>";
                        $h .= "<button onclick=\"openProductEditModal({$enc})\" class='sk-btn sk-btn--accent sk-btn--sm sk-btn--block'><i class='fas fa-edit'></i> Edit</button>";
                        $h .= "<button onclick=\"openStockAdjustModal('{$pcSafe}', '{$pnSafe}')\" class='sk-btn sk-btn--warn sk-btn--sm sk-btn--block'><i class='fas fa-sliders-h'></i> Stock ±</button>";
                        $h .= "{$statusBtn}";
                        $h .= "</div></td>";
                        $h .= "</tr>";
                    }
                } else {
                    $msg = $viewMode === 'inactive' ? 'কোনো ইনঅ্যাকটিভ পণ্য নেই।' : 'কোনো রানিং পণ্য নেই।';
                    $h .= "<tr><td colspan='4'><div class='sk-empty'><i class='fas fa-box-open'></i><p>{$msg}</p></div></td></tr>";
                }
                $h .= '</tbody></table></div>';
                if ($tp > 1) {
                    $h .= "<div class='sk-card sk-row sk-row--between' style='margin-top:.75rem; padding:.5rem;'>";
                    $h .= "<button ".($page>1?"onclick='loadAdminData(".($page-1).")'":"disabled")." class='sk-btn sk-btn--ink sk-btn--sm'><i class='fas fa-chevron-left'></i> Prev</button>";
                    $h .= "<span class='sk-pager__info'>Page {$page} of {$tp}</span>";
                    $h .= "<button ".($page<$tp?"onclick='loadAdminData(".($page+1).")'":"disabled")." class='sk-btn sk-btn--ink sk-btn--sm'>Next <i class='fas fa-chevron-right'></i></button>";
                    $h .= "</div>";
                }
            }
            // ── FOLDERS ──
            elseif ($tab === 'folders') {
                $fs = $conn->prepare("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as month_val, DATE_FORMAT(created_at, '%b %Y') as month_label FROM inventory_sales ORDER BY month_val DESC");
                $fs->execute(); $folders = $fs->fetchAll(PDO::FETCH_ASSOC);
                $h .= '<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:.625rem;">';
                if (count($folders) > 0) {
                    foreach($folders as $f) {
                        $mv = htmlspecialchars((string)$f['month_val'], ENT_QUOTES, 'UTF-8');
                        $ml = htmlspecialchars((string)$f['month_label'], ENT_QUOTES, 'UTF-8');
                        $h .= "<div onclick=\"openFolderData('{$mv}', '{$ml}')\" class='sk-card' style='cursor:pointer; text-align:center; padding:1rem .5rem;'><i class='fas fa-folder' style='font-size:2rem; color:var(--sk-warn); margin-bottom:.5rem; display:block;'></i><span style='font-weight:800; font-size:.75rem; text-transform:uppercase; color:var(--sk-ink-2);'>{$ml}</span></div>";
                    }
                } else $h .= "<div style='grid-column:1/-1;' class='sk-empty'><i class='fas fa-folder-open'></i><p>ফোল্ডার নেই।</p></div>";
                $h .= '</div>';
            }
            echo json_encode(['html'=>$h]); exit;
        } catch (Throwable $e) { logSystemError($e); echo json_encode(['html'=>'<div class="sk-empty" style="color:var(--sk-danger);"><i class="fas fa-exclamation-triangle"></i><p>সার্ভার এরর!</p></div>']); exit; }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>অ্যাডমিন কন্ট্রোল — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme-toggle.js"></script>
    <style>
        .sk-tabs { display:flex; gap:.375rem; overflow-x:auto; padding:.375rem; background:var(--sk-surface); border:1px solid var(--sk-line); border-radius:.75rem; box-shadow:var(--sk-shadow-sm); margin-bottom:.625rem; }
        .sk-tabs::-webkit-scrollbar { display:none; }
        .sk-tabs .tab { display:inline-flex; align-items:center; gap:.375rem; white-space:nowrap; padding:.5rem .75rem; border-radius:.5rem; font-size:.75rem; font-weight:700; color:var(--sk-muted); background:transparent; border:0; cursor:pointer; text-decoration:none; font-family:inherit; transition:.12s; }
        .sk-tabs .tab:hover { background:var(--sk-surface-2); color:var(--sk-ink); }
        .sk-tabs .tab.on { background:var(--sk-grad-primary); color:#fff; box-shadow:var(--sk-shadow-ink); }
        .tab-badge { background:var(--sk-danger); color:#fff; font-size:.6rem; font-weight:800; padding:.125rem .4rem; border-radius:50rem; margin-left:.25rem; }
    </style>
</head>
<body>

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button class="sk-iconbtn" onclick="document.getElementById('skDrawer').classList.add('open'); document.getElementById('skOverlay').classList.add('active');"><i class="fas fa-bars"></i></button>
        <a href="inventory_dashboard.php" class="sk-iconbtn"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title"><span class="dot"></span> Admin Control</div>
    <div class="sk-appbar__right">
        <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<div class="sk-overlay" id="skOverlay" onclick="document.getElementById('skDrawer').classList.remove('open');this.classList.remove('active');"></div>
<aside class="sk-drawer" id="skDrawer">
    <div class="sk-drawer__head">
        <button class="sk-drawer__close" onclick="document.getElementById('skDrawer').classList.remove('open');document.getElementById('skOverlay').classList.remove('active');"><i class="fas fa-times"></i></button>
        <img src="logo.png" class="sk-drawer__logo" onerror="this.style.display='none'">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">ADMIN PANEL</div>
    </div>
    <div class="sk-drawer__section">Main</div>
    <div class="sk-drawer__grid">
        <a href="inventory_dashboard.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-th-large"></i></span><span class="sk-drawer__label">Dashboard</span></a>
        <a href="inventory.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-plus"></i></span><span class="sk-drawer__label">Add Item</span></a>
        <a href="Invantory_Items.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-box-open"></i></span><span class="sk-drawer__label">Items</span></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></span><span class="sk-drawer__label">POS</span></a>
        <a href="inventory_sales_history.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-receipt"></i></span><span class="sk-drawer__label">Sales</span></a>
        <a href="out_of_stock.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-exclamation-triangle"></i></span><span class="sk-drawer__label">Out Stock</span></a>
        <a href="return_product.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-undo"></i></span><span class="sk-drawer__label">Return</span></a>
    </div>
    <div class="sk-drawer__section">Admin</div>
    <div class="sk-drawer__grid">
        <a href="admin_inventory_control.php" class="sk-drawer__item active"><span class="sk-drawer__icon"><i class="fas fa-user-shield"></i></span><span class="sk-drawer__label">Control</span></a>
        <a href="admin_category_control.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-tags"></i></span><span class="sk-drawer__label">Category</span></a>
        <a href="admin_return_history.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-history"></i></span><span class="sk-drawer__label">Returns</span></a>
        <a href="daily_activity.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-shield-alt"></i></span><span class="sk-drawer__label">Activity</span></a>
        <a href="product_edit_history.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-edit"></i></span><span class="sk-drawer__label">Edit Log</span></a>
    </div>
</aside>

<main class="sk-container">

    <section class="sk-card sk-card--ink sk-card--pad-lg" style="margin-bottom:.75rem;">
        <div class="sk-row sk-row--between" style="border-bottom:1px solid rgba(255,255,255,.12); padding-bottom:.75rem; margin-bottom:.75rem;">
            <div>
                <div style="font-size:.7rem; color:rgba(255,255,255,.85); font-weight:700; letter-spacing:.12em; text-transform:uppercase; margin-bottom:.25rem;"><i class="fas fa-crown" style="color:#ffd54f;"></i> শুরু থেকে মুনাফা</div>
                <div style="font-size:1.5rem; font-weight:800; color:#86efac;">৳<?php echo number_format((float)$everNetProfit); ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:.65rem; color:rgba(255,255,255,.85); font-weight:700; letter-spacing:.12em; text-transform:uppercase; margin-bottom:.25rem;">আজকের মুনাফা</div>
                <div style="font-size:1.15rem; font-weight:800; color:#86efac;">৳<?php echo number_format((float)$todayNetProfit); ?></div>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.625rem;">
            <div style="background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:.5rem; padding:.625rem;">
                <div style="font-size:.65rem; color:#ffd54f; font-weight:700; letter-spacing:.1em; text-transform:uppercase; margin-bottom:.375rem; border-bottom:1px solid rgba(255,255,255,.08); padding-bottom:.25rem;">এই মাসে</div>
                <div class="sk-row sk-row--between">
                    <div><div style="font-size:.85rem; font-weight:800; color:#fff;">৳<?php echo number_format((float)$monthNetProfit); ?></div><div style="font-size:.6rem; color:rgba(255,255,255,.75); font-weight:700; text-transform:uppercase;">মুনাফা</div></div>
                    <div style="text-align:right; border-left:1px solid rgba(255,255,255,.12); padding-left:.5rem;">
                        <div style="font-size:.85rem; font-weight:800; color:#fca5a5;">৳<?php echo number_format((float)$monthRetProfitDed); ?></div>
                        <div style="font-size:.6rem; color:rgba(255,255,255,.75); font-weight:700; text-transform:uppercase;">রিটার্ন লস (<?php echo $monthRetPcs; ?>p)</div>
                    </div>
                </div>
            </div>
            <div style="background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:.5rem; padding:.625rem;">
                <div style="font-size:.65rem; color:#ffd54f; font-weight:700; letter-spacing:.1em; text-transform:uppercase; margin-bottom:.375rem; border-bottom:1px solid rgba(255,255,255,.08); padding-bottom:.25rem;">আজকে</div>
                <div class="sk-row sk-row--between">
                    <div><div style="font-size:.85rem; font-weight:800; color:#fff;">৳<?php echo number_format((float)$todayNetProfit); ?></div><div style="font-size:.6rem; color:rgba(255,255,255,.75); font-weight:700; text-transform:uppercase;">মুনাফা</div></div>
                    <div style="text-align:right; border-left:1px solid rgba(255,255,255,.12); padding-left:.5rem;">
                        <div style="font-size:.85rem; font-weight:800; color:#fca5a5;">৳<?php echo number_format((float)$todayRetProfitDed); ?></div>
                        <div style="font-size:.6rem; color:rgba(255,255,255,.75); font-weight:700; text-transform:uppercase;">রিটার্ন লস (<?php echo $todayRetPcs; ?>p)</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="sk-section-title"><h2><i class="fas fa-boxes"></i> ইনভেন্টরি স্টক</h2><span class="sk-sub">Live</span></div>
    <div class="sk-stats sk-stats--4" style="margin-bottom:.625rem;">
        <div class="sk-stat sk-stat--info"><div class="sk-stat__icon"><i class="fas fa-cube"></i></div><div class="sk-stat__lbl">সর্বমোট পিস</div><div class="sk-stat__val"><?php echo number_format($curPcs); ?></div></div>
        <div class="sk-stat sk-stat--accent"><div class="sk-stat__icon"><i class="fas fa-wallet"></i></div><div class="sk-stat__lbl">কেনা ভ্যালু</div><div class="sk-stat__val">৳<?php echo number_format($curBuyVal); ?></div></div>
        <div class="sk-stat sk-stat--warn"><div class="sk-stat__icon"><i class="fas fa-coins"></i></div><div class="sk-stat__lbl">কস্ট ভ্যালু</div><div class="sk-stat__val">৳<?php echo number_format($curCostVal); ?></div></div>
        <div class="sk-stat sk-stat--success"><div class="sk-stat__icon"><i class="fas fa-sack-dollar"></i></div><div class="sk-stat__lbl">টোটাল ভ্যালু</div><div class="sk-stat__val">৳<?php echo number_format($curTotalVal); ?></div></div>
    </div>
    <div class="sk-stat sk-stat--ink" style="margin-bottom:.875rem;">
        <div class="sk-row sk-row--between">
            <div><div class="sk-stat__lbl">আইটেম ধরণ</div><div class="sk-stat__val"><?php echo number_format($curItems); ?> <small>টি প্রোডাক্ট</small></div></div>
            <div class="sk-stat__icon" style="background:var(--sk-grad-brand);"><i class="fas fa-tags"></i></div>
        </div>
    </div>

    <div class="sk-section-title"><h2><i class="fas fa-cart-arrow-down"></i> আজকের সেলস</h2><span class="sk-sub">Today</span></div>
    <div class="sk-stats sk-stats--4" style="margin-bottom:.875rem;">
        <div class="sk-stat sk-stat--info"><div class="sk-stat__icon"><i class="fas fa-shopping-bag"></i></div><div class="sk-stat__lbl">বিক্রি পিস</div><div class="sk-stat__val"><?php echo number_format($todaySalesPcs); ?></div></div>
        <div class="sk-stat sk-stat--accent"><div class="sk-stat__icon"><i class="fas fa-wallet"></i></div><div class="sk-stat__lbl">কেনা দাম</div><div class="sk-stat__val">৳<?php echo number_format($todaySalesBuyVal); ?></div></div>
        <div class="sk-stat sk-stat--warn"><div class="sk-stat__icon"><i class="fas fa-coins"></i></div><div class="sk-stat__lbl">কস্ট দাম</div><div class="sk-stat__val">৳<?php echo number_format($todaySalesCostVal); ?></div></div>
        <div class="sk-stat sk-stat--success"><div class="sk-stat__icon"><i class="fas fa-money-bill-trend-up"></i></div><div class="sk-stat__lbl">সর্বমোট বিল</div><div class="sk-stat__val">৳<?php echo number_format($todaySalesTotalBill); ?></div></div>
    </div>

    <div class="sk-tabs">
        <button onclick="switchAdminTab('pnl_ledger', this)" class="tab on" id="tab_pnl"><i class="fas fa-book-open"></i> P&L লেজার</button>
        <button onclick="switchAdminTab('returns', this)" class="tab" id="tab_returns"><i class="fas fa-undo"></i> রিটার্ন<?php if($pendingReturnsCount > 0): ?><span class="tab-badge"><?php echo $pendingReturnsCount; ?></span><?php endif; ?></button>
        <button onclick="switchAdminTab('products', this)" class="tab" id="tab_products"><i class="fas fa-box-open"></i> পণ্য লিস্ট</button>
        <button onclick="switchAdminTab('folders', this)" class="tab" id="tab_folders"><i class="fas fa-folder"></i> আর্কাইভ</button>
        <a href="daily_activity.php" class="tab" style="color:var(--sk-danger);"><i class="fas fa-shield-alt"></i> সিকিউরিটি লগ</a>
    </div>

    <div class="sk-search" id="adminSearchBoxContainer" style="margin-bottom:.625rem;">
        <div class="sk-input-wrap sk-grow">
            <i class="fas fa-search"></i>
            <input type="text" id="adminGlobalSearch" class="sk-input sk-input--icon" placeholder="কোড বা নাম...">
        </div>
    </div>

    <div id="adminFolderViewHeader" class="hidden" style="margin-bottom:.625rem;">
        <div class="sk-card sk-card--accent sk-row sk-row--between" style="padding:.625rem .75rem;">
            <div style="font-size:.75rem; font-weight:800; text-transform:uppercase; color:var(--sk-primary-ink);">
                <i class="fas fa-folder-open"></i> আর্কাইভ: <span id="adminCurrentFolderName" style="color:var(--sk-primary);"></span>
            </div>
            <button onclick="clearAdminFolderView()" class="sk-btn sk-btn--danger sk-btn--sm"><i class="fas fa-times"></i> বন্ধ</button>
        </div>
    </div>

    <div id="adminDataContainer" style="min-height:250px;"></div>
</main>

<div id="adminEditProductModal" class="sk-modal">
    <div class="sk-modal__sheet">
        <div class="sk-modal__head"><div class="sk-modal__title"><i class="fas fa-edit"></i> প্রোডাক্ট এডিট</div><button class="sk-modal__close" onclick="closeAdminModal('adminEditProductModal')">&times;</button></div>
        <form id="adminEditForm" onsubmit="submitProductEdit(event)">
            <input type="hidden" id="edit_code_val">
            <div class="sk-field"><label class="sk-label">নাম</label><input type="text" id="edit_name_val" class="sk-input" required></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem;">
                <div class="sk-field"><label class="sk-label">কেনা দাম</label><input type="number" step="0.01" id="edit_buy_val" class="sk-input" required></div>
                <div class="sk-field"><label class="sk-label">Cost</label><input type="number" step="0.01" id="edit_cost_val" class="sk-input" required></div>
            </div>
            <div class="sk-field"><label class="sk-label">ক্যাশ বিক্রয় মূল্য</label><input type="number" step="0.01" id="edit_cash_val" class="sk-input" required></div>
            <button type="submit" class="sk-btn sk-btn--accent sk-btn--block sk-btn--lg"><i class="fas fa-save"></i> আপডেট করুন</button>
        </form>
    </div>
</div>

<div id="adminEditSalePriceModal" class="sk-modal">
    <div class="sk-modal__sheet">
        <div class="sk-modal__head"><div class="sk-modal__title"><i class="fas fa-edit"></i> ইনভয়েস প্রাইস আপডেট</div><button class="sk-modal__close" onclick="closeAdminModal('adminEditSalePriceModal')">&times;</button></div>
        <div class="sk-card sk-card--accent" style="margin-bottom:.875rem;">
            <p style="font-size:.8rem; font-weight:700; margin:0 0 .25rem;">ইনভয়েস: <span id="esp_inv" class="sk-pill sk-pill--ink sk-mono"></span></p>
            <p style="font-size:.8rem; font-weight:700; margin:0;">প্রোডাক্ট: <span id="esp_code" class="sk-pill sk-pill--accent sk-mono"></span></p>
        </div>
        <form id="adminEditSalePriceForm" onsubmit="submitSalePriceEdit(event)">
            <input type="hidden" id="esp_sale_id"><input type="hidden" id="esp_product_code">
            <div class="sk-field"><label class="sk-label">নতুন বিক্রয় মূল্য</label><input type="number" step="0.01" id="esp_new_price" class="sk-input" style="text-align:center; font-size:1.15rem; font-weight:800; color:var(--sk-primary);" required></div>
            <div class="sk-pill sk-pill--danger" style="display:block; padding:.5rem .75rem; font-size:.7rem; line-height:1.4; margin-bottom:.75rem;">
                <i class="fas fa-shield-alt"></i> নতুন বিক্রয় মূল্য (কেনা + Cost) এর চেয়ে বেশি হতে হবে।
            </div>
            <button type="submit" class="sk-btn sk-btn--accent sk-btn--block sk-btn--lg"><i class="fas fa-check-circle"></i> সেভ ও আপডেট</button>
        </form>
    </div>
</div>

<div id="adminAdjustModal" class="sk-modal">
    <div class="sk-modal__sheet">
        <div class="sk-modal__head"><div class="sk-modal__title"><i class="fas fa-sliders-h"></i> স্টক কমানো / বাড়ানো</div><button class="sk-modal__close" onclick="closeAdminModal('adminAdjustModal')">&times;</button></div>
        <form id="adminAdjustForm" onsubmit="submitStockAdjust(event)">
            <input type="hidden" id="adj_product_code_val">
            <div class="sk-field"><label class="sk-label">পণ্য</label><input type="text" id="adj_product_name_val" class="sk-input" style="background:var(--sk-surface-2);" disabled></div>
            <div class="sk-field">
                <label class="sk-label">অ্যাকশন</label>
                <select id="adj_type_val" class="sk-select">
                    <option value="increase">পিস যোগ করুন (+)</option>
                    <option value="decrease">পিস কমান (-)</option>
                </select>
            </div>
            <div class="sk-field"><label class="sk-label">পরিমাণ</label><input type="number" id="adj_pieces_val" min="1" class="sk-input" required></div>
            <div class="sk-field"><label class="sk-label">কারণ / নোট</label><textarea id="adj_note_val" class="sk-textarea" style="resize:none;" required></textarea></div>
            <button type="submit" class="sk-btn sk-btn--warn sk-btn--block sk-btn--lg"><i class="fas fa-save"></i> সেভ করুন</button>
        </form>
    </div>
</div>

<div id="adminImageLightbox" onclick="closeImageModal()" style="display:none; position:fixed; z-index:100000; inset:0; background:rgba(0,0,0,.92); align-items:center; justify-content:center;">
    <span onclick="closeImageModal()" style="position:absolute; top:1rem; right:1.25rem; color:#fff; font-size:2rem; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="adminLightboxImg" src="" style="max-width:92%; max-height:82vh; border-radius:.5rem; border:2px solid #fff; object-fit:contain;">
</div>

<script>
const userCsrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
let currentAdminTab = 'pnl_ledger';
let currentAdminMonthFilter = '';
let currentProductViewMode = 'active';
let adminSearchTimeout;

function switchAdminTab(tab, btn) {
    currentAdminTab = tab;
    $('.sk-tabs .tab').removeClass('on');
    $(btn).addClass('on');
    if (tab === 'folders') $('#adminSearchBoxContainer').hide(); else $('#adminSearchBoxContainer').show();
    if (tab !== 'pnl_ledger' || !currentAdminMonthFilter) clearAdminFolderView();
    loadAdminData(1);
}
function setProductView(mode) { currentProductViewMode = mode; loadAdminData(1); }
function openFolderData(mv, ml) { currentAdminMonthFilter = mv; $('#adminFolderViewHeader').removeClass('hidden'); $('#adminCurrentFolderName').text(ml); switchAdminTab('pnl_ledger', document.getElementById('tab_pnl')); }
function clearAdminFolderView() { currentAdminMonthFilter = ''; $('#adminFolderViewHeader').addClass('hidden'); if (currentAdminTab === 'pnl_ledger') loadAdminData(1); }

function loadAdminData(page = 1) {
    let s = $('#adminGlobalSearch').val();
    $('#adminDataContainer').html('<div class="sk-empty"><i class="fas fa-circle-notch fa-spin" style="font-size:2rem; color:var(--sk-primary);"></i></div>');
    $.ajax({
        url:'admin_inventory_control.php', type:'POST',
        data:{ ajax_action:'load_tab_data', csrf_token: userCsrfToken, tab: currentAdminTab, search:s, month_filter: currentAdminMonthFilter, page, product_view_mode: currentProductViewMode },
        dataType:'json',
        success: function(r) {
            if (r.status === 'session_expired') { window.location.href='../index.php'; return; }
            $('#adminDataContainer').html(r.html);
        },
        error: function() { $('#adminDataContainer').html('<div class="sk-empty"><i class="fas fa-exclamation-triangle"></i><p>Error Loading!</p></div>'); }
    });
}
$('#adminGlobalSearch').on('keyup', function() { clearTimeout(adminSearchTimeout); adminSearchTimeout = setTimeout(() => loadAdminData(1), 500); });

function openImageModal(s) { document.getElementById('adminLightboxImg').src = s; document.getElementById('adminImageLightbox').style.display = 'flex'; }
function closeImageModal() { document.getElementById('adminImageLightbox').style.display = 'none'; }

function openProductEditModal(d) {
    $('#edit_code_val').val(d.product_code); $('#edit_name_val').val(d.name);
    $('#edit_buy_val').val(d.buy_price); $('#edit_cost_val').val(d.cost); $('#edit_cash_val').val(d.cash_sell);
    $('#adminEditProductModal').addClass('open');
}
function openSalePriceEditModal(sid, inv, pc, cur) {
    $('#esp_sale_id').val(sid); $('#esp_product_code').val(pc);
    $('#esp_inv').text(inv); $('#esp_code').text(pc); $('#esp_new_price').val(cur);
    $('#adminEditSalePriceModal').addClass('open');
}
function openStockAdjustModal(pc, pn) {
    $('#adj_product_code_val').val(pc); $('#adj_product_name_val').val(pn + ' (' + pc + ')');
    $('#adj_pieces_val').val(''); $('#adj_note_val').val('');
    $('#adminAdjustModal').addClass('open');
}
function closeAdminModal(id) { $('#' + id).removeClass('open'); }

function deleteInvoice(inv) {
    if (confirm('সম্পূর্ণ মেমো (' + inv + ') ডিলিট করবেন? স্টকে মাল ফেরত যাবে!')) {
        $.post('admin_inventory_control.php', { ajax_action:'delete_invoice', csrf_token: userCsrfToken, invoice_no: inv }, function(r) {
            if (r.status === 'session_expired') { window.location.href='../index.php'; return; }
            alert(r.message); if (r.status === 'success') loadAdminData(1);
        });
    }
}

function toggleProdStatus(code, newStatus) {
    let msg = newStatus === 'inactive' ? 'এই পণ্যটি ইনঅ্যাকটিভ করবেন? POS এ আর দেখা যাবে না।' : 'পুনরায় অ্যাকটিভ করবেন?';
    if (confirm(msg)) {
        $.post('admin_inventory_control.php', { ajax_action:'toggle_product_status', csrf_token: userCsrfToken, product_code: code, new_status: newStatus }, function(r) {
            if (r.status === 'session_expired') { window.location.href='../index.php'; return; }
            alert(r.message); if (r.status === 'success') loadAdminData(1);
        });
    }
}

function submitProductEdit(e) {
    e.preventDefault();
    $.post('admin_inventory_control.php', { ajax_action:'update_product', csrf_token: userCsrfToken, edit_code: $('#edit_code_val').val(), edit_name: $('#edit_name_val').val(), edit_buy: $('#edit_buy_val').val(), edit_cost: $('#edit_cost_val').val(), edit_cash: $('#edit_cash_val').val() }, function(r) {
        if (r.status === 'session_expired') { window.location.href='../index.php'; return; }
        alert(r.message); if (r.status === 'success') { closeAdminModal('adminEditProductModal'); loadAdminData(1); }
    });
}

function submitSalePriceEdit(e) {
    e.preventDefault();
    let btn = $('#adminEditSalePriceForm button[type="submit"]'); let orig = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin"></i> আপডেটিং...').prop('disabled', true);
    $.post('admin_inventory_control.php', { ajax_action:'update_sale_item_price', csrf_token: userCsrfToken, sale_id: $('#esp_sale_id').val(), product_code: $('#esp_product_code').val(), new_sell_price: $('#esp_new_price').val() }, function(r) {
        if (r.status === 'session_expired') { window.location.href='../index.php'; return; }
        alert(r.message); if (r.status === 'success') { closeAdminModal('adminEditSalePriceModal'); loadAdminData(1); }
        btn.html(orig).prop('disabled', false);
    }).fail(function() { alert('সার্ভার এরর!'); btn.html(orig).prop('disabled', false); });
}

function submitStockAdjust(e) {
    e.preventDefault();
    $.post('admin_inventory_control.php', { ajax_action:'adjust_stock', csrf_token: userCsrfToken, product_code: $('#adj_product_code_val').val(), adj_type: $('#adj_type_val').val(), pieces: $('#adj_pieces_val').val(), note: $('#adj_note_val').val() }, function(r) {
        if (r.status === 'session_expired') { window.location.href='../index.php'; return; }
        alert(r.message); if (r.status === 'success') { closeAdminModal('adminAdjustModal'); loadAdminData(1); }
    });
}

function approveReturnRequest(id) {
    if (confirm('অ্যাডমিন হিসেবে অ্যাপ্রুভ করবেন? স্টকে পণ্য যোগ হবে!')) {
        $.post('admin_inventory_control.php', { ajax_action:'approve_return', csrf_token: userCsrfToken, return_id: id }, function(r) {
            if (r.status === 'session_expired') { window.location.href='../index.php'; return; }
            alert(r.message); if (r.status === 'success') loadAdminData(1);
        });
    }
}
function rejectReturnRequest(id) {
    if (confirm('রিজেক্ট করবেন? এটি বিক্রি হিসেবেই গণ্য হবে।')) {
        $.post('admin_inventory_control.php', { ajax_action:'reject_return', csrf_token: userCsrfToken, return_id: id }, function(r) {
            if (r.status === 'session_expired') { window.location.href='../index.php'; return; }
            alert(r.message); if (r.status === 'success') loadAdminData(1);
        });
    }
}

$(document).ready(function() { loadAdminData(1); });
</script>
</body>
<style>body{padding-bottom:76px;}</style>
<?php include 'inventory_bottom_nav.php'; ?>
</html>
