<?php
declare(strict_types=1);
session_start();

require_once '../db_connect.php';
require_once '../Controllers/AdminController.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') { 
    header("Location: ../dashboard.php"); exit; 
}

$adminCtrl = new AdminController($conn);
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_block_time'])) {
    $uid = (int)$_POST['time_user_id'];
    $end = $_POST['block_end']; 
    if ($adminCtrl->setTimedBlock($uid, $end)) {
        $success_msg = "✅ নির্দিষ্ট সময়ের জন্য ইউজার ব্লক করা হয়েছে!";
    } else {
        $success_msg = "❌ এরর! টাইম ব্লক সেট করা যায়নি।";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_block'])) {
    $uid = (int)$_POST['user_id'];
    $currentStatus = $_POST['current_status'];
    if ($adminCtrl->toggleUserStatus($uid, $currentStatus)) {
        $success_msg = ($currentStatus === 'blocked') ? "✅ ইউজার আনব্লক হয়েছে!" : "🚫 ইউজার ব্লক হয়েছে!";
    } else {
        $success_msg = "❌ এরর! স্ট্যাটাস পরিবর্তন করা যায়নি।";
    }
}

$current_time = time();
$users_list = $conn->query("SELECT * FROM users WHERE role != 'admin' ORDER BY last_active DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ইউজার কন্ট্রোল | SADA KALO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Hind Siliguri', sans-serif; }</style>
</head>
<body class="bg-slate-100 pb-10">
    <div class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-lg sticky top-0 z-50 border-b-4 border-purple-500">
        <a href="master_control.php" class="bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg text-sm font-bold transition"><i class="fas fa-arrow-left"></i> ব্যাক</a>
        <h1 class="font-bold text-lg">ইউজার কন্ট্রোল</h1>
        <a href="../dashboard.php" class="bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded-lg text-sm font-bold transition"><i class="fas fa-home"></i> হোম</a>
    </div>

    <div class="max-w-4xl mx-auto p-4 mt-4">
        <?php if($success_msg): ?>
            <div class="bg-emerald-100 text-emerald-800 p-3 rounded-lg mb-4 font-bold text-center border border-emerald-300 shadow-sm text-sm"><?= $success_msg ?></div>
        <?php endif; ?>

        <div class="space-y-4">
            <?php foreach($users_list as $user): 
                $last_act = strtotime($user['last_active'] ?? '2000-01-01 00:00:00');
                $is_online = ($last_act > ($current_time - 1200)); 
            ?>
            <div class="bg-white p-4 rounded-2xl shadow-md border-l-8 <?= $is_online ? 'border-emerald-500' : 'border-rose-500' ?> flex justify-between items-center transition hover:shadow-lg">
                <div>
                    <h3 class="font-bold text-lg text-slate-800"><?= htmlspecialchars($user['username']) ?></h3>
                    <p class="text-xs font-bold mt-1 <?= $is_online ? 'text-emerald-600' : 'text-slate-400' ?>">
                        <i class="fas fa-circle text-[10px] <?= $is_online ? 'animate-pulse' : '' ?>"></i> <?= $is_online ? 'অনলাইনে আছেন' : 'অফলাইন' ?>
                    </p>
                    <?php if($user['status'] == 'blocked' && !empty($user['block_end'])): ?>
                        <p class="text-[10px] text-rose-500 font-bold mt-1">🕒 আনব্লক হবে: <?= date('d M, h:i A', strtotime($user['block_end'])) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="flex gap-2">
                    <button type="button" onclick="openTimeModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')" class="bg-blue-500 hover:bg-blue-600 text-white w-12 h-12 rounded-xl flex items-center justify-center shadow-md transition transform active:scale-95 cursor-pointer">
                        <i class="fas fa-clock text-lg"></i>
                    </button>
                    <form method="POST" class="m-0">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="current_status" value="<?= $user['status'] ?>">
                        <button type="submit" name="toggle_block" class="w-12 h-12 rounded-xl flex items-center justify-center shadow-md transition transform active:scale-95 text-white <?= $user['status'] == 'blocked' ? 'bg-emerald-500 hover:bg-emerald-600' : 'bg-rose-500 hover:bg-rose-600' ?> cursor-pointer">
                            <i class="fas <?= $user['status'] == 'blocked' ? 'fa-unlock' : 'fa-ban' ?> text-lg"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="timeModal" class="hidden fixed inset-0 bg-black/80 z-[100] flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm p-6 shadow-2xl border-t-8 border-purple-500">
            <h3 id="modal_user_name" class="font-bold text-xl text-purple-700 mb-4 border-b pb-3">সময় সেট করুন</h3>
            <form method="POST">
                <input type="hidden" name="time_user_id" id="time_user_id">
                <label class="block text-sm font-bold text-slate-700 mb-2">কখন ব্লক খুলে যাবে?</label>
                <input type="datetime-local" name="block_end" required class="w-full border-2 border-slate-300 p-3 rounded-xl mb-6 font-bold text-slate-700 outline-none focus:border-purple-500 bg-slate-50">
                <div class="flex gap-3">
                    <button type="button" onclick="closeTimeModal()" class="flex-1 bg-slate-500 hover:bg-slate-600 text-white py-3 rounded-xl font-bold cursor-pointer">বাতিল</button>
                    <button type="submit" name="save_block_time" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-xl font-bold cursor-pointer">সেভ করুন</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openTimeModal(id, name) {
            document.getElementById('time_user_id').value = id;
            document.getElementById('modal_user_name').innerText = `🕒 ${name} এর সময় সেট`;
            document.getElementById('timeModal').classList.remove('hidden');
        }
        function closeTimeModal() {
            document.getElementById('timeModal').classList.add('hidden');
        }
    </script>
</body>
</html>
