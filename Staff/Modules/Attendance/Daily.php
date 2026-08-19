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
$current_date=date('Y-m-d');
$current_month=date('Y-m');
$display_date=date('d F, Y — l');

$monthly_stats=[];
try{
    $statQ=$conn->query("SELECT s.id,s.staff_name,s.profile_pic,
        SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN a.status='Absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN a.status='Half' THEN 1 ELSE 0 END) as half_days,
        SUM(CASE WHEN a.status='Leave' THEN 1 ELSE 0 END) as leave_days,
        SUM(a.late_time) as total_late
        FROM staff_info s
        LEFT JOIN staff_attendance a ON s.id=a.staff_id AND DATE_FORMAT(a.attendance_date,'%Y-%m')='$current_month'
        WHERE s.staff_status='Active' AND s.email_verified=1
        GROUP BY s.id ORDER BY s.staff_name ASC");
    $monthly_stats=$statQ->fetchAll(PDO::FETCH_ASSOC);
}catch(Exception $e){}

$today_staffs=[];
try{
    $todayQ=$conn->query("SELECT s.id,s.staff_name,s.profile_pic,s.staff_email,a.status as today_status,a.late_time,a.leave_note
        FROM staff_info s
        LEFT JOIN staff_attendance a ON s.id=a.staff_id AND DATE(a.attendance_date)='$current_date'
        WHERE s.staff_status='Active' AND s.email_verified=1
        ORDER BY s.staff_name ASC");
    $today_staffs=$todayQ->fetchAll(PDO::FETCH_ASSOC);
}catch(Exception $e){}

$total=$pending=0;
foreach($today_staffs as $ts){ $total++; if(empty($ts['today_status'])) $pending++; }
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Daily Attendance — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    <style>
        /* Monthly scroll strip */
        .month-strip { display:flex; gap:10px; overflow-x:auto; padding:4px 14px 10px; scrollbar-width:none; -webkit-overflow-scrolling:touch; }
        .month-strip::-webkit-scrollbar { display:none; }
        .month-card {
            background:var(--sk-card);
            border:1px solid var(--sk-border);
            border-radius:14px;
            padding:10px 14px;
            display:flex; align-items:center; gap:10px;
            min-width:200px; flex-shrink:0;
            box-shadow:var(--sk-shadow-card);
        }
        .month-card img { width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid var(--sk-primary);flex-shrink:0; }
        .month-card-name { font-size:11px;font-weight:800;color:var(--sk-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100px; }
        .month-mini-stats { display:flex;gap:4px;margin-top:3px; }
        .mms { font-size:9px;font-weight:900;padding:2px 6px;border-radius:6px; }
        .mms-p { background:rgba(0,206,136,0.15);color:var(--sk-success); }
        .mms-a { background:rgba(255,74,106,0.15);color:var(--sk-danger); }
        .mms-h { background:rgba(255,179,71,0.15);color:var(--sk-warning); }
        .mms-l { background:rgba(33,150,243,0.15);color:var(--sk-info); }
        .mms-lt { background:rgba(255,74,106,0.1);color:var(--sk-danger); }

        /* Date bar */
        .date-bar {
            background:linear-gradient(135deg,var(--sk-primary),var(--sk-primary-dark));
            border-radius:14px 14px 0 0;
            padding:12px 16px;
            display:flex; align-items:center; justify-content:center; gap:8px;
            font-size:13px; font-weight:900; color:#fff; letter-spacing:0.5px;
            margin-top:14px;
        }

        /* Response message */
        #responseMsg { border-radius:12px;padding:12px 16px;font-weight:700;font-size:13px;text-align:center;display:none;margin:10px 14px; }

        /* Attendance table */
        .att-table-wrap { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:0 0 14px 14px;overflow:hidden;margin-bottom:80px; }
        .att-table-wrap table { width:100%;border-collapse:collapse;font-size:13px; }
        .att-table-wrap thead th { background:var(--sk-surface);border-bottom:1px solid var(--sk-border);padding:10px 10px;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:0.6px;color:var(--sk-text-muted);white-space:nowrap; }
        .att-table-wrap tbody tr { border-bottom:1px solid var(--sk-border);transition:background 0.15s; }
        .att-table-wrap tbody tr:last-child { border-bottom:none; }
        .att-table-wrap tbody tr:hover { background:rgba(255,255,255,0.03); }
        .att-table-wrap tbody td { padding:10px; vertical-align:middle; }

        /* Staff cell */
        .staff-cell { display:flex;align-items:center;gap:8px; }
        .staff-cell img { width:32px;height:32px;border-radius:50%;object-fit:cover;border:1.5px solid var(--sk-border);flex-shrink:0; }
        .staff-cell-name { font-size:12px;font-weight:800;color:var(--sk-text); }

        /* Radio Status Buttons */
        .status-radio-group { display:flex;gap:5px;justify-content:center; }
        .status-radio-label { cursor:pointer; }
        .status-radio-label input[type=radio] { display:none; }
        .status-pill {
            width:30px;height:30px;
            border-radius:50%;
            border:2px solid var(--sk-border);
            background:var(--sk-surface);
            color:var(--sk-text-muted);
            font-size:10px;font-weight:900;
            display:flex;align-items:center;justify-content:center;
            transition:all 0.2s cubic-bezier(0.34,1.56,0.64,1);
            user-select:none;
        }
        input[value="Present"]:checked ~ .status-pill { background:var(--sk-success); border-color:var(--sk-success); color:#fff; transform:scale(1.2); box-shadow:0 0 10px rgba(0,206,136,0.5); }
        input[value="Absent"]:checked  ~ .status-pill { background:var(--sk-danger);  border-color:var(--sk-danger);  color:#fff; transform:scale(1.2); box-shadow:0 0 10px rgba(255,74,106,0.5); }
        input[value="Half"]:checked    ~ .status-pill { background:var(--sk-warning); border-color:var(--sk-warning); color:#fff; transform:scale(1.2); box-shadow:0 0 10px rgba(255,179,71,0.5); }
        input[value="Leave"]:checked   ~ .status-pill { background:var(--sk-info);    border-color:var(--sk-info);    color:#fff; transform:scale(1.2); box-shadow:0 0 10px rgba(33,150,243,0.5); }

        /* Late input */
        .late-input {
            width:56px;
            background:var(--sk-input-bg) !important;
            border:1.5px solid var(--sk-border) !important;
            color:var(--sk-danger) !important;
            border-radius:8px;
            padding:5px 6px;
            text-align:center;
            font-size:11px;font-weight:900;
            outline:none;
        }
        .late-input:focus { border-color:var(--sk-danger) !important; box-shadow:0 0 0 2px rgba(255,74,106,0.2) !important; }

        /* Note input */
        .note-input {
            width:90px;
            background:var(--sk-input-bg) !important;
            border:1.5px solid var(--sk-border) !important;
            color:var(--sk-info) !important;
            border-radius:8px;
            padding:5px 6px;
            text-align:center;
            font-size:10px;font-weight:700;
            outline:none;
        }
        .note-input:focus { border-color:var(--sk-info) !important; box-shadow:0 0 0 2px rgba(33,150,243,0.2) !important; }

        /* Locked row */
        .locked-status { font-size:11px;font-weight:900;color:var(--sk-success);display:flex;align-items:center;gap:4px;justify-content:center; }
        .locked-row td { opacity:0.75; }

        /* Progress header */
        .progress-info { display:flex;align-items:center;justify-content:space-between;padding:8px 14px;font-size:11px;font-weight:800; }
        .progress-bar-custom { height:4px;background:var(--sk-surface);border-radius:2px;margin:0 14px 8px;overflow:hidden; }
        .progress-fill { height:100%;background:linear-gradient(90deg,var(--sk-primary),var(--sk-success));border-radius:2px;transition:width 0.5s ease; }

        /* Save bar */
        .save-bar { position:fixed;bottom:0;left:0;right:0;background:var(--sk-navbar-bg);border-top:1px solid var(--sk-border);backdrop-filter:blur(16px);padding:10px 16px;z-index:1040; }
    </style>
</head>
<body>

<nav class="sk-navbar">
    <a href="../../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Back</a>
    <div class="sk-navbar-title">Daily Attendance<span class="sk-navbar-subtitle"><?php echo date('d M Y'); ?></span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>

<!-- Monthly Summary Strip -->
<?php if(!empty($monthly_stats)): ?>
<div class="month-strip animate-in">
    <?php foreach($monthly_stats as $stat):
        $pic=(!empty($stat['profile_pic'])&&$stat['profile_pic']!=='default.png')?"../../Uploads/profiles/".$stat['profile_pic']:"../../Uploads/profiles/default.png"; ?>
    <div class="month-card">
        <img src="<?php echo $pic; ?>" onerror="this.src='../../Uploads/profiles/default.png'">
        <div style="flex:1;min-width:0;">
            <div class="month-card-name"><?php echo htmlspecialchars($stat['staff_name']); ?></div>
            <div class="month-mini-stats">
                <span class="mms mms-p">P:<?php echo $stat['present_days']?:0; ?></span>
                <span class="mms mms-a">A:<?php echo $stat['absent_days']?:0; ?></span>
                <span class="mms mms-h">H:<?php echo $stat['half_days']?:0; ?></span>
                <span class="mms mms-l">L:<?php echo $stat['leave_days']?:0; ?></span>
                <?php if($stat['total_late']>0): ?><span class="mms mms-lt"><?php echo $stat['total_late']; ?>m</span><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="max-width:800px;margin:0 auto;padding:0 14px;">

    <!-- Progress -->
    <?php $done=$total-$pending; ?>
    <div class="progress-info animate-in delay-1">
        <span style="color:var(--sk-text-muted);">আজকের হাজিরা</span>
        <span style="color:var(--sk-primary);"><?php echo $done; ?>/<?php echo $total; ?> সম্পন্ন</span>
    </div>
    <div class="progress-bar-custom animate-in delay-1">
        <div class="progress-fill" style="width:<?php echo $total>0?round(($done/$total)*100):0; ?>%"></div>
    </div>

    <!-- Date Bar -->
    <div class="date-bar animate-in delay-2">
        <i class="fa fa-calendar-check"></i>
        <?php echo $display_date; ?>
    </div>

    <!-- Response -->
    <div id="responseMsg"></div>

    <!-- Attendance Table -->
    <form id="attendanceBulkForm">
        <div class="att-table-wrap animate-in delay-2">
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="min-width:130px;">স্টাফ</th>
                            <th style="text-align:center;min-width:140px;">হাজিরা স্ট্যাটাস</th>
                            <th style="text-align:center;min-width:70px;">লেট (মি)</th>
                            <th style="text-align:center;min-width:100px;">মন্তব্য</th>
                            <th style="text-align:center;min-width:80px;">অবস্থা</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($today_staffs as $staff):
                            $pic=(!empty($staff['profile_pic'])&&$staff['profile_pic']!=='default.png')?"../../Uploads/profiles/".$staff['profile_pic']:"../../Uploads/profiles/default.png";
                            $is_saved=!empty($staff['today_status']);
                        ?>
                        <tr class="<?php echo $is_saved?'locked-row':''; ?>">
                            <td>
                                <div class="staff-cell">
                                    <img src="<?php echo $pic; ?>" onerror="this.src='../../Uploads/profiles/default.png'">
                                    <span class="staff-cell-name"><?php echo htmlspecialchars($staff['staff_name']); ?></span>
                                </div>
                                <input type="hidden" class="staff-id" value="<?php echo $staff['id']; ?>">
                                <input type="hidden" class="staff-email" value="<?php echo htmlspecialchars($staff['staff_email']??''); ?>">
                            </td>
                            <td style="text-align:center;">
                                <?php if($is_saved): ?>
                                    <span class="locked-status"><i class="fa fa-lock"></i> Locked</span>
                                <?php else: ?>
                                    <div class="status-radio-group">
                                        <?php foreach(['P'=>'Present','A'=>'Absent','H'=>'Half','Lv'=>'Leave'] as $lbl=>$val): ?>
                                        <label class="status-radio-label" title="<?php echo $val; ?>">
                                            <input type="radio" class="status-radio" name="status_<?php echo $staff['id']; ?>" value="<?php echo $val; ?>">
                                            <div class="status-pill"><?php echo $lbl; ?></div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if($is_saved): ?>
                                    <span style="font-size:11px;font-weight:900;color:var(--sk-danger);"><?php echo $staff['late_time']?:0; ?>m</span>
                                <?php else: ?>
                                    <input type="number" class="late-input" name="late_<?php echo $staff['id']; ?>" placeholder="0" min="0">
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if($is_saved): ?>
                                    <span style="font-size:9px;font-weight:700;background:var(--sk-surface);border:1px solid var(--sk-border);border-radius:8px;padding:3px 8px;color:var(--sk-text-muted);">
                                        <?php echo !empty($staff['leave_note'])?htmlspecialchars($staff['leave_note']):'— Recorded —'; ?>
                                    </span>
                                <?php else: ?>
                                    <input type="text" class="note-input" name="note_<?php echo $staff['id']; ?>" placeholder="কারণ...">
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if($is_saved): ?>
                                    <span class="sk-badge sk-badge-<?php echo strtolower($staff['today_status'])==='present'?'present':(strtolower($staff['today_status'])==='absent'?'absent':(strtolower($staff['today_status'])==='half'?'half':'leave')); ?>">
                                        <?php echo $staff['today_status']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="sk-badge sk-badge-warning"><i class="fa fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<!-- Save Bar -->
<div class="save-bar">
    <button type="button" id="saveBulkBtn" class="btn-app-success w-100" style="font-size:14px;letter-spacing:0.5px;">
        <i class="fa fa-cloud-arrow-up me-2"></i>Save Attendance &amp; Send Emails
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function(){
    const t=localStorage.getItem('sk_theme')||'dark';
    document.documentElement.setAttribute('data-bs-theme',t);
    document.addEventListener('DOMContentLoaded',function(){const i=document.getElementById('themeIcon');if(i)i.className=t==='dark'?'fas fa-sun':'fas fa-moon';});
})();
function toggleTheme(){
    const c=document.documentElement.getAttribute('data-bs-theme');
    const n=c==='dark'?'light':'dark';
    document.documentElement.setAttribute('data-bs-theme',n);
    localStorage.setItem('sk_theme',n);
    document.getElementById('themeIcon').className=n==='dark'?'fas fa-sun':'fas fa-moon';
}

$('#saveBulkBtn').on('click',function(){
    let attendanceData=[];
    $('tbody tr').each(function(){
        const staffId=$(this).find('.staff-id').val();
        const email=$(this).find('.staff-email').val();
        const radios=$(this).find('.status-radio');
        if(radios.length>0){
            const status=$(this).find('input[name="status_'+staffId+'"]:checked').val();
            const lateTime=$(this).find('input[name="late_'+staffId+'"]').val()||0;
            const leaveNote=$(this).find('input[name="note_'+staffId+'"]').val()||'';
            if(status) attendanceData.push({staff_id:staffId,status:status,late_time:lateTime,leave_note:leaveNote,staff_email:email});
        }
    });
    if(attendanceData.length===0){
        const msg=$('#responseMsg');
        msg.css({background:'rgba(255,179,71,0.1)',color:'var(--sk-warning)',border:'1px solid rgba(255,179,71,0.3)',borderRadius:'12px',padding:'12px 16px',fontWeight:'700',fontSize:'13px',textAlign:'center'}).html('<i class="fa fa-triangle-exclamation me-2"></i>দয়া করে অন্তত একজনের হাজিরা সিলেক্ট করুন!').show();
        setTimeout(()=>msg.fadeOut(),4000); return;
    }
    const btn=$(this);
    btn.html('<i class="fa fa-spinner fa-spin me-2"></i>Saving & Sending Emails...').prop('disabled',true);
    $.ajax({
        url:'SaveAjax.php', type:'POST',
        data:{action:'save_bulk_attendance', attendance_data:JSON.stringify(attendanceData)},
        dataType:'json',
        success:function(res){
            const msg=$('#responseMsg');
            if(res.status==='success'){
                confetti({particleCount:160,spread:80,origin:{y:0.6},colors:['#7C5CFC','#00CE88','#FFB347','#FF4A6A']});
                msg.css({background:'rgba(0,206,136,0.1)',color:'var(--sk-success)',border:'1px solid rgba(0,206,136,0.3)',borderRadius:'12px',padding:'12px 16px',fontWeight:'700',textAlign:'center'}).html('<i class="fa fa-circle-check me-2"></i>'+res.msg).show();
                setTimeout(()=>location.reload(),3000);
            }else{
                msg.css({background:'rgba(255,74,106,0.1)',color:'var(--sk-danger)',border:'1px solid rgba(255,74,106,0.3)',borderRadius:'12px',padding:'12px 16px',fontWeight:'700',textAlign:'center'}).html('<i class="fa fa-circle-xmark me-2"></i>'+res.msg).show();
                btn.html('<i class="fa fa-cloud-arrow-up me-2"></i>Save Attendance & Send Emails').prop('disabled',false);
            }
            window.scrollTo({top:0,behavior:'smooth'});
        },
        error:function(){
            alert('সার্ভার এরর! কানেকশন চেক করুন।');
            btn.html('<i class="fa fa-cloud-arrow-up me-2"></i>Save Attendance & Send Emails').prop('disabled',false);
        }
    });
});
</script>
</body>
</html>
