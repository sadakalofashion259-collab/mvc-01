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
$success_msg="";$error_msg="";

if(isset($_GET['delete_id'])){
    $delete_id=filter_var($_GET['delete_id'],FILTER_SANITIZE_NUMBER_INT);
    try{$stmt=$conn->prepare("DELETE FROM staff_info WHERE id=?");if($stmt->execute([$delete_id]))$success_msg="স্টাফের সম্পূর্ণ তথ্য সফলভাবে মুছে ফেলা হয়েছে!";}catch(Exception $e){$error_msg="মুছে ফেলতে সমস্যা হয়েছে: ".$e->getMessage();}
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['update_staff'])){
    $id=filter_var($_POST['staff_id'],FILTER_SANITIZE_NUMBER_INT);
    $name=htmlspecialchars(trim($_POST['s_name']),ENT_QUOTES,'UTF-8');
    $email=filter_var(trim($_POST['s_email']),FILTER_SANITIZE_EMAIL);
    $phone=htmlspecialchars(trim($_POST['s_phone']),ENT_QUOTES,'UTF-8');
    $salary=floatval($_POST['s_salary']);$status=$_POST['s_status'];
    $address=htmlspecialchars(trim($_POST['s_address']??''),ENT_QUOTES,'UTF-8');
    $join_date=trim($_POST['s_join_date']??'');
    try{
        // ✅ প্রোফাইল ছবি আপলোড/রিপ্লেস — Uploads/profiles/ ফোল্ডারে
        $new_pic=null;
        if(!empty($_FILES['s_photo']['name'])&&is_uploaded_file($_FILES['s_photo']['tmp_name'])){
            $allowed=['jpg','jpeg','png','gif','webp'];
            $ext=strtolower(pathinfo($_FILES['s_photo']['name'],PATHINFO_EXTENSION));
            if(!in_array($ext,$allowed,true)){
                throw new Exception("শুধু JPG/PNG/GIF/WEBP ছবি আপলোড করা যাবে!");
            }
            $pic_dir=__DIR__.'/../../Uploads/profiles/';
            if(!is_dir($pic_dir)) mkdir($pic_dir,0755,true);
            $new_pic=time().'_admin_'.$id.'.'.$ext;
            if(!move_uploaded_file($_FILES['s_photo']['tmp_name'],$pic_dir.$new_pic)){
                throw new Exception("ছবি আপলোড করা যায়নি! (Uploads/profiles permission চেক করুন)");
            }
            // পুরনো ছবি মুছে ফেলা (default.png বাদে)
            $oldQ=$conn->prepare("SELECT profile_pic FROM staff_info WHERE id=?");$oldQ->execute([$id]);
            $old_pic=$oldQ->fetchColumn();
            if($old_pic&&$old_pic!=='default.png'&&file_exists($pic_dir.$old_pic)) @unlink($pic_dir.$old_pic);
        }

        // ✅ সম্পূর্ণ তথ্য আপডেট (ছবিসহ/ছবি ছাড়া)
        $sql="UPDATE staff_info SET staff_name=?,staff_email=?,staff_phone=?,staff_salary=?,staff_status=?";
        $params=[$name,$email,$phone,$salary,$status];
        if($address!==''){$sql.=",staff_address=?";$params[]=$address;}
        if($join_date!==''){$sql.=",staff_join_date=?";$params[]=$join_date;}
        if($new_pic!==null){$sql.=",profile_pic=?";$params[]=$new_pic;}
        $sql.=" WHERE id=?";$params[]=$id;
        $upd=$conn->prepare($sql);
        if($upd->execute($params))$success_msg="স্টাফের সম্পূর্ণ তথ্য".($new_pic?" ও প্রোফাইল ছবি":"")." সফলভাবে আপডেট করা হয়েছে!";
    }catch(Exception $e){$error_msg="আপডেট করতে সমস্যা: ".$e->getMessage();}
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['correct_attendance'])){
    $staff_id=filter_var($_POST['att_staff_id'],FILTER_SANITIZE_NUMBER_INT);
    $att_date=$_POST['att_date'];$new_status=htmlspecialchars(trim($_POST['att_status']),ENT_QUOTES,'UTF-8');
    $new_late=floatval($_POST['att_late']);$new_note=htmlspecialchars(trim($_POST['att_note']),ENT_QUOTES,'UTF-8');
    $admin_user=$_SESSION['username']??'Admin';
    if(empty($att_date)||empty($new_status)){$error_msg="তারিখ এবং স্ট্যাটাস অবশ্যই পূরণ করতে হবে!";}
    else{
        try{
            $oldQ=$conn->prepare("SELECT status FROM staff_attendance WHERE staff_id=? AND DATE(attendance_date)=?");$oldQ->execute([$staff_id,$att_date]);$oldAtt=$oldQ->fetch();
            $salQ=$conn->prepare("SELECT staff_salary,running_balance FROM staff_info WHERE id=?");$salQ->execute([$staff_id]);$staffInfo=$salQ->fetch();
            if($staffInfo){
                $daily_salary=$staffInfo['staff_salary']/30;
                if($oldAtt){
                    $old_st=$oldAtt['status'];
                    $old_added=($old_st==='Present'||$old_st==='Leave')?$daily_salary:($old_st==='Half'?$daily_salary/2:0);
                    $new_added=($new_status==='Present'||$new_status==='Leave')?$daily_salary:($new_status==='Half'?$daily_salary/2:0);
                    $new_balance=$staffInfo['running_balance']+($new_added-$old_added);
                    $conn->beginTransaction();
                    $conn->prepare("UPDATE staff_info SET running_balance=? WHERE id=?")->execute([$new_balance,$staff_id]);
                    $conn->prepare("UPDATE staff_attendance SET status=?,late_time=?,leave_note=? WHERE staff_id=? AND DATE(attendance_date)=?")->execute([$new_status,$new_late,$new_note,$staff_id,$att_date]);
                    $conn->commit();$success_msg="হাজিরা এবং ব্যালেন্স সফলভাবে সংশোধন করা হয়েছে!";
                }else{
                    $new_added=($new_status==='Present'||$new_status==='Leave')?$daily_salary:($new_status==='Half'?$daily_salary/2:0);
                    $new_balance=$staffInfo['running_balance']+$new_added;
                    $conn->beginTransaction();
                    $conn->prepare("UPDATE staff_info SET running_balance=? WHERE id=?")->execute([$new_balance,$staff_id]);
                    $conn->prepare("INSERT INTO staff_attendance (staff_id,status,late_time,leave_note,attendance_date,entry_by) VALUES(?,?,?,?,?,?)")->execute([$staff_id,$new_status,$new_late,$new_note,$att_date,$admin_user]);
                    $conn->commit();$success_msg="বাদ পড়া দিনের হাজিরা সফলভাবে যুক্ত করা হয়েছে!";
                }
            }
        }catch(Exception $e){if($conn->inTransaction())$conn->rollBack();$error_msg="ত্রুটি: ".$e->getMessage();}
    }
}
$staffs=$conn->query("SELECT * FROM staff_info ORDER BY staff_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$total_staff=count($staffs);$active_staff=0;$overdrawn_staff=0;
foreach($staffs as $s){if($s['staff_status']==='Active')$active_staff++;if($s['running_balance']<=0)$overdrawn_staff++;}
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <style>
        .stats-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px; }
        @media(min-width:576px){ .stats-grid{grid-template-columns:repeat(4,1fr);} }
        .stat-big-card { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:16px;padding:18px 14px;text-align:center; }
        .stat-big-val { font-size:28px;font-weight:900;line-height:1; }
        .stat-big-lbl { font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;color:var(--sk-text-muted);margin-top:4px; }

        /* Staff Table Card */
        .admin-table-card { background:var(--sk-card);border:1px solid var(--sk-border);border-radius:16px;overflow:hidden; }
        .admin-table-wrap { overflow-x:auto; }
        .admin-table { width:100%;border-collapse:collapse;font-size:13px;min-width:820px; }
        .admin-table thead th { background:var(--sk-surface);border-bottom:1px solid var(--sk-border);padding:12px 14px;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:0.6px;color:var(--sk-text-muted);white-space:nowrap; }
        .admin-table tbody td { padding:12px 14px;border-bottom:1px solid var(--sk-border);vertical-align:middle; }
        .admin-table tbody tr:last-child td { border-bottom:none; }
        .admin-table tbody tr:hover { background:rgba(255,255,255,0.02); }
        .admin-table tbody tr.inactive-row { opacity:0.6;filter:grayscale(0.3); }

        .staff-cell-info { display:flex;align-items:center;gap:10px; }
        .staff-cell-info img { width:36px;height:36px;border-radius:10px;object-fit:cover;border:1.5px solid var(--sk-border);flex-shrink:0; }
        .staff-cell-name { font-size:13px;font-weight:800; }
        .staff-cell-sub { font-size:9px;color:var(--sk-text-muted);font-weight:700; }

        .action-btn { width:32px;height:32px;border-radius:10px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;transition:all 0.2s; }
        .action-btn-edit { background:rgba(33,150,243,0.1);border-color:rgba(33,150,243,0.2);color:var(--sk-info); }
        .action-btn-edit:hover { background:rgba(33,150,243,0.2); }
        .action-btn-att { background:rgba(0,206,136,0.1);border-color:rgba(0,206,136,0.2);color:var(--sk-success); }
        .action-btn-att:hover { background:rgba(0,206,136,0.2); }
        .action-btn-del { background:rgba(255,74,106,0.1);border-color:rgba(255,74,106,0.2);color:var(--sk-danger);text-decoration:none; }
        .action-btn-del:hover { background:rgba(255,74,106,0.2); }

        .modal-input { background:var(--sk-input-bg)!important;border:1.5px solid var(--sk-border)!important;color:var(--sk-text)!important;border-radius:10px!important;padding:10px 14px!important;width:100%;outline:none;font-family:'Nunito',sans-serif;font-weight:700;font-size:13px;transition:border-color 0.2s; }
        .modal-input:focus { border-color:var(--sk-primary)!important;box-shadow:0 0 0 2px rgba(124,92,252,0.15)!important; }
        .modal-label { font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:0.8px;color:var(--sk-text-muted);display:block;margin-bottom:5px; }
    </style>
</head>
<body>

<nav class="sk-navbar">
    <a href="../../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Back</a>
    <div class="sk-navbar-title" style="color:#FF4A6A;">Admin Control<span class="sk-navbar-subtitle">Full Access Panel</span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>

<div class="page-wrap mt-3">

    <!-- Alerts -->
    <?php if($success_msg): ?>
    <div class="sk-alert sk-alert-success mb-3 animate-in"><i class="fa fa-circle-check"></i><?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if($error_msg): ?>
    <div class="sk-alert sk-alert-danger mb-3 animate-in"><i class="fa fa-triangle-exclamation"></i><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid animate-in">
        <div class="stat-big-card">
            <div class="stat-big-val" style="color:var(--sk-info);"><?php echo $total_staff; ?></div>
            <div class="stat-big-lbl">মোট স্টাফ</div>
        </div>
        <div class="stat-big-card" style="border-color:rgba(0,206,136,0.2);">
            <div class="stat-big-val" style="color:var(--sk-success);"><?php echo $active_staff; ?></div>
            <div class="stat-big-lbl">Active</div>
        </div>
        <div class="stat-big-card">
            <div class="stat-big-val" style="color:var(--sk-warning);"><?php echo $total_staff-$active_staff; ?></div>
            <div class="stat-big-lbl">Inactive</div>
        </div>
        <div class="stat-big-card" style="border-color:rgba(255,74,106,0.2);">
            <div class="stat-big-val" style="color:var(--sk-danger);"><?php echo $overdrawn_staff; ?></div>
            <div class="stat-big-lbl">Zero/Overdrawn</div>
        </div>
    </div>

    <!-- Staff Table -->
    <div class="admin-table-card animate-in delay-1">
        <div class="sk-card-header" style="color:var(--sk-danger);"><i class="fa fa-user-shield me-2" style="color:var(--sk-danger);"></i>All Staff Management</div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Staff Info</th>
                        <th>Contact</th>
                        <th>Salary</th>
                        <th>Balance</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($staffs as $s):
                        $pic=(!empty($s['profile_pic'])&&file_exists("../../Uploads/profiles/".$s['profile_pic']))?"../../Uploads/profiles/".$s['profile_pic']:"../../Uploads/profiles/default.png";
                        $is_active=$s['staff_status']==='Active';
                        $is_overdrawn=$s['running_balance']<=0;
                    ?>
                    <tr class="<?php echo !$is_active?'inactive-row':''; ?>">
                        <td>
                            <div class="staff-cell-info">
                                <img src="<?php echo $pic; ?>" onerror="this.src='../../Uploads/profiles/default.png'">
                                <div>
                                    <div class="staff-cell-name">
                                        <?php echo htmlspecialchars($s['staff_name']); ?>
                                        <?php if($s['email_verified']==1): ?><i class="fa fa-circle-check" style="color:var(--sk-info);font-size:10px;margin-left:4px;"></i><?php endif; ?>
                                    </div>
                                    <div class="staff-cell-sub">Join: <?php echo date('d M Y',strtotime($s['staff_join_date'])); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:12px;font-weight:700;"><?php echo htmlspecialchars($s['staff_phone']); ?></div>
                            <div style="font-size:10px;color:var(--sk-text-muted);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($s['staff_email']); ?></div>
                        </td>
                        <td><span style="font-size:13px;font-weight:900;color:var(--sk-success);">Tk.<?php echo number_format($s['staff_salary'],2); ?></span></td>
                        <td>
                            <div style="font-size:13px;font-weight:900;color:<?php echo $is_overdrawn?'var(--sk-danger)':'var(--sk-success)'; ?>;">
                                Tk.<?php echo number_format($s['running_balance'],2); ?>
                            </div>
                            <?php if($is_overdrawn): ?><span class="sk-badge sk-badge-danger" style="font-size:8px;margin-top:3px;"><i class="fa fa-triangle-exclamation"></i> Overdrawn</span><?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if($is_active): ?>
                            <span class="sk-badge sk-badge-active"><i class="fa fa-circle" style="font-size:7px;"></i> Active</span>
                            <?php else: ?>
                            <span class="sk-badge sk-badge-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:flex;gap:6px;justify-content:flex-end;">
                                <button onclick='openAttModal(<?php echo $s["id"]; ?>,"<?php echo htmlspecialchars(addslashes($s['staff_name'])); ?>")' class="action-btn action-btn-att" title="হাজিরা সংশোধন"><i class="fa fa-calendar-check"></i></button>
                                <button onclick='openEditModal(<?php echo json_encode($s); ?>)' class="action-btn action-btn-edit" title="Edit Staff"><i class="fa fa-pen"></i></button>
                                <a href="?delete_id=<?php echo $s['id']; ?>" onclick="return confirm('সতর্কতা: এই স্টাফের সব ডাটা ডিলিট করবেন?');" class="action-btn action-btn-del" title="Delete"><i class="fa fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div style="height:20px;"></div>
</div>

<!-- Edit Staff Modal -->
<div class="sk-modal-backdrop" id="editModal">
    <div class="sk-modal-box">
        <div class="sk-modal-header">
            <h5 style="color:var(--sk-info);"><i class="fa fa-user-pen me-2"></i>Edit Staff Info</h5>
            <button class="sk-modal-close" onclick="closeEditModal()"><i class="fa fa-times"></i></button>
        </div>
        <div class="sk-modal-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="staff_id" id="modal_id">

                <!-- ✅ প্রোফাইল ছবি প্রিভিউ + আপলোড -->
                <div class="col-12 text-center">
                    <img id="modal_pic_preview" src="" alt="Profile"
                         style="width:84px;height:84px;border-radius:50%;object-fit:cover;border:3px solid var(--sk-primary);box-shadow:0 0 0 5px rgba(124,92,252,0.12);"
                         onerror="this.src='../../Uploads/profiles/default.png'">
                    <div style="margin-top:10px;">
                        <input type="file" name="s_photo" id="modal_photo" accept="image/*" style="display:none;">
                        <button type="button" onclick="document.getElementById('modal_photo').click()"
                                style="background:rgba(124,92,252,0.12);border:1px dashed var(--sk-primary);color:var(--sk-primary);border-radius:10px;padding:8px 16px;font-size:11px;font-weight:800;cursor:pointer;">
                            <i class="fa fa-camera me-1"></i>ছবি পরিবর্তন করুন
                        </button>
                        <div id="modal_photo_name" style="font-size:10px;color:var(--sk-text-muted);margin-top:5px;"></div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="modal-label">স্টাফের নাম</label>
                    <input type="text" name="s_name" id="modal_name" class="modal-input" required placeholder="Full Name">
                </div>
                <div class="col-12">
                    <label class="modal-label">ইমেইল</label>
                    <input type="email" name="s_email" id="modal_email" class="modal-input" required placeholder="email@example.com">
                </div>
                <div class="col-6">
                    <label class="modal-label">ফোন নম্বর</label>
                    <input type="text" name="s_phone" id="modal_phone" class="modal-input" required placeholder="+8801...">
                </div>
                <div class="col-6">
                    <label class="modal-label">বেতন (Tk)</label>
                    <input type="number" step="0.01" name="s_salary" id="modal_salary" class="modal-input" required placeholder="0.00">
                </div>
                <div class="col-6">
                    <label class="modal-label">জয়েনিং তারিখ</label>
                    <input type="date" name="s_join_date" id="modal_join" class="modal-input">
                </div>
                <div class="col-6">
                    <label class="modal-label" style="color:var(--sk-danger);">স্ট্যাটাস</label>
                    <select name="s_status" id="modal_status" class="modal-input">
                        <option value="Active">🟢 Active — হাজিরা খাতায় থাকবে</option>
                        <option value="Inactive">🔴 Inactive — হাজিরা থেকে বাদ পড়বে</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="modal-label">ঠিকানা</label>
                    <textarea name="s_address" id="modal_address" class="modal-input" rows="2" placeholder="সম্পূর্ণ ঠিকানা"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" name="update_staff" class="btn-app-primary w-100" style="font-size:14px;padding:13px;">
                        <i class="fa fa-floppy-disk me-1"></i>Update Staff Details
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Attendance Correction Modal -->
<div class="sk-modal-backdrop" id="attModal">
    <div class="sk-modal-box">
        <div class="sk-modal-header">
            <h5 style="color:var(--sk-success);"><i class="fa fa-calendar-check me-2"></i>হাজিরা সংশোধন</h5>
            <button class="sk-modal-close" onclick="closeAttModal()"><i class="fa fa-times"></i></button>
        </div>
        <div class="sk-modal-body">
            <div class="text-center mb-3">
                <div id="att_modal_name" style="font-size:16px;font-weight:900;text-transform:uppercase;"></div>
                <div style="font-size:10px;color:var(--sk-text-muted);margin-top:3px;">যেদিনের হাজিরা সংশোধন করবেন, সেই তারিখটি নির্বাচন করুন</div>
            </div>
            <form method="POST" class="row g-3">
                <input type="hidden" name="att_staff_id" id="att_modal_id">
                <div class="col-12">
                    <label class="modal-label">তারিখ (Date)</label>
                    <input type="date" name="att_date" class="modal-input" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-12">
                    <label class="modal-label">নতুন স্ট্যাটাস</label>
                    <select name="att_status" class="modal-input" required>
                        <option value="Present">✅ Present — উপস্থিত</option>
                        <option value="Absent">❌ Absent — অনুপস্থিত</option>
                        <option value="Half">🌗 Half Day — অর্ধদিবস</option>
                        <option value="Leave">🌴 Leave — ছুটি</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="modal-label">লেট (মিনিট)</label>
                    <input type="number" name="att_late" class="modal-input" placeholder="0" min="0" value="0">
                </div>
                <div class="col-6">
                    <label class="modal-label">ছুটির কারণ / নোট</label>
                    <input type="text" name="att_note" class="modal-input" placeholder="নোট লিখুন">
                </div>
                <div class="col-12">
                    <button type="submit" name="correct_attendance" class="btn-app-success w-100" style="font-size:14px;padding:13px;">
                        <i class="fa fa-floppy-disk me-1"></i>Save Changes & Update Balance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){const t=localStorage.getItem('sk_theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);document.addEventListener('DOMContentLoaded',function(){const i=document.getElementById('themeIcon');if(i)i.className=t==='dark'?'fas fa-sun':'fas fa-moon';});})();
function toggleTheme(){const c=document.documentElement.getAttribute('data-bs-theme');const n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-bs-theme',n);localStorage.setItem('sk_theme',n);document.getElementById('themeIcon').className=n==='dark'?'fas fa-sun':'fas fa-moon';}

function openEditModal(d){
    document.getElementById('modal_id').value=d.id;
    document.getElementById('modal_name').value=d.staff_name;
    document.getElementById('modal_email').value=d.staff_email;
    document.getElementById('modal_phone').value=d.staff_phone;
    document.getElementById('modal_salary').value=d.staff_salary;
    document.getElementById('modal_status').value=d.staff_status;
    document.getElementById('modal_address').value=d.staff_address||'';
    document.getElementById('modal_join').value=(d.staff_join_date||'').substring(0,10);
    document.getElementById('modal_pic_preview').src='../../Uploads/profiles/'+((d.profile_pic&&d.profile_pic!=='')?d.profile_pic:'default.png');
    document.getElementById('modal_photo').value='';
    document.getElementById('modal_photo_name').textContent='';
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal(){document.getElementById('editModal').classList.remove('show');}

/* নতুন ছবি সিলেক্ট করলে লাইভ প্রিভিউ */
document.getElementById('modal_photo').addEventListener('change',function(e){
    if(e.target.files&&e.target.files[0]){
        const f=e.target.files[0];
        document.getElementById('modal_photo_name').textContent='📷 '+f.name;
        const reader=new FileReader();
        reader.onload=ev=>{document.getElementById('modal_pic_preview').src=ev.target.result;};
        reader.readAsDataURL(f);
    }
});

function openAttModal(id,name){
    document.getElementById('att_modal_id').value=id;
    document.getElementById('att_modal_name').textContent=name;
    document.getElementById('attModal').classList.add('show');
}
function closeAttModal(){document.getElementById('attModal').classList.remove('show');}

document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)closeEditModal();});
document.getElementById('attModal').addEventListener('click',function(e){if(e.target===this)closeAttModal();});
</script>
</body>
</html>
