<?php 
declare(strict_types=1);
extract($viewData); 
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>ডেইলি রিপোর্ট - সাদা কালো ফ্যাশন</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Hind Siliguri', sans-serif; background: #f8fafc; margin: 0; padding: 15px; color: #1e293b; }
        .brand-header { text-align: center; background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; border-bottom: 4px solid #6366f1; }
        .brand-title { font-size: 32px; font-weight: 800; margin: 0; background: linear-gradient(to right, #ec4899, #8b5cf6, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-subtitle { font-size: 18px; color: #475569; font-weight: bold; margin-top: 5px; }
        .filter-badge { background: #1e293b; color: #fff; padding: 4px 12px; border-radius: 15px; font-size: 13px; display: inline-block; margin-top: 5px; }
        
        /* =========================================================
           STAFF SUMMARY BEAUTIFUL CARDS (Slimmed Down) 
           ========================================================= */
        .staff-summary-container { background: linear-gradient(135deg, #1e293b, #0f172a); padding: 15px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .staff-summary-title { color: #e2e8f0; font-size: 14px; text-align: center; margin-bottom: 12px; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; letter-spacing: 1px;}
        .staff-summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        @media(max-width: 600px) { .staff-summary-grid { grid-template-columns: repeat(2, 1fr); } }
        
        .st-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 10px 5px; border-radius: 8px; text-align: center; transition: transform 0.2s; }
        .st-card:hover { transform: translateY(-2px); background: rgba(255,255,255,0.1); }
        .st-card i { font-size: 16px; margin-bottom: 4px; }
        .st-card h4 { margin: 0; font-size: 11px; color: #cbd5e1; margin-bottom: 2px;}
        .st-card span { display: block; font-size: 16px; font-weight: bold; color: #fff; font-family: 'Courier New', monospace;}

        /* --- NEW: Today's Net Cash Box (আজকের ক্যাশ) --- */
        .today-cash-box { background: #ecfccb; border: 1px dashed #84cc16; padding: 12px; border-radius: 8px; text-align: center; margin-top: 15px; }
        .today-cash-box h4 { margin: 0; color: #3f6212; font-size: 13px; font-weight: bold; text-transform: uppercase; display: flex; justify-content: center; align-items: center; gap: 5px;}
        .today-cash-box span { display: block; font-size: 24px; font-weight: 900; color: #166534; margin-top: 4px; }
        /* ========================================================= */

        .control-panel { background: #fff; padding: 10px 15px; border-radius: 10px; display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .search-group { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .inp-field { padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; font-size: 13px; }
        .btn { padding: 8px 15px; border: none; border-radius: 6px; cursor: pointer; color: white; font-weight: bold; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-search { background: #2563eb; }
        .btn-dash { background: #475569; }
        .btn-reset { background: #64748b; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 30px; }
        .s-card { background: white; padding: 10px; border-radius: 10px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-bottom: 3px solid #ddd; }
        .s-card h4 { margin: 0; font-size: 13px; color: #64748b; }
        .s-card span { display: block; font-size: 20px; font-weight: bold; margin-top: 3px; }
        .sec-header { padding: 8px 15px; border-radius: 6px 6px 0 0; color: white; font-weight: bold; display: flex; justify-content: space-between; align-items: center; margin-top: 25px; }
        
        .twrap { overflow-x: auto; background: white; border: 1px solid #e2e8f0; border-top: none; }
        .d-table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 600px; }
        .d-table th { background: #f1f5f9; padding: 10px 8px; border-bottom: 2px solid #e2e8f0; text-align: center; font-size: 13px; color: #334155; white-space: nowrap;}
        .d-table td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; text-align: center; vertical-align: middle; }
        .sub-total-row td { background: #fff7ed; font-weight: bold; color: #ea580c; border-top: 2px solid #fdba74; }
        .thumb { width: 35px; height: 35px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; cursor: pointer; }
        .entry-badge { background: #f1f5f9; padding: 2px 6px; border-radius: 10px; font-size: 11px; color: #475569; border: 1px solid #cbd5e1; }
        .btn-del { background: #ef4444; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; color: white; border: none; cursor: pointer; }
        .bg-sale { background: #059669; } .c-sale { color: #059669; font-weight: bold;}
        .bg-col { background: #2563eb; } .c-col { color: #2563eb; font-weight: bold;}
        .bg-exp { background: #dc2626; } .c-exp { color: #dc2626; font-weight: bold;}
        .bg-sup { background: #ea580c; } .c-sup { color: #ea580c; font-weight: bold;}
        
        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 10px 20px; border-radius: 5px; z-index: 10000; display: none; }
    </style>
</head>
<body>

<div class="brand-header">
    <h1 class="brand-title">সাদা কালো ফ্যাশন</h1>
    <div class="page-subtitle">প্রতিদিনের রিপোর্ট</div>
    <div class="filter-badge"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars((string)$filter_msg); ?></div>
</div>

<?php if ($role !== 'admin'): ?>
<div class="staff-summary-container">
    <div class="staff-summary-title">
        <i class="fas fa-history" style="color: #38bdf8;"></i> বিগত ৭ দিনের সামারি
    </div>
    <div class="staff-summary-grid">
        <div class="st-card" style="border-top: 2px solid #10b981;">
            <i class="fas fa-coins" style="color: #10b981;"></i>
            <h4>ক্যাশ সেল</h4>
            <span>৳ <?php echo number_format($staffSummary['cash_sales']); ?></span>
        </div>
        <div class="st-card" style="border-top: 2px solid #8b5cf6;">
            <i class="fas fa-hand-holding-usd" style="color: #8b5cf6;"></i>
            <h4>কালেকশন</h4>
            <span>৳ <?php echo number_format($staffSummary['customer_collections']); ?></span>
        </div>
        <div class="st-card" style="border-top: 2px solid #ef4444;">
            <i class="fas fa-file-invoice-dollar" style="color: #ef4444;"></i>
            <h4>খরচ</h4>
            <span>৳ <?php echo number_format($staffSummary['expenses']); ?></span>
        </div>
        <div class="st-card" style="border-top: 2px solid #f59e0b;">
            <i class="fas fa-truck" style="color: #f59e0b;"></i>
            <h4>সাপ্লায়ার পে</h4>
            <span>৳ <?php echo number_format($staffSummary['supplier_payments']); ?></span>
        </div>
    </div>

    <div class="today-cash-box">
        <h4><i class="fas fa-wallet"></i> আজকের হাতে ক্যাশ (Net Cash)</h4>
        <span>৳ <?php echo number_format($net_cash); ?></span>
    </div>
</div>
<?php else: ?>
<div class="summary-grid">
    <div class="s-card" style="border-color:#059669;"><h4>ক্যাশ বিক্রি</h4><span class="c-sale"><?php echo number_format($gt_sale_cash); ?></span></div>
    <div class="s-card" style="border-color:#2563eb;"><h4>কালেকশন</h4><span class="c-col"><?php echo number_format($gt_coll); ?></span></div>
    <div class="s-card" style="border-color:#7c3aed;"><h4>কাস্টমার জমা</h4><span style="color:#7c3aed;"><?php echo number_format($gt_cust_rcv); ?></span></div>
    <div class="s-card" style="border-color:#dc2626;"><h4>মোট খরচ</h4><span class="c-exp"><?php echo number_format($gt_exp); ?></span></div>
    <div class="s-card" style="border-color:#ea580c;"><h4>সাপ্লায়ার পে</h4><span class="c-sup"><?php echo number_format($gt_sup_pay); ?></span></div>
    <div class="s-card" style="background:#ecfccb; border-color:#84cc16; grid-column: 1 / -1;">
        <h4 style="color:#3f6212;"><?php echo $is_search ? 'মোট স্থিতি (Filtered)' : 'হাতে ক্যাশ (Net Cash)'; ?></h4>
        <span style="color:#3f6212; font-size:24px;"><?php echo number_format($net_cash); ?> ৳</span>
    </div>
</div>
<?php endif; ?>

<div class="control-panel">
    <form method="GET" class="search-group">
        <label style="font-size: 13px; font-weight: bold; color: #475569;">শুরু:</label>
        <input type="date" name="from_date" value="<?php echo htmlspecialchars((string)$from_date); ?>" <?php echo $date_input_state; ?> class="inp-field">
        <label style="font-size: 13px; font-weight: bold; color: #475569;">শেষ:</label>
        <input type="date" name="to_date" value="<?php echo htmlspecialchars((string)$to_date); ?>" <?php echo $date_input_state; ?> class="inp-field">
        
        <input type="text" name="search" placeholder="নাম, মেমো বা এন্ট্রি..." value="<?php echo htmlspecialchars((string)$search); ?>" class="inp-field" style="width: 180px;">
        
        <button type="submit" class="btn btn-search"><i class="fas fa-sync-alt"></i> লোড করুন</button>
        <?php if($is_search || $from_date != date('Y-m-d') || $to_date != date('Y-m-d')): ?>
            <a href="daily_report.php" class="btn btn-reset"><i class="fas fa-undo"></i> রিসেট</a>
        <?php endif; ?>
    </form>
    <a href="dashboard.php" class="btn btn-dash"><i class="fas fa-home"></i> ড্যাশবোর্ড</a>
</div>

<?php if(!empty($reports['sales'])): ?>
<div class="sec-header bg-sale"><span><i class="fas fa-tshirt"></i> বিক্রি তালিকা</span></div>
<div class="twrap">
<table class="d-table">
    <thead><tr><th>তারিখ</th><th>মেমো</th><th>কাস্টমার</th><th>পিস</th><th>মোট বিল</th><th>জমা</th><th>বাকি</th><th>এন্ট্রি বাই</th><th>ছবি</th><th>অ্যাকশন</th></tr></thead>
    <tbody>
        <?php 
        $st_qty = 0; $st_bill = 0; $st_paid = 0; $st_due = 0;
        foreach($reports['sales'] as $s): 
            $st_qty += (int)$s['quantity']; $st_bill += (float)$s['total_bill']; $st_paid += (float)$s['paid_amount']; $st_due += (float)$s['due_amount'];
        ?>
        <tr>
            <td><?php echo date('d-M', strtotime((string)$s['report_date'])); ?></td>
            <td><?php echo htmlspecialchars((string)$s['memo_no']); ?></td>
            <td style="text-align: left;"><?php echo htmlspecialchars((string)$s['customer_name']); ?></td>
            <td><?php echo htmlspecialchars((string)$s['quantity']); ?></td>
            <td><?php echo number_format((float)$s['total_bill']); ?></td>
            <td class="c-sale"><?php echo number_format((float)$s['paid_amount']); ?></td>
            <td class="c-exp"><?php echo number_format((float)$s['due_amount']); ?></td>
            <td><span class="entry-badge"><?php echo htmlspecialchars((string)$s['prepared_by']); ?></span></td>
            <td><?php if(!empty($s['photo'])) echo "<img src='".htmlspecialchars((string)$s['photo'])."' class='thumb' onclick='viewImg(this.src)'>"; ?></td>
            <td><?php if($role === 'admin'): ?><button onclick="delItem('sales_entries', <?php echo (int)$s['id']; ?>)" class="btn-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="sub-total-row"><td colspan="3" style="text-align:right;">সাব-টোটাল:</td><td><?php echo $st_qty; ?></td><td><?php echo number_format($st_bill); ?></td><td><?php echo number_format($st_paid); ?></td><td><?php echo number_format($st_due); ?></td><td colspan="3"></td></tr>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php if(!empty($reports['colls'])): ?>
<div class="sec-header bg-col"><span><i class="fas fa-hand-holding-usd"></i> কালেকশন</span></div>
<div class="twrap">
<table class="d-table">
    <thead><tr><th>তারিখ</th><th>নাম</th><th>ক্যাশ</th><th>বিকাশ</th><th>মোট জমা</th><th>এন্ট্রি বাই</th><th>অ্যাকশন</th></tr></thead>
    <tbody>
        <?php 
        $st_cash = 0; $st_bkash = 0; $st_tot = 0;
        foreach($reports['colls'] as $c): 
            $st_cash += (float)$c['cash_amount']; $st_bkash += (float)$c['bkash_amount']; $st_tot += (float)$c['total_deposit'];
        ?>
        <tr>
            <td><?php echo date('d-M', strtotime((string)$c['report_date'])); ?></td>
            <td style="text-align: left;"><?php echo htmlspecialchars((string)$c['payer_name']); ?></td>
            <td><?php echo number_format((float)$c['cash_amount']); ?></td>
            <td><?php echo number_format((float)$c['bkash_amount']); ?></td>
            <td class="c-col"><?php echo number_format((float)$c['total_deposit']); ?></td>
            <td><span class="entry-badge"><?php echo htmlspecialchars((string)$c['prepared_by']); ?></span></td>
            <td><?php if($role === 'admin'): ?><button onclick="delItem('collection_entries', <?php echo (int)$c['id']; ?>)" class="btn-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="sub-total-row"><td colspan="2" style="text-align:right;">সাব-টোটাল:</td><td><?php echo number_format($st_cash); ?></td><td><?php echo number_format($st_bkash); ?></td><td><?php echo number_format($st_tot); ?></td><td colspan="2"></td></tr>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php if(!empty($reports['exps'])): ?>
<div class="sec-header bg-exp"><span><i class="fas fa-file-invoice-dollar"></i> খরচ</span></div>
<div class="twrap">
<table class="d-table">
    <thead><tr><th>তারিখ</th><th>বিবরণ</th><th>টাকা</th><th>ভাউচার</th><th>এন্ট্রি বাই</th><th>অ্যাকশন</th></tr></thead>
    <tbody>
        <?php $st_exp_val = 0; foreach($reports['exps'] as $e): $st_exp_val += (float)$e['amount']; ?>
        <tr>
            <td><?php echo date('d-M', strtotime((string)$e['report_date'])); ?></td>
            <td style="text-align: left;"><?php echo htmlspecialchars((string)$e['description']); ?></td>
            <td class="c-exp"><?php echo number_format((float)$e['amount']); ?></td>
            <td><?php if(!empty($e['photo'])) echo "<img src='".htmlspecialchars((string)$e['photo'])."' class='thumb' onclick='viewImg(this.src)'>"; ?></td>
            <td><span class="entry-badge"><?php echo htmlspecialchars((string)$e['prepared_by']); ?></span></td>
            <td><?php if($role === 'admin'): ?><button onclick="delItem('expense_entries', <?php echo (int)$e['id']; ?>)" class="btn-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="sub-total-row"><td colspan="2" style="text-align:right;">সাব-টোটাল:</td><td><?php echo number_format($st_exp_val); ?></td><td colspan="3"></td></tr>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php if(!empty($reports['sup_pay'])): ?>
<div class="sec-header bg-sup"><span><i class="fas fa-truck"></i> সাপ্লায়ার পেমেন্ট</span></div>
<div class="twrap">
<table class="d-table">
    <thead><tr><th>তারিখ</th><th>সাপ্লায়ার</th><th>মেমো</th><th>বিল রিসিভ</th><th>পেমেন্ট</th><th>ছবি</th><th>অ্যাকশন</th></tr></thead>
    <tbody>
        <?php 
        $st_sbill = 0; $st_spay = 0;
        foreach($reports['sup_pay'] as $sp): 
            $st_sbill += (float)$sp['bill_received']; $st_spay += (float)$sp['payment_given'];
        ?>
        <tr>
            <td><?php echo date('d-M', strtotime((string)$sp['tr_date'])); ?></td>
            <td style="text-align: left;"><?php echo htmlspecialchars((string)$sp['shop_name']); ?><br><small style="color:#64748b;"><?php echo htmlspecialchars((string)$sp['sup_name']); ?></small></td>
            <td><?php echo htmlspecialchars((string)$sp['memo_no']); ?></td>
            <td><?php echo number_format((float)$sp['bill_received']); ?></td>
            <td class="c-sup"><?php echo number_format((float)$sp['payment_given']); ?></td>
            <td><?php if(!empty($sp['photo'])) echo "<img src='".htmlspecialchars((string)$sp['photo'])."' class='thumb' onclick='viewImg(this.src)'>"; ?></td>
            <td><?php if($role === 'admin'): ?><button onclick="delItem('supplier_transactions', <?php echo (int)$sp['id']; ?>)" class="btn-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="sub-total-row"><td colspan="3" style="text-align:right;">সাব-টোটাল:</td><td><?php echo number_format($st_sbill); ?></td><td><?php echo number_format($st_spay); ?></td><td colspan="2"></td></tr>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php if(!empty($reports['cust_trans'])): ?>
<div class="sec-header" style="background:#7c3aed;"><span><i class="fas fa-users"></i> কাস্টমার লেজার</span></div>
<div class="twrap">
<table class="d-table">
    <thead><tr><th>তারিখ</th><th>কাস্টমার</th><th>বিবরণ</th><th>বিল</th><th>জমা</th><th>এন্ট্রি বাই</th><th>অ্যাকশন</th></tr></thead>
    <tbody>
        <?php 
        $st_cbill = 0; $st_crcv = 0;
        foreach($reports['cust_trans'] as $ct): 
            $st_cbill += (float)$ct['bill_amount']; $st_crcv += (float)$ct['received_amount'];
        ?>
        <tr>
            <td><?php echo date('d-M', strtotime((string)$ct['tr_date'])); ?></td>
            <td style="text-align: left;"><?php echo htmlspecialchars((string)$ct['shop_name']); ?><br><small style="color:#64748b;"><?php echo htmlspecialchars((string)$ct['customer_name']); ?></small></td>
            <td><?php echo htmlspecialchars((string)$ct['description']); ?></td>
            <td><?php echo number_format((float)$ct['bill_amount']); ?></td>
            <td style="color:#7c3aed; font-weight:bold;"><?php echo number_format((float)$ct['received_amount']); ?></td>
            <td><span class="entry-badge"><?php echo htmlspecialchars((string)($ct['entry_by'] ?? 'N/A')); ?></span></td>
            <td><?php if($role === 'admin'): ?><button onclick="delItem('customer_transactions', <?php echo (int)$ct['id']; ?>)" class="btn-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="sub-total-row"><td colspan="3" style="text-align:right;">সাব-টোটাল:</td><td><?php echo number_format($st_cbill); ?></td><td><?php echo number_format($st_crcv); ?></td><td colspan="2"></td></tr>
    </tbody>
</table>
</div>
<?php endif; ?>

<div id="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; z-index:999;" onclick="this.style.display='none'">
    <img id="bigImg" style="max-width:90%; max-height:90%; border-radius:8px;">
</div>

<div id="toast" class="toast"></div>

<script>
    function viewImg(src) { document.getElementById('bigImg').src = src; document.getElementById('modal').style.display = 'flex'; }
    
    function showToast(msg, isSuccess) {
        let t = document.getElementById('toast');
        t.textContent = msg;
        t.style.background = isSuccess ? '#059669' : '#dc2626';
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }

    function delItem(table, id) {
        let pass = prompt("ডিলিট করতে পাসওয়ার্ড দিন (@):");
        if (pass !== null && pass !== '') {
            if(confirm("আপনি কি নিশ্চিত ডিলিট করবেন?")) {
                let fd = new FormData();
                fd.append('ajax_action', 'delete_entry');
                fd.append('table', table);
                fd.append('id', id);
                fd.append('pass', pass);
                fd.append('csrf_token', '<?php echo $_SESSION["csrf_token"] ?? ""; ?>');

                fetch('daily_report.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if(res.status === 'success'){
                        showToast(res.message, true);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        alert(res.message);
                    }
                })
                .catch(err => alert("সার্ভার সমস্যা!"));
            }
        }
    }
</script>

</body>
</html>