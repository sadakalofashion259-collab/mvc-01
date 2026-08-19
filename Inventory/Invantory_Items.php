<?php
declare(strict_types=1);

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$timeout_duration = 1200;
$last_activity = $_SESSION['LAST_ACTIVITY'] ?? null;
if ($last_activity !== null && is_int($last_activity) && (time() - $last_activity) > $timeout_duration) {
    session_unset(); session_destroy();
    header("Location: ../index.php"); exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../index.php"); exit; }

if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

$db_path = __DIR__ . '/../db_connect.php';
if (file_exists($db_path)) { require_once $db_path; }

/** @var PDO $conn */
$rawRole = isset($_SESSION['role']) && is_string($_SESSION['role']) ? $_SESSION['role'] : 'user';
$userRole = strtolower(trim($rawRole));
$isAdmin = ($userRole === 'admin');
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

require_once __DIR__ . '/Helpers/AuditInit.php';
AuditInit::boot($conn);

$ajax_action = isset($_POST['ajax_action']) && is_string($_POST['ajax_action']) ? $_POST['ajax_action'] : '';

// ─── আপডেট লোকেশন (অ্যাডমিন) ──────────────────────────────────────────
if ($ajax_action === 'update_location') {
    ob_clean(); header('Content-Type: application/json');
    $post_csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($post_csrf === '' || !hash_equals($csrfToken, $post_csrf)) {
        echo json_encode(['status'=>'error','message'=>'Security token mismatch!']); exit;
    }
    if (!$isAdmin) { echo json_encode(['status'=>'error','message'=>'অ্যাক্সেস ডিনাইড!']); exit; }

    $pCode = isset($_POST['product_code']) && is_string($_POST['product_code']) ? trim($_POST['product_code']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';

    if ($pCode === '' || !in_array($location, ['shop', 'godown'], true)) {
        echo json_encode(['status'=>'error','message'=>'সঠিক তথ্য দিন!']); exit;
    }

    try {
        // আগের লোকেশন বের করি (অডিটের জন্য)
        $stmtOld = $conn->prepare("SELECT item_location FROM inventory WHERE product_code = ? LIMIT 1");
        $stmtOld->execute([$pCode]);
        $oldLocation = $stmtOld->fetchColumn();

        $stmt = $conn->prepare("UPDATE inventory SET item_location = ? WHERE product_code = ?");
        $stmt->execute([$location, $pCode]);

        // অডিট লগ
        if (class_exists('AuditLogger')) {
            AuditLogger::update(
                'inventory',
                $pCode,
                ['item_location' => $oldLocation],
                ['item_location' => $location],
                "লোকেশন পরিবর্তন: {$oldLocation} → {$location} ({$pCode})"
            );
        }

        echo json_encode(['status'=>'success', 'message'=>'লোকেশন আপডেট হয়েছে!']);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error', 'message'=>'ডাটাবেস ত্রুটি!']);
    }
    exit;
}

// ─── পূর্ণ পণ্য আপডেট (অ্যাডমিন) ──────────────────────────────────────
if ($ajax_action === 'update_full_product') {
    ob_clean(); header('Content-Type: application/json');
    $post_csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($post_csrf === '' || !hash_equals($csrfToken, $post_csrf)) {
        echo json_encode(['status'=>'error','message'=>'Security token mismatch!']); exit;
    }
    if (!$isAdmin) { echo json_encode(['status'=>'error','message'=>'অ্যাক্সেস ডিনাইড!']); exit; }

    $pCode = isset($_POST['product_code']) && is_string($_POST['product_code']) ? trim($_POST['product_code']) : '';
    $newName = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $newBuy = isset($_POST['buy_price']) && is_numeric($_POST['buy_price']) ? (float)$_POST['buy_price'] : -1.0;
    $newCost = isset($_POST['cost']) && is_numeric($_POST['cost']) ? (float)$_POST['cost'] : -1.0;
    $newCashSell = isset($_POST['cash_sell']) && is_numeric($_POST['cash_sell']) ? (float)$_POST['cash_sell'] : -1.0;

    if ($pCode === '' || $newName === '' || $newBuy < 0.0 || $newCost < 0.0 || $newCashSell < 0.0) {
        echo json_encode(['status'=>'error','message'=>'সঠিক তথ্য দিন!']); exit;
    }

    try {
        $conn->beginTransaction();
        $stmtOld = $conn->prepare("SELECT name, buy_price, cost, cash_sell FROM inventory WHERE product_code = ? FOR UPDATE");
        $stmtOld->execute([$pCode]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
        if (!$oldData) throw new Exception("পণ্যটি পাওয়া যায়নি!");

        $changes = [];
        if ($oldData['name'] !== $newName) $changes[] = "নাম: [{$oldData['name']}] ➔ [{$newName}]";
        if ((float)$oldData['buy_price'] !== $newBuy) $changes[] = "ক্রয়: ৳" . (float)$oldData['buy_price'] . " ➔ ৳" . $newBuy;
        if ((float)$oldData['cost'] !== $newCost) $changes[] = "খরচ: ৳" . (float)$oldData['cost'] . " ➔ ৳" . $newCost;
        if ((float)$oldData['cash_sell'] !== $newCashSell) $changes[] = "বিক্রি: ৳" . (float)$oldData['cash_sell'] . " ➔ ৳" . $newCashSell;

        if (!empty($changes)) {
            $insertLog = $conn->prepare("INSERT INTO product_edit_history (product_code, changes_details, changed_by) VALUES (?, ?, ?)");
            $insertLog->execute([$pCode, implode(' | ', $changes), $userId]);
        }

        $stmtUpdate = $conn->prepare("UPDATE inventory SET name = ?, buy_price = ?, cost = ?, cash_sell = ? WHERE product_code = ?");
        $stmtUpdate->execute([$newName, $newBuy, $newCost, $newCashSell, $pCode]);
        $conn->commit();

        // অডিট লগ
        if (class_exists('AuditLogger')) {
            $oldPayload = [
                'product_code' => $pCode,
                'name' => $oldData['name'],
                'buy_price' => (float)$oldData['buy_price'],
                'cost' => (float)$oldData['cost'],
                'cash_sell' => (float)$oldData['cash_sell'],
            ];
            $newPayload = [
                'product_code' => $pCode,
                'name' => $newName,
                'buy_price' => $newBuy,
                'cost' => $newCost,
                'cash_sell' => $newCashSell,
            ];
            AuditLogger::update('inventory', $pCode, $oldPayload, $newPayload, "পণ্য এডিট — {$pCode}");
        }

        echo json_encode(['status'=>'success','message'=>'পণ্যটি সফলভাবে আপডেট হয়েছে!']); exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $logDir = __DIR__ . '/../Logs'; @mkdir($logDir, 0755, true);
        @file_put_contents($logDir . '/error_log.txt', "[" . date('Y-m-d H:i:s') . "] Edit Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status'=>'error','message'=>'ডাটাবেস আপডেটে সমস্যা।']); exit;
    }
}

// ─── ক্যাটাগরি লোড ──────────────────────────────────────────────────────
if ($ajax_action === 'load_categories') {
    ob_clean(); header('Content-Type: application/json');
    $catStmt = $conn->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");
    $categories = $catStmt instanceof PDOStatement ? $catStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $catOptions = '<option value="all">সব ক্যাটাগরি</option>';
    foreach ($categories as $cat) {
        $cId = isset($cat['id']) ? (int)$cat['id'] : 0;
        $cName = isset($cat['name']) ? htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') : '';
        $catOptions .= '<option value="'.$cId.'">'.$cName.'</option>';
    }
    $catOptions .= '<option value="none">ক্যাটাগরি নেই</option>';
    echo json_encode(['html' => $catOptions]); exit;
}

// ─── কার্ড-স্টাইল লিস্ট (লোকেশন + মার্ক সহ) ──────────────────────────
if ($ajax_action === 'load_items_cards') {
    ob_clean(); header('Content-Type: application/json');

    // ── ফিল্টার ইনপুট ──
    $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
    $perPage = 12;
    $offset = ($page - 1) * $perPage;
    $searchValue = isset($_POST['search']) ? trim((string)$_POST['search']) : '';
    $catFilter = isset($_POST['category_filter']) ? (string)$_POST['category_filter'] : 'all';
    $stockFilter = isset($_POST['stock_filter']) ? (string)$_POST['stock_filter'] : 'all';
    $locationFilter = isset($_POST['location_filter']) ? (string)$_POST['location_filter'] : 'all'; // ← নতুন

    // ── WHERE Clause ──
    $whereParts = ["i.pieces > 0"];
    $params = [];

    if ($searchValue !== '') {
        $whereParts[] = "(i.product_code LIKE ? OR i.name LIKE ? OR c.name LIKE ?)";
        $w = "%{$searchValue}%";
        array_push($params, $w, $w, $w);
    }

    if ($catFilter !== 'all' && $catFilter !== '') {
        if ($catFilter === 'none') {
            $whereParts[] = "(i.category_id IS NULL OR i.category_id = 0)";
        } else {
            $whereParts[] = "i.category_id = ?";
            $params[] = (int)$catFilter;
        }
    }

    if ($stockFilter === 'low') $whereParts[] = "i.pieces < 10";
    elseif ($stockFilter === 'high') $whereParts[] = "i.pieces >= 10";

    // ─── লোকেশন ফিল্টার (নতুন) ───
    if ($locationFilter !== 'all' && in_array($locationFilter, ['shop', 'godown'], true)) {
        $whereParts[] = "i.item_location = ?";
        $params[] = $locationFilter;
    }

    $whereSql = implode(' AND ', $whereParts);

    try {
        // ── কাউন্ট ──
        $stmtCount = $conn->prepare("SELECT COUNT(i.id) FROM inventory i LEFT JOIN categories c ON i.category_id = c.id WHERE {$whereSql}");
        $stmtCount->execute($params);
        $filtered = (int)$stmtCount->fetchColumn();

        // ── মোট পিস ──
        $stmtSum = $conn->prepare("SELECT COALESCE(SUM(i.pieces),0) FROM inventory i LEFT JOIN categories c ON i.category_id = c.id WHERE {$whereSql}");
        $stmtSum->execute($params);
        $totalPieces = (int)$stmtSum->fetchColumn();

        // ── পেজিনেশন ──
        $totalPages = max(1, (int)ceil($filtered / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        // ── অর্ডার ──
        $orderSql = "i.id DESC";
        if ($searchValue !== '') {
            $orderSql = "CASE
                WHEN i.product_code = " . $conn->quote($searchValue) . " THEN 0
                WHEN i.product_code LIKE " . $conn->quote($searchValue . '%') . " THEN 1
                WHEN i.product_code LIKE " . $conn->quote('%' . $searchValue . '%') . " THEN 2
                ELSE 3 END ASC, i.id DESC";
        }

        // ── কোয়েরি (item_location, mark_color, mark_note সহ) ──
        $q = "SELECT i.*, c.name as cat_name, u.username as entry_by,
                     i.mark_color, i.mark_note, i.item_location
              FROM inventory i
              LEFT JOIN categories c ON i.category_id = c.id
              LEFT JOIN users u ON i.added_by = u.id
              WHERE {$whereSql}
              ORDER BY {$orderSql}
              LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $conn->prepare($q);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── HTML জেনারেট ──
        $html = '';
        if (count($items) === 0) {
            $html = '<div class="sk-empty"><i class="fas fa-box-open"></i><p>কোনো পণ্য পাওয়া যায়নি!</p></div>';
        } else {
            foreach ($items as $item) {
                $pCode = htmlspecialchars((string)($item['product_code'] ?? ''), ENT_QUOTES, 'UTF-8');
                $jsCode = addslashes((string)($item['product_code'] ?? ''));
                $pName = htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $catName = htmlspecialchars((string)($item['cat_name'] ?? 'ক্যাটাগরি নেই'), ENT_QUOTES, 'UTF-8');
                $entryBy = htmlspecialchars((string)($item['entry_by'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');
                $img = !empty($item['image_path']) ? htmlspecialchars((string)$item['image_path'], ENT_QUOTES, 'UTF-8') : '';
                $buyP = (float)($item['buy_price'] ?? 0);
                $costP = (float)($item['cost'] ?? 0);
                $cashP = (float)($item['cash_sell'] ?? 0);
                $minP = $buyP + $costP;
                $stock = (int)($item['pieces'] ?? 0);
                $stockCls = $stock < 10 ? 'pill-warn' : 'pill-ok';
                $displayDate = !empty($item['created_at']) ? date('d M Y', strtotime((string)$item['created_at'])) : '—';

                // ─── ইমেজ ───
                $imgEl = $img !== ''
                    ? "<img src=\"{$img}\" class=\"il-img\" onclick=\"openImageModal('{$img}','{$jsCode}')\" alt=\"\">"
                    : "<div class=\"il-img il-img--empty\"><i class=\"fas fa-image\"></i></div>";

                // ─── মার্ক ব্যাজ ───
                $markColor = $item['mark_color'] ?? '';
                $markBadge = '';
                if ($markColor !== '') {
                    $colorMap = [
                        'yellow' => 'background:#fef9c3; color:#92400e; border:1px solid #f59e0b;',
                        'green'  => 'background:#d1fae5; color:#065f46; border:1px solid #10b981;',
                        'purple' => 'background:#ede9fe; color:#5b21b6; border:1px solid #8b5cf6;',
                    ];
                    $style = $colorMap[$markColor] ?? 'background:var(--sk-surface-3);';
                    $markBadge = '<span class="sk-pill" style="' . $style . ' font-size:.55rem; padding:.1rem .45rem;"><i class="fas fa-circle" style="font-size:.45rem; margin-right:.2rem;"></i> ' . ucfirst($markColor) . '</span>';
                }

                // ─── লোকেশন ব্যাজ ───
                $location = $item['item_location'] ?? 'shop';
                if ($location === 'shop') {
                    $locBadge = '<span class="sk-pill" style="background:#dbeafe; color:#1e40af; font-size:.55rem; padding:.1rem .45rem;"><i class="fas fa-store"></i> দোকান</span>';
                } else {
                    $locBadge = '<span class="sk-pill" style="background:#fef3c7; color:#92400e; font-size:.55rem; padding:.1rem .45rem;"><i class="fas fa-warehouse"></i> গোডাউন</span>';
                }

                // ─── অ্যাডমিন বাটন ───
                $adminBtns = '';
                if ($isAdmin) {
                    $enc = htmlspecialchars(json_encode([
                        'code' => (string)($item['product_code'] ?? ''),
                        'name' => (string)($item['name'] ?? ''),
                        'buy'  => $buyP,
                        'cost' => $costP,
                        'cash' => $cashP,
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    $adminBtns = "<button type=\"button\" class=\"il-edit\" onclick='openProductEditModal({$enc})' title=\"এডিট\"><i class=\"fas fa-pen\"></i></button>";
                }

                // ─── লোকেশন পরিবর্তন (অ্যাডমিনের জন্য) ───
                $locDropdown = '';
                if ($isAdmin) {
                    $locDropdown = '<select class="il-loc-select" onchange="updateLocation(\'' . $jsCode . '\', this.value, this)" style="font-size:.55rem; padding:.1rem .3rem; border-radius:6px; border:1.5px solid var(--sk-border); background:var(--sk-surface);">
                        <option value="shop" ' . ($location === 'shop' ? 'selected' : '') . '>🏪 দোকান</option>
                        <option value="godown" ' . ($location === 'godown' ? 'selected' : '') . '>🏢 গোডাউন</option>
                    </select>';
                }

                // ─── কার্ড রেন্ডার ───
                $html .= '<div class="il-row">';
                $html .= '  <div class="il-left">' . $imgEl . '</div>';
                $html .= '  <div class="il-mid">';
                $html .= '    <div class="il-code-row"><span class="il-code">' . $pCode . '</span>' . $adminBtns . '</div>';
                $html .= '    <div class="il-cat">' . $catName . '</div>';
                $html .= '    <div class="il-meta"><span>' . $displayDate . '</span><span class="dot">·</span><span>' . $entryBy . '</span></div>';
                // ─── ব্যাজ (মার্ক + লোকেশন) ───
                $html .= '    <div style="display:flex; flex-wrap:wrap; gap:.25rem; margin-top:.3rem;">';
                if ($locBadge) $html .= $locBadge;
                if ($markBadge) $html .= $markBadge;
                if ($locDropdown) $html .= $locDropdown;
                $html .= '    </div>';
                $html .= '  </div>';
                $html .= '  <div class="il-right">';
                if ($isAdmin) {
                    $html .= '    <div class="il-metric il-metric--buy"><span class="k">কেনা</span><span class="v">৳' . number_format($minP, 0) . '</span></div>';
                }
                $html .= '    <div class="il-metric il-metric--sell"><span class="k">বিক্রি</span><span class="v">৳' . number_format($cashP, 0) . '</span></div>';
                $html .= '    <div class="il-metric ' . ($stock < 10 ? 'il-metric--warn' : 'il-metric--ok') . '"><span class="k">পিস</span><span class="v">' . $stock . '</span></div>';
                $html .= '  </div>';
                $html .= '</div>';
            }
        }

        echo json_encode([
            'status' => 'success',
            'html' => $html,
            'filtered' => $filtered,
            'totalPieces' => $totalPieces,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'ডেটা লোড সমস্যা!', 'html' => '']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>পণ্য তালিকা — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme-toggle.js"></script>
    <style>
      .sk-appbar__title { font-size: .95rem; }

      .il-summary {
        background: var(--sk-grad-primary); color: #fff;
        border-radius: 12px; padding: 10px 14px; margin-bottom: 10px;
      }
      .il-summary__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
      .il-summary .lbl { font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; opacity: .85; }
      .il-summary .val { font-size: 18px; font-weight: 900; line-height: 1.1; margin-top: 1px; }

      /* ─── ফিল্টার (লোকেশন সহ) ─── */
      .il-filters {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 8px;
        margin-bottom: 8px;
      }
      .il-filters .il-search { grid-column: 1 / -1; }
      @media (max-width: 640px) {
        .il-filters { grid-template-columns: 1fr 1fr; }
        .il-filters .il-search { grid-column: 1 / -1; }
        .il-filters .il-loc-filter { grid-column: 1 / -1; }
      }

      .il-list {
        display: flex; flex-direction: column; gap: 0;
        background: var(--sk-surface);
        border: 1px solid var(--sk-line);
        border-radius: 14px;
        overflow: hidden;
        box-shadow: var(--sk-shadow-sm);
      }

      .il-row {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 12px;
        border-bottom: 1px solid var(--sk-line-2);
      }
      .il-row:last-child { border-bottom: 0; }

      .il-left { flex-shrink: 0; }
      .il-img {
        width: 44px; height: 44px; border-radius: 10px; object-fit: cover;
        border: 1px solid var(--sk-line); cursor: pointer;
        background: var(--sk-surface-3); display: block;
      }
      .il-img--empty {
        display: flex; align-items: center; justify-content: center;
        color: var(--sk-muted); font-size: 14px;
        width: 44px; height: 44px; border-radius: 10px;
        border: 1px solid var(--sk-line); background: var(--sk-surface-3);
      }

      .il-mid { flex: 1; min-width: 0; }
      .il-code-row { display: flex; align-items: center; gap: 6px; }
      .il-code {
        font-weight: 900; font-size: 14px; color: var(--sk-primary);
        line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }
      .il-cat {
        font-size: 12px; font-weight: 600; color: var(--sk-ink-2);
        margin-top: 2px; line-height: 1.25;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }
      .il-meta {
        display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
        font-size: 10px; font-weight: 600; color: var(--sk-muted);
        margin-top: 3px;
      }
      .il-meta .dot { opacity: .5; }

      .il-right {
        display: flex; flex-direction: column; align-items: stretch;
        gap: 4px; flex-shrink: 0; min-width: 78px;
      }
      .il-metric {
        display: flex; align-items: center; justify-content: space-between; gap: 6px;
        padding: 3px 8px; border-radius: 8px;
        background: var(--sk-surface-2); border: 1px solid var(--sk-line-2);
      }
      .il-metric .k {
        font-size: 9px; font-weight: 800; letter-spacing: .04em;
        text-transform: uppercase; color: var(--sk-muted); line-height: 1;
      }
      .il-metric .v {
        font-size: 12px; font-weight: 900; color: var(--sk-ink); line-height: 1;
      }
      .il-metric--buy .v { color: var(--sk-danger); }
      .il-metric--sell {
        background: var(--sk-primary-soft); border-color: transparent;
      }
      .il-metric--sell .k { color: var(--sk-primary); }
      .il-metric--sell .v { color: var(--sk-primary); }
      .il-metric--ok .v { color: var(--sk-success); }
      .il-metric--warn {
        background: var(--sk-warn-soft); border-color: transparent;
      }
      .il-metric--warn .v { color: #b45309; }

      .il-edit {
        width: 26px; height: 26px; border-radius: 8px; padding: 0; flex-shrink: 0;
        border: 1px solid var(--sk-line); background: var(--sk-surface-2);
        color: var(--sk-muted); display: inline-flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 10px;
      }
      .il-edit:active { background: var(--sk-primary-soft); color: var(--sk-primary); }

      .il-loc-select {
        font-size: .55rem; padding: .1rem .3rem; border-radius: 6px;
        border: 1.5px solid var(--sk-border); background: var(--sk-surface);
        color: var(--sk-ink); font-family: inherit; cursor: pointer;
        outline: none;
      }
      .il-loc-select:focus { border-color: var(--sk-primary); }

      .il-pager {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px; margin-top: 12px; flex-wrap: wrap;
      }

      #imageLightbox {
        display: none; position: fixed; z-index: 100000; inset: 0;
        background: rgba(9,9,11,.95); align-items: center; justify-content: center; flex-direction: column;
      }
      #lightboxImg { max-width: 90%; max-height: 78vh; border-radius: 16px; border: 3px solid #fff; object-fit: contain; }
      .sk-container { padding-top: .75rem; padding-bottom: .5rem; }
    </style>
</head>
<body>

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button type="button" class="sk-iconbtn" onclick="skOpenDrawer()" aria-label="Menu"><i class="fas fa-bars"></i></button>
        <a href="inventory_dashboard.php" class="sk-iconbtn" title="Back"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title"><span class=""></span> পণ্য তালিকা</div>
    <div class="sk-appbar__right"></div>
</header>

<div class="sk-overlay" id="skOverlay" onclick="skCloseDrawer()"></div>
<aside class="sk-drawer" id="skDrawer">
    <div class="sk-drawer__head">
        <button type="button" class="sk-drawer__close" onclick="skCloseDrawer()"><i class="fas fa-times"></i></button>
        <img src="logo.png" alt="SADA KALO" class="sk-drawer__logo" onerror="this.style.display='none'">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">ITEM LIST</div>
    </div>
    <div class="sk-drawer__section">Menu</div>
    <nav class="sk-drawer__grid">
        <a href="../dashboard.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-home"></i></span><span class="sk-drawer__label">হোম</span></a>
        <a href="inventory_dashboard.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-th-large"></i></span><span class="sk-drawer__label">ড্যাশবোর্ড</span></a>
        <a href="inventory.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-plus"></i></span><span class="sk-drawer__label">Add Item</span></a>
        <a href="Invantory_Items.php" class="sk-drawer__item active"><span class="sk-drawer__icon"><i class="fas fa-box-open"></i></span><span class="sk-drawer__label">Item List</span></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></span><span class="sk-drawer__label">POS</span></a>
        <a href="inventory_sales_history.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-receipt"></i></span><span class="sk-drawer__label">History</span></a>
        <a href="return_product.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-undo-alt"></i></span><span class="sk-drawer__label">Return</span></a>
        <a href="out_of_stock.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-exclamation-triangle"></i></span><span class="sk-drawer__label">Out Stock</span></a>
        <a href="supplier_exchange.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-exchange-alt"></i></span><span class="sk-drawer__label">সাপ্লায়ার এক্সচেঞ্জ</span></a>
        <?php if ($isAdmin): ?>
        <a href="admin_inventory_control.php" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-cogs"></i></span><span class="sk-drawer__label">Inv Ctrl</span></a>
        <a href="Audit/" class="sk-drawer__item"><span class="sk-drawer__icon"><i class="fas fa-clipboard-list"></i></span><span class="sk-drawer__label">অডিট লগ</span></a>
        <?php endif; ?>
    </nav>
</aside>

<main class="sk-container">

    <div class="il-summary">
        <div class="il-summary__grid">
            <div>
                <div class="lbl">দৃশ্যমান আইটেম</div>
                <div class="val"><span id="visibleCount">0</span></div>
            </div>
            <div style="text-align:right;">
                <div class="lbl">মোট পিস</div>
                <div class="val"><span id="totalPiecesDisplay">0</span></div>
            </div>
        </div>
    </div>

    <div class="sk-card" style="margin-bottom:8px; padding:10px;">
        <div class="il-filters">
            <div class="sk-input-wrap il-search">
                <i class="fas fa-search"></i>
                <input type="text" id="customSearch" placeholder="কোড বা নাম লিখুন..." class="sk-input sk-input--icon" autocomplete="off" inputmode="search">
            </div>
            <div class="sk-input-wrap">
                <i class="fas fa-tags"></i>
                <select id="categoryFilter" class="sk-select sk-input--icon">
                    <option value="all">লোড হচ্ছে...</option>
                </select>
            </div>
            <div class="sk-input-wrap">
                <i class="fas fa-layer-group"></i>
                <select id="stockFilter" class="sk-select sk-input--icon">
                    <option value="all">সব স্টক</option>
                    <option value="low">লো-স্টক (&lt;১০)</option>
                    <option value="high">পর্যাপ্ত (১০+)</option>
                </select>
            </div>
            <!-- ─── লোকেশন ফিল্টার (নতুন) ─── -->
            <div class="sk-input-wrap il-loc-filter" style="grid-column:1/-1;">
                <i class="fas fa-store"></i>
                <select id="locationFilter" class="sk-select sk-input--icon">
                    <option value="all">সব লোকেশন</option>
                    <option value="shop">🏪 দোকান</option>
                    <option value="godown">🏢 গোডাউন</option>
                </select>
            </div>
        </div>
    </div>

    <div id="itemsContainer" class="il-list">
        <div class="sk-empty"><i class="fas fa-spinner fa-spin"></i><p>লোড হচ্ছে...</p></div>
    </div>

    <div class="il-pager">
        <button type="button" class="sk-btn sk-btn--ghost sk-btn--sm" id="prevBtn" onclick="changePage(-1)"><i class="fas fa-chevron-left"></i> আগে</button>
        <span class="sk-pill sk-pill--ghost">Page <span id="pageNum">1</span> / <span id="pageTotal">1</span></span>
        <button type="button" class="sk-btn sk-btn--accent sk-btn--sm" id="nextBtn" onclick="changePage(1)">পরে <i class="fas fa-chevron-right"></i></button>
    </div>
</main>

<?php if ($isAdmin): ?>
<div id="itemFullEditModal" class="sk-modal">
    <div class="sk-modal__sheet">
        <div class="sk-modal__head">
            <div class="sk-modal__title"><i class="fas fa-edit"></i> প্রোডাক্ট ফুল আপডেট</div>
            <button type="button" class="sk-modal__close" onclick="closeProductEditModal()">&times;</button>
        </div>
        <form id="fullEditForm" onsubmit="submitProductEdit(event)">
            <input type="hidden" id="edit_product_code">
            <div class="sk-field">
                <label class="sk-label">পণ্যের নাম</label>
                <input type="text" id="edit_product_name" class="sk-input" required>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.625rem;">
                <div class="sk-field"><label class="sk-label">ক্রয় দাম (৳)</label><input type="number" step="0.01" id="edit_buy_price" class="sk-input" required></div>
                <div class="sk-field"><label class="sk-label">অন্যান্য খরচ (৳)</label><input type="number" step="0.01" id="edit_cost" class="sk-input" required></div>
            </div>
            <div class="sk-field">
                <label class="sk-label">ক্যাশ বিক্রয় মূল্য (৳)</label>
                <input type="number" step="0.01" id="edit_cash_sell" class="sk-input" style="text-align:center; font-size:1.1rem; font-weight:800; color:var(--sk-success); background:var(--sk-success-soft); border-color:var(--sk-success);" required>
            </div>
            <button type="submit" class="sk-btn sk-btn--accent sk-btn--block sk-btn--lg"><i class="fas fa-save"></i> আপডেট করুন</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div id="imageLightbox" onclick="closeImageModal()">
    <span style="position:absolute;top:18px;right:24px;color:#fff;font-size:32px;font-weight:900;cursor:pointer;" onclick="closeImageModal()">&times;</span>
    <img id="lightboxImg" src="" alt="">
    <div id="lightboxText" class="sk-pill sk-pill--ink" style="margin-top:12px;"></div>
</div>

<script>
function skOpenDrawer(){ document.getElementById('skDrawer').classList.add('open'); document.getElementById('skOverlay').classList.add('active'); }
function skCloseDrawer(){ document.getElementById('skDrawer').classList.remove('open'); document.getElementById('skOverlay').classList.remove('active'); }

const userCsrfToken = "<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>";
let currentPage = 1;
let totalPages = 1;
let searchTimer;

function openImageModal(src, label) {
    if (!src) return;
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxText').innerText = label || '';
    document.getElementById('imageLightbox').style.display = 'flex';
}
function closeImageModal() { document.getElementById('imageLightbox').style.display = 'none'; }

function openProductEditModal(dataObj) {
    $('#edit_product_code').val(dataObj.code);
    $('#edit_product_name').val(dataObj.name);
    $('#edit_buy_price').val(dataObj.buy);
    $('#edit_cost').val(dataObj.cost);
    $('#edit_cash_sell').val(dataObj.cash);
    $('#itemFullEditModal').addClass('open');
}
function closeProductEditModal() { $('#itemFullEditModal').removeClass('open'); }

// ─── লোকেশন আপডেট ফাংশন ──────────────────────────────────────────────
function updateLocation(productCode, location, selectEl) {
    if (!confirm('লোকেশন পরিবর্তন করবেন?')) {
        $(selectEl).val($(selectEl).data('prev') || 'shop');
        return;
    }
    $(selectEl).data('prev', location);
    $.ajax({
        url: 'Invantory_Items.php',
        type: 'POST',
        data: {
            ajax_action: 'update_location',
            csrf_token: userCsrfToken,
            product_code: productCode,
            location: location
        },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                alert('✅ লোকেশন আপডেট হয়েছে!');
                loadItems();
            } else {
                alert('❌ ' + (res.message || 'ত্রুটি!'));
            }
        },
        error: function() {
            alert('❌ সার্ভার ত্রুটি!');
        }
    });
}

function changePage(dir) {
    const n = currentPage + dir;
    if (n < 1 || n > totalPages) return;
    currentPage = n;
    loadItems();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function loadItems() {
    $('#itemsContainer').html('<div class="sk-empty"><i class="fas fa-spinner fa-spin"></i><p>লোড হচ্ছে...</p></div>');
    $.ajax({
        url: 'Invantory_Items.php', type: 'POST', dataType: 'json',
        data: {
            ajax_action: 'load_items_cards',
            csrf_token: userCsrfToken,
            page: currentPage,
            search: $('#customSearch').val(),
            category_filter: $('#categoryFilter').val(),
            stock_filter: $('#stockFilter').val(),
            location_filter: $('#locationFilter').val() // ← নতুন
        },
        success: function (res) {
            if (!res || res.status === 'error') {
                $('#itemsContainer').html('<div class="sk-empty"><i class="fas fa-exclamation-triangle"></i><p>লোড ব্যর্থ</p></div>');
                return;
            }
            $('#itemsContainer').html(res.html);
            $('#visibleCount').text(res.filtered || 0);
            $('#totalPiecesDisplay').text(res.totalPieces || 0);
            currentPage = res.page || 1;
            totalPages = res.totalPages || 1;
            $('#pageNum').text(currentPage);
            $('#pageTotal').text(totalPages);
            $('#prevBtn').prop('disabled', currentPage <= 1);
            $('#nextBtn').prop('disabled', currentPage >= totalPages);
        },
        error: function () {
            $('#itemsContainer').html('<div class="sk-empty"><i class="fas fa-exclamation-triangle"></i><p>সার্ভার এরর</p></div>');
        }
    });
}

$(document).ready(function () {
    $.post('Invantory_Items.php', { ajax_action: 'load_categories' }, function (res) {
        if (res && res.html) $('#categoryFilter').html(res.html);
    }, 'json');

    loadItems();

    $('#customSearch').on('input keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { currentPage = 1; loadItems(); }, 150);
    });
    // ─── লোকেশন ফিল্টার (নতুন) ───
    $('#categoryFilter, #stockFilter, #locationFilter').on('change', function () {
        currentPage = 1;
        loadItems();
    });
});

function submitProductEdit(e) {
    e.preventDefault();
    let btn = $('#fullEditForm button[type="submit"]'); let origText = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> আপডেট হচ্ছে...');
    $.ajax({
        url: 'Invantory_Items.php', type: 'POST', dataType: 'json',
        data: {
            ajax_action: 'update_full_product',
            csrf_token: userCsrfToken,
            product_code: $('#edit_product_code').val(),
            name: $('#edit_product_name').val(),
            buy_price: $('#edit_buy_price').val(),
            cost: $('#edit_cost').val(),
            cash_sell: $('#edit_cash_sell').val()
        },
        success: function (res) {
            alert(res.message);
            if (res.status === 'success') { closeProductEditModal(); loadItems(); }
        },
        error: function () { alert('সার্ভার এরর!'); },
        complete: function () { btn.prop('disabled', false).html(origText); }
    });
}
</script>
<style>body{padding-bottom:76px;}</style>
<?php include 'inventory_bottom_nav.php'; ?>
</body>
</html>