<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    session_unset(); session_destroy();
    echo "<script>alert('Session Expired!'); window.location.href='/index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo "<script>window.location.href='../../index.php';</script>"; exit;
}
include __DIR__ . '/../../db_connect.php';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'admin';
date_default_timezone_set('Asia/Dhaka');
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Add Staff — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../Assets/css/theme.css">
    <style>
        /* Photo Section */
        .photo-toggle-btn {
            width: 100%;
            background: var(--sk-surface, #1e293b);
            border: 2px dashed var(--sk-border, #334155);
            color: var(--sk-text-secondary, #94a3b8);
            border-radius: var(--sk-radius-sm, 10px);
            padding: 14px;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-bottom: 16px;
        }
        .photo-toggle-btn:hover, .photo-toggle-btn.active {
            border-color: var(--sk-primary, #7c5cfc);
            color: var(--sk-primary, #7c5cfc);
            background: rgba(124,92,252,0.08);
        }
        .photo-section {
            display: none;
            background: var(--sk-surface, #1e293b);
            border: 1px solid var(--sk-border, #334155);
            border-radius: var(--sk-radius-sm);
            padding: 16px;
            margin-bottom: 16px;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        #webcam-view {
            width: 100%; max-width: 260px;
            border-radius: 12px;
            border: 2px solid var(--sk-primary);
            display: none;
        }
        #photo-preview {
            width: 100px; height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--sk-primary);
            display: none;
            box-shadow: 0 0 0 6px rgba(124,92,252,0.15);
        }
        .cam-btns { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; width: 100%; }
        .cam-btn {
            flex: 1; min-width: 110px;
            background: var(--sk-card);
            border: 1px solid var(--sk-border);
            color: var(--sk-text);
            border-radius: var(--sk-radius-sm);
            padding: 9px 12px;
            font-size: 12px; font-weight: 800;
            cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .cam-btn-start  { background: #2196F3; border-color: #2196F3; color:#fff; }
        .cam-btn-shoot  { background: var(--sk-success); border-color: var(--sk-success); color:#fff; }
        .cam-btn:hover  { opacity: 0.85; transform: translateY(-1px); }
        .cam-btn:active { transform: scale(0.97); }

        /* Rejoin toggle */
        .custom-check-row {
            display: flex; align-items: center; gap: 10px;
            cursor: pointer; padding: 10px 0; margin-bottom: 12px;
        }
        .custom-check-box {
            width: 22px; height: 22px;
            border: 2px solid var(--sk-danger);
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; transition: all 0.2s;
        }
        .custom-check-box.checked { background: var(--sk-danger); }
        .custom-check-box i { color: #fff; font-size: 11px; display: none; }
        .custom-check-box.checked i { display: block; }
/* Form card */
        .form-section-title {
            font-size: 11px; font-weight: 900;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--sk-primary);
            padding: 0 0 10px;
            border-bottom: 1px solid var(--sk-border);
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="sk-navbar">
    <a href="../../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Back</a>
    <div class="sk-navbar-title">Add New Staff<span class="sk-navbar-subtitle">SADA KALO FASHION</span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>

<div class="page-wrap mt-3">

    <!-- Banner -->
    <div class="sk-banner animate-in">
        <img src="../../Assets/images/banner.jpg" alt="Banner" onerror="this.parentElement.style.background='linear-gradient(135deg,#7C5CFC,#5A3DD4)';this.style.display='none'">
    </div>

    <!-- Response Box -->
    <div id="responseBox" class="sk-response mb-3"></div>

    <!-- Form Card -->
    <div class="sk-card animate-in delay-1" style="padding:20px;">

        <div class="form-section-title"><i class="fa fa-user-plus"></i> Staff Basic Information</div>

        <form id="staffAddForm" enctype="multipart/form-data">

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6">
                    <label class="sk-label">স্টাফের নাম (Full Name)</label>
                    <div class="sk-input-group">
                        <i class="fa fa-user sk-icon"></i>
                        <input type="text" name="staff_name" class="sk-input" placeholder="Full Name" required>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="sk-label">ইমেইল (Email)</label>
                    <div class="sk-input-group">
                        <i class="fa fa-envelope sk-icon"></i>
                        <input type="email" name="staff_email" class="sk-input" placeholder="email@example.com" required>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="sk-label">ফোন নম্বর (Phone)</label>
                    <div class="sk-input-group">
                        <i class="fa fa-phone sk-icon"></i>
                        <input type="tel" name="staff_phone" class="sk-input" placeholder="+8801XXXXXXXXX" required>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="sk-label">মূল বেতন (Salary — Tk)</label>
                    <div class="sk-input-group">
                        <i class="fa fa-money-bill sk-icon"></i>
                        <input type="number" step="0.01" name="staff_salary" class="sk-input" placeholder="0.00" required>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="sk-label">জয়েনিং তারিখ (Join Date)</label>
                    <div class="sk-input-group">
                        <i class="fa fa-calendar sk-icon"></i>
                        <input type="date" name="staff_join_date" class="sk-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="col-12">
                    <label class="sk-label">ঠিকানা (Address)</label>
                    <textarea name="staff_address" class="sk-input" rows="2" placeholder="সম্পূর্ণ ঠিকানা লিখুন..." required style="padding-left:16px!important;"></textarea>
                </div>
            </div>

            <!-- ✅ Photo Section — Camera + Upload -->
            <div style="margin-bottom:14px;">
                <button type="button" id="togglePhotoSection"
                    style="width:100%;background:#1e293b;border:2px dashed #475569;color:#94a3b8;border-radius:10px;padding:13px;font-size:13px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;"
                    onmouseover="this.style.borderColor='#7c5cfc';this.style.color='#7c5cfc'"
                    onmouseout="this.style.borderColor='#475569';this.style.color='#94a3b8'">
                    <i class="fa fa-camera-retro"></i> প্রোফাইল ছবি যুক্ত করুন (ঐচ্ছিক)
                </button>
            </div>

            <div id="photoModule" style="display:none;background:#1e293b;border:1px solid #334155;border-radius:10px;padding:16px;margin-bottom:14px;text-align:center;">
                <video id="webcam-view" autoplay playsinline style="display:none;width:100%;max-width:260px;border-radius:10px;border:2px solid #7c5cfc;margin-bottom:10px;"></video>
                <canvas id="photo-canvas" style="display:none;"></canvas>
                <div id="photo-preview-wrap" style="display:none;margin-bottom:10px;">
                    <img id="photo-preview" src="" alt="Preview" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid #7c5cfc;">
                </div>
                <input type="hidden" name="captured_image" id="captured_image">
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                    <button type="button" id="start-cam"
                        style="background:#2196F3;color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:12px;font-weight:800;cursor:pointer;">
                        <i class="fa fa-camera"></i> ক্যামেরা চালু
                    </button>
                    <button type="button" id="take-photo" style="display:none;background:#10b981;color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:12px;font-weight:800;cursor:pointer;">
                        <i class="fa fa-circle"></i> ছবি তুলুন
                    </button>
                    <button type="button" id="retake-photo" style="display:none;background:#64748b;color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:12px;font-weight:800;cursor:pointer;">
                        <i class="fa fa-rotate-right"></i> আবার তুলুন
                    </button>
                    <input type="file" name="staff_photo" id="file-upload" accept="image/*" style="display:none;">
                    <button type="button" onclick="document.getElementById('file-upload').click()"
                        style="background:#334155;color:#94a3b8;border:1px solid #475569;border-radius:8px;padding:9px 16px;font-size:12px;font-weight:800;cursor:pointer;">
                        <i class="fa fa-upload"></i> গ্যালারি থেকে
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-app-success w-100 mt-2" id="submitBtn" style="font-size:15px; padding:15px;">
                <i class="fa fa-floppy-disk"></i> SAVE STAFF INFO
            </button>
        </form>
    </div>

    <div style="height:20px;"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
/* Theme */
(function(){
    const t=localStorage.getItem('sk_theme')||'dark';
    document.documentElement.setAttribute('data-bs-theme',t);
    document.addEventListener('DOMContentLoaded',function(){
        const i=document.getElementById('themeIcon');
        if(i) i.className=t==='dark'?'fas fa-sun':'fas fa-moon';
    });
})();
function toggleTheme(){
    const c=document.documentElement.getAttribute('data-bs-theme');
    const n=c==='dark'?'light':'dark';
    document.documentElement.setAttribute('data-bs-theme',n);
    localStorage.setItem('sk_theme',n);
    document.getElementById('themeIcon').className=n==='dark'?'fas fa-sun':'fas fa-moon';
}

/* Photo toggle */
$('#togglePhotoSection').on('click',function(){
    const mod = document.getElementById('photoModule');
    const isHidden = mod.style.display === 'none' || mod.style.display === '';
    mod.style.display = isHidden ? 'block' : 'none';
    this.innerHTML = isHidden
        ? '<i class="fa fa-times"></i> ছবি অপশন বন্ধ করুন'
        : '<i class="fa fa-camera-retro"></i> প্রোফাইল ছবি যুক্ত করুন (ঐচ্ছিক)';
    if (isHidden) {
        this.style.borderColor = '#7c5cfc';
        this.style.color = '#7c5cfc';
    } else {
        this.style.borderColor = '#475569';
        this.style.color = '#94a3b8';
    }
});

/* Webcam */
const video=document.getElementById('webcam-view');
const canvas=document.getElementById('photo-canvas');
const preview=document.getElementById('photo-preview');
const capturedInput=document.getElementById('captured_image');
let streamRef=null;

document.getElementById('start-cam').addEventListener('click',async()=>{
    try{
        streamRef=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'}});
        video.srcObject=streamRef; video.style.display='block'; preview.style.display='none';
        document.getElementById('take-photo').style.display='inline-flex';
        document.getElementById('start-cam').style.display='none';
        document.getElementById('retake-photo').style.display='none';
    }catch(e){alert('ক্যামেরা পারমিশন পাওয়া যায়নি!');}
});
document.getElementById('take-photo').addEventListener('click',()=>{
    canvas.width=video.videoWidth; canvas.height=video.videoHeight;
    canvas.getContext('2d').drawImage(video,0,0);
    const url=canvas.toDataURL('image/jpeg');
    capturedInput.value=url; preview.src=url;
    video.style.display='none';
    document.getElementById('photo-preview-wrap').style.display='block';
    preview.style.display='block';
    document.getElementById('take-photo').style.display='none';
    document.getElementById('retake-photo').style.display='inline-flex';
    if(streamRef) streamRef.getTracks().forEach(t=>t.stop());
});
document.getElementById('retake-photo').addEventListener('click',()=>{
    capturedInput.value='';
    document.getElementById('photo-preview-wrap').style.display='none';
    document.getElementById('start-cam').click();
});
document.getElementById('file-upload').addEventListener('change',function(e){
    if(e.target.files&&e.target.files[0]){
        const reader=new FileReader();
        reader.onload=ev=>{
            preview.src=ev.target.result;
            document.getElementById('photo-preview-wrap').style.display='block';
            preview.style.display='block';
            video.style.display='none'; capturedInput.value='';
            if(streamRef) streamRef.getTracks().forEach(t=>t.stop());
            document.getElementById('take-photo').style.display='none';
            document.getElementById('start-cam').style.display='inline-flex';
            document.getElementById('retake-photo').style.display='none';
        };
        reader.readAsDataURL(e.target.files[0]);
    }
});

/* AJAX Submit */
$('#staffAddForm').on('submit',function(e){
    e.preventDefault();
    const btn=$('#submitBtn');
    const resBox=$('#responseBox');
    const orig=btn.html();
    btn.html('<i class="fa fa-spinner fa-spin me-2"></i>Processing...').prop('disabled',true);
    $.ajax({
        url:'SaveAjax.php', type:'POST',
        data:new FormData(this), contentType:false, processData:false,
        success:function(res){
            resBox.show();
            if(res.status==='success'){
                resBox.removeClass('error').addClass('success').html('<i class="fa fa-check-circle me-2"></i>'+res.message);
                $('#staffAddForm')[0].reset(); preview.style.display='none'; capturedInput.value='';
                $('#photoModule').hide(); $('#togglePhotoSection').removeClass('active').html('<i class="fa fa-camera-retro"></i> প্রোফাইল ছবি যুক্ত করুন (ঐচ্ছিক)');
            }else{
                resBox.removeClass('success').addClass('error').html('<i class="fa fa-triangle-exclamation me-2"></i>'+res.message);
            }
            btn.html(orig).prop('disabled',false);
            setTimeout(()=>resBox.fadeOut(),6000);
        },
        error:function(){
            resBox.show().removeClass('success').addClass('error').html('<i class="fa fa-wifi me-2"></i>সার্ভার বা ডাটাবেস এরর!');
            btn.html(orig).prop('disabled',false);
        }
    });
});
</script>
</body>
</html>
