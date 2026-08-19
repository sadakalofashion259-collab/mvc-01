<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if(isset($_SESSION['last_activity'])&&(time()-$_SESSION['last_activity']>1200)){session_unset();session_destroy();echo "<script>alert('Session Expired!');window.location.href='../../index.php';</script>";exit;}
$_SESSION['last_activity']=time();
if(!isset($_SESSION['loggedin'])||$_SESSION['loggedin']!==true){echo "<script>window.location.href='../../index.php';</script>";exit;}
$role=$_SESSION['role']??'admin';
if($role!=='admin'&&$role!=='Admin'){echo "<script>alert('Access Denied! Admin Only.');window.location.href='../../index.php';</script>";exit;}
include __DIR__ . '/../../db_connect.php';
date_default_timezone_set('Asia/Dhaka');

$staff_id = intval($_GET['id'] ?? 0);
if (!$staff_id) {
    echo "<script>window.location.href='List.php';</script>"; exit;
}

// Staff info - only safe columns
try {
    $stmt = $conn->prepare("SELECT id, staff_name, staff_email, staff_phone, staff_salary, staff_join_date, staff_status, profile_pic FROM staff_info WHERE id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $staff = null;
}

if (!$staff) {
    echo "<script>window.location.href='List.php';</script>"; exit;
}

$daily_salary = floatval($staff['staff_salary']) / 30;
$pic_path = "../../Uploads/profiles/" . ($staff['profile_pic'] ?? 'default.png');
if (empty($staff['profile_pic']) || $staff['profile_pic'] === 'default.png' || !file_exists($pic_path)) {
    $pic_path = "../../Uploads/profiles/default.png";
}

// Pagination
$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 30;
$offset   = ($page - 1) * $per_page;

// Count attendance
$total_att   = 0;
$total_pages = 1;
try {
    $cnt = $conn->prepare("SELECT COUNT(*) FROM staff_attendance WHERE staff_id = ?");
    $cnt->execute([$staff_id]);
    $total_att   = (int)$cnt->fetchColumn();
    $total_pages = max(1, ceil($total_att / $per_page));
} catch(Exception $e) {}

// Attendance list with inline LIMIT
$attendances = [];
try {
    $lim = (int)$per_page;
    $off = (int)$offset;
    $att_q = $conn->prepare("SELECT status, late_time, attendance_date, leave_note FROM staff_attendance WHERE staff_id = ? ORDER BY attendance_date DESC LIMIT {$lim} OFFSET {$off}");
    $att_q->execute([$staff_id]);
    $attendances = $att_q->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Attendance summary
$att_s = ['Present'=>0,'Absent'=>0,'Half'=>0,'Leave'=>0,'Late'=>0];
try {
    $sq = $conn->prepare("SELECT status, COUNT(*) as cnt, COALESCE(SUM(late_time),0) as late FROM staff_attendance WHERE staff_id = ? GROUP BY status");
    $sq->execute([$staff_id]);
    while ($r = $sq->fetch(PDO::FETCH_ASSOC)) {
        if (isset($att_s[$r['status']])) $att_s[$r['status']] = (int)$r['cnt'];
        $att_s['Late'] += (int)$r['late'];
    }
} catch(Exception $e) {}

// Expenses
$expenses      = [];
$total_expense = 0;
try {
    $eq = $conn->prepare("SELECT expense_type, amount, expense_date, description FROM staff_expenses WHERE staff_id = ? ORDER BY expense_date DESC");
    $eq->execute([$staff_id]);
    $expenses      = $eq->fetchAll(PDO::FETCH_ASSOC);
    $total_expense = array_sum(array_column($expenses, 'amount'));
} catch(Exception $e) {}

// Settlements
$settlements = [];
try {
    $sq2 = $conn->prepare("SELECT settlement_date, from_date, to_date, final_balance FROM staff_settlements WHERE staff_id = ? ORDER BY settlement_date DESC");
    $sq2->execute([$staff_id]);
    $settlements = $sq2->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Previous staff - check column exists first
$prev_staff = null;
try {
    $col = $conn->query("SHOW COLUMNS FROM staff_info LIKE 'previous_staff_id'");
    if ($col && $col->rowCount() > 0) {
        $pq = $conn->prepare("SELECT previous_staff_id FROM staff_info WHERE id = ?");
        $pq->execute([$staff_id]);
        $prow = $pq->fetch(PDO::FETCH_ASSOC);
        if (!empty($prow['previous_staff_id'])) {
            $pq2 = $conn->prepare("SELECT id, staff_name FROM staff_info WHERE id = ?");
            $pq2->execute([$prow['previous_staff_id']]);
            $prev_staff = $pq2->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="bn" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($staff['staff_name']); ?> — Archived</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script>tailwind.config={darkMode:'class'}</script>
<style>
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-thumb{background:#334155;border-radius:10px}
.tab-btn.active{background:#10b981!important;color:#fff!important;border-color:#10b981!important}
</style>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen pb-10">

<nav class="sticky top-0 z-50 flex justify-between items-center bg-slate-900 px-4 py-2 border-b border-slate-800">
    <a href="InactiveList.php" class="text-sky-500 font-bold bg-slate-800 px-3 py-1.5 rounded-lg text-xs flex items-center gap-2">
        <i class="fas fa-arrow-left"></i> Inactive List
    </a>
    <span class="text-slate-400 font-black text-xs uppercase tracking-widest">ARCHIVED</span>
    <button onclick="window.print()" class="text-slate-400 bg-slate-800 px-3 py-1.5 rounded-lg text-xs">
        <i class="fas fa-print"></i>
    </button>
</nav>

<div class="max-w-2xl mx-auto px-3 mt-4">

    <!-- Profile -->
    <div class="bg-slate-900 border-2 border-dashed border-slate-700 rounded-2xl p-4 mb-4 flex gap-4 items-center">
        <div class="relative">
            <img src="<?php echo htmlspecialchars($pic_path); ?>" class="w-16 h-16 rounded-full object-cover border-2 border-slate-600 grayscale">
            <span class="absolute -bottom-1 -right-1 bg-rose-600 text-white text-[7px] font-black px-1 py-0.5 rounded-full">OFF</span>
        </div>
        <div class="flex-1 min-w-0">
            <h2 class="text-sm font-black text-slate-300 uppercase"><?php echo htmlspecialchars($staff['staff_name']); ?></h2>
            <p class="text-xs text-slate-500"><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($staff['staff_phone'] ?? ''); ?></p>
            <p class="text-xs text-slate-500 truncate"><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($staff['staff_email'] ?? ''); ?></p>
            <p class="text-xs text-slate-600 mt-1">Joining: <?php echo date('d M Y', strtotime($staff['staff_join_date'])); ?></p>
        </div>
        <div class="text-right text-xs flex-shrink-0">
            <div class="text-slate-500">Salary</div>
            <div class="font-black text-emerald-500">Tk. <?php echo number_format($staff['staff_salary'],0); ?></div>
        </div>
    </div>

    <?php if (!empty($prev_staff)): ?>
    <div class="bg-amber-950/30 border border-amber-800/40 rounded-xl p-3 mb-4 text-xs text-amber-400 flex gap-2 items-center">
        <i class="fas fa-link"></i>
        পূর্বের Profile:
        <a href="InactiveHistory.php?id=<?php echo (int)$prev_staff['id']; ?>" class="font-black underline">
            <?php echo htmlspecialchars($prev_staff['staff_name']); ?> (#<?php echo (int)$prev_staff['id']; ?>)
        </a>
    </div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3">
            <div class="text-[9px] text-slate-500 uppercase font-black mb-2">Lifetime হাজিরা</div>
            <div class="grid grid-cols-2 gap-1 text-xs">
                <span class="text-emerald-400 font-black">P: <?php echo $att_s['Present']; ?></span>
                <span class="text-rose-400 font-black">A: <?php echo $att_s['Absent']; ?></span>
                <span class="text-orange-400 font-black">H: <?php echo $att_s['Half']; ?></span>
                <span class="text-sky-400 font-black">L: <?php echo $att_s['Leave']; ?></span>
            </div>
            <div class="text-[10px] text-purple-400 font-black mt-1">Late: <?php echo $att_s['Late']; ?> min</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3">
            <div class="text-[9px] text-slate-500 uppercase font-black mb-2">মোট খরচ</div>
            <div class="text-xl font-black text-rose-400">Tk. <?php echo number_format($total_expense,2); ?></div>
            <div class="text-[9px] text-slate-500 mt-1"><?php echo count($expenses); ?>টি entry</div>
        </div>
    </div>

    <!-- Settlements -->
    <?php if (!empty($settlements)): ?>
    <div class="bg-slate-900 border border-emerald-900/40 rounded-xl p-4 mb-4">
        <div class="text-[10px] font-black text-emerald-400 uppercase mb-3">
            <i class="fas fa-file-invoice-dollar mr-1"></i>Settlement ইতিহাস
        </div>
        <?php foreach ($settlements as $s): ?>
        <div class="border-b border-slate-800 pb-3 mb-3 last:border-0 last:mb-0 last:pb-0 text-xs">
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-black text-slate-300"><?php echo date('d M Y', strtotime($s['settlement_date'])); ?></div>
                    <div class="text-slate-500"><?php echo date('d M Y', strtotime($s['from_date'])); ?> → <?php echo date('d M Y', strtotime($s['to_date'])); ?></div>
                </div>
                <div class="text-right">
                    <?php $fb = floatval($s['final_balance'] ?? 0); ?>
                    <div class="font-black <?php echo $fb >= 0 ? 'text-emerald-400' : 'text-rose-400'; ?>">
                        Tk. <?php echo number_format(abs($fb),2); ?>
                        <span class="text-[9px]">(<?php echo $fb >= 0 ? 'Staff পেয়েছে' : 'কোম্পানি পেয়েছে'; ?>)</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex gap-2 mb-4">
        <button onclick="showTab('att')" id="tab-att" class="tab-btn active flex-1 text-[10px] font-black py-2 rounded-lg border border-slate-700 bg-slate-800 transition">
            <i class="fas fa-calendar-check mr-1"></i>হাজিরা (<?php echo $total_att; ?>)
        </button>
        <button onclick="showTab('exp')" id="tab-exp" class="tab-btn flex-1 text-[10px] font-black py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-400 transition">
            <i class="fas fa-money-bill mr-1"></i>খরচ (<?php echo count($expenses); ?>)
        </button>
    </div>

    <!-- Attendance pane -->
    <div id="pane-att">
        <?php if (empty($attendances)): ?>
        <div class="text-center text-slate-500 text-sm py-10 bg-slate-900 rounded-xl border border-slate-800">
            <i class="fas fa-calendar-times text-2xl mb-2 block"></i>কোনো হাজিরা নেই
        </div>
        <?php else: ?>
        <div class="space-y-2">
        <?php
        $sc = ['Present'=>'emerald','Absent'=>'rose','Half'=>'orange','Leave'=>'sky'];
        $sl = ['Present'=>'উপস্থিত','Absent'=>'অনুপস্থিত','Half'=>'হাফ ডে','Leave'=>'ছুটি'];
        foreach ($attendances as $a):
            $c = $sc[$a['status']] ?? 'slate';
            $l = $sl[$a['status']] ?? $a['status'];
        ?>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 flex items-center justify-between">
            <div>
                <div class="text-xs font-black text-slate-300"><?php echo date('d M Y, D', strtotime($a['attendance_date'])); ?></div>
                <?php if (!empty($a['leave_note'])): ?>
                <div class="text-[9px] text-slate-500 mt-0.5"><?php echo htmlspecialchars($a['leave_note']); ?></div>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                <?php if (intval($a['late_time']) > 0): ?>
                <span class="text-[9px] text-purple-400 font-black"><?php echo (int)$a['late_time']; ?>m late</span>
                <?php endif; ?>
                <span class="text-[10px] font-black px-2.5 py-1 rounded-full bg-<?php echo $c; ?>-500/10 text-<?php echo $c; ?>-400 border border-<?php echo $c; ?>-500/20">
                    <?php echo $l; ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center gap-2 mt-4 flex-wrap">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="?id=<?php echo $staff_id; ?>&page=<?php echo $p; ?>"
               class="px-3 py-1 rounded text-xs font-black <?php echo $p === $page ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400'; ?>">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>
        </div>
        <div class="text-center text-[9px] text-slate-600 mt-2">মোট <?php echo $total_att; ?>টি · প্রতি page-এ ৩০টি</div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Expense pane -->
    <div id="pane-exp" class="hidden">
        <?php if (empty($expenses)): ?>
        <div class="text-center text-slate-500 text-sm py-10 bg-slate-900 rounded-xl border border-slate-800">
            <i class="fas fa-receipt text-2xl mb-2 block"></i>কোনো খরচ নেই
        </div>
        <?php else: ?>
        <div class="space-y-2">
        <?php foreach ($expenses as $e): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3 flex items-center justify-between">
            <div>
                <div class="text-[9px] font-black text-slate-500 uppercase mb-0.5"><?php echo htmlspecialchars($e['expense_type']); ?></div>
                <div class="text-xs font-black text-slate-300"><?php echo date('d M Y', strtotime($e['expense_date'])); ?></div>
                <?php if (!empty($e['description'])): ?>
                <div class="text-[9px] text-slate-500 mt-0.5"><?php echo htmlspecialchars($e['description']); ?></div>
                <?php endif; ?>
            </div>
            <div class="text-sm font-black text-rose-400">Tk. <?php echo number_format(floatval($e['amount']),2); ?></div>
        </div>
        <?php endforeach; ?>
        </div>
        <div class="bg-slate-800 rounded-xl p-3 mt-3 flex justify-between items-center">
            <span class="text-xs font-black text-slate-400">মোট খরচ</span>
            <span class="text-sm font-black text-rose-400">Tk. <?php echo number_format($total_expense,2); ?></span>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function showTab(tab) {
    ['att','exp'].forEach(function(t) {
        document.getElementById('pane-'+t).classList.add('hidden');
        document.getElementById('tab-'+t).classList.remove('active');
        document.getElementById('tab-'+t).classList.add('text-slate-400');
    });
    document.getElementById('pane-'+tab).classList.remove('hidden');
    document.getElementById('tab-'+tab).classList.add('active');
    document.getElementById('tab-'+tab).classList.remove('text-slate-400');
}
</script>

</body>
</html>
