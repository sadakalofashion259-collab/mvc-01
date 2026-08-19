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

$selected_staff_id=$_POST['staff_id']??'all';
$from_date=$_POST['from_date']??date('Y-m-01');
$to_date=$_POST['to_date']??date('Y-m-d');
$staff_list=[];
try{$staff_list=$conn->query("SELECT id,staff_name FROM staff_info WHERE staff_status='Active' ORDER BY staff_name ASC")->fetchAll(PDO::FETCH_ASSOC);}catch(Exception $e){}
$data=[];$total_expense=0;
try{
    $where="WHERE e.expense_date BETWEEN :fdate AND :tdate";
    $params=[':fdate'=>$from_date,':tdate'=>$to_date];
    if($selected_staff_id!=='all'){$where.=" AND e.staff_id=:sid";$params[':sid']=$selected_staff_id;}
    // ✅ শুধু Active staff-এর expense দেখাবে
    $history=$conn->prepare("SELECT e.*,s.staff_name,s.profile_pic FROM staff_expenses e JOIN staff_info s ON e.staff_id=s.id AND s.staff_status='Active' $where ORDER BY e.expense_date DESC,e.id DESC");
    $history->execute($params);
    $data=$history->fetchAll(PDO::FETCH_ASSOC);
    $total_expense=array_sum(array_column($data,'amount'));
}catch(Exception $e){$error_msg="ডাটাবেস এরর: ".$e->getMessage();}
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Expense History — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <style>
        .total-banner {
            background:linear-gradient(135deg,rgba(255,74,106,0.15),rgba(204,17,51,0.08));
            border:1px solid rgba(255,74,106,0.25);
            border-radius:var(--sk-radius);
            padding:20px;
            display:flex;align-items:center;justify-content:space-between;
            margin-bottom:16px;
        }
        .total-label { font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:1px;color:var(--sk-danger);margin-bottom:4px; }
        .total-amount { font-size:30px;font-weight:900;color:var(--sk-danger); }
        .total-icon { width:52px;height:52px;background:rgba(255,74,106,0.15);border:1px solid rgba(255,74,106,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--sk-danger); }

        .exp-item {
            background:var(--sk-card);border:1px solid var(--sk-border);border-radius:14px;
            padding:12px 14px;display:flex;align-items:center;justify-content:space-between;
            margin-bottom:8px;transition:all 0.2s;
        }
        .exp-item:hover { transform:translateX(4px);border-color:rgba(255,74,106,0.3); }
        .exp-left { display:flex;align-items:center;gap:10px;flex:1;min-width:0; }
        .exp-left img { width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--sk-border);flex-shrink:0; }
        .exp-name { font-size:13px;font-weight:800; }
        .exp-date { font-size:10px;color:var(--sk-info);font-weight:700;margin-top:2px; }
        .exp-type { display:inline-block;background:var(--sk-surface);border:1px solid var(--sk-border);border-radius:8px;padding:2px 8px;font-size:9px;font-weight:900;text-transform:uppercase;color:var(--sk-text-muted);margin-top:3px; }
        .exp-amount { font-size:16px;font-weight:900;color:var(--sk-danger);white-space:nowrap; }
        .exp-desc { font-size:9px;background:var(--sk-surface);border:1px solid var(--sk-border);border-radius:8px;padding:2px 8px;color:var(--sk-text-muted);margin-top:4px;display:inline-block;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }

        .filter-card { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:var(--sk-radius);padding:16px;margin-bottom:16px; }
        .filter-toggle-btn { background:var(--sk-surface);border:1px solid var(--sk-border);color:var(--sk-danger);border-radius:var(--sk-radius-sm);padding:10px 16px;font-size:12px;font-weight:800;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:6px; }
        .period-chip { background:rgba(255,74,106,0.08);border:1px solid rgba(255,74,106,0.15);border-radius:20px;padding:5px 12px;font-size:10px;font-weight:700;color:var(--sk-danger);text-align:center;margin-top:10px; }
        .empty-state { text-align:center;padding:48px 16px;color:var(--sk-text-muted); }
        .empty-state i { font-size:40px;margin-bottom:12px;display:block;opacity:0.4; }
    </style>
</head>
<body>

<nav class="sk-navbar no-print">
    <a href="../../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Back</a>
    <div class="sk-navbar-title">Exp. History<span class="sk-navbar-subtitle">Expense Records</span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>

<div class="page-wrap mt-3">

    <div class="sk-banner no-print animate-in">
        <img src="../../Assets/images/banner.jpg" alt="Banner" onerror="this.parentElement.style.background='linear-gradient(135deg,#FF4A6A,#CC1133)';this.style.display='none'">
    </div>

    <!-- Filter -->
    <div class="filter-card no-print animate-in delay-1">
        <form method="POST" id="historyFilterForm">
            <div class="d-flex gap-2 mb-2">
                <div class="flex-grow-1 sk-input-group" style="margin-bottom:0;">
                    <i class="fa fa-user-circle sk-icon"></i>
                    <select name="staff_id" class="sk-input" onchange="document.getElementById('historyFilterForm').submit()">
                        <option value="all">— সকল স্টাফ (All) —</option>
                        <?php foreach($staff_list as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $selected_staff_id==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['staff_name']); ?></option>
                        <?php endforeach; ?>
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

    <?php if(isset($error_msg)): ?><div class="sk-alert sk-alert-danger mb-3"><i class="fa fa-triangle-exclamation"></i><?php echo $error_msg; ?></div><?php endif; ?>

    <!-- Total Banner -->
    <div class="total-banner animate-in delay-2">
        <div>
            <div class="total-label"><i class="fa fa-chart-pie me-1"></i>মোট খরচের পরিমাণ</div>
            <div class="total-amount">Tk. <?php echo number_format($total_expense,2); ?></div>
            <div style="font-size:10px;color:rgba(255,74,106,0.6);font-weight:700;margin-top:2px;"><?php echo count($data); ?> টি রেকর্ড</div>
        </div>
        <div class="total-icon"><i class="fa fa-hand-holding-dollar"></i></div>
    </div>

    <!-- Records -->
    <?php if(count($data)>0): ?>
    <div class="print-area animate-in delay-3">
        <?php foreach($data as $row):
            $pic=(!empty($row['profile_pic'])&&file_exists("../../Uploads/profiles/".$row['profile_pic'])&&$row['profile_pic']!=='default.png')?"../../Uploads/profiles/".$row['profile_pic']:"../../Uploads/profiles/default.png";
        ?>
        <div class="exp-item">
            <div class="exp-left">
                <img src="<?php echo $pic; ?>" onerror="this.src='../../Uploads/profiles/default.png'">
                <div style="min-width:0;">
                    <div class="exp-name"><?php echo htmlspecialchars($row['staff_name']); ?></div>
                    <div class="exp-date"><i class="fa fa-calendar-day me-1"></i><?php echo date('d M Y',strtotime($row['expense_date'])); ?></div>
                    <span class="exp-type"><?php echo htmlspecialchars($row['expense_type']); ?></span>
                    <?php if(!empty($row['description'])): ?>
                    <div class="exp-desc" title="<?php echo htmlspecialchars($row['description']); ?>"><i class="fa fa-comment-dots me-1" style="color:var(--sk-info);"></i><?php echo htmlspecialchars($row['description']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div class="exp-amount">Tk. <?php echo number_format($row['amount'],2); ?></div>
                <div style="font-size:9px;color:var(--sk-text-muted);margin-top:3px;">By: <?php echo htmlspecialchars($row['entry_by']); ?></div>
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
        <p style="font-size:13px;font-weight:700;">কোনো রেকর্ড পাওয়া যায়নি</p>
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
