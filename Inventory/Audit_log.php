<?php
declare(strict_types=1);
session_start();
include 'db_connect.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$conn->exec("SET NAMES utf8mb4");
date_default_timezone_set('Asia/Dhaka');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'secure_delete') {
    ob_clean(); header('Content-Type: application/json');
    if (!hash_equals($_SESSION['csrf_token'], $_POST['token'] ?? '')) { echo json_encode(['s'=>'err','m'=>'নিরাপত্তা ত্রুটি!']); exit; }
    if (($_POST['pass'] ?? '') === '@2233') {
        try {
            $user_to_del = $_POST['user'] ?? ''; $date_to_del = $_POST['date'] ?? '';
            $stmt = $conn->prepare("DELETE FROM audit_logs WHERE (username = ? OR username = CONCAT('@', ?)) AND DATE(created_at) = ?");
            $stmt->execute([$user_to_del, $user_to_del, $date_to_del]);
            if ($stmt->rowCount() > 0) echo json_encode(['s'=>'ok','m'=>'লগ মুছে ফেলা হয়েছে।']);
            else echo json_encode(['s'=>'err','m'=>'কোনো ডাটা পাওয়া যায়নি।']);
        } catch (Exception $e) { echo json_encode(['s'=>'err','m'=>'ডাটাবেজ ত্রুটি!']); }
    } else { echo json_encode(['s'=>'err','m'=>'ভুল পাসওয়ার্ড!']); }
    exit;
}

$f_date = $_GET['from_date'] ?? date('Y-m-01');
$t_date = $_GET['to_date'] ?? date('Y-m-d');

$logs = [];
try {
    $stmt = $conn->prepare("SELECT * FROM audit_logs WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
    $stmt->execute([$f_date, $t_date]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$groupedLogs = [];
foreach ($logs as $log) {
    $rawUser = $log['username'] ?? 'Unknown';
    $displayUser = ltrim($rawUser, '@');
    $d = date('Y-m-d', strtotime($log['created_at']));
    $groupedLogs[$displayUser][$d][] = $log;
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme-toggle.js"></script>
    <style>
        .user-group { background:var(--sk-surface); margin-bottom:.75rem; border-radius:.75rem; box-shadow:var(--sk-shadow-sm); overflow:hidden; border:1px solid var(--sk-line); }
        .user-head { background:var(--sk-grad-primary); color:#fff; padding:.875rem 1rem; cursor:pointer; font-weight:700; display:flex; justify-content:space-between; align-items:center; }
        .user-head:hover { filter:brightness(1.05); }
        .date-row { background:var(--sk-surface-2); padding:.625rem .875rem; border-bottom:1px solid var(--sk-line); display:flex; justify-content:space-between; align-items:center; font-weight:700; font-size:.85rem; }
        .date-row:last-of-type { border-bottom:0; }
        .audit-table { width:100%; border-collapse:collapse; font-size:.75rem; }
        .audit-table th, .audit-table td { padding:.5rem .75rem; border-bottom:1px solid var(--sk-line-2); text-align:left; }
        .audit-table th { background:var(--sk-surface-2); font-weight:700; color:var(--sk-ink); }
        .audit-table tr:last-child td { border-bottom:0; }
        .btn-del { color:var(--sk-danger); cursor:pointer; font-size:1rem; padding:.25rem; transition:.15s; }
        .btn-del:hover { transform:scale(1.2); }
        .highlight-red { background:var(--sk-danger-soft); }
    </style>
</head>
<body>

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <a href="dashboard.php" class="sk-iconbtn"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title"><span class="dot"></span> Audit Logs 2026</div>
    <div class="sk-appbar__right">
        <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<main class="sk-container">

    <div class="sk-card sk-card--ink" style="margin-bottom:.875rem; text-align:center;">
        <h2 style="margin:0; color:#fff; font-size:1.1rem; font-weight:800;"><i class="fas fa-history"></i> Audit Logs 2026</h2>
        <a href="dashboard.php" style="color:var(--sk-primary-ink); text-decoration:none; font-size:.75rem; font-weight:600;">ড্যাশবোর্ডে ফিরে যান</a>
    </div>

    <form method="GET" class="sk-card" style="display:flex; gap:.5rem; margin-bottom:.875rem; align-items:center; flex-wrap:wrap;">
        <input type="date" name="from_date" value="<?php echo htmlspecialchars($f_date); ?>" class="sk-input" style="flex:1; min-width:140px;">
        <input type="date" name="to_date" value="<?php echo htmlspecialchars($t_date); ?>" class="sk-input" style="flex:1; min-width:140px;">
        <button type="submit" class="sk-btn sk-btn--accent"><i class="fas fa-search"></i> খুঁজুন</button>
    </form>

    <?php if(empty($groupedLogs)): ?>
        <div class="sk-card sk-empty"><i class="fas fa-folder-open"></i><p>কোনো লগ ডাটা পাওয়া যায়নি।</p></div>
    <?php endif; ?>

    <?php foreach ($groupedLogs as $user => $dates): ?>
    <div class="user-group">
        <div class="user-head" onclick="let c=this.nextElementSibling;c.style.display=c.style.display==='none'?'block':'none'">
            <span><i class="fas fa-user-circle"></i> @<?php echo htmlspecialchars($user); ?></span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div style="display:none;">
            <?php foreach ($dates as $date => $entries): ?>
            <div class="date-row">
                <span><i class="fas fa-calendar-alt" style="color:var(--sk-primary);"></i> <?php echo date('d-M-Y', strtotime($date)); ?> (<?php echo count($entries); ?> অ্যাকশন)</span>
                <i class="fas fa-trash-alt btn-del" onclick="confirmDelete('<?php echo addslashes($user); ?>', '<?php echo $date; ?>')" title="ডিলিট"></i>
            </div>
            <table class="audit-table">
                <thead><tr><th style="width:80px;">সময়</th><th style="width:120px;">অ্যাকশন</th><th>বিস্তারিত</th></tr></thead>
                <tbody>
                    <?php foreach ($entries as $row): ?>
                    <tr class="<?php echo ($row['action'] == 'Data Deleted') ? 'highlight-red' : ''; ?>">
                        <td style="color:var(--sk-muted);"><?php echo date('h:i A', strtotime($row['created_at'])); ?></td>
                        <td style="font-weight:700; color:var(--sk-ink);"><?php echo htmlspecialchars($row['action']); ?></td>
                        <td><?php echo htmlspecialchars($row['details']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

</main>

<script>
function confirmDelete(user, date) {
    Swal.fire({
        title:'নিশ্চিত তো?', text:"@"+user+" এর "+date+" তারিখের সব ডাটা মুছে যাবে।",
        input:'password', inputPlaceholder:'অ্যাডমিন পাসওয়ার্ড',
        showCancelButton:true, confirmButtonText:'হ্যাঁ, ডিলিট', cancelButtonText:'বাতিল', confirmButtonColor:'#dc3545',
        showLoaderOnConfirm:true,
        preConfirm: (pass) => {
            if (!pass) { Swal.showValidationMessage('পাসওয়ার্ড লিখুন!'); return false; }
            let fd = new FormData();
            fd.append('action','secure_delete'); fd.append('user',user); fd.append('date',date); fd.append('pass',pass);
            fd.append('token','<?php echo $_SESSION['csrf_token']; ?>');
            return fetch('', { method:'POST', body:fd }).then(r => r.json()).then(res => {
                if (res.s === 'ok') return res; throw new Error(res.m);
            }).catch(error => Swal.showValidationMessage(`ত্রুটি: ${error.message}`));
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then(result => {
        if (result.isConfirmed) Swal.fire('সফল!', result.value.m, 'success').then(() => location.reload());
    });
}
</script>
</body>
</html>
