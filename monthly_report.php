<?php
session_start();
date_default_timezone_set('Asia/Dhaka');
include 'db_connect.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php"); exit;
}
$role     = $_SESSION['role'];
$sess_usr = $_SESSION['username'] ?? '';

if (isset($_SESSION['success_msg'])) { $success_msg = $_SESSION['success_msg']; unset($_SESSION['success_msg']); }
if (isset($_SESSION['error_msg']))   { $error_msg   = $_SESSION['error_msg'];   unset($_SESSION['error_msg']); }

// ============================================================
// হেল্পার — ডাটাবেজ থেকে admin password verify
// ============================================================
function verifyAdminPass($conn, $username, $pass) {
    try {
        $st = $conn->prepare("SELECT password FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        $st->execute([$username]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $stored = $row['password'];
        if ($stored === $pass) return true;
        if (md5($pass) === $stored) return true;
        if (password_verify($pass, $stored)) return true;
        return false;
    } catch (Exception $e) { return false; }
}

// ============================================================
// AJAX — Global Live Search
// ============================================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'global_search') {
    ob_clean(); header('Content-Type: application/json');
    $q = trim($_POST['query'] ?? '');
    if (mb_strlen($q) < 1) { echo json_encode([]); exit; }
    $lk = "%$q%";
    $res = [];
    
    // Sales Search
    try {
        $st = $conn->prepare("SELECT 'Sales' as mod_type, sale_date as dt, CONCAT('বিক্রি - ', customer_name, ' (মেমো: ', memo_no, ')') as txt, paid_amount as amt FROM sales_entries WHERE customer_name LIKE ? OR memo_no LIKE ? OR total_bill LIKE ? ORDER BY sale_date DESC LIMIT 7");
        $st->execute([$lk, $lk, $lk]);
        $res = array_merge($res, $st->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e){}

    // Collection Search
    try {
        $st = $conn->prepare("SELECT 'Collection' as mod_type, coll_date as dt, CONCAT('কালেকশন - ', payer_name) as txt, total_deposit as amt FROM collection_entries WHERE payer_name LIKE ? OR total_deposit LIKE ? ORDER BY coll_date DESC LIMIT 5");
        $st->execute([$lk, $lk]);
        $res = array_merge($res, $st->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e){}

    // Expense Search
    try {
        $st = $conn->prepare("SELECT 'Expense' as mod_type, exp_date as dt, CONCAT('খরচ - ', description) as txt, amount as amt FROM expense_entries WHERE description LIKE ? OR amount LIKE ? ORDER BY exp_date DESC LIMIT 5");
        $st->execute([$lk, $lk]);
        $res = array_merge($res, $st->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e){}

    // Supplier Search
    try {
        $st = $conn->prepare("SELECT 'Supplier' as mod_type, tr_date as dt, CONCAT('সাপ্লায়ার পেমেন্ট (মেমো: ', memo_no, ')') as txt, payment_given as amt FROM supplier_transactions WHERE memo_no LIKE ? OR payment_given LIKE ? ORDER BY tr_date DESC LIMIT 5");
        $st->execute([$lk, $lk]);
        $res = array_merge($res, $st->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e){}

    // Customer Search
    try {
        $st = $conn->prepare("SELECT 'Customer' as mod_type, tr_date as dt, CONCAT('গ্রাহক প্রাপ্তি - ', description) as txt, received_amount as amt FROM customer_transactions WHERE description LIKE ? OR received_amount LIKE ? ORDER BY tr_date DESC LIMIT 5");
        $st->execute([$lk, $lk]);
        $res = array_merge($res, $st->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e){}

    // Sort by date descending and limit total results to 15 to keep it fast
    usort($res, function($a, $b) { return strtotime($b['dt']) <=> strtotime($a['dt']); });
    echo json_encode(array_slice($res, 0, 15));
    exit;
}

// ============================================================
// AJAX — সাধারণ Delete/Edit 
// ============================================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'item_action' && $role === 'admin') {
    ob_clean(); header('Content-Type: application/json');
    $pass  = trim($_POST['pass'] ?? '');
    $type  = $_POST['type']  ?? '';
    $table = $_POST['table'] ?? '';
    $id    = intval($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $val   = $_POST['val']   ?? '';

    if (!verifyAdminPass($conn, $sess_usr, $pass)) {
        echo json_encode(['status'=>'error','message'=>'❌ ভুল পাসওয়ার্ড!']); exit;
    }
    $ok = ['sales_entries','expense_entries','supplier_transactions','stocks','stock_entries',
           'customer_transactions','collection_entries','staff_expenses'];
    if (!in_array($table, $ok)) {
        echo json_encode(['status'=>'error','message'=>'অনুমোদিত নয়।']); exit;
    }
    try {
        if ($type === 'delete') {
            $col = null;
            if (in_array($table, ['sales_entries','expense_entries','supplier_transactions'])) $col = 'photo';
            elseif ($table === 'customer_transactions') $col = 'image_path';
            elseif (in_array($table, ['stocks','stock_entries'])) $col = 'image';
            if ($col) {
                $f = $conn->query("SELECT $col FROM $table WHERE id=$id")->fetchColumn();
                if (!empty($f) && file_exists($f)) unlink($f);
            }
            $conn->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
            echo json_encode(['status'=>'success','message'=>'✅ এন্ট্রি ডিলিট হয়েছে।']); exit;
        } elseif ($type === 'edit') {
            $conn->prepare("UPDATE $table SET $field=? WHERE id=?")->execute([$val, $id]);
            echo json_encode(['status'=>'success','message'=>'✅ এন্ট্রি আপডেট হয়েছে।']); exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>'এরর: '.$e->getMessage()]); exit;
    }
}

// DPS, Loan, Card AJAX Logic
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'delete_dps' && $role === 'admin') { ob_clean(); header('Content-Type: application/json'); $pass = trim($_POST['pass'] ?? ''); $lid = intval($_POST['id'] ?? 0); if (!verifyAdminPass($conn, $sess_usr, $pass)) { echo json_encode(['status'=>'error','message'=>'❌ ভুল পাসওয়ার্ড!']); exit; } try { $conn->beginTransaction(); $row = $conn->query("SELECT dps_id, description FROM sys_dps_ledger WHERE id=$lid")->fetch(); if (!$row) { $conn->rollBack(); echo json_encode(['status'=>'error','message'=>'এন্ট্রি পাওয়া যায়নি।']); exit; } if (stripos($row['description'], 'Opening') !== false) { $conn->rollBack(); echo json_encode(['status'=>'error','message'=>'Opening Balance ডিলিট করা যাবে না।']); exit; } $did = $row['dps_id']; $conn->prepare("DELETE FROM sys_dps_ledger WHERE id=?")->execute([$lid]); $b = $conn->query("SELECT COALESCE(SUM(deposit_amount),0)-COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE dps_id=$did")->fetchColumn(); $nb = max(0, round(floatval($b), 2)); $ns = $nb <= 0.01 ? 'inactive' : 'active'; $conn->prepare("UPDATE sys_dps_accounts SET total_balance=?,status=? WHERE id=?")->execute([$nb,$ns,$did]); $rs = $conn->query("SELECT id,deposit_amount,withdraw_amount FROM sys_dps_ledger WHERE dps_id=$did ORDER BY txn_date ASC,id ASC")->fetchAll(); $run = 0; foreach ($rs as $r) { $run += floatval($r['deposit_amount']) - floatval($r['withdraw_amount']); $conn->prepare("UPDATE sys_dps_ledger SET current_balance=? WHERE id=?")->execute([round($run,2),$r['id']]); } $conn->commit(); echo json_encode(['status'=>'success','message'=>'✅ DPS এন্ট্রি ডিলিট ও ব্যালেন্স আপডেট।']); exit; } catch (Exception $e) { if ($conn->inTransaction()) $conn->rollBack(); echo json_encode(['status'=>'error','message'=>'এরর: '.$e->getMessage()]); exit; } }
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'delete_loan' && $role === 'admin') { ob_clean(); header('Content-Type: application/json'); $pass = trim($_POST['pass'] ?? ''); $lid = intval($_POST['id'] ?? 0); if (!verifyAdminPass($conn, $sess_usr, $pass)) { echo json_encode(['status'=>'error','message'=>'❌ ভুল পাসওয়ার্ড!']); exit; } try { $conn->beginTransaction(); $row = $conn->query("SELECT loan_id FROM sys_loan_ledger WHERE id=$lid")->fetch(); if (!$row) { $conn->rollBack(); echo json_encode(['status'=>'error','message'=>'এন্ট্রি পাওয়া যায়নি।']); exit; } $loid = $row['loan_id']; $conn->prepare("DELETE FROM sys_loan_ledger WHERE id=?")->execute([$lid]); $b = $conn->query("SELECT COALESCE(SUM(debit_amount),0)-COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE loan_id=$loid")->fetchColumn(); $nb = max(0, round(floatval($b), 2)); $ns = $nb <= 0.01 ? 'inactive' : 'active'; $conn->prepare("UPDATE sys_loans SET current_balance=?,status=? WHERE id=?")->execute([$nb,$ns,$loid]); $rs = $conn->query("SELECT id,debit_amount,credit_amount FROM sys_loan_ledger WHERE loan_id=$loid ORDER BY id ASC")->fetchAll(); $run = 0; foreach ($rs as $r) { $run += floatval($r['debit_amount']) - floatval($r['credit_amount']); $conn->prepare("UPDATE sys_loan_ledger SET balance=? WHERE id=?")->execute([round($run,2),$r['id']]); } $conn->commit(); echo json_encode(['status'=>'success','message'=>'✅ লোন এন্ট্রি ডিলিট ও রিক্যালকুলেট।']); exit; } catch (Exception $e) { if ($conn->inTransaction()) $conn->rollBack(); echo json_encode(['status'=>'error','message'=>'এরর: '.$e->getMessage()]); exit; } }
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'delete_card_ledger' && $role === 'admin') { ob_clean(); header('Content-Type: application/json'); $pass = trim($_POST['pass'] ?? ''); $lid = intval($_POST['id'] ?? 0); if (!verifyAdminPass($conn, $sess_usr, $pass)) { echo json_encode(['status'=>'error','message'=>'❌ ভুল পাসওয়ার্ড!']); exit; } try { $conn->beginTransaction(); $row = $conn->query("SELECT card_id, receipt_image FROM sys_card_ledger WHERE id=$lid")->fetch(PDO::FETCH_ASSOC); if (!$row) { $conn->rollBack(); echo json_encode(['status'=>'error','message'=>'এন্ট্রি পাওয়া যায়নি।']); exit; } $card_id = $row['card_id']; if (!empty($row['receipt_image']) && file_exists($row['receipt_image'])) unlink($row['receipt_image']); $conn->prepare("DELETE FROM sys_card_ledger WHERE id=?")->execute([$lid]); $rs = $conn->query("SELECT id, card_balance_change FROM sys_card_ledger WHERE card_id=$card_id ORDER BY txn_date ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC); $run = 0; foreach ($rs as $r) { $run += floatval($r['card_balance_change']); $conn->prepare("UPDATE sys_card_ledger SET running_balance=? WHERE id=?")->execute([round($run,2), $r['id']]); } $conn->commit(); echo json_encode(['status'=>'success','message'=>'✅ কার্ড এন্ট্রি ডিলিট হয়েছে।']); exit; } catch (Exception $e) { if ($conn->inTransaction()) $conn->rollBack(); echo json_encode(['status'=>'error','message'=>'এরর: '.$e->getMessage()]); exit; } }

// ============================================================
// 📅 মাসিক পেজিনেশন লজিক
// ============================================================
$m = $_GET['m'] ?? date('m');
$y = $_GET['y'] ?? date('Y');

if (isset($_GET['from_date']) && $_GET['from_date'] !== '') {
    $from_date = $_GET['from_date'];
    $to_date   = $_GET['to_date'];
} else {
    $from_date = date('Y-m-01', strtotime("$y-$m-01"));
    $to_date   = date('Y-m-t', strtotime($from_date));
}

$prev_m = date('m', strtotime('-1 month', strtotime($from_date)));
$prev_y = date('Y', strtotime('-1 month', strtotime($from_date)));
$next_m = date('m', strtotime('+1 month', strtotime($from_date)));
$next_y = date('Y', strtotime('+1 month', strtotime($from_date)));

$bn_months = ["01"=>"জানুয়ারি", "02"=>"ফেব্রুয়ারি", "03"=>"মার্চ", "04"=>"এপ্রিল", "05"=>"মে", "06"=>"জুন", "07"=>"জুলাই", "08"=>"আগস্ট", "09"=>"সেপ্টেম্বর", "10"=>"অক্টোবর", "11"=>"নভেম্বর", "12"=>"ডিসেম্বর"];
$current_bn_month = $bn_months[date('m', strtotime($from_date))] . ' ' . date('Y', strtotime($from_date));

if ($role === 'manager' || $role === 'user') {
    $from_date = date('Y-m-d');
    $to_date   = date('Y-m-d');
}

// ============================================================
// ডাটা আছে এমন তারিখ
// ============================================================
$d1=[]; $d2=[]; $d3=[]; $d4=[]; $d5=[]; $d6=[]; $d7=[]; $d8=[]; $d9=[];
$d1 = $conn->query("SELECT DISTINCT report_date FROM daily_reports WHERE report_date BETWEEN '$from_date' AND '$to_date'")->fetchAll(PDO::FETCH_COLUMN);
$d2 = $conn->query("SELECT DISTINCT tr_date FROM customer_transactions WHERE tr_date BETWEEN '$from_date' AND '$to_date'")->fetchAll(PDO::FETCH_COLUMN);
$d3 = $conn->query("SELECT DISTINCT tr_date FROM supplier_transactions WHERE tr_date BETWEEN '$from_date' AND '$to_date'")->fetchAll(PDO::FETCH_COLUMN);
try { $d4 = $conn->query("SELECT DISTINCT DATE(created_at) FROM stocks WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) {}
try { $d5 = $conn->query("SELECT DISTINCT tr_date FROM stock_entries WHERE tr_date BETWEEN '$from_date' AND '$to_date'")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) {}
try { $d6 = $conn->query("SELECT DISTINCT expense_date FROM staff_expenses WHERE expense_date BETWEEN '$from_date' AND '$to_date'")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) {}
try { $d7 = $conn->query("SELECT DISTINCT txn_date FROM sys_dps_ledger WHERE txn_date BETWEEN '$from_date' AND '$to_date'")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) {}
try { $d8 = $conn->query("SELECT DISTINCT txn_date FROM sys_loan_ledger WHERE txn_date BETWEEN '$from_date' AND '$to_date'")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) {}
try { $d9 = $conn->query("SELECT DISTINCT txn_date FROM sys_card_ledger WHERE txn_date BETWEEN '$from_date' AND '$to_date'")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) {}

$all_dates   = array_values(array_unique(array_merge($d1,$d2,$d3,$d4,$d5,$d6,$d7,$d8,$d9)));
rsort($all_dates);
$total_dates = count($all_dates);

// ============================================================
// Period Summary হিসাব
// ============================================================
$gt_sale_cash=0; $gt_sale_due=0; $gt_coll=0; $gt_exp=0;
$gt_cust_rcv=0;  $gt_sup_pay=0;  $gt_staff=0;

$rids = $conn->query("SELECT id FROM daily_reports WHERE report_date BETWEEN '$from_date' AND '$to_date'")->fetchAll(PDO::FETCH_COLUMN);
if ($rids) {
    $rs  = implode(',', $rids);
    $ss  = $conn->query("SELECT COALESCE(SUM(paid_amount),0) p, COALESCE(SUM(due_amount),0) d FROM sales_entries WHERE report_id IN ($rs)")->fetch();
    $cs  = $conn->query("SELECT COALESCE(SUM(total_deposit),0) t FROM collection_entries WHERE report_id IN ($rs)")->fetch();
    $es  = $conn->query("SELECT COALESCE(SUM(amount),0) a FROM expense_entries WHERE report_id IN ($rs)")->fetch();
    $gt_sale_cash = floatval($ss['p']); $gt_sale_due = floatval($ss['d']);
    $gt_coll = floatval($cs['t']);      $gt_exp = floatval($es['a']);
}
$gt_cust_rcv = floatval($conn->query("SELECT COALESCE(SUM(received_amount),0) FROM customer_transactions WHERE tr_date BETWEEN '$from_date' AND '$to_date'")->fetchColumn());
$gt_sup_pay  = floatval($conn->query("SELECT COALESCE(SUM(payment_given),0) FROM supplier_transactions WHERE tr_date BETWEEN '$from_date' AND '$to_date'")->fetchColumn());
try { $gt_staff = floatval($conn->query("SELECT COALESCE(SUM(amount),0) FROM staff_expenses WHERE expense_date BETWEEN '$from_date' AND '$to_date'")->fetchColumn()); } catch (Exception $e) {}

$gt_loan_in=0; $gt_loan_out=0;
try {
    $gt_loan_in  = floatval($conn->query("SELECT COALESCE(SUM(debit_amount),0) FROM sys_loan_ledger WHERE txn_date BETWEEN '$from_date' AND '$to_date' AND description NOT LIKE '%মুনাফা%'")->fetchColumn());
    $gt_loan_out = floatval($conn->query("SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE txn_date BETWEEN '$from_date' AND '$to_date'")->fetchColumn());
} catch (Exception $e) {}

$gt_dps_dep=0; $gt_dps_wth=0;
try {
    $gt_dps_dep = floatval($conn->query("SELECT COALESCE(SUM(deposit_amount),0) FROM sys_dps_ledger WHERE txn_date BETWEEN '$from_date' AND '$to_date' AND description NOT LIKE '%মুনাফা%' AND description NOT LIKE '%Opening%'")->fetchColumn());
    $gt_dps_wth = floatval($conn->query("SELECT COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE txn_date BETWEEN '$from_date' AND '$to_date'")->fetchColumn());
} catch (Exception $e) {}

$gt_card_pay=0; $gt_card_charge=0; $gt_card_advance=0; $gt_card_cash_out=0;
$gt_card_outstanding=0; $gt_card_lifetime_charge=0;
$card_active_count=0; $card_inactive_count=0;
try {
    $gt_card_cash_out = floatval($conn->query("SELECT COALESCE(SUM(ABS(cash_impact)),0) FROM sys_card_ledger
        WHERE txn_date BETWEEN '$from_date' AND '$to_date' AND cash_impact < 0")->fetchColumn());
    $gt_card_advance = floatval($conn->query("SELECT COALESCE(SUM(cash_impact),0) FROM sys_card_ledger
        WHERE txn_date BETWEEN '$from_date' AND '$to_date' AND cash_impact > 0")->fetchColumn());
    $gt_card_outstanding = floatval($conn->query("SELECT COALESCE(SUM(card_balance_change),0) FROM sys_card_ledger
        WHERE card_id IN (SELECT id FROM sys_credit_cards WHERE status='active')")->fetchColumn());
    if ($gt_card_outstanding < 0) $gt_card_outstanding = 0;
    $card_active_count   = intval($conn->query("SELECT COUNT(*) FROM sys_credit_cards WHERE status='active'")->fetchColumn());
} catch (Exception $e) {}

$total_in  = $gt_sale_cash + $gt_coll + $gt_cust_rcv + $gt_loan_in  + $gt_dps_wth + $gt_card_advance;
$total_out = $gt_exp       + $gt_sup_pay + $gt_staff  + $gt_loan_out + $gt_dps_dep + $gt_card_cash_out;
$net_cash  = $total_in - $total_out;

// Opening & Closing Balance
$ob = 0;
try {
    $ob_rids = $conn->query("SELECT id FROM daily_reports WHERE report_date < '$from_date'")->fetchAll(PDO::FETCH_COLUMN);
    $ob_sale=0; $ob_coll=0; $ob_exp=0;
    if ($ob_rids) {
        $ors     = implode(',', $ob_rids);
        $ob_sale = floatval($conn->query("SELECT COALESCE(SUM(paid_amount),0) FROM sales_entries WHERE report_id IN ($ors)")->fetchColumn());
        $ob_coll = floatval($conn->query("SELECT COALESCE(SUM(total_deposit),0) FROM collection_entries WHERE report_id IN ($ors)")->fetchColumn());
        $ob_exp  = floatval($conn->query("SELECT COALESCE(SUM(amount),0) FROM expense_entries WHERE report_id IN ($ors)")->fetchColumn());
    }
    $ob_cust   = floatval($conn->query("SELECT COALESCE(SUM(received_amount),0) FROM customer_transactions WHERE tr_date < '$from_date'")->fetchColumn());
    $ob_sup    = floatval($conn->query("SELECT COALESCE(SUM(payment_given),0) FROM supplier_transactions WHERE tr_date < '$from_date'")->fetchColumn());
    $ob_staff  = 0; try { $ob_staff = floatval($conn->query("SELECT COALESCE(SUM(amount),0) FROM staff_expenses WHERE expense_date < '$from_date'")->fetchColumn()); } catch (Exception $e) {}
    $ob_lin=0; $ob_lout=0; try { $ob_lin  = floatval($conn->query("SELECT COALESCE(SUM(debit_amount),0) FROM sys_loan_ledger WHERE txn_date < '$from_date' AND description NOT LIKE '%মুনাফা%'")->fetchColumn()); $ob_lout = floatval($conn->query("SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE txn_date < '$from_date'")->fetchColumn()); } catch (Exception $e) {}
    $ob_dd=0; $ob_dw=0; try { $ob_dd = floatval($conn->query("SELECT COALESCE(SUM(deposit_amount),0) FROM sys_dps_ledger WHERE txn_date < '$from_date' AND description NOT LIKE '%মুনাফা%' AND description NOT LIKE '%Opening%'")->fetchColumn()); $ob_dw = floatval($conn->query("SELECT COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE txn_date < '$from_date'")->fetchColumn()); } catch (Exception $e) {}
    $ob_card_in=0; $ob_card_out=0; try { $ob_card_out = floatval($conn->query("SELECT COALESCE(SUM(ABS(cash_impact)),0) FROM sys_card_ledger WHERE txn_date < '$from_date' AND cash_impact < 0")->fetchColumn()); $ob_card_in  = floatval($conn->query("SELECT COALESCE(SUM(cash_impact),0) FROM sys_card_ledger WHERE txn_date < '$from_date' AND cash_impact > 0")->fetchColumn()); } catch (Exception $e) {}
    $ob = ($ob_sale+$ob_coll+$ob_cust+$ob_lin+$ob_dw+$ob_card_in) - ($ob_exp+$ob_sup+$ob_staff+$ob_lout+$ob_dd+$ob_card_out);
} catch (Exception $e) {}
$closing = $ob + $net_cash;

$loan_outstanding=0; $dps_total=0;
try { $loan_outstanding = floatval($conn->query("SELECT COALESCE(SUM(current_balance),0) FROM sys_loans WHERE status='active'")->fetchColumn()); } catch (Exception $e) {}
try { $dps_total        = floatval($conn->query("SELECT COALESCE(SUM(total_balance),0) FROM sys_dps_accounts WHERE status='active'")->fetchColumn()); } catch (Exception $e) {}

$card_type_labels = [ 'bill_pay' => 'বিল পরিশোধ', 'min_pay' => 'মিনিমাম বিল', 'full_pay' => 'ফুল পরিশোধ', 'charge_pay' => 'চার্জ পরিশোধ', 'cash_advance' => 'ক্যাশ অ্যাডভান্স', 'purchase' => 'কেনাকাটা' ];
?>

<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>লেজার রিপোর্ট — Sada Kalo Fashion</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style_css/monthly_report.css">
</head>
<body>

<div class="ticker no-print">
    <span class="t-lbl">🤲 বিসমিল্লাহ</span>
    <span class="t-txt">🌿 بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ — পরম করুণাময় আল্লাহর নামে 🍃 &nbsp;&nbsp;&nbsp; আলহামদুলিল্লাহ — সমস্ত প্রশংসা আল্লাহর ❤️ &nbsp;&nbsp;&nbsp; সুবহানাল্লাহ 🍂</span>
</div>

<nav class="navbar no-print">
    <div class="nv-l">
        <a href="dashboard.php" class="nv-back"><i class="fas fa-home"></i></a>
    </div>
    <div class="nv-c">
        <img src="logo.png" class="nv-logo" alt="SKF" onerror="this.src='https://via.placeholder.com/32?text=SK'">
        <div style="text-align:center">
            <div class="brand-t">SADA KALO FASHION</div>
            <div class="brand-s">লেজার রিপোর্ট</div>
        </div>
    </div>
    <div class="nv-r">
        <div class="ic-btn no-print" onclick="window.print()"><i class="fas fa-print"></i></div>
        <div class="ic-btn no-print" onclick="toggleTheme()"><i id="themeIco" class="fas fa-moon"></i></div>
    </div>
</nav>

<div id="imgModal" onclick="this.classList.remove('show')"><img id="bigImg"></div>

<div class="pw-modal" id="pwModal">
    <div class="pw-box">
        <i class="fas fa-shield-alt pw-ico"></i>
        <div class="pw-ttl" id="pwTitle">এন্ট্রি ডিলিট করবেন?</div>
        <div class="pw-sub" id="pwSub">Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small></div>
        <input type="password" id="pwInp" class="pw-inp" placeholder="••••••••" autocomplete="off">
        <div class="pw-err" id="pwErr"></div>
        <div class="pw-btns">
            <button class="pw-cancel" onclick="closePwModal()">বাতিল</button>
            <button class="pw-ok" id="pwOkBtn" onclick="pwConfirm()">নিশ্চিত করুন</button>
        </div>
    </div>
</div>

<div class="wrap">

<?php if (isset($success_msg)): ?>
<div class="alert alert-ok no-print"><span><i class="fas fa-check-circle" style="margin-right:6px"></i><?php echo $success_msg; ?></span><span class="alert-x" onclick="this.parentElement.remove()">&times;</span></div>
<?php endif; ?>
<?php if (isset($error_msg)): ?>
<div class="alert alert-err no-print"><span><i class="fas fa-exclamation-triangle" style="margin-right:6px"></i><?php echo $error_msg; ?></span><span class="alert-x" onclick="this.parentElement.remove()">&times;</span></div>
<?php endif; ?>

<div class="ph no-print">
    <div class="ph-title"><div class="ph-dot"></div>লেজার রিপোর্ট</div>
    <div class="ph-badge"><?php echo date('d M', strtotime($from_date)); ?> — <?php echo date('d M Y', strtotime($to_date)); ?></div>
</div>

<div class="top-sum-grid no-print">
    <div class="top-sum-box tsb-1">
        <div class="top-sum-ico"><i class="fas fa-arrow-circle-up"></i></div>
        <div class="top-sum-lbl">Period IN</div>
        <div class="top-sum-val">৳<?php echo number_format($total_in); ?></div>
    </div>
    <div class="top-sum-box tsb-2">
        <div class="top-sum-ico"><i class="fas fa-arrow-circle-down"></i></div>
        <div class="top-sum-lbl">Period OUT</div>
        <div class="top-sum-val">৳<?php echo number_format($total_out); ?></div>
    </div>
    <div class="top-sum-box tsb-3">
        <div class="top-sum-ico"><i class="fas fa-coins"></i></div>
        <div class="top-sum-lbl">Net Cash</div>
        <div class="top-sum-val" style="color:<?php echo $net_cash>=0?'var(--amber)':'var(--ruby)'; ?>">৳<?php echo number_format($net_cash); ?></div>
    </div>
    <div class="top-sum-box tsb-4">
        <div class="top-sum-ico"><i class="fas fa-wallet"></i></div>
        <div class="top-sum-lbl">Closing Balance</div>
        <div class="top-sum-val" style="color:<?php echo $closing>=0?'var(--green)':'var(--ruby)'; ?>">৳<?php echo number_format($closing); ?></div>
    </div>
</div>

<div class="filter-box no-print" id="filterBox">
<?php if ($role === 'admin'): ?>
    <form method="GET">
        <label class="flbl">শুরুর তারিখ</label>
        <input type="date" name="from_date" value="<?php echo $from_date; ?>" class="finp">
        <label class="flbl">শেষের তারিখ</label>
        <input type="date" name="to_date" value="<?php echo $to_date; ?>" class="finp">
        <button type="submit" class="btn-srch"><i class="fas fa-search" style="margin-right:6px"></i>রেকর্ড খুঁজুন</button>
        <div class="fq-row">
            <button type="button" onclick="qDate(7)"  class="fqb">৭ দিন</button>
            <button type="button" onclick="qDate(15)" class="fqb">১৫ দিন</button>
            <button type="button" onclick="qDate(30)" class="fqb">৩০ দিন</button>
            <button type="button" onclick="qMonth()"  class="fqb">এই মাস</button>
            <button type="button" onclick="qAll()"    class="fqb">সব</button>
        </div>
    </form>
<?php else: ?>
    <div style="text-align:center;padding:10px;font-size:11px;color:var(--tm);font-weight:700">Manager / User — শুধু আজকের ডাটা দেখা যাবে।</div>
<?php endif; ?>
</div>

<div class="multi-row no-print">
    <div class="ds-box ds-loan">
        <div class="ds-ttl"><i class="fas fa-university"></i> লোন সামারি</div>
        <div class="ds-row"><span class="ds-lbl">পিরিয়ডে নেওয়া (+)</span><span class="ds-val np">৳ <?php echo number_format($gt_loan_in); ?></span></div>
        <div class="ds-row"><span class="ds-lbl">পিরিয়ডে পরিশোধ (−)</span><span class="ds-val nn">৳ <?php echo number_format($gt_loan_out); ?></span></div>
        <div class="ds-row"><span class="ds-lbl">মোট বকেয়া</span><span class="ds-val na">৳ <?php echo number_format($loan_outstanding); ?></span></div>
    </div>
    <div class="ds-box ds-dps">
        <div class="ds-ttl"><i class="fas fa-piggy-bank"></i> DPS সামারি</div>
        <div class="ds-row"><span class="ds-lbl">পিরিয়ডে জমা (−)</span><span class="ds-val nn">৳ <?php echo number_format($gt_dps_dep); ?></span></div>
        <div class="ds-row"><span class="ds-lbl">উত্তোলন / ক্লোজ (+)</span><span class="ds-val np">৳ <?php echo number_format($gt_dps_wth); ?></span></div>
        <div class="ds-row"><span class="ds-lbl">তহবিলে মোট জমা</span><span class="ds-val nb">৳ <?php echo number_format($dps_total); ?></span></div>
    </div>
    <?php if ($card_active_count > 0 || $gt_card_cash_out > 0 || $gt_card_advance > 0): ?>
    <div class="ds-box ds-card">
        <div class="ds-ttl"><i class="fas fa-credit-card"></i> কার্ড সামারি</div>
        <div class="ds-row"><span class="ds-lbl">পিরিয়ডে পেমেন্ট (−)</span><span class="ds-val nn">৳ <?php echo number_format($gt_card_cash_out); ?></span></div>
        <div class="ds-row"><span class="ds-lbl">কার্ড থেকে ক্যাশ (+)</span><span class="ds-val np">৳ <?php echo number_format($gt_card_advance); ?></span></div>
        <div class="ds-row"><span class="ds-lbl">মোট বকেয়া</span><span class="ds-val" style="color:var(--sky)">৳ <?php echo number_format($gt_card_outstanding); ?></span></div>
    </div>
    <?php else: ?>
    <div class="ds-box ds-card" style="opacity:.5">
        <div class="ds-ttl"><i class="fas fa-credit-card"></i> কার্ড সামারি</div>
        <div style="text-align:center;padding:14px 0;font-size:10px;color:var(--tm);font-weight:700">
            <i class="fas fa-credit-card" style="font-size:20px;display:block;margin-bottom:5px;opacity:.4"></i>
            কোনো কার্ড নেই<br>
            <a href="credit_card.php" style="color:var(--cyan);font-size:9px;text-decoration:none">+ কার্ড যোগ করুন</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="summary-panel no-print">
    <div class="summary-title"><i class="fas fa-chart-pie" style="color:var(--cyan)"></i> Period Summary</div>
    <div class="stat-grid">
        <div class="stat-item"><div class="stat-label">Cash Sales</div><div class="stat-value np"><?php echo number_format($gt_sale_cash); ?></div></div>
        <div class="stat-item"><div class="stat-label">Due Sales</div><div class="stat-value nn"><?php echo number_format($gt_sale_due); ?></div></div>
        <div class="stat-item"><div class="stat-label">Collection</div><div class="stat-value np"><?php echo number_format($gt_coll); ?></div></div>
        <div class="stat-item"><div class="stat-label">Expense</div><div class="stat-value nn"><?php echo number_format($gt_exp); ?></div></div>
        <div class="stat-item"><div class="stat-label">Cust. Rcv</div><div class="stat-value np"><?php echo number_format($gt_cust_rcv); ?></div></div>
        <div class="stat-item"><div class="stat-label">Sup. Paid</div><div class="stat-value nn"><?php echo number_format($gt_sup_pay); ?></div></div>
        <div class="stat-item"><div class="stat-label">Staff Paid</div><div class="stat-value nn"><?php echo number_format($gt_staff); ?></div></div>
        <div class="stat-item" style="border-color:rgba(0,194,255,.2)"><div class="stat-label">লোন নেওয়া (+)</div><div class="stat-value np"><?php echo number_format($gt_loan_in); ?></div></div>
        <div class="stat-item" style="border-color:rgba(255,61,110,.2)"><div class="stat-label">লোন পরিশোধ (−)</div><div class="stat-value nn"><?php echo number_format($gt_loan_out); ?></div></div>
        <div class="stat-item" style="border-color:rgba(255,61,110,.2)"><div class="stat-label">DPS জমা (−)</div><div class="stat-value nn"><?php echo number_format($gt_dps_dep); ?></div></div>
        <div class="stat-item" style="border-color:rgba(0,194,255,.2)"><div class="stat-label">DPS উত্তোলন (+)</div><div class="stat-value np"><?php echo number_format($gt_dps_wth); ?></div></div>
        <?php if ($card_active_count > 0 || $gt_card_cash_out > 0): ?>
        <div class="stat-item" style="border-color:rgba(124,45,18,.4)"><div class="stat-label">কার্ড পেমেন্ট (−)</div><div class="stat-value nn"><?php echo number_format($gt_card_cash_out); ?></div></div>
        <?php if ($gt_card_advance > 0): ?>
        <div class="stat-item" style="border-color:rgba(21,94,117,.4)"><div class="stat-label">কার্ড ক্যাশ (+)</div><div class="stat-value np"><?php echo number_format($gt_card_advance); ?></div></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="bal-row">
        <div class="balance-item"><div class="balance-label">Period IN</div><div class="balance-value np">৳ <?php echo number_format($total_in); ?></div></div>
        <div class="balance-item"><div class="balance-label">Period OUT</div><div class="balance-value nn">৳ <?php echo number_format($total_out); ?></div></div>
        <div class="balance-item" style="border-color:var(--b3)">
            <div class="balance-label">Net Cash</div>
            <div class="balance-value" style="color:<?php echo $net_cash>=0?'var(--cyan)':'var(--ruby)'; ?>">৳ <?php echo number_format($net_cash); ?></div>
        </div>
    </div>
    <div class="bal-row" style="margin-top:5px">
        <div class="balance-item">
            <div class="balance-label">Opening Balance</div>
            <div class="balance-value nb">৳ <?php echo number_format($ob); ?></div>
        </div>
        <div class="balance-item" style="grid-column:span 2;border-color:var(--b3)">
            <div class="balance-label">Closing Balance (Final)</div>
            <div class="balance-value" style="font-size:17px;color:<?php echo $closing>=0?'var(--cyan)':'var(--ruby)'; ?>">৳ <?php echo number_format($closing); ?></div>
        </div>
    </div>
</div>

<div class="global-search-container no-print">
    <div class="gs-wrapper">
        <i class="fas fa-search gs-icon"></i>
        <input type="text" id="globalSearchInp" class="gs-input" placeholder="যেকোনো নাম, মেমো বা অংক খুঁজুন..." autocomplete="off">
        <button class="gs-btn" onclick="clearSearch()"><i class="fas fa-times"></i></button>
    </div>
    <div id="gsResults" class="gs-results"></div>
</div>

<div class="month-pager no-print" style="margin-bottom: 12px; margin-top: 0; padding: 8px 15px;">
    <?php $activeTab = $_GET['tab'] ?? 'all'; ?>
    <a href="?m=<?php echo $prev_m; ?>&y=<?php echo $prev_y; ?>&tab=<?php echo $activeTab; ?>" class="mp-btn"><i class="fas fa-chevron-left"></i> আগের মাস</a>
    <div class="mp-title"><i class="fas fa-calendar-alt" style="color:var(--gold)"></i> <?php echo $current_bn_month; ?></div>
    <a href="?m=<?php echo $next_m; ?>&y=<?php echo $next_y; ?>&tab=<?php echo $activeTab; ?>" class="mp-btn">পরের মাস <i class="fas fa-chevron-right"></i></a>
</div>

<div class="ledger-tabs no-print">
    <button class="ledger-tab-btn active" onclick="filterLedger('all', this)"><i class="fas fa-list-ul"></i> সব লেজার</button>
    <button class="ledger-tab-btn" onclick="filterLedger('sales', this)"><i class="fas fa-shopping-cart"></i> ক্যাশ বিক্রি</button>
    <button class="ledger-tab-btn" onclick="filterLedger('collection', this)"><i class="fas fa-hand-holding-usd"></i> কালেকশন</button>
    <button class="ledger-tab-btn" onclick="filterLedger('expense', this)"><i class="fas fa-file-invoice-dollar"></i> দোকান খরচ</button>
    <button class="ledger-tab-btn" onclick="filterLedger('staff', this)"><i class="fas fa-user-tie"></i> স্টাফ খরচ</button>
    <button class="ledger-tab-btn" onclick="filterLedger('supplier', this)"><i class="fas fa-truck"></i> সাপ্লায়ার পেমেন্ট</button>
    <button class="ledger-tab-btn" onclick="filterLedger('customer', this)"><i class="fas fa-users"></i> কাস্টমার পেমেন্ট</button>
    <button class="ledger-tab-btn" onclick="filterLedger('stock', this)"><i class="fas fa-boxes"></i> স্টক হিস্ট্রি</button>
    <button class="ledger-tab-btn" onclick="filterLedger('loan', this)"><i class="fas fa-university"></i> লোন লেজার</button>
    <button class="ledger-tab-btn" onclick="filterLedger('dps', this)"><i class="fas fa-piggy-bank"></i> DPS লেজার</button>
    <button class="ledger-tab-btn" onclick="filterLedger('card', this)"><i class="fas fa-credit-card"></i> কার্ড লেজার</button>
</div>

<div class="dri no-print">
    <i class="fas fa-calendar-alt"></i>
    <span><?php echo date('d M Y', strtotime($from_date)); ?> — <?php echo date('d M Y', strtotime($to_date)); ?></span>
    <span class="dri-cnt"><?php echo $total_dates; ?> দিনের ডাটা</span>
</div>

<?php if (empty($all_dates)): ?>
<div class="empty">
    <span class="empty-ico"><i class="fas fa-inbox"></i></span>
    <div class="empty-txt">এই সময়ে কোনো ডাটা পাওয়া যায়নি।</div>
</div>
<?php else: ?>

<?php foreach ($all_dates as $cdate):
    $dr=$conn->query("SELECT * FROM daily_reports WHERE report_date='$cdate'")->fetch();
    $sales=[]; $colls=[]; $exps=[];
    if ($dr){
        $rid=$dr['id'];
        $sales=$conn->query("SELECT * FROM sales_entries WHERE report_id=$rid")->fetchAll();
        $colls=$conn->query("SELECT * FROM collection_entries WHERE report_id=$rid")->fetchAll();
        $exps =$conn->query("SELECT * FROM expense_entries WHERE report_id=$rid")->fetchAll();
    }
    $custT  = $conn->query("SELECT ct.*,c.customer_name,c.shop_name,c.phone FROM customer_transactions ct JOIN customers c ON ct.customer_id=c.id WHERE ct.tr_date='$cdate'")->fetchAll();
    $supT   = $conn->query("SELECT st.*,s.name,s.shop_name FROM supplier_transactions st JOIN suppliers s ON st.supplier_id=s.id WHERE st.tr_date='$cdate'")->fetchAll();
    $ostkT  = []; try{$ostkT = $conn->query("SELECT * FROM stocks_ WHERE tr_date='$cdate'")->fetchAll();}catch(Exception $e){}
    $nstkT  = []; try{$nstkT = $conn->query("SELECT * FROM stocks WHERE DATE(created_at)='$cdate'")->fetchAll();}catch(Exception $e){}
    $staffT = []; try{$staffT = $conn->query("SELECT se.*,s.staff_name name FROM staff_expenses se JOIN staff_info s ON se.staff_id=s.id WHERE se.expense_date='$cdate'")->fetchAll(PDO::FETCH_ASSOC);}catch(Exception $e){}
    $dpsT   = []; try{$dpsT = $conn->query("SELECT l.*,a.client_name,a.account_number,a.account_type,a.id acc_id FROM sys_dps_ledger l JOIN sys_dps_accounts a ON l.dps_id=a.id WHERE l.txn_date='$cdate' ORDER BY l.id DESC")->fetchAll(PDO::FETCH_ASSOC);}catch(Exception $e){}
    $loanT  = []; try{$loanT = $conn->query("SELECT l.*,s.borrower_name,s.loan_category FROM sys_loan_ledger l JOIN sys_loans s ON l.loan_id=s.id WHERE l.txn_date='$cdate' ORDER BY l.id DESC")->fetchAll(PDO::FETCH_ASSOC);}catch(Exception $e){}
    $cardOutT = []; $cardInT = [];
    try {
        $cardOutT = $conn->query("SELECT cl.*,cc.card_name,cc.card_last4 FROM sys_card_ledger cl JOIN sys_credit_cards cc ON cl.card_id=cc.id WHERE cl.txn_date='$cdate' AND cl.cash_impact < 0 ORDER BY cl.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $cardInT  = $conn->query("SELECT cl.*,cc.card_name,cc.card_last4 FROM sys_card_ledger cl JOIN sys_credit_cards cc ON cl.card_id=cc.id WHERE cl.txn_date='$cdate' AND cl.cash_impact > 0 ORDER BY cl.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e){}

    // দৈনিক ক্যাশ IN / OUT হিসাব
    $day_in  = 0; $day_out = 0;
    foreach($sales as $s)  { $day_in  += floatval($s['paid_amount']); }
    foreach($colls as $c)  { $day_in  += floatval($c['total_deposit']); }
    foreach($custT as $ct) { $day_in  += floatval($ct['received_amount']); }
    foreach($exps as $e)   { $day_out += floatval($e['amount']); }
    foreach($supT as $st)  { $day_out += floatval($st['payment_given']); }
    foreach($staffT as $sf){ $day_out += floatval($sf['amount']); }
    foreach($cardOutT as $co){ $day_out += abs(floatval($co['cash_impact'])); }
    foreach($cardInT as $ci) { $day_in  += floatval($ci['cash_impact']); }
    foreach($dpsT as $dl)  {
        $dep = floatval($dl['deposit_amount']); $wth = floatval($dl['withdraw_amount']);
        if(!stristr($dl['description'],'মুনাফা') && !stristr($dl['description'],'Opening')) $day_out += $dep;
        $day_in += $wth;
    }
    foreach($loanT as $ll) {
        if(!stristr($ll['description'],'মুনাফা')) $day_in += floatval($ll['debit_amount']);
        $day_out += floatval($ll['credit_amount']);
    }
?>
<div class="date-card">
    <div class="date-hdr">
        <div class="date-hdr-l">
            <div class="date-dot"></div>
            <div class="date-day"><?php echo date('d M Y', strtotime($cdate)); ?></div>
        </div>
        <div class="date-wday"><?php echo date('l', strtotime($cdate)); ?></div>
    </div>

    <div class="date-strip no-print">
        <div class="ds-chip dsc-in"><i class="fas fa-arrow-up"></i> IN: ৳<?php echo number_format($day_in); ?></div>
        <div class="ds-chip dsc-out"><i class="fas fa-arrow-down"></i> OUT: ৳<?php echo number_format($day_out); ?></div>
        <?php $day_net = $day_in - $day_out; ?>
        <div class="ds-chip" style="background:<?php echo $day_net>=0?'rgba(0,194,255,.1)':'rgba(255,61,110,.1)'; ?>;border-color:<?php echo $day_net>=0?'rgba(0,194,255,.3)':'rgba(255,61,110,.3)'; ?>;color:<?php echo $day_net>=0?'var(--cyan)':'var(--ruby)'; ?>">
            <i class="fas fa-balance-scale"></i> Net: ৳<?php echo number_format($day_net); ?>
        </div>
    </div>

    <?php if(!empty($sales)): $tb=0;$tp=0;$td=0;$tq=0; ?>
    <div class="ledger-group cat-sales">
        <div class="sec-hdr s-sale"><div class="sec-ico"><i class="fas fa-shopping-cart"></i></div><div class="sec-name">Sales Entry</div><span class="sec-badge"><?php echo count($sales); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th>MEMO</th><th>CUSTOMER</th><th>QTY</th><th>BILL</th><th>PAID</th><th>DUE</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($sales as $s): $tb+=$s['total_bill'];$tp+=$s['paid_amount'];$td+=$s['due_amount'];$tq+=$s['quantity']; ?>
            <tr class="<?php echo $s['due_amount']>0?'row-due':''; ?>">
                <td class="fw9 nw" style="font-family:var(--mono)"><?php echo $s['memo_no']; ?></td>
                <td class="lft"><?php echo htmlspecialchars($s['customer_name']); ?></td>
                <td class="nq fw9"><?php echo $s['quantity']; ?></td>
                <td class="nw" style="font-family:var(--mono)"><?php echo number_format($s['total_bill']); ?></td>
                <td class="np fw9"><?php echo number_format($s['paid_amount']); ?></td>
                <td class="nn fw9"><?php echo number_format($s['due_amount']); ?></td>
                <td><?php echo !empty($s['photo'])?"<img src='{$s['photo']}' loading='lazy' class='thumb' onclick='showBig(this.src)'>":"<span class='mt'>—</span>"; ?></td>
                <td><span class="ubadge"><?php echo $s['entry_by']??'N/A'; ?></span></td>
                <td class="no-print"><?php if($role==='admin'): ?><button onclick="openPw('edit','sales_entries',<?php echo $s['id']; ?>,'total_bill',<?php echo $s['total_bill']; ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button><button onclick="openPw('delete','sales_entries',<?php echo $s['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nq"><?php echo $tq; ?></td><td class="nw" style="font-family:var(--mono)"><?php echo number_format($tb); ?></td><td class="np"><?php echo number_format($tp); ?></td><td class="nn"><?php echo number_format($td); ?></td><td colspan="3"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($colls)): $tc=0; ?>
    <div class="ledger-group cat-collection">
        <div class="sec-hdr s-col"><div class="sec-ico"><i class="fas fa-hand-holding-usd"></i></div><div class="sec-name">Collection</div><span class="sec-badge"><?php echo count($colls); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th>CUSTOMER</th><th>CASH</th><th>BKASH</th><th>TOTAL</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($colls as $c): $tc+=$c['total_deposit']; ?>
            <tr>
                <td class="lft"><?php echo htmlspecialchars($c['payer_name']); ?></td>
                <td class="nw" style="font-family:var(--mono)"><?php echo number_format($c['cash_amount']); ?></td>
                <td class="nw" style="font-family:var(--mono)"><?php echo number_format($c['bkash_amount']); ?></td>
                <td class="np fw9"><?php echo number_format($c['total_deposit']); ?></td>
                <td><span class="ubadge"><?php echo $c['entry_by']??'N/A'; ?></span></td>
                <td class="no-print"><?php if($role==='admin'): ?><button onclick="openPw('edit','collection_entries',<?php echo $c['id']; ?>,'total_deposit',<?php echo $c['total_deposit']; ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button><button onclick="openPw('delete','collection_entries',<?php echo $c['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3" style="text-align:right">Sub-Total</td><td class="np"><?php echo number_format($tc); ?></td><td colspan="2"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($exps)): $te=0; ?>
    <div class="ledger-group cat-expense">
        <div class="sec-hdr s-exp"><div class="sec-ico"><i class="fas fa-file-invoice-dollar"></i></div><div class="sec-name">Expense Ledger</div><span class="sec-badge"><?php echo count($exps); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th>বিবরণ</th><th>পরিমাণ</th><th>ভাউচার</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($exps as $e): $te+=$e['amount']; ?>
            <tr>
                <td class="lft"><?php echo htmlspecialchars($e['description']); ?></td>
                <td class="nn fw9"><?php echo number_format($e['amount']); ?></td>
                <td><?php echo !empty($e['photo'])?"<img src='{$e['photo']}' loading='lazy' class='thumb' onclick='showBig(this.src)'>":"<span class='mt'>—</span>"; ?></td>
                <td><span class="ubadge"><?php echo $e['entry_by']??'N/A'; ?></span></td>
                <td class="no-print"><?php if($role==='admin'): ?><button onclick="openPw('edit','expense_entries',<?php echo $e['id']; ?>,'amount',<?php echo $e['amount']; ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button><button onclick="openPw('delete','expense_entries',<?php echo $e['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td style="text-align:right">Sub-Total</td><td class="nn"><?php echo number_format($te); ?></td><td colspan="3"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($staffT)): $tsf=0; ?>
    <div class="ledger-group cat-staff">
        <div class="sec-hdr s-staff"><div class="sec-ico"><i class="fas fa-user-tie"></i></div><div class="sec-name">Staff Expense</div><span class="sec-badge"><?php echo count($staffT); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th class="lft">স্টাফ</th><th>নোট</th><th>সময়</th><th>পরিমাণ</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($staffT as $sf): $tsf+=$sf['amount']; ?>
            <tr>
                <td class="lft"><div class="np fw9"><?php echo htmlspecialchars($sf['name']); ?></div></td>
                <td class="mt"><?php echo htmlspecialchars($sf['note']??$sf['expense_type']??'—'); ?></td>
                <td><span class="ubadge"><?php echo isset($sf['expense_time'])?date('h:i A',strtotime($sf['expense_time'])):'—'; ?></span></td>
                <td class="nn fw9">৳ <?php echo number_format($sf['amount']); ?></td>
                <td><span class="ubadge"><?php echo htmlspecialchars($sf['entry_by']??'N/A'); ?></span></td>
                <td class="no-print"><?php if($role==='admin'): ?><button onclick="openPw('delete','staff_expenses',<?php echo $sf['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3" style="text-align:right">Sub-Total</td><td class="nn">৳ <?php echo number_format($tsf); ?></td><td colspan="2"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($custT)): $tcb=0;$tcr=0; ?>
    <div class="ledger-group cat-customer">
        <div class="sec-hdr s-cust"><div class="sec-ico"><i class="fas fa-users"></i></div><div class="sec-name">Customer Ledger</div><span class="sec-badge"><?php echo count($custT); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th class="lft">গ্রাহক</th><th>বিবরণ</th><th>বিল</th><th>প্রাপ্ত</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($custT as $ct): $tcb+=$ct['bill_amount'];$tcr+=$ct['received_amount']; ?>
            <tr class="<?php echo $ct['bill_amount']>$ct['received_amount']?'row-due':''; ?>">
                <td class="lft">
                    <div class="nv fw9" style="font-size:11px"><?php echo htmlspecialchars($ct['shop_name']); ?></div>
                    <div class="mt" style="font-size:9px;margin-top:1px"><?php echo htmlspecialchars($ct['customer_name']); ?></div>
                </td>
                <td class="mt"><?php echo htmlspecialchars($ct['description']??'N/A'); ?></td>
                <td class="nn fw9"><?php echo $ct['bill_amount']>0?number_format($ct['bill_amount']):'—'; ?></td>
                <td class="np fw9"><?php echo $ct['received_amount']>0?number_format($ct['received_amount']):'—'; ?></td>
                <td><?php echo !empty($ct['image_path'])?"<img src='{$ct['image_path']}' loading='lazy' class='thumb' onclick='showBig(this.src)'>":"<span class='mt'>—</span>"; ?></td>
                <td><span class="ubadge"><?php echo $ct['entry_by']??'N/A'; ?></span></td>
                <td class="no-print">
                    <a href="https://wa.me/88<?php echo $ct['phone']; ?>?text=<?php echo urlencode("তারিখ: ".date('d M Y',strtotime($cdate))."\nবিল: ৳".$ct['bill_amount']."\nপ্রাপ্ত: ৳".$ct['received_amount']); ?>" target="_blank" class="abt a-wa"><i class="fab fa-whatsapp"></i></a>
                    <?php if($role==='admin'): ?><button onclick="openPw('edit','customer_transactions',<?php echo $ct['id']; ?>,'bill_amount',<?php echo $ct['bill_amount']; ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button><button onclick="openPw('delete','customer_transactions',<?php echo $ct['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nn"><?php echo number_format($tcb); ?></td><td class="np"><?php echo number_format($tcr); ?></td><td colspan="3"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($supT)): $tsb=0;$tsp=0;$tsq=0; ?>
    <div class="ledger-group cat-supplier">
        <div class="sec-hdr s-sup"><div class="sec-ico"><i class="fas fa-truck"></i></div><div class="sec-name">Supplier Ledger</div><span class="sec-badge"><?php echo count($supT); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th class="lft">সাপ্লায়ার</th><th>মেমো</th><th>বিল</th><th>QTY</th><th>পেমেন্ট</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($supT as $st): $tsb+=$st['bill_received'];$tsp+=$st['payment_given'];$q=isset($st['quantity'])?$st['quantity']:(isset($st['pcs'])?$st['pcs']:0);$tsq+=$q; ?>
            <tr class="<?php echo $st['bill_received']>$st['payment_given']?'row-pend':''; ?>">
                <td class="lft">
                    <div class="na fw9"><?php echo htmlspecialchars($st['shop_name']); ?></div>
                    <div class="mt" style="font-size:9px;margin-top:1px"><?php echo htmlspecialchars($st['name']); ?></div>
                </td>
                <td class="nw" style="font-family:var(--mono);font-size:10px"><?php echo htmlspecialchars($st['memo_no']); ?></td>
                <td class="nn fw9"><?php echo $st['bill_received']>0?number_format($st['bill_received']):'—'; ?></td>
                <td class="nq"><?php echo $q>0?$q:'—'; ?></td>
                <td class="np fw9"><?php echo $st['payment_given']>0?number_format($st['payment_given']):'—'; ?></td>
                <td><?php echo !empty($st['photo'])?"<img src='{$st['photo']}' loading='lazy' class='thumb' onclick='showBig(this.src)'>":"<span class='mt'>—</span>"; ?></td>
                <td><span class="ubadge"><?php echo $st['entry_by']??'N/A'; ?></span></td>
                <td class="no-print"><?php if($role==='admin'): ?><button onclick="openPw('edit','supplier_transactions',<?php echo $st['id']; ?>,'bill_received',<?php echo $st['bill_received']; ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button><button onclick="openPw('delete','supplier_transactions',<?php echo $st['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nn"><?php echo number_format($tsb); ?></td><td class="nq"><?php echo $tsq; ?></td><td class="np"><?php echo number_format($tsp); ?></td><td colspan="3"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($ostkT)): $osi=0;$oso=0;$osb=0; ?>
    <div class="ledger-group cat-stock">
        <div class="sec-hdr s-stk"><div class="sec-ico"><i class="fas fa-boxes"></i></div><div class="sec-name">Stock History</div><span class="sec-badge"><?php echo count($ostkT); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th class="lft">বিবরণ</th><th>IN</th><th>OUT</th><th>বিল</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($ostkT as $sk): $inq=isset($sk['stock_in'])?$sk['stock_in']:0;$outq=isset($sk['stock_out'])?$sk['stock_out']:0;$osi+=$inq;$oso+=$outq;$osb+=$sk['total_bill']; ?>
            <tr>
                <td class="lft fw9"><?php echo htmlspecialchars($sk['description']); ?></td>
                <td class="np fw9"><?php echo $inq>0?$inq:'—'; ?></td>
                <td class="nn fw9"><?php echo $outq>0?$outq:'—'; ?></td>
                <td class="nb"><?php echo $sk['total_bill']>0?number_format($sk['total_bill']):'—'; ?></td>
                <td><?php echo !empty($sk['image'])?"<img src='{$sk['image']}' loading='lazy' class='thumb' onclick='showBig(this.src)'>":"<span class='mt'>—</span>"; ?></td>
                <td><span class="ubadge"><?php echo $sk['entry_by']??'N/A'; ?></span></td>
                <td class="no-print"><?php if($role==='admin'): ?><button onclick="openPw('delete','stock_entries',<?php echo $sk['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td style="text-align:right">Sub-Total</td><td class="np"><?php echo $osi; ?></td><td class="nn"><?php echo $oso; ?></td><td class="nb"><?php echo number_format($osb); ?></td><td colspan="3"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($nstkT)): $nsi=0;$nso=0;$nsb=0; ?>
    <div class="ledger-group cat-stock">
        <div class="sec-hdr s-nstk"><div class="sec-ico"><i class="fas fa-cart-plus"></i></div><div class="sec-name">Stock Added</div><span class="sec-badge"><?php echo count($nstkT); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th class="lft">বিবরণ</th><th>IN</th><th>OUT</th><th>বিল</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($nstkT as $sk): $inq=isset($sk['in_qty'])?$sk['in_qty']:0;$outq=isset($sk['out_qty'])?$sk['out_qty']:0;$nsi+=$inq;$nso+=$outq;$nsb+=$sk['total_bill']; ?>
            <tr>
                <td class="lft fw9"><?php echo htmlspecialchars($sk['description']); ?></td>
                <td class="np fw9"><?php echo $inq>0?$inq:'—'; ?></td>
                <td class="nn fw9"><?php echo $outq>0?$outq:'—'; ?></td>
                <td class="nb"><?php echo $sk['total_bill']>0?number_format($sk['total_bill']):'—'; ?></td>
                <td><?php echo !empty($sk['image'])?"<img src='{$sk['image']}' loading='lazy' class='thumb' onclick='showBig(this.src)'>":"<span class='mt'>—</span>"; ?></td>
                <td><span class="ubadge"><?php echo $sk['entry_by']??'N/A'; ?></span></td>
                <td class="no-print"><?php if($role==='admin'): ?><button onclick="openPw('delete','stocks',<?php echo $sk['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td style="text-align:right">Sub-Total</td><td class="np"><?php echo $nsi; ?></td><td class="nn"><?php echo $nso; ?></td><td class="nb"><?php echo number_format($nsb); ?></td><td colspan="3"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($dpsT)): $ddt=0;$dwt=0; ?>
    <div class="ledger-group cat-dps">
        <div class="sec-hdr s-dps"><div class="sec-ico"><i class="fas fa-piggy-bank"></i></div><div class="sec-name">DPS / FDR লেজার</div><span class="sec-badge"><?php echo count($dpsT); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th class="lft">A/C ও গ্রাহক</th><th>বিবরণ</th><th>জমা (+)</th><th>উত্তোলন (−)</th><th>ব্যালেন্স</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($dpsT as $dl):
                $dd=floatval($dl['deposit_amount']); $dw=floatval($dl['withdraw_amount']);
                $ddt+=$dd; $dwt+=$dw;
                $isO=stripos($dl['description'],'Opening')!==false;
                $accNo=!empty($dl['account_number'])?$dl['account_number']:($dl['account_type'].'-'.(1000+intval($dl['acc_id'])));
            ?>
            <tr>
                <td class="lft">
                    <div class="np fw9" style="font-size:11px"><?php echo htmlspecialchars($accNo); ?></div>
                    <div class="mt" style="font-size:9px;margin-top:1px"><?php echo htmlspecialchars($dl['client_name']); ?></div>
                </td>
                <td class="mt" style="font-size:10px"><?php echo htmlspecialchars($dl['description']); ?></td>
                <td class="np fw9"><?php echo $dd>0?'৳'.number_format($dd):'—'; ?></td>
                <td class="nn fw9"><?php echo $dw>0?'৳'.number_format($dw):'—'; ?></td>
                <td class="nb fw9">৳<?php echo number_format(floatval($dl['current_balance'])); ?></td>
                <td class="no-print"><?php if($role==='admin' && !$isO): ?><button onclick="openPwDps(<?php echo $dl['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="np">৳<?php echo number_format($ddt); ?></td><td class="nn">৳<?php echo number_format($dwt); ?></td><td colspan="2"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($loanT)): $ldt=0;$lct=0; ?>
    <div class="ledger-group cat-loan">
        <div class="sec-hdr s-loan"><div class="sec-ico"><i class="fas fa-university"></i></div><div class="sec-name">লোন লেজার (NGO / Bank)</div><span class="sec-badge"><?php echo count($loanT); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th class="lft">গ্রাহক</th><th>বিবরণ</th><th>ডেবিট (+)</th><th>পরিশোধ (−)</th><th>ব্যালেন্স</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($loanT as $ll):
                $ld=floatval($ll['debit_amount']); $lc=floatval($ll['credit_amount']);
                $ldt+=$ld; $lct+=$lc;
            ?>
            <tr>
                <td class="lft">
                    <div class="nn fw9" style="font-size:11px"><?php echo htmlspecialchars($ll['borrower_name']); ?></div>
                    <div class="mt" style="font-size:9px;margin-top:1px"><?php echo strtoupper($ll['loan_category']); ?></div>
                </td>
                <td class="mt" style="font-size:10px"><?php echo htmlspecialchars($ll['description']); ?></td>
                <td class="nn fw9"><?php echo $ld>0?'৳'.number_format($ld):'—'; ?></td>
                <td class="np fw9"><?php echo $lc>0?'−৳'.number_format($lc):'—'; ?></td>
                <td class="nb fw9">৳<?php echo number_format(floatval($ll['balance'])); ?></td>
                <td class="no-print"><?php if($role==='admin'): ?><button onclick="openPwLoan(<?php echo $ll['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nn">৳<?php echo number_format($ldt); ?></td><td class="np">−৳<?php echo number_format($lct); ?></td><td colspan="2"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($cardOutT)): $cot=0; ?>
    <div class="ledger-group cat-card">
        <div class="sec-hdr s-card-out"><div class="sec-ico"><i class="fas fa-credit-card"></i></div><div class="sec-name">ক্রেডিট কার্ড পেমেন্ট (−)</div><span class="sec-badge"><?php echo count($cardOutT); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th class="lft">কার্ড</th><th>টাইপ</th><th>অ্যামাউন্ট</th><th>চার্জ</th><th>ক্যাশ কাটা</th><th>রিসিট</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($cardOutT as $co):
                $cot += abs(floatval($co['cash_impact']));
                $tlbl = $card_type_labels[$co['txn_type']] ?? $co['txn_type'];
                $tcls = ['bill_pay'=>'ctb-bill','min_pay'=>'ctb-min','full_pay'=>'ctb-full','charge_pay'=>'ctb-chg','cash_advance'=>'ctb-adv','purchase'=>'ctb-pur'][$co['txn_type']] ?? 'ctb-bill';
            ?>
            <tr>
                <td class="lft">
                    <div class="fw9" style="font-size:11px;color:var(--sky)"><?php echo htmlspecialchars($co['card_name']); ?></div>
                    <div class="mt" style="font-size:9px">**** <?php echo $co['card_last4']; ?></div>
                </td>
                <td><span class="card-type-badge <?php echo $tcls; ?>"><?php echo $tlbl; ?></span></td>
                <td class="nn fw9">৳<?php echo number_format($co['amount']); ?></td>
                <td class="<?php echo $co['charge_amount']>0?'nn':'mt'; ?>"><?php echo $co['charge_amount']>0?'৳'.number_format($co['charge_amount']):'—'; ?></td>
                <td class="nn fw9">−৳<?php echo number_format(abs($co['cash_impact'])); ?></td>
                <td><?php echo !empty($co['receipt_image'])?"<img src='{$co['receipt_image']}' loading='lazy' class='thumb' onclick='showBig(this.src)'>":"<span class='mt'>—</span>"; ?></td>
                <td><span class="ubadge"><?php echo htmlspecialchars($co['entry_by']??'admin'); ?></span></td>
                <td class="no-print"><?php if($role==='admin'): ?><button onclick="openPwCard(<?php echo $co['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="4" style="text-align:right">মোট ক্যাশ কাটা</td><td class="nn">−৳<?php echo number_format($cot); ?></td><td colspan="3"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($cardInT)): $cit=0; ?>
    <div class="ledger-group cat-card">
        <div class="sec-hdr s-card-in"><div class="sec-ico"><i class="fas fa-hand-holding-usd"></i></div><div class="sec-name">ক্রেডিট কার্ড ক্যাশ (+)</div><span class="sec-badge"><?php echo count($cardInT); ?> entries</span></div>
        <div class="twrap"><table class="dt">
            <thead><tr><th class="lft">কার্ড</th><th>টাইপ</th><th>অ্যামাউন্ট</th><th>চার্জ</th><th>ক্যাশ যোগ</th><th>রিসিট</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
            <tbody>
            <?php foreach($cardInT as $ci):
                $cit += floatval($ci['cash_impact']);
                $tlbl = $card_type_labels[$ci['txn_type']] ?? $ci['txn_type'];
                $tcls = ['bill_pay'=>'ctb-bill','min_pay'=>'ctb-min','full_pay'=>'ctb-full','charge_pay'=>'ctb-chg','cash_advance'=>'ctb-adv','purchase'=>'ctb-pur'][$ci['txn_type']] ?? 'ctb-adv';
            ?>
            <tr>
                <td class="lft">
                    <div class="fw9" style="font-size:11px;color:var(--sky)"><?php echo htmlspecialchars($ci['card_name']); ?></div>
                    <div class="mt" style="font-size:9px">**** <?php echo $ci['card_last4']; ?></div>
                </td>
                <td><span class="card-type-badge <?php echo $tcls; ?>"><?php echo $tlbl; ?></span></td>
                <td class="nw fw9">৳<?php echo number_format($ci['amount']); ?></td>
                <td class="<?php echo $ci['charge_amount']>0?'nn':'mt'; ?>"><?php echo $ci['charge_amount']>0?'৳'.number_format($ci['charge_amount']):'—'; ?></td>
                <td class="np fw9">+৳<?php echo number_format($ci['cash_impact']); ?></td>
                <td><?php echo !empty($ci['receipt_image'])?"<img src='{$ci['receipt_image']}' loading='lazy' class='thumb' onclick='showBig(this.src)'>":"<span class='mt'>—</span>"; ?></td>
                <td><span class="ubadge"><?php echo htmlspecialchars($ci['entry_by']??'admin'); ?></span></td>
                <td class="no-print"><?php if($role==='admin'): ?><button onclick="openPwCard(<?php echo $ci['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="4" style="text-align:right">মোট ক্যাশ যোগ</td><td class="np">+৳<?php echo number_format($cit); ?></td><td colspan="3"></td></tr></tfoot>
        </table></div>
    </div>
    <?php endif; ?>

</div><?php endforeach; ?>

<div class="month-pager no-print" style="margin-top: 20px; margin-bottom: 20px;">
    <?php $activeTab = $_GET['tab'] ?? 'all'; ?>
    <a href="?m=<?php echo $prev_m; ?>&y=<?php echo $prev_y; ?>&tab=<?php echo $activeTab; ?>" class="mp-btn"><i class="fas fa-chevron-left"></i> আগের মাস</a>
    <div class="mp-title"><i class="fas fa-calendar-alt" style="color:var(--gold)"></i> <?php echo $current_bn_month; ?></div>
    <a href="?m=<?php echo $next_m; ?>&y=<?php echo $next_y; ?>&tab=<?php echo $activeTab; ?>" class="mp-btn">পরের মাস <i class="fas fa-chevron-right"></i></a>
</div>

<?php endif; ?>

</div><div class="bottom-nav no-print">
    <div class="bn-row">
        <a href="dashboard.php" class="bn-btn bn-home">
            <div class="bn-icon"><i class="fas fa-home"></i></div>
            <span class="bn-lbl">ড্যাশবোর্ড</span>
        </a>
        <a href="customers.php" class="bn-btn bn-cust">
            <div class="bn-icon"><i class="fas fa-users"></i></div>
            <span class="bn-lbl">কাস্টমার</span>
        </a>
        <a href="suppliers.php" class="bn-btn bn-sup">
            <div class="bn-icon"><i class="fas fa-truck"></i></div>
            <span class="bn-lbl">সাপ্লায়ার</span>
        </a>
        <a href="Stock/index.php" class="bn-btn bn-stk">
            <div class="bn-icon"><i class="fas fa-boxes"></i></div>
            <span class="bn-lbl">স্টক</span>
        </a>
        <a href="credit_card.php" class="bn-btn bn-card">
            <div class="bn-icon"><i class="fas fa-credit-card"></i></div>
            <span class="bn-lbl">কার্ড</span>
        </a>
        <button class="bn-btn bn-filt" onclick="toggleFilter()">
            <div class="bn-icon"><i class="fas fa-sliders-h"></i></div>
            <span class="bn-lbl">ফিল্টার</span>
        </button>
        <button class="bn-btn bn-prnt" onclick="window.print()">
            <div class="bn-icon"><i class="fas fa-print"></i></div>
            <span class="bn-lbl">প্রিন্ট</span>
        </button>
    </div>
</div>

<script>
// ── Theme ──
function toggleTheme(){
    document.body.classList.toggle('light-mode');
    const d=!document.body.classList.contains('light-mode');
    localStorage.setItem('theme',d?'dark':'light');
    document.getElementById('themeIco').className=d?'fas fa-sun':'fas fa-moon';
}
(function(){
    if(localStorage.getItem('theme')==='light'){
        document.body.classList.add('light-mode');
        const i=document.getElementById('themeIco');
        if(i)i.className='fas fa-moon';
    }
})();

// ── Image Zoom ──
function showBig(s){
    document.getElementById('bigImg').src=s;
    document.getElementById('imgModal').classList.add('show');
}

// ── Filter Toggle ──
function toggleFilter(){
    const b=document.getElementById('filterBox');
    if(!b)return;
    b.style.display=b.style.display==='block'?'none':'block';
}

function qDate(d){
    const t=new Date(),f=new Date();
    f.setDate(t.getDate()-(d-1));
    location.href='historys.php?from_date='+fmt(f)+'&to_date='+fmt(t);
}
function qMonth(){
    const t=new Date(),f=new Date(t.getFullYear(),t.getMonth(),1);
    location.href='historys.php?from_date='+fmt(f)+'&to_date='+fmt(t);
}
function qAll(){
    location.href='historys.php?from_date=2020-01-01&to_date='+fmt(new Date());
}
function fmt(d){return d.toISOString().split('T')[0]}

// ── Password Modal State ──
let pwState={type:'',table:'',id:0,field:'',val:'',mode:''};

function openPw(type,table,id,field,val){
    pwState={type,table,id,field:field||'',val:val||'',mode:'item'};
    document.getElementById('pwTitle').textContent=type==='delete'?'এন্ট্রি ডিলিট করবেন?':'এন্ট্রি এডিট করবেন?';
    document.getElementById('pwSub').innerHTML='Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent=type==='delete'?'ডিলিট করুন':'আপডেট করুন';
    _openModal();
}

function openPwDps(id){
    pwState={type:'',table:'',id,field:'',val:'',mode:'dps'};
    document.getElementById('pwTitle').textContent='DPS এন্ট্রি ডিলিট করবেন?';
    document.getElementById('pwSub').innerHTML='Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent='ডিলিট করুন';
    _openModal();
}

function openPwLoan(id){
    pwState={type:'',table:'',id,field:'',val:'',mode:'loan'};
    document.getElementById('pwTitle').textContent='লোন এন্ট্রি ডিলিট করবেন?';
    document.getElementById('pwSub').innerHTML='Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent='ডিলিট করুন';
    _openModal();
}

function openPwCard(id){
    pwState={type:'',table:'',id,field:'',val:'',mode:'card'};
    document.getElementById('pwTitle').textContent='কার্ড এন্ট্রি ডিলিট করবেন?';
    document.getElementById('pwSub').innerHTML='Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent='ডিলিট করুন';
    _openModal();
}

function _openModal(){
    document.getElementById('pwInp').value='';
    document.getElementById('pwErr').textContent='';
    document.getElementById('pwModal').classList.add('show');
    setTimeout(()=>document.getElementById('pwInp').focus(),200);
}
function closePwModal(){document.getElementById('pwModal').classList.remove('show')}

function pwConfirm(){
    const pass=document.getElementById('pwInp').value.trim();
    const btn=document.getElementById('pwOkBtn');
    const err=document.getElementById('pwErr');
    if(!pass){err.textContent='পাসওয়ার্ড লিখুন।';return}

    if(pwState.mode==='item' && pwState.type==='edit'){
        const nv=prompt('নতুন মান লিখুন (বর্তমান: '+pwState.val+'):',pwState.val);
        if(nv===null){return}
        pwState.newVal=nv;
    }

    btn.textContent='যাচাই হচ্ছে…';btn.disabled=true;

    const fd=new FormData();
    if(pwState.mode==='item'){
        fd.append('ajax_action','item_action');
        fd.append('type',pwState.type);
        fd.append('table',pwState.table);
        fd.append('id',pwState.id);
        fd.append('field',pwState.field);
        fd.append('val',pwState.newVal||pwState.val);
    } else if(pwState.mode==='dps'){
        fd.append('ajax_action','delete_dps');
        fd.append('id',pwState.id);
    } else if(pwState.mode==='loan'){
        fd.append('ajax_action','delete_loan');
        fd.append('id',pwState.id);
    } else if(pwState.mode==='card'){
        fd.append('ajax_action','delete_card_ledger');
        fd.append('id',pwState.id);
    }
    fd.append('pass',pass);

    fetch('historys.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(res=>{
            btn.textContent='নিশ্চিত করুন';btn.disabled=false;
            if(res.status==='success'){
                closePwModal();
                showToast(res.message,true);
                setTimeout(()=>location.reload(),1200);
            } else {
                err.textContent=res.message;
            }
        })
        .catch(()=>{
            btn.textContent='নিশ্চিত করুন';btn.disabled=false;
            err.textContent='সার্ভার সমস্যা হয়েছে।';
        });
}

document.addEventListener('keydown',e=>{
    if(e.key==='Enter' && document.getElementById('pwModal').classList.contains('show')) pwConfirm();
    if(e.key==='Escape' && document.getElementById('pwModal').classList.contains('show')) closePwModal();
    if(e.key==='Escape') document.getElementById('imgModal').classList.remove('show');
});

function showToast(msg,ok){
    const t=document.createElement('div');
    t.className='toast-el';
    t.textContent=msg;
    t.style.background=ok?'#0369a1':'#dc2626';
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),2500);
}

// ============================================================
// JS Tab Filter Logic
// ============================================================
function filterLedger(category, btnElement) {
    document.querySelectorAll('.ledger-tab-btn').forEach(btn => btn.classList.remove('active'));
    if(btnElement) {
        btnElement.classList.add('active');
        btnElement.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
    
    const dateCards = document.querySelectorAll('.date-card');
    dateCards.forEach(card => {
        let hasVisibleGroup = false;
        const groups = card.querySelectorAll('.ledger-group');
        
        groups.forEach(group => {
            if (category === 'all' || group.classList.contains('cat-' + category)) {
                group.style.display = 'block';
                hasVisibleGroup = true;
            } else {
                group.style.display = 'none';
            }
        });
        card.style.display = hasVisibleGroup ? 'block' : 'none';
    });

    // Update Pagination Links
    const mpBtns = document.querySelectorAll('.mp-btn');
    mpBtns.forEach(btn => {
        let url = new URL(btn.href); 
        url.searchParams.set('tab', category); 
        btn.href = url.toString();
    });

    let cUrl = new URL(window.location); cUrl.searchParams.set('tab', category);
    window.history.replaceState({}, '', cUrl);
}

document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'all';
    const tabBtn = document.querySelector(`.ledger-tab-btn[onclick*="'${activeTab}'"]`);
    if(tabBtn) filterLedger(activeTab, tabBtn);
});

// ============================================================
// Global Ajax Live Search JS
// ============================================================
let searchTimeout;
const gsInput = document.getElementById('globalSearchInp');
const gsResults = document.getElementById('gsResults');

function clearSearch() {
    gsInput.value = '';
    gsResults.classList.remove('show');
}

gsInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length === 0) {
        gsResults.classList.remove('show');
        return;
    }
    searchTimeout = setTimeout(() => {
        fetchSearch(q);
    }, 300);
});

function fetchSearch(query) {
    const fd = new FormData();
    fd.append('ajax_action', 'global_search');
    fd.append('query', query);

    fetch('historys.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        gsResults.innerHTML = '';
        if (data.length === 0) {
            gsResults.innerHTML = '<div style="padding:15px;text-align:center;color:var(--tm);font-size:12px;"><i class="fas fa-box-open" style="font-size:20px;display:block;margin-bottom:5px;opacity:0.5;"></i>কোনো রেকর্ড পাওয়া যায়নি!</div>';
        } else {
            data.forEach(item => {
                const html = `
                    <div class="gs-item">
                        <div>
                            <div class="gs-title">${item.txt}</div>
                            <div class="gs-sub"><i class="fas fa-calendar-day"></i> ${new Date(item.dt).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'})} &nbsp;•&nbsp; ${item.mod_type}</div>
                        </div>
                        <div class="gs-amt">৳${parseFloat(item.amt).toLocaleString('en-IN')}</div>
                    </div>
                `;
                gsResults.insertAdjacentHTML('beforeend', html);
            });
        }
        gsResults.classList.add('show');
    }).catch(err => console.error("Search Error: ", err));
}
</script>

</body>
</html>