<?php
declare(strict_types=1);

// ── Production Error Handling ─────────────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors',     '1');

session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../Controllers/AdminController.php';
require_once __DIR__ . '/auto_block_engine.php';

// ── Auth Guard (SECURITY FIX: was != instead of !==) ─────────────────────────
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

// ── Run Auto-Block Engine (Lazy Cron) ─────────────────────────────────────────
runAutoBlockEngine($conn);

// ── CSRF Token (SECURITY FIX: was completely missing) ────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$adminCtrl  = new AdminController($conn);
$status_msg = '';
$is_error   = false;

// ── Output escape helper ──────────────────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ============================================================================
// POST: Manual Timed Block (block_end)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_block_time'])) {

    // SECURITY FIX: CSRF check was completely missing
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $status_msg = 'সিকিউরিটি ত্রুটি! পেজ রিফ্রেশ করুন।';
        $is_error   = true;
    } else {
        $uid      = filter_var($_POST['time_user_id'] ?? '', FILTER_VALIDATE_INT);
        $blockEnd = trim($_POST['block_end'] ?? '');

        // SECURITY FIX: $blockEnd was used raw without any validation
        $validFormat  = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $blockEnd);
        $isFuture     = $validFormat && strtotime($blockEnd) > time();

        if (!$uid || $uid <= 0 || !$isFuture) {
            $status_msg = 'অবৈধ ইউজার বা সময়! ভবিষ্যতের সময় নির্বাচন করুন।';
            $is_error   = true;
        } else {
            // Verify target is not an admin (double-check server-side)
            $chk = $conn->prepare("SELECT `id` FROM `users` WHERE `id` = ? AND `role` != 'admin' LIMIT 1");
            $chk->execute([$uid]);
            if (!$chk->fetch()) {
                $status_msg = 'ইউজার পাওয়া যায়নি বা অ্যাডমিন।';
                $is_error   = true;
            } else {
                try {
                    if ($adminCtrl->setTimedBlock((int)$uid, $blockEnd)) {
                        // Explicitly mark as manual block (auto_blocked = 0) so
                        // the engine does not auto-restore this user at window end.
                        $conn->prepare("UPDATE `users` SET `auto_blocked` = 0 WHERE `id` = ? AND `role` != 'admin'")
                             ->execute([$uid]);
                        $status_msg = 'নির্দিষ্ট সময়ের জন্য ইউজার ব্লক হয়েছে!';
                    } else {
                        $status_msg = 'টাইম ব্লক সেট করা যায়নি।';
                        $is_error   = true;
                    }
                } catch (Throwable $ex) {
                    error_log('[ManageUsers] setTimedBlock error: ' . $ex->getMessage());
                    $status_msg = 'ডাটাবেজ এরর! পুনরায় চেষ্টা করুন।';
                    $is_error   = true;
                }
            }
        }
    }
}

// ============================================================================
// POST: Toggle Block / Unblock
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_block'])) {

    // SECURITY FIX: CSRF check was completely missing
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $status_msg = 'সিকিউরিটি ত্রুটি! পেজ রিফ্রেশ করুন।';
        $is_error   = true;
    } else {
        $uid = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);

        if (!$uid || $uid <= 0) {
            $status_msg = 'অবৈধ ইউজার আইডি।';
            $is_error   = true;
        } else {
            // SECURITY FIX: original code trusted $_POST['current_status'] from client.
            // Attacker could manipulate this to bypass/force block states.
            // Always fetch the ACTUAL status from the database.
            try {
                $stmtChk = $conn->prepare("
                    SELECT `status`, `auto_blocked`
                    FROM   `users`
                    WHERE  `id`   = ?
                      AND  `role` != 'admin'
                    LIMIT  1
                ");
                $stmtChk->execute([$uid]);
                $row = $stmtChk->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $status_msg = 'ইউজার পাওয়া যায়নি।';
                    $is_error   = true;
                } else {
                    $actualStatus = $row['status'];
                    if ($adminCtrl->toggleUserStatus((int)$uid, $actualStatus)) {
                        // Always reset auto_blocked on manual admin action:
                        //  - If unblocking an auto-blocked user, admin intent is clear.
                        //  - If blocking an active user, it's a manual block (not engine).
                        $conn->prepare("UPDATE `users` SET `auto_blocked` = 0 WHERE `id` = ? AND `role` != 'admin'")
                             ->execute([$uid]);
                        $status_msg = $actualStatus === 'blocked'
                            ? 'ইউজার আনব্লক হয়েছে!'
                            : 'ইউজার ব্লক হয়েছে!';
                    } else {
                        $status_msg = 'স্ট্যাটাস পরিবর্তন করা যায়নি।';
                        $is_error   = true;
                    }
                }
            } catch (Throwable $ex) {
                error_log('[ManageUsers] toggleUserStatus error: ' . $ex->getMessage());
                $status_msg = 'ডাটাবেজ এরর! পুনরায় চেষ্টা করুন।';
                $is_error   = true;
            }
        }
    }
}

// ── Fetch users list ──────────────────────────────────────────────────────────
// SECURITY FIX: original used SELECT * which includes password hashes.
// Select only the columns actually used in this view.
$current_time = time();
try {
    $users_list = $conn->query("
        SELECT `id`, `username`, `status`, `auto_blocked`, `block_end`, `last_active`
        FROM   `users`
        WHERE  `role` != 'admin'
        ORDER  BY `last_active` DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    error_log('[ManageUsers] User fetch error: ' . $ex->getMessage());
    $users_list = [];
}

// Count auto-blocked users for the live banner
$autoBlockedCount = 0;
foreach ($users_list as $u) {
    if ((int)($u['auto_blocked'] ?? 0) === 1) { $autoBlockedCount++; }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ইউজার কন্ট্রোল | SADA KALO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Hind Siliguri', sans-serif; }</style>
</head>
<body class="bg-slate-100 pb-10 min-h-screen">

    <!-- Header -->
    <div class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-lg sticky top-0 z-50 border-b-4 border-purple-500">
        <a href="master_control.php"
           class="bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg text-sm font-bold transition">
            <i class="fas fa-arrow-left"></i> ব্যাক
        </a>
        <h1 class="font-bold text-lg">ইউজার কন্ট্রোল</h1>
        <a href="../dashboard.php"
           class="bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded-lg text-sm font-bold transition">
            <i class="fas fa-home"></i> হোম
        </a>
    </div>

    <div class="max-w-4xl mx-auto p-4 mt-4 space-y-4">

        <!-- Auto-block live banner -->
        <?php if ($autoBlockedCount > 0): ?>
        <div class="bg-red-900 text-white p-4 rounded-2xl flex items-center justify-between shadow-lg border border-red-700">
            <div class="flex items-center gap-3">
                <i class="fas fa-clock-rotate-left text-red-300 text-xl animate-pulse flex-shrink-0"></i>
                <div>
                    <p class="font-black text-sm">অটো-ব্লক এখন সক্রিয়!</p>
                    <p class="text-xs text-red-300 font-medium">
                        <?= $autoBlockedCount ?> জন ইউজার শিডিউল অনুযায়ী ব্লকড।
                    </p>
                </div>
            </div>
            <a href="auto_block_settings.php"
               class="bg-red-700 hover:bg-red-600 text-white text-xs font-bold px-3 py-2 rounded-xl transition flex-shrink-0">
                <i class="fas fa-cog"></i> সেটিংস
            </a>
        </div>
       <?php endif; ?>

        <!-- Status message -->
        <?php if (!empty($status_msg)): ?>
        <div class="p-3 rounded-xl font-bold text-center border shadow-sm text-sm flex items-center justify-center gap-2
            <?= !empty($is_error) ? 'bg-rose-50 text-rose-800 border-rose-300' : 'bg-emerald-50 text-emerald-800 border-emerald-300' ?>">
            <?= !empty($is_error) ? '❌' : '✅' ?>
            <!-- SECURITY FIX: output through e() -->
            <?= e($status_msg) ?>
        </div>
        <?php endif; ?>
    
        <!-- User Cards -->
        <div class="space-y-3">
            <?php if (empty($users_list)): ?>
            <div class="bg-white p-8 rounded-2xl shadow text-center text-slate-400 font-semibold text-sm">
                <i class="fas fa-users-slash text-3xl mb-3 block"></i>
                কোনো নন-অ্যাডমিন ইউজার পাওয়া যায়নি।
            </div>
            <?php else: foreach ($users_list as $user):
                $lastAct   = strtotime((string)($user['last_active'] ?? '2000-01-01'));
                $isOnline  = ($lastAct > ($current_time - 1200));
                $isAutoBlk = (int)($user['auto_blocked'] ?? 0) === 1;
                $isBlocked = (string)($user['status'] ?? '') === 'blocked';

                // Border color: red = auto-blocked, orange = manual blocked, green = online active, grey = offline active
                $border = $isAutoBlk   ? 'border-red-500'
                        : ($isBlocked  ? 'border-orange-400'
                        : ($isOnline   ? 'border-emerald-500'
                                       : 'border-slate-300'));
            ?>
            <div class="bg-white p-4 rounded-2xl shadow-md border-l-8 <?= $border ?> flex justify-between items-center transition hover:shadow-lg">
                <div class="min-w-0 pr-3">
                    <h3 class="font-bold text-lg text-slate-800 flex items-center gap-2 flex-wrap">
                        <?= e((string)($user['username'] ?? '')) ?>
                        <?php if ($isAutoBlk): ?>
                            <span class="text-[10px] bg-red-100 text-red-700 font-black px-2 py-0.5 rounded-full whitespace-nowrap">
                                🤖 অটো-ব্লক
                            </span>
                        <?php elseif ($isBlocked): ?>
                            <span class="text-[10px] bg-orange-100 text-orange-700 font-black px-2 py-0.5 rounded-full whitespace-nowrap">
                                🔒 ম্যানুয়াল ব্লক
                            </span>
                        <?php endif; ?>
                    </h3>

                    <p class="text-xs font-bold mt-1 <?= $isOnline ? 'text-emerald-600' : 'text-slate-400' ?>">
                        <i class="fas fa-circle text-[10px] <?= $isOnline && !$isBlocked ? 'animate-pulse' : '' ?>"></i>
                        <?= $isOnline && !$isBlocked ? 'অনলাইনে আছেন' : 'অফলাইন' ?>
                    </p>

                    <?php if ($isBlocked && !$isAutoBlk && !empty($user['block_end'])): ?>
                        <p class="text-[10px] text-orange-500 font-bold mt-1">
                            🕒 আনব্লক হবে: <?= e(date('d M Y, h:i A', strtotime((string)$user['block_end']))) ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($isAutoBlk): ?>
                        <p class="text-[10px] text-red-400 font-semibold mt-1">
                            শিডিউল শেষ হলে অটোমেটিক আনব্লক হবে।
                        </p>
                    <?php endif; ?>
                </div>

                <div class="flex gap-2 flex-shrink-0">
                    <!-- Timed block button -->
                    <button type="button"
                            onclick="openTimeModal(<?= (int)$user['id'] ?>, <?= json_encode((string)($user['username'] ?? '')) ?>)"
                            class="bg-blue-500 hover:bg-blue-600 text-white w-12 h-12 rounded-xl flex items-center justify-center shadow-md transition active:scale-95"
                            title="সময়-ভিত্তিক ব্লক সেট করুন">
                        <i class="fas fa-clock text-lg"></i>
                    </button>

                    <!-- Toggle block form -->
                    <!-- SECURITY FIX 1: Added CSRF token (was completely missing)         -->
                    <!-- SECURITY FIX 2: Removed current_status hidden field (was trusted) -->
                    <form method="POST" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="user_id"    value="<?= (int)$user['id'] ?>">
                        <button type="submit" name="toggle_block"
                                class="w-12 h-12 rounded-xl flex items-center justify-center shadow-md transition active:scale-95 text-white
                                    <?= $isBlocked ? 'bg-emerald-500 hover:bg-emerald-600' : 'bg-rose-500 hover:bg-rose-600' ?>"
                                title="<?= $isBlocked ? 'আনব্লক করুন' : 'ব্লক করুন' ?>">
                            <i class="fas <?= $isBlocked ? 'fa-unlock' : 'fa-ban' ?> text-lg"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Timed-block Modal -->
    <div id="timeModal" class="hidden fixed inset-0 bg-black/80 z-[100] flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm p-6 shadow-2xl border-t-8 border-purple-500">
            <h3 id="modal_user_name" class="font-bold text-xl text-purple-700 mb-4 border-b pb-3">
                সময় সেট করুন
            </h3>
            <!-- SECURITY FIX: CSRF token added to modal form (was completely missing) -->
            <form method="POST">
                <input type="hidden" name="csrf_token"    value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="time_user_id"  id="time_user_id">
                <label class="block text-sm font-bold text-slate-700 mb-2">কখন ব্লক খুলে যাবে?</label>
                <!-- min attribute prevents selecting past dates in browser -->
                <input type="datetime-local" name="block_end" id="block_end_input" required
                       min="<?= date('Y-m-d\TH:i') ?>"
                       class="w-full border-2 border-slate-300 p-3 rounded-xl mb-6 font-bold text-slate-700 outline-none focus:border-purple-500 bg-slate-50">
                <div class="flex gap-3">
                    <button type="button" onclick="closeTimeModal()"
                            class="flex-1 bg-slate-500 hover:bg-slate-600 text-white py-3 rounded-xl font-bold transition active:scale-95">
                        বাতিল
                    </button>
                    <button type="submit" name="save_block_time"
                            class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-xl font-bold transition active:scale-95">
                        সেভ করুন
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openTimeModal(id, name) {
            document.getElementById('time_user_id').value = id;
            // Set the min attribute dynamically so it reflects current time
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('block_end_input').min = now.toISOString().slice(0, 16);
            // Use textContent (not innerHTML) to safely display username
            document.getElementById('modal_user_name').textContent = '🕒 ' + name + ' এর সময় সেট';
            document.getElementById('timeModal').classList.remove('hidden');
        }

        function closeTimeModal() {
            document.getElementById('timeModal').classList.add('hidden');
        }

        // Close modal on backdrop click
        document.getElementById('timeModal').addEventListener('click', function(e) {
            if (e.target === this) { closeTimeModal(); }
        });
    </script>
</body>
</html>
