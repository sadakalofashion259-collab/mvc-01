<?php
declare(strict_types=1);

// ── Production Error Handling ────────────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors',     '1');

session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/auto_block_engine.php';

// ── Auth Guard ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// ── Run Lazy Cron on every load ───────────────────────────────────────────────
runAutoBlockEngine($conn);

// ── CSRF Token ───────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$status_msg = '';
$is_error   = false;

// Bengali day labels (PHP date('w') = 0 Sunday … 6 Saturday)
const DAY_NAMES = [
    'রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার',
    'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার',
];

// ============================================================================
// POST Handlers (all guarded by CSRF)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $status_msg = 'সিকিউরিটি ত্রুটি! পেজ রিফ্রেশ করুন।';
        $is_error   = true;

    } elseif (isset($_POST['add_schedule'])) {
        // ── Add new schedule window ──────────────────────────────────────────
        $label  = mb_substr(trim($_POST['label'] ?? ''), 0, 100);
        $dow    = filter_var($_POST['day_of_week'] ?? '', FILTER_VALIDATE_INT,
                      ['options' => ['min_range' => 0, 'max_range' => 6]]);
        $start  = trim($_POST['start_time'] ?? '');
        $end    = trim($_POST['end_time']   ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($dow === false || $dow === null) {
            $status_msg = 'অবৈধ দিন নির্বাচন।';
            $is_error   = true;
        } elseif (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
            $status_msg = 'সময় ফরম্যাট ভুল — HH:MM দিন।';
            $is_error   = true;
        } elseif ($start === $end) {
            $status_msg = 'শুরু ও শেষ সময় একই হতে পারবে না।';
            $is_error   = true;
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO `auto_block_schedules`
                        (`label`, `day_of_week`, `start_time`, `end_time`, `is_active`)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$label, (int)$dow, $start . ':00', $end . ':00', $active]);
                $status_msg = 'নতুন শিডিউল সফলভাবে যোগ হয়েছে!';
            } catch (Throwable $e) {
                error_log('[AutoBlockSettings] Insert failed: ' . $e->getMessage());
                $status_msg = 'ডাটাবেজ এরর! শিডিউল যোগ করা যায়নি।';
                $is_error   = true;
            }
        }
        header('Location: auto_block_settings.php?msg=' . urlencode($status_msg)
             . '&err=' . ($is_error ? '1' : '0'));
        exit;

    } elseif (isset($_POST['toggle_schedule'])) {
        // ── Toggle active / paused ───────────────────────────────────────────
        $sid = filter_var($_POST['schedule_id'] ?? '', FILTER_VALIDATE_INT);
        if ($sid && $sid > 0) {
            try {
                $conn->prepare("UPDATE `auto_block_schedules` SET `is_active` = 1 - `is_active` WHERE `id` = ?")
                     ->execute([$sid]);
                $status_msg = 'শিডিউল স্ট্যাটাস পরিবর্তন হয়েছে।';
            } catch (Throwable $e) {
                error_log('[AutoBlockSettings] Toggle failed: ' . $e->getMessage());
                $status_msg = 'স্ট্যাটাস পরিবর্তন করা যায়নি।';
                $is_error   = true;
            }
        }
        header('Location: auto_block_settings.php?msg=' . urlencode($status_msg)
             . '&err=' . ($is_error ? '1' : '0'));
        exit;

    } elseif (isset($_POST['delete_schedule'])) {
        // ── Delete schedule ──────────────────────────────────────────────────
        $sid = filter_var($_POST['schedule_id'] ?? '', FILTER_VALIDATE_INT);
        if ($sid && $sid > 0) {
            try {
                $conn->prepare("DELETE FROM `auto_block_schedules` WHERE `id` = ?")
                     ->execute([$sid]);
                $status_msg = 'শিডিউল ডিলিট হয়েছে।';
            } catch (Throwable $e) {
                error_log('[AutoBlockSettings] Delete failed: ' . $e->getMessage());
                $status_msg = 'ডিলিট করা যায়নি।';
                $is_error   = true;
            }
        }
        header('Location: auto_block_settings.php?msg=' . urlencode($status_msg)
             . '&err=' . ($is_error ? '1' : '0'));
        exit;
    }
}

// ── Handle redirect message from PRG ─────────────────────────────────────────
if (empty($status_msg) && isset($_GET['msg'])) {
    $status_msg = (string) $_GET['msg'];
    $is_error   = ($_GET['err'] ?? '0') === '1';
}

// ── Data Fetch ────────────────────────────────────────────────────────────────
try {
    $schedules = $conn->query("
        SELECT * FROM `auto_block_schedules`
        ORDER BY `day_of_week` ASC, `start_time` ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
    $schedules = [];
}

try {
    $autoBlockedCount = (int) $conn->query(
        "SELECT COUNT(*) FROM `users` WHERE `auto_blocked` = 1"
    )->fetchColumn();
} catch (Throwable) {
    $autoBlockedCount = 0;
}

// Is any window currently active?
$now          = new DateTimeImmutable('now');
$todayDow     = (int) $now->format('w');
$yesterdayDow = ($todayDow + 6) % 7;
$currentSec   = (int) $now->format('H') * 3600 + (int) $now->format('i') * 60;
$windowActive = false;
foreach ($schedules as $sch) {
    if (!(int)$sch['is_active']) { continue; }
    $dow = (int) $sch['day_of_week'];
    $sp  = explode(':', $sch['start_time']); $ep = explode(':', $sch['end_time']);
    $ss  = (int)$sp[0] * 3600 + (int)$sp[1] * 60;
    $es  = (int)$ep[0] * 3600 + (int)$ep[1] * 60;
    if ($ss === $es) { continue; }
    if ($ss < $es) {
        if ($dow === $todayDow && $currentSec >= $ss && $currentSec < $es) { $windowActive = true; break; }
    } else {
        if ($dow === $todayDow     && $currentSec >= $ss) { $windowActive = true; break; }
        if ($dow === $yesterdayDow && $currentSec <  $es) { $windowActive = true; break; }
    }
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অটো-ব্লক সেটিংস | SADA KALO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Hind Siliguri', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen pb-16">

    <!-- Header -->
    <div class="bg-slate-900 text-white p-5 flex justify-between items-center shadow-lg border-b-4 border-red-500">
        <h1 class="font-bold text-lg flex items-center gap-3">
            <i class="fas fa-clock-rotate-left text-red-400"></i> অটো-ব্লক সেটিংস
        </h1>
        <a href="master_control.php"
           class="bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg text-sm font-bold transition flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> ব্যাক
        </a>
    </div>

    <div class="max-w-3xl mx-auto p-4 mt-6 space-y-6">

        <!-- Live Status Banner -->
        <div class="rounded-2xl shadow-md p-5 border-l-8 flex items-center justify-between
            <?= $windowActive ? 'bg-red-50 border-red-500' : 'bg-emerald-50 border-emerald-500' ?>">
            <div>
                <p class="font-black text-lg <?= $windowActive ? 'text-red-700' : 'text-emerald-700' ?>">
                    <i class="fas <?= $windowActive ? 'fa-ban' : 'fa-check-circle' ?> mr-1"></i>
                    <?= $windowActive ? 'অটো-ব্লক এখন চলছে!' : 'কোনো অটো-ব্লক সক্রিয় নেই' ?>
                </p>
                <p class="text-sm text-slate-500 mt-1 font-semibold">
                    <?= $autoBlockedCount > 0
                        ? "এই মুহূর্তে <strong class='text-red-600'>{$autoBlockedCount} জন</strong> ইউজার অটো-ব্লকড।"
                        : 'সব ইউজার স্বাভাবিক অ্যাক্সেস পাচ্ছেন।' ?>
                </p>
            </div>
            <i class="fas <?= $windowActive ? 'fa-lock text-red-300' : 'fa-unlock text-emerald-300' ?> text-4xl"></i>
        </div>

        <!-- Status message -->
        <?php if ($status_msg): ?>
        <div class="p-4 rounded-xl font-bold flex items-center gap-3 text-sm border-l-4 shadow-sm
            <?= $is_error ? 'border-red-500 text-red-800 bg-red-50' : 'border-emerald-500 text-emerald-800 bg-emerald-50' ?>">
            <i class="fas <?= $is_error ? 'fa-times-circle text-red-500' : 'fa-check-circle text-emerald-500' ?> text-lg flex-shrink-0"></i>
            <?= e($status_msg) ?>
        </div>
        <?php endif; ?>

        <!-- Add Schedule Form -->
        <div class="bg-white rounded-2xl shadow-md p-6 border-t-4 border-blue-500">
            <h3 class="font-bold text-slate-800 mb-5 flex items-center gap-2 text-base">
                <i class="fas fa-plus-circle text-blue-500"></i> নতুন ব্লক শিডিউল যোগ করুন
            </h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">
                            শিডিউলের নাম <span class="text-slate-400 font-normal">(ঐচ্ছিক)</span>
                        </label>
                        <input type="text" name="label" maxlength="100" placeholder="যেমন: রাতের ব্লক"
                               class="w-full border border-slate-300 rounded-lg p-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 bg-slate-50">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">
                            দিন <span class="text-red-500">*</span>
                        </label>
                        <select name="day_of_week" required
                                class="w-full border border-slate-300 rounded-lg p-2.5 text-sm font-bold outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 bg-slate-50">
                            <?php foreach (DAY_NAMES as $i => $day): ?>
                                <option value="<?= $i ?>"><?= e($day) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">
                            শুরুর সময় <span class="text-red-500">*</span>
                        </label>
                        <input type="time" name="start_time" required
                               class="w-full border border-slate-300 rounded-lg p-2.5 text-sm font-bold outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 bg-slate-50">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">
                            শেষ সময় <span class="text-red-500">*</span>
                        </label>
                        <input type="time" name="end_time" required
                               class="w-full border border-slate-300 rounded-lg p-2.5 text-sm font-bold outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 bg-slate-50">
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between flex-wrap gap-3">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_active" checked class="w-4 h-4 accent-blue-600 rounded">
                        <span class="text-sm font-bold text-slate-700">এখনই সক্রিয় রাখুন</span>
                    </label>
                    <button type="submit" name="add_schedule"
                            class="bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-bold py-2.5 px-6 rounded-lg transition text-sm flex items-center gap-2 shadow-md">
                        <i class="fas fa-save"></i> শিডিউল সেভ করুন
                    </button>
                </div>
            </form>
        </div>

        <!-- Schedule List -->
        <div class="bg-white rounded-2xl shadow-md p-6 border-t-4 border-slate-600">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-800 flex items-center gap-2 text-base">
                    <i class="fas fa-list-ul text-slate-500"></i> বর্তমান শিডিউল তালিকা
                </h3>
                <span class="bg-slate-100 text-slate-500 text-xs font-black px-3 py-1 rounded-full">
                    মোট: <?= count($schedules) ?> টি
                </span>
            </div>

            <?php if (empty($schedules)): ?>
                <div class="text-center py-8 text-slate-400">
                    <i class="fas fa-calendar-xmark text-3xl mb-3 block"></i>
                    <p class="text-sm font-semibold">কোনো শিডিউল নেই।<br>উপরের ফর্ম থেকে যোগ করুন।</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($schedules as $sch):
                        $isActive = (int)$sch['is_active'] === 1;
                    ?>
                    <div class="flex items-center justify-between p-4 rounded-xl border-2 transition
                        <?= $isActive ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-slate-50' ?>">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-800 text-sm truncate">
                                <i class="fas fa-calendar-day <?= $isActive ? 'text-red-500' : 'text-slate-400' ?> mr-1"></i>
                                <?= e(DAY_NAMES[(int)$sch['day_of_week']] ?? '?') ?>
                                <?php if (!empty($sch['label'])): ?>
                                    <span class="text-slate-400 font-normal text-xs"> — <?= e($sch['label']) ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-slate-500 mt-1 font-bold">
                                <i class="fas fa-clock mr-1"></i>
                                <?= e(substr((string)$sch['start_time'], 0, 5)) ?>
                                &rarr;
                                <?= e(substr((string)$sch['end_time'],   0, 5)) ?>
                                <?php
                                    $ss2 = explode(':', $sch['start_time']); $es2 = explode(':', $sch['end_time']);
                                    $sval = (int)$ss2[0]*60+(int)$ss2[1]; $eval = (int)$es2[0]*60+(int)$es2[1];
                                    if ($sval > $eval): ?>
                                    <span class="text-amber-500 ml-1">(রাতব্যাপী)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="flex gap-2 items-center ml-3 flex-shrink-0">
                            <span class="text-[10px] font-black px-2 py-1 rounded-full
                                <?= $isActive ? 'bg-red-100 text-red-600' : 'bg-slate-200 text-slate-500' ?>">
                                <?= $isActive ? 'সক্রিয়' : 'বিরতি' ?>
                            </span>
                            <!-- Toggle -->
                            <form method="POST" class="inline m-0">
                                <input type="hidden" name="csrf_token"   value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="schedule_id"  value="<?= (int)$sch['id'] ?>">
                                <button type="submit" name="toggle_schedule" title="<?= $isActive ? 'বিরতি দিন' : 'সক্রিয় করুন' ?>"
                                        class="w-9 h-9 rounded-lg flex items-center justify-center text-white transition active:scale-95
                                            <?= $isActive ? 'bg-slate-400 hover:bg-slate-500' : 'bg-emerald-500 hover:bg-emerald-600' ?>">
                                    <i class="fas <?= $isActive ? 'fa-pause' : 'fa-play' ?> text-xs"></i>
                                </button>
                            </form>
                            <!-- Delete -->
                            <form method="POST" class="inline m-0"
                                  onsubmit="return confirm('এই শিডিউলটি চিরতরে ডিলিট করবেন?')">
                                <input type="hidden" name="csrf_token"   value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="schedule_id"  value="<?= (int)$sch['id'] ?>">
                                <button type="submit" name="delete_schedule" title="ডিলিট"
                                        class="w-9 h-9 rounded-lg flex items-center justify-center text-white bg-red-500 hover:bg-red-600 transition active:scale-95">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- How it works info box -->
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 text-sm text-blue-900">
            <h4 class="font-bold flex items-center gap-2 mb-3 text-base">
                <i class="fas fa-circle-info text-blue-500"></i> কীভাবে কাজ করে
            </h4>
            <ul class="space-y-2 font-medium">
                <li><i class="fas fa-check text-blue-500 mr-1.5"></i>শিডিউলের নির্ধারিত সময়ে সকল নন-অ্যাডমিন ইউজার <strong>অটোমেটিক ব্লক</strong> হবেন।</li>
                <li><i class="fas fa-check text-blue-500 mr-1.5"></i>সময় শেষ হলে শুধুমাত্র অটো-ব্লকড ইউজাররা <strong>অটোমেটিক আনব্লক</strong> হবেন।</li>
                <li><i class="fas fa-check text-blue-500 mr-1.5"></i>ম্যানুয়ালি ব্লক করা ইউজাররা <strong>কখনো অটো-রিস্টোর হবেন না</strong>।</li>
                <li><i class="fas fa-check text-blue-500 mr-1.5"></i><strong>রাতব্যাপী শিডিউল</strong> সাপোর্ট করে (যেমন ২২:০০ → ০৬:০০)।</li>
                <li><i class="fas fa-check text-blue-500 mr-1.5"></i>এটি <strong>cPanel Cron Job</strong> দ্বারা প্রতি ৫ মিনিট পর পর ব্যাকগ্রাউন্ডে স্বয়ংক্রিয়ভাবে চেক হয়।</li>
            </ul>
        </div>
    </div>
</body>
</html>
