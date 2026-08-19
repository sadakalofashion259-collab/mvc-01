<?php
declare(strict_types=1);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') { header("Location: ../index.php"); exit; }

$db_path = __DIR__ . '/../db_connect.php';
if (file_exists($db_path)) { require_once $db_path; }
/** @var PDO $conn */
date_default_timezone_set('Asia/Dhaka');

try {
    $stmt = $conn->query("SELECT h.*, u.username FROM product_edit_history h LEFT JOIN users u ON h.changed_by = u.id ORDER BY h.id DESC LIMIT 200");
    $historyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $historyData = []; $errorMsg = "লগ লোড করতে সমস্যা!"; }
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>প্রোডাক্ট এডিট হিস্ট্রি — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme-toggle.js"></script>
</head>
<body>

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button type="button" class="sk-iconbtn" onclick="skOpenDrawer()"><i class="fas fa-bars"></i></button>
        <a href="Invantory_Items.php" class="sk-iconbtn"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title"><span class="dot"></span> এডিট হিস্ট্রি</div>
    <div class="sk-appbar__right">
        <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<div class="sk-overlay" id="skOverlay" onclick="skCloseDrawer()"></div>
<aside class="sk-drawer" id="skDrawer">
    <div class="sk-drawer__head">
        <button class="sk-drawer__close" onclick="skCloseDrawer()"><i class="fas fa-times"></i></button>
        <img src="logo.png" class="sk-drawer__logo" onerror="this.style.display='none'">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">EDIT HISTORY</div>
    </div>
    <div class="sk-drawer__section">Main</div>
    <div class="sk-drawer__grid">
        <a href="inventory_dashboard.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-th-large"></i></span><span class="sk-drawer__label">Dashboard</span></a>
        <a href="Invantory_Items.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-box-open"></i></span><span class="sk-drawer__label">Items</span></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></span><span class="sk-drawer__label">POS</span></a>
        <a href="inventory_sales_history.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-receipt"></i></span><span class="sk-drawer__label">Sales</span></a>
        <a href="return_product.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-undo-alt"></i></span><span class="sk-drawer__label">Return</span></a>
        <a href="out_of_stock.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-exclamation-triangle"></i></span><span class="sk-drawer__label">Out Stock</span></a>
    </div>
    <div class="sk-drawer__section">Admin</div>
    <div class="sk-drawer__grid">
        <a href="admin_inventory_control.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-cogs"></i></span><span class="sk-drawer__label">Inv Ctrl</span></a>
        <a href="admin_category_control.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-tags"></i></span><span class="sk-drawer__label">Category</span></a>
        <a href="admin_return_history.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-history"></i></span><span class="sk-drawer__label">Returns</span></a>
        <a href="daily_activity.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-clipboard-check"></i></span><span class="sk-drawer__label">Activity</span></a>
        <a href="product_edit_history.php" class="sk-drawer__item active"><span class="sk-drawer__icon"><i class="fas fa-pen-to-square"></i></span><span class="sk-drawer__label">Edit Log</span></a>
        <a href="../logout.php" class="sk-drawer__item"><span class="sk-drawer__icon" style="background:var(--sk-danger);"><i class="fas fa-power-off"></i></span><span class="sk-drawer__label">Logout</span></a>
    </div>
</aside>

<main class="sk-container">

    <div class="sk-banner"><img src="banner.jpg" onerror="this.parentElement.style.display='none'"></div>

    <div class="sk-card sk-card--accent" style="margin-bottom:.75rem;">
        <div class="sk-row">
            <span class="sk-stat__icon" style="background:var(--sk-grad-primary);"><i class="fas fa-shield-alt"></i></span>
            <div class="sk-grow">
                <div style="font-weight:800; font-size:.95rem; color:var(--sk-ink);">সিকিউর লগ সিস্টেম</div>
                <div style="font-size:.7rem; font-weight:600; color:var(--sk-muted); margin-top:.125rem;">সর্বশেষ ২০০টি দাম/নাম পরিবর্তনের রেকর্ড।</div>
            </div>
            <span class="sk-pill sk-pill--ink"><i class="fas fa-database"></i> <?php echo count($historyData); ?></span>
        </div>
    </div>

    <div class="sk-section-title">
        <h2><i class="fas fa-history"></i> প্রোডাক্ট এডিট লগ</h2>
        <span class="sk-sub">LAST 200</span>
    </div>

    <?php if(isset($errorMsg)): ?>
        <div class="sk-card" style="border-color:var(--sk-danger); background:var(--sk-danger-soft);">
            <div class="sk-row sk-row--center" style="color:var(--sk-danger-ink); font-weight:700;"><i class="fas fa-circle-exclamation"></i> <?php echo $errorMsg; ?></div>
        </div>
    <?php else: ?>
        <div class="sk-table-wrap">
            <table class="sk-table">
                <thead>
                    <tr>
                        <th>তারিখ ও অ্যাডমিন</th>
                        <th>প্রোডাক্ট কোড</th>
                        <th>পরিবর্তনসমূহ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($historyData) > 0): ?>
                        <?php foreach($historyData as $row):
                            $date = date('d M Y, h:i A', strtotime($row['changed_at']));
                            $adminName = htmlspecialchars($row['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                            $pCode = htmlspecialchars($row['product_code'], ENT_QUOTES, 'UTF-8');
                            $changesArr = explode(' | ', $row['changes_details']);
                        ?>
                        <tr>
                            <td style="vertical-align:top;">
                                <div style="font-weight:700; font-size:.75rem; color:var(--sk-ink-2);"><i class="far fa-clock" style="color:var(--sk-muted);"></i> <?php echo $date; ?></div>
                                <div style="margin-top:.25rem;"><span class="sk-pill sk-pill--info"><i class="fas fa-user-shield"></i> <?php echo $adminName; ?></span></div>
                            </td>
                            <td style="vertical-align:top;"><span class="sk-pill sk-pill--accent sk-mono"><?php echo $pCode; ?></span></td>
                            <td style="vertical-align:top;">
                                <div style="display:flex; flex-direction:column; gap:.25rem; align-items:flex-start;">
                                    <?php foreach($changesArr as $change): ?>
                                        <span class="sk-pill sk-pill--info"><?php echo htmlspecialchars($change, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3"><div class="sk-empty"><i class="fas fa-inbox"></i><p>কোনো এডিট হিস্ট্রি নেই</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<script>
function skOpenDrawer(){ document.getElementById('skDrawer').classList.add('open'); document.getElementById('skOverlay').classList.add('active'); }
function skCloseDrawer(){ document.getElementById('skDrawer').classList.remove('open'); document.getElementById('skOverlay').classList.remove('active'); }
</script>
</body>
</html>
