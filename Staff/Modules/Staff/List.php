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

$query = "
    SELECT s.*,
        COALESCE(SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END),0) as total_present,
        COALESCE(SUM(CASE WHEN a.status='Absent'  THEN 1 ELSE 0 END),0) as total_absent,
        COALESCE(SUM(CASE WHEN a.status='Half'    THEN 1 ELSE 0 END),0) as total_half,
        COALESCE(SUM(CASE WHEN a.status='Leave'   THEN 1 ELSE 0 END),0) as total_leave,
        COALESCE(SUM(a.late_time),0) as total_late_mins
    FROM staff_info s
    LEFT JOIN staff_attendance a ON s.id=a.staff_id
    GROUP BY s.id
    ORDER BY s.staff_status ASC, s.staff_name ASC";
$stmt = $conn->query($query);
$staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$exp_stmt = $conn->query("SELECT staff_id, SUM(amount) as total_expense FROM staff_expenses WHERE amount>0 GROUP BY staff_id");
$expenses_data = [];
while($row=$exp_stmt->fetch(PDO::FETCH_ASSOC)) $expenses_data[$row['staff_id']]=$row['total_expense'];
foreach($staffs as $k=>$s) $staffs[$k]['total_lifetime_expense']=isset($expenses_data[$s['id']])?$expenses_data[$s['id']]:0;

$active_staffs   = array_filter($staffs, fn($s)=>$s['staff_status']==='Active');
$inactive_staffs = array_filter($staffs, fn($s)=>$s['staff_status']!=='Active');
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Staff List — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <style>
        .staff-card {
            background: var(--sk-card);
            border: 1px solid var(--sk-border);
            border-radius: 18px;
            overflow: hidden;
            transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1);
            cursor: pointer;
        }
        .staff-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.35); border-color: rgba(124,92,252,0.3); }
        .staff-card:active { transform: scale(0.97); }

        .card-cover {
            height: 56px;
            background: linear-gradient(135deg, var(--sk-surface), var(--sk-surface2));
            position: relative;
        }
        .card-cover-active   { background: linear-gradient(135deg,#1a0940,#2d1a6e); }
        .card-cover-inactive { background: linear-gradient(135deg,#1a1a2e,#2a2a3e); filter: grayscale(0.5); }

        .card-body-inner { padding: 0 14px 14px; text-align: center; }
        .card-avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--sk-card);
            box-shadow: 0 4px 16px rgba(0,0,0,0.3);
            margin-top: -32px;
            display: block;
            margin-left: auto; margin-right: auto;
            background: var(--sk-surface);
        }
        .card-name {
            font-size: 13px; font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin: 8px 0 2px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .card-phone { font-size: 10px; color: var(--sk-text-secondary); font-weight: 600; margin-bottom: 10px; }

        .card-stats { display: flex; gap: 6px; justify-content: center; margin-bottom: 10px; }
        .card-stat {
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 10px; font-weight: 900;
            border: 1px solid transparent;
        }

        .card-actions { display: flex; gap: 6px; }
        .card-action-btn {
            flex: 1;
            border-radius: 10px;
            padding: 8px 4px;
            font-size: 10px; font-weight: 800;
            text-align: center;
            cursor: pointer;
            text-decoration: none !important;
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 4px;
        }
        .card-action-bio { background: var(--sk-surface); border: 1px solid var(--sk-border); color: var(--sk-text-secondary); }
        .card-action-bio:hover { background: var(--sk-surface2); color: var(--sk-text); }
        .card-action-profile { background: rgba(0,206,136,0.1); border: 1px solid rgba(0,206,136,0.2); color: var(--sk-success); }
        .card-action-profile:hover { background: rgba(0,206,136,0.18); }

        /* Search */
        .search-wrap { position: relative; margin-bottom: 20px; }
        .search-wrap .fa { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--sk-text-muted); }
        .search-wrap input { padding-left: 40px !important; }

        /* Section Header */
        .section-head {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 14px; margin-top: 4px;
        }
        .section-head-badge {
            display: flex; align-items: center; gap: 6px;
            background: var(--sk-surface);
            border: 1px solid var(--sk-border);
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 11px; font-weight: 800;
        }
        .pulse-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .pulse-green { background: var(--sk-success); box-shadow: 0 0 0 3px rgba(0,206,136,0.2); animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{box-shadow:0 0 0 3px rgba(0,206,136,0.2)} 50%{box-shadow:0 0 0 6px rgba(0,206,136,0.1)} }
        .section-toggle-btn { font-size: 11px; font-weight: 800; color: var(--sk-primary); background: none; border: none; cursor: pointer; text-decoration: underline; }

        /* Modal */
        .modal-cover { height: 80px; background: linear-gradient(135deg,#7C5CFC,#A07AF0); position: relative; }
        .modal-avatar {
            width: 80px; height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--sk-card);
            position: absolute;
            bottom: -40px; left: 50%; transform: translateX(-50%);
            background: var(--sk-surface);
        }
        .modal-stat-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 1px; background: var(--sk-border); border-radius: 10px; overflow: hidden; margin-top: 12px; }
        .modal-stat-cell { background: var(--sk-surface); text-align: center; padding: 10px 6px; }
        .modal-stat-val { font-size: 18px; font-weight: 900; }
        .modal-stat-lbl { font-size: 9px; font-weight: 800; color: var(--sk-text-muted); text-transform: uppercase; }

        /* inactive grayscale */
        .staff-card-inactive .card-avatar { filter: grayscale(0.7); }
        .staff-card-inactive .card-name { color: var(--sk-text-secondary); }

        [data-bs-theme="light"] .card-cover-active { background: linear-gradient(135deg,#ede9ff,#d8ceff); }
        [data-bs-theme="light"] .card-cover-inactive { background: linear-gradient(135deg,#f5f5f5,#e8e8e8); }
    </style>
</head>
<body>

<nav class="sk-navbar">
    <a href="../../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Back</a>
    <div class="sk-navbar-title">Staff Profiles<span class="sk-navbar-subtitle">Bio-Data & Records</span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>

<div class="page-wrap mt-3">

    <!-- Search -->
    <div class="search-wrap animate-in">
        <i class="fa fa-search"></i>
        <input type="search" id="searchInput" class="sk-input" placeholder="Staff খুঁজুন নামে..." oninput="filterCards()">
    </div>

    <!-- Active Staff -->
    <?php if(!empty($active_staffs)): ?>
    <div class="section-head animate-in delay-1">
        <div class="section-head-badge">
            <span class="pulse-dot pulse-green"></span>
            <span style="color:var(--sk-success);">Active Staff</span>
            <span style="font-size:12px;color:var(--sk-success);"><?php echo count($active_staffs); ?> জন</span>
        </div>
    </div>
    <div class="row g-3 mb-4" id="activeGrid">
        <?php foreach($active_staffs as $s):
            $pic = (!empty($s['profile_pic'])&&file_exists("../../Uploads/profiles/".$s['profile_pic'])) ? "../../Uploads/profiles/".$s['profile_pic'] : "../../Uploads/profiles/default.png";
            $json = htmlspecialchars(json_encode($s),ENT_QUOTES,'UTF-8');
        ?>
        <div class="col-6 col-sm-4 col-md-3 staff-card-wrap" data-name="<?php echo strtolower(htmlspecialchars($s['staff_name'])); ?>">
            <div class="staff-card" onclick="openProfile(<?php echo $json; ?>,'<?php echo $pic; ?>')">
                <div class="card-cover card-cover-active">
                    <?php if($s['email_verified']==1): ?>
                    <span style="position:absolute;top:8px;right:8px;" class="sk-badge sk-badge-primary" style="font-size:8px;"><i class="fa fa-check-circle"></i> Verified</span>
                    <?php else: ?>
                    <span style="position:absolute;top:8px;right:8px;" class="sk-badge sk-badge-warning" style="font-size:8px;">
                        ⚠ Pending
                        <button onclick="event.stopPropagation();forceVerify(<?php echo $s['id']; ?>,'<?php echo addslashes($s['staff_name']); ?>')" style="margin-left:4px;background:#10b981;border:none;color:#fff;font-size:7px;font-weight:900;padding:1px 5px;border-radius:3px;cursor:pointer;">✓ Verify</button>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="card-body-inner">
                    <img src="<?php echo $pic; ?>" class="card-avatar" onerror="this.src='../../Uploads/profiles/default.png'">
                    <div class="card-name"><?php echo htmlspecialchars($s['staff_name']); ?></div>
                    <div class="card-phone"><i class="fa fa-phone me-1" style="color:var(--sk-success);"></i><?php echo htmlspecialchars($s['staff_phone']); ?></div>
                    <div class="card-stats">
                        <span class="card-stat sk-badge-present">P:<?php echo $s['total_present']; ?></span>
                        <span class="card-stat sk-badge-absent">A:<?php echo $s['total_absent']; ?></span>
                        <span class="card-stat sk-badge-leave">L:<?php echo $s['total_leave']; ?></span>
                    </div>
                    <div class="card-actions">
                        <button onclick="event.stopPropagation();openProfile(<?php echo $json; ?>,'<?php echo $pic; ?>')" class="card-action-btn card-action-bio"><i class="fa fa-id-card"></i> Bio</button>
                        <a href="ProfileView.php?id=<?php echo $s['id']; ?>" onclick="event.stopPropagation()" class="card-action-btn card-action-profile"><i class="fa fa-chart-line"></i> Profile</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Inactive Staff -->
    <?php if(!empty($inactive_staffs)): ?>
    <div class="section-head animate-in delay-2">
        <div class="section-head-badge">
            <span class="pulse-dot" style="background:var(--sk-text-muted);"></span>
            <span style="color:var(--sk-text-muted);">Inactive / Archived</span>
            <span style="font-size:12px;color:var(--sk-text-muted);"><?php echo count($inactive_staffs); ?> জন</span>
        </div>
        <button class="section-toggle-btn" onclick="toggleInactive()" id="inactiveToggleBtn">দেখান</button>
    </div>
    <div id="inactiveGrid" class="row g-3 mb-4" style="display:none;">
        <?php foreach($inactive_staffs as $s):
            $pic = (!empty($s['profile_pic'])&&file_exists("../../Uploads/profiles/".$s['profile_pic'])) ? "../../Uploads/profiles/".$s['profile_pic'] : "../../Uploads/profiles/default.png";
            $json = htmlspecialchars(json_encode($s),ENT_QUOTES,'UTF-8');
        ?>
        <div class="col-6 col-sm-4 col-md-3">
            <div class="staff-card staff-card-inactive" onclick="openProfile(<?php echo $json; ?>,'<?php echo $pic; ?>')">
                <div class="card-cover card-cover-inactive" style="position:relative;">
                    <span style="position:absolute;top:8px;left:8px;font-size:8px;background:rgba(150,150,180,0.2);color:var(--sk-text-muted);border:1px solid rgba(150,150,180,0.2);border-radius:10px;padding:2px 8px;font-weight:900;text-transform:uppercase;">INACTIVE</span>
                </div>
                <div class="card-body-inner">
                    <img src="<?php echo $pic; ?>" class="card-avatar" style="filter:grayscale(0.6);" onerror="this.src='../../Uploads/profiles/default.png'">
                    <div class="card-name" style="color:var(--sk-text-muted);"><?php echo htmlspecialchars($s['staff_name']); ?></div>
                    <div class="card-phone"><i class="fa fa-phone me-1"></i><?php echo htmlspecialchars($s['staff_phone']); ?></div>
                    <div class="card-stats">
                        <span class="card-stat" style="background:var(--sk-surface);border-color:var(--sk-border);color:var(--sk-text-muted);">P:<?php echo $s['total_present']; ?></span>
                        <span class="card-stat" style="background:var(--sk-surface);border-color:var(--sk-border);color:var(--sk-text-muted);">A:<?php echo $s['total_absent']; ?></span>
                    </div>
                    <a href="InactiveHistory.php?id=<?php echo $s['id']; ?>" onclick="event.stopPropagation()" class="card-action-btn card-action-bio w-100 mt-1" style="font-size:10px;"><i class="fa fa-clock-rotate-left"></i> Full History</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Profile Modal -->
<div class="sk-modal-backdrop" id="profileModal">
    <div class="sk-modal-box" style="max-width:420px;">
        <div class="modal-cover" id="modalCover">
            <img id="modalImg" src="" class="modal-avatar" onerror="this.src='../../Uploads/profiles/default.png'">
            <div id="modalStatusBadge" style="position:absolute;top:10px;left:12px;"></div>
            <button onclick="closeProfile()" style="position:absolute;top:10px;right:12px;" class="sk-modal-close"><i class="fa fa-times"></i></button>
        </div>
        <div style="padding:50px 20px 20px;">
            <div class="text-center mb-3">
                <div id="modalName" style="font-size:18px;font-weight:900;text-transform:uppercase;"></div>
                <div style="display:flex;justify-content:center;gap:16px;margin-top:4px;font-size:11px;color:var(--sk-text-muted);">
                    <span><i class="fa fa-phone" style="color:var(--sk-success);"></i> <span id="modalPhone"></span></span>
                    <span><i class="fa fa-envelope" style="color:var(--sk-info);"></i> <span id="modalEmail" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;display:inline-block;vertical-align:bottom;"></span></span>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="stat-chip">
                        <div class="lbl">Joining Date</div>
                        <div style="font-size:12px;font-weight:800;margin-top:4px;" id="modalJoinDate"></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-chip stat-chip-success">
                        <div class="lbl">Basic Salary</div>
                        <div style="font-size:13px;font-weight:900;color:var(--sk-success);margin-top:4px;" id="modalSalary"></div>
                    </div>
                </div>
            </div>

            <div class="modal-stat-row">
                <div class="modal-stat-cell"><div class="modal-stat-val" id="modalP" style="color:var(--sk-success);">0</div><div class="modal-stat-lbl">Present</div></div>
                <div class="modal-stat-cell"><div class="modal-stat-val" id="modalA" style="color:var(--sk-danger);">0</div><div class="modal-stat-lbl">Absent</div></div>
                <div class="modal-stat-cell"><div class="modal-stat-val" id="modalH" style="color:var(--sk-warning);">0</div><div class="modal-stat-lbl">Half</div></div>
                <div class="modal-stat-cell"><div class="modal-stat-val" id="modalL" style="color:var(--sk-info);">0</div><div class="modal-stat-lbl">Leave</div></div>
            </div>
            <div style="background:rgba(255,74,106,0.07);border:1px solid rgba(255,74,106,0.15);border-radius:10px;padding:8px 12px;text-align:center;font-size:11px;font-weight:800;color:var(--sk-danger);margin-top:8px;">
                <i class="fa fa-clock me-1"></i>মোট লেট: <span id="modalLate">0 Min</span>
            </div>

            <div class="row g-2 mt-2">
                <div class="col-6">
                    <div class="stat-chip stat-chip-danger">
                        <div class="lbl">Total Expenses</div>
                        <div style="font-size:12px;font-weight:900;color:var(--sk-danger);margin-top:4px;" id="modalTotalExp"></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-chip">
                        <div class="lbl">Current Balance</div>
                        <div style="font-size:12px;font-weight:900;margin-top:4px;" id="modalBalance"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const t=localStorage.getItem('sk_theme')||'dark';
    document.documentElement.setAttribute('data-bs-theme',t);
    document.addEventListener('DOMContentLoaded',function(){ const i=document.getElementById('themeIcon'); if(i) i.className=t==='dark'?'fas fa-sun':'fas fa-moon'; });
})();
function toggleTheme(){
    const c=document.documentElement.getAttribute('data-bs-theme');
    const n=c==='dark'?'light':'dark';
    document.documentElement.setAttribute('data-bs-theme',n);
    localStorage.setItem('sk_theme',n);
    document.getElementById('themeIcon').className=n==='dark'?'fas fa-sun':'fas fa-moon';
}

function filterCards(){
    const q=document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.staff-card-wrap').forEach(el=>{
        el.style.display=(!q||el.dataset.name.includes(q))?'':'none';
    });
}

function toggleInactive(){
    const grid=document.getElementById('inactiveGrid');
    const btn=document.getElementById('inactiveToggleBtn');
    const isHidden=grid.style.display==='none';
    grid.style.display=isHidden?'flex':'none';
    if(isHidden) grid.style.flexWrap='wrap';
    btn.textContent=isHidden?'লুকান':'দেখান';
}

function openProfile(d, imgSrc){
    document.getElementById('modalImg').src=imgSrc;
    document.getElementById('modalName').textContent=d.staff_name;
    document.getElementById('modalPhone').textContent=d.staff_phone;
    document.getElementById('modalEmail').textContent=d.staff_email||'N/A';
    document.getElementById('modalJoinDate').textContent=new Date(d.staff_join_date).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});
    document.getElementById('modalSalary').textContent='Tk. '+Number(d.staff_salary).toLocaleString('en-US',{minimumFractionDigits:2});
    document.getElementById('modalP').textContent=d.total_present;
    document.getElementById('modalA').textContent=d.total_absent;
    document.getElementById('modalH').textContent=d.total_half;
    document.getElementById('modalL').textContent=d.total_leave;
    document.getElementById('modalLate').textContent=d.total_late_mins+' Min';
    document.getElementById('modalTotalExp').textContent='- Tk. '+Number(d.total_lifetime_expense).toLocaleString('en-US',{minimumFractionDigits:2});
    const bal=Number(d.running_balance);
    const balEl=document.getElementById('modalBalance');
    balEl.style.color=bal>=0?'var(--sk-warning)':'var(--sk-danger)';
    balEl.textContent=(bal>=0?'(Due) ':'(Adv) ')+'Tk. '+Math.abs(bal).toLocaleString('en-US',{minimumFractionDigits:2});
    const badge=document.getElementById('modalStatusBadge');
    badge.innerHTML=d.staff_status==='Active'
        ?'<span class="sk-badge sk-badge-active"><i class="fa fa-circle" style="font-size:7px;"></i> ACTIVE</span>'
        :'<span class="sk-badge sk-badge-inactive"><i class="fa fa-circle" style="font-size:7px;"></i> INACTIVE</span>';
    document.getElementById('profileModal').classList.add('show');
}
function closeProfile(){ document.getElementById('profileModal').classList.remove('show'); }
document.getElementById('profileModal').addEventListener('click',function(e){ if(e.target===this) closeProfile(); });

function forceVerify(id, name) {
    if (!confirm(name + ' কে এখনই Verified করবেন?')) return;
    fetch('ForceVerifyAjax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=force_verify&staff_id=' + id
    }).then(r => r.json()).then(r => {
        alert(r.status === 'success' ? '✅ ' + r.message : '❌ ' + r.message);
        if (r.status === 'success') location.reload();
    });
}
</script>
</body>
</html>
