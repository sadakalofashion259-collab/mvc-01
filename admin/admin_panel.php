<?php
declare(strict_types=1);
session_start();

// এররগুলো যেন সাদা পেজ না দেখিয়ে স্ক্রিনে দেখায় (ডিবাগিংয়ের জন্য)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// সঠিক ফোল্ডার পাথ
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../Controllers/AdminController.php';

// অ্যাডমিন ছাড়া অন্য কাউকে ঢুকতে দেওয়া হবে না
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$adminCtrl  = new AdminController($conn);
$uid        = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
$status_msg = "";

// CSRF টোকেন তৈরি করা (সিকিউরিটির জন্য)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==========================================
// টাইমার কলাম আছে কিনা যাচাই + মেয়াদোত্তীর্ণ রোল অটো-রিভার্ট
// ==========================================
$hasTimerCols = false;
try {
    $conn->query("SELECT prev_role, role_expires_at FROM users LIMIT 1");
    $hasTimerCols = true;
} catch (Exception $e) {
    $hasTimerCols = false;
}
if ($hasTimerCols) {
    try {
        // সময় শেষ হয়ে যাওয়া টেম্প-রোলগুলো আগের রোলে ফিরিয়ে দেওয়া (লেজি ক্রন)
        $conn->exec("UPDATE users
                     SET role = prev_role, prev_role = NULL, role_expires_at = NULL
                     WHERE role_expires_at IS NOT NULL
                       AND role_expires_at <= NOW()
                       AND prev_role IS NOT NULL");
    } catch (Exception $e) { /* ignore */ }
}

// ==========================================
// AJAX: রোল পরিবর্তন (পেজ রিলোড ছাড়া)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_role') {
    header('Content-Type: application/json; charset=utf-8');

    // CSRF ভেরিফিকেশন
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'msg' => 'সিকিউরিটি ত্রুটি! পেজ রিফ্রেশ করুন।']);
        exit;
    }

    $target_id = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);
    $new_role  = trim((string)($_POST['role'] ?? ''));
    $duration  = (int) filter_var($_POST['duration'] ?? 0, FILTER_VALIDATE_INT); // মিনিটে
    if ($duration < 0)     { $duration = 0; }
    if ($duration > 43200) { $duration = 43200; } // সর্বোচ্চ ৩০ দিন
    $allowed   = ['staff', 'manager', 'viewer', 'admin'];

    if (!$target_id || !in_array($new_role, $allowed, true)) {
        echo json_encode(['ok' => false, 'msg' => 'ভুল তথ্য পাঠানো হয়েছে।']);
        exit;
    }

    try {
        $cols = $hasTimerCols ? "username, role, prev_role, role_expires_at" : "username, role";
        $q = $conn->prepare("SELECT $cols FROM users WHERE id = ?");
        $q->execute([$target_id]);
        $target = $q->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            echo json_encode(['ok' => false, 'msg' => 'ইউজার খুঁজে পাওয়া যায়নি।']);
            exit;
        }
        // নিজের রোল ও সুপার অ্যাডমিন পরিবর্তন করা যাবে না
        if ($target_id === $uid) {
            echo json_encode(['ok' => false, 'msg' => 'নিজের রোল পরিবর্তন করা যাবে না!']);
            exit;
        }
        if (($target['username'] ?? '') === '{-ADMIN-}') {
            echo json_encode(['ok' => false, 'msg' => 'সুপার অ্যাডমিন সুরক্ষিত।']);
            exit;
        }

        $expires_iso = null;

        if ($hasTimerCols && $duration > 0) {
            // টেম্পোরারি রোল — সময় শেষে যেখানে ফিরবে (আগে টেম্প থাকলে সেই আসল prev_role রাখি)
            $base_prev = !empty($target['prev_role']) ? $target['prev_role'] : ($target['role'] ?? 'staff');
            if ($base_prev === $new_role) { $base_prev = $target['role'] ?? 'staff'; }

            $expires_at  = date('Y-m-d H:i:s', time() + $duration * 60);
            $expires_iso = date('c', time() + $duration * 60);
            $stmt = $conn->prepare("UPDATE users SET role = ?, prev_role = ?, role_expires_at = ? WHERE id = ?");
            $stmt->execute([$new_role, $base_prev, $expires_at, $target_id]);

            $note = "'{$target['username']}' এখন {$duration} মিনিটের জন্য " . ucfirst($new_role)
                  . " — সময় শেষে " . ucfirst($base_prev) . " এ ফিরে যাবে।";
        } else {
            // স্থায়ী পরিবর্তন — টাইমার/prev_role মুছে দেই
            if ($hasTimerCols) {
                $stmt = $conn->prepare("UPDATE users SET role = ?, prev_role = NULL, role_expires_at = NULL WHERE id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            }
            $stmt->execute([$new_role, $target_id]);

            $note = $new_role === 'admin'
                ? "'{$target['username']}' এখন পূর্ণ অ্যাডমিন (ডিলিট ছাড়া সব করতে পারবে)।"
                : "'{$target['username']}' এর রোল এখন " . ucfirst($new_role) . "।";
        }

        echo json_encode(['ok' => true, 'msg' => '✅ ' . $note, 'role' => $new_role, 'expires' => $expires_iso]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'ডাটাবেজ এরর: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================
// ফর্ম সাবমিশন: ইউজার তৈরি বা আপডেট
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF ভেরিফিকেশন
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $status_msg = "❌ সিকিউরিটি ত্রুটি (CSRF)! পেজ রিফ্রেশ করুন।";
    }
    elseif (isset($_POST['save_user'])) {
        $user    = htmlspecialchars(trim($_POST['username']));
        $pass    = trim($_POST['password']);
        $role    = htmlspecialchars($_POST['role']);
        $email   = !empty($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : null;
        $phone   = htmlspecialchars(trim($_POST['phone'] ?? ''));
        $mobile  = htmlspecialchars(trim($_POST['mobile'] ?? ''));
        $address = htmlspecialchars(trim($_POST['address'] ?? ''));
        $joining = date('Y-m-d');

        if (!empty($user)) {
            try {
                $conn->beginTransaction();
                $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$user]);

                if ($check->rowCount() > 0) {
                    // ---- বিদ্যমান ইউজার আপডেট ----
                    if (!empty($pass)) {
                        $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password=?, role=?, email=?, phone=?, mobile=?, address=? WHERE username=?");
                        $stmt->execute([$hashed_pass, $role, $email, $phone, $mobile, $address, $user]);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET role=?, email=?, phone=?, mobile=?, address=? WHERE username=?");
                        $stmt->execute([$role, $email, $phone, $mobile, $address, $user]);
                    }
                    $status_msg = "✅ ইউজার '$user' এর তথ্য সফলভাবে আপডেট হয়েছে!";
                } else {
                    // ---- নতুন ইউজার ইনসার্ট ----
                    if (empty($pass)) {
                        $status_msg = "❌ নতুন ইউজার তৈরির জন্য পাসওয়ার্ড বাধ্যতামূলক!";
                    } else {
                        $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
                        // is_verified = 0 রাখা হচ্ছে যেন সে OTP ভেরিফাই করে
                        $stmt = $conn->prepare("INSERT INTO users (username, password, role, email, phone, mobile, address, joining_date, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
                        $stmt->execute([$user, $hashed_pass, $role, $email, $phone, $mobile, $address, $joining]);
                        $status_msg = "✅ নতুন ইউজার '$user' সফলভাবে তৈরি হয়েছে! (OTP ভেরিফিকেশন বাকি)";
                    }
                }

                if ($conn->inTransaction()) { $conn->commit(); }
            } catch (Exception $e) {
                if ($conn->inTransaction()) { $conn->rollBack(); }
                $status_msg = "❌ ডাটাবেজ এরর: " . $e->getMessage();
            }
        }
    }
}

// ==========================================
// ডিলিট অ্যাকশন (GET Request)
// ==========================================
if (isset($_GET['del_user'])) {
    $del_id = filter_var($_GET['del_user'], FILTER_VALIDATE_INT);
    if ($del_id) {
        $adminCtrl->deleteUser($del_id, $uid);
        header("Location: admin_panel.php");
        exit;
    }
}

// ==========================================
// ডাটা ফেচিং (ভিউ লোড করার জন্য)
// ==========================================
try {
    // * ব্যবহার করা হচ্ছে যেন image/photo যেকোনো নামে থাকলেও নিতে পারে
    $users_list = $conn->query("SELECT * FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users_list = [];
}

// টেবিলের ছবির কলাম খুঁজে বের করার হেল্পার (profile_picture / profile_pic)
// ⚠️ admin_panel.php থাকে admin/ ফোল্ডারে, তাই রুট-রিলেটিভ পাথে '../' যোগ করতে হয়
function get_user_image(array $u): ?string {
    foreach (['profile_pic', 'profile_picture'] as $key) {
        if (empty($u[$key])) continue;

        $val = trim((string)$u[$key]);
        if ($val === '' || $val === 'default_user.png') continue;

        // পুরো URL (http / https / //) হলে সরাসরি
        if (preg_match('#^(https?:)?//#i', $val)) {
            return $val;
        }
        // শুরুর ./ বা / সরানো
        $val = ltrim($val, '/');
        if (str_starts_with($val, './')) { $val = substr($val, 2); }

        // ইতিমধ্যে ../ দিয়ে শুরু হলে হুবহু
        if (str_starts_with($val, '../')) {
            return $val;
        }
        // ফোল্ডারসহ পাথ (uploads/..) হলে ../ যোগ; শুধু ফাইলনেম হলে ../uploads/
        return str_contains($val, '/') ? '../' . $val : '../uploads/' . $val;
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ইউজার ম্যানেজমেন্ট — SADA KALO FASHION</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Hind Siliguri', sans-serif; }
        #userForm { transition: max-height 0.4s ease, opacity 0.3s ease; overflow: hidden; }
        .form-hidden  { max-height: 0; opacity: 0; }
        .form-visible { max-height: 900px; opacity: 1; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen pb-16">

    <div class="max-w-6xl mx-auto px-4 mt-6">

        <!-- হেডার -->
        <div class="flex flex-col sm:flex-row justify-between items-center bg-slate-900 text-white p-5 rounded-2xl shadow-lg mb-6 gap-4 border-b-4 border-emerald-500">
            <h1 class="text-lg sm:text-xl font-bold flex items-center gap-3 uppercase tracking-wide">
                <i class="fas fa-users-cog text-emerald-400"></i> ইউজার ম্যানেজমেন্ট
            </h1>
            <div class="flex gap-2 flex-wrap justify-center">
                <a href="master_control.php" class="bg-slate-700 hover:bg-slate-600 px-4 py-2.5 rounded-lg font-bold transition flex items-center gap-2 text-sm border border-slate-600">
                    <i class="fas fa-arrow-left"></i> ব্যাক
                </a>
                <a href="../dashboard.php" class="bg-emerald-600 hover:bg-emerald-500 px-4 py-2.5 rounded-lg font-bold transition flex items-center gap-2 text-sm">
                    <i class="fas fa-home"></i> হোম
                </a>
            </div>
        </div>

        <!-- স্ট্যাটাস মেসেজ -->
        <?php if ($status_msg): ?>
            <?php $is_err = strpos($status_msg, '❌') !== false; ?>
            <div class="p-4 rounded-xl shadow-sm mb-6 font-bold flex items-center gap-3 text-sm border-l-4
                <?php echo $is_err ? 'border-rose-500 text-rose-800 bg-rose-50' : 'border-emerald-500 text-emerald-800 bg-emerald-50'; ?>">
                <i class="fas <?php echo $is_err ? 'fa-times-circle text-rose-500' : 'fa-check-circle text-emerald-500'; ?> text-lg"></i>
                <?php echo $status_msg; ?>
            </div>
        <?php endif; ?>

        <!-- ইউজার প্যানেল -->
        <div class="bg-white p-5 rounded-2xl shadow-md border-t-4 border-emerald-500">

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-slate-200 pb-4 gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-user-plus text-emerald-500"></i> ইউজার তৈরি ও আপডেট
                    </h3>
                    <p class="text-xs text-slate-400 mt-1 font-medium">নতুন ইউজার যোগ করুন অথবা একই ইউজারনেম দিয়ে তথ্য আপডেট করুন।</p>
                </div>
                <button onclick="toggleUserForm()" class="w-full sm:w-auto bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-4 py-2.5 rounded-lg text-sm font-bold border border-emerald-200 transition flex items-center justify-center gap-2">
                    <i class="fas fa-plus-circle" id="toggleIcon"></i> <span id="toggleText">নতুন ইউজার ফর্ম দেখান</span>
                </button>
            </div>

            <!-- ফর্ম -->
            <form method="POST" id="userForm" class="form-hidden">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-5 mb-5">
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">ইউজারনেম <span class="text-rose-500">*</span></label>
                        <input type="text" name="username" required placeholder="ex: masum12" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">পাসওয়ার্ড <span class="text-[10px] text-slate-400 font-medium">(নতুন হলে বাধ্যতামূলক)</span></label>
                        <input type="text" name="password" placeholder="নতুন পাসওয়ার্ড" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">রোল <span class="text-rose-500">*</span></label>
                        <select name="role" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-bold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="staff">Staff</option>
                            <option value="manager">Manager</option>
                            <option value="viewer">Viewer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">ইমেইল</label>
                        <input type="email" name="email" placeholder="example@email.com" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">ফোন</label>
                        <input type="text" name="phone" placeholder="017XXXXXXXX" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">মোবাইল</label>
                        <input type="text" name="mobile" placeholder="018XXXXXXXX" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">ঠিকানা</label>
                        <input type="text" name="address" placeholder="ঠিকানা..." class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" name="save_user" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-8 rounded-lg transition text-sm shadow-md flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i> ইউজার সেভ করুন
                    </button>
                </div>
            </form>

            <!-- ইউজার তালিকা -->
            <div class="flex items-center justify-between mt-6 mb-3">
                <h4 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                    <i class="fas fa-list text-slate-400"></i> সব ইউজারের তালিকা
                </h4>
                <span class="bg-slate-100 text-slate-500 text-xs font-bold px-3 py-1 rounded-full">মোট: <?php echo count($users_list); ?> জন</span>
            </div>
            <p class="text-[11px] text-slate-400 font-medium mb-3 flex items-center gap-1.5">
                <i class="fas fa-info-circle text-emerald-400"></i> রোলের ড্রপডাউন থেকে সরাসরি Admin/Manager/Staff বানানো যাবে (পেজ রিলোড ছাড়া)। <b>পাশে টাইমার দিলে</b> ইউজার অস্থায়ীভাবে Admin হবে — সময় শেষে অটো আগের রোলে ফিরে যাবে। টাইমার না দিলে স্থায়ী।
            </p>

            <div class="overflow-x-auto bg-slate-50 border border-slate-200 rounded-xl">
                <table class="w-full text-left whitespace-nowrap">
                    <thead class="bg-slate-800 text-slate-100 text-[11px] uppercase font-black tracking-wide">
                        <tr>
                            <th class="p-3.5">ছবি</th>
                            <th class="p-3.5">ইউজারনেম</th>
                            <th class="p-3.5">ইমেইল</th>
                            <th class="p-3.5">ফোন</th>
                            <th class="p-3.5">মোবাইল</th>
                            <th class="p-3.5">রোল</th>
                            <th class="p-3.5">টাইমার</th>
                            <th class="p-3.5 text-center">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm">
                        <?php if (count($users_list) > 0): foreach ($users_list as $u): ?>
                        <?php $img = get_user_image($u); ?>
                        <tr class="hover:bg-white transition">
                            <!-- ছবি -->
                            <td class="p-3">
                                <?php if ($img): ?>
                                    <img src="<?php echo htmlspecialchars($img); ?>" alt="pic"
                                         class="w-10 h-10 rounded-full object-cover border-2 border-slate-200 shadow-sm"
                                         onerror="this.onerror=null;this.outerHTML='<div class=\'w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white font-black text-sm shadow-sm\'><?php echo strtoupper(mb_substr($u['username'] ?? '?', 0, 1)); ?></div>';">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white font-black text-sm shadow-sm">
                                        <?php echo strtoupper(mb_substr($u['username'] ?? '?', 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <!-- ইউজারনেম -->
                            <td class="p-3 font-bold text-slate-800"><?php echo htmlspecialchars($u['username'] ?? ''); ?></td>
                            <!-- ইমেইল -->
                            <td class="p-3 text-slate-600 text-xs">
                                <?php echo !empty($u['email']) ? htmlspecialchars($u['email']) : '<span class="text-slate-400 italic">নেই</span>'; ?>
                            </td>
                            <!-- ফোন -->
                            <td class="p-3 text-slate-600 text-xs">
                                <?php echo !empty($u['phone']) ? htmlspecialchars($u['phone']) : '<span class="text-slate-400 italic">নেই</span>'; ?>
                            </td>
                            <!-- মোবাইল -->
                            <td class="p-3 text-slate-600 text-xs">
                                <?php echo !empty($u['mobile']) ? htmlspecialchars($u['mobile']) : '<span class="text-slate-400 italic">নেই</span>'; ?>
                            </td>
                            <!-- রোল (ড্রপডাউন দিয়ে পরিবর্তনযোগ্য) -->
                            <td class="p-3">
                                <?php
                                    $role = strtolower($u['role'] ?? '');
                                    $is_locked = (($u['id'] ?? 0) == ($_SESSION['user_id'] ?? -1)) || (($u['username'] ?? '') === '{-ADMIN-}');
                                    $role_color = $role === 'admin'   ? 'bg-rose-100 text-rose-700 border-rose-200'
                                                : ($role === 'manager' ? 'bg-blue-100 text-blue-700 border-blue-200'
                                                : ($role === 'staff'   ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                                : 'bg-slate-200 text-slate-600 border-slate-300'));
                                ?>
                                <?php if ($is_locked): ?>
                                    <span class="inline-block px-2.5 py-1.5 rounded-lg text-[10px] font-black uppercase border <?php echo $role_color; ?>">
                                        <?php echo htmlspecialchars($u['role'] ?? ''); ?> <i class="fas fa-lock text-[8px] opacity-60"></i>
                                    </span>
                                <?php else: ?>
                                    <select data-id="<?php echo (int)$u['id']; ?>"
                                            data-prev="<?php echo htmlspecialchars($role); ?>"
                                            onchange="changeRole(this)"
                                            class="role-select cursor-pointer text-[11px] font-black uppercase border rounded-lg px-2 py-1.5 outline-none focus:ring-2 focus:ring-emerald-200 transition <?php echo $role_color; ?>">
                                        <?php foreach (['staff'=>'Staff','manager'=>'Manager','viewer'=>'Viewer','admin'=>'Admin'] as $val=>$lbl): ?>
                                            <option value="<?php echo $val; ?>" <?php echo $role === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <!-- টাইমার (অস্থায়ী রোল / সময় সেট) -->
                            <td class="p-3">
                                <?php
                                    $exp_raw = $hasTimerCols ? ($u['role_expires_at'] ?? null) : null;
                                    $exp_ts  = (!empty($exp_raw)) ? strtotime((string)$exp_raw) : 0;
                                    $prev_r  = $hasTimerCols ? ($u['prev_role'] ?? '') : '';
                                    $is_temp = $exp_ts > time();
                                ?>
                                <?php if ($is_locked): ?>
                                    <span class="text-slate-300 text-xs">—</span>
                                <?php elseif ($is_temp): ?>
                                    <div class="flex flex-col gap-1">
                                        <span class="countdown inline-flex items-center gap-1.5 bg-amber-100 text-amber-700 border border-amber-200 rounded-lg px-2 py-1 text-[11px] font-black"
                                              data-expires="<?php echo date('c', $exp_ts); ?>">
                                            <i class="fas fa-hourglass-half"></i> <span class="cd-text">--:--</span>
                                        </span>
                                        <span class="text-[10px] text-slate-400 font-semibold">→ <?php echo htmlspecialchars(ucfirst($prev_r ?: 'staff')); ?> এ ফিরবে</span>
                                    </div>
                                <?php elseif ($hasTimerCols): ?>
                                    <div class="flex items-center gap-1">
                                        <input type="number" min="1" placeholder="সময়" class="timer-val w-16 bg-slate-50 border border-slate-300 rounded-lg px-2 py-1.5 text-xs font-bold outline-none focus:border-emerald-500">
                                        <select class="timer-unit bg-slate-50 border border-slate-300 rounded-lg px-1 py-1.5 text-[11px] font-bold outline-none focus:border-emerald-500">
                                            <option value="1">মিনিট</option>
                                            <option value="60">ঘন্টা</option>
                                            <option value="1440">দিন</option>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-300 text-[10px] italic" title="prev_role ও role_expires_at কলাম যোগ করুন">○</span>
                                <?php endif; ?>
                            </td>
                            <!-- অ্যাকশন -->
                            <td class="p-3 text-center">
                                <?php if (($u['id'] ?? 0) != ($_SESSION['user_id'] ?? -1) && strtolower($u['role'] ?? '') !== 'admin' && ($u['username'] ?? '') !== '{-ADMIN-}'): ?>
                                    <a href="?del_user=<?php echo (int)$u['id']; ?>" onclick="return confirm('আপনি কি নিশ্চিত এই ইউজারকে ডিলিট করতে চান?');" class="text-rose-500 hover:bg-rose-100 p-2 rounded-lg transition inline-block" title="ডিলিট">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-300"><i class="fas fa-lock" title="সুরক্ষিত"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="8" class="p-6 text-center text-slate-400 font-semibold text-sm">কোনো ইউজার পাওয়া যায়নি।</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- টোস্ট নোটিফিকেশন -->
    <div id="toast" class="fixed top-5 left-1/2 -translate-x-1/2 z-50 hidden">
        <div id="toastBox" class="px-5 py-3 rounded-xl shadow-xl font-bold text-sm text-white flex items-center gap-2"></div>
    </div>

    <script>
        const CSRF_TOKEN = "<?php echo $_SESSION['csrf_token']; ?>";

        // রোলভিত্তিক রঙ (ড্রপডাউনের ক্লাস আপডেটের জন্য)
        const ROLE_COLORS = {
            admin:   'bg-rose-100 text-rose-700 border-rose-200',
            manager: 'bg-blue-100 text-blue-700 border-blue-200',
            staff:   'bg-emerald-100 text-emerald-700 border-emerald-200',
            viewer:  'bg-slate-200 text-slate-600 border-slate-300'
        };
        const ALL_COLOR_CLASSES = Object.values(ROLE_COLORS).join(' ').split(' ');

        function showToast(msg, ok = true) {
            const t = document.getElementById('toast');
            const box = document.getElementById('toastBox');
            box.className = 'px-5 py-3 rounded-xl shadow-xl font-bold text-sm text-white flex items-center gap-2 ' + (ok ? 'bg-emerald-600' : 'bg-rose-600');
            box.innerHTML = '<i class="fas ' + (ok ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i>' + msg;
            t.classList.remove('hidden');
            clearTimeout(window.__toastTimer);
            window.__toastTimer = setTimeout(() => t.classList.add('hidden'), 3500);
        }

        function changeRole(sel) {
            const id = sel.dataset.id;
            const newRole = sel.value;
            const prev = sel.dataset.prev;
            if (newRole === prev) return;

            // এই রো-এর টাইমার ইনপুট পড়ি (থাকলে)
            const row = sel.closest('tr');
            const valEl  = row ? row.querySelector('.timer-val') : null;
            const unitEl = row ? row.querySelector('.timer-unit') : null;
            const amount = valEl ? parseInt(valEl.value || '0', 10) : 0;
            const unit   = unitEl ? parseInt(unitEl.value || '1', 10) : 1;
            const duration = (amount > 0) ? amount * unit : 0; // মিনিটে

            let confirmMsg = '';
            if (newRole === 'admin' && duration > 0) {
                confirmMsg = "এই ইউজারকে " + amount + (unit===60?' ঘন্টা':unit===1440?' দিন':' মিনিট') + " এর জন্য অস্থায়ী Admin বানাবেন?\nসময় শেষে অটো আগের রোলে ফিরে যাবে।";
            } else if (newRole === 'admin') {
                confirmMsg = "এই ইউজারকে স্থায়ীভাবে পূর্ণ Admin বানাতে চান?\nসে ডিলিট ছাড়া সব করতে পারবে।";
            }
            if (confirmMsg && !confirm(confirmMsg)) { sel.value = prev; return; }

            sel.disabled = true;
            const body = new URLSearchParams({ action: 'change_role', csrf_token: CSRF_TOKEN, user_id: id, role: newRole, duration: duration });

            fetch('admin_panel.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        sel.dataset.prev = res.role;
                        sel.classList.remove(...ALL_COLOR_CLASSES);
                        (ROLE_COLORS[res.role] || '').split(' ').forEach(c => c && sel.classList.add(c));
                        showToast(res.msg, true);
                        // টাইমার সেট হলে সার্ভার-সাইড স্টেট দেখানোর জন্য রিলোড
                        if (res.expires) { setTimeout(() => location.reload(), 900); }
                    } else {
                        sel.value = prev;
                        showToast(res.msg || 'পরিবর্তন ব্যর্থ!', false);
                    }
                })
                .catch(() => { sel.value = prev; showToast('নেটওয়ার্ক এরর!', false); })
                .finally(() => { sel.disabled = false; });
        }

        // টেম্প-রোলের লাইভ কাউন্টডাউন (শূন্য হলে রিলোড → সার্ভার অটো-রিভার্ট)
        function tickCountdowns() {
            const now = Date.now();
            document.querySelectorAll('.countdown').forEach(el => {
                const exp = new Date(el.dataset.expires).getTime();
                let diff = Math.floor((exp - now) / 1000);
                const txt = el.querySelector('.cd-text');
                if (diff <= 0) { location.reload(); return; }
                const d = Math.floor(diff / 86400); diff %= 86400;
                const h = Math.floor(diff / 3600);  diff %= 3600;
                const m = Math.floor(diff / 60);
                const s = diff % 60;
                const p = n => String(n).padStart(2, '0');
                if (txt) txt.textContent = d > 0 ? (d + 'দিন ' + p(h) + ':' + p(m)) : (p(h) + ':' + p(m) + ':' + p(s));
            });
        }
        setInterval(tickCountdowns, 1000);
        tickCountdowns();

        function toggleUserForm() {
            const form = document.getElementById('userForm');
            const icon = document.getElementById('toggleIcon');
            const text = document.getElementById('toggleText');
            if (form.classList.contains('form-hidden')) {
                form.classList.replace('form-hidden', 'form-visible');
                icon.classList.replace('fa-plus-circle', 'fa-minus-circle');
                text.innerText = 'ফর্ম লুকান';
            } else {
                form.classList.replace('form-visible', 'form-hidden');
                icon.classList.replace('fa-minus-circle', 'fa-plus-circle');
                text.innerText = 'নতুন ইউজার ফর্ম দেখান';
            }
        }
    </script>
</body>
</html>
