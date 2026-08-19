<?php
declare(strict_types=1);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

class BusinessLogicException extends Exception {}

function aprLogError(Throwable $e): void {
    $d = __DIR__ . '/../Logs';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    @file_put_contents($d . '/error_log.txt',
        "[" . date('Y-m-d H:i:s') . "] APPROVAL: " . $e->getMessage()
        . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL,
        FILE_APPEND);
}

$isAjax = !empty($_POST['ajax_action']);

// Session timeout
$lastAct = $_SESSION['last_activity'] ?? null;
if ($lastAct !== null && is_int($lastAct) && (time() - $lastAct > 1200)) {
    session_unset(); session_destroy();
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'session_expired']); exit; }
    echo "<script>window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();

// Auth guard
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'session_expired']); exit; }
    header("Location: ../index.php"); exit;
}

$role = strtolower(trim((string)($_SESSION['role'] ?? 'user')));
if ($role !== 'admin') {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'error','message'=>'শুধু অ্যাডমিন!']); exit; }
    header("Location: inventory_dashboard.php"); exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

// DB
$dbPath = __DIR__ . '/../db_connect.php';
if (file_exists($dbPath)) require_once $dbPath;
/** @var PDO $conn */

// Audit
$auditPath = __DIR__ . '/Helpers/AuditInit.php';
if (file_exists($auditPath)) { require_once $auditPath; AuditInit::boot($conn); }

$uid      = isset($_SESSION['user_id']) && is_scalar($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$username = is_string($_SESSION['username'] ?? '') ? (string)$_SESSION['username'] : 'admin';
date_default_timezone_set('Asia/Dhaka');

// ════════════════════════════════════════════════════════════════
// NOTIFICATION CONFIG HELPERS
// ════════════════════════════════════════════════════════════════
$NOTIF_CONFIG_PATH = __DIR__ . '/notification_config.json';

function aprLoadConfig(string $path): array {
    if (!file_exists($path)) return [];
    $raw = @file_get_contents($path);
    $cfg = $raw !== false ? @json_decode($raw, true) : null;
    return is_array($cfg) ? $cfg : [];
}

function aprSaveConfig(string $path, array $cfg): bool {
    return file_put_contents($path, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function aprGetEnvVal(string $key): string {
    // /home/sadakalo/public_html/Inventory/ → /home/sadakalo/App/.env
    $path = __DIR__ . '/../../App/.env';
    if (!file_exists($path)) return '';
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === $key) return trim(trim($v), "\"'");
    }
    return '';
}

function aprNormalizeBDPhone(string $phone): string {
    $p = preg_replace('/\D/', '', $phone) ?? '';
    if (str_starts_with($p, '880')) return $p;
    if (str_starts_with($p, '0'))   return '880' . substr($p, 1);
    return '880' . $p;
}

function aprSendSms(string $phone, string $message, string $path): bool {
    try {
        $cfg = aprLoadConfig($path);
        if (empty($cfg['sms_enabled'])) return false;

        $apiKey   = aprGetEnvVal('SMS_APIKEY') ?: aprGetEnvVal('SMS_API_KEY');
        $userName = aprGetEnvVal('S_USERNAME') ?: aprGetEnvVal('SMS_USERNAME');
        $senderId = aprGetEnvVal('SMS_SENDER') ?: aprGetEnvVal('SMS_SENDER_ID');
        if (empty($apiKey) || empty($userName) || empty($senderId)) return false;

        $payload = json_encode([
            'apiKey'          => $apiKey,
            'userName'        => $userName,
            'senderName'      => $senderId,
            'transactionType' => 'T',
            'mobileNumber'    => aprNormalizeBDPhone($phone),
            'message'         => $message,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.mimsms.com/api/V2/SMS');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if ($raw === false) return false;

        $res = json_decode((string)$raw, true);
        return is_array($res) && (($res['status'] ?? '') === 'Success' || ($res['statusCode'] ?? '') === '200');
    } catch (Throwable $e) { aprLogError($e); return false; }
}

function aprSendEmail(string $to, string $subject, string $html, string $path): bool {
    try {
        $cfg = aprLoadConfig($path);
        if (empty($cfg['email_enabled'])) return false;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
        $h  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $h .= "From: Sada Kalo Inventory <inventory@sadakalo.com>\r\n";
        return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, $h);
    } catch (Throwable $e) { aprLogError($e); return false; }
}

// ════════════════════════════════════════════════════════════════
// AJAX
// ════════════════════════════════════════════════════════════════
if ($isAjax) {
    ob_clean(); header('Content-Type: application/json');

    $postCsrf = is_string($_POST['csrf_token'] ?? '') ? (string)$_POST['csrf_token'] : '';
    if ($postCsrf === '' || !hash_equals($csrfToken, $postCsrf)) {
        echo json_encode(['status'=>'error','message'=>'সিকিউরিটি টোকেন মিসম্যাচ!']); exit;
    }
    $action = trim((string)($_POST['ajax_action'] ?? ''));

    // ── অনুমোদন (approve) ────────────────────────────────────
    if ($action === 'approve_return') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new BusinessLogicException('অবৈধ আইডি!');

            $conn->beginTransaction();

            // রেকর্ড লোড করো (lock)
            $s = $conn->prepare(
                "SELECT se.*, i.id AS inv_id, i.pieces AS cur_pieces, i.buy_price AS cur_buy_price
                 FROM supplier_exchanges se
                 JOIN inventory i ON se.old_product_code = i.product_code
                 WHERE se.id = ? AND se.status = 'pending'
                 LIMIT 1 FOR UPDATE"
            );
            $s->execute([$id]);
            $rec = $s->fetch(PDO::FETCH_ASSOC);
            if (!$rec) throw new BusinessLogicException('এন্ট্রি পাওয়া যায়নি বা ইতিমধ্যে প্রসেস করা হয়েছে!');

            $retPcs  = (int)$rec['returned_pieces'];
            $curPcs  = (int)$rec['cur_pieces'];
            $invId   = (int)$rec['inv_id'];
            $excNo   = (string)$rec['exchange_no'];
            $oldCode = (string)$rec['old_product_code'];
            $excType = (string)$rec['exchange_type'];

            if ($curPcs < $retPcs) {
                throw new BusinessLogicException("স্টকে মাত্র {$curPcs} পিস! {$retPcs} পিস কমানো সম্ভব নয়!");
            }

            $message = '';

            // ══ return_only: শুধু স্টক কমানো ═══════════════════
            if ($excType === 'return_only') {
                $conn->prepare("UPDATE inventory SET pieces = pieces - ? WHERE product_code = ?")
                     ->execute([$retPcs, $oldCode]);
                $conn->prepare("INSERT INTO inventory_adjustments (user_id, product_code, adjustment_type, status, pieces, note, adjusted_by) VALUES(?,?,?,?,?,?,?)")
                     ->execute([$uid, $oldCode, 'decrease', 'approved', $retPcs,
                         "রিটার্ন অনুমোদিত [{$excNo}]: সাপ্লায়ার: {$rec['supplier_name']}", $uid]);

                if (class_exists('AuditLogger')) {
                    AuditLogger::update('inventory', $invId,
                        ['pieces' => $curPcs], ['pieces' => $curPcs - $retPcs],
                        "সাপ্লায়ার রিটার্ন অনুমোদিত [{$excNo}] by {$username}"
                    );
                }
                $message = "অনুমোদন সফল! [{$excNo}] — {$oldCode} থেকে {$retPcs} পিস স্টক বাদ হয়েছে।";
            }

            // ══ same_product: পেন্ডিং পেলোড দিয়ে স্টক আপডেট ══════
            elseif ($excType === 'same_product') {
                $payload = json_decode((string)($rec['pending_payload'] ?? ''), true) ?: [];
                $newPcs  = (int)($payload['new_pieces']    ?? 0);
                $newBuy  = (float)($payload['new_buy_price'] ?? 0);
                $newCost = (float)($payload['new_cost']    ?? 15);
                $newSell = (float)($payload['new_cash_sell'] ?? 0);
                $newImg  = (string)($payload['new_image_path'] ?? '');
                if ($newPcs <= 0 || $newBuy <= 0 || $newSell <= 0) {
                    throw new BusinessLogicException('পেন্ডিং ডাটা অসম্পূর্ণ — এই রিকোয়েস্টটি প্রসেস করা যাচ্ছে না!');
                }

                if ($newImg !== '') {
                    $conn->prepare("UPDATE inventory SET pieces=pieces-?+?,buy_price=?,cost=?,cash_sell=?,image_path=? WHERE product_code=?")
                         ->execute([$retPcs,$newPcs,$newBuy,$newCost,$newSell,$newImg,$oldCode]);
                } else {
                    $conn->prepare("UPDATE inventory SET pieces=pieces-?+?,buy_price=?,cost=?,cash_sell=? WHERE product_code=?")
                         ->execute([$retPcs,$newPcs,$newBuy,$newCost,$newSell,$oldCode]);
                }
                $delta = $newPcs - $retPcs;
                $adjType = $delta >= 0 ? 'increase' : 'decrease';
                $conn->prepare("INSERT INTO inventory_adjustments (user_id, product_code, adjustment_type, status, pieces, note, adjusted_by) VALUES(?,?,?,?,?,?,?)")
                     ->execute([$uid, $oldCode, $adjType, 'approved', abs($delta),
                         "সাপ্লায়ার এক্সচেঞ্জ অনুমোদিত [{$excNo}]: {$retPcs} পিস ফেরত → {$newPcs} পিস নতুন", $uid]);

                if (class_exists('AuditLogger')) {
                    AuditLogger::update('inventory', $invId,
                        ['pieces' => $curPcs, 'buy_price' => (float)$rec['cur_buy_price']],
                        ['pieces' => $curPcs - $retPcs + $newPcs, 'buy_price' => $newBuy],
                        "সাপ্লায়ার এক্সচেঞ্জ অনুমোদন [{$excNo}] by {$username}"
                    );
                }
                $message = "এক্সচেঞ্জ অনুমোদিত! [{$excNo}] — {$retPcs} পিস ফেরত, {$newPcs} পিস নতুন স্টকে যোগ হয়েছে।";
            }

            // ══ new_product: পেন্ডিং পেলোড দিয়ে নতুন পণ্য তৈরি ═══
            elseif ($excType === 'new_product') {
                $payload = json_decode((string)($rec['pending_payload'] ?? ''), true) ?: [];
                $newCode = (string)($payload['new_product_code'] ?? '');
                $newCat  = (int)($payload['new_category_id']    ?? 0);
                $newName = (string)($payload['new_category_name'] ?? '');
                $newPcs  = (int)($payload['new_pieces']    ?? 0);
                $newBuy  = (float)($payload['new_buy_price'] ?? 0);
                $newCost = (float)($payload['new_cost']    ?? 15);
                $newSell = (float)($payload['new_cash_sell'] ?? 0);
                $newImg  = (string)($payload['new_image_path'] ?? '');

                if ($newCode === '' || $newCat <= 0 || $newPcs <= 0 || $newBuy <= 0 || $newSell <= 0) {
                    throw new BusinessLogicException('পেন্ডিং ডাটা অসম্পূর্ণ — এই রিকোয়েস্টটি প্রসেস করা যাচ্ছে না!');
                }

                $dc = $conn->prepare("SELECT id FROM inventory WHERE product_code=? LIMIT 1");
                $dc->execute([$newCode]);
                if ($dc->rowCount() > 0) {
                    throw new BusinessLogicException("'{$newCode}' কোডটি ইতিমধ্যে অন্য পণ্যে ব্যবহৃত হয়ে গেছে — রিকোয়েস্টকারীকে নতুন কোড দিয়ে আবার পাঠাতে বলুন!");
                }

                $conn->prepare("UPDATE inventory SET pieces = pieces - ? WHERE product_code = ?")
                     ->execute([$retPcs, $oldCode]);
                $conn->prepare("INSERT INTO inventory (product_code,category_id,name,image_path,pieces,buy_price,cost,cash_sell,added_by) VALUES(?,?,?,?,?,?,?,?,?)")
                     ->execute([$newCode,$newCat,$newName,$newImg,$newPcs,$newBuy,$newCost,$newSell,$uid]);
                $newProdDbId = (int)$conn->lastInsertId();

                $conn->prepare("INSERT INTO inventory_adjustments (user_id, product_code, adjustment_type, status, pieces, note, adjusted_by) VALUES(?,?,?,?,?,?,?)")
                     ->execute([$uid, $oldCode, 'decrease', 'approved', $retPcs, "সাপ্লায়ার এক্সচেঞ্জ অনুমোদিত [{$excNo}]: ফেরত → নতুন {$newCode}", $uid]);
                $conn->prepare("INSERT INTO inventory_adjustments (user_id, product_code, adjustment_type, status, pieces, note, adjusted_by) VALUES(?,?,?,?,?,?,?)")
                     ->execute([$uid, $newCode, 'increase', 'approved', $newPcs, "সাপ্লায়ার এক্সচেঞ্জ অনুমোদিত [{$excNo}]: {$oldCode} থেকে প্রাপ্ত", $uid]);

                if (class_exists('AuditLogger')) {
                    AuditLogger::update('inventory', $invId,
                        ['pieces' => $curPcs], ['pieces' => $curPcs - $retPcs],
                        "সাপ্লায়ার এক্সচেঞ্জ অনুমোদন [{$excNo}]: স্টক আপডেট"
                    );
                    AuditLogger::create('inventory', $newProdDbId, null,
                        ['product_code'=>$newCode,'pieces'=>$newPcs,'buy_price'=>$newBuy],
                        "সাপ্লায়ার এক্সচেঞ্জে নতুন পণ্য অনুমোদিত [{$excNo}]"
                    );
                }
                $message = "নতুন পণ্য [{$newCode}] অনুমোদিত হয়ে যোগ হয়েছে! [{$excNo}]";
            } else {
                throw new BusinessLogicException('অজানা এক্সচেঞ্জ টাইপ!');
            }

            // status আপডেট (সব টাইপের জন্য কমন)
            $conn->prepare("UPDATE supplier_exchanges SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                 ->execute([$uid, $id]);

            $conn->commit();

            if (class_exists('AuditLogger')) {
                AuditLogger::update('supplier_exchanges', $id,
                    ['status' => 'pending'],
                    ['status' => 'approved', 'reviewed_by' => $uid],
                    "এক্সচেঞ্জ অনুমোদন"
                );
            }

            // অনুমোদনকারীর SMS (ঐচ্ছিক — নোটিফিকেশন কনফিগ থেকে)
            $cfg    = aprLoadConfig($NOTIF_CONFIG_PATH);
            $sPhone = $cfg['admin_phone'] ?? '';
            if ($sPhone) {
                aprSendSms($sPhone,
                    "[SADA KALO] এক্সচেঞ্জ অনুমোদিত!\nনং: {$excNo}\nপণ্য: {$oldCode}",
                    $NOTIF_CONFIG_PATH);
            }

            echo json_encode(['status' => 'success', 'message' => $message]); exit;

        } catch (BusinessLogicException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            aprLogError($e);
            echo json_encode(['status'=>'error','message'=>'সার্ভার ত্রুটি!']); exit;
        }
    }

    // ── বাতিল (cancel) ───────────────────────────────────────
    if ($action === 'cancel_return') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new BusinessLogicException('অবৈধ আইডি!');

            $s = $conn->prepare("SELECT * FROM supplier_exchanges WHERE id=? AND status='pending' LIMIT 1");
            $s->execute([$id]);
            $rec = $s->fetch(PDO::FETCH_ASSOC);
            if (!$rec) throw new BusinessLogicException('এন্ট্রি পাওয়া যায়নি বা ইতিমধ্যে প্রসেস করা হয়েছে!');

            $conn->prepare("UPDATE supplier_exchanges SET status='cancelled', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                 ->execute([$uid, $id]);

            // pending adjustment লগ মুছে ফেলো
            $conn->prepare("DELETE FROM inventory_adjustments WHERE product_code=? AND note LIKE ? AND adjustment_type='decrease' AND status='pending'")
                 ->execute([$rec['old_product_code'], "%[{$rec['exchange_no']}]%"]);

            if (class_exists('AuditLogger')) {
                AuditLogger::update('supplier_exchanges', $id,
                    ['status' => 'pending'],
                    ['status' => 'cancelled', 'reviewed_by' => $uid],
                    "রিটার্ন বাতিল by {$username}"
                );
            }

            echo json_encode([
                'status'  => 'success',
                'message' => "রিটার্ন বাতিল করা হয়েছে। [{$rec['exchange_no']}] — স্টকে কোনো পরিবর্তন হয়নি।",
            ]); exit;

        } catch (BusinessLogicException $e) {
            echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) {
            aprLogError($e);
            echo json_encode(['status'=>'error','message'=>'সার্ভার ত্রুটি!']); exit;
        }
    }

    // ── হিস্টরি পেজিনেশন ────────────────────────────────────────
    if ($action === 'load_hist_page') {
        try {
            $pg  = max(1, (int)($_POST['page'] ?? 1));
            $per = 20;
            $off = ($pg - 1) * $per;
            $tot = (int)$conn->query("SELECT COUNT(*) FROM supplier_exchanges")->fetchColumn();
            $s   = $conn->prepare(
                "SELECT se.*, u.username AS by_name, rv.username AS reviewer_name
                 FROM supplier_exchanges se
                 LEFT JOIN users u  ON se.exchanged_by = u.id
                 LEFT JOIN users rv ON se.reviewed_by  = rv.id
                 ORDER BY se.id DESC LIMIT {$per} OFFSET {$off}"
            );
            $s->execute();
            echo json_encode([
                'status' => 'success',
                'rows'   => $s->fetchAll(PDO::FETCH_ASSOC),
                'total'  => $tot,
                'page'   => $pg,
                'pages'  => max(1, (int)ceil($tot / $per)),
            ]); exit;
        } catch (Throwable $e) {
            aprLogError($e);
            echo json_encode(['status'=>'error','message'=>'লোড ব্যর্থ']); exit;
        }
    }

    // ── নোটিফিকেশন কনফিগ আপডেট ─────────────────────────────
    if ($action === 'update_notif_config') {
        try {
            $cfg = aprLoadConfig($NOTIF_CONFIG_PATH);

            $cfg['sms_enabled']       = !empty($_POST['sms_enabled']);
            $cfg['email_enabled']     = !empty($_POST['email_enabled']);
            $cfg['admin_phone']       = trim((string)($_POST['admin_phone']       ?? ''));
            $cfg['admin_email']       = trim((string)($_POST['admin_email']       ?? ''));
            $cfg['approval_base_url'] = trim((string)($_POST['approval_base_url'] ?? ''));

            if (!empty($cfg['admin_email']) && !filter_var($cfg['admin_email'], FILTER_VALIDATE_EMAIL)) {
                throw new BusinessLogicException('ইমেইল ঠিকানাটি সঠিক নয়!');
            }

            if (!aprSaveConfig($NOTIF_CONFIG_PATH, $cfg)) {
                throw new BusinessLogicException('কনফিগ ফাইল সেভ করা যায়নি! ফাইল permission চেক করুন।');
            }

            echo json_encode(['status'=>'success','message'=>'নোটিফিকেশন সেটিংস সেভ হয়েছে!']); exit;

        } catch (BusinessLogicException $e) {
            echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) {
            aprLogError($e);
            echo json_encode(['status'=>'error','message'=>'সার্ভার ত্রুটি!']); exit;
        }
    }

    echo json_encode(['status'=>'error','message'=>'অজানা অ্যাকশন!']); exit;
}

// ════════════════════════════════════════════════════════════════
// PAGE DATA
// ════════════════════════════════════════════════════════════════
$pendingList = [];
try {
    $pendingList = $conn->query(
        "SELECT se.*, u.username AS by_name,
                i.pieces AS cur_pieces, i.name AS product_name_live
         FROM supplier_exchanges se
         LEFT JOIN users u ON se.exchanged_by = u.id
         LEFT JOIN inventory i ON se.old_product_code = i.product_code
         WHERE se.status = 'pending'
         ORDER BY se.id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { aprLogError($e); }

$allHistory = [];
$histTotal  = 0;
$histPages  = 1;
try {
    $histTotal = (int)$conn->query("SELECT COUNT(*) FROM supplier_exchanges")->fetchColumn();
    $histPages = max(1, (int)ceil($histTotal / 20));
    $allHistory = $conn->query(
        "SELECT se.*, u.username AS by_name, rv.username AS reviewer_name
         FROM supplier_exchanges se
         LEFT JOIN users u  ON se.exchanged_by = u.id
         LEFT JOIN users rv ON se.reviewed_by  = rv.id
         ORDER BY se.id DESC LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$notifCfg    = aprLoadConfig($NOTIF_CONFIG_PATH);
$csrf        = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
$pendingCount= count($pendingList);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>রিটার্ন অনুমোদন — SADA KALO</title>
<meta name="theme-color" content="#ffffff">
<script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link  href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link  rel="stylesheet" href="theme.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script defer src="theme-toggle.js"></script>
<style>
/* ── Approval card ── */
.apr-card {
    border-radius:.875rem; overflow:hidden;
    border:1.5px solid var(--sk-line);
    margin-bottom:.875rem;
    background:var(--sk-surface);
}
.apr-card-head {
    background:var(--sk-surface-2);
    padding:.75rem .875rem;
    border-bottom:1px solid var(--sk-line);
    display:flex; align-items:center; gap:.625rem;
}
.apr-exc-no {
    font-size:.72rem; font-weight:900;
    color:var(--sk-accent); letter-spacing:.04em;
}
.apr-date { font-size:.65rem; color:var(--sk-muted); font-weight:600; margin-left:auto; }
.apr-body { padding:.875rem; }
.apr-row  { display:flex; align-items:center; gap:.5rem; margin-bottom:.4rem; font-size:.8rem; }
.apr-lbl  { color:var(--sk-muted); font-weight:600; min-width:85px; flex-shrink:0; }
.apr-val  { font-weight:700; color:var(--sk-ink); }
.apr-val.danger  { color:var(--sk-danger); }
.apr-val.warning { color:#f59e0b; }
.apr-val.success { color:var(--sk-success); }
.apr-stock-warn {
    background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.3);
    border-radius:.5rem; padding:.4rem .75rem;
    font-size:.72rem; font-weight:700; color:#92400e;
    margin-bottom:.625rem; display:flex; align-items:center; gap:.4rem;
}
.apr-btn-row { display:flex; gap:.5rem; margin-top:.75rem; }
.apr-btn-approve {
    flex:1; padding:.7rem; border-radius:.625rem;
    background:var(--sk-success); color:#fff;
    border:none; font-size:.82rem; font-weight:800;
    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:.4rem;
    -webkit-tap-highlight-color:transparent;
}
.apr-btn-cancel {
    flex:1; padding:.7rem; border-radius:.625rem;
    background:var(--sk-danger-soft); color:var(--sk-danger);
    border:1.5px solid var(--sk-danger); font-size:.82rem; font-weight:800;
    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:.4rem;
    -webkit-tap-highlight-color:transparent;
}
.apr-btn-approve:active { opacity:.85; }
.apr-btn-cancel:active  { opacity:.85; }
/* Toggle switch */
.notif-toggle-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:.625rem 0; border-bottom:1px solid var(--sk-line);
}
.notif-toggle-row:last-child { border-bottom:none; }
.notif-toggle-lbl { font-size:.82rem; font-weight:700; color:var(--sk-ink); }
.notif-toggle-sub { font-size:.68rem; color:var(--sk-muted); font-weight:600; }
.sk-toggle {
    position:relative; width:44px; height:24px; flex-shrink:0;
}
.sk-toggle input { opacity:0; width:0; height:0; }
.sk-toggle-slider {
    position:absolute; cursor:pointer; inset:0;
    background:var(--sk-line); border-radius:999px;
    transition:.2s;
}
.sk-toggle-slider::before {
    content:''; position:absolute;
    width:18px; height:18px; border-radius:50%;
    background:#fff; left:3px; top:3px; transition:.2s;
    box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.sk-toggle input:checked + .sk-toggle-slider { background:var(--sk-success); }
.sk-toggle input:checked + .sk-toggle-slider::before { transform:translateX(20px); }
/* History list */
.hist-row {
    display:flex; align-items:flex-start; gap:.625rem;
    padding:.625rem 0; border-bottom:1px solid var(--sk-line);
}
.hist-row:last-child { border-bottom:none; }
.hist-badge {
    display:inline-block; padding:.15rem .5rem;
    border-radius:.375rem; font-size:.62rem; font-weight:800;
    text-transform:uppercase; white-space:nowrap; flex-shrink:0;
}
.hist-badge.approved  { background:rgba(16,185,129,.12); color:var(--sk-success); }
.hist-badge.pending   { background:rgba(245,158,11,.15);  color:#92400e; }
.hist-badge.cancelled { background:rgba(107,114,128,.12); color:#6b7280; }
/* Empty state */
.apr-empty {
    text-align:center; padding:3rem 1rem; color:var(--sk-muted);
}
.apr-empty i { font-size:2.5rem; display:block; margin-bottom:.75rem; opacity:.25; }
.apr-empty div { font-size:.88rem; font-weight:600; }
body { padding-bottom:80px!important; }
</style>
</head>
<body>

<!-- AppBar -->
<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button class="sk-iconbtn" onclick="skToggleDrawer()"><i class="fas fa-bars"></i></button>
        <a href="supplier_exchange.php" class="sk-iconbtn"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title">
        রিটার্ন অনুমোদন
        <?php if ($pendingCount > 0): ?>
        <span style="background:var(--sk-danger);color:#fff;border-radius:999px;font-size:.65rem;padding:.15rem .5rem;font-weight:900;margin-left:.4rem;">
            <?= $pendingCount ?>
        </span>
        <?php endif; ?>
    </div>
    <div class="sk-appbar__right">
        <button class="sk-iconbtn" onclick="skToggleTheme()"><i class="fas fa-circle-half-stroke"></i></button>
        <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<div class="sk-overlay" id="skOverlay" onclick="skToggleDrawer()"></div>
<aside class="sk-drawer" id="skDrawer">
    <div class="sk-drawer__head">
        <button class="sk-drawer__close" onclick="skToggleDrawer()"><i class="fas fa-times"></i></button>
        <img src="logo.png" onerror="this.style.display='none'" class="sk-drawer__logo" alt="">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">FASHION</div>
    </div>
    <div class="sk-drawer__section">ইনভেন্টরি</div>
    <div class="sk-drawer__grid">
        <a href="inventory_dashboard.php"    class="sk-drawer__item"><i class="fas fa-chart-pie"></i><span>ড্যাশবোর্ড</span></a>
        <a href="inventory_pos.php"           class="sk-drawer__item"><i class="fas fa-cash-register"></i><span>POS</span></a>
        <a href="return_product.php"          class="sk-drawer__item"><i class="fas fa-undo"></i><span>কাস্টমার রিটার্ন</span></a>
        <a href="supplier_exchange.php"       class="sk-drawer__item"><i class="fas fa-exchange-alt"></i><span>সাপ্লায়ার এক্সচেঞ্জ</span></a>
        <a href="supplier_return_approval.php" class="sk-drawer__item on"><i class="fas fa-tasks"></i><span>রিটার্ন অনুমোদন</span></a>
        <a href="admin_inventory_control.php" class="sk-drawer__item"><i class="fas fa-shield-alt"></i><span>মাস্টার কন্ট্রোল</span></a>
        <a href="Audit_log.php"               class="sk-drawer__item"><i class="fas fa-clipboard-list"></i><span>অডিট লগ</span></a>
    </div>
</aside>

<!-- Page Content -->
<div class="sk-page-content">

    <!-- Title -->
    <div style="text-align:center;padding:.5rem 0 1rem;">
        <div style="font-size:1.4rem;font-weight:900;color:var(--sk-ink);letter-spacing:-.02em;">
            <i class="fas fa-tasks" style="color:var(--sk-accent);margin-right:.4rem;"></i>রিটার্ন অনুমোদন
        </div>
        <div style="font-size:.73rem;color:var(--sk-muted);font-weight:600;margin-top:.2rem;">
            সাপ্লায়ার এক্সচেঞ্জ/রিটার্ন রিকোয়েস্ট অনুমোদন বা বাতিল করুন
        </div>
    </div>

    <!-- ══ পেন্ডিং তালিকা ══════════════════════════════ -->
    <?php if (empty($pendingList)): ?>
    <div class="sk-card">
        <div class="apr-empty">
            <i class="fas fa-check-circle" style="color:var(--sk-success);"></i>
            <div>কোনো পেন্ডিং রিকোয়েস্ট নেই</div>
            <div style="font-size:.72rem;margin-top:.3rem;">সব রিকোয়েস্ট প্রসেস হয়ে গেছে</div>
        </div>
    </div>

    <?php else: ?>

    <div style="font-size:.78rem;font-weight:800;color:var(--sk-danger);margin-bottom:.625rem;display:flex;align-items:center;gap:.4rem;">
        <i class="fas fa-clock"></i> <?= $pendingCount ?> টি রিকোয়েস্ট অনুমোদনের অপেক্ষায়
    </div>

    <?php foreach ($pendingList as $p):
        $retPcs   = (int)$p['returned_pieces'];
        $curPcs   = (int)$p['cur_pieces'];
        $buyVal   = number_format($retPcs * (float)$p['old_buy_price']);
        $isLow    = $curPcs < $retPcs;
        $pType    = (string)$p['exchange_type'];
        $payload  = $pType !== 'return_only' ? (json_decode((string)($p['pending_payload'] ?? ''), true) ?: []) : [];
        $typeInfo = match($pType) {
            'same_product' => ['icon'=>'fa-rotate','color'=>'#6366f1','label'=>'একই পণ্য আপডেট'],
            'new_product'  => ['icon'=>'fa-box-open','color'=>'#6366f1','label'=>'নতুন পণ্য'],
            default        => ['icon'=>'fa-arrow-circle-left','color'=>'#f59e0b','label'=>'শুধু ফেরত'],
        };
    ?>
    <div class="apr-card" id="apr_<?= (int)$p['id'] ?>">
        <div class="apr-card-head">
            <i class="fas <?= $typeInfo['icon'] ?>" style="color:<?= $typeInfo['color'] ?>;font-size:.9rem;"></i>
            <div>
                <div class="apr-exc-no"><?= htmlspecialchars((string)$p['exchange_no'],ENT_QUOTES,'UTF-8') ?></div>
                <div style="font-size:.62rem;font-weight:800;color:<?= $typeInfo['color'] ?>;"><?= $typeInfo['label'] ?></div>
            </div>
            <div class="apr-date"><?= date('d M Y', strtotime((string)$p['created_at'])) ?></div>
        </div>

        <div class="apr-body">
            <?php if ($isLow): ?>
            <div class="apr-stock-warn">
                <i class="fas fa-exclamation-triangle"></i>
                স্টকে মাত্র <?= $curPcs ?> পিস — ফেরত পিস <?= $retPcs ?> এর বেশি!
            </div>
            <?php endif; ?>

            <div class="apr-row">
                <span class="apr-lbl"><i class="fas fa-barcode" style="width:14px;"></i> পুরানো পণ্য</span>
                <span class="apr-val"><?= htmlspecialchars((string)$p['old_product_code'],ENT_QUOTES,'UTF-8') ?>
                    <span style="font-weight:500;color:var(--sk-muted);font-size:.72rem;">
                        — <?= htmlspecialchars((string)$p['old_product_name'],ENT_QUOTES,'UTF-8') ?>
                    </span>
                </span>
            </div>
            <?php if ($pType === 'return_only'): ?>
            <div class="apr-row">
                <span class="apr-lbl"><i class="fas fa-store" style="width:14px;"></i> সাপ্লায়ার</span>
                <span class="apr-val warning"><?= htmlspecialchars((string)$p['supplier_name'],ENT_QUOTES,'UTF-8') ?></span>
            </div>
            <?php endif; ?>
            <div class="apr-row">
                <span class="apr-lbl"><i class="fas fa-cubes" style="width:14px;"></i> ফেরত পিস</span>
                <span class="apr-val danger"><?= $retPcs ?> পিস</span>
                <span style="font-size:.68rem;color:var(--sk-muted);font-weight:600;">
                    (স্টকে এখন <?= $curPcs ?> পিস)
                </span>
            </div>
            <?php if ($pType === 'return_only'): ?>
            <div class="apr-row">
                <span class="apr-lbl"><i class="fas fa-taka-sign" style="width:14px;"></i> আনুমানিক</span>
                <span class="apr-val">৳<?= $buyVal ?></span>
            </div>
            <?php else: ?>
            <div class="apr-row">
                <span class="apr-lbl"><i class="fas fa-plus-circle" style="width:14px;"></i> নতুন পিস</span>
                <span class="apr-val success"><?= (int)($payload['new_pieces'] ?? 0) ?> পিস</span>
            </div>
            <div class="apr-row">
                <span class="apr-lbl"><i class="fas fa-taka-sign" style="width:14px;"></i> ক্রয়/বিক্রয়</span>
                <span class="apr-val">৳<?= number_format((float)($payload['new_buy_price'] ?? 0)) ?> / ৳<?= number_format((float)($payload['new_cash_sell'] ?? 0)) ?></span>
            </div>
            <?php if ($pType === 'new_product'): ?>
            <div class="apr-row">
                <span class="apr-lbl"><i class="fas fa-tag" style="width:14px;"></i> নতুন কোড</span>
                <span class="apr-val"><?= htmlspecialchars((string)($payload['new_product_code'] ?? '—'),ENT_QUOTES,'UTF-8') ?></span>
            </div>
            <?php endif; ?>
            <div class="apr-row">
                <span class="apr-lbl"><i class="fas fa-tags" style="width:14px;"></i> ক্যাটাগরি</span>
                <span class="apr-val" style="font-size:.75rem;"><?= htmlspecialchars((string)($payload['new_category_name'] ?? '—'),ENT_QUOTES,'UTF-8') ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($p['note'])): ?>
            <div class="apr-row">
                <span class="apr-lbl"><i class="fas fa-sticky-note" style="width:14px;"></i> নোট</span>
                <span class="apr-val" style="font-size:.75rem;font-weight:500;">
                    <?= htmlspecialchars((string)$p['note'],ENT_QUOTES,'UTF-8') ?>
                </span>
            </div>
            <?php endif; ?>
            <div class="apr-row">
                <span class="apr-lbl"><i class="fas fa-user" style="width:14px;"></i> জমাকারী</span>
                <span class="apr-val" style="font-size:.75rem;">
                    <?= htmlspecialchars((string)($p['by_name'] ?? 'admin'),ENT_QUOTES,'UTF-8') ?>
                    · <?= date('h:i A', strtotime((string)$p['created_at'])) ?>
                </span>
            </div>

            <div class="apr-btn-row">
                <button class="apr-btn-approve" onclick="doApprove(<?= (int)$p['id'] ?>,'<?= htmlspecialchars((string)$p['exchange_no'],ENT_QUOTES,'UTF-8') ?>',<?= $retPcs ?>,'<?= htmlspecialchars((string)$p['old_product_code'],ENT_QUOTES,'UTF-8') ?>','<?= $pType ?>')">
                    <i class="fas fa-check"></i> অনুমোদন করুন
                </button>
                <button class="apr-btn-cancel" onclick="doCancel(<?= (int)$p['id'] ?>,'<?= htmlspecialchars((string)$p['exchange_no'],ENT_QUOTES,'UTF-8') ?>')">
                    <i class="fas fa-times"></i> বাতিল করুন
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- ══ নোটিফিকেশন সেটিংস → আলাদা পেজে ════════════════════ -->
    <div class="sk-card" style="margin-bottom:.875rem;">
        <a href="notification_settings.php" style="display:flex;align-items:center;justify-content:space-between;text-decoration:none;color:var(--sk-ink);">
            <div style="font-size:.85rem;font-weight:800;">
                <i class="fas fa-bell" style="color:var(--sk-accent);margin-right:.4rem;"></i>নোটিফিকেশন সেটিংস
            </div>
            <i class="fas fa-chevron-right" style="color:var(--sk-muted);font-size:.8rem;"></i>
        </a>
    </div>

    <!-- ══ হিস্টরি — paginated compact table ════════════════════ -->
    <div class="sk-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem;">
            <div style="font-size:.85rem;font-weight:800;color:var(--sk-ink);">
                <i class="fas fa-history" style="color:var(--sk-accent);margin-right:.35rem;"></i>এক্সচেঞ্জ হিস্টরি
                <span class="hist-badge approved" style="margin-left:.35rem;" id="aprHistTotal"><?= $histTotal ?></span>
            </div>
            <a href="supplier_exchange_report.php" style="font-size:.7rem;font-weight:700;color:var(--sk-accent);text-decoration:none;">
                <i class="fas fa-chart-bar"></i> পূর্ণ রিপোর্ট
            </a>
        </div>
        <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <table style="width:100%;border-collapse:collapse;font-size:.72rem;" id="aprHistTable">
            <thead>
                <tr style="background:#f8f8fc;border-bottom:2px solid var(--sk-border);">
                    <th style="padding:.4rem .35rem;text-align:left;font-weight:800;color:var(--sk-muted);white-space:nowrap;">নং</th>
                    <th style="padding:.4rem .35rem;text-align:left;font-weight:800;color:var(--sk-muted);">পণ্য</th>
                    <th style="padding:.4rem .35rem;text-align:center;font-weight:800;color:var(--sk-muted);">ধরন</th>
                    <th style="padding:.4rem .35rem;text-align:center;font-weight:800;color:var(--sk-muted);">ফেরত</th>
                    <th style="padding:.4rem .35rem;text-align:center;font-weight:800;color:var(--sk-muted);">নতুন</th>
                    <th style="padding:.4rem .35rem;text-align:center;font-weight:800;color:var(--sk-muted);">স্ট্যাটাস</th>
                    <th style="padding:.4rem .35rem;text-align:right;font-weight:800;color:var(--sk-muted);white-space:nowrap;">তারিখ</th>
                </tr>
            </thead>
            <tbody id="aprHistBody">
            <?php foreach ($allHistory as $h):
                $st      = $h['status'] ?? 'pending';
                $stCls   = match($st){ 'approved'=>'approved','cancelled'=>'cancelled',default=>'pending' };
                $stLbl   = match($st){ 'approved'=>'অনুমোদিত','cancelled'=>'বাতিল',default=>'পেন্ডিং' };
                $ht      = (string)($h['exchange_type'] ?? 'return_only');
                $htLbl   = match($ht){ 'same_product'=>'একই পণ্য','new_product'=>'নতুন পণ্য',default=>'ফেরত' };
                $htCls   = match($ht){ 'same_product'=>'pending','new_product'=>'approved',default=>'' };
            ?>
            <tr style="border-bottom:1px solid var(--sk-border);">
                <td style="padding:.4rem .35rem;font-weight:800;color:var(--sk-accent);white-space:nowrap;"><?= htmlspecialchars((string)$h['exchange_no'],ENT_QUOTES,'UTF-8') ?></td>
                <td style="padding:.4rem .35rem;max-width:110px;">
                    <div style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars((string)$h['old_product_code'],ENT_QUOTES,'UTF-8') ?></div>
                    <?php if (!empty($h['by_name'])): ?><div style="font-size:.64rem;color:var(--sk-muted);"><?= htmlspecialchars((string)$h['by_name'],ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
                </td>
                <td style="padding:.4rem .35rem;text-align:center;"><span class="hist-badge <?= $htCls ?>"><?= $htLbl ?></span></td>
                <td style="padding:.4rem .35rem;text-align:center;color:var(--sk-danger);font-weight:800;">−<?= (int)$h['returned_pieces'] ?></td>
                <td style="padding:.4rem .35rem;text-align:center;color:var(--sk-success);font-weight:800;"><?= (int)($h['received_pieces']??0) > 0 ? '+' . (int)$h['received_pieces'] : '—' ?></td>
                <td style="padding:.4rem .35rem;text-align:center;"><span class="hist-badge <?= $stCls ?>"><?= $stLbl ?></span></td>
                <td style="padding:.4rem .35rem;text-align:right;color:var(--sk-muted);white-space:nowrap;"><?= date('d/m/y', strtotime((string)$h['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($allHistory)): ?>
            <tr><td colspan="7" style="padding:1.5rem;text-align:center;color:var(--sk-muted);font-weight:600;">কোনো রেকর্ড নেই</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
        <!-- Pagination -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:.65rem;">
            <button class="apr-btn-cancel" style="padding:.3rem .7rem;font-size:.72rem;border-radius:8px;" id="aprHistPrev" onclick="aprHistGo(aprHistCurPage-1)" disabled>
                <i class="fas fa-chevron-left"></i>
            </button>
            <span id="aprHistPageInfo" style="font-size:.72rem;font-weight:700;color:var(--sk-muted);">পেজ ১ / <?= $histPages ?></span>
            <button class="apr-btn-cancel" style="padding:.3rem .7rem;font-size:.72rem;border-radius:8px;" id="aprHistNext" onclick="aprHistGo(aprHistCurPage+1)" <?= $histPages <= 1 ? 'disabled' : '' ?>>
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

</div><!-- /.sk-page-content -->

<script>
const CSRF = '<?= $csrf ?>';
const PAGE = 'supplier_return_approval.php';

function skToggleDrawer() {
    document.getElementById('skDrawer').classList.toggle('open');
    document.getElementById('skOverlay').classList.toggle('active');
}

// ── অনুমোদন ──────────────────────────────────────────────────
function doApprove(id, excNo, retPcs, oldCode, excType) {
    let actionTxt = {
        return_only:  `পণ্য <b>${oldCode}</b> থেকে <b>${retPcs} পিস</b> স্টক বাদ হবে।`,
        same_product: `পণ্য <b>${oldCode}</b>-এ <b>${retPcs} পিস</b> ফেরত নিয়ে নতুন পিস/দাম আপডেট হবে।`,
        new_product:  `পণ্য <b>${oldCode}</b> থেকে <b>${retPcs} পিস</b> কমে নতুন পণ্য ইনভেন্টরিতে যোগ হবে।`,
    }[excType] || `এক্সচেঞ্জ <b>${excNo}</b> কার্যকর হবে।`;
    Swal.fire({
        icon: 'question',
        title: 'অনুমোদন করবেন?',
        html: `এক্সচেঞ্জ <b>${excNo}</b><br>
               ${actionTxt}<br>
               <span style="color:#dc3545;font-size:.85em;">এই কাজ ফেরত নেওয়া যাবে না!</span>`,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> হ্যাঁ, অনুমোদন দিন',
        cancelButtonText:  'না, বাতিল করুন',
        confirmButtonColor: '#059669',
        cancelButtonColor:  '#6b7280',
        reverseButtons: true,
    }).then(function(res) {
        if (!res.isConfirmed) return;
        let card = document.getElementById('apr_' + id);
        let btns = card ? card.querySelectorAll('button') : [];
        btns.forEach(b => b.disabled = true);
        $.ajax({
            url: PAGE, type: 'POST', dataType: 'json',
            data: { ajax_action: 'approve_return', csrf_token: CSRF, id: id },
            success: function(r) {
                if (r.status === 'session_expired') { location.href='../index.php'; return; }
                if (r.status === 'success') {
                    Swal.fire({ icon:'success', title:'অনুমোদিত!', text:r.message, confirmButtonColor:'#059669' })
                        .then(() => location.reload());
                } else {
                    btns.forEach(b => b.disabled = false);
                    Swal.fire({ icon:'error', title:'সমস্যা', text:r.message, confirmButtonColor:'#dc3545' });
                }
            },
            error: function() {
                btns.forEach(b => b.disabled = false);
                Swal.fire({ icon:'error', title:'সার্ভার এরর', text:'সংযোগ ব্যর্থ!', confirmButtonColor:'#dc3545' });
            }
        });
    });
}

// ── বাতিল ────────────────────────────────────────────────────
function doCancel(id, excNo) {
    Swal.fire({
        icon: 'warning',
        title: 'বাতিল করবেন?',
        html: `এক্সচেঞ্জ <b>${excNo}</b> বাতিল হবে।<br>
               <span style="color:#6b7280;font-size:.85em;">স্টকে কোনো পরিবর্তন হবে না।</span>`,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-times"></i> হ্যাঁ, বাতিল করুন',
        cancelButtonText:  'না, ফিরে যান',
        confirmButtonColor: '#dc3545',
        cancelButtonColor:  '#6b7280',
        reverseButtons: true,
    }).then(function(res) {
        if (!res.isConfirmed) return;
        $.ajax({
            url: PAGE, type: 'POST', dataType: 'json',
            data: { ajax_action: 'cancel_return', csrf_token: CSRF, id: id },
            success: function(r) {
                if (r.status === 'session_expired') { location.href='../index.php'; return; }
                if (r.status === 'success') {
                    Swal.fire({ icon:'success', title:'বাতিল হয়েছে!', text:r.message, confirmButtonColor:'#6b7280' })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon:'error', title:'সমস্যা', text:r.message, confirmButtonColor:'#dc3545' });
                }
            },
            error: function() { Swal.fire({ icon:'error', title:'সার্ভার এরর', text:'সংযোগ ব্যর্থ!', confirmButtonColor:'#dc3545' }); }
        });
    });
}

// ── হিস্টরি পেজিনেশন ───────────────────────────────────────────
let aprHistCurPage = 1;
const APR_HIST_TOTAL_PAGES = <?= $histPages ?>;

function aprHistGo(p) {
    if (p < 1 || p > APR_HIST_TOTAL_PAGES) return;
    aprHistCurPage = p;
    $.post(PAGE, {ajax_action:'load_hist_page', csrf_token:CSRF, page:p}, function(r) {
        if (r.status !== 'success') return;
        let rows = r.rows, html = '';
        if (!rows.length) {
            html = '<tr><td colspan="7" style="padding:1.5rem;text-align:center;color:var(--sk-muted);font-weight:600;">কোনো রেকর্ড নেই</td></tr>';
        } else {
            rows.forEach(h => {
                let stLbl = {approved:'অনুমোদিত',cancelled:'বাতিল',pending:'পেন্ডিং'}[h.status]??h.status;
                let stCls = {approved:'approved',cancelled:'cancelled',pending:'pending'}[h.status]??'';
                let htLbl = {same_product:'একই পণ্য',new_product:'নতুন পণ্য',return_only:'ফেরত'}[h.exchange_type]??'—';
                let htCls = {same_product:'pending',new_product:'approved'}[h.exchange_type]??'';
                let by    = h.by_name ? `<div style="font-size:.64rem;color:var(--sk-muted);">${h.by_name}</div>` : '';
                let newP  = parseInt(h.received_pieces||0) > 0 ? `+${h.received_pieces}` : '—';
                let dt    = h.created_at ? h.created_at.substring(2,10).split('-').reverse().join('/') : '';
                html += `<tr style="border-bottom:1px solid var(--sk-border);">
                    <td style="padding:.4rem .35rem;font-weight:800;color:var(--sk-accent);white-space:nowrap;">${h.exchange_no}</td>
                    <td style="padding:.4rem .35rem;max-width:110px;">
                        <div style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${h.old_product_code}</div>${by}
                    </td>
                    <td style="padding:.4rem .35rem;text-align:center;"><span class="hist-badge ${htCls}">${htLbl}</span></td>
                    <td style="padding:.4rem .35rem;text-align:center;color:var(--sk-danger);font-weight:800;">−${h.returned_pieces}</td>
                    <td style="padding:.4rem .35rem;text-align:center;color:var(--sk-success);font-weight:800;">${newP}</td>
                    <td style="padding:.4rem .35rem;text-align:center;"><span class="hist-badge ${stCls}">${stLbl}</span></td>
                    <td style="padding:.4rem .35rem;text-align:right;color:var(--sk-muted);white-space:nowrap;">${dt}</td>
                </tr>`;
            });
        }
        document.getElementById('aprHistBody').innerHTML = html;
        document.getElementById('aprHistPageInfo').textContent = `পেজ ${p} / ${r.pages}`;
        document.getElementById('aprHistTotal').textContent = r.total;
        document.getElementById('aprHistPrev').disabled = (p <= 1);
        document.getElementById('aprHistNext').disabled = (p >= r.pages);
    }, 'json');
}
</script>

<?php include 'inventory_bottom_nav.php'; ?>
</body>
</html>
