<?php
/**
 * ========================================================
 * historys_view.php — মূল পেজ HTML (View লেয়ার)
 * Controller থেকে পাস করা ভেরিয়েবল ব্যবহার করে।
 *
 * উপলব্ধ ভেরিয়েবল (controller inject করে):
 *   $role, $from_date, $to_date, $all_dates, $total_dates
 *   $summary (array), $ob, $closing
 *   $loan_outstanding, $dps_total
 *   $card_type_labels (array)
 *   $success_msg, $error_msg
 * ========================================================
 */

// ─── Image Base URL (Historys/ ফোল্ডার থেকে root পর্যন্ত) ───
// DB তে পাথ সেভ থাকে: "uploads/expense/2026-07/img.jpg"
// Historys/ থেকে root যেতে হয় একধাপ উপরে: "../"
// যদি absolute URL দরকার হয়, নিচের লাইনটি uncomment করুন:
// define('IMG_BASE', 'https://yourdomain.com/');
if (!defined('IMG_BASE')) {
    define('IMG_BASE', '../');
}

// ─── Helper: ছবির src তৈরি করে ───────────────────────────
function imgSrc(string $raw): string {
    if (empty($raw)) return '';
    // যদি ইতোমধ্যে http/https দিয়ে শুরু হয়
    if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) return $raw;
    // যদি / দিয়ে শুরু হয় (server-root absolute)
    if (str_starts_with($raw, '/')) return $raw;
    // relative path → IMG_BASE যোগ করো
    return IMG_BASE . ltrim($raw, '/');
}

// শর্টকাট
$gt_sale_cash       = $summary['sale_cash'];
$gt_sale_due        = $summary['sale_due'];
$gt_coll            = $summary['coll'];
$gt_exp             = $summary['exp'];
$gt_cust_rcv        = $summary['cust_rcv'];
$gt_sup_pay         = $summary['sup_pay'];
$gt_staff           = $summary['staff'];
$gt_loan_in         = $summary['loan_in'];
$gt_loan_out        = $summary['loan_out'];
$gt_dps_dep         = $summary['dps_dep'];
$gt_dps_wth         = $summary['dps_wth'];
$gt_card_cash_out   = $summary['card_cash_out'];
$gt_card_advance    = $summary['card_advance'];
$gt_card_outstanding= $summary['card_outstanding'];
$card_active_count  = $summary['card_active_count'];
$total_in           = $summary['total_in'];
$total_out          = $summary['total_out'];
$net_cash           = $summary['net_cash'];
?>

<?php if (isset($success_msg)): ?>
<div class="alert alert-ok no-print">
    <span><i class="fas fa-check-circle" style="margin-right:6px"></i><?php echo $success_msg; ?></span>
    <span class="alert-x" onclick="this.parentElement.remove()">&times;</span>
</div>
<?php endif; ?>
<?php if (isset($error_msg)): ?>
<div class="alert alert-err no-print">
    <span><i class="fas fa-exclamation-triangle" style="margin-right:6px"></i><?php echo $error_msg; ?></span>
    <span class="alert-x" onclick="this.parentElement.remove()">&times;</span>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="ph no-print">
    <div class="ph-title"><div class="ph-dot"></div>লেজার রিপোর্ট</div>
    <div class="ph-badge"><?php echo date('d M', strtotime($from_date)); ?> — <?php echo date('d M Y', strtotime($to_date)); ?></div>
</div>

<!-- Top 4 Summary Boxes -->
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
        <div class="top-sum-val" style="color:<?php echo $net_cash >= 0 ? 'var(--amber)' : 'var(--ruby)'; ?>">
            ৳<?php echo number_format($net_cash); ?>
        </div>
    </div>
    <div class="top-sum-box tsb-4">
        <div class="top-sum-ico"><i class="fas fa-wallet"></i></div>
        <div class="top-sum-lbl">Closing Balance</div>
        <div class="top-sum-val" style="color:<?php echo $closing >= 0 ? 'var(--green)' : 'var(--ruby)'; ?>">
            ৳<?php echo number_format($closing); ?>
        </div>
    </div>
</div>

<!-- Filter Box -->
<div class="filter-box no-print" id="filterBox">
<?php if ($role === 'admin'): ?>
    <form method="GET" action="index.php">
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
    <div style="text-align:center;padding:10px;font-size:11px;color:var(--tm);font-weight:700">
        Manager / User — শুধু আজকের ডাটা দেখা যাবে।
    </div>
<?php endif; ?>
</div>

<!-- Loan + DPS + Card Summary -->
<div class="multi-row no-print">
    <!-- লোন সামারি -->
    <div class="ds-box ds-loan">
        <div class="ds-ttl"><i class="fas fa-university"></i> লোন সামারি</div>
        <div class="ds-row">
            <span class="ds-lbl">পিরিয়ডে নেওয়া (+)</span>
            <span class="ds-val np">৳ <?php echo number_format($gt_loan_in); ?></span>
        </div>
        <div class="ds-row">
            <span class="ds-lbl">পিরিয়ডে পরিশোধ (−)</span>
            <span class="ds-val nn">৳ <?php echo number_format($gt_loan_out); ?></span>
        </div>
        <div class="ds-row">
            <span class="ds-lbl">মোট বকেয়া</span>
            <span class="ds-val na">৳ <?php echo number_format($loan_outstanding); ?></span>
        </div>
    </div>

    <!-- DPS সামারি -->
    <div class="ds-box ds-dps">
        <div class="ds-ttl"><i class="fas fa-piggy-bank"></i> DPS সামারি</div>
        <div class="ds-row">
            <span class="ds-lbl">পিরিয়ডে জমা (−)</span>
            <span class="ds-val nn">৳ <?php echo number_format($gt_dps_dep); ?></span>
        </div>
        <div class="ds-row">
            <span class="ds-lbl">উত্তোলন / ক্লোজ (+)</span>
            <span class="ds-val np">৳ <?php echo number_format($gt_dps_wth); ?></span>
        </div>
        <div class="ds-row">
            <span class="ds-lbl">তহবিলে মোট জমা</span>
            <span class="ds-val nb">৳ <?php echo number_format($dps_total); ?></span>
        </div>
    </div>

    <!-- কার্ড সামারি -->
    <?php if ($card_active_count > 0 || $gt_card_cash_out > 0 || $gt_card_advance > 0): ?>
    <div class="ds-box ds-card">
        <div class="ds-ttl"><i class="fas fa-credit-card"></i> কার্ড সামারি</div>
        <div class="ds-row">
            <span class="ds-lbl">পিরিয়ডে পেমেন্ট (−)</span>
            <span class="ds-val nn">৳ <?php echo number_format($gt_card_cash_out); ?></span>
        </div>
        <div class="ds-row">
            <span class="ds-lbl">কার্ড থেকে ক্যাশ (+)</span>
            <span class="ds-val np">৳ <?php echo number_format($gt_card_advance); ?></span>
        </div>
        <div class="ds-row">
            <span class="ds-lbl">মোট বকেয়া</span>
            <span class="ds-val" style="color:var(--sky)">৳ <?php echo number_format($gt_card_outstanding); ?></span>
        </div>
    </div>
    <?php else: ?>
    <div class="ds-box ds-card" style="opacity:.5">
        <div class="ds-ttl"><i class="fas fa-credit-card"></i> কার্ড সামারি</div>
        <div style="text-align:center;padding:14px 0;font-size:10px;color:var(--tm);font-weight:700">
            <i class="fas fa-credit-card" style="font-size:20px;display:block;margin-bottom:5px;opacity:.4"></i>
            কোনো কার্ড নেই<br>
            <a href="../../credit_card.php" style="color:var(--cyan);font-size:9px;text-decoration:none">+ কার্ড যোগ করুন</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Period Summary Panel -->
<div class="summary-panel no-print">
    <div class="summary-title"><i class="fas fa-chart-pie" style="color:var(--cyan)"></i> Period Summary</div>
    <div class="stat-grid">
        <div class="stat-item"><div class="stat-label">Cash Sales</div><div class="stat-value np"><?php echo number_format($gt_sale_cash); ?></div></div>
        <div class="stat-item"><div class="stat-label">Due Sales</div><div class="stat-value nn"><?php echo number_format($gt_sale_due); ?></div></div>
        <div class="stat-item"><div class="stat-label">Collection</div><div class="stat-value np"><?php echo number_format($gt_coll); ?></div></div>
        <div class="stat-item"><div class="stat-label">Expense</div><div class="stat-value nn"><?php echo number_format($gt_exp); ?></div></div>
        <div class="stat-item"><div class="stat-label">Cust. Rcv</div><div class="stat-value np"><?php echo number_format($gt_cust_rcv); ?></div></div>
        <div class="stat-item"><div class="stat-label">Sup. Paid</div><div class="stat-value nn"><?php echo number_format($gt_sup_pay); ?></div></div>
        <div class="stat-item"><div class="stat-label">Staff Exp</div><div class="stat-value nn"><?php echo number_format($gt_staff); ?></div></div>
        <div class="stat-item"><div class="stat-label">Days</div><div class="stat-value nq"><?php echo $total_dates; ?></div></div>
    </div>
    <div class="balance-grid">
        <div class="balance-item">
            <div class="balance-label">Opening Balance</div>
            <div class="balance-value" style="color:<?php echo $ob >= 0 ? 'var(--nq)' : 'var(--ruby)'; ?>">৳<?php echo number_format($ob); ?></div>
        </div>
        <div class="balance-item">
            <div class="balance-label">Period Net</div>
            <div class="balance-value" style="color:<?php echo $net_cash >= 0 ? 'var(--amber)' : 'var(--ruby)'; ?>">৳<?php echo number_format($net_cash); ?></div>
        </div>
        <div class="balance-item">
            <div class="balance-label">Closing Balance</div>
            <div class="balance-value" style="color:<?php echo $closing >= 0 ? 'var(--green)' : 'var(--ruby)'; ?>">৳<?php echo number_format($closing); ?></div>
        </div>
    </div>
</div>

<!-- ============================================================
     Daily Data Loop
     ============================================================ -->
<?php if (empty($all_dates)): ?>
<div class="empty">
    <span class="empty-ico"><i class="fas fa-inbox"></i></span>
    <div class="empty-txt">এই সময়ে কোনো ডাটা পাওয়া যায়নি।</div>
</div>
<?php else: ?>

<!-- Date Range Info -->
<div class="dri no-print">
    <i class="fas fa-calendar-alt"></i>
    <span><?php echo date('d M Y', strtotime($from_date)); ?> — <?php echo date('d M Y', strtotime($to_date)); ?></span>
    <span class="dri-cnt"><?php echo $total_dates; ?> দিনের ডাটা</span>
</div>

<?php foreach ($all_dates as $cdate):
    // প্রতিটি তারিখের ডেটা Model থেকে আনা (controller এর $model ব্যবহার না করে সরাসরি query)
    // Note: view এ $conn সরাসরি ব্যবহার MVC লঙ্ঘন, তাই controller থেকে day data pass করা উচিত।
    // এখানে backward-compatibility এর জন্য controller এ একটি helper ব্যবহার করা হচ্ছে।
    // Controller renderPage() এ $model->getDayData($cdate) call করে $dayData pass করবে।
    // আপাতত controller থেকে $dayDataMap[$cdate] হিসেবে পাস করা হচ্ছে।
    $day    = $dayDataMap[$cdate] ?? [];
    $sales  = $day['sales']    ?? [];
    $colls  = $day['colls']    ?? [];
    $exps   = $day['exps']     ?? [];
    $custT  = $day['custT']    ?? [];
    $supT   = $day['supT']     ?? [];
    $ostkT  = $day['ostkT']    ?? [];
    $nstkT  = $day['nstkT']    ?? [];
    $staffT = $day['staffT']   ?? [];
    $dpsT   = $day['dpsT']     ?? [];
    $loanT  = $day['loanT']    ?? [];
    $cardOutT = $day['cardOutT'] ?? [];
    $cardInT  = $day['cardInT']  ?? [];

    // দৈনিক ক্যাশ IN / OUT
    $day_in = $day_out = 0;
    foreach ($sales   as $s)  { $day_in  += floatval($s['paid_amount']); }
    foreach ($colls   as $c)  { $day_in  += floatval($c['total_deposit']); }
    foreach ($custT   as $ct) { $day_in  += floatval($ct['received_amount']); }
    foreach ($exps    as $e)  { $day_out += floatval($e['amount']); }
    foreach ($supT    as $st) { $day_out += floatval($st['payment_given']); }
    foreach ($staffT  as $sf) { $day_out += floatval($sf['amount']); }
    foreach ($cardOutT as $co){ $day_out += abs(floatval($co['cash_impact'])); }
    foreach ($cardInT  as $ci){ $day_in  += floatval($ci['cash_impact']); }
    foreach ($dpsT as $dl) {
        $dep = floatval($dl['deposit_amount']); $wth = floatval($dl['withdraw_amount']);
        if (!stristr($dl['description'], 'মুনাফা') && !stristr($dl['description'], 'Opening')) $day_out += $dep;
        $day_in += $wth;
    }
    foreach ($loanT as $ll) {
        if (!stristr($ll['description'], 'মুনাফা')) $day_in += floatval($ll['debit_amount']);
        $day_out += floatval($ll['credit_amount']);
    }
?>
<div class="date-card">

    <!-- Date Header -->
    <div class="date-hdr">
        <div class="date-hdr-l">
            <div class="date-dot"></div>
            <div class="date-day"><?php echo date('d M Y', strtotime($cdate)); ?></div>
        </div>
        <div class="date-wday"><?php echo date('l', strtotime($cdate)); ?></div>
    </div>

    <!-- দৈনিক ক্যাশ IN/OUT Strip -->
    <div class="date-strip no-print">
        <div class="ds-chip dsc-in"><i class="fas fa-arrow-up"></i> IN: ৳<?php echo number_format($day_in); ?></div>
        <div class="ds-chip dsc-out"><i class="fas fa-arrow-down"></i> OUT: ৳<?php echo number_format($day_out); ?></div>
        <?php $day_net = $day_in - $day_out; ?>
        <div class="ds-chip" style="
            background:<?php echo $day_net >= 0 ? 'rgba(0,194,255,.1)' : 'rgba(255,61,110,.1)'; ?>;
            border-color:<?php echo $day_net >= 0 ? 'rgba(0,194,255,.3)' : 'rgba(255,61,110,.3)'; ?>;
            color:<?php echo $day_net >= 0 ? 'var(--cyan)' : 'var(--ruby)'; ?>;">
            <i class="fas fa-balance-scale"></i> Net: ৳<?php echo number_format($day_net); ?>
        </div>
    </div>

    <!-- ── Sales Entry ─────────────────────────────────── -->
    <?php if (!empty($sales)): $tb=0;$tp=0;$td=0;$tq=0; ?>
    <div class="sec-hdr s-sale">
        <div class="sec-ico"><i class="fas fa-shopping-cart"></i></div>
        <div class="sec-name">Sales Entry</div>
        <span class="sec-badge"><?php echo count($sales); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th>MEMO</th><th>CUSTOMER</th><th>QTY</th><th>BILL</th><th>PAID</th><th>DUE</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($sales as $s): $tb+=$s['total_bill'];$tp+=$s['paid_amount'];$td+=$s['due_amount'];$tq+=$s['quantity']; ?>
        <tr class="<?php echo $s['due_amount'] > 0 ? 'row-due' : ''; ?>">
            <td class="fw9 nw" style="font-family:var(--mono)"><?php echo $s['memo_no']; ?></td>
            <td class="lft"><?php echo htmlspecialchars($s['customer_name']); ?></td>
            <td class="nq fw9"><?php echo $s['quantity']; ?></td>
            <td class="nw" style="font-family:var(--mono)"><?php echo number_format($s['total_bill']); ?></td>
            <td class="np fw9"><?php echo number_format($s['paid_amount']); ?></td>
            <td class="nn fw9"><?php echo number_format($s['due_amount']); ?></td>
            <td><?php echo !empty($s['photo']) ? "<img src='".imgSrc($s['photo'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>"; ?></td>
            <td><span class="ubadge"><?php echo $s['entry_by'] ?? 'N/A'; ?></span></td>
            <td class="no-print">
                <?php if ($role === 'admin'): ?>
                <button onclick="openPw('edit','sales_entries',<?php echo $s['id']; ?>,'total_bill',<?php echo $s['total_bill']; ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button>
                <button onclick="openPw('delete','sales_entries',<?php echo $s['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="2" style="text-align:right">Sub-Total</td>
            <td class="nq"><?php echo $tq; ?></td>
            <td class="nw" style="font-family:var(--mono)"><?php echo number_format($tb); ?></td>
            <td class="np"><?php echo number_format($tp); ?></td>
            <td class="nn"><?php echo number_format($td); ?></td>
            <td colspan="3"></td>
        </tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── Collection ──────────────────────────────────── -->
    <?php if (!empty($colls)): $tc = 0; ?>
    <div class="sec-hdr s-col">
        <div class="sec-ico"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="sec-name">Collection</div>
        <span class="sec-badge"><?php echo count($colls); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th>CUSTOMER</th><th>CASH</th><th>BKASH</th><th>TOTAL</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($colls as $c): $tc += $c['total_deposit']; ?>
        <tr>
            <td class="lft"><?php echo htmlspecialchars($c['payer_name']); ?></td>
            <td class="nw" style="font-family:var(--mono)"><?php echo number_format($c['cash_amount']); ?></td>
            <td class="nw" style="font-family:var(--mono)"><?php echo number_format($c['bkash_amount']); ?></td>
            <td class="np fw9"><?php echo number_format($c['total_deposit']); ?></td>
            <td><span class="ubadge"><?php echo $c['entry_by'] ?? 'N/A'; ?></span></td>
            <td class="no-print">
                <?php if ($role === 'admin'): ?>
                <button onclick="openPw('edit','collection_entries',<?php echo $c['id']; ?>,'total_deposit',<?php echo $c['total_deposit']; ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button>
                <button onclick="openPw('delete','collection_entries',<?php echo $c['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="3" style="text-align:right">Sub-Total</td><td class="np"><?php echo number_format($tc); ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── Expense Ledger ──────────────────────────────── -->
    <?php if (!empty($exps)): $te = 0; ?>
    <div class="sec-hdr s-exp">
        <div class="sec-ico"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="sec-name">Expense Ledger</div>
        <span class="sec-badge"><?php echo count($exps); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th>বিবরণ</th><th>পরিমাণ</th><th>ভাউচার</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($exps as $e): $te += $e['amount']; ?>
        <tr>
            <td class="lft"><?php echo htmlspecialchars($e['description']); ?></td>
            <td class="nn fw9">৳ <?php echo number_format($e['amount']); ?></td>
            <td><?php echo !empty($e['photo']) ? "<img src='".imgSrc($e['photo'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>"; ?></td>
            <td><span class="ubadge"><?php echo $e['entry_by'] ?? 'N/A'; ?></span></td>
            <td class="no-print">
                <?php if ($role === 'admin'): ?>
                <button onclick="openPw('edit','expense_entries',<?php echo $e['id']; ?>,'amount',<?php echo $e['amount']; ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button>
                <button onclick="openPw('delete','expense_entries',<?php echo $e['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td style="text-align:right">Sub-Total</td><td class="nn">৳ <?php echo number_format($te); ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── Staff Expense ───────────────────────────────── -->
    <?php if (!empty($staffT)): $tsf = 0; ?>
    <div class="sec-hdr s-staff">
        <div class="sec-ico"><i class="fas fa-user-tie"></i></div>
        <div class="sec-name">Staff Expense</div>
        <span class="sec-badge"><?php echo count($staffT); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">স্টাফ</th><th>নোট</th><th>সময়</th><th>পরিমাণ</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($staffT as $sf): $tsf += $sf['amount']; ?>
        <tr>
            <td class="lft"><div class="np fw9"><?php echo htmlspecialchars($sf['name']); ?></div></td>
            <td class="mt"><?php echo htmlspecialchars($sf['note'] ?? $sf['expense_type'] ?? '—'); ?></td>
            <td><span class="ubadge"><?php echo isset($sf['expense_time']) ? date('h:i A', strtotime($sf['expense_time'])) : '—'; ?></span></td>
            <td class="nn fw9">৳ <?php echo number_format($sf['amount']); ?></td>
            <td><span class="ubadge"><?php echo htmlspecialchars($sf['entry_by'] ?? 'N/A'); ?></span></td>
            <td class="no-print">
                <?php if ($role === 'admin'): ?>
                <button onclick="openPw('delete','staff_expenses',<?php echo $sf['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="3" style="text-align:right">Sub-Total</td><td class="nn">৳ <?php echo number_format($tsf); ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── Customer Ledger ─────────────────────────────── -->
    <?php if (!empty($custT)): $tcb = 0; $tcr = 0; ?>
    <div class="sec-hdr s-cust">
        <div class="sec-ico"><i class="fas fa-users"></i></div>
        <div class="sec-name">Customer Ledger</div>
        <span class="sec-badge"><?php echo count($custT); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">গ্রাহক</th><th>বিবরণ</th><th>বিল</th><th>প্রাপ্ত</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($custT as $ct): $tcb += $ct['bill_amount']; $tcr += $ct['received_amount']; ?>
        <tr class="<?php echo $ct['bill_amount'] > $ct['received_amount'] ? 'row-due' : ''; ?>">
            <td class="lft">
                <div class="nv fw9" style="font-size:11px"><?php echo htmlspecialchars($ct['shop_name']); ?></div>
                <div class="mt" style="font-size:9px;margin-top:1px"><?php echo htmlspecialchars($ct['customer_name']); ?></div>
            </td>
            <td class="mt"><?php echo htmlspecialchars($ct['description'] ?? 'N/A'); ?></td>
            <td class="nn fw9"><?php echo $ct['bill_amount'] > 0 ? number_format($ct['bill_amount']) : '—'; ?></td>
            <td class="np fw9"><?php echo $ct['received_amount'] > 0 ? number_format($ct['received_amount']) : '—'; ?></td>
            <td><?php echo !empty($ct['image_path']) ? "<img src='".imgSrc($ct['image_path'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>"; ?></td>
            <td><span class="ubadge"><?php echo $ct['entry_by'] ?? 'N/A'; ?></span></td>
            <td class="no-print">
                <a href="https://wa.me/88<?php echo $ct['phone']; ?>?text=<?php echo urlencode("তারিখ: " . date('d M Y', strtotime($cdate)) . "\nবিল: ৳" . $ct['bill_amount'] . "\nপ্রাপ্ত: ৳" . $ct['received_amount']); ?>" target="_blank" class="abt a-wa"><i class="fab fa-whatsapp"></i></a>
                <?php if ($role === 'admin'): ?>
                <button onclick="openPw('edit','customer_transactions',<?php echo $ct['id']; ?>,'bill_amount',<?php echo $ct['bill_amount']; ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button>
                <button onclick="openPw('delete','customer_transactions',<?php echo $ct['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nn"><?php echo number_format($tcb); ?></td><td class="np"><?php echo number_format($tcr); ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── Supplier Ledger ─────────────────────────────── -->
    <?php if (!empty($supT)): $tsb = 0; $tsp = 0; $tsq = 0; ?>
    <div class="sec-hdr s-sup">
        <div class="sec-ico"><i class="fas fa-truck"></i></div>
        <div class="sec-name">Supplier Ledger</div>
        <span class="sec-badge"><?php echo count($supT); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">সাপ্লায়ার</th><th>মেমো</th><th>বিল</th><th>QTY</th><th>পেমেন্ট</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($supT as $st): $tsb += $st['bill_received']; $tsp += $st['payment_given']; $q = $st['quantity'] ?? ($st['pcs'] ?? 0); $tsq += $q; ?>
        <tr class="<?php echo $st['bill_received'] > $st['payment_given'] ? 'row-pend' : ''; ?>">
            <td class="lft">
                <div class="na fw9"><?php echo htmlspecialchars($st['shop_name']); ?></div>
                <div class="mt" style="font-size:9px;margin-top:1px"><?php echo htmlspecialchars($st['name']); ?></div>
            </td>
            <td class="nw" style="font-family:var(--mono);font-size:10px"><?php echo htmlspecialchars($st['memo_no']); ?></td>
            <td class="nn fw9"><?php echo $st['bill_received'] > 0 ? number_format($st['bill_received']) : '—'; ?></td>
            <td class="nq"><?php echo $q > 0 ? $q : '—'; ?></td>
            <td class="np fw9"><?php echo $st['payment_given'] > 0 ? number_format($st['payment_given']) : '—'; ?></td>
            <td><?php echo !empty($st['photo']) ? "<img src='".imgSrc($st['photo'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>"; ?></td>
            <td><span class="ubadge"><?php echo $st['entry_by'] ?? 'N/A'; ?></span></td>
            <td class="no-print">
                <?php if ($role === 'admin'): ?>
                <button onclick="openPw('edit','supplier_transactions',<?php echo $st['id']; ?>,'bill_received',<?php echo $st['bill_received']; ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button>
                <button onclick="openPw('delete','supplier_transactions',<?php echo $st['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nn"><?php echo number_format($tsb); ?></td><td class="nq"><?php echo $tsq; ?></td><td class="np"><?php echo number_format($tsp); ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── Stock History (পুরনো টেবিল) ──────────────────── -->
    <?php if (!empty($ostkT)): $osi = 0; $oso = 0; $osb = 0; ?>
    <div class="sec-hdr s-stk">
        <div class="sec-ico"><i class="fas fa-boxes"></i></div>
        <div class="sec-name">Stock History</div>
        <span class="sec-badge"><?php echo count($ostkT); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">বিবরণ</th><th>IN</th><th>OUT</th><th>বিল</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($ostkT as $sk): $inq = $sk['stock_in'] ?? 0; $outq = $sk['stock_out'] ?? 0; $osi += $inq; $oso += $outq; $osb += $sk['total_bill']; ?>
        <tr>
            <td class="lft fw9"><?php echo htmlspecialchars($sk['description']); ?></td>
            <td class="np fw9"><?php echo $inq > 0 ? $inq : '—'; ?></td>
            <td class="nn fw9"><?php echo $outq > 0 ? $outq : '—'; ?></td>
            <td class="nb"><?php echo $sk['total_bill'] > 0 ? number_format($sk['total_bill']) : '—'; ?></td>
            <td><?php echo !empty($sk['image']) ? "<img src='".imgSrc($sk['image'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>"; ?></td>
            <td><span class="ubadge"><?php echo $sk['entry_by'] ?? 'N/A'; ?></span></td>
            <td class="no-print">
                <?php if ($role === 'admin'): ?>
                <button onclick="openPw('delete','stock_entries',<?php echo $sk['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td style="text-align:right">Sub-Total</td><td class="np"><?php echo $osi; ?></td><td class="nn"><?php echo $oso; ?></td><td class="nb"><?php echo number_format($osb); ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── Stock Added (নতুন টেবিল) ─────────────────────── -->
    <?php if (!empty($nstkT)): $nsi = 0; $nso = 0; $nsb = 0; ?>
    <div class="sec-hdr s-nstk">
        <div class="sec-ico"><i class="fas fa-cart-plus"></i></div>
        <div class="sec-name">Stock Added</div>
        <span class="sec-badge"><?php echo count($nstkT); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">বিবরণ</th><th>IN</th><th>OUT</th><th>বিল</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($nstkT as $sk): $inq = $sk['in_qty'] ?? 0; $outq = $sk['out_qty'] ?? 0; $nsi += $inq; $nso += $outq; $nsb += $sk['total_bill']; ?>
        <tr>
            <td class="lft fw9"><?php echo htmlspecialchars($sk['description']); ?></td>
            <td class="np fw9"><?php echo $inq > 0 ? $inq : '—'; ?></td>
            <td class="nn fw9"><?php echo $outq > 0 ? $outq : '—'; ?></td>
            <td class="nb"><?php echo $sk['total_bill'] > 0 ? number_format($sk['total_bill']) : '—'; ?></td>
            <td><?php echo !empty($sk['image']) ? "<img src='".imgSrc($sk['image'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>"; ?></td>
            <td><span class="ubadge"><?php echo $sk['entry_by'] ?? 'N/A'; ?></span></td>
            <td class="no-print">
                <?php if ($role === 'admin'): ?>
                <button onclick="openPw('delete','stocks',<?php echo $sk['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td style="text-align:right">Sub-Total</td><td class="np"><?php echo $nsi; ?></td><td class="nn"><?php echo $nso; ?></td><td class="nb"><?php echo number_format($nsb); ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── DPS / FDR লেজার ────────────────────────────── -->
    <?php if (!empty($dpsT)): $ddt = 0; $dwt = 0; ?>
    <div class="sec-hdr s-dps">
        <div class="sec-ico"><i class="fas fa-piggy-bank"></i></div>
        <div class="sec-name">DPS / FDR লেজার</div>
        <span class="sec-badge"><?php echo count($dpsT); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">A/C ও গ্রাহক</th><th>বিবরণ</th><th>জমা (+)</th><th>উত্তোলন (−)</th><th>ব্যালেন্স</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($dpsT as $dl):
            $dd  = floatval($dl['deposit_amount']); $dw = floatval($dl['withdraw_amount']);
            $ddt += $dd; $dwt += $dw;
            $isO = stripos($dl['description'], 'Opening') !== false;
            $accNo = !empty($dl['account_number']) ? $dl['account_number'] : ($dl['account_type'] . '-' . (1000 + intval($dl['acc_id'])));
        ?>
        <tr>
            <td class="lft">
                <div class="np fw9" style="font-size:11px"><?php echo htmlspecialchars($accNo); ?></div>
                <div class="mt" style="font-size:9px;margin-top:1px"><?php echo htmlspecialchars($dl['client_name']); ?></div>
            </td>
            <td class="mt" style="font-size:10px"><?php echo htmlspecialchars($dl['description']); ?></td>
            <td class="np fw9"><?php echo $dd > 0 ? '৳' . number_format($dd) : '—'; ?></td>
            <td class="nn fw9"><?php echo $dw > 0 ? '৳' . number_format($dw) : '—'; ?></td>
            <td class="nb fw9">৳<?php echo number_format(floatval($dl['current_balance'])); ?></td>
            <td class="no-print">
                <?php if ($role === 'admin' && !$isO): ?>
                <button onclick="openPwDps(<?php echo $dl['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="np">৳<?php echo number_format($ddt); ?></td><td class="nn">৳<?php echo number_format($dwt); ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── লোন লেজার ──────────────────────────────────── -->
    <?php if (!empty($loanT)): $ldt = 0; $lct = 0; ?>
    <div class="sec-hdr s-loan">
        <div class="sec-ico"><i class="fas fa-university"></i></div>
        <div class="sec-name">লোন লেজার (NGO / Bank)</div>
        <span class="sec-badge"><?php echo count($loanT); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">গ্রাহক</th><th>বিবরণ</th><th>ডেবিট (+)</th><th>পরিশোধ (−)</th><th>ব্যালেন্স</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($loanT as $ll): $ld = floatval($ll['debit_amount']); $lc = floatval($ll['credit_amount']); $ldt += $ld; $lct += $lc; ?>
        <tr>
            <td class="lft">
                <div class="nn fw9" style="font-size:11px"><?php echo htmlspecialchars($ll['borrower_name']); ?></div>
                <div class="mt" style="font-size:9px;margin-top:1px"><?php echo strtoupper($ll['loan_category']); ?></div>
            </td>
            <td class="mt" style="font-size:10px"><?php echo htmlspecialchars($ll['description']); ?></td>
            <td class="nn fw9"><?php echo $ld > 0 ? '৳' . number_format($ld) : '—'; ?></td>
            <td class="np fw9"><?php echo $lc > 0 ? '−৳' . number_format($lc) : '—'; ?></td>
            <td class="nb fw9">৳<?php echo number_format(floatval($ll['balance'])); ?></td>
            <td class="no-print">
                <?php if ($role === 'admin'): ?>
                <button onclick="openPwLoan(<?php echo $ll['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nn">৳<?php echo number_format($ldt); ?></td><td class="np">−৳<?php echo number_format($lct); ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── ক্রেডিট কার্ড পেমেন্ট (−) ──────────────────── -->
    <?php if (!empty($cardOutT)): $cot = 0; ?>
    <div class="sec-hdr s-card-out">
        <div class="sec-ico"><i class="fas fa-credit-card"></i></div>
        <div class="sec-name">ক্রেডিট কার্ড পেমেন্ট (−)</div>
        <span class="sec-badge"><?php echo count($cardOutT); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">কার্ড</th><th>টাইপ</th><th>অ্যামাউন্ট</th><th>চার্জ</th><th>ক্যাশ কাটা</th><th>রিসিট</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($cardOutT as $co):
            $cot += abs(floatval($co['cash_impact']));
            $tlbl = $card_type_labels[$co['txn_type']] ?? $co['txn_type'];
            $tcls = ['bill_pay'=>'ctb-bill','min_pay'=>'ctb-min','full_pay'=>'ctb-full','charge_pay'=>'ctb-chg','cash_advance'=>'ctb-adv','purchase'=>'ctb-pur'][$co['txn_type']] ?? 'ctb-bill';
        ?>
        <tr>
            <td class="lft">
                <div class="fw9" style="font-size:11px;color:var(--ruby)"><?php echo htmlspecialchars($co['card_name']); ?></div>
                <div class="mt" style="font-size:9px">**** <?php echo $co['card_last4']; ?></div>
            </td>
            <td><span class="card-type-badge <?php echo $tcls; ?>"><?php echo $tlbl; ?></span></td>
            <td class="nw fw9">৳<?php echo number_format($co['amount']); ?></td>
            <td class="<?php echo $co['charge_amount'] > 0 ? 'nn' : 'mt'; ?>"><?php echo $co['charge_amount'] > 0 ? '৳' . number_format($co['charge_amount']) : '—'; ?></td>
            <td class="nn fw9">−৳<?php echo number_format(abs($co['cash_impact'])); ?></td>
            <td><?php echo !empty($co['receipt_image']) ? "<img src='".imgSrc($co['receipt_image'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>"; ?></td>
            <td><span class="ubadge"><?php echo htmlspecialchars($co['entry_by'] ?? 'admin'); ?></span></td>
            <td class="no-print">
                <?php if ($role === 'admin'): ?>
                <button onclick="openPwCard(<?php echo $co['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4" style="text-align:right">মোট ক্যাশ কাটা</td><td class="nn">−৳<?php echo number_format($cot); ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

    <!-- ── ক্রেডিট কার্ড ক্যাশ (+) ─────────────────────── -->
    <?php if (!empty($cardInT)): $cit = 0; ?>
    <div class="sec-hdr s-card-in">
        <div class="sec-ico"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="sec-name">ক্রেডিট কার্ড ক্যাশ (+)</div>
        <span class="sec-badge"><?php echo count($cardInT); ?> entries</span>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">কার্ড</th><th>টাইপ</th><th>অ্যামাউন্ট</th><th>চার্জ</th><th>ক্যাশ যোগ</th><th>রিসিট</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($cardInT as $ci):
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
            <td class="<?php echo $ci['charge_amount'] > 0 ? 'nn' : 'mt'; ?>"><?php echo $ci['charge_amount'] > 0 ? '৳' . number_format($ci['charge_amount']) : '—'; ?></td>
            <td class="np fw9">+৳<?php echo number_format($ci['cash_impact']); ?></td>
            <td><?php echo !empty($ci['receipt_image']) ? "<img src='".imgSrc($ci['receipt_image'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>"; ?></td>
            <td><span class="ubadge"><?php echo htmlspecialchars($ci['entry_by'] ?? 'admin'); ?></span></td>
            <td class="no-print">
                <?php if ($role === 'admin'): ?>
                <button onclick="openPwCard(<?php echo $ci['id']; ?>)" class="abt a-del"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4" style="text-align:right">মোট ক্যাশ যোগ</td><td class="np">+৳<?php echo number_format($cit); ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
    <?php endif; ?>

</div><!-- /date-card -->
<?php endforeach; ?>
<?php endif; ?>
