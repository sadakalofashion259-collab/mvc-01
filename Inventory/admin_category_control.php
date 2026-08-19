<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    session_unset(); session_destroy();
    echo "<script>alert('Session Expired!'); window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    echo "<script>window.location.href='../index.php';</script>"; exit;
}
include '../db_connect.php';
$role = $_SESSION['role'];

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];

try { $conn->query("SELECT status FROM categories LIMIT 1"); }
catch (Exception $e) { try { $conn->query("ALTER TABLE categories ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'"); } catch (Exception $e2) {} }

if (isset($_POST['ajax_action'])) {
    ob_clean(); header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['status'=>'error','message'=>'সিকিউরিটি টোকেন মিসম্যাচ!']); exit;
    }
    if ($role !== 'admin') { echo json_encode(['status'=>'error','message'=>'অনুমতি নেই!']); exit; }
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
            echo json_encode(['status'=>'success','message'=>'নাম আপডেট হয়েছে!']); exit;
        }
    } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit; }
}

$sqlCat = "SELECT c.*,
    COALESCE(stock.remaining, 0) AS total_remaining,
    COALESCE(sales.sold, 0) AS total_sold,
    COALESCE(stock.remaining, 0) + COALESCE(sales.sold, 0) AS total_added
  FROM categories c
  LEFT JOIN (SELECT category_id, SUM(pieces) AS remaining FROM inventory GROUP BY category_id) stock ON stock.category_id = c.id
  LEFT JOIN (SELECT i.category_id, SUM(si.pieces) AS sold FROM inventory_sale_items si JOIN inventory i ON i.product_code = si.product_code GROUP BY i.category_id) sales ON sales.category_id = c.id
  ORDER BY c.status ASC, c.id DESC";
try { $categoryList = $conn->query($sqlCat)->fetchAll(PDO::FETCH_ASSOC); }
catch (Exception $eCat) { $categoryList = $conn->query("SELECT *, 0 AS total_added, 0 AS total_sold, 0 AS total_remaining FROM categories ORDER BY status ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC); }

$grandAdded = 0; $grandSold = 0; $grandRemaining = 0;
foreach ($categoryList as $catRow) {
    $grandAdded += (int)($catRow['total_added'] ?? 0);
    $grandSold += (int)($catRow['total_sold'] ?? 0);
    $grandRemaining += (int)($catRow['total_remaining'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ক্যাটাগরি কন্ট্রোল — SADA KALO</title>
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
</head>
<body>

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button type="button" class="sk-iconbtn" onclick="toggleSidebar()" aria-label="Menu"><i class="fas fa-bars"></i></button>
        <a href="inventory_dashboard.php" class="sk-iconbtn"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title"><span class="dot"></span> ক্যাটাগরি কন্ট্রোল</div>
    <div class="sk-appbar__right">
        <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<div class="sk-overlay" id="myOverlay" onclick="toggleSidebar()"></div>
<aside class="sk-drawer" id="mySidebar">
    <div class="sk-drawer__head">
        <button class="sk-drawer__close" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        <img src="logo.png" class="sk-drawer__logo" onerror="this.style.display='none'">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">CATEGORY ADMIN</div>
    </div>
    <div class="sk-drawer__section">Quick Menu</div>
    <nav class="sk-drawer__grid">
        <a href="../dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-home"></i></div><span class="sk-drawer__label">হোম</span></a>
        <a href="inventory_dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-th-large"></i></div><span class="sk-drawer__label">Dashboard</span></a>
        <a href="inventory.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-plus"></i></div><span class="sk-drawer__label">Add Item</span></a>
        <a href="Invantory_Items.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-box-open"></i></div><span class="sk-drawer__label">Item List</span></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></div><span class="sk-drawer__label">POS</span></a>
        <a href="admin_inventory_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-cogs"></i></div><span class="sk-drawer__label">Admin</span></a>
        <a href="admin_category_control.php" class="sk-drawer__item active"><div class="sk-drawer__icon"><i class="fas fa-tags"></i></div><span class="sk-drawer__label">Category</span></a>
        <a href="unsold_inventory.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-archive"></i></div><span class="sk-drawer__label">অবিক্রীত</span></a>
    </nav>
</aside>

<main class="sk-container">

    <div class="sk-banner"><img src="banner.jpg" alt="Banner" onerror="this.parentElement.style.display='none'"></div>

    <div class="sk-stats sk-stats--3" style="margin-bottom:.875rem;">
        <div class="sk-stat sk-stat--info">
            <span class="sk-stat__icon"><i class="fas fa-plus-circle"></i></span>
            <div class="sk-stat__lbl">মোট কেনা</div>
            <div class="sk-stat__val"><?php echo number_format($grandAdded); ?></div>
        </div>
        <div class="sk-stat sk-stat--success">
            <span class="sk-stat__icon"><i class="fas fa-shopping-cart"></i></span>
            <div class="sk-stat__lbl">মোট বিক্রি</div>
            <div class="sk-stat__val"><?php echo number_format($grandSold); ?></div>
        </div>
        <div class="sk-stat sk-stat--warn">
            <span class="sk-stat__icon"><i class="fas fa-boxes"></i></span>
            <div class="sk-stat__lbl">অবশিষ্ট</div>
            <div class="sk-stat__val"><?php echo number_format($grandRemaining); ?></div>
        </div>
    </div>

    <div class="sk-card sk-card--pad-lg" style="margin-bottom:1.5rem;">

        <div class="sk-section-title">
            <h2><i class="fas fa-tags"></i> সকল ক্যাটাগরি লিস্ট</h2>
            <span class="sk-sub"><?php echo count($categoryList); ?> Items</span>
        </div>

        <div class="sk-search" style="margin-bottom:.875rem;">
            <div class="sk-input-wrap sk-grow">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="সার্চ বা স্ক্যান করুন..." class="sk-input sk-input--icon">
            </div>
            <button type="button" onclick="toggleScanner()" id="scanBtnTop" class="sk-btn sk-btn--ink" style="padding:0 .875rem;"><i class="fas fa-qrcode"></i></button>
        </div>

        <div id="inlineScannerArea" class="hidden" style="margin-bottom:.875rem;">
            <div id="readerSearch" class="sk-scanner"></div>
            <div style="text-align:center; margin-top:.5rem;"><span class="sk-scanner__hint"><i class="fas fa-crosshairs"></i> স্ক্যান করতে বারকোড ধরুন</span></div>
        </div>

        <div class="sk-table-wrap" style="overflow-x:auto;">
            <table class="sk-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ক্যাটাগরি নাম</th>
                        <th style="text-align:center;">মোট এড</th>
                        <th style="text-align:center;">মোট বিক্রি</th>
                        <th style="text-align:center;">অবশিষ্ট</th>
                        <th style="text-align:center;">স্ট্যাটাস</th>
                        <th style="text-align:center;">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody id="categoryTableBody">
                    <?php if(count($categoryList) > 0): ?>
                        <?php foreach($categoryList as $cat):
                            $statusValue = $cat['status'] ?? 'active';
                            $statusPill = $statusValue == 'active' ? 'sk-pill--success' : 'sk-pill--ghost';
                            $statusText = $statusValue == 'active' ? 'Active' : 'Inactive';
                            $toggleStatus = $statusValue == 'active' ? 'inactive' : 'active';
                            $opacity = $statusValue == 'inactive' ? 'opacity:.6;' : '';
                        ?>
                        <tr class="table-row-item" style="<?php echo $opacity; ?>">
                            <td><span class="sk-mono" style="color:var(--sk-primary); font-weight:800;">#<?php echo $cat['id']; ?></span></td>
                            <td class="row-name" style="font-weight:700; color:var(--sk-ink);"><?php echo htmlspecialchars($cat['name']); ?></td>
                            <td style="text-align:center;"><span class="sk-pill sk-pill--info"><?php echo (int)($cat['total_added'] ?? 0); ?></span></td>
                            <td style="text-align:center;"><span class="sk-pill sk-pill--success"><?php echo (int)($cat['total_sold'] ?? 0); ?></span></td>
                            <td style="text-align:center;"><span class="sk-pill sk-pill--warn"><?php echo (int)($cat['total_remaining'] ?? 0); ?></span></td>
                            <td style="text-align:center;">
                                <button onclick="toggleCategoryStatus(<?php echo $cat['id']; ?>, '<?php echo $toggleStatus; ?>')" class="sk-pill <?php echo $statusPill; ?>" style="border:0; cursor:pointer;">
                                    <?php echo $statusText; ?>
                                </button>
                            </td>
                            <td style="text-align:center;">
                                <button onclick="openCategoryEditModal(<?php echo $cat['id']; ?>, '<?php echo addslashes(htmlspecialchars($cat['name'])); ?>')" class="sk-btn sk-btn--sm sk-btn--accent">
                                    <i class="fas fa-edit"></i> এডিট
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="noDataRow"><td colspan="7"><div class="sk-empty"><i class="fas fa-inbox"></i><p>কোনো ক্যাটাগরি তৈরি করা হয়নি!</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="sk-row sk-row--between sk-row--wrap" style="margin-top:1rem; gap:.625rem;">
            <div class="sk-pager__info" id="pageInfo">Showing 0 entries</div>
            <div class="sk-pager" id="paginationControls"></div>
        </div>
    </div>
</main>

<div id="editCategoryModal" class="sk-modal">
    <div class="sk-modal__sheet">
        <div class="sk-modal__head">
            <div class="sk-modal__title"><i class="fas fa-edit"></i> ক্যাটাগরি নাম পরিবর্তন</div>
            <button type="button" onclick="closeCategoryEditModal()" class="sk-modal__close">&times;</button>
        </div>
        <form id="editCategoryForm">
            <input type="hidden" id="edit_category_id">
            <div class="sk-field">
                <label class="sk-label">ক্যাটাগরির নতুন নাম</label>
                <input type="text" id="edit_category_name" class="sk-input" required>
            </div>
            <button type="submit" id="saveCategoryBtn" class="sk-btn sk-btn--accent sk-btn--block sk-btn--lg"><i class="fas fa-save"></i> সেভ করুন</button>
        </form>
    </div>
</div>

<script>
function toggleSidebar() { document.getElementById("mySidebar").classList.toggle("open"); document.getElementById("myOverlay").classList.toggle("active"); }

const rowsPerPage = 10;
let currentPage = 1; let allRows = []; let filteredRows = [];
let savedPage = sessionStorage.getItem('categoryCurrentPage');
if (savedPage) currentPage = parseInt(savedPage);

$(document).ready(function() {
    $('.table-row-item').each(function() { allRows.push($(this)); });
    filteredRows = [...allRows]; displayTable();
});

$('#searchInput').on('keyup', function() {
    let q = $(this).val().toLowerCase().trim();
    filteredRows = allRows.filter(r => r.text().toLowerCase().indexOf(q) > -1);
    currentPage = 1; sessionStorage.setItem('categoryCurrentPage', currentPage); displayTable();
});

function displayTable() {
    $('#categoryTableBody').empty();
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    else if (totalPages === 0) currentPage = 1;
    let start = (currentPage - 1) * rowsPerPage;
    let items = filteredRows.slice(start, start + rowsPerPage);
    if (filteredRows.length === 0) {
        $('#categoryTableBody').append('<tr><td colspan="7"><div class="sk-empty"><i class="fas fa-search"></i><p>কোনো ডাটা পাওয়া যায়নি!</p></div></td></tr>');
        $('#paginationControls').html(''); $('#pageInfo').text('Showing 0 entries');
    } else {
        items.forEach(r => $('#categoryTableBody').append(r));
        updatePagination();
    }
}

function updatePagination() {
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage); let html = '';
    if(currentPage > 1) html += `<button onclick="goToPage(${currentPage-1})" class="sk-pager__btn"><i class="fas fa-chevron-left"></i></button>`;
    for(let i=1; i<=totalPages; i++) html += `<button onclick="goToPage(${i})" class="sk-pager__btn ${i===currentPage?'active':''}">${i}</button>`;
    if(currentPage < totalPages) html += `<button onclick="goToPage(${currentPage+1})" class="sk-pager__btn"><i class="fas fa-chevron-right"></i></button>`;
    $('#paginationControls').html(html);
    let s = ((currentPage-1) * rowsPerPage) + 1; let e = Math.min(currentPage * rowsPerPage, filteredRows.length);
    $('#pageInfo').text(`মোট ${filteredRows.length} টির মধ্যে ${s} থেকে ${e} পর্যন্ত`);
}
function goToPage(p) { currentPage = p; sessionStorage.setItem('categoryCurrentPage', currentPage); displayTable(); }

let html5QrScan; let isScanRunning = false;
function toggleScanner() {
    let area = document.getElementById('inlineScannerArea');
    let btn = document.getElementById('scanBtnTop');
    if(area.classList.contains('hidden')) {
        area.classList.remove('hidden');
        btn.innerHTML = '<i class="fas fa-times"></i>';
        btn.classList.remove('sk-btn--ink'); btn.classList.add('sk-btn--danger');
        setTimeout(() => {
            if (!html5QrScan) html5QrScan = new Html5Qrcode("readerSearch");
            html5QrScan.start({ facingMode: "environment" }, { fps:15, qrbox:function(vw,vh){return{width:Math.floor(Math.min(vw*0.92,420)),height:160};}, aspectRatio:1.7777 },
                (text) => { if(isScanRunning) { $('#searchInput').val(text).trigger('keyup'); try{new Audio('https://www.soundjay.com/buttons/sounds/button-09.mp3').play().catch(()=>{});}catch(e){} stopScanner(); } }, ()=>{}
            ).then(() => { isScanRunning = true; }).catch(() => { alert('ক্যামেরা চালু করা যায়নি!'); stopScanner(); });
        }, 120);
    } else stopScanner();
}
function stopScanner() {
    let area = document.getElementById('inlineScannerArea');
    let btn = document.getElementById('scanBtnTop');
    btn.innerHTML = '<i class="fas fa-qrcode"></i>';
    btn.classList.remove('sk-btn--danger'); btn.classList.add('sk-btn--ink');
    if(html5QrScan && isScanRunning) {
        html5QrScan.stop().then(() => { isScanRunning = false; area.classList.add('hidden'); }).catch(() => { isScanRunning = false; area.classList.add('hidden'); });
    } else area.classList.add('hidden');
}

function toggleCategoryStatus(id, newStatus) {
    if(confirm('স্ট্যাটাস পরিবর্তন করতে নিশ্চিত?')) {
        $.ajax({
            url:'admin_category_control.php', type:'POST',
            data:{ ajax_action:'toggle_status', id:id, status:newStatus, csrf_token:'<?php echo $csrfToken; ?>' },
            dataType:'json',
            success:function(r) { if(r.status==='success') location.reload(); else alert(r.message); }
        });
    }
}
function openCategoryEditModal(id, name) { $('#edit_category_id').val(id); $('#edit_category_name').val(name); $('#editCategoryModal').addClass('open'); }
function closeCategoryEditModal() { $('#editCategoryModal').removeClass('open'); }

$('#editCategoryForm').submit(function(e) {
    e.preventDefault();
    let btn = $('#saveCategoryBtn'); let orig = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin"></i> সেভ হচ্ছে...').prop('disabled', true);
    $.ajax({
        url:'admin_category_control.php', type:'POST',
        data:{ ajax_action:'edit_category', id: $('#edit_category_id').val(), name: $('#edit_category_name').val(), csrf_token:'<?php echo $csrfToken; ?>' },
        dataType:'json',
        success:function(r) { if(r.status==='success') { alert(r.message); location.reload(); } else alert(r.message); },
        complete:function() { btn.html(orig).prop('disabled', false); }
    });
});
</script>
<style>body{padding-bottom:76px;}</style>
<?php include 'inventory_bottom_nav.php'; ?>
</body>
</html>
