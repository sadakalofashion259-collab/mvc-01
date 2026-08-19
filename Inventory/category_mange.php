<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    session_unset(); session_destroy();
    echo "<script>alert('Session Expired!'); window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { echo "<script>window.location.href='../index.php';</script>"; exit; }

include '../db_connect.php';
$role = $_SESSION['role'];
$isAdmin = ($role === 'admin');

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];

try { $conn->query("SELECT status FROM categories LIMIT 1"); }
catch (Exception $e) { try { $conn->query("ALTER TABLE categories ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'"); } catch (Exception $e2) {} }

if (isset($_POST['ajax_action'])) {
    ob_clean(); header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { echo json_encode(['status'=>'error','message'=>'সিকিউরিটি টোকেন মিসম্যাচ!']); exit; }
    if (!$isAdmin) { echo json_encode(['status'=>'error','message'=>'অনুমতি নেই!']); exit; }
    try {
        if ($_POST['ajax_action'] == 'toggle_status') {
            $stmt = $conn->prepare("UPDATE categories SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['id']]);
            echo json_encode(['status'=>'success','message'=>'স্ট্যাটাস আপডেট!']); exit;
        }
        if ($_POST['ajax_action'] == 'edit_category') {
            $catName = trim($_POST['name']);
            if (empty($catName)) { echo json_encode(['status'=>'error','message'=>'নাম খালি রাখা যাবে না!']); exit; }
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$catName, $_POST['id']]);
            echo json_encode(['status'=>'success','message'=>'নাম আপডেট!']); exit;
        }
    } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>'সার্ভার সমস্যা!']); exit; }
}

$totalRemaining = 0; $totalSold = 0; $totalAdded = 0; $todayAdded = 0; $todaySold = 0;
try {
    $totalRemaining = (int)($conn->query("SELECT SUM(pieces) FROM inventory")->fetchColumn() ?? 0);
    $totalSold = (int)($conn->query("SELECT SUM(pieces) FROM inventory_sale_items")->fetchColumn() ?? 0);
    $totalAdded = $totalRemaining + $totalSold;
    $stmtDateCol = $conn->query("SHOW COLUMNS FROM inventory LIKE 'date'");
    $invDateCol = $stmtDateCol->rowCount() > 0 ? 'date' : 'created_at';
    $todayAdded = (int)($conn->query("SELECT SUM(pieces) FROM inventory WHERE DATE($invDateCol) = CURDATE()")->fetchColumn() ?? 0);
    $stmtSoldDateCol = $conn->query("SHOW COLUMNS FROM inventory_sale_items LIKE 'sale_date'");
    $soldDateCol = $stmtSoldDateCol->rowCount() > 0 ? 'sale_date' : 'created_at';
    $todaySold = (int)($conn->query("SELECT SUM(pieces) FROM inventory_sale_items WHERE DATE($soldDateCol) = CURDATE()")->fetchColumn() ?? 0);
} catch (Exception $e) {}

$categoryList = []; $totalCategoriesCount = 0;
try {
    $sqlCat = "SELECT c.*, COALESCE(stock.remaining,0) AS total_remaining, COALESCE(sales.sold,0) AS total_sold, COALESCE(stock.remaining,0)+COALESCE(sales.sold,0) AS total_added FROM categories c LEFT JOIN (SELECT category_id, SUM(pieces) AS remaining FROM inventory GROUP BY category_id) stock ON stock.category_id = c.id LEFT JOIN (SELECT i.category_id, SUM(si.pieces) AS sold FROM inventory_sale_items si JOIN inventory i ON i.product_code = si.product_code GROUP BY i.category_id) sales ON sales.category_id = c.id";
    $categoryList = $conn->query($sqlCat)->fetchAll(PDO::FETCH_ASSOC);
    $totalCategoriesCount = count($categoryList);

    $uncatRemaining = (int)($conn->query("SELECT SUM(pieces) FROM inventory WHERE category_id = 0 OR category_id IS NULL")->fetchColumn() ?? 0);
    $uncatSold = (int)($conn->query("SELECT SUM(si.pieces) FROM inventory_sale_items si JOIN inventory i ON i.product_code = si.product_code WHERE i.category_id = 0 OR i.category_id IS NULL")->fetchColumn() ?? 0);
    $uncatAdded = $uncatRemaining + $uncatSold;
    if ($uncatAdded > 0 || $uncatRemaining > 0 || $uncatSold > 0) {
        $categoryList[] = ['id'=>0, 'name'=>'ক্যাটাগরি ছাড়া (Uncategorized)', 'status'=>'active', 'total_added'=>$uncatAdded, 'total_sold'=>$uncatSold, 'total_remaining'=>$uncatRemaining, 'is_uncategorized'=>true];
    }
    $remainingSort = array_column($categoryList, 'total_remaining');
    $addedSort = array_column($categoryList, 'total_added');
    array_multisort($remainingSort, SORT_DESC, $addedSort, SORT_DESC, $categoryList);
} catch (Exception $e) {}

$activeCategories = []; $inactiveCategories = [];
foreach ($categoryList as $cat) {
    if (($cat['status'] ?? 'active') == 'active') $activeCategories[] = $cat;
    else $inactiveCategories[] = $cat;
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ক্যাটাগরি ম্যানেজ — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme-toggle.js"></script>
    <style>
        .catgrid { display:grid; grid-template-columns:repeat(2,1fr); gap:.625rem; }
        @media(min-width:640px) { .catgrid { grid-template-columns:repeat(3,1fr); } }
        @media(min-width:960px) { .catgrid { grid-template-columns:repeat(4,1fr); } }
        .catcard { background:var(--sk-surface); border:1px solid var(--sk-line); border-radius:.75rem; padding:.875rem; box-shadow:var(--sk-shadow-sm); display:flex; flex-direction:column; gap:.5rem; transition:.15s; }
        .catcard:hover { transform:translateY(-2px); box-shadow:var(--sk-shadow); }
        .catcard__top { display:flex; justify-content:space-between; align-items:center; }
        .catcard__title { font-weight:800; color:var(--sk-ink); font-size:.85rem; line-height:1.25; }
        .catcard__stats { display:grid; grid-template-columns:repeat(3,1fr); gap:.25rem; text-align:center; }
        .catcard__stat { background:var(--sk-surface-2); border-radius:.375rem; padding:.375rem .25rem; }
        .catcard__statlbl { display:block; font-size:.6rem; font-weight:700; line-height:1; margin-bottom:.125rem; }
        .catcard__statval { font-weight:800; font-size:.85rem; line-height:1; }
        .catcard--inactive { opacity:.7; }
        .catcard--inactive:hover { opacity:1; }
        .readonly-badge { display:inline-flex; align-items:center; justify-content:center; gap:.25rem; background:var(--sk-surface-2); color:var(--sk-muted); font-size:.65rem; font-weight:700; padding:.375rem; border-radius:.375rem; border:1px solid var(--sk-line); width:100%; }
        #readerSearch { width:100%; height:120px; border-radius:.5rem; overflow:hidden; border:2px solid var(--sk-primary); background:#000; }
        #readerSearch video { object-fit:cover !important; width:100% !important; height:100% !important; }
    </style>
</head>
<body>

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button class="sk-iconbtn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <a href="inventory_dashboard.php" class="sk-iconbtn"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title"><span class="dot"></span> ক্যাটাগরি প্যানেল</div>
    <div class="sk-appbar__right">
        <?php if (!$isAdmin): ?>
        <span class="sk-pill sk-pill--ghost"><i class="fas fa-eye"></i> VIEW ONLY</span>
        <?php endif; ?>
        <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<div class="sk-overlay" id="myOverlay" onclick="toggleSidebar()"></div>
<aside class="sk-drawer" id="mySidebar">
    <div class="sk-drawer__head">
        <button class="sk-drawer__close" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        <img src="logo.png" class="sk-drawer__logo" onerror="this.style.display='none'">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">CATEGORY PANEL</div>
    </div>
    <div class="sk-drawer__section">Quick Links</div>
    <div class="sk-drawer__grid">
        <a href="../dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-home"></i></div><span class="sk-drawer__label">হোম</span></a>
        <a href="inventory_dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-th-large"></i></div><span class="sk-drawer__label">Dashboard</span></a>
        <a href="inventory.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-plus"></i></div><span class="sk-drawer__label">Add Item</span></a>
        <a href="Invantory_Items.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-box-open"></i></div><span class="sk-drawer__label">Item List</span></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></div><span class="sk-drawer__label">POS</span></a>
        <a href="category_mange.php" class="sk-drawer__item active"><div class="sk-drawer__icon"><i class="fas fa-folder-tree"></i></div><span class="sk-drawer__label">Category</span></a>
        <?php if ($isAdmin): ?>
        <a href="admin_inventory_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-cogs"></i></div><span class="sk-drawer__label">Admin</span></a>
        <a href="admin_category_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-tags"></i></div><span class="sk-drawer__label">Cat Ctrl</span></a>
        <?php endif; ?>
    </div>
</aside>

<main class="sk-container">

    <div class="sk-stats sk-stats--4" style="margin-bottom:.875rem;">
        <div class="sk-stat sk-stat--info">
            <div class="sk-stat__icon"><i class="fas fa-box"></i></div>
            <div class="sk-stat__lbl">সর্বমোট এড</div>
            <div class="sk-stat__val"><?php echo number_format($totalAdded); ?> <small>পিস</small></div>
            <span class="sk-pill sk-pill--info" style="margin-top:.375rem;"><i class="far fa-calendar-alt"></i> আজ: <?php echo number_format($todayAdded); ?></span>
        </div>
        <div class="sk-stat sk-stat--success">
            <div class="sk-stat__icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="sk-stat__lbl">সর্বমোট বিক্রি</div>
            <div class="sk-stat__val"><?php echo number_format($totalSold); ?> <small>পিস</small></div>
            <span class="sk-pill sk-pill--success" style="margin-top:.375rem;"><i class="far fa-calendar-check"></i> আজ: <?php echo number_format($todaySold); ?></span>
        </div>
        <div class="sk-stat sk-stat--warn">
            <div class="sk-stat__icon"><i class="fas fa-cubes"></i></div>
            <div class="sk-stat__lbl">স্টকে আছে</div>
            <div class="sk-stat__val"><?php echo number_format($totalRemaining); ?> <small>পিস</small></div>
        </div>
        <div class="sk-stat sk-stat--accent">
            <div class="sk-stat__icon"><i class="fas fa-tags"></i></div>
            <div class="sk-stat__lbl">মোট ক্যাটাগরি</div>
            <div class="sk-stat__val"><?php echo number_format($totalCategoriesCount); ?> <small>টি</small></div>
        </div>
    </div>

    <div class="sk-row sk-row--between sk-row--wrap" style="margin-bottom:.625rem; gap:.625rem;">
        <h2 style="font-size:.95rem; font-weight:700; color:var(--sk-ink); margin:0; display:flex; align-items:center; gap:.5rem;"><i class="fas fa-list-ul" style="color:var(--sk-primary);"></i> ক্যাটাগরি প্যানেল</h2>
        <div style="display:flex; gap:.5rem; flex:1; max-width:380px;">
            <div class="sk-input-wrap sk-grow"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="সার্চ বা স্ক্যান..." class="sk-input sk-input--icon"></div>
            <button onclick="toggleScanner()" id="scanBtnTop" class="sk-btn sk-btn--accent" style="padding:0 .75rem;"><i class="fas fa-qrcode"></i></button>
        </div>
    </div>

    <div id="inlineScannerArea" class="hidden" style="margin-bottom:.875rem;">
        <div id="readerSearch"></div>
        <div style="text-align:center; margin-top:.5rem;"><span class="sk-scanner__hint"><i class="fas fa-crosshairs"></i> বারকোড ধরুন</span></div>
    </div>

    <div class="catgrid" id="activeCardsContainer">
        <?php if (count($activeCategories) > 0): foreach ($activeCategories as $cat):
            $isUncat = isset($cat['is_uncategorized']) && $cat['is_uncategorized'];
        ?>
        <div class="catcard card-item-wrapper">
            <div class="catcard__top">
                <span class="sk-pill sk-pill--success"><i class="fas fa-check-circle"></i> ACTIVE</span>
                <span style="font-size:.7rem; color:var(--sk-muted-2); font-weight:700;">#<?php echo $cat['id']; ?></span>
            </div>
            <h3 class="catcard__title row-name"><?php echo htmlspecialchars($cat['name']); ?></h3>
            <div class="catcard__stats">
                <div class="catcard__stat"><span class="catcard__statlbl" style="color:var(--sk-info);">এড</span><span class="catcard__statval" style="color:var(--sk-info);"><?php echo (int)($cat['total_added'] ?? 0); ?></span></div>
                <div class="catcard__stat"><span class="catcard__statlbl" style="color:var(--sk-success);">বিক্রি</span><span class="catcard__statval" style="color:var(--sk-success);"><?php echo (int)($cat['total_sold'] ?? 0); ?></span></div>
                <div class="catcard__stat"><span class="catcard__statlbl" style="color:var(--sk-warn);">স্টক</span><span class="catcard__statval" style="color:var(--sk-warn);"><?php echo (int)($cat['total_remaining'] ?? 0); ?></span></div>
            </div>
            <?php if (!$isUncat): ?>
                <?php if ($isAdmin): ?>
                <div style="display:flex; gap:.375rem;">
                    <button onclick="toggleCategoryStatus(<?php echo $cat['id']; ?>, 'inactive')" class="sk-btn sk-btn--ghost sk-btn--sm" style="flex:1;"><i class="fas fa-power-off"></i> DISABLE</button>
                    <button onclick="openCategoryEditModal(<?php echo $cat['id']; ?>, '<?php echo addslashes(htmlspecialchars($cat['name'])); ?>')" class="sk-btn sk-btn--accent sk-btn--sm" style="flex:1;"><i class="far fa-edit"></i> এডিট</button>
                </div>
                <?php else: ?>
                <span class="readonly-badge"><i class="fas fa-eye"></i> শুধু দেখার অনুমতি</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="readonly-badge"><i class="fas fa-info-circle"></i> সিস্টেম ক্যাটাগরি</span>
            <?php endif; ?>
        </div>
        <?php endforeach; else: ?>
            <div style="grid-column:1/-1;" class="sk-card sk-empty"><i class="fas fa-box-open"></i><p>কোনো অ্যাক্টিভ ক্যাটাগরি নেই!</p></div>
        <?php endif; ?>
    </div>

    <div class="sk-row sk-row--between sk-row--wrap" style="margin-top:1rem; gap:.625rem;">
        <div class="sk-pager__info" id="pageInfo">Showing 0 entries</div>
        <div class="sk-pager" id="paginationControls"></div>
    </div>

    <div style="margin-top:2rem;">
        <button onclick="toggleInactiveSection()" class="sk-card" style="width:100%; padding:.875rem 1rem; border:none; text-align:left; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-weight:700; font-size:.9rem; color:var(--sk-ink-2); font-family:inherit;">
            <span style="display:flex; align-items:center; gap:.5rem;"><i class="fas fa-ban" style="color:var(--sk-danger);"></i> ইনঅ্যাক্টিভ ক্যাটাগরি <span class="sk-pill sk-pill--danger"><?php echo count($inactiveCategories); ?></span></span>
            <i class="fas fa-chevron-down" id="inactiveIcon" style="transition:transform .25s;"></i>
        </button>
        <div id="inactiveCardsContainer" class="hidden catgrid" style="margin-top:.75rem;">
            <?php if (count($inactiveCategories) > 0): foreach ($inactiveCategories as $cat): ?>
            <div class="catcard catcard--inactive inactive-card-wrapper">
                <div class="catcard__top">
                    <span class="sk-pill sk-pill--danger"><i class="fas fa-ban"></i> INACTIVE</span>
                    <span style="font-size:.7rem; color:var(--sk-muted-2); font-weight:700;">#<?php echo $cat['id']; ?></span>
                </div>
                <h3 class="catcard__title row-name"><?php echo htmlspecialchars($cat['name']); ?></h3>
                <div class="catcard__stats">
                    <div class="catcard__stat"><span class="catcard__statlbl" style="color:var(--sk-muted);">এড</span><span class="catcard__statval" style="color:var(--sk-muted);"><?php echo (int)($cat['total_added'] ?? 0); ?></span></div>
                    <div class="catcard__stat"><span class="catcard__statlbl" style="color:var(--sk-muted);">বিক্রি</span><span class="catcard__statval" style="color:var(--sk-muted);"><?php echo (int)($cat['total_sold'] ?? 0); ?></span></div>
                    <div class="catcard__stat"><span class="catcard__statlbl" style="color:var(--sk-muted);">স্টক</span><span class="catcard__statval" style="color:var(--sk-muted);"><?php echo (int)($cat['total_remaining'] ?? 0); ?></span></div>
                </div>
                <?php if ($isAdmin): ?>
                <div style="display:flex; gap:.375rem;">
                    <button onclick="toggleCategoryStatus(<?php echo $cat['id']; ?>, 'active')" class="sk-btn sk-btn--success sk-btn--sm" style="flex:1;"><i class="fas fa-check"></i> ENABLE</button>
                    <button onclick="openCategoryEditModal(<?php echo $cat['id']; ?>, '<?php echo addslashes(htmlspecialchars($cat['name'])); ?>')" class="sk-btn sk-btn--ghost sk-btn--sm" style="flex:1;"><i class="far fa-edit"></i> এডিট</button>
                </div>
                <?php else: ?>
                <span class="readonly-badge"><i class="fas fa-eye"></i> শুধু দেখার অনুমতি</span>
                <?php endif; ?>
            </div>
            <?php endforeach; else: ?>
                <div style="grid-column:1/-1; text-align:center; padding:1rem; color:var(--sk-muted); font-weight:600;">কোনো ইনঅ্যাক্টিভ ক্যাটাগরি নেই।</div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php if ($isAdmin): ?>
<div id="editCategoryModal" class="sk-modal">
    <div class="sk-modal__sheet">
        <div class="sk-modal__head">
            <div class="sk-modal__title"><i class="fas fa-edit"></i> ক্যাটাগরি আপডেট</div>
            <button onclick="closeCategoryEditModal()" class="sk-modal__close">&times;</button>
        </div>
        <form id="editCategoryForm">
            <input type="hidden" id="edit_category_id">
            <div class="sk-field">
                <label class="sk-label">ক্যাটাগরির নাম</label>
                <input type="text" id="edit_category_name" class="sk-input" required>
            </div>
            <button type="submit" id="saveCategoryBtn" class="sk-btn sk-btn--accent sk-btn--block sk-btn--lg"><i class="fas fa-save"></i> সেভ করুন</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function toggleSidebar() { document.getElementById("mySidebar").classList.toggle("open"); document.getElementById("myOverlay").classList.toggle("active"); }
function toggleInactiveSection() {
    let c = document.getElementById('inactiveCardsContainer');
    let i = document.getElementById('inactiveIcon');
    c.classList.toggle('hidden');
    i.style.transform = c.classList.contains('hidden') ? '' : 'rotate(180deg)';
}

const itemsPerPage = 12;
let currentPage = 1;
let allActiveCards = []; let filteredActiveCards = [];
let savedPage = sessionStorage.getItem('categoryCurrentPage');
if (savedPage) currentPage = parseInt(savedPage);

$(document).ready(function() {
    $('.card-item-wrapper').each(function() { allActiveCards.push($(this)); });
    filteredActiveCards = [...allActiveCards]; displayCards();
});

$('#searchInput').on('keyup', function() {
    let q = $(this).val().toLowerCase().trim();
    filteredActiveCards = allActiveCards.filter(c => c.find('.row-name').text().toLowerCase().indexOf(q) > -1);
    currentPage = 1; sessionStorage.setItem('categoryCurrentPage', currentPage); displayCards();
    let hasInactive = false;
    $('.inactive-card-wrapper').each(function() {
        let t = $(this).text().toLowerCase();
        if (t.indexOf(q) > -1) { $(this).show(); hasInactive = true; } else $(this).hide();
    });
    if (q.length > 0 && hasInactive && $('#inactiveCardsContainer').hasClass('hidden')) toggleInactiveSection();
});

function displayCards() {
    $('#activeCardsContainer').empty();
    let totalPages = Math.ceil(filteredActiveCards.length / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    else if (totalPages === 0) currentPage = 1;
    let start = (currentPage - 1) * itemsPerPage;
    let items = filteredActiveCards.slice(start, start + itemsPerPage);
    if (filteredActiveCards.length === 0) {
        $('#activeCardsContainer').html('<div style="grid-column:1/-1;" class="sk-empty"><i class="fas fa-search"></i><p>কোনো ডেটা পাওয়া যায়নি!</p></div>');
        $('#paginationControls').html(''); $('#pageInfo').text('Showing 0 entries');
    } else {
        items.forEach(c => $('#activeCardsContainer').append(c));
        updatePagination();
    }
}

function updatePagination() {
    let totalPages = Math.ceil(filteredActiveCards.length / itemsPerPage); let h = '';
    if (currentPage > 1) h += `<button onclick="goToPage(${currentPage-1})" class="sk-pager__btn"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) h += `<button onclick="goToPage(${i})" class="sk-pager__btn ${i===currentPage?'active':''}">${i}</button>`;
    if (currentPage < totalPages) h += `<button onclick="goToPage(${currentPage+1})" class="sk-pager__btn"><i class="fas fa-chevron-right"></i></button>`;
    $('#paginationControls').html(h);
    let s = ((currentPage-1) * itemsPerPage) + 1; let e = Math.min(currentPage * itemsPerPage, filteredActiveCards.length);
    $('#pageInfo').text(`মোট ${filteredActiveCards.length} টির মধ্যে ${s} থেকে ${e} পর্যন্ত`);
}
function goToPage(p) { currentPage = p; sessionStorage.setItem('categoryCurrentPage', currentPage); displayCards(); $('html, body').animate({ scrollTop:0 }, 200); }

let html5QrScan; let isScanRunning = false;
function toggleScanner() {
    let area = document.getElementById('inlineScannerArea');
    let btn = document.getElementById('scanBtnTop');
    if (area.classList.contains('hidden')) {
        area.classList.remove('hidden');
        btn.innerHTML = '<i class="fas fa-times"></i>';
        btn.classList.remove('sk-btn--accent'); btn.classList.add('sk-btn--danger');
        setTimeout(() => {
            if (!html5QrScan) html5QrScan = new Html5Qrcode("readerSearch");
            html5QrScan.start({ facingMode:"environment" }, { fps:15, qrbox:{ width:250, height:60 }, aspectRatio:3.0 },
                (text) => { if (isScanRunning) { $('#searchInput').val(text).trigger('keyup'); try{new Audio('https://www.soundjay.com/buttons/sounds/button-09.mp3').play().catch(()=>{});}catch(e){} stopScanner(); } }, ()=>{}
            ).then(() => { isScanRunning = true; }).catch(() => { alert("ক্যামেরা পাওয়া যায়নি!"); stopScanner(); });
        }, 100);
    } else stopScanner();
}
function stopScanner() {
    let area = document.getElementById('inlineScannerArea');
    let btn = document.getElementById('scanBtnTop');
    btn.innerHTML = '<i class="fas fa-qrcode"></i>';
    btn.classList.remove('sk-btn--danger'); btn.classList.add('sk-btn--accent');
    if (html5QrScan && isScanRunning) {
        html5QrScan.stop().then(() => { isScanRunning = false; area.classList.add('hidden'); }).catch(() => { isScanRunning = false; area.classList.add('hidden'); });
    } else area.classList.add('hidden');
}

<?php if ($isAdmin): ?>
function toggleCategoryStatus(id, newStatus) {
    let t = newStatus === 'inactive' ? 'ডিজেবল' : 'এনাবল';
    if (confirm(`${t} করতে নিশ্চিত?`)) {
        $.ajax({ url:'category_mange.php', type:'POST',
            data:{ ajax_action:'toggle_status', id:id, status:newStatus, csrf_token:'<?php echo $csrfToken; ?>' },
            dataType:'json',
            success:function(r) { r.status==='success' ? location.reload() : alert(r.message); }
        });
    }
}
function openCategoryEditModal(id, name) {
    $('#edit_category_id').val(id); $('#edit_category_name').val(name);
    $('#editCategoryModal').addClass('open');
}
function closeCategoryEditModal() { $('#editCategoryModal').removeClass('open'); }
$('#editCategoryForm').submit(function(e) {
    e.preventDefault();
    let btn = $('#saveCategoryBtn'); let orig = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin"></i> সেভ হচ্ছে...').prop('disabled', true);
    $.ajax({ url:'category_mange.php', type:'POST',
        data:{ ajax_action:'edit_category', id:$('#edit_category_id').val(), name:$('#edit_category_name').val(), csrf_token:'<?php echo $csrfToken; ?>' },
        dataType:'json',
        success:function(r) { r.status==='success' ? location.reload() : alert(r.message); },
        complete:function() { btn.html(orig).prop('disabled', false); }
    });
});
<?php endif; ?>
</script>

</body>
</html>
