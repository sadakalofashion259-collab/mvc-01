<?php
declare(strict_types=1);

// ── Production Error Handling (SECURITY FIX: was display_errors=1) ───────────
error_reporting(E_ALL);
ini_set('display_errors', '0');   // NEVER expose errors in production
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');       // log to server error_log instead

session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../Controllers/AdminController.php';
require_once __DIR__ . '/auto_block_engine.php';

// ── Auth Guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// ── Lazy Cron: Auto-Block Engine ──────────────────────────────────────────────
runAutoBlockEngine($conn);

// ── Session Data ──────────────────────────────────────────────────────────────
$adminCtrl  = new AdminController($conn);
// SECURITY FIX: default was 1 (could accidentally protect user id=1). Use 0 = nobody.
$sessionUid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$status_msg = '';

// ── CSRF Token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Whitelist: allowed roles ──────────────────────────────────────────────────
const ADMIN_ALLOWED_ROLES = ['staff', 'manager', 'viewer', 'admin'];

// ── Timer columns detection (lazy schema check) ───────────────────────────────
$hasTimerCols = false;
try {
    $conn->query('SELECT `prev_role`, `role_expires_at` FROM `users` LIMIT 1');
    $hasTimerCols = true;
} catch (Throwable) { /* columns not yet migrated */ }

// ── Expired temp-role revert (Lazy Cron) ─────────────────────────────────────
if ($hasTimerCols) {
    try {
        $conn->exec("
            UPDATE `users`
            SET    `role`            = `prev_role`,
                   `prev_role`       = NULL,
                   `role_expires_at` = NULL
            WHERE  `role_expires_at`  IS NOT NULL
              AND  `role_expires_at`  <= NOW()
              AND  `prev_role`        IS NOT NULL
        ");
    } catch (Throwable) { /* non-fatal */ }
}

// ── Output escape helper ──────────────────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ============================================================================
// AJAX: Role Change
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_role') {
    header('Content-Type: application/json; charset=utf-8');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'msg' => 'সিকিউরিটি ত্রুটি! পেজ রিফ্রেশ করুন।']);
        exit;
    }

    $targetId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);
    $newRole  = trim((string)($_POST['role'] ?? ''));
    $duration = (int) max(0, min(43200, (int)($_POST['duration'] ?? 0))); // 0–43200 min

    if (!$targetId || $targetId <= 0 || !in_array($newRole, ADMIN_ALLOWED_ROLES, true)) {
        echo json_encode(['ok' => false, 'msg' => 'ভুল তথ্য পাঠানো হয়েছে।']);
        exit;
    }

    try {
        $cols = $hasTimerCols
            ? '`id`, `username`, `role`, `prev_role`, `role_expires_at`'
            : '`id`, `username`, `role`';

        $q = $conn->prepare("SELECT {$cols} FROM `users` WHERE `id` = ? LIMIT 1");
        $q->execute([$targetId]);
        $target = $q->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            echo json_encode(['ok' => false, 'msg' => 'ইউজার খুঁজে পাওয়া যায়নি।']);
            exit;
        }
        if ((int)$targetId === $sessionUid) {
            echo json_encode(['ok' => false, 'msg' => 'নিজের রোল পরিবর্তন করা যাবে না!']);
            exit;
        }
        if (($target['username'] ?? '') === '{-ADMIN-}') {
            echo json_encode(['ok' => false, 'msg' => 'সুপার অ্যাডমিন সুরক্ষিত।']);
            exit;
        }

        $expiresIso = null;

        if ($hasTimerCols && $duration > 0) {
            $basePrev = !empty($target['prev_role']) ? $target['prev_role'] : ($target['role'] ?? 'staff');
            if ($basePrev === $newRole) { $basePrev = $target['role'] ?? 'staff'; }
            $expiresAt  = date('Y-m-d H:i:s', time() + $duration * 60);
            $expiresIso = date('c',            time() + $duration * 60);
            $conn->prepare('UPDATE `users` SET `role` = ?, `prev_role` = ?, `role_expires_at` = ? WHERE `id` = ?')
                 ->execute([$newRole, $basePrev, $expiresAt, $targetId]);
            $note = "'{$target['username']}' এখন {$duration} মিনিটের জন্য "
                  . ucfirst($newRole) . " — সময় শেষে " . ucfirst($basePrev) . " এ ফিরবে।";
        } else {
            if ($hasTimerCols) {
                $conn->prepare('UPDATE `users` SET `role` = ?, `prev_role` = NULL, `role_expires_at` = NULL WHERE `id` = ?')
                     ->execute([$newRole, $targetId]);
            } else {
                $conn->prepare('UPDATE `users` SET `role` = ? WHERE `id` = ?')
                     ->execute([$newRole, $targetId]);
            }
            $note = $newRole === 'admin'
                ? "'{$target['username']}' এখন পূর্ণ অ্যাডমিন।"
                : "'{$target['username']}' এর রোল এখন " . ucfirst($newRole) . "।";
        }

        echo json_encode(['ok' => true, 'msg' => '✅ ' . $note, 'role' => $newRole, 'expires' => $expiresIso]);

    } catch (Throwable $e_) {
        // SECURITY FIX: log error internally, never expose DB details to client
        error_log('[AdminPanel] Role change error: ' . $e_->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'ডাটাবেজ এরর! পুনরায় চেষ্টা করুন।']);
    }
    exit;
}

// ============================================================================
// POST: Save User (Create or Update)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $status_msg = '❌ সিকিউরিটি ত্রুটি (CSRF)! পেজ রিফ্রেশ করুন।';
    } else {
        // SECURITY FIX: htmlspecialchars belongs at OUTPUT, NOT at DB storage.
        // Raw trimmed values go into prepared statements safely.
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = trim($_POST['role']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $phone    = trim($_POST['phone']    ?? '');
        $mobile   = trim($_POST['mobile']   ?? '');
        $address  = trim($_POST['address']  ?? '');
        $joining  = date('Y-m-d');

        // ── Validation ───────────────────────────────────────────────────────
        if ($username === '') {
            $status_msg = '❌ ইউজারনেম বাধ্যতামূলক।';
        } elseif (mb_strlen($username) > 50) {
            $status_msg = '❌ ইউজারনেম সর্বোচ্চ ৫০ অক্ষর।';
        } elseif (!in_array($role, ADMIN_ALLOWED_ROLES, true)) {
            // SECURITY FIX: role whitelist check was missing in the form handler
            $status_msg = '❌ অবৈধ রোল নির্বাচন।';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $status_msg = '❌ ইমেইল ঠিকানা সঠিক নয়।';
        } else {
            try {
                $conn->beginTransaction();

                $checkStmt = $conn->prepare('SELECT `id` FROM `users` WHERE `username` = ? LIMIT 1');
                $checkStmt->execute([$username]);
                $existingId = $checkStmt->fetchColumn();

                if ($existingId !== false) {
                    // ── Update existing user ─────────────────────────────────
                    if ($password !== '') {
                        // SECURITY FIX: use BCRYPT with explicit cost (more transparent than PASSWORD_DEFAULT)
                        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $conn->prepare('UPDATE `users` SET `password`=?, `role`=?, `email`=?, `phone`=?, `mobile`=?, `address`=? WHERE `username`=?')
                             ->execute([$hashed, $role, $email ?: null, $phone, $mobile, $address, $username]);
                    } else {
                        $conn->prepare('UPDATE `users` SET `role`=?, `email`=?, `phone`=?, `mobile`=?, `address`=? WHERE `username`=?')
                             ->execute([$role, $email ?: null, $phone, $mobile, $address, $username]);
                    }
                    // SECURITY FIX: e() on username when embedding in message
                    $status_msg = '✅ ইউজার \'' . e($username) . '\' এর তথ্য আপডেট হয়েছে!';
                } else {
                    // ── Insert new user ──────────────────────────────────────
                    if ($password === '') {
                        $status_msg = '❌ নতুন ইউজারের জন্য পাসওয়ার্ড বাধ্যতামূলক!';
                    } else {
                        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $conn->prepare('INSERT INTO `users` (`username`,`password`,`role`,`email`,`phone`,`mobile`,`address`,`joining_date`,`is_verified`) VALUES (?,?,?,?,?,?,?,?,0)')
                             ->execute([$username, $hashed, $role, $email ?: null, $phone, $mobile, $address, $joining]);
                        $status_msg = '✅ নতুন ইউজার \'' . e($username) . '\' তৈরি হয়েছে! (OTP ভেরিফিকেশন বাকি)';
                    }
                }

                if ($conn->inTransaction()) { $conn->commit(); }

            } catch (Throwable $e_) {
                if ($conn->inTransaction()) { $conn->rollBack(); }
                // SECURITY FIX: log internally, show generic message
                error_log('[AdminPanel] User save error: ' . $e_->getMessage());
                $status_msg = '❌ ডাটাবেজ এরর! পুনরায় চেষ্টা করুন।';
            }
        }
    }
}

// ============================================================================
// POST: Delete User (SECURITY FIX: was GET ?del_user=X — CSRF vulnerable)
// Now requires POST + CSRF token.
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $status_msg = '❌ সিকিউরিটি ত্রুটি!';
    } else {
        $delId = filter_var($_POST['del_user_id'] ?? '', FILTER_VALIDATE_INT);
        if ($delId && (int)$delId !== $sessionUid) {
            try {
                $adminCtrl->deleteUser((int)$delId, $sessionUid);
            } catch (Throwable $e_) {
                error_log('[AdminPanel] Delete user error: ' . $e_->getMessage());
            }
        }
        header('Location: admin_panel.php');
        exit;
    }
}

// ── Data Fetch ────────────────────────────────────────────────────────────────
try {
    $users_list = $conn->query("SELECT * FROM `users` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
    $users_list = [];
}

// ── Image path resolver ───────────────────────────────────────────────────────
function get_user_image(array $u): ?string {
    foreach (['profile_pic', 'profile_picture'] as $key) {
        $val = trim((string)($u[$key] ?? ''));
        if ($val === '' || $val === 'default_user.png') { continue; }
        if (preg_match('#^(https?:)?//#i', $val)) { return $val; }
        $val = ltrim($val, '/');
        if (str_starts_with($val, './'))  { $val = substr($val, 2); }
        if (str_starts_with($val, '../')) { return $val; }
        return str_contains($val, '/') ? '../' . $val : '../uploads/' . $val;
    }
    return null;
}

$is_err = str_starts_with($status_msg, '❌');
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
        #userForm { transition: max-height .4s ease, opacity .3s ease; overflow: hidden; }
        .form-hidden  { max-height: 0;     opacity: 0; }
        .form-visible { max-height: 1000px; opacity: 1; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen pb-16">

    <div class="max-w-6xl mx-auto px-4 mt-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-center bg-slate-900 text-white p-5 rounded-2xl shadow-lg mb-6 gap-4 border-b-4 border-emerald-500">
            <h1 class="text-lg sm:text-xl font-bold flex items-center gap-3 uppercase tracking-wide">
                <i class="fas fa-users-cog text-emerald-400"></i> ইউজার ম্যানেজমেন্ট
            </h1>
            <div class="flex gap-2 flex-wrap justify-center">
                <a href="master_control.php"
                   class="bg-slate-700 hover:bg-slate-600 px-4 py-2.5 rounded-lg font-bold transition flex items-center gap-2 text-sm border border-slate-600">
                    <i class="fas fa-arrow-left"></i> ব্যাক
                </a>
                <a href="../dashboard.php"
                   class="bg-emerald-600 hover:bg-emerald-500 px-4 py-2.5 rounded-lg font-bold transition flex items-center gap-2 text-sm">
                    <i class="fas fa-home"></i> হোম
                </a>
            </div>
        </div>

        <!-- Status Message -->
        <?php if ($status_msg): ?>
        <div class="p-4 rounded-xl shadow-sm mb-6 font-bold flex items-center gap-3 text-sm border-l-4
            <?= $is_err ? 'border-rose-500 text-rose-800 bg-rose-50' : 'border-emerald-500 text-emerald-800 bg-emerald-50' ?>">
            <i class="fas <?= $is_err ? 'fa-times-circle text-rose-500' : 'fa-check-circle text-emerald-500' ?> text-lg"></i>
            <?= $status_msg /* already safe: username e()'d when building message */ ?>
        </div>
        <?php endif; ?>

        <!-- User Panel Card -->
        <div class="bg-white p-5 rounded-2xl shadow-md border-t-4 border-emerald-500">

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-slate-200 pb-4 gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-user-plus text-emerald-500"></i> ইউজার তৈরি ও আপডেট
                    </h3>
                    <p class="text-xs text-slate-400 mt-1 font-medium">নতুন ইউজার যোগ করুন বা একই ইউজারনেম দিয়ে তথ্য আপডেট করুন।</p>
                </div>
                <button onclick="toggleUserForm()"
                        class="w-full sm:w-auto bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-4 py-2.5 rounded-lg text-sm font-bold border border-emerald-200 transition flex items-center justify-center gap-2">
                    <i class="fas fa-plus-circle" id="toggleIcon"></i>
                    <span id="toggleText">নতুন ইউজার ফর্ম দেখান</span>
                </button>
            </div>

            <!-- User Form -->
            <form method="POST" id="userForm" class="form-hidden">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-5 mb-5">
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">
                            ইউজারনেম <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="username" required maxlength="50" placeholder="ex: Ayan"
                               class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">
                            পাসওয়ার্ড <span class="text-[10px] text-slate-400 font-medium">(নতুন হলে বাধ্যতামূলক)</span>
                        </label>
                        <!-- SECURITY FIX: was type="text" — password must be hidden -->
                        <input type="password" name="password" placeholder="নতুন পাসওয়ার্ড" autocomplete="new-password"
                               class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">
                            রোল <span class="text-rose-500">*</span>
                        </label>
                        <select name="role"
                                class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-bold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <option value="staff">Staff</option>
                            <option value="manager">Manager</option>
                            <option value="viewer">Viewer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">ইমেইল</label>
                        <input type="email" name="email" placeholder="example@email.com"
                               class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">ফোন</label>
                        <input type="text" name="phone" placeholder="017XXXXXXXX" maxlength="20"
                               class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">মোবাইল</label>
                        <input type="text" name="mobile" placeholder="018XXXXXXXX" maxlength="20"
                               class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div class="md:col-span-3">
                        <label class="font-bold text-slate-600 text-xs mb-1.5 block">ঠিকানা</label>
                        <input type="text" name="address" placeholder="ঠিকানা..." maxlength="255"
                               class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 outline-none font-semibold text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" name="save_user"
                            class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-8 rounded-lg transition text-sm shadow-md flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i> ইউজার সেভ করুন
                    </button>
                </div>
            </form>

            <!-- User Table -->
            <div class="flex items-center justify-between mt-6 mb-3">
                <h4 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                    <i class="fas fa-list text-slate-400"></i> সব ইউজারের তালিকা
                </h4>
                <span class="bg-slate-100 text-slate-500 text-xs font-bold px-3 py-1 rounded-full">
                    মোট: <?= count($users_list) ?> জন
                </span>
            </div>
            <p class="text-[11px] text-slate-400 font-medium mb-3 flex items-center gap-1.5">
                <i class="fas fa-info-circle text-emerald-400"></i>
                রোল ড্রপডাউন থেকে সরাসরি পরিবর্তন করুন। টাইমার দিলে অস্থায়ী — সময়ে অটো ফিরবে।
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
                        <?php if (!empty($users_list)): foreach ($users_list as $u):
                            $img        = get_user_image($u);
                            $role       = strtolower((string)($u['role'] ?? ''));
                            $is_locked  = ((int)($u['id'] ?? 0) === $sessionUid) || (($u['username'] ?? '') === '{-ADMIN-}');
                            $is_autoBlk = (int)($u['auto_blocked'] ?? 0) === 1;
                            $role_color = match($role) {
                                'admin'   => 'bg-rose-100 text-rose-700 border-rose-200',
                                'manager' => 'bg-blue-100 text-blue-700 border-blue-200',
                                'staff'   => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                default   => 'bg-slate-200 text-slate-600 border-slate-300',
                            };
                            $exp_ts  = $hasTimerCols && !empty($u['role_expires_at']) ? strtotime((string)$u['role_expires_at']) : 0;
                            $prev_r  = $hasTimerCols ? (string)($u['prev_role'] ?? '') : '';
                            $is_temp = $exp_ts > time();
                        ?>
                        <tr class="hover:bg-white transition <?= $is_autoBlk ? 'bg-red-50' : '' ?>">
                            <!-- Avatar -->
                            <td class="p-3">
                                <?php if ($img): ?>
                                    <img src="<?= e($img) ?>" alt="pic"
                                         class="w-10 h-10 rounded-full object-cover border-2 border-slate-200 shadow-sm"
                                         onerror="this.style.display='none'">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white font-black text-sm shadow-sm">
                                        <?= e(strtoupper(mb_substr((string)($u['username'] ?? '?'), 0, 1))) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <!-- Username -->
                            <td class="p-3 font-bold text-slate-800">
                                <?= e((string)($u['username'] ?? '')) ?>
                                <?php if ($is_autoBlk): ?>
                                    <span class="text-[9px] bg-red-100 text-red-600 font-black px-1.5 py-0.5 rounded-full ml-1">🤖</span>
                                <?php endif; ?>
                            </td>
                            <!-- Email -->
                            <td class="p-3 text-slate-600 text-xs">
                                <?= !empty($u['email']) ? e((string)$u['email']) : '<span class="text-slate-400 italic">নেই</span>' ?>
                            </td>
                            <!-- Phone -->
                            <td class="p-3 text-slate-600 text-xs">
                                <?= !empty($u['phone']) ? e((string)$u['phone']) : '<span class="text-slate-400 italic">নেই</span>' ?>
                            </td>
                            <!-- Mobile -->
                            <td class="p-3 text-slate-600 text-xs">
                                <?= !empty($u['mobile']) ? e((string)$u['mobile']) : '<span class="text-slate-400 italic">নেই</span>' ?>
                            </td>
                            <!-- Role -->
                            <td class="p-3">
                                <?php if ($is_locked): ?>
                                    <span class="inline-block px-2.5 py-1.5 rounded-lg text-[10px] font-black uppercase border <?= $role_color ?>">
                                        <?= e((string)($u['role'] ?? '')) ?> <i class="fas fa-lock text-[8px] opacity-60"></i>
                                    </span>
                                <?php else: ?>
                                    <select data-id="<?= (int)$u['id'] ?>"
                                            data-prev="<?= e($role) ?>"
                                            onchange="changeRole(this)"
                                            class="role-select cursor-pointer text-[11px] font-black uppercase border rounded-lg px-2 py-1.5 outline-none focus:ring-2 focus:ring-emerald-200 transition <?= $role_color ?>">
                                        <?php foreach (['staff' => 'Staff', 'manager' => 'Manager', 'viewer' => 'Viewer', 'admin' => 'Admin'] as $val => $lbl): ?>
                                            <option value="<?= $val ?>" <?= $role === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <!-- Timer -->
                            <td class="p-3">
                                <?php if ($is_locked): ?>
                                    <span class="text-slate-300 text-xs">—</span>
                                <?php elseif ($is_temp): ?>
                                    <div class="flex flex-col gap-1">
                                        <span class="countdown inline-flex items-center gap-1.5 bg-amber-100 text-amber-700 border border-amber-200 rounded-lg px-2 py-1 text-[11px] font-black"
                                              data-expires="<?= e(date('c', $exp_ts)) ?>">
                                            <i class="fas fa-hourglass-half"></i>
                                            <span class="cd-text">--:--</span>
                                        </span>
                                        <span class="text-[10px] text-slate-400 font-semibold">
                                            &rarr; <?= e(ucfirst($prev_r ?: 'staff')) ?> এ ফিরবে
                                        </span>
                                    </div>
                                <?php elseif ($hasTimerCols): ?>
                                    <div class="flex items-center gap-1">
                                        <input type="number" min="1" placeholder="সময়"
                                               class="timer-val w-16 bg-slate-50 border border-slate-300 rounded-lg px-2 py-1.5 text-xs font-bold outline-none focus:border-emerald-500">
                                        <select class="timer-unit bg-slate-50 border border-slate-300 rounded-lg px-1 py-1.5 text-[11px] font-bold outline-none focus:border-emerald-500">
                                            <option value="1">মিনিট</option>
                                            <option value="60">ঘন্টা</option>
                                            <option value="1440">দিন</option>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-300 text-[10px] italic">—</span>
                                <?php endif; ?>
                            </td>
                            <!-- Action -->
                            <td class="p-3 text-center">
                                <?php if (!$is_locked && $role !== 'admin'): ?>
                                    <!-- SECURITY FIX: was <a href="?del_user=X"> — CSRF vulnerable GET.
                                         Now uses POST form with CSRF token. -->
                                    <form method="POST" class="inline m-0"
                                          onsubmit="return confirm('ইউজারটি স্থায়ীভাবে ডিলিট করবেন?\nএই অ্যাকশন পূর্বাবস্থায় ফেরানো যাবে না।')">
                                        <input type="hidden" name="csrf_token"  value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="del_user_id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" name="delete_user"
                                                class="text-rose-500 hover:bg-rose-100 p-2 rounded-lg transition" title="ডিলিট">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-slate-300"><i class="fas fa-lock" title="সুরক্ষিত"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="8" class="p-6 text-center text-slate-400 font-semibold text-sm">
                                কোনো ইউজার পাওয়া যায়নি।
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed top-5 left-1/2 -translate-x-1/2 z-50 hidden pointer-events-none">
        <div id="toastBox" class="px-5 py-3 rounded-xl shadow-xl font-bold text-sm text-white flex items-center gap-2"></div>
    </div>

    <script>
        // SECURITY FIX: use json_encode to safely embed server value into JS context
        const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token']); ?>;

        const ROLE_COLORS = {
            admin:   'bg-rose-100 text-rose-700 border-rose-200',
            manager: 'bg-blue-100 text-blue-700 border-blue-200',
            staff:   'bg-emerald-100 text-emerald-700 border-emerald-200',
            viewer:  'bg-slate-200 text-slate-600 border-slate-300'
        };
        const ALL_COLOR_CLASSES = Object.values(ROLE_COLORS).join(' ').split(' ');

        function showToast(msg, ok = true) {
            const t   = document.getElementById('toast');
            const box = document.getElementById('toastBox');
            box.className = 'px-5 py-3 rounded-xl shadow-xl font-bold text-sm text-white flex items-center gap-2 '
                          + (ok ? 'bg-emerald-600' : 'bg-rose-600');
            // SECURITY: Build DOM safely — never set innerHTML with untrusted server data
            box.innerHTML = '';
            const ico = document.createElement('i');
            ico.className = 'fas ' + (ok ? 'fa-check-circle' : 'fa-exclamation-circle');
            box.appendChild(ico);
            box.appendChild(document.createTextNode(' ' + msg));
            t.classList.remove('hidden');
            clearTimeout(window.__toastTimer);
            window.__toastTimer = setTimeout(() => t.classList.add('hidden'), 3500);
        }

        function changeRole(sel) {
            const id      = sel.dataset.id;
            const newRole = sel.value;
            const prev    = sel.dataset.prev;
            if (newRole === prev) return;

            const row    = sel.closest('tr');
            const valEl  = row?.querySelector('.timer-val');
            const unitEl = row?.querySelector('.timer-unit');
            const amount   = valEl  ? parseInt(valEl.value  || '0', 10) : 0;
            const unit     = unitEl ? parseInt(unitEl.value || '1', 10) : 1;
            const duration = amount > 0 ? amount * unit : 0;

            let confirmMsg = '';
            if (newRole === 'admin' && duration > 0) {
                const label = unit === 60 ? ' ঘন্টা' : (unit === 1440 ? ' দিন' : ' মিনিট');
                confirmMsg  = `এই ইউজারকে ${amount}${label} এর জন্য অস্থায়ী Admin বানাবেন?\nসময় শেষে অটো আগের রোলে ফিরবে।`;
            } else if (newRole === 'admin') {
                confirmMsg = 'এই ইউজারকে স্থায়ী Admin বানাতে চান?';
            }
            if (confirmMsg && !confirm(confirmMsg)) { sel.value = prev; return; }

            sel.disabled = true;
            const body = new URLSearchParams({
                action: 'change_role', csrf_token: CSRF_TOKEN,
                user_id: id, role: newRole, duration: duration
            });

            fetch('admin_panel.php', {
                method:  'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body
            })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    sel.dataset.prev = res.role;
                    sel.classList.remove(...ALL_COLOR_CLASSES);
                    (ROLE_COLORS[res.role] || '').split(' ').forEach(c => c && sel.classList.add(c));
                    showToast(res.msg, true);
                    if (res.expires) { setTimeout(() => location.reload(), 900); }
                } else {
                    sel.value = prev;
                    showToast(res.msg || 'পরিবর্তন ব্যর্থ!', false);
                }
            })
            .catch(() => { sel.value = prev; showToast('নেটওয়ার্ক এরর!', false); })
            .finally(() => { sel.disabled = false; });
        }

        // Live countdown for temp-role expiry
        function tickCountdowns() {
            const now = Date.now();
            document.querySelectorAll('.countdown').forEach(el => {
                const exp  = new Date(el.dataset.expires).getTime();
                let   diff = Math.floor((exp - now) / 1000);
                const txt  = el.querySelector('.cd-text');
                if (diff <= 0) { location.reload(); return; }
                const d = Math.floor(diff / 86400); diff %= 86400;
                const h = Math.floor(diff / 3600);  diff %= 3600;
                const m = Math.floor(diff / 60);
                const s = diff % 60;
                const p = n => String(n).padStart(2, '0');
                if (txt) txt.textContent = d > 0
                    ? (d + 'দিন ' + p(h) + ':' + p(m))
                    : (p(h) + ':' + p(m) + ':' + p(s));
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
                text.textContent = 'ফর্ম লুকান';
            } else {
                form.classList.replace('form-visible', 'form-hidden');
                icon.classList.replace('fa-minus-circle', 'fa-plus-circle');
                text.textContent = 'নতুন ইউজার ফর্ম দেখান';
            }
        }
    </script>
</body>
</html>
