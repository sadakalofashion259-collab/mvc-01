<?php
declare(strict_types=1);

/**
 * notification_dashboard.php
 * --------------------------------------------------------------------------
 * SADA KALO — Notification / Activity Center
 *
 *   - SOLD products  -> RED   (লাল)   notification with code + thumbnail.
 *   - ADDED / restock -> GREEN (সবুজ) notification with code + thumbnail.
 *   - Animated bell with unseen-count badge.
 *   - Product-code / barcode lookup (memo).
 *   - Feed search by SKF code or name.
 *   - Pagination ("আরও দেখুন").
 *   - Day grouping (আজ / গতকাল / তারিখ).
 *   - First page shows TODAY's notifications only.
 *
 * Security: PDO prepared statements, CSRF via hash_equals(), session timeout,
 *           strict auth gate, output encoding + client-side text nodes,
 *           whitelisted image paths, hardened headers, error logging.
 *           No new/unverified third-party dependencies introduced.
 * --------------------------------------------------------------------------
 */

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

/* --------------------------------------------------------------------- */
function logSystemError(Throwable $e): void
{
    $logDir = __DIR__ . '/../Logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $line = sprintf(
        "[%s] %s | %s:%d%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL
    );
    @file_put_contents($logDir . '/error_log.txt', $line, FILE_APPEND);
}

$ajaxAction = isset($_POST['ajax_action']) && is_string($_POST['ajax_action']) ? $_POST['ajax_action'] : '';
$isAjax     = $ajaxAction !== '';

function jsonExit(array $payload): void
{
    if (ob_get_length() !== false) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Idle timeout (20 min) -------------------------------------------------- */
$lastActivity = $_SESSION['last_activity'] ?? null;
if (is_int($lastActivity) && (time() - $lastActivity > 1200)) {
    session_unset();
    session_destroy();
    if ($isAjax) {
        jsonExit(['error' => 'session_expired']);
    }
    header('Location: ../index.php');
    exit;
}
$_SESSION['last_activity'] = time();

/* Auth gate ------------------------------------------------------------- */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if ($isAjax) {
        jsonExit(['error' => 'session_expired']);
    }
    header('Location: ../index.php');
    exit;
}

/* CSRF ------------------------------------------------------------------ */
if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

/* DB + identity --------------------------------------------------------- */
$dbPath = '../db_connect.php';
if (file_exists($dbPath)) {
    include $dbPath;
}
/** @var PDO $conn */
$role = isset($_SESSION['role']) && is_string($_SESSION['role']) ? $_SESSION['role'] : 'user';
date_default_timezone_set('Asia/Dhaka');

/* --------------------------------------------------------------------- */
/* Helpers                                                                */
/* --------------------------------------------------------------------- */
function bnNum(string $value): string
{
    return strtr($value, [
        '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
        '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
    ]);
}

function bnTimeAgo(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $diff = max(0, time() - $ts);
    if ($diff < 60)      return 'এইমাত্র';
    if ($diff < 3600)    return bnNum((string) intdiv($diff, 60)) . ' মিনিট আগে';
    if ($diff < 86400)   return bnNum((string) intdiv($diff, 3600)) . ' ঘণ্টা আগে';
    if ($diff < 172800)  return 'গতকাল';
    if ($diff < 2592000) return bnNum((string) intdiv($diff, 86400)) . ' দিন আগে';
    return bnNum(date('d/m/Y', $ts));
}

/** Bengali day label for grouping (timezone-safe, server computed). */
function bnDayLabel(int $epoch): string
{
    if ($epoch <= 0) {
        return '';
    }
    $d     = date('Y-m-d', $epoch);
    $today = date('Y-m-d');
    $yest  = date('Y-m-d', strtotime('-1 day'));
    if ($d === $today) return 'আজ';
    if ($d === $yest)  return 'গতকাল';
    $months = [
        1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
        5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
        9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর',
    ];
    $day = bnNum((string) ((int) date('j', $epoch)));
    $mon = $months[(int) date('n', $epoch)] ?? '';
    $yr  = bnNum(date('Y', $epoch));
    return trim($day . ' ' . $mon . ' ' . $yr);
}

/**
 * Whitelist an image path. Permits the application's own relative upload
 * paths (optionally prefixed with a single "../") while rejecting URL
 * schemes, protocol-relative URLs, and any further directory traversal.
 */
function safeImagePath(mixed $path): string
{
    if (!is_string($path)) {
        return '';
    }
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path)) {  // javascript:, data:, http:, ...
        return '';
    }
    if (strpos($path, '//') !== false) {                 // protocol-relative / doubled slash
        return '';
    }
    $core = $path;
    if (strncmp($core, '../', 3) === 0) {                // allow exactly one leading "../"
        $core = substr($core, 3);
    }
    if (strpos($core, '..') !== false) {                 // no further traversal
        return '';
    }
    if (!preg_match('#^[A-Za-z0-9_\-/]+\.(jpe?g|png|webp|gif)$#i', $core)) {
        return '';
    }
    return $path;
}

/* --------------------------------------------------------------------- */
/* AJAX: barcode / product-code lookup                                    */
/* --------------------------------------------------------------------- */
if ($isAjax && $ajaxAction === 'scan_lookup') {
    $postCsrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($postCsrf === '' || !hash_equals($csrfToken, $postCsrf)) {
        jsonExit(['status' => 'error', 'message' => 'নিরাপত্তা টোকেন মিলছে না!']);
    }

    $code = isset($_POST['product_code']) && is_string($_POST['product_code']) ? trim($_POST['product_code']) : '';
    if ($code === '' || mb_strlen($code) > 100) {
        jsonExit(['status' => 'error', 'message' => 'অবৈধ পণ্য কোড।']);
    }

    try {
        /* Exact code first (best for barcode scans); then partial matches so
           that typing just the number — e.g. "242" — resolves "SKF-242".
           Ranking: exact > ends-with "-<q>" > ends-with "<q>" > contains > name. */
        $endsDash = '%-' . $code;
        $endsCode = '%' . $code;
        $contains = '%' . $code . '%';
        $stmt = $conn->prepare(
            'SELECT i.product_code, i.name, i.image_path, i.pieces, c.name AS cat_name
             FROM inventory i
             LEFT JOIN categories c ON i.category_id = c.id
             WHERE i.product_code = ?
                OR i.product_code LIKE ?
                OR i.product_code LIKE ?
                OR i.name LIKE ?
             ORDER BY
                CASE
                    WHEN i.product_code = ?        THEN 0
                    WHEN i.product_code LIKE ?     THEN 1
                    WHEN i.product_code LIKE ?     THEN 2
                    WHEN i.product_code LIKE ?     THEN 3
                    ELSE 4
                END,
                i.id DESC
             LIMIT 1'
        );
        $stmt->execute([$code, $endsDash, $contains, $contains, $code, $endsDash, $endsCode, $contains]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($product)) {
            jsonExit([
                'status'  => 'success',
                'product' => [
                    'code'  => (string) ($product['product_code'] ?? ''),
                    'name'  => (string) ($product['name'] ?? ''),
                    'image' => safeImagePath($product['image_path'] ?? ''),
                    'stock' => is_numeric($product['pieces'] ?? null) ? (int) $product['pieces'] : 0,
                    'cat'   => (string) ($product['cat_name'] ?? ''),
                ],
            ]);
        }
        jsonExit(['status' => 'error', 'message' => 'এই কোডের কোনো পণ্য পাওয়া যায়নি।']);
    } catch (Throwable $e) {
        logSystemError($e);
        jsonExit(['status' => 'error', 'message' => 'ডাটাবেস এরর!']);
    }
}

/* --------------------------------------------------------------------- */
/* AJAX: notification feed (search + pagination + day grouping)           */
/* --------------------------------------------------------------------- */
if ($isAjax && $ajaxAction === 'load_notifications') {
    $postCsrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($postCsrf === '' || !hash_equals($csrfToken, $postCsrf)) {
        jsonExit(['error' => 'নিরাপত্তা টোকেন মিলছে না!']);
    }

    $page = isset($_POST['page']) && is_numeric($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
    $type = isset($_POST['type']) && is_string($_POST['type']) ? $_POST['type'] : 'all';
    if (!in_array($type, ['all', 'sold', 'added'], true)) {
        $type = 'all';
    }
    $q = isset($_POST['q']) && is_string($_POST['q']) ? trim($_POST['q']) : '';
    if (mb_strlen($q) > 60) {
        $q = mb_substr($q, 0, 60);
    }
    $isSearch  = ($q !== '');
    $pageSize  = 30;

    /* Unified feed source (parameterised, no string-built values). */
    $unionSql =
        "SELECT 'sold' AS ntype, isi.product_code AS code, COALESCE(i.name,'') AS name,
                i.image_path AS image, isi.pieces AS qty, s.created_at AS ts,
                COALESCE(u.username,'') AS who
         FROM inventory_sale_items isi
         JOIN inventory_sales s ON isi.sale_id = s.id
         LEFT JOIN inventory i  ON isi.product_code = i.product_code
         LEFT JOIN users u      ON s.sold_by = u.id
         UNION ALL
         SELECT 'added', i.product_code, COALESCE(i.name,''), i.image_path, i.pieces,
                i.created_at, COALESCE(u.username,'')
         FROM inventory i
         LEFT JOIN users u ON i.added_by = u.id
         UNION ALL
         SELECT 'restock', a.product_code, COALESCE(i.name,''), i.image_path, a.pieces,
                a.created_at, COALESCE(u.username,'')
         FROM inventory_adjustments a
         LEFT JOIN inventory i ON a.product_code = i.product_code
         LEFT JOIN users u     ON a.adjusted_by = u.id
         WHERE a.adjustment_type = 'increase'";

    /* WHERE / params for the outer query. */
    $where  = [];
    $params = [];

    if ($isSearch) {
        $where[]  = '(feed.code LIKE ? OR feed.name LIKE ?)';
        $like     = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    } elseif ($page === 1) {
        $where[] = 'DATE(feed.ts) = CURRENT_DATE';          // first page = today only
    } else {
        $where[] = 'feed.ts < CURRENT_DATE';                // later pages = older
    }

    if ($type === 'sold') {
        $where[] = "feed.ntype = 'sold'";
    } elseif ($type === 'added') {
        $where[] = "feed.ntype IN ('added','restock')";
    }

    /* OFFSET / LIMIT */
    if ($isSearch) {
        $offset = ($page - 1) * $pageSize;
        $limit  = $pageSize;
    } elseif ($page === 1) {
        $offset = 0;
        $limit  = 300;                                       // all of today (safety cap)
    } else {
        $offset = ($page - 2) * $pageSize;
        $limit  = $pageSize;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    /* Fetch one extra row to detect "has more". */
    $fetchLimit = $limit + 1;

    $items   = [];
    $hasMore = false;
    try {
        $sql  = "SELECT feed.* FROM ({$unionSql}) AS feed {$whereSql}
                 ORDER BY feed.ts DESC
                 LIMIT {$fetchLimit} OFFSET {$offset}";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > $limit) {
            $hasMore = true;
            $rows = array_slice($rows, 0, $limit);
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ts    = isset($row['ts']) && is_string($row['ts']) ? $row['ts'] : '';
            $epoch = $ts !== '' ? strtotime($ts) : false;
            $epoch = $epoch !== false ? $epoch : 0;
            $items[] = [
                'type'     => (string) ($row['ntype'] ?? 'added'),
                'code'     => (string) ($row['code'] ?? ''),
                'name'     => (string) ($row['name'] ?? ''),
                'image'    => safeImagePath($row['image'] ?? ''),
                'qty'      => is_numeric($row['qty'] ?? null) ? (int) $row['qty'] : 0,
                'who'      => (string) ($row['who'] ?? ''),
                'epoch'    => $epoch,
                'ago'      => $ts !== '' ? bnTimeAgo($ts) : '',
                'dayKey'   => $epoch > 0 ? date('Y-m-d', $epoch) : '',
                'dayLabel' => bnDayLabel($epoch),
            ];
        }
    } catch (Throwable $e) {
        logSystemError($e);
    }

    /* On the first, unfiltered page, also detect whether older items exist
       (so "load older" can be offered even when today is empty) and compute
       today's headline counters for the two summary cards. */
    $stats = null;
    if ($page === 1 && !$isSearch) {
        if (!$hasMore) {
            try {
                $probe = $conn->query(
                    "SELECT
                        (SELECT COUNT(*) FROM inventory WHERE created_at < CURRENT_DATE)
                      + (SELECT COUNT(*) FROM inventory_sale_items isi
                         JOIN inventory_sales s ON isi.sale_id = s.id
                         WHERE s.created_at < CURRENT_DATE)
                      + (SELECT COUNT(*) FROM inventory_adjustments
                         WHERE adjustment_type = 'increase' AND created_at < CURRENT_DATE) AS older_cnt"
                );
                $olderCnt = $probe !== false ? (int) $probe->fetchColumn() : 0;
                $hasMore  = $olderCnt > 0;
            } catch (Throwable $e) {
                logSystemError($e);
            }
        }

        if ($type === 'all') {
            $soldToday  = 0;
            $addedToday = 0;
            try {
                $s1 = $conn->query("SELECT COUNT(*) FROM inventory_sale_items isi
                                    JOIN inventory_sales s ON isi.sale_id = s.id
                                    WHERE DATE(s.created_at) = CURRENT_DATE");
                if ($s1 !== false) {
                    $soldToday = (int) $s1->fetchColumn();
                }
                $s2 = $conn->query("SELECT
                        (SELECT COUNT(*) FROM inventory WHERE DATE(created_at) = CURRENT_DATE)
                      + (SELECT COUNT(*) FROM inventory_adjustments
                         WHERE adjustment_type = 'increase' AND DATE(created_at) = CURRENT_DATE) AS c");
                if ($s2 !== false) {
                    $addedToday = (int) $s2->fetchColumn();
                }
            } catch (Throwable $e) {
                logSystemError($e);
            }
            $stats = ['soldToday' => $soldToday, 'addedToday' => $addedToday];
        }
    }

    jsonExit([
        'items'   => $items,
        'page'    => $page,
        'hasMore' => $hasMore,
        'search'  => $isSearch,
        'stats'   => $stats,
    ]);
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>নোটিফিকেশন সেন্টার — SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=Hind+Siliguri:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme-toggle.js"></script>
    <style>
        /* ---------- Notification bell ---------- */
        .nf-bell { position:relative; }
        .nf-bell.ringing i { transform-origin:50% 0; animation:nf-ring .9s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes nf-ring { 0%,100%{transform:rotate(0);} 10%{transform:rotate(22deg);} 20%{transform:rotate(-18deg);} 30%{transform:rotate(16deg);} 40%{transform:rotate(-12deg);} 50%{transform:rotate(8deg);} 60%{transform:rotate(-5deg);} 70%{transform:rotate(3deg);} }
        .nf-bell__badge { position:absolute; top:-4px; right:-4px; min-width:18px; height:18px; padding:0 5px; background:var(--sk-brand); color:#fff; font-size:10px; font-weight:900; line-height:18px; text-align:center; border-radius:999px; border:2px solid var(--sk-surface); display:none; animation:nf-badge-pulse 1.8s infinite; }
        .nf-bell__badge.show { display:block; }
        @keyframes nf-badge-pulse { 0%{box-shadow:0 0 0 0 rgba(220,53,69,.5);} 70%{box-shadow:0 0 0 9px rgba(220,53,69,0);} 100%{box-shadow:0 0 0 0 rgba(220,53,69,0);} }

        /* ---------- Scan / lookup ---------- */
        .nf-scan { display:flex; gap:8px; align-items:stretch; }
        .nf-scan__input { flex:1; height:46px; border:1px solid var(--sk-line); border-radius:14px; padding:0 14px; font-size:13px; font-weight:700; color:var(--sk-ink); background:var(--sk-surface); outline:none; transition:.18s; font-family:'JetBrains Mono', monospace; letter-spacing:1px; }
        .nf-scan__input:focus { border-color:var(--sk-brand); box-shadow:var(--sk-shadow-brand); }
        .nf-scan__btn { width:46px; height:46px; border:none; border-radius:14px; color:#fff; font-size:17px; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; background:var(--sk-grad-ink); box-shadow:var(--sk-shadow-sm); transition:.18s; }
        .nf-scan__btn--brand { background:var(--sk-grad-brand); }
        .nf-scan__btn:active { transform:scale(.93); }

        .nf-result { display:none; margin-top:12px; padding:12px; border-radius:16px; gap:12px; background:var(--sk-surface-2); border:1px solid var(--sk-line); align-items:center; animation:nf-slide-in .35s ease both; }
        .nf-result.show { display:flex; }
        .nf-result img, .nf-result .noimg { width:64px; height:64px; border-radius:14px; object-fit:cover; flex-shrink:0; display:flex; align-items:center; justify-content:center; background:var(--sk-surface-3); color:var(--sk-muted); font-size:22px; border:1px solid var(--sk-line-2); cursor:pointer; }
        .nf-result__code { font-family:'JetBrains Mono', monospace; font-weight:900; font-size:14px; color:var(--sk-ink); letter-spacing:1px; }
        .nf-result__name { font-size:12px; font-weight:700; color:var(--sk-ink-3); margin-top:2px; }
        .nf-result__meta { font-size:11px; font-weight:700; color:var(--sk-muted); margin-top:4px; }

        /* ---------- Feed search ---------- */
        .nf-search { position:relative; margin-bottom:12px; }
        .nf-search input { width:100%; height:44px; border:1px solid var(--sk-line); border-radius:14px; padding:0 40px 0 40px; font-size:13px; font-weight:700; color:var(--sk-ink); background:var(--sk-surface); outline:none; transition:.18s; }
        .nf-search input:focus { border-color:var(--sk-brand); box-shadow:var(--sk-shadow-brand); }
        .nf-search__ic { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--sk-muted); font-size:14px; }
        .nf-search__clear { position:absolute; right:10px; top:50%; transform:translateY(-50%); width:26px; height:26px; border:none; border-radius:8px; background:var(--sk-surface-3); color:var(--sk-ink-3); cursor:pointer; display:none; align-items:center; justify-content:center; }
        .nf-search__clear.show { display:flex; }

        /* ---------- Filter chips ---------- */
        .nf-filters { display:flex; gap:8px; margin:0 0 12px; }
        .nf-filter { flex:1; height:38px; border:1px solid var(--sk-line); border-radius:12px; cursor:pointer; font-size:11px; font-weight:800; color:var(--sk-ink-3); background:var(--sk-surface); display:flex; align-items:center; justify-content:center; gap:6px; transition:.16s; }
        .nf-filter.active { color:#fff; border-color:transparent; }
        .nf-filter[data-f="all"].active   { background:var(--sk-grad-ink); }
        .nf-filter[data-f="sold"].active  { background:var(--sk-grad-brand); }
        .nf-filter[data-f="added"].active { background:linear-gradient(135deg,#198754 0%,#0a3622 100%); }

        /* ---------- Day group header ---------- */
        .nf-day { display:flex; align-items:center; gap:10px; margin:6px 2px 2px; }
        .nf-day:first-child { margin-top:0; }
        .nf-day__label { font-size:11px; font-weight:900; color:var(--sk-ink); background:var(--sk-surface-3); padding:4px 12px; border-radius:999px; white-space:nowrap; }
        .nf-day__line { flex:1; height:1px; background:var(--sk-line); }

        /* ---------- Notification items ---------- */
        .nf-feed { display:flex; flex-direction:column; gap:10px; }
        .nf-item { display:flex; gap:12px; align-items:center; padding:12px; border-radius:16px; background:var(--sk-surface); border:1px solid var(--sk-line); box-shadow:var(--sk-shadow-sm); position:relative; overflow:hidden; animation:nf-slide-in .4s ease both; }
        .nf-item::before { content:''; position:absolute; left:0; top:0; bottom:0; width:5px; }
        .nf-item--sold::before  { background:var(--sk-danger); }
        .nf-item--added::before { background:var(--sk-success); }
        .nf-item.is-new { box-shadow:0 0 0 2px var(--sk-brand-soft), var(--sk-shadow); }
        @keyframes nf-slide-in { from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }

        .nf-item__thumb { width:54px; height:54px; border-radius:13px; object-fit:cover; flex-shrink:0; cursor:pointer; display:flex; align-items:center; justify-content:center; background:var(--sk-surface-3); color:var(--sk-muted); font-size:20px; border:1px solid var(--sk-line-2); }
        .nf-item--sold .nf-item__thumb { filter:grayscale(.15); }
        .nf-item__body { flex:1; min-width:0; }
        .nf-item__top { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .nf-item__tag { font-size:9px; font-weight:900; padding:2px 8px; border-radius:999px; text-transform:uppercase; letter-spacing:.5px; display:inline-flex; align-items:center; gap:4px; }
        .nf-item__tag--sold  { background:var(--sk-danger-soft); color:var(--sk-danger-ink); }
        .nf-item__tag--added { background:var(--sk-success-soft); color:var(--sk-success-ink); }
        .nf-item__code { font-family:'JetBrains Mono', monospace; font-weight:900; font-size:13px; color:var(--sk-ink); letter-spacing:.5px; }
        .nf-item__name { font-size:11px; font-weight:700; color:var(--sk-ink-3); margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .nf-item__meta { font-size:10px; font-weight:700; color:var(--sk-muted); margin-top:4px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .nf-item__qty { flex-shrink:0; text-align:center; min-width:42px; padding:6px 8px; border-radius:12px; font-weight:900; font-size:14px; line-height:1; }
        .nf-item__qty--sold  { background:var(--sk-danger-soft); color:var(--sk-danger-ink); }
        .nf-item__qty--added { background:var(--sk-success-soft); color:var(--sk-success-ink); }
        .nf-item__qty span { display:block; font-size:8px; font-weight:800; letter-spacing:.4px; margin-top:2px; opacity:.8; }

        .nf-empty { text-align:center; padding:36px 16px; color:var(--sk-muted); }
        .nf-empty i { font-size:34px; color:var(--sk-success); margin-bottom:10px; }

        .nf-more { width:100%; height:44px; margin-top:12px; border:1px dashed var(--sk-line); border-radius:14px; background:var(--sk-surface); color:var(--sk-ink-2); font-size:12px; font-weight:800; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:.16s; }
        .nf-more:hover { border-color:var(--sk-brand); color:var(--sk-brand); }
        .nf-more[disabled] { opacity:.6; cursor:default; }

        /* ---------- Toast ---------- */
        .nf-toast { position:fixed; left:50%; bottom:40px; transform:translateX(-50%) translateY(20px); background:var(--sk-grad-ink); color:#fff; padding:10px 18px; border-radius:999px; font-size:12px; font-weight:800; box-shadow:var(--sk-shadow-lg); z-index:9000; opacity:0; pointer-events:none; transition:.3s; display:flex; align-items:center; gap:8px; }
        .nf-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }

        /* ---------- Scanner modal ---------- */
        .sk-modal { display:none; position:fixed; inset:0; z-index:99000; background:rgba(9,9,11,.92); align-items:center; justify-content:center; padding:18px; backdrop-filter:blur(6px); }
        .sk-modal.open { display:flex; }
        .sk-modal__box { width:100%; max-width:380px; background:var(--sk-surface); border-radius:22px; padding:16px; box-shadow:var(--sk-shadow-lg); }
        .sk-modal__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
        .sk-modal__title { font-size:13px; font-weight:900; color:var(--sk-ink); }
        .sk-modal__close { width:34px; height:34px; border:none; border-radius:11px; background:var(--sk-surface-3); color:var(--sk-ink); font-size:15px; cursor:pointer; }
        #reader { width:100%; border-radius:14px; overflow:hidden; }
        .nf-scanner-hint { display:block; text-align:center; font-size:11px; font-weight:700; color:var(--sk-muted); margin-top:10px; }

        /* ---------- Lightbox ---------- */
        #imageLightbox { display:none; position:fixed; z-index:100000; inset:0; background:rgba(9,9,11,.95); align-items:center; justify-content:center; flex-direction:column; backdrop-filter:blur(8px); }
        #lightboxImg { max-width:90%; max-height:78vh; border-radius:18px; border:3px solid #fff; object-fit:contain; box-shadow:var(--sk-shadow-lg); }
        .close-lightbox { position:absolute; top:18px; right:24px; color:#fff; font-size:36px; font-weight:900; cursor:pointer; }
    </style>
</head>
<body>

<!-- App Bar -->
<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button class="sk-iconbtn" onclick="toggleSidebar()" aria-label="Menu"><i class="fas fa-bars"></i></button>
    </div>
    <div class="sk-appbar__title"><span class="dot"></span> নোটিফিকেশন</div>
    <div class="sk-appbar__right" style="display:flex; gap:8px; align-items:center;">
        <button class="sk-iconbtn nf-bell" id="notifBell" onclick="markAllSeen()" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <span class="nf-bell__badge" id="notifBellBadge">0</span>
        </button>
        <a href="../logout.php" onclick="localStorage.removeItem('sk-cache');" class="sk-iconbtn sk-iconbtn--danger" aria-label="Logout"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<!-- Drawer -->
<div class="sk-overlay" id="myOverlay" onclick="toggleSidebar()"></div>
<aside class="sk-drawer" id="mySidebar">
    <div class="sk-drawer__head">
        <button class="sk-drawer__close" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        <img src="logo.png" alt="Logo" onerror="this.style.display='none'" class="sk-drawer__logo">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">FASHION</div>
    </div>
    <div class="sk-drawer__section">Quick Menu</div>
    <div class="sk-drawer__grid">
        <a href="../dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-home"></i></div><span class="sk-drawer__label">হোমপেজ</span></a>
        <a href="notification_dashboard.php" class="sk-drawer__item active"><div class="sk-drawer__icon"><i class="fas fa-bell"></i></div><span class="sk-drawer__label">নোটিফিকেশন</span></a>
        <a href="inventory_dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-th-large"></i></div><span class="sk-drawer__label">ড্যাশবোর্ড</span></a>
        <a href="inventory.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-plus"></i></div><span class="sk-drawer__label">Add Item</span></a>
        <a href="Invantory_Items.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-box-open"></i></div><span class="sk-drawer__label">Item List</span></a>
        <a href="inventory_pos.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></div><span class="sk-drawer__label">POS Sell</span></a>
        <a href="inventory_sales_history.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-receipt"></i></div><span class="sk-drawer__label">History</span></a>
        <a href="out_of_stock.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-exclamation-triangle"></i></div><span class="sk-drawer__label">Out Stock</span></a>
    </div>
</aside>

<main class="sk-container">

    <!-- Today summary -->
    <div class="sk-stats sk-stats--2" style="margin-bottom:14px;">
        <div class="sk-stat sk-stat--accent">
            <div class="sk-row sk-row--between">
                <span class="sk-stat__icon" style="background:var(--sk-grad-brand);"><i class="fas fa-arrow-trend-down"></i></span>
                <span class="sk-pill sk-pill--brand">আজ</span>
            </div>
            <div class="sk-stat__lbl">বিক্রি হয়েছে</div>
            <div class="sk-stat__val" id="statSold"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
        <div class="sk-stat sk-stat--success">
            <div class="sk-row sk-row--between">
                <span class="sk-stat__icon"><i class="fas fa-arrow-trend-up"></i></span>
                <span class="sk-pill sk-pill--success">আজ</span>
            </div>
            <div class="sk-stat__lbl">যোগ হয়েছে</div>
            <div class="sk-stat__val" id="statAdded"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>

    <!-- Scan / product-code lookup -->
    <div class="sk-card" style="margin-bottom:14px;">
        <div class="sk-section-title" style="margin:0 0 10px;">
            <h2><i class="fas fa-barcode"></i> পণ্য কোড / বারকোড স্ক্যান</h2>
            <span class="sk-sub">Memo Lookup</span>
        </div>
        <div class="nf-scan">
            <input type="text" id="scanInput" class="nf-scan__input" inputmode="text"
                   placeholder="পণ্য কোড লিখুন..." autocomplete="off" maxlength="100"
                   onkeydown="if(event.key==='Enter'){event.preventDefault();lookupProduct();}">
            <button type="button" class="nf-scan__btn" onclick="lookupProduct()" aria-label="Search"><i class="fas fa-magnifying-glass"></i></button>
            <button type="button" class="nf-scan__btn nf-scan__btn--brand" onclick="openScanner()" aria-label="Scan"><i class="fas fa-qrcode"></i></button>
        </div>
        <div class="nf-result" id="scanResult">
            <div id="scanResultThumb"></div>
            <div style="flex:1; min-width:0;">
                <div class="nf-result__code" id="scanResultCode"></div>
                <div class="nf-result__name" id="scanResultName"></div>
                <div class="nf-result__meta" id="scanResultMeta"></div>
            </div>
        </div>
        <div id="scanError" style="display:none; margin-top:10px; font-size:12px; font-weight:700; color:var(--sk-danger); text-align:center;"></div>
    </div>

    <!-- Feed -->
    <div class="sk-card" style="margin-bottom:24px;">
        <div class="sk-section-title" style="margin:0 0 10px;">
            <h2><i class="fas fa-clock-rotate-left"></i> সাম্প্রতিক অ্যাক্টিভিটি</h2>
            <span class="sk-sub" id="feedScopeLabel"><i class="fas fa-circle" style="color:var(--sk-success); font-size:7px;"></i> আজ</span>
        </div>

        <!-- Feed search -->
        <div class="nf-search">
            <i class="fas fa-magnifying-glass nf-search__ic"></i>
            <input type="text" id="feedSearch" placeholder="SKF কোড বা নাম সার্চ করুন..." autocomplete="off" maxlength="60">
            <button type="button" class="nf-search__clear" id="feedSearchClear" onclick="clearFeedSearch()" aria-label="Clear"><i class="fas fa-times"></i></button>
        </div>

        <!-- Filters -->
        <div class="nf-filters">
            <div class="nf-filter active" data-f="all" onclick="setFilter('all')"><i class="fas fa-layer-group"></i> সব</div>
            <div class="nf-filter" data-f="sold" onclick="setFilter('sold')"><i class="fas fa-circle" style="color:var(--sk-danger);"></i> বিক্রি</div>
            <div class="nf-filter" data-f="added" onclick="setFilter('added')"><i class="fas fa-circle" style="color:var(--sk-success);"></i> নতুন স্টক</div>
        </div>

        <div class="nf-feed" id="notifFeed">
            <div class="nf-empty" id="feedLoading"><i class="fas fa-spinner fa-spin" style="color:var(--sk-brand);"></i><div style="font-weight:700; margin-top:8px;">লোড হচ্ছে...</div></div>
        </div>
        <div id="feedMoreWrap"></div>
    </div>

</main>

<!-- Scanner Modal -->
<div id="scannerModal" class="sk-modal">
    <div class="sk-modal__box">
        <div class="sk-modal__head">
            <div class="sk-modal__title"><i class="fas fa-qrcode"></i> বারকোড স্ক্যান</div>
            <button class="sk-modal__close" onclick="closeScanner()"><i class="fas fa-times"></i></button>
        </div>
        <div id="reader"></div>
        <span class="nf-scanner-hint"><i class="fas fa-info-circle"></i> বারকোড ক্যামেরার সামনে ধরুন</span>
    </div>
</div>

<!-- Image Lightbox -->
<div id="imageLightbox" onclick="closeImageModal()">
    <span class="close-lightbox" onclick="closeImageModal()">&times;</span>
    <img id="lightboxImg" src="" alt="">
    <div id="lightboxText" style="margin-top:14px; font-weight:900; letter-spacing:2.5px; font-size:14px; color:#fff; background:#09090b; border:1px solid var(--sk-brand); padding:8px 18px; border-radius:14px;"></div>
</div>

<!-- Toast -->
<div class="nf-toast" id="nfToast"><i class="fas fa-bell"></i> <span id="nfToastText"></span></div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
const userCsrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
const SELF = 'notification_dashboard.php';

/* ----------------------------- UI helpers ----------------------------- */
function toggleSidebar() {
    document.getElementById('mySidebar').classList.toggle('open');
    document.getElementById('myOverlay').classList.toggle('active');
}
function openImageModal(src, label) {
    if (!src) return;
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxText').innerText = label || '';
    document.getElementById('imageLightbox').style.display = 'flex';
}
function closeImageModal() { document.getElementById('imageLightbox').style.display = 'none'; }

let toastTimer = null;
function showToast(text) {
    const t = document.getElementById('nfToast');
    document.getElementById('nfToastText').textContent = text;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}
function ringBell() {
    const bell = document.getElementById('notifBell');
    bell.classList.remove('ringing');
    void bell.offsetWidth;
    bell.classList.add('ringing');
}

/* ------------------------ Unseen tracking ----------------------------- */
function getLastSeen() { const v = parseInt(localStorage.getItem('sk-notif-seen') || '0', 10); return isNaN(v) ? 0 : v; }
function setLastSeen(epoch) { localStorage.setItem('sk-notif-seen', String(epoch)); }

let maxEpochSeenInFeed = 0;
function updateBadge() {
    const seen = getLastSeen();
    const unseen = feedItems.filter(it => it.epoch > seen).length;
    const badge = document.getElementById('notifBellBadge');
    if (unseen > 0) { badge.textContent = unseen > 99 ? '99+' : String(unseen); badge.classList.add('show'); }
    else { badge.classList.remove('show'); }
}
function markAllSeen() {
    setLastSeen(maxEpochSeenInFeed || Math.floor(Date.now() / 1000));
    updateBadge();
    renderFeed();
    ringBell();
}

/* ----------------------------- State ---------------------------------- */
let feedItems = [];
let curPage = 1;
let curType = 'all';
let curQ = '';
let hasMore = false;
let loading = false;

/* ----------------------------- Thumbs --------------------------------- */
function buildThumb(image, code, big) {
    if (image) {
        const img = document.createElement('img');
        img.className = big ? '' : 'nf-item__thumb';
        img.src = image;
        img.alt = 'Img';
        img.loading = 'lazy';
        img.addEventListener('click', () => openImageModal(image, code));
        return img;
    }
    const ph = document.createElement('div');
    ph.className = big ? 'noimg' : 'nf-item__thumb';
    ph.innerHTML = '<i class="fas fa-image"></i>';
    return ph;
}

/* ----------------------------- Render --------------------------------- */
function makeItemEl(it) {
    const sold = it.type === 'sold';
    const item = document.createElement('div');
    item.className = 'nf-item ' + (sold ? 'nf-item--sold' : 'nf-item--added');
    if (it.epoch > getLastSeen()) item.classList.add('is-new');

    item.appendChild(buildThumb(it.image, it.code, false));

    const body = document.createElement('div');
    body.className = 'nf-item__body';

    const top = document.createElement('div');
    top.className = 'nf-item__top';
    const tag = document.createElement('span');
    tag.className = 'nf-item__tag ' + (sold ? 'nf-item__tag--sold' : 'nf-item__tag--added');
    let label, icon;
    if (sold) { label = 'বিক্রি'; icon = 'fa-cart-shopping'; }
    else if (it.type === 'restock') { label = 'স্টক বৃদ্ধি'; icon = 'fa-arrow-up'; }
    else { label = 'নতুন পণ্য'; icon = 'fa-plus'; }
    tag.innerHTML = '<i class="fas ' + icon + '"></i> ' + label;
    const code = document.createElement('span');
    code.className = 'nf-item__code';
    code.textContent = it.code || '—';
    top.appendChild(tag);
    top.appendChild(code);

    const name = document.createElement('div');
    name.className = 'nf-item__name';
    name.textContent = it.name || 'নাম নেই';

    const meta = document.createElement('div');
    meta.className = 'nf-item__meta';
    const ago = document.createElement('span');
    ago.innerHTML = '<i class="far fa-clock"></i> ';
    ago.appendChild(document.createTextNode(it.ago || ''));
    meta.appendChild(ago);
    if (it.who) {
        const who = document.createElement('span');
        who.innerHTML = '<i class="far fa-user"></i> ';
        who.appendChild(document.createTextNode(it.who));
        meta.appendChild(who);
    }

    body.appendChild(top);
    body.appendChild(name);
    body.appendChild(meta);

    const qty = document.createElement('div');
    qty.className = 'nf-item__qty ' + (sold ? 'nf-item__qty--sold' : 'nf-item__qty--added');
    const qn = document.createElement('div');
    qn.textContent = (sold ? '-' : '+') + (it.qty || 0);
    const ql = document.createElement('span');
    ql.textContent = 'পিস';
    qty.appendChild(qn);
    qty.appendChild(ql);

    item.appendChild(body);
    item.appendChild(qty);
    return item;
}

function renderFeed() {
    const wrap = document.getElementById('notifFeed');
    wrap.innerHTML = '';

    if (feedItems.length === 0) {
        let msg;
        if (curQ) msg = '<i class="fas fa-magnifying-glass" style="color:var(--sk-muted);"></i><div style="font-weight:700;">"' + escapeHtml(curQ) + '" এর কোনো ফলাফল নেই।</div>';
        else if (curType === 'sold') msg = '<i class="fas fa-cart-shopping"></i><div style="font-weight:700;">আজ কোনো বিক্রি নেই।</div>';
        else if (curType === 'added') msg = '<i class="fas fa-box"></i><div style="font-weight:700;">আজ নতুন কোনো স্টক যোগ হয়নি।</div>';
        else msg = '<i class="fas fa-inbox"></i><div style="font-weight:700;">আজ কোনো নোটিফিকেশন নেই।</div>';
        wrap.innerHTML = '<div class="nf-empty">' + msg + '</div>';
    } else {
        let lastDay = null;
        feedItems.forEach(it => {
            if (it.dayKey !== lastDay) {
                lastDay = it.dayKey;
                const dh = document.createElement('div');
                dh.className = 'nf-day';
                dh.innerHTML = '<span class="nf-day__label"></span><span class="nf-day__line"></span>';
                dh.querySelector('.nf-day__label').textContent = it.dayLabel || '';
                wrap.appendChild(dh);
            }
            wrap.appendChild(makeItemEl(it));
        });
    }

    /* "load more" button */
    const moreWrap = document.getElementById('feedMoreWrap');
    moreWrap.innerHTML = '';
    if (hasMore) {
        const btn = document.createElement('button');
        btn.className = 'nf-more';
        btn.id = 'feedMoreBtn';
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> ' + (curQ ? 'আরও ফলাফল' : (curPage === 1 ? 'পুরোনো নোটিফিকেশন দেখুন' : 'আরও দেখুন'));
        btn.addEventListener('click', loadMore);
        moreWrap.appendChild(btn);
    }
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

/* ----------------------------- Data load ------------------------------ */
function loadFeed(append, isPoll) {
    if (loading) return;
    loading = true;
    if (!append && !isPoll) {
        document.getElementById('notifFeed').innerHTML =
            '<div class="nf-empty"><i class="fas fa-spinner fa-spin" style="color:var(--sk-brand);"></i><div style="font-weight:700; margin-top:8px;">লোড হচ্ছে...</div></div>';
        document.getElementById('feedMoreWrap').innerHTML = '';
    }
    const moreBtn = document.getElementById('feedMoreBtn');
    if (append && moreBtn) { moreBtn.setAttribute('disabled', 'disabled'); moreBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> লোড হচ্ছে...'; }

    $.ajax({
        url: SELF, type: 'POST', dataType: 'json',
        data: { ajax_action: 'load_notifications', csrf_token: userCsrfToken, page: curPage, type: curType, q: curQ },
        success: function (res) {
            loading = false;
            if (res.error === 'session_expired') { window.location.href = '../index.php'; return; }
            if (res.error) { return; }

            if (res.stats) {
                document.getElementById('statSold').textContent  = (res.stats.soldToday || 0) + ' টি';
                document.getElementById('statAdded').textContent = (res.stats.addedToday || 0) + ' টি';
            }

            hasMore = !!res.hasMore;
            const incoming = Array.isArray(res.items) ? res.items : [];

            if (append) {
                /* de-dup guard on (type|code|epoch) */
                const seen = new Set(feedItems.map(x => x.type + '|' + x.code + '|' + x.epoch));
                incoming.forEach(x => { const k = x.type + '|' + x.code + '|' + x.epoch; if (!seen.has(k)) { feedItems.push(x); seen.add(k); } });
            } else {
                feedItems = incoming;
            }

            feedItems.forEach(x => { if (x.epoch > maxEpochSeenInFeed) maxEpochSeenInFeed = x.epoch; });

            renderFeed();
            updateBadge();
        },
        error: function () {
            loading = false;
            if (!append) {
                document.getElementById('notifFeed').innerHTML =
                    '<div class="nf-empty"><i class="fas fa-triangle-exclamation" style="color:var(--sk-danger);"></i><div style="font-weight:700;">লোড করা যায়নি। আবার চেষ্টা করুন।</div></div>';
            } else { renderFeed(); }
        }
    });
}

function loadMore() {
    curPage += 1;
    loadFeed(true, false);
}

/* ----------------------------- Controls ------------------------------- */
function setFilter(f) {
    if (curType === f) return;
    curType = f;
    document.querySelectorAll('.nf-filter').forEach(el => el.classList.toggle('active', el.dataset.f === f));
    curPage = 1;
    updateScopeLabel();
    loadFeed(false, false);
}

let searchTimer = null;
$('#feedSearch').on('input', function () {
    const val = $(this).val().trim();
    document.getElementById('feedSearchClear').classList.toggle('show', val.length > 0);
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
        curQ = val;
        curPage = 1;
        updateScopeLabel();
        loadFeed(false, false);
    }, 350);
});
function clearFeedSearch() {
    document.getElementById('feedSearch').value = '';
    document.getElementById('feedSearchClear').classList.remove('show');
    curQ = '';
    curPage = 1;
    updateScopeLabel();
    loadFeed(false, false);
}
function updateScopeLabel() {
    const el = document.getElementById('feedScopeLabel');
    if (curQ) { el.innerHTML = '<i class="fas fa-magnifying-glass" style="font-size:9px;"></i> সার্চ'; }
    else { el.innerHTML = '<i class="fas fa-circle" style="color:var(--sk-success); font-size:7px;"></i> আজ'; }
}

/* --------------------------- Product lookup --------------------------- */
function lookupProduct() {
    const code = document.getElementById('scanInput').value.trim();
    const errBox = document.getElementById('scanError');
    const resBox = document.getElementById('scanResult');
    errBox.style.display = 'none';
    if (!code) { return; }

    $.ajax({
        url: SELF, type: 'POST', dataType: 'json',
        data: { ajax_action: 'scan_lookup', csrf_token: userCsrfToken, product_code: code },
        success: function (res) {
            if (res.error === 'session_expired') { window.location.href = '../index.php'; return; }
            if (res.status === 'success' && res.product) {
                const p = res.product;
                const thumbWrap = document.getElementById('scanResultThumb');
                thumbWrap.innerHTML = '';
                thumbWrap.appendChild(buildThumb(p.image, p.code, true));
                document.getElementById('scanResultCode').textContent = p.code || '';
                document.getElementById('scanResultName').textContent = p.name || 'নাম নেই';
                const meta = document.getElementById('scanResultMeta');
                meta.innerHTML = '';
                const cat = document.createElement('span');
                cat.innerHTML = '<i class="fas fa-folder"></i> ';
                cat.appendChild(document.createTextNode(p.cat || 'N/A'));
                const stock = document.createElement('span');
                stock.style.marginLeft = '10px';
                stock.innerHTML = '<i class="fas fa-cubes"></i> স্টক: ';
                stock.appendChild(document.createTextNode(String(p.stock)));
                meta.appendChild(cat);
                meta.appendChild(stock);
                resBox.classList.add('show');
            } else {
                resBox.classList.remove('show');
                errBox.textContent = res.message || 'পণ্য পাওয়া যায়নি।';
                errBox.style.display = 'block';
            }
        },
        error: function () {
            errBox.textContent = 'সার্ভার এরর! আবার চেষ্টা করুন।';
            errBox.style.display = 'block';
        }
    });
}

/* ----------------------------- Scanner -------------------------------- */
let html5Scanner = null;
let scannerRunning = false;
function openScanner() {
    document.getElementById('scannerModal').classList.add('open');
    if (!html5Scanner) html5Scanner = new Html5Qrcode('reader');
    html5Scanner.start(
        { facingMode: 'environment' },
        { fps: 15, qrbox: { width: 250, height: 130 } },
        (decodedText) => {
            if (!scannerRunning) return;
            scannerRunning = false;
            document.getElementById('scanInput').value = decodedText;
            html5Scanner.stop()
                .then(() => { document.getElementById('scannerModal').classList.remove('open'); lookupProduct(); })
                .catch(() => { document.getElementById('scannerModal').classList.remove('open'); lookupProduct(); });
        },
        () => {}
    ).then(() => { scannerRunning = true; })
     .catch(() => { alert('ক্যামেরা এক্সেস পাওয়া যায়নি!'); closeScanner(); });
}
function closeScanner() {
    if (html5Scanner && scannerRunning) {
        html5Scanner.stop()
            .then(() => { scannerRunning = false; document.getElementById('scannerModal').classList.remove('open'); })
            .catch(() => document.getElementById('scannerModal').classList.remove('open'));
    } else {
        document.getElementById('scannerModal').classList.remove('open');
    }
}

/* --------------------------- Live poll (today) ------------------------ */
function pollToday() {
    /* Only refresh the live "today" view; never disturb search or paged scrolling. */
    if (curPage !== 1 || curQ !== '' || loading) return;
    const prevMax = maxEpochSeenInFeed;
    $.ajax({
        url: SELF, type: 'POST', dataType: 'json',
        data: { ajax_action: 'load_notifications', csrf_token: userCsrfToken, page: 1, type: curType, q: '' },
        success: function (res) {
            if (!res || res.error || !Array.isArray(res.items)) return;
            if (res.stats) {
                document.getElementById('statSold').textContent  = (res.stats.soldToday || 0) + ' টি';
                document.getElementById('statAdded').textContent = (res.stats.addedToday || 0) + ' টি';
            }
            hasMore = !!res.hasMore;
            feedItems = res.items;
            let newMax = 0;
            feedItems.forEach(x => { if (x.epoch > newMax) newMax = x.epoch; });
            renderFeed();
            updateBadge();
            if (newMax > prevMax && prevMax > 0) {
                const cnt = feedItems.filter(x => x.epoch > prevMax).length;
                if (cnt > 0) { ringBell(); showToast(cnt + ' টি নতুন নোটিফিকেশন'); }
            }
            maxEpochSeenInFeed = Math.max(maxEpochSeenInFeed, newMax);
        }
    });
}

/* ------------------------------ Boot ---------------------------------- */
$(document).ready(function () {
    updateScopeLabel();
    loadFeed(false, false);
    setInterval(pollToday, 25000);
});
</script>
</body>
</html>
