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
$staffs=$conn->query("
    SELECT s.id,s.staff_name,s.staff_phone,s.staff_salary,s.staff_join_date,s.profile_pic,
        COALESCE(exp_sub.total_exp,0) AS total_expenses,
        COALESCE(att_sub.present,0) AS lt_present,
        COALESCE(att_sub.absent,0) AS lt_absent,
        COALESCE(att_sub.half,0) AS lt_half,
        (SELECT MAX(settlement_date) FROM staff_settlements WHERE staff_id=s.id) AS last_settlement,
        (SELECT final_balance FROM staff_settlements WHERE staff_id=s.id ORDER BY settlement_date DESC LIMIT 1) AS final_balance,
        (SELECT carry_forward_balance FROM staff_settlements WHERE staff_id=s.id ORDER BY settlement_date DESC LIMIT 1) AS carry_forward
    FROM staff_info s
    LEFT JOIN (SELECT staff_id,SUM(amount) AS total_exp FROM staff_expenses GROUP BY staff_id) exp_sub ON s.id=exp_sub.staff_id
    LEFT JOIN (SELECT staff_id,SUM(status='Present') AS present,SUM(status='Absent') AS absent,SUM(status='Half') AS half FROM staff_attendance GROUP BY staff_id) att_sub ON s.id=att_sub.staff_id
    WHERE s.staff_status='Inactive'
    ORDER BY s.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inactive Profiles — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <style>
        .inactive-card {
            background:var(--sk-card);border:1px solid var(--sk-border);border-radius:18px;
            overflow:hidden;cursor:pointer;transition:all 0.25s;margin-bottom:12px;
        }
        .inactive-card:hover { transform:translateY(-2px);box-shadow:var(--sk-shadow); }
        .inactive-card:active { transform:scale(0.98); }
        .ic-stripe { height:3px;width:100%;background:linear-gradient(90deg,#4a4a6a,#2a2a4a); }
        .ic-body { padding:14px; }
        .ic-top { display:flex;align-items:flex-start;gap:12px; }
        .ic-avatar-wrap { position:relative;flex-shrink:0; }
        .ic-avatar { width:56px;height:56px;border-radius:14px;object-fit:cover;border:2px solid var(--sk-border);filter:grayscale(0.65); }
        .ic-off-badge { position:absolute;top:-6px;right:-6px;background:var(--sk-danger);color:#fff;font-size:7px;font-weight:900;padding:2px 5px;border-radius:8px;border:1.5px solid var(--sk-card); }
        .ic-name { font-size:14px;font-weight:900;text-transform:uppercase;color:var(--sk-text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
        .ic-phone { font-size:10px;color:var(--sk-text-muted);margin-top:2px; }
        .ic-meta { font-size:9px;color:var(--sk-text-muted);margin-top:3px; }
        .ic-salary { font-size:12px;font-weight:900;color:var(--sk-text-secondary); }
        .ic-sal-lbl { font-size:8px;color:var(--sk-text-muted);text-align:right; }
        .ic-stats { display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--sk-border);border-radius:10px;overflow:hidden;margin:12px 0; }
        .ic-stat { background:var(--sk-card);text-align:center;padding:7px 4px; }
        .ic-stat .v { font-size:15px;font-weight:900;color:var(--sk-text-muted); }
        .ic-stat .l { font-size:8px;font-weight:800;text-transform:uppercase;color:var(--sk-text-muted); }
        .ic-footer { display:flex;align-items:center;justify-content:space-between;background:var(--sk-surface);border-radius:10px;padding:9px 12px; }
        .ic-footer-label { font-size:9px;font-weight:900;text-transform:uppercase;color:var(--sk-text-muted); }
        .ic-footer-val { font-size:14px;font-weight:900; }
        .carry-bar { background:rgba(255,179,71,0.08);border:1px solid rgba(255,179,71,0.2);border-radius:10px;padding:7px 12px;display:flex;justify-content:space-between;align-items:center;margin-top:8px; }
        .search-wrap { position:relative;margin-bottom:16px; }
        .search-wrap .fa { position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--sk-text-muted); }
        .search-wrap input { padding-left:40px!important; }
        .empty-state { text-align:center;padding:48px 16px;color:var(--sk-text-muted); }
        .empty-state i { font-size:40px;margin-bottom:12px;display:block;opacity:0.3; }
        .no-result { display:none;text-align:center;padding:40px 16px;color:var(--sk-text-muted); }
        .no-result i { font-size:36px;opacity:0.3;margin-bottom:10px;display:block; }
    </style>
</head>
<body>

<nav class="sk-navbar">
    <a href="../../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Dashboard</a>
    <div class="sk-navbar-title">Inactive Profiles<span class="sk-navbar-subtitle"><?php echo count($staffs); ?> Archived</span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>

<div class="page-wrap mt-3">

    <!-- Search -->
    <div class="search-wrap animate-in">
        <i class="fa fa-search"></i>
        <input type="search" id="searchInput" class="sk-input" placeholder="Staff নাম লিখে খুঁজুন..." oninput="filterStaff()">
    </div>

    <!-- Count badge -->
    <div style="margin-bottom:14px;" class="animate-in delay-1">
        <span class="sk-badge sk-badge-inactive" style="font-size:11px;padding:6px 14px;">
            <i class="fa fa-archive me-1"></i><?php echo count($staffs); ?> জন Inactive / Archived Staff
        </span>
    </div>

    <div id="staffList" class="animate-in delay-2">
    <?php if(empty($staffs)): ?>
        <div class="empty-state sk-card"><i class="fa fa-box-open"></i><p style="font-size:13px;font-weight:700;">কোনো Inactive Staff নেই</p></div>
    <?php else: ?>
    <?php foreach($staffs as $s):
        $pic=(!empty($s['profile_pic'])&&$s['profile_pic']!=='default.png'&&file_exists("../../Uploads/profiles/".$s['profile_pic']))?"../../Uploads/profiles/".$s['profile_pic']:"../../Uploads/profiles/default.png";
        $carry=floatval($s['carry_forward']??0);
        $final=floatval($s['final_balance']??0);
        $settled_date=$s['last_settlement']?date('d M Y',strtotime($s['last_settlement'])):'N/A';
        $fin_color=$final<0?'var(--sk-danger)':'var(--sk-success)';
        $fin_label=$final<0?'কোম্পানি পেয়েছিল':'Staff পেয়েছিল';
    ?>
    <div class="inactive-card staff-item" data-name="<?php echo strtolower(htmlspecialchars($s['staff_name'])); ?>" onclick="window.location.href='InactiveHistory.php?id=<?php echo $s['id']; ?>'">
        <div class="ic-stripe"></div>
        <div class="ic-body">
            <div class="ic-top">
                <div class="ic-avatar-wrap">
                    <img src="<?php echo $pic; ?>" class="ic-avatar" onerror="this.src='../../Uploads/profiles/default.png'">
                    <span class="ic-off-badge">OFF</span>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="ic-name"><?php echo htmlspecialchars($s['staff_name']); ?></div>
                    <div class="ic-phone"><i class="fa fa-phone me-1"></i><?php echo htmlspecialchars($s['staff_phone']); ?></div>
                    <div class="ic-meta"><i class="fa fa-calendar me-1"></i>Joined: <?php echo date('d M Y',strtotime($s['staff_join_date'])); ?></div>
                    <div class="ic-meta"><i class="fa fa-circle-check me-1" style="color:var(--sk-text-muted);"></i>Settled: <strong style="color:var(--sk-text-secondary);"><?php echo $settled_date; ?></strong></div>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <div class="ic-salary">Tk.<?php echo number_format($s['staff_salary'],0); ?></div>
                    <div class="ic-sal-lbl">/month</div>
                    <div style="margin-top:8px;font-size:12px;color:var(--sk-text-muted);"><i class="fa fa-clock-rotate-left"></i></div>
                </div>
            </div>

            <!-- Stats -->
            <div class="ic-stats">
                <div class="ic-stat"><div class="v"><?php echo $s['lt_present']; ?></div><div class="l">Present</div></div>
                <div class="ic-stat"><div class="v"><?php echo $s['lt_absent']; ?></div><div class="l">Absent</div></div>
                <div class="ic-stat"><div class="v"><?php echo $s['lt_half']; ?></div><div class="l">Half</div></div>
                <div class="ic-stat"><div class="v" style="font-size:11px;"><?php echo number_format($s['total_expenses'],0); ?></div><div class="l">Expense</div></div>
            </div>

            <!-- Final Balance -->
            <div class="ic-footer">
                <div>
                    <div class="ic-footer-label">চূড়ান্ত ব্যালেন্স</div>
                    <div style="font-size:10px;color:var(--sk-text-muted);font-weight:700;"><?php echo $fin_label; ?></div>
                </div>
                <div class="ic-footer-val" style="color:<?php echo $fin_color; ?>;">Tk. <?php echo number_format(abs($final),2); ?></div>
            </div>

            <!-- Carry Forward -->
            <?php if($carry>0): ?>
            <div class="carry-bar">
                <span style="font-size:10px;font-weight:800;color:var(--sk-warning);"><i class="fa fa-forward me-1"></i>ঋণ বাবদ Carry Forward</span>
                <span style="font-size:12px;font-weight:900;color:var(--sk-warning);">Tk. <?php echo number_format($carry,2); ?></span>
            </div>
            <?php endif; ?>

            <div style="text-align:right;margin-top:8px;font-size:9px;color:var(--sk-text-muted);font-weight:700;">
                <i class="fa fa-eye me-1"></i>ইতিহাস দেখতে Tap করুন
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <div id="noResult" class="no-result">
        <i class="fa fa-magnifying-glass"></i>
        <p style="font-size:13px;font-weight:700;">কোনো Staff পাওয়া যায়নি</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){const t=localStorage.getItem('sk_theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);document.addEventListener('DOMContentLoaded',function(){const i=document.getElementById('themeIcon');if(i)i.className=t==='dark'?'fas fa-sun':'fas fa-moon';});})();
function toggleTheme(){const c=document.documentElement.getAttribute('data-bs-theme');const n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-bs-theme',n);localStorage.setItem('sk_theme',n);document.getElementById('themeIcon').className=n==='dark'?'fas fa-sun':'fas fa-moon';}
function filterStaff(){
    const q=document.getElementById('searchInput').value.toLowerCase();
    const items=document.querySelectorAll('.staff-item');let vis=0;
    items.forEach(el=>{const show=!q||el.dataset.name.includes(q);el.style.display=show?'block':'none';if(show)vis++;});
    document.getElementById('noResult').style.display=vis===0?'block':'none';
}
</script>
</body>
</html>
