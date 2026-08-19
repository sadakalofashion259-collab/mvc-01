<?php
declare(strict_types=1);

if(isset($_POST['verify_admin_action_pass'])){
    session_start();
    require_once '../db_connect.php';
    require_once '../Controllers/AdminController.php';

    $adminCtrl = new AdminController($conn);
    $pass = trim($_POST['pass']);
    $uname = $_SESSION['username'] ?? '';
    
    if ($adminCtrl->verifyActionAuth($uname, $pass)) {
        echo 'ok';
    } else {
        echo 'fail';
    }
    exit;
}
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
async function requireAdminAuth(callback) {
    const { value: password } = await Swal.fire({
        title: '<i class="fas fa-shield-alt" style="color:#ef4444;"></i> অ্যাডমিন ভেরিফিকেশন',
        html: '<p style="font-size:13px; color:#64748b; font-weight:bold;">এই কাজটি করার জন্য আপনার <b>অ্যাকশন পাসওয়ার্ড</b> দিন:</p>',
        input: 'password',
        inputPlaceholder: 'পাসওয়ার্ড লিখুন...',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'নিশ্চিত করুন',
        cancelButtonText: 'বাতিল',
        footer: '<a href="reset_action_otp.php" style="color:#3b82f6; font-size:13px; font-weight:900; text-decoration:none;"><i class=\"fas fa-key\"></i> পাসওয়ার্ড ভুলে গেছেন?</a>'
    });

    if (password) {
        let formData = new FormData();
        formData.append('verify_admin_action_pass', '1');
        formData.append('pass', password);
        
        try {
            let response = await fetch('admin_action_auth.php', { method: 'POST', body: formData });
            let result = await response.text();
            
            if(result.trim() === 'ok') { callback(); } 
            else { Swal.fire('ভুল পাসওয়ার্ড!', 'আপনার দেওয়া পাসওয়ার্ডটি সঠিক নয়।', 'error'); }
        } catch (e) {
            Swal.fire('এরর!', 'ইন্টারনেট বা সার্ভার সমস্যা।', 'error');
        }
    }
}
</script>
