<?php
declare(strict_types=1);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function logSystemError(Throwable $e): void {
    $logDir = __DIR__ . '/../Logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir.'/error_log.txt', "[".date('Y-m-d H:i:s')."] ".$e->getMessage().PHP_EOL, FILE_APPEND);
}

$lastActivity = $_SESSION['last_activity'] ?? null;
if ($lastActivity !== null && is_int($lastActivity) && (time() - $lastActivity > 1200)) {
    session_unset(); session_destroy(); header("Location: ../index.php"); exit;
}
$_SESSION['last_activity'] = time();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../index.php"); exit; }

$db_path = '../db_connect.php';
if (file_exists($db_path)) include $db_path; else die("System Error");

/** @var PDO $conn */
$role = $_SESSION['role'] ?? 'user';
date_default_timezone_set('Asia/Dhaka');

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = "i.pieces <= 0";
$params = []; $searchParam = '';
if ($search !== '') {
    $whereClause .= " AND (i.product_code LIKE ? OR i.name LIKE ? OR c.name LIKE ?)";
    $searchParam = "%{$search}%"; $params = [$searchParam, $searchParam, $searchParam];
}

$outOfStockItems = []; $outOfStockCount = 0; $totalPages = 1;
try {
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM inventory i LEFT JOIN categories c ON i.category_id = c.id WHERE $whereClause");
    $stmtCount->execute($params);
    $totalItems = (int)$stmtCount->fetchColumn();
    $totalPages = (int)max(1, ceil($totalItems / $limit));

    $query = "SELECT i.*, c.name as cat_name,
        (SELECT MAX(s.created_at) FROM inventory_sale_items isi JOIN inventory_sales s ON isi.sale_id = s.id WHERE isi.product_code = i.product_code) as last_sold_date
        FROM inventory i LEFT JOIN categories c ON i.category_id = c.id WHERE $whereClause ORDER BY last_sold_date DESC, i.id DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $i = 1;
    if ($search !== '') { $stmt->bindValue($i++, $searchParam, PDO::PARAM_STR); $stmt->bindValue($i++, $searchParam, PDO::PARAM_STR); $stmt->bindValue($i++, $searchParam, PDO::PARAM_STR); }
    $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $outOfStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $outOfStockCount = count($outOfStockItems);
} catch (Throwable $e) { logSystemError($e); }
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>স্টক শেষ — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme-toggle.js"></script>
</head>
<body>

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button class="sk-iconbtn" onclick="toggleSidebar()" aria-label="Menu"><i class="fas fa-bars"></i></button>
        <a href="inventory_dashboard.php" class="sk-iconbtn" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title"><span class="dot" style="background:var(--sk-danger); box-shadow:0 0 0 4px var(--sk-danger-soft);"></span> Out of Stock</div>
    <div class="sk-appbar__right">
        <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger" aria-label="Logout"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<div class="sk-overlay" id="myOverlay" onclick="toggleSidebar()"></div>
<aside class="sk-drawer" id="mySidebar">
    <div class="sk-drawer__head">
        <button class="sk-drawer__close" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        <img src="logo.png" onerror="this.style.display='none'" class="sk-drawer__logo" alt="logo">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">STOCK ZERO</div>
    </div>
    <div class="sk-drawer__section">Navigation</div>
    <div class="sk-drawer__grid">
        <a href="../dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-home"></i></div><span class="sk-drawer__label">হোম</span></a>
        <a href="inventory_dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-th-large"></i></div><span class="sk-drawer__label">Dashboard</span></a>
        <a href="inventory.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-plus"></i></div><span class="sk-drawer__label">Add Item</span></a>
        <a href="Invantory_Items.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-box-open"></i></div><span class="sk-drawer__label">Item List</span></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></div><span class="sk-drawer__label">POS</span></a>
        <a href="inventory_sales_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-receipt"></i></div><span class="sk-drawer__label">History</span></a>
        <a href="out_of_stock.php" class="sk-drawer__item active"><div class="sk-drawer__icon" style="background:var(--sk-danger);"><i class="fas fa-exclamation-triangle"></i></div><span class="sk-drawer__label">স্টক শূন্য</span></a>
        <?php if($role == 'admin'): ?>
        <a href="admin_inventory_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-cogs"></i></div><span class="sk-drawer__label">Admin</span></a>
        <?php endif; ?>
    </div>
</aside>

<main class="sk-container">

    <div class="sk-card sk-card--pad-lg" style="border-left:.25rem solid var(--sk-danger); margin-bottom:.875rem;">
        <div class="sk-row sk-row--between" style="align-items:flex-start;">
            <div>
                <h2 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--sk-danger); display:flex; align-items:center; gap:.5rem;">
                    <i class="fas fa-ban"></i> স্টক শূন্য পণ্যসমূহ
                </h2>
                <p style="margin:.375rem 0 0; font-size:.8rem; font-weight:500; color:var(--sk-muted);">
                    এই পণ্যগুলো বর্তমানে স্টকে নেই।
                </p>
            </div>
            <span class="sk-pill sk-pill--danger"><i class="fas fa-box"></i> <?php echo (int)$outOfStockCount; ?></span>
        </div>
    </div>

    <form method="GET" action="out_of_stock.php" class="sk-search" style="margin-bottom:.875rem;">
        <div class="sk-input-wrap sk-grow">
            <i class="fas fa-search"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="কোড বা নাম লিখে Enter চাপুন..." class="sk-input sk-input--icon">
        </div>
        <?php if($search !== ''): ?>
            <a href="out_of_stock.php" class="sk-btn sk-btn--ghost"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>

    <div class="sk-table-wrap">
        <table class="sk-table">
            <thead>
                <tr>
                    <th width="20%" style="text-align:center;">ছবি</th>
                    <th width="45%">পণ্যের বিবরণ</th>
                    <th width="35%">কবে শেষ?</th>
                </tr>
            </thead>
            <tbody>
                <?php if($outOfStockCount > 0): foreach($outOfStockItems as $item): ?>
                <tr>
                    <td style="text-align:center;">
                        <?php $img = !empty($item['image_path']) ? htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') : ''; ?>
                        <?php if ($img !== ''): ?>
                            <img src="<?php echo $img; ?>" style="width:48px; height:48px; object-fit:cover; border-radius:.5rem; border:1px solid var(--sk-line); filter:grayscale(1); opacity:.8;">
                        <?php else: ?>
                            <div style="width:48px; height:48px; border-radius:.5rem; background:var(--sk-surface-3); display:inline-flex; align-items:center; justify-content:center; color:var(--sk-muted);"><i class="fas fa-image"></i></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:800; font-size:.95rem; color:var(--sk-ink); text-decoration:line-through; text-decoration-color:var(--sk-danger);"><?php echo htmlspecialchars($item['product_code'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div style="font-weight:600; font-size:.8rem; color:var(--sk-muted); margin-top:.125rem;"><?php echo htmlspecialchars($item['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div style="margin-top:.375rem;"><span class="sk-pill sk-pill--danger">Out of Stock</span></div>
                    </td>
                    <td>
                        <?php if($item['last_sold_date']): ?>
                            <div class="sk-pill sk-pill--danger" style="margin-bottom:.25rem;">
                                <i class="far fa-calendar-times"></i> <?php echo date('d M Y', strtotime($item['last_sold_date'])); ?>
                            </div>
                            <div style="font-size:.7rem; font-weight:600; color:var(--sk-muted);">সময়: <?php echo date('h:i A', strtotime($item['last_sold_date'])); ?></div>
                        <?php else: ?>
                            <span class="sk-pill sk-pill--ghost"><i class="fas fa-info-circle"></i> তথ্য নেই</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="3">
                        <div class="sk-empty">
                            <?php if($search !== ''): ?>
                                <i class="fas fa-search"></i><p>কোনো পণ্য পাওয়া যায়নি!</p>
                            <?php else: ?>
                                <i class="fas fa-check-circle" style="color:var(--sk-success);"></i>
                                <p style="color:var(--sk-success);">আলহামদুলিল্লাহ! কোনো পণ্যের স্টক শূন্য নেই।</p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1):
        $prevPage = max(1, $page - 1);
        $nextPage = min($totalPages, $page + 1);
        $searchQuery = $search !== '' ? '&search=' . urlencode($search) : '';
    ?>
    <div class="sk-card" style="margin-top:.875rem; padding:.625rem;">
        <div class="sk-row sk-row--between">
            <a href="?page=<?php echo $prevPage . $searchQuery; ?>" class="sk-btn sk-btn--ghost sk-btn--sm <?php echo ($page <= 1) ? 'disabled' : ''; ?>" <?php echo ($page <= 1) ? 'style="opacity:.5; pointer-events:none;"' : ''; ?>><i class="fas fa-chevron-left"></i> আগে</a>
            <div class="sk-pager__info">Page <?php echo $page; ?> / <?php echo $totalPages; ?></div>
            <a href="?page=<?php echo $nextPage . $searchQuery; ?>" class="sk-btn sk-btn--accent sk-btn--sm <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>" <?php echo ($page >= $totalPages) ? 'style="opacity:.5; pointer-events:none;"' : ''; ?>>পরে <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    <?php endif; ?>

</main>

<script>
function toggleSidebar() { document.getElementById("mySidebar").classList.toggle("open"); document.getElementById("myOverlay").classList.toggle("active"); }
</script>
<style>body{padding-bottom:76px;}</style>
<?php include 'inventory_bottom_nav.php'; ?>
</body>
</html>
