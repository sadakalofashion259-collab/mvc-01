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

$staff_list=[];
try{$stmt=$conn->query("SELECT id,staff_name,staff_status FROM staff_info ORDER BY staff_status ASC,staff_name ASC");if($stmt)$staff_list=$stmt->fetchAll(PDO::FETCH_ASSOC);}catch(Exception $e){}

$selected_staff_id=$_POST['staff_id']??'all';
$from_date=$_POST['from_date']??date('Y-m-01');
$to_date=$_POST['to_date']??date('Y-m-d');
$records=[];
$summary=['Present'=>0,'Absent'=>0,'Half'=>0,'Leave'=>0,'Total_Late'=>0];
try{
    $whereClause="WHERE a.attendance_date BETWEEN :fdate AND :tdate";
    $params=[':fdate'=>$from_date,':tdate'=>$to_date];
    if($selected_staff_id!=='all'){$whereClause.=" AND a.staff_id=:sid";$params[':sid']=$selected_staff_id;}
    // ✅ শুধু Active staff-এর attendance দেখাবে
$query="SELECT a.*,s.staff_name,s.profile_pic FROM staff_attendance a JOIN staff_info s ON a.staff_id=s.id AND s.staff_status='Active' $whereClause ORDER BY a.attendance_date DESC,s.staff_name ASC";
    $stmt=$conn->prepare($query);$stmt->execute($params);$records=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($records as $row){if(isset($summary[$row['status']]))$summary[$row['status']]++;$summary['Total_Late']+=(int)$row['late_time'];}
}catch(PDOException $e){$error_msg="ডাটাবেস এরর: ".$e->getMessage();}
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Attendance History — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <style>
        .filter-card { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:var(--sk-radius);padding:16px;margin-bottom:16px; }
        .record-item {
            background:var(--sk-card);
            border:1px solid var(--sk-border);
            border-radius:14px;
            padding:12px 14px;
            display:flex; align-items:center; justify-content:space-between;
            margin-bottom:8px;
            transition:all 0.2s;
        }
        .record-item:hover { transform:translateX(4px); border-color:rgba(124,92,252,0.25); }
        .record-left { display:flex;align-items:center;gap:10px;flex:1;min-width:0; }
        .record-left img { width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--sk-border);flex-shrink:0; }
        .record-name { font-size:13px;font-weight:800; }
        .record-date { font-size:10px;color:var(--sk-info);font-weight:700;margin-top:2px; }
        .record-right { text-align:right;flex-shrink:0; }
        .record-note { font-size:9px;background:var(--sk-surface);border:1px solid var(--sk-border);border-radius:8px;padding:3px 8px;color:var(--sk-text-muted);margin-top:4px;display:inline-block;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }

        .summary-grid { display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:16px; }
        @media(max-width:480px){ .summary-grid{ grid-template-columns:repeat(3,1fr); } }

        /* Filter panel */
        #dateFilterPanel { overflow:hidden; }
        .filter-toggle-btn {
            background:var(--sk-surface);border:1px solid var(--sk-border);color:var(--sk-success);
            border-radius:var(--sk-radius-sm);padding:10px 16px;font-size:12px;font-weight:800;
            cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:6px;
        }
        .filter-toggle-btn:hover { background:var(--sk-surface2); }

        .period-chip { background:rgba(124,92,252,0.1);border:1px solid rgba(124,92,252,0.2);border-radius:20px;padding:5px 12px;font-size:10px;font-weight:700;color:var(--sk-primary);text-align:center;margin-top:10px; }

        .empty-state { text-align:center;padding:48px 16px;color:var(--sk-text-muted); }
        .empty-state i { font-size:40px;margin-bottom:12px;display:block;opacity:0.4; }
        .empty-state p { font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px; }
    </style>
</head>
<body>

<nav class="sk-navbar no-print">
    <a href="../../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Back</a>
    <div class="sk-navbar-title">Att. History<span class="sk-navbar-subtitle">Attendance Records</span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>

<div class="page-wrap mt-3">

    <!-- Banner -->
    <div class="sk-banner no-print animate-in">
        <img src="../../Assets/images/banner.jpg" alt="Banner" onerror="this.parentElement.style.background='linear-gradient(135deg,#7C5CFC,#A07AF0)';this.style.display='none'">
    </div>

    <!-- Filter Card -->
    <div class="filter-card no-print animate-in delay-1">
        <form method="POST" id="historyFilterForm">
            <div class="d-flex gap-2 mb-2">
                <div class="flex-grow-1 sk-input-group" style="margin-bottom:0;">
                    <i class="fa fa-user-circle sk-icon"></i>
                    <select name="staff_id" class="sk-input" onchange="document.getElementById('historyFilterForm').submit()">
                        <option value="all">— সকল স্টাফ (All Staff) —</option>
                        <optgroup label="✅ Active Staff">
                            <?php foreach($staff_list as $s): if($s['staff_status']!=='Active') continue; ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $selected_staff_id==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['staff_name']); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="🔒 Inactive">
                            <?php foreach($staff_list as $s): if($s['staff_status']==='Active') continue; ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $selected_staff_id==$s['id']?'selected':''; ?>">[INACTIVE] <?php echo htmlspecialchars($s['staff_name']); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <button type="button" onclick="toggleFilter()" class="filter-toggle-btn"><i class="fa fa-sliders"></i> Filter</button>
            </div>
            <div id="dateFilterPanel" style="display:none;">
                <hr style="border-color:var(--sk-border);margin:10px 0;">
                <div class="row g-2">
                    <div class="col-5">
                        <label class="sk-label">From</label>
                        <input type="date" name="from_date" class="sk-input" value="<?php echo htmlspecialchars($from_date); ?>">
                    </div>
                    <div class="col-5">
                        <label class="sk-label">To</label>
                        <input type="date" name="to_date" class="sk-input" value="<?php echo htmlspecialchars($to_date); ?>">
                    </div>
                    <div class="col-2 d-flex align-items-end">
                        <button type="submit" class="btn-app-primary w-100" style="padding:11px 8px;font-size:13px;"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <div class="period-chip"><i class="fa fa-calendar me-1"></i><?php echo date('d M Y',strtotime($from_date)); ?> → <?php echo date('d M Y',strtotime($to_date)); ?></div>
    </div>

    <?php if(isset($error_msg)): ?>
    <div class="sk-alert sk-alert-danger mb-3"><i class="fa fa-triangle-exclamation"></i><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="summary-grid animate-in delay-2">
        <div class="stat-chip stat-chip-success"><div class="val" style="color:var(--sk-success);"><?php echo $summary['Present']; ?></div><div class="lbl">উপস্থিত</div></div>
        <div class="stat-chip stat-chip-danger"><div class="val" style="color:var(--sk-danger);"><?php echo $summary['Absent']; ?></div><div class="lbl">অনুপস্থিত</div></div>
        <div class="stat-chip stat-chip-warning"><div class="val" style="color:var(--sk-warning);"><?php echo $summary['Half']; ?></div><div class="lbl">হাফ ডে</div></div>
        <div class="stat-chip stat-chip-info"><div class="val" style="color:var(--sk-info);"><?php echo $summary['Leave']; ?></div><div class="lbl">ছুটি</div></div>
        <div class="stat-chip stat-chip-danger"><div class="val" style="color:var(--sk-danger);font-size:15px;"><?php echo $summary['Total_Late']; ?></div><div class="lbl">Late (min)</div></div>
    </div>

    <!-- Records List -->
    <?php if(count($records)>0): ?>
    <div class="print-area animate-in delay-3">
        <?php
        $badge_map=['Present'=>'present','Absent'=>'absent','Half'=>'half','Leave'=>'leave'];
        $label_map=['Present'=>'উপস্থিত','Absent'=>'অনুপস্থিত','Half'=>'হাফ ডে','Leave'=>'ছুটি'];
        foreach($records as $row):
            $pic=(!empty($row['profile_pic'])&&file_exists("../../Uploads/profiles/".$row['profile_pic'])&&$row['profile_pic']!=='default.png')?"../../Uploads/profiles/".$row['profile_pic']:"../../Uploads/profiles/default.png";
            $b=$badge_map[$row['status']]??'primary';
            $l=$label_map[$row['status']]??$row['status'];
        ?>
        <div class="record-item">
            <div class="record-left">
                <img src="<?php echo $pic; ?>" onerror="this.src='../../Uploads/profiles/default.png'">
                <div style="min-width:0;">
                    <div class="record-name"><?php echo htmlspecialchars($row['staff_name']); ?></div>
                    <div class="record-date"><i class="fa fa-calendar-day me-1"></i><?php echo date('d M Y — D',strtotime($row['attendance_date'])); ?></div>
                    <?php if(!empty($row['leave_note'])): ?><div class="record-note"><i class="fa fa-comment-dots me-1"></i><?php echo htmlspecialchars($row['leave_note']); ?></div><?php endif; ?>
                </div>
            </div>
            <div class="record-right">
                <span class="sk-badge sk-badge-<?php echo $b; ?>"><?php echo $l; ?></span>
                <?php if($row['late_time']>0): ?>
                <div style="font-size:10px;font-weight:800;color:var(--sk-danger);margin-top:4px;"><i class="fa fa-clock me-1"></i><?php echo $row['late_time']; ?>m late</div>
                <?php endif; ?>
                <div style="font-size:9px;color:var(--sk-text-muted);margin-top:2px;">By: <?php echo htmlspecialchars($row['entry_by']); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-3 mb-3 no-print">
        <button onclick="window.print()" class="btn-app-danger" style="padding:10px 28px;font-size:13px;">
            <i class="fa fa-print me-2"></i>Print Report
        </button>
    </div>
    <?php else: ?>
    <div class="empty-state animate-in delay-3">
        <i class="fa fa-folder-open"></i>
        <p>কোনো রেকর্ড পাওয়া যায়নি</p>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){const t=localStorage.getItem('sk_theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);document.addEventListener('DOMContentLoaded',function(){const i=document.getElementById('themeIcon');if(i)i.className=t==='dark'?'fas fa-sun':'fas fa-moon';});})();
function toggleTheme(){const c=document.documentElement.getAttribute('data-bs-theme');const n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-bs-theme',n);localStorage.setItem('sk_theme',n);document.getElementById('themeIcon').className=n==='dark'?'fas fa-sun':'fas fa-moon';}
function toggleFilter(){const p=document.getElementById('dateFilterPanel');p.style.display=p.style.display==='none'?'block':'none';}
</script>
</body>
</html>
