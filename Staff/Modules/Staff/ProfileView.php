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
$staff_id=intval($_GET['id']??0);
if(!$staff_id){echo "<script>window.location.href='List.php';</script>";exit;}
$stmt=$conn->prepare("SELECT * FROM staff_info WHERE id=? AND staff_status='Active'");$stmt->execute([$staff_id]);$staff=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$staff){echo "<script>window.location.href='List.php';</script>";exit;}
$daily_salary=$staff['staff_salary']/30;
$pic=(!empty($staff['profile_pic'])&&$staff['profile_pic']!=='default.png'&&file_exists("../../Uploads/profiles/".$staff['profile_pic']))?"../../Uploads/profiles/".$staff['profile_pic']:"../../Uploads/profiles/default.png";
$sel_month=$_GET['month']??date('Y-m');
$month_start=$sel_month.'-01';$month_end=date('Y-m-t',strtotime($month_start));
$prev_month=date('Y-m',strtotime($month_start.' -1 month'));$next_month=date('Y-m',strtotime($month_start.' +1 month'));
$month_label=date('F Y',strtotime($month_start));
$att_q=$conn->prepare("SELECT status,late_time,attendance_date,leave_note FROM staff_attendance WHERE staff_id=? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date DESC");$att_q->execute([$staff_id,$month_start,$month_end]);$month_att=$att_q->fetchAll(PDO::FETCH_ASSOC);
$m_present=$m_absent=$m_half=$m_leave=$m_late=0;
foreach($month_att as $a){if($a['status']==='Present')$m_present++;elseif($a['status']==='Absent')$m_absent++;elseif($a['status']==='Half')$m_half++;elseif($a['status']==='Leave')$m_leave++;$m_late+=intval($a['late_time']);}
$exp_q=$conn->prepare("SELECT * FROM staff_expenses WHERE staff_id=? AND expense_date BETWEEN ? AND ? ORDER BY expense_date DESC");$exp_q->execute([$staff_id,$month_start,$month_end]);$month_exp=$exp_q->fetchAll(PDO::FETCH_ASSOC);$m_total_exp=array_sum(array_column($month_exp,'amount'));
$life=$conn->prepare("SELECT COALESCE(att.present,0) as lt_present,COALESCE(att.absent,0) as lt_absent,COALESCE(att.half,0) as lt_half,COALESCE(att.lv_leave,0) as lt_leave,COALESCE(att.late,0) as lt_late,COALESCE(exp.total,0) as lt_expense FROM (SELECT 1) dummy LEFT JOIN (SELECT SUM(status='Present') as present,SUM(status='Absent') as absent,SUM(status='Half') as half,SUM(status='Leave') as lv_leave,SUM(late_time) as late FROM staff_attendance WHERE staff_id=?) att ON 1=1 LEFT JOIN (SELECT SUM(amount) as total FROM staff_expenses WHERE staff_id=?) exp ON 1=1");$life->execute([$staff_id,$staff_id]);$lt=$life->fetch(PDO::FETCH_ASSOC);
$lt_earned=($lt['lt_present']+$lt['lt_half']*0.5)*$daily_salary;$real_balance=$lt['lt_expense']-$lt_earned;
$notes_q=$conn->prepare("SELECT * FROM staff_notes WHERE staff_id=? ORDER BY created_at DESC");$notes_q->execute([$staff_id]);$notes=$notes_q->fetchAll(PDO::FETCH_ASSOC);
$prev_ref=null;if(!empty($staff['previous_staff_id'])){$pr=$conn->prepare("SELECT id,staff_name,staff_join_date FROM staff_info WHERE id=?");$pr->execute([$staff['previous_staff_id']]);$prev_ref=$pr->fetch(PDO::FETCH_ASSOC);}
$months_q=$conn->prepare("SELECT DISTINCT DATE_FORMAT(attendance_date,'%Y-%m') as ym FROM staff_attendance WHERE staff_id=? ORDER BY ym DESC");$months_q->execute([$staff_id]);$available_months=$months_q->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($staff['staff_name']); ?> — Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <style>
        .profile-hero { background:linear-gradient(135deg,#1a0940,#0D0B22);padding:20px 16px 80px;position:relative; }
        [data-bs-theme="light"] .profile-hero { background:linear-gradient(135deg,#5b3fcf,#7C5CFC); }
        .hero-avatar { width:80px;height:80px;border-radius:20px;object-fit:cover;border:3px solid rgba(124,92,252,0.6);box-shadow:0 8px 24px rgba(0,0,0,0.4); }
        .hero-name { font-size:18px;font-weight:900;color:#fff;text-transform:uppercase;letter-spacing:0.5px;margin-top:10px; }
        .hero-phone { font-size:11px;color:rgba(255,255,255,0.6);margin-top:3px; }
        .hero-email { font-size:11px;color:rgba(255,255,255,0.5);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
        .hero-chips { display:flex;gap:6px;flex-wrap:wrap;margin-top:8px; }
        .hero-chip { background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.18);border-radius:20px;padding:3px 10px;font-size:10px;font-weight:700;color:rgba(255,255,255,0.85); }

        /* Hero summary */
        .hero-summary { display:grid;grid-template-columns:repeat(3,1fr);gap:8px;position:absolute;bottom:-36px;left:16px;right:16px; }
        .hs-card { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:14px;padding:12px 8px;text-align:center;box-shadow:var(--sk-shadow-card); }
        .hs-val { font-size:14px;font-weight:900; }
        .hs-lbl { font-size:8px;font-weight:800;text-transform:uppercase;color:var(--sk-text-muted);margin-top:2px; }

        /* Tabs */
        .sk-tabs { display:flex;gap:4px;background:var(--sk-surface);border:1px solid var(--sk-border);border-radius:14px;padding:4px;margin-top:50px; }
        .sk-tab { flex:1;border:none;background:transparent;border-radius:10px;padding:9px 4px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:0.3px;color:var(--sk-text-muted);cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:5px; }
        .sk-tab.active { background:var(--sk-primary);color:#fff;box-shadow:0 3px 12px rgba(124,92,252,0.35); }

        /* Month nav */
        .month-nav { display:flex;align-items:center;justify-content:space-between;background:var(--sk-card);border:1px solid var(--sk-border);border-radius:14px;padding:10px 14px;margin-bottom:14px; }
        .month-nav-btn { background:var(--sk-surface);border:1px solid var(--sk-border);color:var(--sk-text-muted);width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;font-size:14px;transition:all 0.2s; }
        .month-nav-btn:hover { background:var(--sk-primary);border-color:var(--sk-primary);color:#fff; }
        .month-nav-btn.disabled { opacity:0.3;pointer-events:none; }
        .month-select { background:transparent;border:none;color:var(--sk-text);font-weight:800;font-size:14px;text-align:center;outline:none;cursor:pointer; }

        /* Att rows */
        .att-row { display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--sk-border); }
        .att-row:last-child { border-bottom:none; }
        .att-date { font-size:12px;font-weight:800; }
        .att-day { font-size:9px;color:var(--sk-info);font-weight:700;margin-top:2px; }
        .att-note { font-size:9px;color:var(--sk-text-muted);margin-top:2px; }

        /* Expense rows */
        .exp-row { display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--sk-border); }
        .exp-row:last-child { border-bottom:none; }
        .exp-type { font-size:9px;background:var(--sk-surface);border:1px solid var(--sk-border);border-radius:8px;padding:2px 8px;font-weight:900;text-transform:uppercase;color:var(--sk-text-muted);margin-bottom:3px;display:inline-block; }
        .exp-date { font-size:10px;font-weight:700;color:var(--sk-text-muted); }
        .exp-actions { display:flex;gap:6px;align-items:center; }
        .action-icon { background:none;border:none;cursor:pointer;font-size:13px;transition:all 0.2s;padding:4px;border-radius:6px; }
        .action-icon-edit { color:var(--sk-info); } .action-icon-edit:hover { background:rgba(33,150,243,0.1); }
        .action-icon-del { color:var(--sk-danger); } .action-icon-del:hover { background:rgba(255,74,106,0.1); }

        /* Lifetime grid */
        .lt-grid { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
        .lt-card { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:14px;padding:16px; }
        .lt-card-title { font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:0.8px;color:var(--sk-text-muted);margin-bottom:12px;display:flex;align-items:center;gap:6px; }
        .lt-row { display:flex;justify-content:space-between;font-size:12px;margin-bottom:8px; }
        .lt-row:last-child { margin-bottom:0; }

        /* Month breakdown */
        .mb-row { display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--sk-border);text-decoration:none;color:var(--sk-text);transition:background 0.15s; }
        .mb-row:last-child { border-bottom:none; }
        .mb-row:hover { background:rgba(124,92,252,0.05); }

        /* Notes */
        .note-card { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:14px;padding:14px;margin-bottom:10px; }
        .note-meta { font-size:9px;color:var(--sk-text-muted);font-weight:700;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between; }
        .note-text { font-size:13px;line-height:1.6;color:var(--sk-text); }
        .note-photo { width:100%;max-height:180px;object-fit:contain;border-radius:10px;border:1px solid var(--sk-border);margin-top:8px;cursor:pointer; }

        /* Add note */
        .add-note-card { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:14px;padding:16px;margin-bottom:14px; }
        .note-textarea { background:var(--sk-surface)!important;border:1.5px solid var(--sk-border)!important;border-radius:10px!important;color:var(--sk-text)!important;padding:12px!important;resize:none;width:100%;outline:none;font-family:'Nunito',sans-serif;font-size:13px;font-weight:600; }
        .note-textarea:focus { border-color:var(--sk-primary)!important; }

        .photo-options { display:flex;gap:8px;margin:10px 0; }
        .photo-btn { flex:1;background:var(--sk-surface);border:1.5px dashed var(--sk-border);border-radius:10px;padding:10px;font-size:11px;font-weight:800;color:var(--sk-text-muted);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:all 0.2s; }
        .photo-btn:hover { border-color:var(--sk-primary);color:var(--sk-primary); }

        .empty-pane { text-align:center;padding:40px 16px;color:var(--sk-text-muted); }
        .empty-pane i { font-size:36px;margin-bottom:10px;display:block;opacity:0.3; }

        /* Edit expense modal */
        .modal-input { background:var(--sk-input-bg)!important;border:1.5px solid var(--sk-border)!important;color:var(--sk-text)!important;border-radius:10px;padding:10px 14px;width:100%;outline:none;font-weight:700;font-size:13px; }
        .modal-input:focus { border-color:var(--sk-primary)!important; }

        @media print { .no-print{display:none!important} body{background:#fff!important;padding:0!important} }
    </style>
</head>
<body>

<nav class="sk-navbar no-print">
    <a href="List.php" class="btn-back"><i class="fa fa-arrow-left"></i> Staff List</a>
    <div class="sk-navbar-title" style="font-size:11px;">Active Profile</div>
    <button onclick="window.print()" class="btn-theme" style="font-size:13px;"><i class="fa fa-print"></i></button>
</nav>

<!-- Hero Section -->
<div class="profile-hero">
    <div style="display:flex;align-items:flex-start;gap:14px;position:relative;z-index:1;">
        <img src="<?php echo $pic; ?>" class="hero-avatar" onerror="this.src='../../Uploads/profiles/default.png'">
        <div style="flex:1;min-width:0;">
            <div class="hero-name"><?php echo htmlspecialchars($staff['staff_name']); ?></div>
            <div class="hero-phone"><i class="fa fa-phone me-1"></i><?php echo htmlspecialchars($staff['staff_phone']); ?></div>
            <div class="hero-email"><i class="fa fa-envelope me-1"></i><?php echo htmlspecialchars($staff['staff_email']); ?></div>
            <div class="hero-chips">
                <span class="hero-chip"><i class="fa fa-calendar me-1"></i><?php echo date('d M Y',strtotime($staff['staff_join_date'])); ?></span>
                <span class="hero-chip" style="color:#A0F0C0;border-color:rgba(0,206,136,0.3);">Tk.<?php echo number_format($staff['staff_salary'],0); ?>/mo</span>
                <?php if($staff['email_verified']): ?><span class="hero-chip" style="color:#90CAF9;border-color:rgba(33,150,243,0.3);"><i class="fa fa-circle-check me-1"></i>Verified</span><?php endif; ?>
                <?php if($prev_ref): ?><a href="InactiveHistory.php?id=<?php echo $prev_ref['id']; ?>" class="hero-chip" style="color:#FFD54F;border-color:rgba(255,213,79,0.3);text-decoration:none;"><i class="fa fa-link me-1"></i>Prev: <?php echo htmlspecialchars($prev_ref['staff_name']); ?></a><?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Hero summary cards -->
    <div class="hero-summary">
        <div class="hs-card">
            <div class="hs-val" style="color:var(--sk-success);">Tk.<?php echo number_format($lt_earned,0); ?></div>
            <div class="hs-lbl">মোট আয়</div>
        </div>
        <div class="hs-card">
            <div class="hs-val" style="color:var(--sk-danger);">Tk.<?php echo number_format($lt['lt_expense'],0); ?></div>
            <div class="hs-lbl">মোট খরচ</div>
        </div>
        <div class="hs-card" style="border-color:<?php echo $real_balance>0?'rgba(255,74,106,0.3)':'rgba(0,206,136,0.3)'; ?>">
            <div class="hs-val" style="color:<?php echo $real_balance>0?'var(--sk-danger)':'var(--sk-success)'; ?>">Tk.<?php echo number_format(abs($real_balance),0); ?></div>
            <div class="hs-lbl"><?php echo $real_balance>0?'কোম্পানি পাবে':'Staff পাবে'; ?></div>
        </div>
    </div>
</div>

<div class="page-wrap" style="padding-top:4px;">

    <!-- Tabs -->
    <div class="sk-tabs no-print animate-in">
        <button class="sk-tab active" id="tab-monthly" onclick="showTab('monthly')"><i class="fa fa-calendar-alt"></i>মাসিক</button>
        <button class="sk-tab" id="tab-lifetime" onclick="showTab('lifetime')"><i class="fa fa-clock-rotate-left"></i>সামগ্রিক</button>
        <button class="sk-tab" id="tab-notes" onclick="showTab('notes')">
            <i class="fa fa-note-sticky"></i>নোট
            <?php if(count($notes)>0): ?><span style="background:var(--sk-danger);color:#fff;font-size:9px;border-radius:10px;padding:1px 6px;margin-left:2px;"><?php echo count($notes); ?></span><?php endif; ?>
        </button>
    </div>

    <!-- MONTHLY TAB -->
    <div id="pane-monthly" class="mt-3">

        <!-- Month Navigator -->
        <div class="month-nav no-print">
            <a href="?id=<?php echo $staff_id; ?>&month=<?php echo $prev_month; ?>" class="month-nav-btn"><i class="fa fa-chevron-left"></i></a>
            <div style="text-align:center;">
                <div style="font-size:15px;font-weight:900;"><?php echo $month_label; ?></div>
                <select class="month-select" onchange="location='?id=<?php echo $staff_id; ?>&month='+this.value">
                    <?php foreach($available_months as $ym): ?><option value="<?php echo $ym; ?>" <?php echo $ym===$sel_month?'selected':''; ?>><?php echo date('F Y',strtotime($ym.'-01')); ?></option><?php endforeach; ?>
                </select>
            </div>
            <a href="?id=<?php echo $staff_id; ?>&month=<?php echo $next_month; ?>" class="month-nav-btn <?php echo $next_month>date('Y-m')?'disabled':''; ?>"><i class="fa fa-chevron-right"></i></a>
        </div>

        <!-- Monthly Stats -->
        <div class="row g-2 mb-3">
            <div class="col-4 col-sm-2"><div class="stat-chip stat-chip-success"><div class="val" style="color:var(--sk-success);"><?php echo $m_present; ?></div><div class="lbl">উপস্থিত</div></div></div>
            <div class="col-4 col-sm-2"><div class="stat-chip stat-chip-danger"><div class="val" style="color:var(--sk-danger);"><?php echo $m_absent; ?></div><div class="lbl">অনুপস্থিত</div></div></div>
            <div class="col-4 col-sm-2"><div class="stat-chip stat-chip-warning"><div class="val" style="color:var(--sk-warning);"><?php echo $m_half; ?></div><div class="lbl">হাফ ডে</div></div></div>
            <div class="col-4 col-sm-2"><div class="stat-chip stat-chip-info"><div class="val" style="color:var(--sk-info);"><?php echo $m_leave; ?></div><div class="lbl">ছুটি</div></div></div>
            <div class="col-4 col-sm-2"><div class="stat-chip stat-chip-danger"><div class="val" style="color:var(--sk-danger);font-size:15px;"><?php echo $m_late; ?></div><div class="lbl">Late(m)</div></div></div>
            <div class="col-4 col-sm-2"><div class="stat-chip stat-chip-danger"><div class="val" style="color:var(--sk-danger);font-size:14px;"><?php echo number_format($m_total_exp,0); ?></div><div class="lbl">খরচ Tk</div></div></div>
        </div>

        <!-- Attendance List -->
        <?php if(empty($month_att)): ?><div class="empty-pane sk-card"><i class="fa fa-calendar-xmark"></i><p>এই মাসে কোনো হাজিরা নেই</p></div>
        <?php else: ?>
        <div class="sk-card mb-3">
            <div class="sk-card-header"><i class="fa fa-clipboard-list"></i>হাজিরা রেকর্ড — <?php echo $month_label; ?></div>
            <?php $s_colors=['Present'=>'present','Absent'=>'absent','Half'=>'half','Leave'=>'leave'];$s_labels=['Present'=>'উপস্থিত','Absent'=>'অনুপস্থিত','Half'=>'হাফ ডে','Leave'=>'ছুটি']; ?>
            <?php foreach($month_att as $a): $b=$s_colors[$a['status']]??'primary';$l=$s_labels[$a['status']]??$a['status']; ?>
            <div class="att-row">
                <div>
                    <div class="att-date"><?php echo date('d F, Y',strtotime($a['attendance_date'])); ?></div>
                    <div class="att-day"><?php echo date('l',strtotime($a['attendance_date'])); ?></div>
                    <?php if(!empty($a['leave_note'])): ?><div class="att-note"><i class="fa fa-comment me-1"></i><?php echo htmlspecialchars($a['leave_note']); ?></div><?php endif; ?>
                </div>
                <div style="text-align:right;">
                    <span class="sk-badge sk-badge-<?php echo $b; ?>"><?php echo $l; ?></span>
                    <?php if($a['late_time']>0): ?><div style="font-size:10px;font-weight:800;color:var(--sk-danger);margin-top:4px;"><i class="fa fa-clock me-1"></i><?php echo $a['late_time']; ?>m</div><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Expense List -->
        <?php if(!empty($month_exp)): ?>
        <div class="sk-card mb-3">
            <div class="sk-card-header"><i class="fa fa-receipt"></i>খরচের রেকর্ড — <?php echo $month_label; ?></div>
            <?php foreach($month_exp as $e): ?>
            <div class="exp-row">
                <div>
                    <div class="exp-type"><?php echo htmlspecialchars($e['expense_type']); ?></div>
                    <div class="exp-date"><?php echo date('d M Y',strtotime($e['expense_date'])); ?></div>
                    <?php if(!empty($e['description'])): ?><div style="font-size:9px;color:var(--sk-text-muted);"><?php echo htmlspecialchars($e['description']); ?></div><?php endif; ?>
                </div>
                <div class="exp-actions">
                    <span style="font-size:14px;font-weight:900;color:var(--sk-danger);">Tk.<?php echo number_format($e['amount'],2); ?></span>
                    <button class="action-icon action-icon-edit no-print" onclick="editExp(<?php echo $e['id']; ?>,'<?php echo addslashes($e['expense_type']); ?>',<?php echo $e['amount']; ?>,'<?php echo $e['expense_date']; ?>','<?php echo addslashes($e['description']??''); ?>')"><i class="fa fa-pen"></i></button>
                    <button class="action-icon action-icon-del no-print" onclick="delExp(<?php echo $e['id']; ?>,<?php echo $e['amount']; ?>)"><i class="fa fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:rgba(255,74,106,0.06);border-top:1px solid var(--sk-border);">
                <span style="font-size:10px;font-weight:900;color:var(--sk-text-muted);text-transform:uppercase;">এই মাসের মোট খরচ</span>
                <span style="font-size:15px;font-weight:900;color:var(--sk-danger);">Tk.<?php echo number_format($m_total_exp,2); ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- LIFETIME TAB -->
    <div id="pane-lifetime" class="mt-3" style="display:none;">
        <div class="lt-grid mb-3">
            <div class="lt-card">
                <div class="lt-card-title"><i class="fa fa-calendar-check" style="color:var(--sk-primary);"></i>সামগ্রিক হাজিরা</div>
                <div class="lt-row"><span style="color:var(--sk-success);font-weight:700;">উপস্থিত</span><span style="font-weight:900;"><?php echo $lt['lt_present']; ?> দিন</span></div>
                <div class="lt-row"><span style="color:var(--sk-danger);font-weight:700;">অনুপস্থিত</span><span style="font-weight:900;"><?php echo $lt['lt_absent']; ?> দিন</span></div>
                <div class="lt-row"><span style="color:var(--sk-warning);font-weight:700;">হাফ ডে</span><span style="font-weight:900;"><?php echo $lt['lt_half']; ?> দিন</span></div>
                <div class="lt-row"><span style="color:var(--sk-info);font-weight:700;">ছুটি</span><span style="font-weight:900;"><?php echo $lt['lt_leave']; ?> দিন</span></div>
                <div class="lt-row"><span style="color:var(--sk-danger);font-weight:700;">মোট লেট</span><span style="font-weight:900;"><?php echo $lt['lt_late']; ?> মিনিট</span></div>
            </div>
            <div class="lt-card">
                <div class="lt-card-title"><i class="fa fa-coins" style="color:var(--sk-warning);"></i>সামগ্রিক আর্থিক</div>
                <div class="lt-row"><span style="color:var(--sk-text-muted);font-weight:700;">Daily Rate</span><span style="font-weight:900;font-size:11px;">Tk.<?php echo number_format($daily_salary,2); ?></span></div>
                <div class="lt-row"><span style="color:var(--sk-success);font-weight:700;">মোট আয়</span><span style="font-weight:900;color:var(--sk-success);font-size:11px;">Tk.<?php echo number_format($lt_earned,2); ?></span></div>
                <div class="lt-row"><span style="color:var(--sk-danger);font-weight:700;">মোট খরচ</span><span style="font-weight:900;color:var(--sk-danger);font-size:11px;">Tk.<?php echo number_format($lt['lt_expense'],2); ?></span></div>
                <div style="border-top:1px solid var(--sk-border);padding-top:8px;margin-top:8px;" class="lt-row">
                    <span style="font-weight:900;"><?php echo $real_balance>0?'কোম্পানি পাবে':'Staff পাবে'; ?></span>
                    <span style="font-weight:900;color:<?php echo $real_balance>0?'var(--sk-danger)':'var(--sk-success)'; ?>;">Tk.<?php echo number_format(abs($real_balance),2); ?></span>
                </div>
            </div>
        </div>

        <!-- Month Breakdown -->
        <div class="sk-card">
            <div class="sk-card-header"><i class="fa fa-table-list"></i>মাস অনুযায়ী সামারি</div>
            <?php
            $ms=$conn->prepare("SELECT DATE_FORMAT(attendance_date,'%Y-%m') as ym,SUM(status='Present') as p,SUM(status='Absent') as ab,SUM(status='Half') as h,SUM(status='Leave') as lv,SUM(late_time) as late FROM staff_attendance WHERE staff_id=? GROUP BY ym ORDER BY ym DESC");
            $ms->execute([$staff_id]);$mrows=$ms->fetchAll(PDO::FETCH_ASSOC);
            $mes=$conn->prepare("SELECT DATE_FORMAT(expense_date,'%Y-%m') as ym,SUM(amount) as total FROM staff_expenses WHERE staff_id=? GROUP BY ym ORDER BY ym DESC");
            $mes->execute([$staff_id]);$ebm=[];foreach($mes->fetchAll(PDO::FETCH_ASSOC) as $r)$ebm[$r['ym']]=$r['total'];
            foreach($mrows as $mr):
            ?>
            <a href="?id=<?php echo $staff_id; ?>&month=<?php echo $mr['ym']; ?>" class="mb-row">
                <div>
                    <div style="font-size:13px;font-weight:800;"><?php echo date('F Y',strtotime($mr['ym'].'-01')); ?></div>
                    <div style="font-size:10px;color:var(--sk-text-muted);font-weight:700;margin-top:2px;">
                        <span style="color:var(--sk-success);">P:<?php echo $mr['p']; ?></span> ·
                        <span style="color:var(--sk-danger);">A:<?php echo $mr['ab']; ?></span> ·
                        <span style="color:var(--sk-warning);">H:<?php echo $mr['h']; ?></span> ·
                        <span style="color:var(--sk-info);">L:<?php echo $mr['lv']; ?></span>
                        <?php if($mr['late']>0): ?> · <span style="color:var(--sk-danger);"><?php echo $mr['late']; ?>m</span><?php endif; ?>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:13px;font-weight:900;color:var(--sk-danger);">Tk.<?php echo number_format($ebm[$mr['ym']]??0,0); ?></div>
                    <div style="font-size:10px;color:var(--sk-text-muted);"><i class="fa fa-chevron-right"></i></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- NOTES TAB -->
    <div id="pane-notes" class="mt-3" style="display:none;">

        <!-- Add Note -->
        <div class="add-note-card no-print mb-3">
            <div style="font-size:11px;font-weight:900;text-transform:uppercase;color:var(--sk-primary);margin-bottom:10px;"><i class="fa fa-plus me-1"></i>নতুন নোট যোগ করুন</div>
            <textarea id="new_note_text" class="note-textarea" rows="3" placeholder="নোট লিখুন..."></textarea>
            <div class="photo-options">
                <label class="photo-btn"><i class="fa fa-upload" style="color:var(--sk-info);"></i>আপলোড<input type="file" id="note_photo_file" accept="image/*" style="display:none;" onchange="previewNotePhoto(this)"></label>
                <button onclick="openCamera()" class="photo-btn"><i class="fa fa-camera" style="color:var(--sk-success);"></i>ক্যামেরা</button>
            </div>
            <div id="note_photo_preview" style="display:none;margin-bottom:10px;">
                <img id="preview_img" src="" style="width:100%;max-height:160px;object-fit:contain;border-radius:10px;border:1px solid var(--sk-border);">
                <button onclick="clearPhoto()" style="font-size:10px;color:var(--sk-danger);background:none;border:none;cursor:pointer;margin-top:4px;font-weight:800;"><i class="fa fa-times me-1"></i>Remove</button>
            </div>
            <div id="note_msg" style="display:none;font-size:11px;font-weight:800;margin-bottom:8px;text-align:center;"></div>
            <button onclick="addNote()" class="btn-app-primary w-100" style="font-size:13px;padding:11px;"><i class="fa fa-floppy-disk me-1"></i>নোট সেভ করুন</button>
        </div>

        <!-- Notes List -->
        <?php if(empty($notes)): ?>
        <div class="empty-pane sk-card"><i class="fa fa-note-sticky"></i><p>কোনো নোট নেই</p></div>
        <?php else: ?>
        <div id="notes_list">
        <?php foreach($notes as $n): ?>
        <div class="note-card" id="note-<?php echo $n['id']; ?>">
            <div class="note-meta">
                <span><i class="fa fa-clock me-1"></i><?php echo date('d M Y, h:i A',strtotime($n['created_at'])); ?> · <?php echo htmlspecialchars($n['created_by']); ?></span>
                <div class="d-flex gap-2 no-print">
                    <button onclick="editNote(<?php echo $n['id']; ?>)" class="action-icon action-icon-edit"><i class="fa fa-pen"></i></button>
                    <button onclick="deleteNote(<?php echo $n['id']; ?>)" class="action-icon action-icon-del"><i class="fa fa-trash"></i></button>
                </div>
            </div>
            <div class="note-text" id="note-text-<?php echo $n['id']; ?>"><?php echo nl2br(htmlspecialchars($n['note_text'])); ?></div>
            <?php
            // পুরনো DB রেকর্ডের 'uploads/notes/...' পাথকে নতুন Uploads/notes/ লোকেশনে ম্যাপ করা
            $note_photo = $n['photo_path'] ?? '';
            if ($note_photo !== '' && stripos($note_photo, 'uploads/') === 0) {
                $note_photo = '../../Uploads/notes/' . basename($note_photo);
            }
            ?>
            <?php if(!empty($note_photo)&&file_exists($note_photo)): ?>
            <img src="<?php echo $note_photo; ?>" class="note-photo" onclick="this.style.maxHeight=this.style.maxHeight?'':'180px'">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div style="height:16px;"></div>
</div>

<!-- Camera Modal -->
<div class="sk-modal-backdrop" id="cameraModal">
    <div class="sk-modal-box" style="max-width:360px;">
        <div class="sk-modal-header">
            <h5 style="color:var(--sk-success);"><i class="fa fa-camera me-2"></i>ছবি তুলুন</h5>
            <button class="sk-modal-close" onclick="closeCamera()"><i class="fa fa-times"></i></button>
        </div>
        <div class="sk-modal-body">
            <video id="cameraStream" autoplay playsinline style="width:100%;border-radius:12px;border:2px solid var(--sk-border);max-height:260px;object-fit:cover;"></video>
            <canvas id="cameraCanvas" style="display:none;"></canvas>
            <button onclick="capturePhoto()" class="btn-app-success w-100 mt-3" style="font-size:13px;padding:12px;"><i class="fa fa-camera me-1"></i>ছবি তুলুন</button>
        </div>
    </div>
</div>

<!-- Edit Expense Modal -->
<div class="sk-modal-backdrop" id="editExpModal">
    <div class="sk-modal-box" style="max-width:380px;">
        <div class="sk-modal-header">
            <h5 style="color:var(--sk-info);"><i class="fa fa-pen me-2"></i>খরচ সম্পাদনা</h5>
            <button class="sk-modal-close" onclick="closeEditExp()"><i class="fa fa-times"></i></button>
        </div>
        <div class="sk-modal-body">
            <input type="hidden" id="edit_exp_id">
            <div class="mb-2"><label class="sk-label">ধরন</label><select id="edit_exp_type" class="modal-input"><option>Emergency Advance</option><option>Weekly Expense</option><option>Monthly Expense</option><option>Other</option></select></div>
            <div class="mb-2"><label class="sk-label">পরিমাণ (Tk)</label><input type="number" step="0.01" id="edit_exp_amount" class="modal-input" placeholder="0.00"></div>
            <div class="mb-2"><label class="sk-label">তারিখ</label><input type="date" id="edit_exp_date" class="modal-input"></div>
            <div class="mb-3"><label class="sk-label">বিবরণ</label><input type="text" id="edit_exp_desc" class="modal-input" placeholder="বিবরণ..."></div>
            <div id="edit_exp_msg" style="display:none;font-size:11px;font-weight:800;text-align:center;margin-bottom:10px;"></div>
            <button onclick="saveEditExp()" class="btn-app-primary w-100" style="font-size:13px;padding:12px;"><i class="fa fa-floppy-disk me-1"></i>আপডেট করুন</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function(){const t=localStorage.getItem('sk_theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);})();
function toggleTheme(){const c=document.documentElement.getAttribute('data-bs-theme');const n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-bs-theme',n);localStorage.setItem('sk_theme',n);}

const STAFF_ID=<?php echo $staff_id; ?>;
let capturedImageData=null,stream=null;

function showTab(tab){
    ['monthly','lifetime','notes'].forEach(t=>{document.getElementById('pane-'+t).style.display='none';document.getElementById('tab-'+t).classList.remove('active');});
    document.getElementById('pane-'+tab).style.display='block';document.getElementById('tab-'+tab).classList.add('active');
}

function addNote(){
    const text=document.getElementById('new_note_text').value.trim();
    if(!text){showNoteMsg('নোট লিখুন!','var(--sk-warning)');return;}
    const fd=new FormData();fd.append('action','add_note');fd.append('staff_id',STAFF_ID);fd.append('note_text',text);
    if(capturedImageData)fd.append('captured_image',capturedImageData);
    const file=document.getElementById('note_photo_file').files[0];if(file)fd.append('note_photo',file);
    $.ajax({url:'NoteAjax.php',type:'POST',data:fd,processData:false,contentType:false,success:function(r){if(r.status==='success'){showNoteMsg('✅ '+r.message,'var(--sk-success)');setTimeout(()=>location.reload(),1000);}else showNoteMsg('❌ '+r.message,'var(--sk-danger)');},dataType:'json'});
}
function showNoteMsg(msg,color){const el=document.getElementById('note_msg');el.style.color=color;el.textContent=msg;el.style.display='block';}
function editNote(id){const t=document.getElementById('note-text-'+id).textContent;const nt=prompt('নোট সম্পাদনা করুন:',t);if(nt===null||!nt.trim())return;$.post('NoteAjax.php',{action:'edit_note',note_id:id,note_text:nt.trim()},r=>{if(r.status==='success')location.reload();else alert('❌ '+r.message);},'json');}
function deleteNote(id){if(!confirm('এই নোটটি মুছে দেবেন?'))return;$.post('NoteAjax.php',{action:'delete_note',note_id:id},r=>{if(r.status==='success')document.getElementById('note-'+id).remove();else alert('❌ '+r.message);},'json');}

function openCamera(){document.getElementById('cameraModal').classList.add('show');navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then(s=>{stream=s;document.getElementById('cameraStream').srcObject=s;}).catch(()=>alert('ক্যামেরা access পাওয়া যায়নি'));}
function closeCamera(){if(stream)stream.getTracks().forEach(t=>t.stop());document.getElementById('cameraModal').classList.remove('show');}
function capturePhoto(){const v=document.getElementById('cameraStream'),c=document.getElementById('cameraCanvas');c.width=v.videoWidth;c.height=v.videoHeight;c.getContext('2d').drawImage(v,0,0);capturedImageData=c.toDataURL('image/jpeg',0.8);document.getElementById('preview_img').src=capturedImageData;document.getElementById('note_photo_preview').style.display='block';closeCamera();}
function previewNotePhoto(input){if(!input.files[0])return;const r=new FileReader();r.onload=e=>{document.getElementById('preview_img').src=e.target.result;document.getElementById('note_photo_preview').style.display='block';};r.readAsDataURL(input.files[0]);}
function clearPhoto(){capturedImageData=null;document.getElementById('note_photo_file').value='';document.getElementById('note_photo_preview').style.display='none';}

function editExp(id,type,amount,date,desc){document.getElementById('edit_exp_id').value=id;document.getElementById('edit_exp_type').value=type;document.getElementById('edit_exp_amount').value=amount;document.getElementById('edit_exp_date').value=date;document.getElementById('edit_exp_desc').value=desc;document.getElementById('edit_exp_msg').style.display='none';document.getElementById('editExpModal').classList.add('show');}
function closeEditExp(){document.getElementById('editExpModal').classList.remove('show');}
function saveEditExp(){$.post('../Expense/DeleteAjax.php',{action:'edit_expense',expense_id:$('#edit_exp_id').val(),expense_type:$('#edit_exp_type').val(),amount:$('#edit_exp_amount').val(),expense_date:$('#edit_exp_date').val(),description:$('#edit_exp_desc').val()},r=>{const m=document.getElementById('edit_exp_msg');m.style.display='block';if(r.status==='success'){m.style.color='var(--sk-success)';m.textContent='✅ '+r.message;setTimeout(()=>{closeEditExp();location.reload();},1000);}else{m.style.color='var(--sk-danger)';m.textContent='❌ '+r.message;}},'json');}
function delExp(id,amount){if(!confirm('Tk.'+amount+' এর এই খরচটি মুছে দেবেন?'))return;$.post('../Expense/DeleteAjax.php',{action:'delete_expense',expense_id:id},r=>{if(r.status==='success'){alert('✅ মুছে গেছে!');location.reload();}else alert('❌ '+r.message);},'json');}

document.getElementById('cameraModal').addEventListener('click',function(e){if(e.target===this)closeCamera();});
document.getElementById('editExpModal').addEventListener('click',function(e){if(e.target===this)closeEditExp();});
</script>
</body>
</html>
