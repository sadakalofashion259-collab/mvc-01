<?php
declare(strict_types=1);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
date_default_timezone_set('Asia/Dhaka');

function rptLogError(Throwable $e): void {
    $d = __DIR__ . '/../Logs';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    @file_put_contents($d . '/error_log.txt',
        "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL,
        FILE_APPEND);
}

$isAjax = !empty($_REQUEST['ajax_action']);

$lastAct = $_SESSION['last_activity'] ?? null;
if ($lastAct !== null && is_int($lastAct) && (time() - $lastAct > 1200)) {
    session_unset(); session_destroy();
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'session_expired']); exit; }
    echo "<script>window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'session_expired']); exit; }
    header("Location: ../index.php"); exit;
}

$role    = strtolower(trim(isset($_SESSION['role']) && is_string($_SESSION['role']) ? $_SESSION['role'] : 'user'));
$isAdmin = ($role === 'admin');
$allowed = ['admin','manager','staff'];
if (!in_array($role, $allowed, true)) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'error','message'=>'অনুমতি নেই']); exit; }
    header("Location: inventory_dashboard.php"); exit;
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = (string)$_SESSION['csrf_token'];

$dbPath = __DIR__ . '/../db_connect.php';
if (file_exists($dbPath)) require_once $dbPath;
/** @var PDO $conn */

// ════════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════════
function rptBuildWhere(array $f): array {
    $w = []; $p = [];
    if (!empty($f['date_from'])) { $w[] = 'se.created_at >= ?'; $p[] = $f['date_from'] . ' 00:00:00'; }
    if (!empty($f['date_to']))   { $w[] = 'se.created_at <= ?'; $p[] = $f['date_to']   . ' 23:59:59'; }
    if (!empty($f['type']))      { $w[] = 'se.exchange_type = ?'; $p[] = $f['type']; }
    if (!empty($f['status']))    { $w[] = 'se.status = ?';        $p[] = $f['status']; }
    if (!empty($f['search']))    {
        $s = '%' . $f['search'] . '%';
        $w[] = '(se.old_product_code LIKE ? OR se.new_product_code LIKE ? OR se.supplier_name LIKE ? OR se.exchange_no LIKE ?)';
        array_push($p, $s, $s, $s, $s);
    }
    return [empty($w) ? '' : 'WHERE ' . implode(' AND ', $w), $p];
}

function rptStats(PDO $conn, string $where, array $params): array {
    $st = $conn->prepare(
        "SELECT COUNT(*) AS total_exc,
                SUM(returned_pieces) AS total_returned,
                SUM(received_pieces) AS total_received,
                SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) AS cnt_pending,
                SUM(CASE WHEN status='approved'  THEN 1 ELSE 0 END) AS cnt_approved,
                SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cnt_cancelled,
                SUM(returned_pieces * old_buy_price) AS total_return_value
         FROM supplier_exchanges se $where"
    );
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
}

// ════════════════════════════════════════════════════════════════
// AJAX
// ════════════════════════════════════════════════════════════════
if ($isAjax) {
    header('Content-Type: application/json');
    $action = (string)($_REQUEST['ajax_action'] ?? '');

    if (!in_array($action, ['export_csv'], true)) {
        $postCsrf = (string)($_POST['csrf_token'] ?? '');
        if ($postCsrf === '' || !hash_equals($csrfToken, $postCsrf)) {
            echo json_encode(['status'=>'error','message'=>'সেশন মেয়াদ শেষ!']); exit;
        }
    }

    $filters = [
        'date_from' => trim((string)($_REQUEST['date_from'] ?? '')),
        'date_to'   => trim((string)($_REQUEST['date_to']   ?? '')),
        'type'      => trim((string)($_REQUEST['type']      ?? '')),
        'status'    => trim((string)($_REQUEST['status']    ?? '')),
        'search'    => trim((string)($_REQUEST['search']    ?? '')),
    ];

    // ── ইউজার রিপোর্ট লোড ─────────────────────────────────────
    if ($action === 'load_user') {
        try {
            $page    = max(1, (int)($_POST['page'] ?? 1));
            $perPage = max(10, min(100, (int)($_POST['per'] ?? 25)));
            $offset  = ($page - 1) * $perPage;
            [$where, $params] = rptBuildWhere($filters);

            $st = $conn->prepare("SELECT COUNT(*) FROM supplier_exchanges se $where");
            $st->execute($params); $total = (int)$st->fetchColumn();

            $rows = $conn->prepare(
                "SELECT se.exchange_no, se.created_at, se.exchange_type, se.status,
                        se.old_product_code, se.returned_pieces,
                        se.new_product_code, se.received_pieces,
                        se.supplier_name, se.old_buy_price, se.new_buy_price,
                        u.username AS by_name
                 FROM supplier_exchanges se
                 LEFT JOIN users u ON se.exchanged_by = u.id
                 $where ORDER BY se.id DESC LIMIT {$perPage} OFFSET {$offset}"
            );
            $rows->execute($params);
            echo json_encode([
                'status' => 'success',
                'rows'   => $rows->fetchAll(PDO::FETCH_ASSOC),
                'total'  => $total,
                'page'   => $page,
                'pages'  => max(1, (int)ceil($total / $perPage)),
                'stats'  => rptStats($conn, $where, $params),
            ]); exit;
        } catch (Throwable $e) { rptLogError($e); echo json_encode(['status'=>'error','message'=>'লোড ব্যর্থ']); exit; }
    }

    // ── অ্যাডমিন রিপোর্ট লোড (ছবিসহ) ─────────────────────────
    if ($action === 'load_admin') {
        if (!$isAdmin) { echo json_encode(['status'=>'error','message'=>'অনুমতি নেই']); exit; }
        try {
            $page    = max(1, (int)($_POST['page'] ?? 1));
            $perPage = max(10, min(100, (int)($_POST['per'] ?? 15)));
            $offset  = ($page - 1) * $perPage;
            [$where, $params] = rptBuildWhere($filters);

            $st = $conn->prepare("SELECT COUNT(*) FROM supplier_exchanges se $where");
            $st->execute($params); $total = (int)$st->fetchColumn();

            $rows = $conn->prepare(
                "SELECT se.*,
                        u.username  AS by_name,
                        rv.username AS reviewer_name,
                        i.image_path AS img_path,
                        i.pieces     AS current_pieces
                 FROM supplier_exchanges se
                 LEFT JOIN users u   ON se.exchanged_by = u.id
                 LEFT JOIN users rv  ON se.reviewed_by  = rv.id
                 LEFT JOIN inventory i ON se.old_product_code = i.product_code
                 $where ORDER BY se.id DESC LIMIT {$perPage} OFFSET {$offset}"
            );
            $rows->execute($params);
            echo json_encode([
                'status' => 'success',
                'rows'   => $rows->fetchAll(PDO::FETCH_ASSOC),
                'total'  => $total,
                'page'   => $page,
                'pages'  => max(1, (int)ceil($total / $perPage)),
                'stats'  => rptStats($conn, $where, $params),
            ]); exit;
        } catch (Throwable $e) { rptLogError($e); echo json_encode(['status'=>'error','message'=>'লোড ব্যর্থ']); exit; }
    }

    // ── CSV Export ─────────────────────────────────────────────
    if ($action === 'export_csv') {
        try {
            [$where, $params] = rptBuildWhere($filters);
            $rows = $conn->prepare(
                "SELECT se.exchange_no, se.created_at, se.exchange_type, se.status,
                        se.old_product_code, se.old_product_name, se.returned_pieces, se.old_buy_price,
                        se.new_product_code, se.new_product_name, se.received_pieces, se.new_buy_price,
                        se.supplier_name, se.note, u.username AS by_name, rv.username AS reviewer_name,
                        se.reviewed_at
                 FROM supplier_exchanges se
                 LEFT JOIN users u  ON se.exchanged_by = u.id
                 LEFT JOIN users rv ON se.reviewed_by  = rv.id
                 $where ORDER BY se.id DESC"
            );
            $rows->execute($params);
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="exchange_report_' . date('Ymd_His') . '.csv"');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['নং','তারিখ','ধরন','স্ট্যাটাস','পুরানো কোড','পুরানো নাম','ফেরত পিস','পুরানো দাম','নতুন কোড','নতুন নাম','নতুন পিস','নতুন দাম','সাপ্লায়ার','নোট','করেছে','অনুমোদন','অনুমোদনের সময়']);
            $tm = ['same_product'=>'একই পণ্য','new_product'=>'নতুন পণ্য','return_only'=>'শুধু ফেরত'];
            $sm = ['approved'=>'অনুমোদিত','pending'=>'পেন্ডিং','cancelled'=>'বাতিল'];
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
                fputcsv($out, [
                    $r['exchange_no'], date('d/m/Y H:i', strtotime($r['created_at'])),
                    $tm[$r['exchange_type']] ?? $r['exchange_type'],
                    $sm[$r['status']]        ?? $r['status'],
                    $r['old_product_code'], $r['old_product_name'], $r['returned_pieces'], $r['old_buy_price'],
                    $r['new_product_code'] ?? '', $r['new_product_name'] ?? '',
                    $r['received_pieces'] ?? '', $r['new_buy_price'] ?? '',
                    $r['supplier_name'] ?? '', $r['note'] ?? '',
                    $r['by_name'] ?? '', $r['reviewer_name'] ?? '',
                    $r['reviewed_at'] ? date('d/m/Y H:i', strtotime($r['reviewed_at'])) : '',
                ]);
            }
            fclose($out); exit;
        } catch (Throwable $e) { rptLogError($e); http_response_code(500); echo 'Export failed'; exit; }
    }

    echo json_encode(['status'=>'error','message'=>'অজানা অ্যাকশন']); exit;
}

$csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>এক্সচেঞ্জ রিপোর্ট — সাদা কালো ফ্যাশন</title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{
    --sk-accent:#4f46e5;--sk-ink:#1e293b;--sk-muted:#64748b;
    --sk-success:#059669;--sk-danger:#dc2626;--sk-warning:#f59e0b;
    --sk-bg:#f4f5fb;--sk-card:#fff;--sk-border:#e5e7eb;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--sk-bg);color:var(--sk-ink);font-family:'Hind Siliguri',system-ui,sans-serif;padding-bottom:5rem;}

/* topbar */
.rpt-top{background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;padding:.85rem 1rem 1.1rem;border-radius:0 0 16px 16px;}
.rpt-top a{color:#fff;text-decoration:none;font-size:.78rem;opacity:.9;}
.rpt-top h1{font-size:1.05rem;margin:.3rem 0 0;font-weight:800;}

.rpt-wrap{padding:.7rem .8rem;max-width:100%;}

/* tab switcher */
.rpt-tabs{display:flex;gap:.4rem;margin-bottom:.75rem;background:var(--sk-card);border-radius:12px;padding:.35rem;border:1px solid var(--sk-border);}
.rpt-tab{flex:1;text-align:center;padding:.45rem .5rem;border-radius:9px;font-size:.78rem;font-weight:800;cursor:pointer;border:none;background:transparent;color:var(--sk-muted);transition:.15s;}
.rpt-tab.active{background:var(--sk-accent);color:#fff;}

/* stats */
.rpt-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.45rem;margin-bottom:.7rem;}
.rpt-stat{background:var(--sk-card);border-radius:11px;padding:.55rem .5rem;text-align:center;border:1px solid var(--sk-border);}
.rpt-stat-val{font-size:1rem;font-weight:800;color:var(--sk-accent);}
.rpt-stat-lbl{font-size:.6rem;font-weight:700;color:var(--sk-muted);margin-top:.1rem;}

/* filters */
.rpt-filters{background:var(--sk-card);border-radius:12px;padding:.7rem .75rem;margin-bottom:.7rem;border:1px solid var(--sk-border);}
.rpt-fg{display:grid;grid-template-columns:1fr 1fr;gap:.4rem;}
.rpt-fg input,.rpt-fg select{width:100%;padding:.42rem .55rem;border:1.5px solid var(--sk-border);border-radius:8px;font-size:.76rem;font-family:inherit;background:#fbfbfe;}
.rpt-fg input:focus,.rpt-fg select:focus{outline:none;border-color:var(--sk-accent);}
.rpt-fg .full{grid-column:1/-1;}
.rpt-fbtns{display:flex;gap:.4rem;margin-top:.45rem;align-items:center;}
.rpt-btn{border:none;border-radius:9px;padding:.42rem .7rem;font-size:.74rem;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;}
.rpt-btn--primary{background:var(--sk-accent);color:#fff;flex:1;justify-content:center;}
.rpt-btn--outline{background:#eef0fd;color:var(--sk-accent);}
.rpt-btn--csv{background:#059669;color:#fff;}
.rpt-btn:disabled{opacity:.55;cursor:not-allowed;}
.rpt-per{padding:.42rem .55rem;border:1.5px solid var(--sk-border);border-radius:8px;font-size:.74rem;font-family:inherit;background:#fbfbfe;}

/* card + table */
.rpt-card{background:var(--sk-card);border-radius:12px;border:1px solid var(--sk-border);overflow:hidden;margin-bottom:.7rem;}
.rpt-card-head{display:flex;align-items:center;justify-content:space-between;padding:.55rem .75rem;border-bottom:1px solid var(--sk-border);}
.rpt-card-title{font-size:.82rem;font-weight:800;}
.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;}
table.tbl{width:100%;border-collapse:collapse;font-size:.71rem;}
table.tbl thead tr{background:#f8f8fc;}
table.tbl th{padding:.4rem .35rem;text-align:left;font-weight:800;color:var(--sk-muted);border-bottom:2px solid var(--sk-border);white-space:nowrap;}
table.tbl td{padding:.38rem .35rem;border-bottom:1px solid #f3f4f6;vertical-align:middle;}
table.tbl tbody tr:last-child td{border-bottom:none;}
table.tbl tbody tr:hover{background:#fafbff;}

/* badges */
.bdg{display:inline-block;padding:.13rem .42rem;border-radius:99px;font-size:.61rem;font-weight:800;white-space:nowrap;}
.bdg-ap{background:rgba(5,150,105,.12);color:#065f46;}
.bdg-pe{background:rgba(245,158,11,.15);color:#92400e;}
.bdg-ca{background:rgba(107,114,128,.12);color:#4b5563;}
.bdg-new{background:rgba(79,70,229,.1);color:#4338ca;}
.bdg-sam{background:rgba(245,158,11,.12);color:#92400e;}
.bdg-ret{background:rgba(220,38,38,.08);color:#991b1b;}
.minus{color:var(--sk-danger);font-weight:800;}
.plus{color:var(--sk-success);font-weight:800;}

/* product image (admin tab) */
.p-img{width:36px;height:36px;object-fit:cover;border-radius:7px;border:1px solid var(--sk-border);display:block;}
.p-img-ph{width:36px;height:36px;background:#f1f3f9;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

/* pagination */
.rpt-pg{display:flex;align-items:center;justify-content:space-between;padding:.5rem .75rem;border-top:1px solid var(--sk-border);}
.rpt-pg-btn{border:1.5px solid var(--sk-border);background:var(--sk-card);border-radius:8px;padding:.28rem .6rem;font-size:.72rem;font-weight:700;cursor:pointer;color:var(--sk-muted);}
.rpt-pg-btn:disabled{opacity:.4;cursor:not-allowed;}
.rpt-pg-info{font-size:.71rem;font-weight:700;color:var(--sk-muted);}
.rpt-empty{padding:2rem 1rem;text-align:center;color:var(--sk-muted);}
.rpt-empty i{font-size:1.8rem;opacity:.2;margin-bottom:.4rem;display:block;}
.rpt-loading{padding:1.5rem;text-align:center;color:var(--sk-muted);font-size:.8rem;}
</style>
</head>
<body>

<div class="rpt-top">
    <a href="supplier_exchange.php"><i class="fas fa-arrow-left"></i> ফিরে যান</a>
    <h1><i class="fas fa-chart-bar"></i> এক্সচেঞ্জ রিপোর্ট</h1>
</div>

<div class="rpt-wrap">

    <!-- ══ ট্যাব ══ -->
    <div class="rpt-tabs">
        <button class="rpt-tab active" onclick="switchTab('user')">
            <i class="fas fa-list"></i> এক্সচেঞ্জ রিপোর্ট
        </button>
        <?php if ($isAdmin): ?>
        <button class="rpt-tab" onclick="switchTab('admin')">
            <i class="fas fa-shield-alt"></i> অ্যাডমিন রিপোর্ট
        </button>
        <?php endif; ?>
    </div>

    <!-- ══ স্ট্যাটস ══ -->
    <div class="rpt-stats" id="rptStats">
        <div class="rpt-stat"><div class="rpt-stat-val" id="stTotal">—</div><div class="rpt-stat-lbl">মোট</div></div>
        <div class="rpt-stat"><div class="rpt-stat-val" id="stPend" style="color:var(--sk-warning);">—</div><div class="rpt-stat-lbl">পেন্ডিং</div></div>
        <div class="rpt-stat"><div class="rpt-stat-val" id="stAppr" style="color:var(--sk-success);">—</div><div class="rpt-stat-lbl">অনুমোদিত</div></div>
        <div class="rpt-stat"><div class="rpt-stat-val" id="stRet" style="color:var(--sk-danger);">—</div><div class="rpt-stat-lbl">ফেরত পিস</div></div>
        <div class="rpt-stat"><div class="rpt-stat-val" id="stNew" style="color:var(--sk-success);">—</div><div class="rpt-stat-lbl">নতুন পিস</div></div>
        <div class="rpt-stat"><div class="rpt-stat-val" id="stVal" style="font-size:.8rem;">—</div><div class="rpt-stat-lbl">ফেরত মূল্য</div></div>
    </div>

    <!-- ══ ফিল্টার ══ -->
    <div class="rpt-filters">
        <div class="rpt-fg">
            <input type="date" id="fFrom">
            <input type="date" id="fTo">
            <select id="fType">
                <option value="">সব ধরন</option>
                <option value="return_only">শুধু ফেরত</option>
                <option value="same_product">একই পণ্য</option>
                <option value="new_product">নতুন পণ্য</option>
            </select>
            <select id="fStatus">
                <option value="">সব স্ট্যাটাস</option>
                <option value="pending">পেন্ডিং</option>
                <option value="approved">অনুমোদিত</option>
                <option value="cancelled">বাতিল</option>
            </select>
            <input type="text" id="fSearch" class="full" placeholder="🔍 পণ্য কোড, সাপ্লায়ার, নং...">
        </div>
        <div class="rpt-fbtns">
            <select class="rpt-per" id="fPer">
                <option value="10">১০/পেজ</option>
                <option value="25" selected>২৫/পেজ</option>
                <option value="50">৫০/পেজ</option>
                <option value="100">১০০/পেজ</option>
            </select>
            <button class="rpt-btn rpt-btn--primary" onclick="curLoad(1)"><i class="fas fa-search"></i> খুঁজুন</button>
            <button class="rpt-btn rpt-btn--outline" onclick="rptReset()"><i class="fas fa-undo"></i></button>
            <button class="rpt-btn rpt-btn--csv"    onclick="rptCsv()"><i class="fas fa-file-csv"></i></button>
        </div>
    </div>

    <!-- ══ ট্যাব ১: ইউজার রিপোর্ট ══ -->
    <div id="tabUser">
        <div class="rpt-card">
            <div class="rpt-card-head">
                <div class="rpt-card-title"><i class="fas fa-table" style="color:var(--sk-accent);margin-right:.35rem;"></i>এক্সচেঞ্জ তালিকা</div>
                <span id="uRowCnt" style="font-size:.7rem;font-weight:700;color:var(--sk-muted);"></span>
            </div>
            <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>নং / তারিখ</th>
                        <th>পণ্য</th>
                        <th>সাপ্লায়ার</th>
                        <th>ধরন</th>
                        <th style="text-align:center;">ফেরত</th>
                        <th style="text-align:center;">নতুন</th>
                        <th style="text-align:center;">±</th>
                        <th>দাম</th>
                        <th style="text-align:center;">স্ট্যাটাস</th>
                        <th>ইউজার</th>
                    </tr>
                </thead>
                <tbody id="uBody">
                    <tr><td colspan="10" class="rpt-loading"><i class="fas fa-spinner fa-spin"></i> লোড হচ্ছে...</td></tr>
                </tbody>
            </table>
            </div>
            <div class="rpt-pg">
                <button class="rpt-pg-btn" id="uPrev" onclick="uLoad(uPage-1)" disabled><i class="fas fa-chevron-left"></i></button>
                <span class="rpt-pg-info" id="uPageInfo">—</span>
                <button class="rpt-pg-btn" id="uNext" onclick="uLoad(uPage+1)" disabled><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- ══ ট্যাব ২: অ্যাডমিন রিপোর্ট (ছবিসহ) ══ -->
    <?php if ($isAdmin): ?>
    <div id="tabAdmin" style="display:none;">
        <div class="rpt-card">
            <div class="rpt-card-head">
                <div class="rpt-card-title"><i class="fas fa-shield-alt" style="color:var(--sk-accent);margin-right:.35rem;"></i>অ্যাডমিন বিবরণ</div>
                <span id="aRowCnt" style="font-size:.7rem;font-weight:700;color:var(--sk-muted);"></span>
            </div>
            <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width:42px;">ছবি</th>
                        <th>পণ্য / সাপ্লায়ার</th>
                        <th>ধরন</th>
                        <th style="text-align:center;">ফেরত<br>পিস</th>
                        <th style="text-align:center;">নতুন<br>পিস</th>
                        <th style="text-align:center;">±পিস</th>
                        <th>দাম আগে→পরে</th>
                        <th>ফেরত মূল্য</th>
                        <th style="text-align:center;">স্ট্যাটাস</th>
                        <th>ইউজার /<br>অনুমোদন</th>
                        <th>তারিখ</th>
                    </tr>
                </thead>
                <tbody id="aBody">
                    <tr><td colspan="11" class="rpt-loading"><i class="fas fa-spinner fa-spin"></i> লোড হচ্ছে...</td></tr>
                </tbody>
            </table>
            </div>
            <div class="rpt-pg">
                <button class="rpt-pg-btn" id="aPrev" onclick="aLoad(aPage-1)" disabled><i class="fas fa-chevron-left"></i></button>
                <span class="rpt-pg-info" id="aPageInfo">—</span>
                <button class="rpt-pg-btn" id="aNext" onclick="aLoad(aPage+1)" disabled><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.rpt-wrap -->

<script>
const CSRF    = '<?= $csrf ?>';
const PAGE    = 'supplier_exchange_report.php';
const IS_ADMIN= <?= $isAdmin ? 'true' : 'false' ?>;
const IMG_BASE= '../';

let curTab = 'user';
let uPage = 1, uPages = 1;
let aPage = 1, aPages = 1;

// ── Tab ───────────────────────────────────────────────────────
function switchTab(t) {
    curTab = t;
    document.querySelectorAll('.rpt-tab').forEach(el => el.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('tabUser').style.display  = t==='user'  ? '' : 'none';
    if (IS_ADMIN) document.getElementById('tabAdmin').style.display = t==='admin' ? '' : 'none';
    curLoad(1);
}

function curLoad(p) {
    if (curTab === 'user')  uLoad(p);
    else                    aLoad(p);
}

// ── Filters ───────────────────────────────────────────────────
function getFilters() {
    return {
        date_from: document.getElementById('fFrom').value,
        date_to:   document.getElementById('fTo').value,
        type:      document.getElementById('fType').value,
        status:    document.getElementById('fStatus').value,
        search:    document.getElementById('fSearch').value.trim(),
        per:       document.getElementById('fPer').value,
    };
}

function rptReset() {
    ['fFrom','fTo','fSearch'].forEach(id => document.getElementById(id).value='');
    document.getElementById('fType').value='';
    document.getElementById('fStatus').value='';
    curLoad(1);
}

function updateStats(s) {
    if (!s) return;
    document.getElementById('stTotal').textContent = s.total_exc    || 0;
    document.getElementById('stPend').textContent  = s.cnt_pending  || 0;
    document.getElementById('stAppr').textContent  = s.cnt_approved || 0;
    document.getElementById('stRet').textContent   = s.total_returned || 0;
    document.getElementById('stNew').textContent   = s.total_received || 0;
    document.getElementById('stVal').textContent   = '৳' + parseInt(s.total_return_value||0).toLocaleString();
}

const typeMap   = {same_product:'একই পণ্য', new_product:'নতুন পণ্য', return_only:'ফেরত'};
const typeCls   = {same_product:'sam', new_product:'new', return_only:'ret'};
const statusMap = {approved:'অনুমোদিত', pending:'পেন্ডিং', cancelled:'বাতিল'};
const statusCls = {approved:'ap', pending:'pe', cancelled:'ca'};

// ── ইউজার টেবিল ──────────────────────────────────────────────
function uLoad(p) {
    if (p < 1) return;
    uPage = p;
    document.getElementById('uBody').innerHTML = '<tr><td colspan="10" class="rpt-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    document.getElementById('uPrev').disabled = true;
    document.getElementById('uNext').disabled = true;
    $.post(PAGE, {ajax_action:'load_user', csrf_token:CSRF, page:p, ...getFilters()}, function(r) {
        if (r.status==='session_expired'){location.href='../index.php';return;}
        if (r.status!=='success'){document.getElementById('uBody').innerHTML='<tr><td colspan="10" style="padding:1rem;text-align:center;color:var(--sk-danger);">লোড ব্যর্থ</td></tr>';return;}
        updateStats(r.stats);
        uPages = r.pages;
        document.getElementById('uPageInfo').textContent = `পেজ ${r.page}/${r.pages} (${r.total}টি)`;
        document.getElementById('uRowCnt').textContent   = r.total+'টি';
        document.getElementById('uPrev').disabled = (p<=1);
        document.getElementById('uNext').disabled = (p>=r.pages);
        if (!r.rows.length){document.getElementById('uBody').innerHTML='<tr><td colspan="10"><div class="rpt-empty"><i class="fas fa-inbox"></i><p>কোনো রেকর্ড নেই</p></div></td></tr>';return;}
        let html='';
        r.rows.forEach(h=>{
            const ret = parseInt(h.returned_pieces||0);
            const nw  = parseInt(h.received_pieces||0);
            const d   = nw-ret;
            const dH  = d===0?'<span style="color:var(--sk-muted);">±0</span>':d>0?`<span class="plus">+${d}</span>`:`<span class="minus">${d}</span>`;
            const dt  = h.created_at?h.created_at.substring(2,10).split('-').reverse().join('/'):'';
            const oldB= parseFloat(h.old_buy_price||0);
            const newB= parseFloat(h.new_buy_price||0);
            const prH = h.exchange_type==='return_only'?`৳${oldB.toLocaleString()}`:`<span class="minus">৳${oldB.toLocaleString()}</span>→<span class="plus">৳${newB.toLocaleString()}</span>`;
            html+=`<tr>
                <td><div style="font-weight:800;font-size:.68rem;color:var(--sk-accent);">${h.exchange_no}</div><div style="font-size:.63rem;color:var(--sk-muted);">${dt}</div></td>
                <td><div style="font-weight:700;">${h.old_product_code}</div>${h.new_product_code&&h.new_product_code!==h.old_product_code?`<div style="font-size:.63rem;color:var(--sk-success);">→${h.new_product_code}</div>`:''}</td>
                <td style="font-size:.68rem;color:#f59e0b;font-weight:700;">${h.supplier_name||'—'}</td>
                <td><span class="bdg bdg-${typeCls[h.exchange_type]||'ret'}">${typeMap[h.exchange_type]||h.exchange_type}</span></td>
                <td style="text-align:center;" class="minus">−${ret}</td>
                <td style="text-align:center;" class="plus">${nw>0?'+'+nw:'—'}</td>
                <td style="text-align:center;">${dH}</td>
                <td style="font-size:.68rem;white-space:nowrap;">${prH}</td>
                <td style="text-align:center;"><span class="bdg bdg-${statusCls[h.status]||'pe'}">${statusMap[h.status]||h.status}</span></td>
                <td style="font-size:.68rem;color:var(--sk-muted);">${h.by_name||'—'}</td>
            </tr>`;
        });
        document.getElementById('uBody').innerHTML=html;
    },'json');
}

// ── অ্যাডমিন টেবিল (ছবিসহ) ──────────────────────────────────
function aLoad(p) {
    if (!IS_ADMIN||p<1) return;
    aPage=p;
    document.getElementById('aBody').innerHTML='<tr><td colspan="11" class="rpt-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    document.getElementById('aPrev').disabled=true;
    document.getElementById('aNext').disabled=true;
    $.post(PAGE,{ajax_action:'load_admin',csrf_token:CSRF,page:p,...getFilters()},function(r){
        if(r.status==='session_expired'){location.href='../index.php';return;}
        if(r.status!=='success'){document.getElementById('aBody').innerHTML='<tr><td colspan="11" style="padding:1rem;text-align:center;color:var(--sk-danger);">লোড ব্যর্থ</td></tr>';return;}
        updateStats(r.stats);
        aPages=r.pages;
        document.getElementById('aPageInfo').textContent=`পেজ ${r.page}/${r.pages} (${r.total}টি)`;
        document.getElementById('aRowCnt').textContent=r.total+'টি';
        document.getElementById('aPrev').disabled=(p<=1);
        document.getElementById('aNext').disabled=(p>=r.pages);
        if(!r.rows.length){document.getElementById('aBody').innerHTML='<tr><td colspan="11"><div class="rpt-empty"><i class="fas fa-inbox"></i><p>কোনো রেকর্ড নেই</p></div></td></tr>';return;}
        let html='';
        r.rows.forEach(h=>{
            const ret   = parseInt(h.returned_pieces||0);
            const nw    = parseInt(h.received_pieces||0);
            const d     = nw-ret;
            const dH    = d===0?'<span style="color:var(--sk-muted);">±0</span>':d>0?`<span class="plus">+${d}</span>`:`<span class="minus">${d}</span>`;
            const oldB  = parseFloat(h.old_buy_price||0);
            const newB  = parseFloat(h.new_buy_price||0);
            const retVal= (ret*oldB).toLocaleString();
            const dt    = h.created_at?h.created_at.substring(0,16):'';
            const supH  = h.supplier_name?`<div style="font-size:.63rem;color:#f59e0b;font-weight:700;"><i class="fas fa-store" style="font-size:.6rem;"></i> ${h.supplier_name}</div>`:'';
            const newCodeH= h.new_product_code&&h.new_product_code!==h.old_product_code?`<div style="font-size:.63rem;color:var(--sk-success);">→${h.new_product_code}</div>`:'';
            const imgH  = h.img_path
                ?`<img src="${IMG_BASE}${h.img_path}" class="p-img" onerror="this.style.display='none'">`
                :`<div class="p-img-ph"><i class="fas fa-image" style="font-size:.65rem;color:var(--sk-muted);"></i></div>`;
            const byH   = `<div style="font-weight:700;font-size:.69rem;">${h.by_name||'—'}</div>${h.reviewer_name?`<div style="font-size:.62rem;color:var(--sk-success);"><i class="fas fa-check" style="font-size:.58rem;"></i> ${h.reviewer_name}</div>`:''}`;
            html+=`<tr>
                <td style="padding:.3rem .35rem;">${imgH}</td>
                <td style="max-width:120px;">
                    <div style="font-weight:800;font-size:.68rem;color:var(--sk-accent);">${h.exchange_no}</div>
                    <div style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${h.old_product_code}</div>
                    ${newCodeH}${supH}
                </td>
                <td><span class="bdg bdg-${typeCls[h.exchange_type]||'ret'}">${typeMap[h.exchange_type]||h.exchange_type}</span></td>
                <td style="text-align:center;" class="minus">−${ret}</td>
                <td style="text-align:center;" class="plus">${nw>0?'+'+nw:'—'}</td>
                <td style="text-align:center;">${dH}</td>
                <td style="font-size:.68rem;white-space:nowrap;"><span class="minus">৳${oldB.toLocaleString()}</span>${newB>0?` → <span class="plus">৳${newB.toLocaleString()}</span>`:''}</td>
                <td style="font-size:.68rem;font-weight:700;">৳${retVal}</td>
                <td style="text-align:center;"><span class="bdg bdg-${statusCls[h.status]||'pe'}">${statusMap[h.status]||h.status}</span></td>
                <td>${byH}</td>
                <td style="font-size:.63rem;color:var(--sk-muted);white-space:nowrap;">${dt}</td>
            </tr>`;
        });
        document.getElementById('aBody').innerHTML=html;
    },'json');
}

function rptCsv(){
    const f=getFilters();
    window.open(PAGE+'?'+new URLSearchParams({ajax_action:'export_csv',...f}).toString(),'_blank');
}

document.getElementById('fSearch').addEventListener('keydown',e=>{if(e.key==='Enter')curLoad(1);});

// init
uLoad(1);
</script>

<?php include 'inventory_bottom_nav.php'; ?>
</body>
</html>
