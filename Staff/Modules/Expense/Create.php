<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if(isset($_SESSION['last_activity'])&&(time()-$_SESSION['last_activity']>1200)){session_unset();session_destroy();echo "<script>alert('Session Expired!');window.location.href='../../index.php';</script>";exit;}

$_SESSION['last_activity']=time();
if(!isset($_SESSION['loggedin'])||$_SESSION['loggedin']!==true){echo "<script>window.location.href='../../index.php';</script>";exit;}

$role=$_SESSION['role']??'admin';
if($role!=='admin'&&$role!=='Admin'){echo "<script>alert('Access Denied! Admin Only.');window.location.href='../../index.php';</script>";exit;}

include __DIR__ . '/../../db_connect.php';
$entry_by_user=$_SESSION['username']??'Admin';
date_default_timezone_set('Asia/Dhaka');
$staff_list=$conn->query("SELECT id,staff_name,staff_email FROM staff_info WHERE staff_status='Active' AND email_verified=1 ORDER BY staff_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Entry — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <style>
        .amount-display {
            background:var(--sk-surface);
            border:2px solid var(--sk-border);
            border-radius:var(--sk-radius);
            padding:16px;
            text-align:center;
            margin-bottom:20px;
        }
        .amount-display .big-amount {
            font-size:36px;font-weight:900;
            color:var(--sk-danger);
            font-variant-numeric:tabular-nums;
        }
        .amount-display .currency { font-size:20px;font-weight:900;color:var(--sk-danger);opacity:0.7; }

        .type-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:16px; }
        .type-btn {
            background:var(--sk-surface);
            border:2px solid var(--sk-border);
            border-radius:12px;
            padding:12px 8px;
            text-align:center;
            cursor:pointer;
            transition:all 0.2s;
            font-size:11px;font-weight:800;
            color:var(--sk-text-secondary);
            display:flex;flex-direction:column;align-items:center;gap:6px;
        }
        .type-btn i { font-size:18px; }
        .type-btn.selected { border-color:var(--sk-primary);background:rgba(124,92,252,0.1);color:var(--sk-primary); }
        .type-btn:hover { border-color:var(--sk-primary);color:var(--sk-primary); }
    </style>
</head>
<body>

<nav class="sk-navbar">
    <a href="../../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Back</a>
    <div class="sk-navbar-title">Expense Entry<span class="sk-navbar-subtitle">Staff Advance & Deductions</span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>

<div class="page-wrap mt-3">

    <div class="sk-banner animate-in">
        <img src="../../Assets/images/banner.jpg" alt="Banner" onerror="this.parentElement.style.background='linear-gradient(135deg,#FF4A6A,#CC1133)';this.style.display='none'">
    </div>

    <div id="responseMsg" class="sk-response mb-3"></div>

    <div class="sk-card animate-in delay-1" style="padding:20px;">

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--sk-border);">
            <div style="width:42px;height:42px;background:linear-gradient(135deg,#FF4A6A,#CC1133);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;box-shadow:0 4px 14px rgba(255,74,106,0.4);">
                <i class="fa fa-hand-holding-dollar"></i>
            </div>
            <div>
                <div style="font-size:14px;font-weight:900;text-transform:uppercase;letter-spacing:0.5px;">স্টাফ খরচ / অ্যাডভান্স</div>
                <div style="font-size:10px;color:var(--sk-text-muted);font-weight:700;">Entry by: <?php echo htmlspecialchars($entry_by_user); ?></div>
            </div>
        </div>

        <form id="expenseForm">

            <!-- Date -->
            <label class="sk-label">তারিখ (Date)</label>
            <div class="sk-input-group">
                <i class="fa fa-calendar sk-icon"></i>
                <input type="date" name="expense_date" class="sk-input" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <!-- Staff Select -->
            <label class="sk-label">স্টাফ নির্বাচন করুন</label>
            <div class="sk-input-group">
                <i class="fa fa-user sk-icon"></i>
                <select name="staff_id" class="sk-input" required id="staffSelect">
                    <option value="">— স্টাফ সিলেক্ট করুন —</option>
                    <?php foreach($staff_list as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['staff_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Expense Type Grid -->
            <label class="sk-label">খরচের ধরন (Type)</label>
            <input type="hidden" name="expense_type" id="expenseTypeInput" value="Weekly Expense" required>
            <div class="type-grid mb-3">
                <div class="type-btn selected" onclick="selectType(this,'Weekly Expense')">
                    <i class="fa fa-calendar-week" style="color:#FFB347;"></i>সাপ্তাহিক
                </div>
                <div class="type-btn" onclick="selectType(this,'Monthly Expense')">
                    <i class="fa fa-calendar-days" style="color:#7C5CFC;"></i>মাসিক
                </div>
                <div class="type-btn" onclick="selectType(this,'Emergency Advance')">
                    <i class="fa fa-bolt" style="color:#FF4A6A;"></i>জরুরি অ্যাডভান্স
                </div>
                <div class="type-btn" onclick="selectType(this,'Other')">
                    <i class="fa fa-ellipsis" style="color:#2196F3;"></i>অন্যান্য
                </div>
            </div>

            <!-- Amount -->
            <label class="sk-label">টাকার পরিমাণ (Amount)</label>
            <div class="amount-display">
                <div>
                    <span class="currency">৳</span>
                    <span class="big-amount" id="amountDisplay">0.00</span>
                </div>
                <div style="font-size:10px;color:var(--sk-text-muted);margin-top:4px;font-weight:700;">টাকা লিখুন নিচে</div>
            </div>
            <div class="sk-input-group">
                <i class="fa fa-bangladeshi-taka-sign sk-icon" style="color:var(--sk-danger);"></i>
                <input type="number" step="0.01" name="amount" class="sk-input" placeholder="0.00" required
                       style="color:var(--sk-danger)!important;font-size:18px!important;font-weight:900!important;"
                       id="amountInput" oninput="updateDisplay(this.value)">
            </div>

            <!-- Description -->
            <label class="sk-label">বিস্তারিত (Description)</label>
            <textarea name="details" class="sk-input" rows="2" placeholder="খরচের কারণ লিখুন..." style="padding-left:16px!important;"></textarea>

            <button type="submit" id="submitBtn" class="btn-app-danger w-100 mt-2" style="font-size:15px;padding:14px;">
                <i class="fa fa-floppy-disk me-2"></i>সেভ করুন ও ইমেইল পাঠান
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function(){const t=localStorage.getItem('sk_theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);document.addEventListener('DOMContentLoaded',function(){const i=document.getElementById('themeIcon');if(i)i.className=t==='dark'?'fas fa-sun':'fas fa-moon';});})();
function toggleTheme(){const c=document.documentElement.getAttribute('data-bs-theme');const n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-bs-theme',n);localStorage.setItem('sk_theme',n);document.getElementById('themeIcon').className=n==='dark'?'fas fa-sun':'fas fa-moon';}

function selectType(el,val){
    document.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('expenseTypeInput').value=val;
}
function updateDisplay(val){
    const v=parseFloat(val)||0;
    document.getElementById('amountDisplay').textContent=v.toLocaleString('en-US',{minimumFractionDigits:2});
}

$('#expenseForm').on('submit',function(e){
    e.preventDefault();
    const btn=$('#submitBtn');
    const msg=$('#responseMsg');
    const orig=btn.html();
    btn.html('<i class="fa fa-spinner fa-spin me-2"></i>Processing...').prop('disabled',true);
    $.ajax({
        url:'SaveAjax.php',type:'POST',data:$(this).serialize(),dataType:'json',
        success:function(res){
            msg.show();
            if(res.status==='success'){
                msg.removeClass('error').addClass('success').html('<i class="fa fa-circle-check me-2"></i>'+res.message);
                $('#expenseForm')[0].reset();
                document.getElementById('amountDisplay').textContent='0.00';
                document.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('selected'));
                document.querySelectorAll('.type-btn')[0].classList.add('selected');
                document.getElementById('expenseTypeInput').value='Weekly Expense';
                $('input[type="date"]').val(new Date().toISOString().slice(0,10));
            }else{
                msg.removeClass('success').addClass('error').html('<i class="fa fa-triangle-exclamation me-2"></i>'+res.message);
            }
            btn.html(orig).prop('disabled',false);
            setTimeout(()=>msg.fadeOut(),5000);
        },
        error:function(){
            msg.show().removeClass('success').addClass('error').html('<i class="fa fa-wifi me-2"></i>সার্ভার এরর!');
            btn.html(orig).prop('disabled',false);
        }
    });
});
</script>
</body>
</html>
