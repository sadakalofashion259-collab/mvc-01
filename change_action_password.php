<?php
declare(strict_types=1);
ob_start();
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../index.php"); 
    exit; 
}

$adminUsername = $_SESSION['username'];
$alertMessage = "";
$alertType = "";

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

if (isset($_POST['update_login_pass'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $currentPass = $_POST['current_pass'] ?? '';
    $newPass = $_POST['new_pass'] ?? '';
    $confirmPass = $_POST['confirm_pass'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $alertMessage = "সিকিউরিটি ত্রুটি! পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।";
        $alertType = "error";
    } elseif (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $alertMessage = "দয়া করে সব ফিল্ড পূরণ করুন!";
        $alertType = "error";
    } elseif ($newPass !== $confirmPass) {
        $alertMessage = "নতুন পাসওয়ার্ড দুটি মিলেনি!";
        $alertType = "error";
    } else {
        try {
            $stmt = $conn->prepare("SELECT action_pass, password FROM users WHERE username = ? AND role = 'admin'");
            $stmt->execute([$adminUsername]);
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            $passToCheck = !empty($adminData['action_pass']) ? $adminData['action_pass'] : $adminData['password'];

            if ($adminData && (password_verify($currentPass, $passToCheck) || $currentPass === $passToCheck)) {
                $hashedPass = password_hash($newPass, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare("UPDATE users SET action_pass = ? WHERE username = ? AND role = 'admin'");
                $updateStmt->execute([$hashedPass, $adminUsername]);
                $alertMessage = "অ্যাডমিন অ্যাকশন পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে!";
                $alertType = "success";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $alertMessage = "আপনার বর্তমান পাসওয়ার্ডটি সঠিক নয়!";
                $alertType = "error";
            }
        } catch (PDOException $e) {
            $alertMessage = "সিস্টেম এরর!";
            $alertType = "error";
        }
    }
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অ্যাডমিন পাসওয়ার্ড পরিবর্তন</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #e2e8f0; color: #1e293b; margin: 0; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; width: 100%; max-width: 400px; padding: 30px 20px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 2px solid #cbd5e1; }
        .header-icon { width: 60px; height: 60px; background: #eff6ff; color: #3b82f6; font-size: 25px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 15px; border: 2px solid #bfdbfe; }
        h2 { text-align: center; margin: 0 0 5px; font-size: 20px; font-weight: 900; color: #1e293b; text-transform: uppercase; }
        p.subtitle { text-align: center; font-size: 12px; color: #64748b; margin-bottom: 25px; font-weight: 600; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 800; color: #475569; margin-bottom: 5px; text-transform: uppercase; }
        .inp { width: 100%; padding: 14px; border: 2px solid #e2e8f0; background: #f8fafc; color: #1e293b; border-radius: 10px; font-size: 15px; outline: none; font-weight: bold; transition: 0.2s; text-align: center; letter-spacing: 2px; }
        .inp:focus { border-color: #3b82f6; background: #fff; }
        .forgot-link { display: block; text-align: right; font-size: 12px; font-weight: 800; color: #ef4444; text-decoration: none; margin-top: 5px; margin-bottom: 15px; transition: 0.2s;}
        .forgot-link:hover { text-decoration: underline; color: #b91c1c;}
        .btn-update { width: 100%; background: #3b82f6; box-shadow: 0 4px 0 #2563eb; color: #fff; border: none; padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 900; cursor: pointer; text-transform: uppercase; margin-top: 10px; transition: 0.1s; }
        .btn-update:active { transform: translateY(4px); box-shadow: none; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #64748b; font-size: 13px; font-weight: bold; text-decoration: none; }
        .back-link:hover { text-decoration: underline; color: #1e293b;}
    </style>
</head>
<body>
    <div class="card">
        <div class="header-icon"><i class="fas fa-lock"></i></div>
        <h2>অ্যাডমিন পাসওয়ার্ড</h2>
        <p class="subtitle">লগইন, ডিলিট এবং এডিট করার মাস্টার পাসওয়ার্ড পরিবর্তন করুন</p>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-group"><label>বর্তমান পাসওয়ার্ড</label><input type="password" name="current_pass" class="inp" placeholder="••••••••" required></div>
            <div class="form-group"><label>নতুন পাসওয়ার্ড</label><input type="password" name="new_pass" class="inp" placeholder="••••••••" required></div>
            <div class="form-group"><label>পুনরায় নতুন পাসওয়ার্ড দিন</label><input type="password" name="confirm_pass" class="inp" placeholder="••••••••" required></div>
            <a href="sadakalo_full_admin_rest_otp.php" class="forgot-link"><i class="fas fa-key"></i> পাসওয়ার্ড ভুলে গেছেন?</a>
            <button type="submit" name="update_login_pass" class="btn-update"><i class="fas fa-save"></i> পাসওয়ার্ড আপডেট করুন</button>
        </form>
        <a href="master_control.php" class="back-link"><i class="fas fa-arrow-left"></i> মাস্টার হাবে ফিরে যান</a>
    </div>
    <?php if ($alertMessage !== ""): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({ icon: '<?= $alertType ?>', title: '<?= $alertType === "success" ? "সফল!" : "ত্রুটি!" ?>', text: '<?= $alertMessage ?>', confirmButtonColor: '<?= $alertType === "success" ? "#10b981" : "#ef4444" ?>' });
            });
        </script>
    <?php endif; ?>
</body>
</html>
