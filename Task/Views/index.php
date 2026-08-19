<?php
declare(strict_types=1);
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---- লগইন গার্ড (অপরিবর্তিত) ---- */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Models/TaskModelInterface.php';
require_once __DIR__ . '/../Models/TaskModel.php';
require_once __DIR__ . '/../Controllers/TaskController.php';

$userRole        = $_SESSION['role'] ?? 'user';
$currentUsername = $_SESSION['username'] ?? 'Unknown';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

$taskModel      = new TaskModel($conn);
$taskController = new TaskController($taskModel);

/* ---- POST অ্যাকশন হ্যান্ডলিং (অপরিবর্তিত) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskController->handleRequest($_POST, $userRole, $currentUsername);
}

$routineTasks = $taskController->getTasksForView($currentUsername, $userRole);
$allUsersList = $taskController->getUsersForDropdown();

/* ---- Active ও Completed ভাগ করা (অপরিবর্তিত) ---- */
$activeTasks    = [];
$completedTasks = [];
if (!empty($routineTasks)) {
    foreach ($routineTasks as $task) {
        if (($task['status'] ?? '') === 'active') {
            $activeTasks[] = $task;
        } else {
            $completedTasks[] = $task;
        }
    }
}

/* ---- সারসংক্ষেপ গণনা ---- */
$activeCount    = count($activeTasks);
$completedCount = count($completedTasks);
$totalCount     = count($routineTasks);

/* ---- এরর বার্তা (paste-corruption ঠিক করা হয়েছে) ---- */
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

/* ---- কার্ড আইকন সেট ---- */
$iconSet = ['fa-mug-hot','fa-boxes-stacked','fa-headset','fa-file-lines','fa-truck-fast','fa-clipboard-check','fa-calculator','fa-bell'];

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="bn" data-theme="indigo" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>রুটিন ম্যানেজমেন্ট — SADA KALO FASHION</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+Da+2:wght@500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{ --font-display:'Baloo Da 2',system-ui,sans-serif; --font-body:'Hind Siliguri',system-ui,sans-serif;
  --radius-xl:24px; --radius-lg:18px; --radius-md:14px; --radius-sm:11px; --shell:560px; }
[data-theme="indigo"]{ --accent:#6d5bf6; --accent-2:#3b82f6; --accent-deep:#4f46e5; }
[data-theme="emerald"]{ --accent:#0ea372; --accent-2:#10b981; --accent-deep:#047857; }
[data-theme="sunset"]{ --accent:#f5576c; --accent-2:#f093a4; --accent-deep:#e11d48; }
[data-theme="ocean"]{ --accent:#0284c7; --accent-2:#06b6d4; --accent-deep:#0369a1; }
[data-bs-theme="light"]{ --bg:#eef0f7; --bg-2:#e4e7f2; --surface:#fff; --surface-2:#f4f5fb; --surface-3:#eceefa;
  --text:#1d2030; --text-muted:#7b8094; --text-soft:#9aa0b4; --border:rgba(20,24,60,.08);
  --shadow:0 14px 36px -18px rgba(40,44,90,.32); --success:#10b981; --success-deep:#059669; --danger:#ef4444; --warn:#f59e0b;
  --accent-soft:color-mix(in srgb,var(--accent) 12%,#fff); }
[data-bs-theme="dark"]{ --bg:#0c0e16; --bg-2:#111421; --surface:#171a27; --surface-2:#1f2333; --surface-3:#262b3d;
  --text:#e9ebf4; --text-muted:#9aa0bb; --text-soft:#6e7493; --border:rgba(255,255,255,.08);
  --shadow:0 18px 40px -20px rgba(0,0,0,.7); --success:#34d399; --success-deep:#34d399; --danger:#f87171; --warn:#fbbf24;
  --accent-soft:color-mix(in srgb,var(--accent) 24%,#171a27); }
*{ -webkit-tap-highlight-color:transparent; box-sizing:border-box; }
body{ font-family:var(--font-body); margin:0; min-height:100vh; color:var(--text); padding-bottom:40px;
  background:radial-gradient(120% 55% at 100% 0%, color-mix(in srgb,var(--accent) 9%,transparent), transparent 60%), linear-gradient(180deg,var(--bg-2),var(--bg));
  transition:background .4s ease,color .3s ease; }

/* topbar */
.topbar{ position:sticky; top:0; z-index:50; backdrop-filter:blur(10px);
  background:color-mix(in srgb,var(--surface) 86%,transparent); border-bottom:1px solid var(--border); }
.topbar-in{ max-width:var(--shell); margin:0 auto; display:flex; align-items:center; gap:10px; padding:11px 14px; }
.tb-title{ font-family:var(--font-display); font-weight:800; font-size:17px; color:var(--text); display:flex; align-items:center; gap:8px; white-space:nowrap; }
.tb-title i{ color:var(--accent); }
.tb-btn{ width:42px; height:42px; flex-shrink:0; border-radius:13px; border:1px solid var(--border); cursor:pointer;
  background:var(--surface-2); color:var(--text); display:grid; place-items:center; font-size:16px; text-decoration:none; transition:transform .2s; }
.tb-btn:active{ transform:scale(.9); }
.tb-back{ background:linear-gradient(135deg,var(--accent),var(--accent-2)); color:#fff; border-color:transparent; box-shadow:0 8px 16px -8px var(--accent); }

.wrap{ max-width:var(--shell); margin:0 auto; padding:14px 14px 0; }

/* summary */
.stat-row{ display:flex; gap:10px; }
.stat{ flex:1; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:13px 8px; text-align:center; box-shadow:var(--shadow); }
.stat-num{ font-family:var(--font-display); font-weight:800; font-size:26px; line-height:1; }
.stat-lbl{ font-size:12.5px; color:var(--text-muted); margin-top:5px; font-weight:600; }
.stat.s-active .stat-num{ color:var(--accent); }
.stat.s-done .stat-num{ color:var(--success); }
.stat.s-total .stat-num{ color:var(--accent-2); }

/* card */
.card-soft{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow); padding:16px; margin-top:16px; }
.card-head{ display:flex; align-items:center; gap:11px; margin-bottom:14px; }
.card-ico{ width:42px; height:42px; flex-shrink:0; border-radius:12px; display:grid; place-items:center; color:#fff; font-size:17px;
  background:linear-gradient(135deg,var(--accent),var(--accent-2)); box-shadow:0 8px 16px -8px var(--accent); }
.card-title{ font-family:var(--font-display); font-weight:700; font-size:16.5px; color:var(--text); margin:0; line-height:1.2; white-space:nowrap; }
.admin-pill{ margin-left:auto; display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:700; color:#fff; white-space:nowrap;
  background:linear-gradient(135deg,var(--accent),var(--accent-2)); padding:6px 12px; border-radius:30px; }

/* form */
.field{ margin-bottom:12px; }
.field-top{ display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; }
.field-top label{ font-size:13px; font-weight:600; color:var(--text-muted); white-space:nowrap; }
.voice-tools{ display:flex; gap:6px; flex-shrink:0; }
.voice-btn{ width:30px; height:30px; border:none; border-radius:9px; cursor:pointer; color:var(--accent-deep);
  background:var(--accent-soft); display:grid; place-items:center; font-size:12px; transition:all .2s; }
[data-bs-theme="dark"] .voice-btn{ color:#fff; }
.voice-btn:active{ transform:scale(.85); }
.voice-btn.recording{ background:var(--danger); color:#fff; animation:pulse-red 1.4s infinite; }
@keyframes pulse-red{ 0%{ box-shadow:0 0 0 0 color-mix(in srgb,var(--danger) 70%,transparent);} 70%{ box-shadow:0 0 0 7px transparent;} 100%{ box-shadow:0 0 0 0 transparent;} }
.inp{ width:100%; background:var(--surface-2); border:1px solid var(--border); color:var(--text); border-radius:var(--radius-sm);
  padding:11px 13px; font-family:var(--font-body); font-size:14.5px; font-weight:600; outline:none; transition:border .2s, box-shadow .2s; }
.inp:focus{ border-color:var(--accent); box-shadow:0 0 0 4px var(--accent-soft); }
.inp::placeholder{ color:var(--text-soft); font-weight:500; }
textarea.inp{ resize:vertical; min-height:64px; }
/* শিরোনাম ও বিবরণ ইনপুট হাইলাইট (মূল ডিজাইনের অ্যাকসেন্ট-টেক্সট ভাব) */
#task_title, #task_description{ color:var(--accent-deep); font-weight:700; }
[data-bs-theme="dark"] #task_title, [data-bs-theme="dark"] #task_description{ color:var(--accent-2); }
.assignee-box{ background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:10px; max-height:150px; overflow-y:auto; }
.assignee-all{ display:flex; align-items:center; gap:9px; font-weight:700; font-size:13px; color:var(--accent-deep);
  border-bottom:1px solid var(--border); padding-bottom:9px; margin-bottom:8px; cursor:pointer; }
[data-bs-theme="dark"] .assignee-all{ color:var(--accent-2); }
.assignee-row{ display:flex; align-items:center; gap:9px; font-size:13.5px; font-weight:600; color:var(--text); padding:7px 8px; border-radius:9px; cursor:pointer; transition:background .15s; }
.assignee-row:hover{ background:var(--surface-3); }
.assignee-box input[type=checkbox]{ width:17px; height:17px; accent-color:var(--accent); flex-shrink:0; }
.btn-grad{ width:100%; border:none; border-radius:var(--radius-sm); padding:13px; cursor:pointer; font-family:var(--font-display);
  font-weight:700; font-size:15.5px; color:#fff; background:linear-gradient(135deg,var(--accent),var(--accent-2)); box-shadow:0 12px 24px -12px var(--accent); transition:transform .2s; }
.btn-grad:active{ transform:translateY(2px) scale(.99); }
.alert-err{ background:color-mix(in srgb,var(--danger) 14%,transparent); color:var(--danger); border:1px solid color-mix(in srgb,var(--danger) 40%,transparent);
  border-radius:var(--radius-sm); padding:10px 13px; font-weight:700; font-size:13px; text-align:center; margin-bottom:12px; }

/* task card */
.task{ background:var(--surface); border:1px solid var(--border); border-left:4px solid var(--accent); border-radius:var(--radius-md);
  box-shadow:var(--shadow); padding:13px; margin-top:11px; display:flex; gap:11px; align-items:flex-start; }
.task-body{ flex:1; min-width:0; word-break:break-word; }
.task-title{ font-family:var(--font-display); font-weight:700; font-size:15.5px; color:var(--text); line-height:1.25; }
.task-meta{ display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.meta-chip{ display:inline-flex; align-items:center; gap:5px; font-size:11.5px; font-weight:600; color:var(--text-muted);
  background:var(--surface-2); border:1px solid var(--border); padding:4px 9px; border-radius:30px; }
.meta-chip i.c-date{ color:var(--accent-2); } .meta-chip i.c-time{ color:var(--warn); } .meta-chip i.c-user{ color:var(--accent); }
.task-desc{ margin-top:9px; font-size:12.5px; line-height:1.6; color:var(--text); background:var(--surface-2); border:1px solid var(--border);
  border-radius:var(--radius-sm); padding:9px 11px; white-space:pre-wrap; }
.task-actions{ display:flex; flex-direction:column; gap:7px; align-items:center; flex-shrink:0; }
.act-btn{ width:36px; height:36px; border:none; border-radius:11px; cursor:pointer; display:grid; place-items:center; font-size:13px; transition:transform .2s; }
.act-btn:active{ transform:scale(.85); }
.act-done{ background:linear-gradient(135deg,var(--success),var(--success-deep)); color:#fff; box-shadow:0 8px 14px -8px var(--success); }
.act-hide{ background:var(--surface-2); color:var(--text-muted); border:1px solid var(--border); }
.act-del{ background:color-mix(in srgb,var(--danger) 14%,transparent); color:var(--danger); }
.act-undo{ background:var(--accent-soft); color:var(--accent-deep); }
[data-bs-theme="dark"] .act-undo{ color:#fff; }
.inline-form{ margin:0; display:flex; }

/* empty */
.empty{ text-align:center; padding:30px 12px; color:var(--text-muted); }
.empty i{ font-size:40px; color:var(--accent); opacity:.5; }
.empty p{ margin:12px 0 0; font-weight:700; font-size:14.5px; }

/* completed */
.done-toggle{ width:100%; margin-top:16px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md);
  padding:13px 15px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; color:var(--text); font-weight:700; font-size:13.5px;
  box-shadow:var(--shadow); }
.done-toggle .lead{ display:inline-flex; align-items:center; gap:9px; }
.done-toggle .lead i.h{ color:var(--success); }
.done-toggle .chev{ transition:transform .3s; color:var(--text-soft); }
.done-toggle.open .chev{ transform:rotate(180deg); }
.task.is-done{ border-left-color:var(--text-soft); opacity:.82; }
.task.is-done .task-title{ text-decoration:line-through; color:var(--text-muted); }
.done-by{ display:inline-flex; align-items:center; gap:5px; font-size:10.5px; font-weight:800; margin-top:9px;
  color:var(--success-deep); background:color-mix(in srgb,var(--success) 16%,transparent); padding:4px 9px; border-radius:30px; }
.hidden{ display:none; }

/* theme popup */
.theme-pop{ position:fixed; right:14px; top:64px; z-index:80; background:var(--surface); border:1px solid var(--border); border-radius:16px;
  box-shadow:var(--shadow); padding:13px; width:206px; display:none; }
.theme-pop.open{ display:block; }
.theme-pop h6{ font-family:var(--font-display); font-weight:700; font-size:11.5px; color:var(--text-muted); margin:0 0 9px; text-transform:uppercase; letter-spacing:.5px; }
.swatch-row{ display:flex; gap:9px; }
.swatch{ flex:1; aspect-ratio:1; border-radius:12px; cursor:pointer; border:3px solid transparent; transition:transform .2s; }
.swatch:active{ transform:scale(.88); }
.swatch.sel{ border-color:var(--text); }
.sw-indigo{ background:linear-gradient(135deg,#6d5bf6,#3b82f6); }
.sw-emerald{ background:linear-gradient(135deg,#0ea372,#10b981); }
.sw-sunset{ background:linear-gradient(135deg,#f5576c,#f093a4); }
.sw-ocean{ background:linear-gradient(135deg,#0284c7,#06b6d4); }
.fade-in{ animation:fadeIn .5s ease both; }
@keyframes fadeIn{ from{opacity:0; transform:translateY(12px)} to{opacity:1; transform:none} }
</style>
</head>
<body>

<!-- ===== টপ বার ===== -->
<div class="topbar">
  <div class="topbar-in">
    <a href="../dashboard.php" class="tb-btn tb-back" title="ব্যাক"><i class="fas fa-arrow-left"></i></a>
    <div class="tb-title"><i class="fas fa-list-check"></i> রুটিন প্যানেল</div>
    <div style="margin-left:auto; display:flex; gap:8px;">
      <button class="tb-btn" id="themeBtn" title="থিম"><i class="fas fa-palette"></i></button>
      <button class="tb-btn" id="modeBtn" title="ডার্ক/লাইট"><i class="fas fa-moon"></i></button>
    </div>
  </div>
</div>

<div class="wrap">

  <!-- ===== সারসংক্ষেপ ===== -->
  <div class="stat-row fade-in">
    <div class="stat s-active"><div class="stat-num"><?php echo $activeCount; ?></div><div class="stat-lbl">রানিং কাজ</div></div>
    <div class="stat s-done"><div class="stat-num"><?php echo $completedCount; ?></div><div class="stat-lbl">সম্পন্ন</div></div>
    <div class="stat s-total"><div class="stat-num"><?php echo $totalCount; ?></div><div class="stat-lbl">মোট</div></div>
  </div>

  <?php if ($userRole === 'admin'): ?>
  <!-- ===== নতুন কাজ যুক্ত (অ্যাডমিন) ===== -->
  <div class="card-soft fade-in" style="animation-delay:.06s">
    <div class="card-head">
      <div class="card-ico"><i class="fas fa-plus"></i></div>
      <h3 class="card-title">নতুন কাজ যুক্ত করুন</h3>
      <span class="admin-pill"><i class="fas fa-shield-halved"></i> অ্যাডমিন</span>
    </div>

    <?php if (!empty($errorMessage)): ?>
      <div class="alert-err"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="routine_action" value="add">

      <div class="field">
        <div class="field-top">
          <label>কাজের শিরোনাম</label>
          <div class="voice-tools">
            <button type="button" class="voice-btn" onclick="toggleVoiceTyping('task_title', this)" title="ভয়েস টাইপিং"><i class="fas fa-microphone"></i></button>
            <button type="button" class="voice-btn" onclick="readTextFromInput('task_title')" title="পড়ে শুনুন"><i class="fas fa-volume-up"></i></button>
          </div>
        </div>
        <input type="text" name="task_title" id="task_title" placeholder="কাজের নাম লিখুন..." required class="inp">
      </div>

      <div class="field">
        <div class="field-top">
          <label>বিস্তারিত বিবরণ</label>
          <div class="voice-tools">
            <button type="button" class="voice-btn" onclick="toggleVoiceTyping('task_description', this)" title="ভয়েস টাইপিং"><i class="fas fa-microphone"></i></button>
            <button type="button" class="voice-btn" onclick="readTextFromInput('task_description')" title="পড়ে শুনুন"><i class="fas fa-volume-up"></i></button>
          </div>
        </div>
        <textarea name="task_description" id="task_description" placeholder="কাজের বিস্তারিত বিবরণ..." rows="2" class="inp"></textarea>
      </div>

      <div class="d-flex gap-2 field">
        <input type="date" name="task_date" required class="inp">
        <input type="time" name="task_time" required class="inp">
      </div>

      <div class="field">
        <label style="font-size:13px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:6px;">কাদের জন্য কাজ (একাধিক সিলেক্ট করুন)</label>
        <div class="assignee-box">
          <label class="assignee-all">
            <input type="checkbox" value="all" name="assigned_to[]" onchange="toggleAllUsers(this)">
            🌐 সবাইকে সিলেক্ট করুন
          </label>
          <?php foreach ($allUsersList as $user):
            $uname = (string)($user['username'] ?? '');
            if ($uname === '') continue;
            $safeU = htmlspecialchars($uname, ENT_QUOTES, 'UTF-8');
          ?>
          <label class="assignee-row">
            <input type="checkbox" name="assigned_to[]" value="<?php echo $safeU; ?>" class="user-cb">
            👤 <?php echo $safeU; ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn-grad"><i class="fas fa-floppy-disk"></i> সেভ করুন</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- ===== রানিং কাজ ===== -->
  <div class="card-head fade-in" style="margin-top:20px; animation-delay:.1s">
    <div class="card-ico"><i class="fas fa-bolt"></i></div>
    <h3 class="card-title">রানিং কাজ</h3>
    <span class="admin-pill" style="background:var(--accent-soft); color:var(--accent-deep);"><?php echo $activeCount; ?> টি</span>
  </div>

  <?php if (empty($activeTasks)): ?>
    <div class="empty fade-in"><i class="fas fa-clipboard-check"></i><p>কোনো রানিং কাজ নেই!</p></div>
  <?php else: ?>
    <?php foreach ($activeTasks as $idx => $task):
      $cleanTitle = htmlspecialchars_decode((string)($task['task_title'] ?? ''), ENT_QUOTES);
      $cleanDesc  = htmlspecialchars_decode((string)($task['task_description'] ?? ''), ENT_QUOTES);
      $assignedUsersDisplay = (($task['assigned_to'] ?? '') === 'all') ? 'সবার জন্য' : str_replace(',', ', ', (string)($task['assigned_to'] ?? ''));
      $icon = $iconSet[$idx % count($iconSet)];
    ?>
    <div class="task fade-in">
      <div class="card-ico" style="width:38px; height:38px; border-radius:11px; font-size:15px;"><i class="fas <?php echo $icon; ?>"></i></div>
      <div class="task-body">
        <div class="task-title"><?php echo htmlspecialchars($cleanTitle, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="task-meta">
          <span class="meta-chip"><i class="fas fa-calendar-alt c-date"></i> <?php echo date('d M, y', strtotime((string)$task['task_date'])); ?></span>
          <span class="meta-chip"><i class="fas fa-clock c-time"></i> <?php echo date('h:i A', strtotime((string)$task['task_time'])); ?></span>
          <span class="meta-chip"><i class="fas fa-user-tag c-user"></i> <?php echo htmlspecialchars($assignedUsersDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php if (!empty($cleanDesc)): ?>
          <div class="task-desc"><?php echo htmlspecialchars($cleanDesc, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>
      <div class="task-actions">
        <form method="POST" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="routine_action" value="mark_done">
          <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
          <button type="submit" onclick="return confirm('কাজটি সম্পন্ন করেছেন?');" class="act-btn act-done" title="সম্পন্ন"><i class="fas fa-check"></i></button>
        </form>
        <?php if ($userRole === 'admin'): ?>
          <form method="POST" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="routine_action" value="toggle">
            <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
            <input type="hidden" name="new_status" value="inactive">
            <button type="submit" class="act-btn act-hide" title="লুকান"><i class="fas fa-eye-slash"></i></button>
          </form>
          <form method="POST" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="routine_action" value="delete">
            <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
            <button type="submit" onclick="return confirm('ডিলিট করবেন?');" class="act-btn act-del" title="ডিলিট"><i class="fas fa-trash-alt"></i></button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- ===== সম্পন্ন হওয়া কাজ (কোলাপ্সিবল) ===== -->
  <?php if (!empty($completedTasks)): ?>
    <button class="done-toggle" id="doneToggle" onclick="toggleCompleted(this)">
      <span class="lead"><i class="fas fa-history h"></i> সম্পন্ন হওয়া কাজ (<?php echo count($completedTasks); ?>)</span>
      <i class="fas fa-chevron-down chev"></i>
    </button>
    <div id="completedTasksSection" class="hidden">
      <?php foreach ($completedTasks as $idx => $task):
        $cleanTitle = htmlspecialchars_decode((string)($task['task_title'] ?? ''), ENT_QUOTES);
        $assignedUsersDisplay = (($task['assigned_to'] ?? '') === 'all') ? 'সবার জন্য' : str_replace(',', ', ', (string)($task['assigned_to'] ?? ''));
      ?>
      <div class="task is-done">
        <div class="card-ico" style="width:38px; height:38px; border-radius:11px; font-size:15px; background:linear-gradient(135deg,var(--text-soft),var(--text-muted));"><i class="fas fa-check-double"></i></div>
        <div class="task-body">
          <div class="task-title"><?php echo htmlspecialchars($cleanTitle, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="task-meta">
            <span class="meta-chip"><i class="fas fa-calendar-alt"></i> <?php echo date('d M, y', strtotime((string)$task['task_date'])); ?></span>
            <span class="meta-chip"><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($assignedUsersDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <?php if (!empty($task['completed_by'])): ?>
            <span class="done-by"><i class="fas fa-check-double"></i> সম্পন্ন: @<?php echo htmlspecialchars((string)$task['completed_by'], ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
        </div>
        <?php if ($userRole === 'admin'): ?>
        <div class="task-actions">
          <form method="POST" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="routine_action" value="toggle">
            <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
            <input type="hidden" name="new_status" value="active">
            <button type="submit" class="act-btn act-undo" title="আবার চালু করুন"><i class="fas fa-undo"></i></button>
          </form>
          <form method="POST" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="routine_action" value="delete">
            <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
            <button type="submit" onclick="return confirm('ডিলিট করবেন?');" class="act-btn act-del" title="ডিলিট"><i class="fas fa-trash-alt"></i></button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<!-- থিম পপআপ -->
<div class="theme-pop" id="themePop">
  <h6>কালার থিম</h6>
  <div class="swatch-row">
    <div class="swatch sw-indigo sel" data-t="indigo"></div>
    <div class="swatch sw-emerald" data-t="emerald"></div>
    <div class="swatch sw-sunset" data-t="sunset"></div>
    <div class="swatch sw-ocean" data-t="ocean"></div>
  </div>
</div>

<!-- 📖 ডকুমেন্টেশন বাটন -->
<button class="done-toggle" id="docToggle" onclick="toggleDocSection(this)" style="margin-top:20px;">
  <span class="lead"><i class="fas fa-book"></i> 📖 ডকুমেন্টেশন ও সাহায্য</span>
  <i class="fas fa-chevron-down chev"></i>
</button>

<div id="docSection" class="hidden" style="margin-top:;">
  <div class="card-soft" style="border-left:4px solid var(--accent);">
    
    <!-- ═══ ওভারভিউ ═══ -->
    <div class="doc-block">
      <div class="doc-icon"><i class="fas fa-info-circle"></i></div>
      <div>
        <h2 class="doc-title">📋 ওভারভিউ</h2>
        <p class="doc-text">এটি একটি <strong>সম্পূর্ণ MVC-স্ট্রাকচারড ডেইলি টাস্ক/রুটিন ম্যানেজমেন্ট সিস্টেম</strong> যা PHP, PDO, MySQL এবং আধুনিক JavaScript (Web Speech API) দিয়ে তৈরি।</p>
      </div>
    </div>

    <!-- ═══ ফোল্ডার স্ট্রাকচার ═══ -->
    <div class="doc-block">
      <div class="doc-icon"><i class="fas fa-folder-tree"></i></div>
      <div>
        <h2 class="doc-title">🏗️ ফোল্ডার স্ট্রাকচার</h2>
        <pre class="doc-code">Task/
├── Config/
│   └── database.php          # ডেটাবেজ কানেকশন কনফিগারেশন
├── Controllers/
│   └── TaskController.php     # রিকোয়েস্ট হ্যান্ডলার (CSRF + রোল ভ্যালিডেশন)
├── Models/
│   ├── TaskModelInterface.php # ইন্টারফেস/কন্ট্রাক্ট
│   └── TaskModel.php          # ডেটা অ্যাক্সেস লেয়ার (PDO)
├── Views/
│   ├── index.php              # UI টেমপ্লেট (বাংলা)
│   └── dashboard_routine.php  # ড্যাশবোর্ডের জন্য মিনি ভিউ
├── Logs/
│   └── index.php              # ডিরেক্টরি প্রোটেকশন
├── index.php                  # এন্ট্রি পয়েন্ট (ফ্রন্ট কন্ট্রোলার)
└── README.md                  # ডকুমেন্টেশন</pre>
      </div>
    </div>

    <!-- ═══ ফিচার ═══ -->
    <div class="doc-block">
      <div class="doc-icon"><i class="fas fa-star"></i></div>
      <div>
        <h2 class="doc-title">🚀 ফিচারসমূহ</h2>
        <div class="doc-grid">
          <span class="doc-badge">✅ MVC আর্কিটেকচার</span>
          <span class="doc-badge">✅ রোল-বেসড অ্যাক্সেস</span>
          <span class="doc-badge">✅ CSRF প্রোটেকশন</span>
          <span class="doc-badge">✅ PDO প্রিপেয়ার্ড স্টেটমেন্ট</span>
          <span class="doc-badge">✅ XSS প্রিভেনশন</span>
          <span class="doc-badge">✅ ট্রানজ্যাকশন সেফটি</span>
          <span class="doc-badge">✅ PRG প্যাটার্ন</span>
          <span class="doc-badge">✅ বাংলা ইউজার ইন্টারফেস</span>
          <span class="doc-badge">✅ ভয়েস টাইপিং</span>
          <span class="doc-badge">✅ টেক্সট-টু-স্পিচ (TTS)</span>
          <span class="doc-badge">✅ ডার্ক/লাইট মোড</span>
          <span class="doc-badge">✅ ৪ কালার থিম</span>
          <span class="doc-badge">✅ রেসপনসিভ ডিজাইন</span>
        </div>
      </div>
    </div>

    <!-- ═══ রিকোয়্যারমেন্ট ═══ -->
    <div class="doc-block">
      <div class="doc-icon"><i class="fas fa-cog"></i></div>
      <div>
        <h2 class="doc-title">🛠️ রিকোয়্যারমেন্ট</h2>
        <ul class="doc-list">
          <li>PHP 8.+</li>
          <li>MySQL 5.7+</li>
          <li>PDO MySQL এক্সটেনশন</li>
          <li>Web Speech API সাপোর্টেড ব্রাউজার (Chrome/Edge) — ভয়েস টাইপিংয়ের জন্য</li>
        </ul>
      </div>
    </div>

    <!-- ═══ ডেটাবেজ স্কিমা ═══ -->
    <div class="doc-block">
      <div class="doc-icon"><i class="fas fa-database"></i></div>
      <div>
        <h2 class="doc-title">🗄️ ডেটাবেজ স্কিমা</h2>
        <pre class="doc-code">CREATE TABLE IF NOT EXISTS `daily_tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_title` VARCHAR(255) NOT NULL,
    `task_description` TEXT NULL,
    `task_date` DATE NOT NULL,
    `task_time` TIME NOT NULL,
    `assigned_to` VARCHAR(500) NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `completed_by` VARCHAR(100) NULL,
    `completed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_date` (`task_date`),
    INDEX `idx_assigned` (`assigned_to`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;</pre>
        <p class="doc-text" style="margin-top:8px;">সিস্টেমে <code>users</code> টেবিলও প্রয়োজন (username ও role কলাম সহ)।</p>
      </div>
    </div>

    <!-- ═══ API রেফারেন্স ═══ -->
    <div class="doc-block">
      <div class="doc-icon"><i class="fas fa-code"></i></div>
      <div>
        <h2 class="doc-title">🌐 API রেফারেন্স — TaskModelInterface মেথডসমূহ</h2>
        <div class="table-wrap">
          <table class="doc-table">
            <thead><tr><th>মেথড</th><th>প্যারামিটার</th><th>বিবরণ</th></tr></thead>
            <tbody>
              <tr><td><code>getAllUsers()</code></td><td>—</td><td>সব সিস্টেম ইউজার রিটার্ন করে</td></tr>
              <tr><td><code>getTasks()</code></td><td><code>$username, $role</code></td><td>ইউজার-নির্দিষ্ট টাস্ক লিস্ট</td></tr>
              <tr><td><code>addTask()</code></td><td><code>$title, $date, $time, $assignedTo, $description</code></td><td>নতুন টাস্ক তৈরি</td></tr>
              <tr><td><code>markTaskAsDone()</code></td><td><code>$id, $username</code></td><td>টাস্ক সম্পন্ন চিহ্নিত</td></tr>
              <tr><td><code>toggleTaskStatus()</code></td><td><code>$id, $newStatus</code></td><td>active/inactive টগল</td></tr>
              <tr><td><code>deleteTask()</code></td><td><code>$id</code></td><td>টাস্ক ডিলিট</td></tr>
            </tbody>
          </table>
        </div>

        <h2 class="doc-title" style="margin-top:18px;">⚙️ TaskController POST অ্যাকশনসমূহ</h2>
        <div class="table-wrap">
          <table class="doc-table">
            <thead><tr><th><code>routine_action</code></th><th>অনুমতি</th><th>বিবরণ</th></tr></thead>
            <tbody>
              <tr><td><code>add</code></td><td>শুধু Admin</td><td>নতুন টাস্ক তৈরি</td></tr>
              <tr><td><code>mark_done</code></td><td>সবাই</td><td>টাস্ক সম্পন্ন চিহ্নিত</td></tr>
              <tr><td><code>toggle</code></td><td>শুধু Admin</td><td>স্ট্যাটাস পরিবর্তন</td></tr>
              <tr><td><code>delete</code></td><td>শুধু Admin</td><td>টাস্ক মুছে ফেলা</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══ নিরাপত্তা ═══ -->
    <div class="doc-block">
      <div class="doc-icon"><i class="fas fa-shield-alt"></i></div>
      <div>
        <h2 class="doc-title">🔒 নিরাপত্তা</h2>
        <ul class="doc-list">
          <li>CSRF টোকেন — সব POST রিকোয়েস্টে যাচাই</li>
          <li>রোল ভেরিফিকেশন — সংবেদনশীল অ্যাকশনের জন্য (admin/non-admin)</li>
          <li>PDO প্রিপেয়ার্ড স্টেটমেন্ট — SQL ইনজেকশন প্রতিরোধ</li>
          <li><code>htmlspecialchars()</code> — XSS আক্রমণ প্রতিরোধ</li>
          <li>সেশন-বেসড অথেন্টিকেশন — টাইমআউট সহ</li>
          <li>লগ স্টোরেজ — Logs/ ডিরেক্টরিতে সুরক্ষিত</li>
        </ul>
      </div>
    </div>

    <!-- ═══ লাইসেন্স ═══ -->
    <div class="doc-block" style="border-bottom:none; margin-bottom:;">
      <div class="doc-icon"><i class="fas fa-copyright"></i></div>
      <div>
        <h2 class="doc-title">📝 লাইসেন্স</h2>
        <p class="doc-text"><strong>Proprietary</strong> — SADA KALO FASHION Internal Use Only</p>
      </div>
    </div>

  </div>
</div>

<style>
/* ── ডকুমেন্টেশন স্টাইল ── */
.doc-block {
  display:flex; gap:14px; align-items:flex-start;
  padding:14px ; border-bottom:1px solid var(--border);
  margin-bottom:4px;
}
.doc-icon {
  width:40px; height:40px; flex-shrink:; border-radius:11px;
  background:linear-gradient(135deg,var(--accent),var(--accent-2));
  color:#fff; display:grid; place-items:center; font-size:17px;
  box-shadow: 6px 12px -6px var(--accent); margin-top:4px;
}
.doc-title {
  font-family:var(--font-display); font-weight:700; font-size:16px;
  color:var(--text); margin:  6px; line-height:1.3;
}
.doc-text { font-size:13.5px; color:var(--text); line-height:1.7; margin:; }
.doc-code {
  background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius-sm);
  padding:12px 14px; font-size:12px; line-height:1.6; overflow-x:auto;
  color:var(--text); font-family:'Courier New',monospace; margin:6px  ; white-space:pre;
}
.doc-grid { display:flex; flex-wrap:wrap; gap:6px; margin-top:4px; }
.doc-badge {
  display:inline-flex; align-items:center; gap:4px;
  background:var(--accent-soft); color:var(--accent-deep); border:1px solid color-mix(in srgb,var(--accent) 20%,transparent);
  padding:5px 10px; border-radius:20px; font-size:12px; font-weight:700;
}
.doc-list { margin:4px  ; padding-left:20px; font-size:13.5px; color:var(--text); line-height:1.8; }
.doc-list li { margin-bottom:3px; }
.doc-list li::marker { color:var(--accent); }
.table-wrap { overflow-x:auto; margin-top:6px; }
.doc-table {
  width:100%; border-collapse:collapse; font-size:12.5px;
  background:var(--surface-2); border-radius:var(--radius-sm); overflow:hidden;
}
.doc-table th, .doc-table td {
  padding:8px 10px; text-align:left; border-bottom:1px solid var(--border);
  white-space:nowrap; color:var(--text);
}
.doc-table th { background:var(--accent-soft); color:var(--accent-deep); font-weight:700; }
.doc-table td code { color:var(--accent-2); font-weight:600; }
.doc-table tr:last-child td { border-bottom:none; }
</style>

<script>
  const root = document.documentElement;

  /* ----- ডার্ক/লাইট মোড ----- */
  const modeBtn = document.getElementById('modeBtn');
  function applyMode(m){ root.setAttribute('data-bs-theme', m); modeBtn.innerHTML = (m==='dark') ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>'; try{ localStorage.setItem('routine_mode', m); }catch(e){} }
  modeBtn.addEventListener('click', () => applyMode(root.getAttribute('data-bs-theme')==='dark'?'light':'dark'));
  applyMode((function(){ try{ return localStorage.getItem('routine_mode'); }catch(e){ return null; } })() || 'light');

  /* ----- কালার থিম ----- */
  const themeBtn = document.getElementById('themeBtn'), pop = document.getElementById('themePop');
  themeBtn.addEventListener('click', (e)=>{ e.stopPropagation(); pop.classList.toggle('open'); });
  document.addEventListener('click', ()=> pop.classList.remove('open'));
  pop.addEventListener('click', (e)=> e.stopPropagation());
  function applyTheme(t){ root.setAttribute('data-theme', t); try{ localStorage.setItem('routine_theme', t); }catch(e){} document.querySelectorAll('.swatch').forEach(s=> s.classList.toggle('sel', s.dataset.t===t)); }
  document.querySelectorAll('.swatch').forEach(s=> s.addEventListener('click', ()=> applyTheme(s.dataset.t)));
  applyTheme((function(){ try{ return localStorage.getItem('routine_theme'); }catch(e){ return null; } })() || 'indigo');

  /* ----- সম্পন্ন সেকশন টগল ----- */
  function toggleCompleted(btn){ btn.classList.toggle('open'); document.getElementById('completedTasksSection').classList.toggle('hidden'); }

  /* ===================================================================
     নিচের সব লজিক মূল ফাইল থেকে হুবহু অপরিবর্তিত:
     select-all, ভয়েস টাইপিং (bn-BD), এবং পড়ে শোনানো (TTS)
     =================================================================== */
  function toggleAllUsers(source) {
      const checkboxes = document.querySelectorAll('.user-cb');
      for(let i = 0; i < checkboxes.length; i++) {
          checkboxes[i].checked = source.checked;
      }
  }

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  const synth = window.speechSynthesis;
  let activeRecognition = null;
  let activeBtn = null;

  function toggleVoiceTyping(targetId, btn) {
      if (btn.classList.contains('recording')) {
          if(activeRecognition) activeRecognition.stop();
          stopRec(btn);
          return;
      }

      if (!SpeechRecognition) {
          alert("আপনার ব্রাউজারে ভয়েস টাইপিং সাপোর্ট করে না।");
          return;
      }

      if(activeRecognition) {
          activeRecognition.stop();
          if(activeBtn) stopRec(activeBtn);
      }

      activeRecognition = new SpeechRecognition();
      activeBtn = btn;

      activeRecognition.lang = 'bn-BD';
      activeRecognition.interimResults = true;
      activeRecognition.continuous = true;

      const targetField = document.getElementById(targetId);

      let startPos = targetField.selectionStart;
      let textBefore = targetField.value.substring(0, startPos);
      let textAfter = targetField.value.substring(targetField.selectionEnd);

      activeRecognition.onstart = () => {
          btn.classList.add('recording');
          btn.innerHTML = '<i class="fas fa-microphone-alt"></i>';
      };

      activeRecognition.onresult = (event) => {
          let finalTranscript = '';
          let interimTranscript = '';

          for (let i = event.resultIndex; i < event.results.length; ++i) {
              if (event.results[i].isFinal) {
                  finalTranscript += event.results[i][0].transcript;
              } else {
                  interimTranscript += event.results[i][0].transcript;
              }
          }

          let currentDictation = finalTranscript + interimTranscript;
          targetField.value = textBefore + " " + currentDictation.trim() + " " + textAfter;

          let newCursorPos = textBefore.length + currentDictation.length + 2;
          targetField.setSelectionRange(newCursorPos, newCursorPos);
      };

      activeRecognition.onerror = (e) => {
          if(e.error !== 'no-speech') stopRec(btn);
      };

      activeRecognition.onend = () => {
          stopRec(btn);
      };

      activeRecognition.start();
  }

  function stopRec(btn) {
      btn.classList.remove('recording');
      btn.innerHTML = '<i class="fas fa-microphone"></i>';
  }

  function readTextFromInput(targetId) {
      const text = document.getElementById(targetId).value;
      if (!text) return;
      synth.cancel();
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.lang = 'bn-BD';
      utterance.rate = 0.85;
      utterance.pitch = 1.1;
      synth.speak(utterance);
  }
</script>
</body>
</html>
