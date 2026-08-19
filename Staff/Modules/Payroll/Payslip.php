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
date_default_timezone_set('Asia/Dhaka');

$selected_staff_id=$_POST['staff_id']??'';
$staff_list=$conn->query("SELECT id,staff_name FROM staff_info WHERE staff_status='Active' AND email_verified=1 ORDER BY staff_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$staff=null;
$att_summary=['P'=>0,'A'=>0,'H'=>0,'L'=>0,'Late'=>0];
$exp_adv=$exp_week=$exp_month=$exp_other=0;
$daily_salary=0;$to_date=date('Y-m-d');$from_date='';

if($selected_staff_id){
    $stmt=$conn->prepare("SELECT * FROM staff_info WHERE id=?");$stmt->execute([$selected_staff_id]);$staff=$stmt->fetch(PDO::FETCH_ASSOC);
    if($staff){
        $daily_salary = $staff['staff_salary'] / 30;

        // ✅ Attendance period = প্রথম হাজিরা → to_date
        $first_att_q = $conn->prepare("SELECT MIN(DATE(attendance_date)) FROM staff_attendance WHERE staff_id=?");
        $first_att_q->execute([$selected_staff_id]);
        $att_from = $first_att_q->fetchColumn();

        // ✅ Expense period = প্রথম খরচ → to_date
        $first_exp_q = $conn->prepare("SELECT MIN(expense_date) FROM staff_expenses WHERE staff_id=?");
        $first_exp_q->execute([$selected_staff_id]);
        $exp_from = $first_exp_q->fetchColumn();

        // দুটোর মধ্যে যেটা আগে — payslip-এ overall period দেখানোর জন্য
        $dates = array_filter([$att_from, $exp_from]);
        $from_date = !empty($dates) ? min($dates) : $staff['staff_join_date'];

        // ✅ Attendance summary — প্রথম হাজিরা থেকে to_date
        if ($att_from) {
            $att_q = $conn->prepare("SELECT status, SUM(late_time) as late, COUNT(*) as cnt FROM staff_attendance WHERE staff_id=? AND attendance_date BETWEEN ? AND ? GROUP BY status");
            $att_q->execute([$selected_staff_id, $att_from, $to_date]);
            while($r = $att_q->fetch(PDO::FETCH_ASSOC)){
                if($r['status']=='Present') $att_summary['P'] = $r['cnt'];
                if($r['status']=='Absent')  $att_summary['A'] = $r['cnt'];
                if($r['status']=='Half')    $att_summary['H'] = $r['cnt'];
                if($r['status']=='Leave')   $att_summary['L'] = $r['cnt'];
                $att_summary['Late'] += $r['late'];
            }
        }

        // ✅ Expense summary — প্রথম খরচ থেকে to_date
        if ($exp_from) {
            $exp_q = $conn->prepare("SELECT expense_type, SUM(amount) as total FROM staff_expenses WHERE staff_id=? AND expense_date BETWEEN ? AND ? GROUP BY expense_type");
            $exp_q->execute([$selected_staff_id, $exp_from, $to_date]);
            while($r = $exp_q->fetch(PDO::FETCH_ASSOC)){
                if($r['expense_type']=='Emergency Advance')   $exp_adv   += $r['total'];
                elseif($r['expense_type']=='Weekly Expense')  $exp_week  += $r['total'];
                elseif($r['expense_type']=='Monthly Expense') $exp_month += $r['total'];
                else $exp_other += $r['total'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip & Settlement — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <style>
        /* Payslip Paper */
        .payslip-paper {
            background:#ffffff;
            color:#1a1a2e;
            border-radius:var(--sk-radius);
            padding:24px;
            box-shadow:var(--sk-shadow);
            border:1px solid var(--sk-border);
            max-width:800px;margin:0 auto;
            font-family:'Nunito',sans-serif;
        }
        [data-bs-theme="dark"] .payslip-paper { background:#12112a;color:#eeeeff;border-color:var(--sk-border); }

        .payslip-header { display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid;padding-bottom:12px;margin-bottom:16px; }
        [data-bs-theme="dark"] .payslip-header { border-color:#2a2a50; }
        [data-bs-theme="light"] .payslip-header { border-color:#e0e0f0; }

        .payslip-logo { width:48px;height:48px;object-fit:contain; }
        .payslip-company { font-size:16px;font-weight:900;text-transform:uppercase;letter-spacing:1px; }
        .payslip-sub { font-size:10px;color:#7070A0;font-weight:700;text-transform:uppercase; }

        .payslip-info-grid { display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;background:var(--sk-surface);padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:12px;border:1px solid var(--sk-border); }
        .payslip-info-row { display:flex;justify-content:space-between;font-weight:600; }
        .payslip-info-row span:first-child { color:#7070A0; }
        .payslip-info-row span:last-child { font-weight:800; }

        .ps-section-title { font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:1px;padding:4px 10px;margin:10px 0 4px;border-left:3px solid var(--sk-primary);background:rgba(124,92,252,0.07);border-radius:0 6px 6px 0; }
        .ps-row { display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed var(--sk-border);font-size:12px;font-weight:600; }
        .ps-row:last-child { border-bottom:none; }
        .ps-row-total { display:flex;justify-content:space-between;padding:6px 0;border-top:2px solid var(--sk-border);font-size:13px;font-weight:900;margin-top:4px; }
        .ps-earn { color:var(--sk-success); } .ps-deduct { color:var(--sk-danger); }

        .net-balance-box { display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-radius:10px;border:2px solid;font-size:15px;font-weight:900;margin-top:12px; }

        .signature-area { display:flex;justify-content:space-between;margin-top:40px;padding-top:10px; }
        .sig-box { border-top:1px solid var(--sk-border);padding-top:8px;width:180px;text-align:center;font-size:10px;font-weight:700;color:#7070A0;text-transform:uppercase; }

        /* Admin panel */
        .admin-panel { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:var(--sk-radius);padding:20px;margin-top:16px; }
        .admin-panel-title { font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:1px;color:var(--sk-info);margin-bottom:16px;display:flex;align-items:center;gap:8px; }

        .adj-row { background:var(--sk-surface);border:1px solid var(--sk-border);border-radius:12px;padding:12px 14px;margin-bottom:10px;display:flex;flex-wrap:wrap;align-items:center;gap:10px; }
        .adj-label { font-size:11px;font-weight:800;min-width:140px; }
        .adj-input { background:var(--sk-input-bg)!important;border:1.5px solid var(--sk-border)!important;color:var(--sk-text)!important;border-radius:10px!important;padding:8px 12px!important;width:100px;font-weight:800!important;text-align:center;outline:none; }
        .adj-input:focus { border-color:var(--sk-primary)!important;box-shadow:0 0 0 2px rgba(124,92,252,0.2)!important; }
        .adj-note { font-size:10px;color:var(--sk-text-muted);font-weight:700; }

        /* OTP Panel */
        .otp-panel { background:var(--sk-surface);border:1px solid var(--sk-border);border-radius:var(--sk-radius);padding:16px;margin-top:16px;text-align:center; }
        .otp-input { background:var(--sk-input-bg)!important;border:2px solid var(--sk-primary)!important;color:var(--sk-text)!important;border-radius:10px;padding:10px 14px;text-align:center;font-size:16px;font-weight:900;letter-spacing:6px;width:160px;outline:none; }

        .btn-disabled { opacity:0.45;cursor:not-allowed;filter:grayscale(0.5);pointer-events:none; }

        @media print {
            .no-print{display:none!important}
            body{background:white!important;padding:0!important}
            .payslip-paper{box-shadow:none!important;border:none!important;background:white!important;color:black!important;padding:0!important;max-width:100%!important;margin:0!important}
            [data-bs-theme="dark"] .payslip-paper{background:white!important;color:black!important}
            .ps-section-title{background:#f3f4f6!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        }
    </style>
</head>
<body>

<nav class="sk-navbar no-print">
    <a href="../../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Dashboard</a>
    <div class="sk-navbar-title">Payslip & Ledger<span class="sk-navbar-subtitle">Settlement Statement</span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>

<div class="page-wrap mt-3">

    <!-- Staff Selector -->
    <div class="sk-card no-print mb-3 animate-in" style="padding:16px;">
        <form method="POST" class="d-flex gap-2 align-items-end flex-wrap">
            <div class="flex-grow-1">
                <label class="sk-label">Staff নির্বাচন করুন</label>
                <div class="sk-input-group" style="margin-bottom:0;">
                    <i class="fa fa-user sk-icon"></i>
                    <select name="staff_id" class="sk-input" required>
                        <option value="">— Select Staff —</option>
                        <?php foreach($staff_list as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $selected_staff_id==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['staff_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-app-primary" style="padding:12px 20px;font-size:13px;">
                <i class="fa fa-magnifying-glass"></i> Generate
            </button>
        </form>
    </div>

    <?php if($staff): ?>

    <!-- Payslip Paper -->
    <div class="payslip-paper mb-3 animate-in delay-1" id="payslipArea">

        <!-- Header -->
        <div class="payslip-header">
            <div class="d-flex align-items-center gap-3">
                <img src="../../Assets/images/logo.png" class="payslip-logo" onerror="this.style.display='none'">
                <div>
                    <div class="payslip-company">Sada Kalo Fashion</div>
                    <div class="payslip-sub">Payslip & Final Settlement</div>
                </div>
            </div>
            <div style="text-align:right;font-size:11px;">
                <div style="color:#7070A0;font-weight:700;text-transform:uppercase;font-size:9px;">Report Date</div>
                <div style="font-weight:800;"><?php echo date('d M Y',strtotime($to_date)); ?></div>
            </div>
        </div>

        <!-- Staff Info Grid -->
        <div class="payslip-info-grid">
            <div class="payslip-info-row"><span>Staff Name</span><span><?php echo htmlspecialchars($staff['staff_name']); ?></span></div>
            <div class="payslip-info-row"><span>Basic Salary</span><span style="color:var(--sk-success);">Tk. <?php echo number_format($staff['staff_salary'],2); ?></span></div>
            <div class="payslip-info-row"><span>Joining Date</span><span><?php echo date('d M Y',strtotime($staff['staff_join_date'])); ?></span></div>
            <div class="payslip-info-row" style="color:var(--sk-info);"><span>হাজিরা হিসাব (Attendance)</span><span><?php echo $att_from ? date('d M Y',strtotime($att_from)) . ' → ' . date('d M Y',strtotime($to_date)) : 'কোনো হাজিরা নেই'; ?></span></div>
            <div class="payslip-info-row" style="color:var(--sk-info);"><span>খরচ হিসাব (Expense)</span><span><?php echo $exp_from ? date('d M Y',strtotime($exp_from)) . ' → ' . date('d M Y',strtotime($to_date)) : 'কোনো খরচ নেই'; ?></span></div>
        </div>

        <div class="row g-3">
            <!-- Left Column -->
            <div class="col-12 col-md-6">
                <div class="ps-section-title"><i class="fa fa-calendar-check me-1"></i>Attendance Report</div>
                <div class="ps-row"><span>Present Days</span><span><?php echo $att_summary['P']; ?> day(s)</span></div>
                <div class="ps-row"><span>Half Days</span><span><?php echo $att_summary['H']; ?> day(s)</span></div>
                <div class="ps-row"><span>Leave Days</span><span><?php echo $att_summary['L']; ?> day(s)</span></div>
                <div class="ps-row ps-deduct"><span>Absent Days</span><span><?php echo $att_summary['A']; ?> day(s)</span></div>
                <div class="ps-row ps-deduct"><span>Late Time</span><span><?php echo $att_summary['Late']; ?> min(s)</span></div>

                <div class="ps-section-title mt-3"><i class="fa fa-coins me-1"></i>Earnings & Adjustments</div>
                <div class="ps-row ps-earn"><span>Basic Salary Earned</span><span id="disp_basic">Tk. 0.00</span></div>
                <div class="ps-row ps-earn" id="leave_approved_row" style="display:none;"><span>(+) Leave Approved</span><span id="disp_leave_approved">0 days</span></div>
                <div class="ps-row ps-deduct" id="leave_cut_row" style="display:none;"><span>(-) Leave Deduction</span><span id="disp_leave_cut">Tk. 0.00</span></div>
                <div class="ps-row ps-earn" id="absent_approved_row" style="display:none;"><span>(+) Absent Approved</span><span id="disp_absent_approved">0 days</span></div>
                <div class="ps-row ps-deduct" id="absent_cut_row" style="display:none;"><span>(-) Absent Deduction</span><span id="disp_absent_cut">Tk. 0.00</span></div>
                <div class="ps-row ps-deduct" id="late_cut_row" style="display:none;"><span>(-) Late Deduction</span><span id="disp_late_deduct">Tk. 0.00</span></div>
                <div class="ps-row ps-earn" id="sal_bonus_row" style="display:none;"><span>(+) Salary Bonus</span><span id="disp_sal_bonus">Tk. 0.00</span></div>
                <div class="ps-row ps-earn" id="oth_bonus_row" style="display:none;"><span>(+) Other Bonus</span><span id="disp_oth_bonus">Tk. 0.00</span></div>
                <div class="ps-row-total ps-earn"><span>Total Earnings</span><span id="total_earnings">Tk. 0.00</span></div>
            </div>

            <!-- Right Column -->
            <div class="col-12 col-md-6">
                <div class="ps-section-title"><i class="fa fa-file-invoice-dollar me-1"></i>Deductions / Expenses</div>
                <div class="ps-row ps-deduct"><span>Advance Taken</span><span>Tk. <?php echo number_format($exp_adv,2); ?></span></div>
                <div class="ps-row ps-deduct"><span>Weekly Expenses</span><span>Tk. <?php echo number_format($exp_week,2); ?></span></div>
                <div class="ps-row ps-deduct"><span>Monthly Expenses</span><span>Tk. <?php echo number_format($exp_month,2); ?></span></div>
                <div class="ps-row ps-deduct"><span>Other Deductions</span><span>Tk. <?php echo number_format($exp_other,2); ?></span></div>
                <div class="ps-row-total ps-deduct"><span>Total Deductions</span><span id="total_expenses">Tk. <?php echo number_format($exp_adv+$exp_week+$exp_month+$exp_other,2); ?></span></div>

                <div class="ps-section-title mt-3" style="border-left-color:var(--sk-warning);background:rgba(255,179,71,0.07);"><i class="fa fa-scale-balanced me-1"></i>Final Settlement</div>
                <div class="ps-row ps-earn"><span>Gross Earnings</span><span id="final_earn">Tk. 0.00</span></div>
                <div class="ps-row ps-deduct"><span>(-) Gross Deductions</span><span id="final_exp">Tk. <?php echo number_format($exp_adv+$exp_week+$exp_month+$exp_other,2); ?></span></div>
                <div class="ps-row-total"><span>Payable / Receivable</span><span id="final_payable">Tk. 0.00</span></div>
                <div class="ps-row ps-earn"><span>(-) Cash Paid Today</span><span id="disp_cash_paid">Tk. 0.00</span></div>

                <div class="net-balance-box mt-2" id="final_net_balance" style="border-color:var(--sk-border);">
                    <span>Net Balance</span><span>Tk. 0.00</span>
                </div>
            </div>
        </div>

        <!-- Signature Area -->
        <div class="signature-area">
            <div class="sig-box" id="staff_signature_box">
                Staff Signature<br><span style="font-size:9px;color:#9090A0;">(Pending OTP Verification)</span>
            </div>
            <div class="sig-box">Authorized Signature</div>
        </div>
    </div>

    <!-- OTP Panel -->
    <div class="otp-panel no-print animate-in delay-2" id="otpPanel">
        <div style="font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:0.5px;color:var(--sk-info);margin-bottom:6px;"><i class="fa fa-fingerprint me-2"></i>Staff Digital Signature Required</div>
        <p style="font-size:11px;color:var(--sk-text-muted);margin-bottom:12px;">OTP পাঠানো হবে: <strong style="color:var(--sk-text);"><?php echo $staff['staff_email']; ?></strong></p>
        <div id="otp_msg" style="font-size:11px;font-weight:800;margin-bottom:8px;display:none;"></div>
        <button type="button" id="btnSendOTP" onclick="sendSignatureOTP()" class="btn-app-primary" style="padding:10px 24px;font-size:12px;margin-bottom:10px;">
            <i class="fa fa-paper-plane me-1"></i>Send OTP to Email
        </button>
        <div id="otpInputBox" style="display:none;justify-content:center;gap:8px;align-items:center;">
            <input type="text" id="staff_otp" class="otp-input" placeholder="______" maxlength="6">
            <button type="button" id="btnVerifyOTP" onclick="verifySignatureOTP()" class="btn-app-success" style="padding:10px 20px;font-size:12px;">
                <i class="fa fa-check me-1"></i>Verify & Sign
            </button>
        </div>
    </div>

    <!-- Admin Toggle -->
    <div class="text-center mt-3 no-print animate-in delay-2">
        <button onclick="toggleAdminPanel()" class="btn-theme" style="font-size:12px;padding:10px 20px;">
            <i class="fa fa-sliders-h me-2" style="color:var(--sk-info);"></i>Toggle Admin Control Panel
        </button>
    </div>

    <!-- Admin Panel -->
    <div id="adminPanel" class="admin-panel no-print animate-in" style="display:none;">
        <div id="ajaxResponse" style="display:none;margin-bottom:12px;"></div>
        <div class="admin-panel-title"><i class="fa fa-user-shield"></i>Admin Settlement Controls</div>

        <form id="saveSettlementForm">
            <input type="hidden" name="action" value="save_simple_payslip">
            <input type="hidden" name="staff_id" id="hidden_staff_id" value="<?php echo $selected_staff_id; ?>">
            <input type="hidden" name="from_date" value="<?php echo $from_date; ?>">
            <input type="hidden" name="to_date" value="<?php echo $to_date; ?>">
            <input type="hidden" name="final_balance" id="input_final_balance" value="0">

            <div class="adj-row">
                <div class="adj-label" style="color:var(--sk-success);">✅ ছুটি মঞ্জুর (দিন)</div>
                <input type="number" step="0.5" name="leave_approved" id="in_leave" value="0" class="adj-input" oninput="liveCalc()">
                <div class="adj-note">মোট ছুটি: <?php echo $att_summary['L']; ?> দিন</div>
            </div>
            <div class="adj-row">
                <div class="adj-label" style="color:var(--sk-success);">✅ অনুপস্থিত মঞ্জুর (দিন)</div>
                <input type="number" step="0.5" name="absent_approved" id="in_absent" value="0" class="adj-input" oninput="liveCalc()">
                <div class="adj-note">মোট অনুপস্থিত: <?php echo $att_summary['A']; ?> দিন</div>
            </div>
            <div class="adj-row" style="border-color:rgba(255,74,106,0.2);">
                <div class="adj-label" style="color:var(--sk-danger);">⏰ লেট কাটা (মিনিট)</div>
                <input type="number" name="late_fine_mins" id="in_late" value="<?php echo $att_summary['Late']; ?>" class="adj-input" style="color:var(--sk-danger)!important;border-color:rgba(255,74,106,0.4)!important;" oninput="liveCalc()">
                <div class="adj-note">মোট লেট: <?php echo $att_summary['Late']; ?> মিনিট</div>
            </div>
            <div class="adj-row">
                <div class="adj-label" style="color:var(--sk-success);">🎁 বেতন বোনাস (টাকা)</div>
                <input type="number" step="0.01" name="salary_bonus" id="in_sal_bonus" value="0" class="adj-input" style="color:var(--sk-success)!important;" oninput="liveCalc()">
            </div>
            <div class="adj-row">
                <div class="adj-label" style="color:var(--sk-success);">🎁 অন্যান্য বোনাস (টাকা)</div>
                <input type="number" step="0.01" name="other_bonus" id="in_oth_bonus" value="0" class="adj-input" style="color:var(--sk-success)!important;" oninput="liveCalc()">
            </div>
            <div class="adj-row" style="border-color:rgba(33,150,243,0.2);">
                <div class="adj-label" style="color:var(--sk-info);">💵 পরিশোধ/আদায় (টাকা)</div>
                <input type="number" step="0.01" name="cash_paid" id="in_paid" value="0" class="adj-input" style="color:var(--sk-info)!important;border-color:rgba(33,150,243,0.3)!important;" oninput="liveCalc()">
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" id="btnSave" class="btn-app-success flex-1 btn-disabled" style="font-size:13px;padding:12px;">
                    <i class="fa fa-floppy-disk me-1"></i>Save & Auto-Inactive
                </button>
                <button type="button" onclick="window.print()" class="btn-theme" style="padding:12px 20px;font-size:13px;">
                    <i class="fa fa-print me-1"></i>Print
                </button>
            </div>
        </form>
    </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function(){const t=localStorage.getItem('sk_theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);document.addEventListener('DOMContentLoaded',function(){const i=document.getElementById('themeIcon');if(i)i.className=t==='dark'?'fas fa-sun':'fas fa-moon';});})();
function toggleTheme(){const c=document.documentElement.getAttribute('data-bs-theme');const n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-bs-theme',n);localStorage.setItem('sk_theme',n);document.getElementById('themeIcon').className=n==='dark'?'fas fa-sun':'fas fa-moon';}

const dailySal=<?php echo $daily_salary??0; ?>;
const countP=<?php echo $att_summary['P']??0; ?>;
const countH=<?php echo $att_summary['H']??0; ?>;
const countL=<?php echo $att_summary['L']??0; ?>;
const countA=<?php echo $att_summary['A']??0; ?>;
const fixedExpenses=<?php echo ($exp_adv+$exp_week+$exp_month+$exp_other)??0; ?>;

function showRow(id,show){document.getElementById(id).style.display=show?'flex':'none';}
function fmt(v){return'Tk. '+Number(v).toLocaleString('en-US',{minimumFractionDigits:2});}

function liveCalc(){
    const gL=parseFloat(document.getElementById('in_leave').value)||0;
    const gA=parseFloat(document.getElementById('in_absent').value)||0;
    const dLm=parseFloat(document.getElementById('in_late').value)||0;
    const sB=parseFloat(document.getElementById('in_sal_bonus').value)||0;
    const oB=parseFloat(document.getElementById('in_oth_bonus').value)||0;
    const cP=parseFloat(document.getElementById('in_paid').value)||0;

    const lvCutDays=Math.max(0,countL-gL);
    const lvCutAmt=lvCutDays*dailySal;
    const abCutDays=Math.max(0,countA-gA);
    const abCutAmt=abCutDays*dailySal;
    const lateDeduct=dLm*(dailySal/480);
    const payDays=countP+(countH*0.5)+gL+gA;
    const basicEarned=payDays*dailySal;
    const totalEarnings=basicEarned-lvCutAmt-abCutAmt-lateDeduct+sB+oB;
    const payable=totalEarnings-fixedExpenses;
    const finalBalance=payable-cP;

    document.getElementById('disp_basic').textContent=fmt(basicEarned);
    showRow('leave_approved_row',gL>0); if(gL>0)document.getElementById('disp_leave_approved').textContent=gL+' days';
    showRow('leave_cut_row',lvCutDays>0); if(lvCutDays>0)document.getElementById('disp_leave_cut').textContent=fmt(lvCutAmt);
    showRow('absent_approved_row',gA>0); if(gA>0)document.getElementById('disp_absent_approved').textContent=gA+' days';
    showRow('absent_cut_row',abCutDays>0); if(abCutDays>0)document.getElementById('disp_absent_cut').textContent=fmt(abCutAmt);
    showRow('late_cut_row',dLm>0); if(dLm>0)document.getElementById('disp_late_deduct').textContent=fmt(lateDeduct)+' ('+dLm+' mins)';
    showRow('sal_bonus_row',sB>0); if(sB>0)document.getElementById('disp_sal_bonus').textContent=fmt(sB);
    showRow('oth_bonus_row',oB>0); if(oB>0)document.getElementById('disp_oth_bonus').textContent=fmt(oB);

    document.getElementById('total_earnings').textContent=fmt(totalEarnings);
    document.getElementById('final_earn').textContent=fmt(totalEarnings);
    document.getElementById('final_payable').textContent=fmt(payable);
    document.getElementById('disp_cash_paid').textContent=fmt(cP);
    document.getElementById('input_final_balance').value=finalBalance.toFixed(2);

    const nb=document.getElementById('final_net_balance');
    nb.style.borderColor=finalBalance>0?'var(--sk-success)':finalBalance<0?'var(--sk-danger)':'var(--sk-border)';
    nb.innerHTML=finalBalance>0
        ?'<span>Net Balance</span><span style="color:var(--sk-success);">'+fmt(finalBalance)+' <small style="font-size:11px;">(Staff gets)</small></span>'
        :finalBalance<0
        ?'<span>Net Balance</span><span style="color:var(--sk-danger);">'+fmt(Math.abs(finalBalance))+' <small style="font-size:11px;">(Owner gets)</small></span>'
        :'<span>Net Balance</span><span>Tk. 0.00 (Settled)</span>';
}

function toggleAdminPanel(){document.getElementById('adminPanel').style.display=document.getElementById('adminPanel').style.display==='none'?'block':'none';}

function sendSignatureOTP(){
    const btn=$('#btnSendOTP');
    btn.html('<i class="fa fa-spinner fa-spin me-1"></i>Sending...').prop('disabled',true);
    $.post('SignatureOtpAjax.php',{action:'send_otp',staff_id:$('#hidden_staff_id').val()},function(res){
        const msg=$('#otp_msg');msg.show();
        if(res.status==='success'){
            msg.css('color','var(--sk-success)').text('✅ '+res.message);
            btn.hide();$('#otpInputBox').css('display','flex');
        }else{msg.css('color','var(--sk-danger)').text('❌ '+res.message);btn.html('<i class="fa fa-paper-plane me-1"></i>Retry OTP').prop('disabled',false);}
    },'json');
}

function verifySignatureOTP(){
    const otp=$('#staff_otp').val();
    if(otp.length<6){$('#otp_msg').show().css('color','var(--sk-danger)').text('6 digit OTP দিন');return;}
    const btn=$('#btnVerifyOTP');
    btn.html('<i class="fa fa-spinner fa-spin me-1"></i>Verifying...').prop('disabled',true);
    $.post('SignatureOtpAjax.php',{action:'verify_otp',staff_id:$('#hidden_staff_id').val(),otp:otp},function(res){
        if(res.status==='success'){
            $('#otpPanel').hide();
            const staffName="<?php echo isset($staff['staff_name'])?htmlspecialchars(addslashes($staff['staff_name'])):''; ?>";
            const dateStr=new Date().toLocaleString('en-US',{dateStyle:'medium',timeStyle:'short'});
            $('#staff_signature_box').html('<div style="font-size:12px;font-weight:900;text-transform:uppercase;">'+staffName+'</div><div style="font-size:10px;color:var(--sk-info);margin:4px 0;">'+dateStr+'</div><div style="font-size:10px;color:var(--sk-success);font-weight:800;"><i class="fa fa-circle-check me-1"></i>DIGITALLY VERIFIED</div>');
            $('#btnSave').removeClass('btn-disabled').prop('disabled',false);
        }else{
            $('#otp_msg').show().css('color','var(--sk-danger)').text('❌ '+res.message);
            btn.html('<i class="fa fa-check me-1"></i>Verify & Sign').prop('disabled',false);
        }
    },'json');
}

$('#saveSettlementForm').on('submit',function(e){
    e.preventDefault();
    const btn=$('#btnSave'),orig=btn.html();
    btn.html('<i class="fa fa-spinner fa-spin me-1"></i>Processing...').prop('disabled',true);
    $.ajax({url:'SaveAjax.php',type:'POST',data:$(this).serialize(),dataType:'json',
        success:function(res){
            const rb=$('#ajaxResponse');rb.show();
            if(res.status==='success'){
                rb.html('<div class="sk-alert sk-alert-success"><i class="fa fa-check-circle"></i>'+res.message+'</div>');
                setTimeout(()=>location.reload(),3000);
            }else{
                rb.html('<div class="sk-alert sk-alert-danger"><i class="fa fa-triangle-exclamation"></i>'+res.message+'</div>');
                btn.html(orig).prop('disabled',false);
            }
            window.scrollTo({top:0,behavior:'smooth'});
        },
        error:function(){alert('Server Error!');btn.html(orig).prop('disabled',false);}
    });
});

<?php if($staff): ?>liveCalc();<?php endif; ?>
</script>
</body>
</html>
