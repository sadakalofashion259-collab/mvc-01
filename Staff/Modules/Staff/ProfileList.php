<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if(isset($_SESSION['last_activity'])&&(time()-$_SESSION['last_activity']>1200)){session_unset();session_destroy();echo "<script>alert('Session Expired!');window.location.href='/index.php';</script>";exit;}
$_SESSION['last_activity']=time();
if(!isset($_SESSION['loggedin'])||$_SESSION['loggedin']!==true){echo "<script>window.location.href='../../index.php';</script>";exit;}
$role=$_SESSION['role']??'admin';
if($role!=='admin'&&$role!=='Admin'){echo "<script>alert('Access Denied! Admin Only.');window.location.href='../../index.php';</script>";exit;}
include __DIR__ . '/../../db_connect.php';
date_default_timezone_set('Asia/Dhaka');

$staffs=$conn->query("
    SELECT s.id,s.staff_name,s.staff_phone,s.staff_salary,s.staff_join_date,s.profile_pic,s.email_verified,
        COALESCE(exp_sub.total_exp,0) AS total_expenses,
        COALESCE(att_sub.payable_days,0)*(s.staff_salary/30.0) AS total_earned,
        COALESCE(att_sub.present,0) AS lt_present,
        COALESCE(att_sub.absent,0) AS lt_absent,
        COALESCE(att_sub.half,0) AS lt_half,
        COALESCE(att_sub.late,0) AS lt_late,
        COALESCE(exp_sub.total_exp,0)-COALESCE(att_sub.payable_days,0)*(s.staff_salary/30.0) AS real_balance
    FROM staff_info s
    LEFT JOIN (SELECT staff_id,SUM(amount) AS total_exp FROM staff_expenses GROUP BY staff_id) exp_sub ON s.id=exp_sub.staff_id
    LEFT JOIN (SELECT staff_id,
        SUM(CASE WHEN status='Present' THEN 1.0 WHEN status='Half' THEN 0.5 ELSE 0 END) AS payable_days,
        SUM(status='Present') AS present,SUM(status='Absent') AS absent,SUM(status='Half') AS half,SUM(late_time) AS late
        FROM staff_attendance GROUP BY staff_id) att_sub ON s.id=att_sub.staff_id
    WHERE s.staff_status='Active'
    ORDER BY s.staff_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$total_owed=array_sum(array_map(fn($s)=>max(0,$s['real_balance']),$staffs));
$total_payable=array_sum(array_map(fn($s)=>max(0,-$s['real_balance']),$staffs));
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profiles — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <style>
        .summary-bar { display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px; }
        .sum-chip { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:14px;padding:14px 10px;text-align:center; }
        .sum-chip .val { font-size:18px;font-weight:900;line-height:1; }
        .sum-chip .lbl { font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;color:var(--sk-text-muted);margin-top:4px; }

        .profile-card {
            background:var(--sk-card);
            border:1px solid var(--sk-border);
            border-radius:18px;
            overflow:hidden;
            cursor:pointer;
            transition:all 0.25s cubic-bezier(0.34,1.56,0.64,1);
            margin-bottom:12px;
        }
        .profile-card:hover { transform:translateY(-3px);box-shadow:0 12px 40px rgba(0,0,0,0.35); }
        .profile-card:active { transform:scale(0.98); }

        .balance-stripe { height:4px;width:100%; }
        .stripe-debt { background:linear-gradient(90deg,var(--sk-danger),#CC1133); }
        .stripe-credit { background:linear-gradient(90deg,var(--sk-success),#009966); }
        .stripe-zero { background:var(--sk-border); }

        .pc-body { padding:14px; }
        .pc-top { display:flex;align-items:flex-start;gap:12px; }
        .pc-avatar-wrap { position:relative;flex-shrink:0; }
        .pc-avatar { width:58px;height:58px;border-radius:14px;object-fit:cover;border:2px solid var(--sk-border);display:block; }
        .pc-online { position:absolute;bottom:-3px;right:-3px;width:14px;height:14px;background:var(--sk-success);border-radius:50%;border:2px solid var(--sk-card); }

        .pc-info { flex:1;min-width:0; }
        .pc-name { font-size:14px;font-weight:900;text-transform:uppercase;letter-spacing:0.3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
        .pc-phone { font-size:10px;color:var(--sk-text-muted);margin-top:2px; }
        .pc-join { font-size:9px;color:var(--sk-text-muted);margin-top:3px; }
        .pc-salary { font-size:13px;font-weight:900;color:var(--sk-success);white-space:nowrap; }
        .pc-salary-lbl { font-size:8px;color:var(--sk-text-muted);text-align:right; }

        .pc-stats { display:grid;grid-template-columns:repeat(5,1fr);gap:1px;background:var(--sk-border);border-radius:10px;overflow:hidden;margin:12px 0; }
        .pc-stat { background:var(--sk-card);text-align:center;padding:8px 4px; }
        .pc-stat .v { font-size:16px;font-weight:900; }
        .pc-stat .l { font-size:8px;font-weight:800;text-transform:uppercase;color:var(--sk-text-muted); }

        .pc-balance { display:flex;align-items:center;justify-content:space-between;background:var(--sk-surface);border-radius:12px;padding:10px 14px; }
        .pc-balance-label { font-size:10px;font-weight:900;text-transform:uppercase;color:var(--sk-text-muted); }
        .pc-balance-val { font-size:15px;font-weight:900; }

        .search-wrap { position:relative;margin-bottom:16px; }
        .search-wrap .fa { position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--sk-text-muted); }
        .search-wrap input { padding-left:40px!important; }

        .no-result { text-align:center;padding:40px 16px;color:var(--sk-text-muted);display:none; }
        .no-result i { font-size:36px;margin-bottom:10px;display:block;opacity:0.3; }
    </style>
</head>
<body>

<nav class="sk-navbar">
    <a href="../../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Dashboard</a>
    <div class="sk-navbar-title">Staff Profiles<span class="sk-navbar-subtitle"><?php echo count($staffs); ?> Active</span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>

<div class="page-wrap mt-3">

    <!-- Search -->
    <div class="search-wrap animate-in">
        <i class="fa fa-search"></i>
        <input type="search" id="searchInput" class="sk-input" placeholder="Staff নাম লিখে খুঁজুন..." oninput="filterStaff()">
    </div>

    <!-- Summary Bar -->
    <div class="summary-bar animate-in delay-1">
        <div class="sum-chip">
            <div class="val"><?php echo count($staffs); ?></div>
            <div class="lbl">মোট Staff</div>
        </div>
        <div class="sum-chip" style="border-color:rgba(255,74,106,0.2);">
            <div class="val" style="color:var(--sk-danger);"><?php echo number_format($total_owed,0); ?></div>
            <div class="lbl" style="color:var(--sk-danger);">কোম্পানি পাবে</div>
        </div>
        <div class="sum-chip" style="border-color:rgba(0,206,136,0.2);">
            <div class="val" style="color:var(--sk-success);"><?php echo number_format($total_payable,0); ?></div>
            <div class="lbl" style="color:var(--sk-success);">Staff পাবে</div>
        </div>
    </div>

    <!-- Staff Cards -->
    <div id="staffList" class="animate-in delay-2">
    <?php if(empty($staffs)): ?>
        <div class="no-result" style="display:block;"><i class="fa fa-users-slash"></i><p>কোনো Active Staff নেই</p></div>
    <?php else: ?>
    <?php foreach($staffs as $s):
        $pic=(!empty($s['profile_pic'])&&$s['profile_pic']!=='default.png'&&file_exists("../../Uploads/profiles/".$s['profile_pic']))?"../../Uploads/profiles/".$s['profile_pic']:"../../Uploads/profiles/default.png";
        $balance=floatval($s['real_balance']);
        $debt=$balance>0;
        $days_joined=max(0,floor((time()-strtotime($s['staff_join_date']))/86400));
        $stripe=$debt?'stripe-debt':($balance<0?'stripe-credit':'stripe-zero');
        $bal_color=$debt?'var(--sk-danger)':'var(--sk-success)';
        $bal_label=$debt?'⚠️ কোম্পানি পাবে':'✅ Staff পাবে';
    ?>
    <div class="profile-card staff-item" data-name="<?php echo strtolower($s['staff_name']); ?>" onclick="window.location.href='ProfileView.php?id=<?php echo $s['id']; ?>'">
        <div class="balance-stripe <?php echo $stripe; ?>"></div>
        <div class="pc-body">
            <div class="pc-top">
                <div class="pc-avatar-wrap">
                    <img src="<?php echo $pic; ?>" class="pc-avatar" onerror="this.src='../../Uploads/profiles/default.png'">
                    <span class="pc-online"></span>
                </div>
                <div class="pc-info">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="pc-name"><?php echo htmlspecialchars($s['staff_name']); ?></div>
                        <?php if(!$s['email_verified']): ?><span class="sk-badge sk-badge-warning" style="font-size:8px;flex-shrink:0;">UNVERIFIED</span><?php endif; ?>
                    </div>
                    <div class="pc-phone"><i class="fa fa-phone me-1" style="color:var(--sk-text-muted);"></i><?php echo htmlspecialchars($s['staff_phone']); ?></div>
                    <div class="pc-join"><i class="fa fa-calendar me-1"></i><?php echo date('d M Y',strtotime($s['staff_join_date'])); ?> — <?php echo $days_joined; ?> দিন আগে</div>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <div class="pc-salary">Tk.<?php echo number_format($s['staff_salary'],0); ?></div>
                    <div class="pc-salary-lbl">/month</div>
                </div>
            </div>

            <!-- Stats -->
            <div class="pc-stats">
                <div class="pc-stat"><div class="v" style="color:var(--sk-success);"><?php echo $s['lt_present']; ?></div><div class="l">P</div></div>
                <div class="pc-stat"><div class="v" style="color:var(--sk-danger);"><?php echo $s['lt_absent']; ?></div><div class="l">A</div></div>
                <div class="pc-stat"><div class="v" style="color:var(--sk-warning);"><?php echo $s['lt_half']; ?></div><div class="l">H</div></div>
                <div class="pc-stat"><div class="v" style="color:var(--sk-primary);"><?php echo $s['lt_late']; ?></div><div class="l">Late</div></div>
                <div class="pc-stat"><div class="v" style="color:var(--sk-danger);font-size:12px;"><?php echo number_format($s['total_expenses'],0); ?></div><div class="l">Exp</div></div>
            </div>

            <!-- Balance Footer -->
            <div class="pc-balance">
                <div class="pc-balance-label"><?php echo $bal_label; ?></div>
                <div class="pc-balance-val" style="color:<?php echo $bal_color; ?>;">Tk. <?php echo number_format(abs($balance),2); ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <div id="noResult" class="no-result">
        <i class="fa fa-magnifying-glass"></i>
        <p>কোনো Staff পাওয়া যায়নি</p>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){const t=localStorage.getItem('sk_theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);document.addEventListener('DOMContentLoaded',function(){const i=document.getElementById('themeIcon');if(i)i.className=t==='dark'?'fas fa-sun':'fas fa-moon';});})();
function toggleTheme(){const c=document.documentElement.getAttribute('data-bs-theme');const n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-bs-theme',n);localStorage.setItem('sk_theme',n);document.getElementById('themeIcon').className=n==='dark'?'fas fa-sun':'fas fa-moon';}
function filterStaff(){
    const q=document.getElementById('searchInput').value.toLowerCase();
    const items=document.querySelectorAll('.staff-item');
    let vis=0;
    items.forEach(el=>{const show=!q||el.dataset.name.includes(q);el.style.display=show?'block':'none';if(show)vis++;});
    document.getElementById('noResult').style.display=vis===0?'block':'none';
}
</script>
</body>
</html>
