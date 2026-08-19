<?php
declare(strict_types=1);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

class BusinessLogicException extends Exception {}

function excLogError(Throwable $e): void {
    $d = __DIR__ . '/../Logs';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    @file_put_contents($d . '/error_log.txt',
        "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage()
        . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL,
        FILE_APPEND);
}

$isAjax = !empty($_POST['ajax_action']);

// Session timeout: 20 min
$lastAct = $_SESSION['last_activity'] ?? null;
if ($lastAct !== null && is_int($lastAct) && (time() - $lastAct > 1200)) {
    session_unset(); session_destroy();
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'session_expired']); exit; }
    echo "<script>window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'session_expired']); exit; }
    header("Location: ../index.php"); exit;
}

$role = strtolower(trim(isset($_SESSION['role']) && is_string($_SESSION['role']) ? $_SESSION['role'] : 'user'));
$EXC_ALLOWED_ROLES = ['admin', 'manager', 'staff'];
if (!in_array($role, $EXC_ALLOWED_ROLES, true)) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'error','message'=>'এই পেজ ব্যবহারের অনুমতি নেই!']); exit; }
    header("Location: inventory_dashboard.php"); exit;
}
// admin-এর কাজ সাথে সাথে কার্যকর হয়; ম্যানেজার/স্টাফের কাজ admin অনুমোদনের অপেক্ষায় থাকবে
$isAdmin = ($role === 'admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

$dbPath = __DIR__ . '/../db_connect.php';
if (file_exists($dbPath)) { require_once $dbPath; }
/** @var PDO $conn */

$auditPath = __DIR__ . '/Helpers/AuditInit.php';
if (file_exists($auditPath)) { require_once $auditPath; AuditInit::boot($conn); }

$uid      = isset($_SESSION['user_id']) && is_scalar($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$username = isset($_SESSION['username']) && is_string($_SESSION['username']) ? $_SESSION['username'] : 'admin';
date_default_timezone_set('Asia/Dhaka');

// ════════════════════════════════════════════════════════════════
// CORE HELPERS
// ════════════════════════════════════════════════════════════════

function excNextNo(PDO $conn): string {
    try {
        $s = $conn->query("SELECT exchange_no FROM supplier_exchanges ORDER BY id DESC LIMIT 1");
        $l = $s->fetchColumn();
        $n = ($l && preg_match('/SKF-EXC-(\d+)$/', (string)$l, $m)) ? (int)$m[1] + 1 : 1;
    } catch (Throwable $e) { $n = 1; }
    return 'SKF-EXC-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function excNextProductCode(PDO $conn): string {
    $s = $conn->query(
        "SELECT product_code FROM inventory WHERE product_code LIKE 'SKF-%'
         ORDER BY CAST(SUBSTRING_INDEX(product_code,'-',-1) AS UNSIGNED) DESC LIMIT 1"
    );
    $l = $s->fetchColumn();
    $n = $l ? (int)str_replace('SKF-', '', (string)$l) + 1 : 1;
    return 'SKF-' . str_pad((string)$n, 2, '0', STR_PAD_LEFT);
}

function excSaveImage(string $b64, string $code): string {
    $dir = __DIR__ . '/uploads/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $raw = preg_replace('#^data:image/\w+;base64,#i', '', $b64);
    $img = base64_decode($raw, true);
    if ($img === false)                throw new BusinessLogicException('ছবির ডাটা পড়া যায়নি!');
    if (strlen($img) > 6 * 1024*1024) throw new BusinessLogicException('ছবি ৬MB-এর বেশি!');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($img);
    if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true))
        throw new BusinessLogicException('শুধু JPG/PNG/WebP ছবি দিন!');
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $code) ?: ('exc_' . time());
    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring($img);
        if ($src !== false) {
            [$w, $h, $mx] = [imagesx($src), imagesy($src), 900];
            if ($w > $mx || $h > $mx) {
                $r   = min($mx / max(1,$w), $mx / max(1,$h));
                $nw  = max(1, (int)round($w*$r));
                $nh  = max(1, (int)round($h*$r));
                $dst = imagecreatetruecolor($nw, $nh);
                if ($dst !== false) { imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h); imagedestroy($src); $src=$dst; }
            }
            $f = $safe.'.jpg'; $p = $dir.$f; $i = 2;
            while (file_exists($p)) { $f = $safe.'_'.$i.'.jpg'; $p = $dir.$f; $i++; }
            imagejpeg($src, $p, 82); imagedestroy($src);
            return 'uploads/'.$f;
        }
    }
    $f = $safe.'.jpg'; $p = $dir.$f; $i = 2;
    while (file_exists($p)) { $f = $safe.'_'.$i.'.jpg'; $p = $dir.$f; $i++; }
    if (file_put_contents($p, $img) === false) throw new BusinessLogicException('ছবি সেভ করা যায়নি!');
    return 'uploads/'.$f;
}

// ════════════════════════════════════════════════════════════════
// NOTIFICATION HELPERS
// ════════════════════════════════════════════════════════════════

/**
 * notification_config.json লোড করে
 */
function excLoadNotifConfig(): array {
    $path = __DIR__ . '/notification_config.json';
    if (!file_exists($path)) return [];
    $raw = @file_get_contents($path);
    $cfg = $raw !== false ? @json_decode($raw, true) : null;
    return is_array($cfg) ? $cfg : [];
}

/**
 * .env ফাইল থেকে key-value পড়ে
 */
function excGetEnvVal(string $key): string {
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

/**
 * বাংলাদেশি ফোন নম্বরকে MiMSMS-এর প্রয়োজনীয় ফরম্যাটে আনে (880XXXXXXXXXX)
 */
function excNormalizeBDPhone(string $phone): string {
    $p = preg_replace('/\D/', '', $phone) ?? '';
    if (str_starts_with($p, '880')) return $p;
    if (str_starts_with($p, '0'))   return '880' . substr($p, 1);
    return '880' . $p;
}

/**
 * SMS পাঠানো — MiMSMS API v2 (https://api.mimsms.com/api/V2/SMS)
 * .env-এ প্রয়োজন: SMS_APIKEY, S_USERNAME (MiMSMS প্যানেলের লগইন ইমেইল), SMS_SENDER
 * (Staff/Core/Config.php-এর সাথে একই নামকরণ — পুরনো নাম SMS_API_KEY/SMS_USERNAME/SMS_SENDER_ID থাকলে সেটাও fallback হিসেবে কাজ করবে)
 */
function excSendSms(string $phone, string $message): bool {
    try {
        $cfg = excLoadNotifConfig();
        if (empty($cfg['sms_enabled'])) return false;

        $apiKey   = excGetEnvVal('SMS_APIKEY')   ?: excGetEnvVal('SMS_API_KEY');
        $userName = excGetEnvVal('S_USERNAME')   ?: excGetEnvVal('SMS_USERNAME');
        $senderId = excGetEnvVal('SMS_SENDER')   ?: excGetEnvVal('SMS_SENDER_ID');
        if (empty($apiKey) || empty($userName) || empty($senderId)) {
            excLogError(new \RuntimeException('SMS_APIKEY / S_USERNAME / SMS_SENDER .env-এ নেই'));
            return false;
        }

        $payload = json_encode([
            'apiKey'          => $apiKey,
            'userName'        => $userName,
            'senderName'      => $senderId,
            'transactionType' => 'T',
            'mobileNumber'    => excNormalizeBDPhone($phone),
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
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            excLogError(new \RuntimeException("MiMSMS cURL error: {$err}"));
            return false;
        }
        $res = json_decode((string)$raw, true);
        $ok  = is_array($res) && (($res['status'] ?? '') === 'Success' || ($res['statusCode'] ?? '') === '200');
        if (!$ok) {
            excLogError(new \RuntimeException('MiMSMS পাঠানো ব্যর্থ: ' . (string)$raw));
        }
        return $ok;
    } catch (Throwable $e) {
        excLogError($e); return false;
    }
}

/**
 * Email পাঠানো — PHP mail() ব্যবহার করে
 */
function excSendEmail(string $to, string $subject, string $htmlBody): bool {
    try {
        $cfg = excLoadNotifConfig();
        if (empty($cfg['email_enabled'])) return false;
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            excLogError(new \RuntimeException("Invalid admin email: {$to}")); return false;
        }
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From:  Inventory <inventory@sadakalofashion.com>\r\n";
        $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
    } catch (Throwable $e) {
        excLogError($e); return false;
    }
}

/**
 * Return_only pending হলে অ্যাডমিনকে SMS + Email পাঠাও
 */
function excNotifyAdmin(
    string $excNo, string $oldCode, string $oldName,
    string $supplierName, int $retPcs, float $buyPrice,
    string $note, string $doneBy
): void {
    $cfg      = excLoadNotifConfig();
    $baseUrl  = rtrim($cfg['approval_base_url'] ?? '', '/');
    $approvalUrl = $baseUrl . '/supplier_return_approval.php';
    $retValue = number_format($retPcs * $buyPrice);
    $timeStr  = date('d M Y, h:i A');

    // ── SMS ──────────────────────────────────────────────────
    $phone = $cfg['admin_phone'] ?? '';
    if (!empty($phone)) {
        $smsMsg = "[SADA KALO] নতুন রিটার্ন রিকোয়েস্ট!\n"
                . "নং: {$excNo}\n"
                . "পণ্য: {$oldCode} ({$oldName})\n"
                . "সাপ্লায়ার: {$supplierName}\n"
                . "পিস: {$retPcs} | মূল্য: ৳{$retValue}\n"
                . "অনুমোদন করুন: {$approvalUrl}";
        excSendSms($phone, $smsMsg);
    }

    // ── Email ─────────────────────────────────────────────────
    $email = $cfg['admin_email'] ?? '';
    if (!empty($email)) {
        $noteHtml = htmlspecialchars($note !== '' ? $note : '—', ENT_QUOTES, 'UTF-8');
        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="bn">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:16px;">
<div style="max-width:500px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
    <div style="background:#111;color:#fff;padding:16px 20px;font-size:1rem;font-weight:bold;">
        SADA KALO FASHION — সাপ্লায়ার রিটার্ন রিকোয়েস্ট
    </div>
    <div style="padding:20px;">
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:.85rem;font-weight:700;color:#856404;">
            ⚠ নতুন পেন্ডিং রিটার্ন রিকোয়েস্ট — অনুমোদন বাকি
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
            <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;color:#666;width:40%">এক্সচেঞ্জ নং</td><td style="padding:7px 0;font-weight:700">{$excNo}</td></tr>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;color:#666">পণ্য কোড</td><td style="padding:7px 0;font-weight:700">{$oldCode}</td></tr>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;color:#666">পণ্যের নাম</td><td style="padding:7px 0">{$oldName}</td></tr>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;color:#666">সাপ্লায়ার</td><td style="padding:7px 0;font-weight:700;color:#dc3545">{$supplierName}</td></tr>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;color:#666">ফেরত পিস</td><td style="padding:7px 0;font-weight:700;color:#dc3545">{$retPcs} পিস</td></tr>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;color:#666">আনুমানিক মূল্য</td><td style="padding:7px 0">৳{$retValue}</td></tr>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;color:#666">নোট</td><td style="padding:7px 0">{$noteHtml}</td></tr>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:7px 0;color:#666">জমাকারী</td><td style="padding:7px 0">{$doneBy}</td></tr>
            <tr><td style="padding:7px 0;color:#666">সময়</td><td style="padding:7px 0">{$timeStr}</td></tr>
        </table>
        <div style="margin-top:18px;text-align:center;">
            <a href="{$approvalUrl}" style="display:inline-block;background:#dc3545;color:#fff;text-decoration:none;padding:10px 24px;border-radius:6px;font-weight:700;font-size:.9rem;">
                ✓ অনুমোদন পেজে যান
            </a>
        </div>
    </div>
    <div style="padding:10px 20px;background:#f9fafb;font-size:.75rem;color:#9ca3af;text-align:center;">
        SADA KALO FASHION — Inventory Management System
    </div>
</div>
</body>
</html>
HTML;
        excSendEmail($email, "নতুন রিটার্ন রিকোয়েস্ট [{$excNo}] — অনুমোদন প্রয়োজন", $htmlBody);
    }
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

    // ── পণ্য খোঁজা ───────────────────────────────────────────
    if ($action === 'load_history') {
        $page    = max(1, (int)($_POST['page'] ?? 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;
        try {
            $total = (int)$conn->query("SELECT COUNT(*) FROM supplier_exchanges")->fetchColumn();
            $rows  = $conn->prepare(
                "SELECT se.*, u.username AS by_name
                 FROM supplier_exchanges se
                 LEFT JOIN users u ON se.exchanged_by = u.id
                 ORDER BY se.id DESC LIMIT {$perPage} OFFSET {$offset}"
            );
            $rows->execute();
            echo json_encode([
                'status' => 'success',
                'rows'   => $rows->fetchAll(PDO::FETCH_ASSOC),
                'total'  => $total,
                'page'   => $page,
                'pages'  => (int)ceil($total / $perPage),
            ]); exit;
        } catch (Throwable $e) {
            excLogError($e);
            echo json_encode(['status'=>'error','message'=>'ডেটা লোড ব্যর্থ']); exit;
        }
    }

    if ($action === 'search_product') {
        try {
            $code = trim((string)($_POST['product_code'] ?? ''));
            if ($code === '') throw new BusinessLogicException('প্রোডাক্ট কোড দিন!');
            $s = $conn->prepare(
                "SELECT i.id, i.product_code, i.name, i.pieces, i.buy_price,
                        i.cost, i.cash_sell, i.image_path, i.status,
                        c.name AS cat_name, c.id AS cat_id
                 FROM inventory i
                 LEFT JOIN categories c ON i.category_id = c.id
                 WHERE i.product_code = ? LIMIT 1"
            );
            $s->execute([$code]);
            $p = $s->fetch(PDO::FETCH_ASSOC);
            if (!$p)                               throw new BusinessLogicException("'{$code}' কোডের কোনো পণ্য পাওয়া যায়নি!");
            if ((int)$p['pieces'] <= 0)            throw new BusinessLogicException("স্টক শূন্য — ফেরত দেওয়ার মতো পণ্য নেই!");
            if (($p['status']??'') === 'inactive') throw new BusinessLogicException("এই পণ্যটি নিষ্ক্রিয়!");
            echo json_encode([
                'status'      => 'success',
                'id'          => (int)$p['id'],
                'product_code'=> $p['product_code'],
                'name'        => htmlspecialchars((string)$p['name'],       ENT_QUOTES,'UTF-8'),
                'cat_name'    => htmlspecialchars((string)$p['cat_name'],   ENT_QUOTES,'UTF-8'),
                'cat_id'      => (int)$p['cat_id'],
                'pieces'      => (int)$p['pieces'],
                'buy_price'   => (float)$p['buy_price'],
                'cost'        => (float)$p['cost'],
                'cash_sell'   => (float)$p['cash_sell'],
                'image_path'  => htmlspecialchars((string)$p['image_path'], ENT_QUOTES,'UTF-8'),
            ]); exit;
        } catch (BusinessLogicException $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e)              { excLogError($e); echo json_encode(['status'=>'error','message'=>'সার্ভার ত্রুটি!']); exit; }
    }

    // ── এক্সচেঞ্জ প্রসেস ─────────────────────────────────────
    if ($action === 'process_exchange') {
        try {
            $oldCode      = trim((string)($_POST['old_product_code'] ?? ''));
            $retPcs       = (int)($_POST['returned_pieces']  ?? 0);
            $excType      = trim((string)($_POST['exchange_type']    ?? ''));
            $note         = trim((string)($_POST['note']             ?? ''));
            $supplierName = trim((string)($_POST['supplier_name']    ?? ''));
            $allowed      = ['new_product','same_product','return_only'];

            if ($oldCode === '')                     throw new BusinessLogicException('পুরানো পণ্যের কোড দিন!');
            if ($retPcs <= 0)                        throw new BusinessLogicException('ফেরত পিস সঠিক নয়!');
            if (!in_array($excType, $allowed, true)) throw new BusinessLogicException('এক্সচেঞ্জ টাইপ সঠিক নয়!');

            // return_only: সাপ্লায়ারের নাম বাধ্যতামূলক
            if ($excType === 'return_only' && $supplierName === '') {
                throw new BusinessLogicException('সাপ্লায়ারের নাম লিখুন (বাধ্যতামূলক)!');
            }

            $newCatId = 0; $newPcs = 0; $newBuy = 0.0; $newCost = 15.0;
            $newSell  = 0.0; $newCode = ''; $newImgB64 = ''; $hasImg = false;

            if ($excType !== 'return_only') {
                $newCatId  = (int)($_POST['new_category_id'] ?? 0);
                $newPcs    = (int)($_POST['new_pieces']      ?? 0);
                $newBuy    = (float)($_POST['new_buy_price'] ?? 0);
                $newCost   = (float)($_POST['new_cost']      ?? 15);
                $newSell   = (float)($_POST['new_cash_sell'] ?? 0);
                $hasImg    = !empty($_POST['new_image_b64']) && is_string($_POST['new_image_b64']);
                $newImgB64 = $hasImg ? (string)$_POST['new_image_b64'] : '';
                if ($newCatId <= 0) throw new BusinessLogicException('নতুন পণ্যের ক্যাটাগরি বেছে নিন!');
                if ($newPcs   <= 0) throw new BusinessLogicException('নতুন পণ্যের পিস সংখ্যা দিন!');
                if ($newBuy   <= 0) throw new BusinessLogicException('নতুন পণ্যের ক্রয় মূল্য দিন!');
                if ($newSell  <= 0) throw new BusinessLogicException('নতুন পণ্যের বিক্রয় মূল্য দিন!');
                if ($excType === 'new_product') {
                    $newCode = trim((string)($_POST['new_product_code'] ?? ''));
                    if ($newCode === '') $newCode = excNextProductCode($conn);
                }
            }

            $conn->beginTransaction();

            // পুরানো পণ্য লক + যাচাই
            $st = $conn->prepare("SELECT id, pieces, buy_price, name FROM inventory WHERE product_code = ? LIMIT 1 FOR UPDATE");
            $st->execute([$oldCode]);
            $old = $st->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new BusinessLogicException("পুরানো পণ্য পাওয়া যায়নি!");
            if ((int)$old['pieces'] < $retPcs)
                throw new BusinessLogicException("স্টকে মাত্র {$old['pieces']} পিস! {$retPcs} পিস ফেরত সম্ভব নয়!");

            $excNo       = excNextNo($conn);
            $newImgPath  = '';
            $newName     = '';
            $newProdDbId = 0;
            $oldId       = (int)$old['id'];
            $oldPiecesBefore = (int)$old['pieces'];

            // admin করলে সাথে সাথে কার্যকর হয়; ম্যানেজার/স্টাফ করলে সব ধরনের
            // এক্সচেঞ্জই admin অনুমোদনের অপেক্ষায় থাকে (কোনো স্টক এখনই বদলায় না)
            $status = (!$isAdmin || $excType === 'return_only') ? 'pending' : 'approved';
            $pendingPayload = null;

            if ($status === 'approved') {
                // ══ A: একই পণ্য (same_product) — সরাসরি কার্যকর ══
                if ($excType === 'same_product') {
                    $newCode = $oldCode;
                    $cs = $conn->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
                    $cs->execute([$newCatId]);
                    $newName = (string)($cs->fetchColumn() ?: $old['name']);
                    if ($hasImg && $newImgB64 !== '') {
                        $newImgPath = excSaveImage($newImgB64, $oldCode . '_exc');
                        $conn->prepare("UPDATE inventory SET pieces=pieces-?+?,buy_price=?,cost=?,cash_sell=?,image_path=? WHERE product_code=?")
                             ->execute([$retPcs,$newPcs,$newBuy,$newCost,$newSell,$newImgPath,$oldCode]);
                    } else {
                        $conn->prepare("UPDATE inventory SET pieces=pieces-?+?,buy_price=?,cost=?,cash_sell=? WHERE product_code=?")
                             ->execute([$retPcs,$newPcs,$newBuy,$newCost,$newSell,$oldCode]);
                    }
                    $delta = $newPcs - $retPcs;
                    $adjType = $delta >= 0 ? 'increase' : 'decrease';
                    $conn->prepare("INSERT INTO inventory_adjustments (user_id, product_code, adjustment_type, status, pieces, note, adjusted_by) VALUES(?,?,?,?,?,?,?)")
                         ->execute([$uid, $oldCode, $adjType, 'approved', abs($delta),
                             "সাপ্লায়ার এক্সচেঞ্জ [{$excNo}]: {$retPcs} পিস ফেরত → {$newPcs} পিস নতুন", $uid]);
                }

                // ══ B: নতুন পণ্য (new_product) — সরাসরি কার্যকর ══
                elseif ($excType === 'new_product') {
                    $dc = $conn->prepare("SELECT id FROM inventory WHERE product_code=? LIMIT 1");
                    $dc->execute([$newCode]);
                    if ($dc->rowCount() > 0) throw new BusinessLogicException("'{$newCode}' কোডটি ইতিমধ্যে ব্যবহৃত!");
                    $cs = $conn->prepare("SELECT name FROM categories WHERE id=? LIMIT 1");
                    $cs->execute([$newCatId]);
                    $newName = (string)$cs->fetchColumn();
                    if ($newName === '') throw new BusinessLogicException('ক্যাটাগরি পাওয়া যায়নি!');
                    $conn->prepare("UPDATE inventory SET pieces=pieces-? WHERE product_code=?")
                         ->execute([$retPcs, $oldCode]);
                    if ($hasImg && $newImgB64 !== '') $newImgPath = excSaveImage($newImgB64, $newCode);
                    $conn->prepare("INSERT INTO inventory (product_code,category_id,name,image_path,pieces,buy_price,cost,cash_sell,added_by) VALUES(?,?,?,?,?,?,?,?,?)")
                         ->execute([$newCode,$newCatId,$newName,$newImgPath,$newPcs,$newBuy,$newCost,$newSell,$uid]);
                    $newProdDbId = (int)$conn->lastInsertId();
                    $conn->prepare("INSERT INTO inventory_adjustments (user_id, product_code, adjustment_type, status, pieces, note, adjusted_by) VALUES(?,?,?,?,?,?,?)")
                         ->execute([$uid, $oldCode, 'decrease', 'approved', $retPcs, "সাপ্লায়ার এক্সচেঞ্জ [{$excNo}]: ফেরত → নতুন {$newCode}", $uid]);
                    $conn->prepare("INSERT INTO inventory_adjustments (user_id, product_code, adjustment_type, status, pieces, note, adjusted_by) VALUES(?,?,?,?,?,?,?)")
                         ->execute([$uid, $newCode, 'increase', 'approved', $newPcs, "সাপ্লায়ার এক্সচেঞ্জ [{$excNo}]: {$oldCode} থেকে প্রাপ্ত", $uid]);
                }
            } else {
                // ══ PENDING — কোনো স্টক/ইনভেন্টরি এখনই বদলাবে না ══
                if ($excType === 'same_product') {
                    $newCode = $oldCode;
                    $cs = $conn->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
                    $cs->execute([$newCatId]);
                    $newName = (string)($cs->fetchColumn() ?: $old['name']);
                    if ($hasImg && $newImgB64 !== '') $newImgPath = excSaveImage($newImgB64, $oldCode . '_pend');
                    $pendingPayload = json_encode([
                        'new_category_id' => $newCatId,
                        'new_category_name'=> $newName,
                        'new_pieces'       => $newPcs,
                        'new_buy_price'    => $newBuy,
                        'new_cost'         => $newCost,
                        'new_cash_sell'    => $newSell,
                        'new_image_path'   => $newImgPath,
                    ], JSON_UNESCAPED_UNICODE);
                } elseif ($excType === 'new_product') {
                    if ($newCode !== '') {
                        $dc = $conn->prepare("SELECT id FROM inventory WHERE product_code=? LIMIT 1");
                        $dc->execute([$newCode]);
                        if ($dc->rowCount() > 0) throw new BusinessLogicException("'{$newCode}' কোডটি ইতিমধ্যে ব্যবহৃত!");
                    }
                    $cs = $conn->prepare("SELECT name FROM categories WHERE id=? LIMIT 1");
                    $cs->execute([$newCatId]);
                    $newName = (string)$cs->fetchColumn();
                    if ($newName === '') throw new BusinessLogicException('ক্যাটাগরি পাওয়া যায়নি!');
                    if ($hasImg && $newImgB64 !== '') $newImgPath = excSaveImage($newImgB64, ($newCode ?: 'new') . '_pend');
                    $pendingPayload = json_encode([
                        'new_product_code' => $newCode,
                        'new_category_id'  => $newCatId,
                        'new_category_name'=> $newName,
                        'new_pieces'       => $newPcs,
                        'new_buy_price'    => $newBuy,
                        'new_cost'         => $newCost,
                        'new_cash_sell'    => $newSell,
                        'new_image_path'   => $newImgPath,
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    // return_only: স্টক এখনই কমবে না, শুধু নোট লগ হবে
                    $conn->prepare("INSERT INTO inventory_adjustments (user_id, product_code, adjustment_type, status, pieces, note, adjusted_by) VALUES(?,?,?,?,?,?,?)")
                         ->execute([$uid, $oldCode, 'decrease', 'pending', $retPcs,
                             "পেন্ডিং রিটার্ন [{$excNo}]: অনুমোদন বাকি — সাপ্লায়ার: {$supplierName}", $uid]);
                }
            }

            // এক্সচেঞ্জ রেকর্ড সেভ করো
            $conn->prepare(
                "INSERT INTO supplier_exchanges
                 (exchange_no, old_product_code, old_product_name, returned_pieces, old_buy_price,
                  exchange_type, supplier_name, status,
                  new_product_code, new_product_name, received_pieces, new_buy_price, note, exchanged_by, pending_payload)
                 VALUES (?,?,?,?,?, ?,?,?, ?,?,?,?,?,?,?)"
            )->execute([
                $excNo, $oldCode, $old['name'], $retPcs, (float)$old['buy_price'],
                $excType, $supplierName !== '' ? $supplierName : null, $status,
                $newCode    ?: null, $newName ?: null,
                $excType !== 'return_only' ? $newPcs  : null,
                $excType !== 'return_only' ? $newBuy  : null,
                $note !== '' ? $note : null,
                $uid,
                $pendingPayload,
            ]);
            $excDbId = (int)$conn->lastInsertId();

            $conn->commit();

            // ── Audit logging ────────────────────────────────────
            if (class_exists('AuditLogger')) {
                AuditLogger::create('supplier_exchanges', $excDbId, null, [
                    'exchange_no'   => $excNo,
                    'old_code'      => $oldCode,
                    'returned_pcs'  => $retPcs,
                    'type'          => $excType,
                    'status'        => $status,
                    'supplier_name' => $supplierName,
                    'new_code'      => $newCode ?: null,
                    'done_by'       => $username,
                ], "সাপ্লায়ার এক্সচেঞ্জ — {$excNo} [{$status}]");

                if ($status === 'approved' && $excType !== 'return_only') {
                    $newOldPcs = $oldPiecesBefore - $retPcs + ($excType === 'same_product' ? $newPcs : 0);
                    AuditLogger::update('inventory', $oldId,
                        ['pieces' => $oldPiecesBefore, 'buy_price' => (float)$old['buy_price']],
                        ['pieces' => $newOldPcs],
                        "সাপ্লায়ার এক্সচেঞ্জ [{$excNo}]: স্টক আপডেট"
                    );
                    if ($excType === 'new_product' && $newProdDbId > 0) {
                        AuditLogger::create('inventory', $newProdDbId, null,
                            ['product_code'=>$newCode,'pieces'=>$newPcs,'buy_price'=>$newBuy],
                            "সাপ্লায়ার এক্সচেঞ্জে নতুন পণ্য [{$excNo}]"
                        );
                    }
                }
            }

            // ── pending হলে admin-কে নোটিফাই করা ────────────────
            if ($status === 'pending') {
                excNotifyAdmin(
                    $excNo, $oldCode, (string)$old['name'],
                    $supplierName !== '' ? $supplierName : '—', $retPcs, (float)$old['buy_price'],
                    $note, $username
                );
                $typeLbl = match($excType) {
                    'same_product' => 'একই পণ্য আপডেট',
                    'new_product'  => 'নতুন পণ্য',
                    default        => 'শুধু ফেরত',
                };
                echo json_encode([
                    'status'      => 'success',
                    'message'     => "রিকোয়েস্ট পাঠানো হয়েছে! [{$excNo}] — {$typeLbl}\nঅ্যাডমিন অনুমোদন করলে তবেই স্টক/পণ্য আপডেট হবে।",
                    'exchange_no' => $excNo,
                    'is_pending'  => true,
                ]); exit;
            }

            $msg = match($excType) {
                'same_product' => "এক্সচেঞ্জ সফল! [{$excNo}] — {$retPcs} পিস ফেরত, {$newPcs} পিস নতুন।",
                'new_product'  => "নতুন পণ্য [{$newCode}] যোগ হয়েছে! [{$excNo}]",
                default        => "সফল! [{$excNo}]",
            };
            echo json_encode(['status'=>'success','message'=>$msg,'exchange_no'=>$excNo,'is_pending'=>false]); exit;

        } catch (BusinessLogicException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            excLogError($e);
            echo json_encode(['status'=>'error','message'=>'সার্ভার ত্রুটি ঘটেছে!']); exit;
        }
    }

    echo json_encode(['status'=>'error','message'=>'অজানা অ্যাকশন!']); exit;
}

// ════════════════════════════════════════════════════════════════
// PAGE DATA
// ════════════════════════════════════════════════════════════════
$cats = [];
try {
    $cats = $conn->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name ASC")
                 ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { excLogError($e); }

$historyTotal = 0;
$historyPages = 1;
$history = [];
try {
    $historyTotal = (int)$conn->query("SELECT COUNT(*) FROM supplier_exchanges")->fetchColumn();
    $historyPages = (int)ceil($historyTotal / 15) ?: 1;
    $history = $conn->query(
        "SELECT se.*, u.username AS by_name
         FROM supplier_exchanges se
         LEFT JOIN users u ON se.exchanged_by = u.id
         ORDER BY se.id DESC LIMIT 15"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* first run */ }

$pendingCount = 0;
try {
    $pendingCount = (int)$conn->query("SELECT COUNT(*) FROM supplier_exchanges WHERE status='pending'")->fetchColumn();
} catch (Throwable $e) {}

$csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>সাপ্লায়ার এক্সচেঞ্জ — SADA KALO</title>
<meta name="theme-color" content="#ffffff">
<script>(function(){try{var t=localStorage.getItem('sk-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<link  href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link  rel="stylesheet" href="theme.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script defer src="theme-toggle.js"></script>

<style>
.exc-step-head{display:flex;align-items:center;gap:.55rem;margin-bottom:.75rem}
.exc-step-num{width:24px;height:24px;border-radius:50%;background:var(--sk-accent);color:#fff;font-size:.68rem;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.exc-step-title{font-size:.82rem;font-weight:800;color:var(--sk-ink);letter-spacing:-.01em}
.exc-step-sub{font-size:.68rem;color:var(--sk-muted);font-weight:600;margin-top:.05rem}
.exc-search-row{display:flex;gap:.5rem;align-items:center}
.exc-search-row .sk-input{flex:1;height:48px;font-size:.95rem;font-weight:700;letter-spacing:.04em}
.exc-qr-btn{width:48px;height:48px;border-radius:.625rem;border:1.5px solid var(--sk-line);background:var(--sk-surface-2);color:var(--sk-ink);font-size:1rem;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:border-color .15s}
.exc-qr-btn.active{border-color:var(--sk-danger);background:var(--sk-danger-soft);color:var(--sk-danger)}
.exc-found-card{display:none;border:2px solid var(--sk-accent);border-radius:.875rem;overflow:hidden;margin-bottom:.875rem;background:var(--sk-surface)}
.exc-found-card.show{display:block}
.exc-found-top{display:flex;gap:.75rem;align-items:flex-start;padding:.875rem;background:var(--sk-surface-2);border-bottom:1px solid var(--sk-line)}
.exc-found-img{width:62px;height:62px;border-radius:.5rem;object-fit:cover;border:1.5px solid var(--sk-line);flex-shrink:0;background:var(--sk-surface-2)}
.exc-found-name{font-size:1rem;font-weight:800;color:var(--sk-ink);line-height:1.2;margin-bottom:.2rem}
.exc-found-cat{font-size:.72rem;color:var(--sk-muted);font-weight:600}
.exc-found-stock{display:inline-flex;align-items:center;gap:.3rem;margin-top:.35rem;font-size:.78rem;font-weight:800;color:var(--sk-success);background:rgba(16,185,129,.1);border-radius:999px;padding:.15rem .6rem}
.exc-found-stock.low{color:var(--sk-danger);background:var(--sk-danger-soft)}
.exc-found-prices{font-size:.7rem;font-weight:600;color:var(--sk-muted);margin-top:.3rem}
.exc-section{padding:.875rem;border-top:1px solid var(--sk-line)}
.exc-ret-row{display:grid;grid-template-columns:1fr 1fr;gap:.625rem}
.exc-ret-value{background:var(--sk-danger-soft)!important;color:var(--sk-danger)!important;font-weight:800!important;text-align:center!important;cursor:not-allowed!important}
.exc-type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem}
.exc-type-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.35rem;padding:.75rem .4rem;border:2px solid var(--sk-line);border-radius:.75rem;background:var(--sk-surface-2);cursor:pointer;transition:all .15s;-webkit-tap-highlight-color:transparent;user-select:none}
.exc-type-btn i{font-size:1.1rem;color:var(--sk-muted);transition:color .15s}
.exc-type-btn span{font-size:.68rem;font-weight:800;color:var(--sk-muted);text-align:center;transition:color .15s}
.exc-type-btn.active-new{border-color:#6366f1;background:rgba(99,102,241,.08)}
.exc-type-btn.active-new i,.exc-type-btn.active-new span{color:#6366f1}
.exc-type-btn.active-same{border-color:var(--sk-success);background:rgba(16,185,129,.08)}
.exc-type-btn.active-same i,.exc-type-btn.active-same span{color:var(--sk-success)}
.exc-type-btn.active-ret{border-color:#f59e0b;background:rgba(245,158,11,.08)}
.exc-type-btn.active-ret i,.exc-type-btn.active-ret span{color:#f59e0b}
.exc-new-section{display:none;margin-bottom:.875rem}
.exc-new-section.show{display:block}
.exc-price-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem}
.exc-profit-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .75rem;border-radius:999px;font-size:.78rem;font-weight:800;background:var(--sk-surface-2);border:1px solid var(--sk-line);margin-bottom:.625rem}
.exc-cam-trigger{display:flex;align-items:center;gap:.75rem;padding:.875rem;border:2px dashed var(--sk-line);border-radius:.75rem;cursor:pointer;background:var(--sk-surface-2);transition:border-color .15s;-webkit-tap-highlight-color:transparent}
.exc-cam-trigger:active{border-color:var(--sk-accent)}
.exc-cam-trigger i{font-size:1.3rem;color:var(--sk-accent)}
.exc-cam-lbl{font-size:.82rem;font-weight:700;color:var(--sk-ink)}
.exc-cam-sub{font-size:.68rem;color:var(--sk-muted);font-weight:600}
.exc-img-preview{width:100%;border-radius:.625rem;object-fit:cover;max-height:180px;display:none;margin-top:.625rem;border:1.5px solid var(--sk-line)}
.exc-img-preview.show{display:block}
/* Supplier name field */
.exc-supplier-row{background:rgba(245,158,11,.06);border:1.5px solid rgba(245,158,11,.3);border-radius:.75rem;padding:.875rem;margin-bottom:.625rem;display:none}
.exc-supplier-row.show{display:block}
.exc-supplier-label{font-size:.78rem;font-weight:800;color:#b45309;margin-bottom:.4rem;display:flex;align-items:center;gap:.35rem}
/* Pending badge */
.exc-pending-banner{display:flex;align-items:center;gap:.625rem;padding:.75rem .875rem;border-radius:.75rem;background:rgba(245,158,11,.1);border:1.5px solid rgba(245,158,11,.3);margin-bottom:.875rem;cursor:pointer;text-decoration:none}
.exc-pending-banner i{color:#f59e0b;font-size:1rem;flex-shrink:0}
.exc-pending-txt{font-size:.78rem;font-weight:700;color:#92400e}
/* History */
.exc-hist-item{display:flex;align-items:flex-start;gap:.625rem;padding:.625rem 0;border-bottom:1px solid var(--sk-line)}
.exc-hist-item:last-child{border-bottom:none}
.exc-hist-no{font-size:.65rem;font-weight:800;color:var(--sk-accent);white-space:nowrap}
.exc-hist-badge{display:inline-block;padding:.1rem .45rem;border-radius:.375rem;font-size:.62rem;font-weight:800;text-transform:uppercase;white-space:nowrap}
.exc-hist-badge.new{background:rgba(99,102,241,.12);color:#6366f1}
.exc-hist-badge.same{background:rgba(16,185,129,.12);color:var(--sk-success)}
.exc-hist-badge.ret{background:rgba(245,158,11,.12);color:#f59e0b}
.exc-hist-badge.pend{background:rgba(245,158,11,.2);color:#92400e}
.exc-hist-badge.canc{background:rgba(107,114,128,.12);color:#6b7280}
.exc-hist-codes{font-size:.72rem;font-weight:700;color:var(--sk-ink);margin:.15rem 0 .1rem}
.exc-hist-meta{font-size:.65rem;color:var(--sk-muted);font-weight:600}
/* Camera modal */
#excCamModal{display:none;position:fixed;inset:0;z-index:10001;background:rgba(0,0,0,.85);align-items:flex-end;justify-content:center}
#excCamModal.open{display:flex}
.exc-cam-sheet{background:var(--sk-surface);border-radius:1.25rem 1.25rem 0 0;padding:1.25rem 1rem 2rem;width:100%;max-width:480px}
#excVideo{width:100%;border-radius:.75rem;aspect-ratio:4/3;object-fit:cover;background:#000;display:none}
.exc-cam-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;padding:2.5rem 0;color:var(--sk-muted);font-size:.82rem;font-weight:600}
#excScanArea{margin-top:.625rem;border-radius:.625rem;overflow:hidden;display:none}
#excScanArea.open{display:block}
body{padding-bottom:80px!important}
</style>
</head>
<body>

<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button class="sk-iconbtn" onclick="skToggleDrawer()"><i class="fas fa-bars"></i></button>
        <a href="inventory_dashboard.php" class="sk-iconbtn"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title">সাপ্লায়ার এক্সচেঞ্জ</div>
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
        <a href="inventory.php"              class="sk-drawer__item"><i class="fas fa-plus-circle"></i><span>পণ্য এড</span></a>
        <a href="Invantory_Items.php"         class="sk-drawer__item"><i class="fas fa-boxes"></i><span>আইটেম</span></a>
        <a href="inventory_pos.php"           class="sk-drawer__item"><i class="fas fa-cash-register"></i><span>POS</span></a>
        <a href="inventory_sales_history.php" class="sk-drawer__item"><i class="fas fa-history"></i><span>বিক্রয় হিস্টরি</span></a>
        <a href="return_product.php"          class="sk-drawer__item"><i class="fas fa-undo"></i><span>কাস্টমার রিটার্ন</span></a>
        <a href="supplier_exchange.php"       class="sk-drawer__item on"><i class="fas fa-exchange-alt"></i><span>সাপ্লায়ার এক্সচেঞ্জ</span></a>
        <a href="supplier_exchange_report.php"class="sk-drawer__item"><i class="fas fa-chart-bar"></i><span>এক্সচেঞ্জ রিপোর্ট</span></a>
        <?php if ($isAdmin): ?>
        <a href="supplier_return_approval.php"class="sk-drawer__item"><i class="fas fa-tasks"></i><span>রিটার্ন অনুমোদন</span></a>
        <?php endif; ?>
        <a href="out_of_stock.php"            class="sk-drawer__item"><i class="fas fa-exclamation-triangle"></i><span>স্টক শেষ</span></a>
        <a href="unsold_inventory.php"        class="sk-drawer__item"><i class="fas fa-clock"></i><span>অনবিক্রিত</span></a>
    </div>
    <?php if ($isAdmin): ?>
    <div class="sk-drawer__section">অ্যাডমিন</div>
    <div class="sk-drawer__grid">
        <a href="admin_inventory_control.php" class="sk-drawer__item"><i class="fas fa-shield-alt"></i><span>মাস্টার কন্ট্রোল</span></a>
        <a href="Audit_log.php"               class="sk-drawer__item"><i class="fas fa-clipboard-list"></i><span>অডিট লগ</span></a>
        <a href="notification_settings.php"   class="sk-drawer__item"><i class="fas fa-bell"></i><span>নোটিফিকেশন</span></a>
    </div>
    <?php endif; ?>
</aside>

<div class="sk-page-content">

    <div style="text-align:center;padding:.5rem 0 1rem;">
        <div style="font-size:1.4rem;font-weight:900;color:var(--sk-ink);letter-spacing:-.02em;">
            <i class="fas fa-exchange-alt" style="color:var(--sk-accent);margin-right:.4rem;"></i>সাপ্লায়ার এক্সচেঞ্জ
        </div>
        <div style="font-size:.73rem;color:var(--sk-muted);font-weight:600;margin-top:.2rem;">
            না বিকানো পণ্য ফেরত → নতুন পণ্য গ্রহণ
        </div>
    </div>

    <!-- Pending approval banner -->
    <?php if ($pendingCount > 0): ?>
    <a href="supplier_return_approval.php" class="exc-pending-banner" style="text-decoration:none;">
        <i class="fas fa-clock"></i>
        <div class="exc-pending-txt">
            <?= $pendingCount ?> টি রিকোয়েস্ট অনুমোদনের অপেক্ষায় —
            <span style="text-decoration:underline;">এখানে অনুমোদন করুন</span>
        </div>
        <i class="fas fa-chevron-right" style="color:#f59e0b;font-size:.75rem;margin-left:auto;"></i>
    </a>
    <?php endif; ?>

    <!-- ধাপ ১: পণ্য খোঁজা -->
    <div class="sk-card" style="margin-bottom:.875rem;">
        <div class="exc-step-head">
            <div class="exc-step-num">১</div>
            <div>
                <div class="exc-step-title">পুরানো পণ্য খুঁজুন</div>
                <div class="exc-step-sub">কোড লিখুন বা QR স্ক্যান করুন</div>
            </div>
        </div>
        <div id="excScanArea"><div id="excReader"></div></div>
        <div class="exc-search-row">
            <input type="text" id="inpOldCode" class="sk-input"
                   placeholder="যেমন: SKF-05"
                   autocomplete="off" autocorrect="off" spellcheck="false"
                   oninput="onOldCodeType()"
                   onkeydown="if(event.key==='Enter'){event.preventDefault();doSearch();}">
            <button type="button" class="exc-qr-btn" id="excQrBtn" onclick="toggleQr()">
                <i class="fas fa-qrcode"></i>
            </button>
            <button type="button" class="sk-btn sk-btn--accent" onclick="doSearch()"
                    id="excSearchBtn" style="height:48px;padding:0 1rem;">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>

    <!-- পণ্য পাওয়া গেলে -->
    <div class="exc-found-card" id="excFoundCard">
        <div class="exc-found-top">
            <img id="excProdImg" src="" alt="" class="exc-found-img" onerror="this.src='logo.png'">
            <div style="flex:1;min-width:0;">
                <div class="exc-found-name" id="excProdName">—</div>
                <div class="exc-found-cat"  id="excProdCat">—</div>
                <div class="exc-found-prices" id="excProdPrices">—</div>
                <span class="exc-found-stock" id="excProdStock">
                    <i class="fas fa-cubes"></i> <span id="excProdStockNum">০</span> পিস
                </span>
            </div>
        </div>

        <!-- ধাপ ২: কত পিস ফেরত -->
        <div class="exc-section">
            <div class="exc-step-head" style="margin-bottom:.625rem;">
                <div class="exc-step-num">২</div>
                <div><div class="exc-step-title">কত পিস ফেরত দিচ্ছেন?</div></div>
            </div>
            <div class="exc-ret-row">
                <div class="sk-field" style="margin:0;">
                    <label class="sk-label">ফেরত পিস <span style="color:var(--sk-danger)">*</span></label>
                    <input type="number" id="inpRetPcs" class="sk-input" min="1"
                           placeholder="যেমন: ১৫"
                           style="text-align:center;font-size:1.15rem;font-weight:800;"
                           oninput="onRetPcsChange()">
                </div>
                <div class="sk-field" style="margin:0;">
                    <label class="sk-label">আনুমানিক মূল্য</label>
                    <input type="text" id="inpRetVal" class="sk-input exc-ret-value" readonly placeholder="৳ ০.০০">
                </div>
            </div>
        </div>

        <!-- ধাপ ৩: এক্সচেঞ্জ টাইপ -->
        <div class="exc-section">
            <div class="exc-step-head" style="margin-bottom:.625rem;">
                <div class="exc-step-num">৩</div>
                <div><div class="exc-step-title">এক্সচেঞ্জ টাইপ বেছে নিন</div></div>
            </div>
            <div class="exc-type-grid">
                <div class="exc-type-btn" id="tbn_new"  onclick="setType('new_product')">
                    <i class="fas fa-plus-circle"></i><span>নতুন পণ্য</span>
                </div>
                <div class="exc-type-btn" id="tbn_same" onclick="setType('same_product')">
                    <i class="fas fa-sync-alt"></i><span>একই পণ্য আপডেট</span>
                </div>
                <div class="exc-type-btn" id="tbn_ret"  onclick="setType('return_only')">
                    <i class="fas fa-arrow-circle-left"></i><span>শুধু ফেরত</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ধাপ ৪: নতুন পণ্যের তথ্য -->
    <div class="exc-new-section sk-card" id="excNewForm">
        <div class="exc-step-head">
            <div class="exc-step-num">৪</div>
            <div>
                <div class="exc-step-title" id="excNewFormTitle">নতুন পণ্যের তথ্য</div>
                <div class="exc-step-sub">সাপ্লায়ারের কাছ থেকে যা নিচ্ছেন</div>
            </div>
        </div>
        <div class="sk-field" id="excNewCodeWrap">
            <label class="sk-label">নতুন পণ্যের কোড <span style="color:var(--sk-muted);font-weight:500">(ফাঁকা = অটো)</span></label>
            <input type="text" id="inpNewCode" class="sk-input" placeholder="যেমন: SKF-66 (ঐচ্ছিক)" autocomplete="off">
        </div>
        <div class="sk-field">
            <label class="sk-label">ক্যাটাগরি <span style="color:var(--sk-danger)">*</span></label>
            <select id="inpNewCat" class="sk-input">
                <option value="">— ক্যাটাগরি বেছে নিন —</option>
                <?php foreach ($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sk-field">
            <label class="sk-label">পিস <span style="color:var(--sk-danger)">*</span></label>
            <input type="number" id="inpNewPcs" class="sk-input" min="1" placeholder="যেমন: ১৫"
                   style="text-align:center;font-size:1.05rem;font-weight:800;" oninput="calcProfit()">
        </div>
        <div class="exc-price-grid-3">
            <div class="sk-field" style="margin-bottom:.5rem;">
                <label class="sk-label">ক্রয় ৳ <span style="color:var(--sk-danger)">*</span></label>
                <input type="number" id="inpNewBuy"  class="sk-input" step="0.01" min="0" placeholder="০.০০" oninput="calcProfit()">
            </div>
            <div class="sk-field" style="margin-bottom:.5rem;">
                <label class="sk-label">খরচ ৳</label>
                <input type="number" id="inpNewCost" class="sk-input" step="0.01" min="0" value="15" oninput="calcProfit()">
            </div>
            <div class="sk-field" style="margin-bottom:.5rem;">
                <label class="sk-label">বিক্রয় ৳ <span style="color:var(--sk-danger)">*</span></label>
                <input type="number" id="inpNewSell" class="sk-input" step="0.01" min="0" placeholder="০.০০" oninput="calcProfit()">
            </div>
        </div>
        <div id="excProfitBox" style="display:none;margin-bottom:.625rem;">
            <span class="exc-profit-pill" id="excProfitPill">—</span>
        </div>
        <div class="sk-field" style="margin-bottom:0;">
            <label class="sk-label">নতুন পণ্যের ছবি <span style="color:var(--sk-muted);font-weight:500">(ঐচ্ছিক)</span></label>
            <div class="exc-cam-trigger" onclick="openExcCam()">
                <i class="fas fa-camera-retro"></i>
                <div>
                    <div class="exc-cam-lbl">ছবি তুলুন / বেছে নিন</div>
                    <div class="exc-cam-sub" id="excCamSub">ক্যামেরা বা গ্যালারি</div>
                </div>
                <i class="fas fa-chevron-right" style="color:var(--sk-muted);margin-left:auto;font-size:.75rem;"></i>
            </div>
            <img id="excNewImgPreview" class="exc-img-preview" alt="preview">
            <input type="hidden" id="inpNewImgB64">
        </div>
    </div>

    <!-- ধাপ ৫: নোট + সাপ্লায়ার নাম + সাবমিট -->
    <div class="exc-new-section sk-card" id="excSubmitSection">

        <!-- সাপ্লায়ারের নাম (only for return_only) -->
        <div class="exc-supplier-row" id="excSupplierNameWrap">
            <div class="exc-supplier-label">
                <i class="fas fa-store"></i>
                সাপ্লায়ারের নাম <span style="color:var(--sk-danger)">*</span>
                <span style="font-size:.65rem;font-weight:600;color:#92400e;">(বাধ্যতামূলক)</span>
            </div>
            <input type="text" id="inpSupplierName" class="sk-input"
                   placeholder="যেমন: ABC Textile, Dhaka"
                   autocomplete="off">
        </div>

        <div class="sk-field" style="margin-bottom:.625rem;">
            <label class="sk-label" id="excNoteLbl">নোট / কারণ <span style="color:var(--sk-muted);font-weight:500">(ঐচ্ছিক)</span></label>
            <textarea id="inpNote" class="sk-textarea" rows="2"
                      placeholder="যেমন: কালার পছন্দ হয়নি, নতুন ডিজাইন নেওয়া হলো..."></textarea>
        </div>

        <button type="button" class="sk-btn sk-btn--accent sk-btn--block sk-btn--lg"
                id="excSubmitBtn" onclick="submitExchange()">
            <i class="fas fa-check-circle"></i>&nbsp; এক্সচেঞ্জ নিশ্চিত করুন
        </button>
    </div>

    <!-- ══ সাম্প্রতিক এক্সচেঞ্জ — paginated ══ -->
    <div class="sk-card" style="margin-bottom:.875rem;" id="excHistCard">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem;">
            <div style="font-size:.85rem;font-weight:800;color:var(--sk-ink);">
                <i class="fas fa-history" style="color:var(--sk-accent);margin-right:.35rem;"></i>সাম্প্রতিক এক্সচেঞ্জ
                <span class="sk-pill sk-pill--accent" id="excHistTotal" style="margin-left:.35rem;"><?= $historyTotal ?></span>
            </div>
            <?php if ($isAdmin): ?>
            <a href="supplier_exchange_report.php" style="font-size:.7rem;font-weight:700;color:var(--sk-accent);text-decoration:none;">
                <i class="fas fa-chart-bar"></i> রিপোর্ট
            </a>
            <?php endif; ?>
        </div>

        <!-- টেবিল -->
        <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <table style="width:100%;border-collapse:collapse;font-size:.72rem;" id="excHistTable">
            <thead>
                <tr style="background:#f8f8fc;border-bottom:2px solid var(--sk-border);">
                    <th style="padding:.4rem .35rem;text-align:left;font-weight:800;color:var(--sk-muted);white-space:nowrap;">নং</th>
                    <th style="padding:.4rem .35rem;text-align:left;font-weight:800;color:var(--sk-muted);white-space:nowrap;">পণ্য</th>
                    <th style="padding:.4rem .35rem;text-align:center;font-weight:800;color:var(--sk-muted);">ধরন</th>
                    <th style="padding:.4rem .35rem;text-align:center;font-weight:800;color:var(--sk-muted);">ফেরত</th>
                    <th style="padding:.4rem .35rem;text-align:center;font-weight:800;color:var(--sk-muted);">নতুন</th>
                    <th style="padding:.4rem .35rem;text-align:center;font-weight:800;color:var(--sk-muted);">স্ট্যাটাস</th>
                    <th style="padding:.4rem .35rem;text-align:right;font-weight:800;color:var(--sk-muted);white-space:nowrap;">তারিখ</th>
                </tr>
            </thead>
            <tbody id="excHistBody">
            <?php foreach ($history as $h):
                $hst = $h['status'] ?? 'approved';
                $ht  = $h['exchange_type'];
                $typeCls = match($ht){ 'new_product'=>'new','same_product'=>'same',default=>'ret' };
                $typeLbl = match($ht){ 'new_product'=>'নতুন পণ্য','same_product'=>'একই পণ্য',default=>'ফেরত' };
                $stCls   = match($hst){ 'approved'=>'ret','cancelled'=>'canc',default=>'pend' };
                $stLbl   = match($hst){ 'approved'=>'অনুমোদিত','cancelled'=>'বাতিল',default=>'পেন্ডিং' };
            ?>
            <tr style="border-bottom:1px solid var(--sk-border);">
                <td style="padding:.4rem .35rem;font-weight:800;color:var(--sk-accent);white-space:nowrap;"><?= htmlspecialchars((string)$h['exchange_no'],ENT_QUOTES,'UTF-8') ?></td>
                <td style="padding:.4rem .35rem;max-width:110px;">
                    <div style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars((string)$h['old_product_code'],ENT_QUOTES,'UTF-8') ?></div>
                    <?php if (!empty($h['new_product_code']) && $h['new_product_code'] !== $h['old_product_code']): ?>
                    <div style="color:var(--sk-success);font-size:.66rem;">→ <?= htmlspecialchars((string)$h['new_product_code'],ENT_QUOTES,'UTF-8') ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:.4rem .35rem;text-align:center;"><span class="exc-hist-badge <?= $typeCls ?>"><?= $typeLbl ?></span></td>
                <td style="padding:.4rem .35rem;text-align:center;color:var(--sk-danger);font-weight:800;">−<?= (int)$h['returned_pieces'] ?></td>
                <td style="padding:.4rem .35rem;text-align:center;color:var(--sk-success);font-weight:800;"><?= (int)($h['received_pieces']??0) > 0 ? '+' . (int)$h['received_pieces'] : '—' ?></td>
                <td style="padding:.4rem .35rem;text-align:center;"><span class="exc-hist-badge <?= $stCls ?>"><?= $stLbl ?></span></td>
                <td style="padding:.4rem .35rem;text-align:right;color:var(--sk-muted);white-space:nowrap;"><?= date('d/m/y', strtotime((string)$h['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
            <tr><td colspan="7" style="padding:1.5rem;text-align:center;color:var(--sk-muted);font-weight:600;">কোনো রেকর্ড নেই</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <div id="excHistPagination" style="display:flex;align-items:center;justify-content:space-between;margin-top:.65rem;gap:.4rem;">
            <button class="sk-btn sk-btn--outline" id="excHistPrev" style="padding:.35rem .7rem;font-size:.72rem;" onclick="excHistGo(excHistCurPage-1)" disabled>
                <i class="fas fa-chevron-left"></i>
            </button>
            <span id="excHistPageInfo" style="font-size:.72rem;font-weight:700;color:var(--sk-muted);">
                পেজ ১ / <?= $historyPages ?>
            </span>
            <button class="sk-btn sk-btn--outline" id="excHistNext" style="padding:.35rem .7rem;font-size:.72rem;" onclick="excHistGo(excHistCurPage+1)" <?= $historyPages <= 1 ? 'disabled' : '' ?>>
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

</div>

<!-- Camera Modal -->
<div id="excCamModal">
    <div class="exc-cam-sheet">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.875rem;">
            <div style="font-weight:800;font-size:.95rem;color:var(--sk-ink);">
                <i class="fas fa-camera" style="color:var(--sk-accent);margin-right:.4rem;"></i>ছবি তুলুন
            </div>
            <button class="sk-iconbtn" onclick="closeExcCam()"><i class="fas fa-times"></i></button>
        </div>
        <div class="exc-cam-loading" id="excCamLoading">
            <i class="fas fa-circle-notch fa-spin" style="font-size:1.5rem;color:var(--sk-accent);"></i>
            ক্যামেরা চালু হচ্ছে...
        </div>
        <video id="excVideo" autoplay playsinline></video>
        <canvas id="excCanvas" style="display:none;"></canvas>
        <div style="display:flex;gap:.625rem;margin-top:.875rem;">
            <button class="sk-btn sk-btn--accent" style="flex:1;" onclick="captureExcPhoto()">
                <i class="fas fa-camera"></i>&nbsp; ছবি তুলুন
            </button>
            <button class="sk-btn sk-btn--outline" onclick="closeExcCam()">বাতিল</button>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= $csrf ?>';
const PAGE = 'supplier_exchange.php';
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
let ST = { product:null, type:'', qrOn:false, qrInst:null, camStr:null };

function skToggleDrawer() {
    document.getElementById('skDrawer').classList.toggle('open');
    document.getElementById('skOverlay').classList.toggle('active');
}
function onOldCodeType() {
    let el = document.getElementById('inpOldCode');
    let v  = el.value.toUpperCase();
    if (v && !v.startsWith('SKF-')) el.value = 'SKF-' + v.replace(/^SKF-?/i,'');
}
function toggleQr() { ST.qrOn ? stopQr() : startQr(); }
function startQr() {
    let area = document.getElementById('excScanArea');
    let btn  = document.getElementById('excQrBtn');
    area.classList.add('open');
    document.getElementById('excReader').innerHTML = '';
    ST.qrInst = new Html5Qrcode('excReader');
    ST.qrInst.start(
        {facingMode:'environment'},
        {fps:12,qrbox:{width:220,height:80},aspectRatio:2.5},
        function(text) { document.getElementById('inpOldCode').value=text; stopQr(); doSearch(); },
        function(){}
    ).then(function() {
        ST.qrOn=true; btn.innerHTML='<i class="fas fa-times"></i>'; btn.classList.add('active');
    }).catch(function() {
        area.classList.remove('open');
        Swal.fire({icon:'error',title:'ক্যামেরা এরর',text:'QR স্ক্যানার চালু হয়নি।',confirmButtonColor:'#dc3545'});
    });
}
function stopQr() {
    let btn = document.getElementById('excQrBtn');
    btn.innerHTML='<i class="fas fa-qrcode"></i>'; btn.classList.remove('active');
    if (ST.qrInst && ST.qrOn) {
        ST.qrInst.stop().then(function(){ST.qrOn=false;document.getElementById('excScanArea').classList.remove('open');})
        .catch(function(){ST.qrOn=false;document.getElementById('excScanArea').classList.remove('open');});
    } else { document.getElementById('excScanArea').classList.remove('open'); }
}
function doSearch() {
    let code = document.getElementById('inpOldCode').value.trim();
    if (!code) { Swal.fire({icon:'warning',title:'কোড দিন',text:'প্রোডাক্ট কোড লিখুন বা QR স্ক্যান করুন!',confirmButtonColor:'#6366f1'}); return; }
    let btn = document.getElementById('excSearchBtn');
    let orig = btn.innerHTML;
    btn.innerHTML='<i class="fas fa-circle-notch fa-spin"></i>'; btn.disabled=true;
    $.ajax({url:PAGE,type:'POST',dataType:'json',
        data:{ajax_action:'search_product',csrf_token:CSRF,product_code:code},
        success:function(r){
            btn.innerHTML=orig; btn.disabled=false;
            if (r.status==='session_expired'){location.href='../index.php';return;}
            if (r.status==='success'){ST.product=r;renderFoundProduct(r);}
            else{hideFoundCard();Swal.fire({icon:'error',title:'পাওয়া যায়নি',text:r.message,confirmButtonColor:'#dc3545'});}
        },
        error:function(){btn.innerHTML=orig;btn.disabled=false;Swal.fire({icon:'error',title:'সার্ভার এরর',text:'কানেকশন ব্যর্থ!',confirmButtonColor:'#dc3545'});}
    });
}
function renderFoundProduct(p) {
    document.getElementById('excProdImg').src          = p.image_path || 'logo.png';
    document.getElementById('excProdName').textContent   = p.name;
    document.getElementById('excProdCat').textContent    = p.cat_name;
    document.getElementById('excProdPrices').textContent = 'ক্রয়: ৳'+p.buy_price+' · বিক্রয়: ৳'+p.cash_sell;
    document.getElementById('excProdStockNum').textContent = p.pieces;
    document.getElementById('excProdStock').className = 'exc-found-stock'+(p.pieces<10?' low':'');
    document.getElementById('inpRetPcs').value = '';
    document.getElementById('inpRetPcs').max   = p.pieces;
    document.getElementById('inpRetVal').value = '';
    resetTypeButtons(); hideNewForm();
    document.getElementById('excFoundCard').classList.add('show');
    document.getElementById('excFoundCard').scrollIntoView({behavior:'smooth',block:'nearest'});
}
function hideFoundCard() {
    ST.product=null; document.getElementById('excFoundCard').classList.remove('show'); hideNewForm();
}
function onRetPcsChange() {
    if (!ST.product) return;
    let el=document.getElementById('inpRetPcs'), pcs=parseInt(el.value)||0;
    if (pcs>ST.product.pieces){pcs=ST.product.pieces;el.value=pcs;}
    document.getElementById('inpRetVal').value = pcs>0 ? '৳ '+(pcs*ST.product.buy_price).toLocaleString('bn-BD') : '';
}

// ── Exchange type (BUG FIXED) ─────────────────────────────────────
function setType(t) {
    ST.type = t;
    resetTypeButtons();
    let btnMap = {new_product:'tbn_new', same_product:'tbn_same', return_only:'tbn_ret'};
    let clsMap = {new_product:'active-new', same_product:'active-same', return_only:'active-ret'};
    let el = document.getElementById(btnMap[t]);
    if (el) el.classList.add(clsMap[t]);

    if (t === 'return_only') {
        // ✅ FIX: নতুন পণ্য ফর্ম লুকাও কিন্তু Submit Section দেখাও
        document.getElementById('excNewForm').classList.remove('show');
        document.getElementById('excSubmitSection').classList.add('show');

        // সাপ্লায়ার নাম ফিল্ড দেখাও
        document.getElementById('excSupplierNameWrap').classList.add('show');
        document.getElementById('inpSupplierName').focus();

        // বাটন + placeholder আপডেট
        document.getElementById('excSubmitBtn').innerHTML =
            '<i class="fas fa-paper-plane"></i>&nbsp; অনুমোদনের জন্য পাঠান';
        document.getElementById('excNoteLbl').innerHTML =
            'নোট <span style="color:var(--sk-muted);font-weight:500">(ঐচ্ছিক)</span>';
        document.getElementById('inpNote').placeholder =
            'যেমন: কালার পছন্দ হয়নি, পুরানো ডিজাইন...';

        document.getElementById('excSubmitSection').scrollIntoView({behavior:'smooth',block:'nearest'});
    } else {
        // সাপ্লায়ার নাম লুকাও + রিসেট
        document.getElementById('excSupplierNameWrap').classList.remove('show');
        document.getElementById('inpSupplierName').value = '';

        document.getElementById('excNewCodeWrap').style.display = (t==='new_product') ? 'block' : 'none';
        document.getElementById('excNewFormTitle').textContent  =
            (t==='same_product') ? 'একই পণ্যের নতুন তথ্য' : 'নতুন পণ্যের তথ্য';
        document.getElementById('excSubmitBtn').innerHTML = IS_ADMIN
            ? '<i class="fas fa-check-circle"></i>&nbsp; এক্সচেঞ্জ নিশ্চিত করুন'
            : '<i class="fas fa-paper-plane"></i>&nbsp; অনুমোদনের জন্য পাঠান';
        document.getElementById('excNoteLbl').innerHTML =
            'নোট / কারণ <span style="color:var(--sk-muted);font-weight:500">(ঐচ্ছিক)</span>';
        document.getElementById('inpNote').placeholder =
            'যেমন: কালার পছন্দ হয়নি, নতুন ডিজাইন নেওয়া হলো...';

        document.getElementById('excNewForm').classList.add('show');
        document.getElementById('excSubmitSection').classList.add('show');
        document.getElementById('excNewForm').scrollIntoView({behavior:'smooth',block:'nearest'});
    }
}
function resetTypeButtons() {
    ['tbn_new','tbn_same','tbn_ret'].forEach(id=>{
        let el=document.getElementById(id); if(el) el.className='exc-type-btn';
    });
}
function hideNewForm() {
    document.getElementById('excNewForm').classList.remove('show');
    document.getElementById('excSubmitSection').classList.remove('show');
    document.getElementById('excSupplierNameWrap').classList.remove('show');
}

function calcProfit() {
    let buy=parseFloat(document.getElementById('inpNewBuy').value)||0;
    let cost=parseFloat(document.getElementById('inpNewCost').value)||15;
    let sell=parseFloat(document.getElementById('inpNewSell').value)||0;
    let box=document.getElementById('excProfitBox'), pill=document.getElementById('excProfitPill');
    if (buy>0||sell>0) {
        box.style.display='inline-block';
        let p=sell-buy-cost;
        if (!sell){pill.textContent='—';pill.style.color='var(--sk-muted)';}
        else if (p>0){pill.textContent='✓ লাভ: +৳'+p.toFixed(2);pill.style.color='var(--sk-success)';}
        else if (p===0){pill.textContent='= সমান';pill.style.color='var(--sk-muted)';}
        else{pill.textContent='⚠ ক্ষতি: ৳'+Math.abs(p).toFixed(2);pill.style.color='var(--sk-danger)';}
    } else { box.style.display='none'; }
}

function openExcCam() {
    let modal=document.getElementById('excCamModal'), vid=document.getElementById('excVideo'), load=document.getElementById('excCamLoading');
    modal.classList.add('open'); vid.style.display='none'; load.style.display='flex';
    navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false})
        .then(function(s){ST.camStr=s;vid.srcObject=s;vid.onloadedmetadata=function(){vid.play();vid.style.display='block';load.style.display='none';};})
        .catch(function(){closeExcCam();Swal.fire({icon:'error',title:'ক্যামেরা চালু হয়নি',text:'অনুমতি দিন।',confirmButtonColor:'#dc3545'});});
}
function closeExcCam() {
    if (ST.camStr){ST.camStr.getTracks().forEach(t=>t.stop());ST.camStr=null;}
    document.getElementById('excVideo').srcObject=null;
    document.getElementById('excVideo').style.display='none';
    document.getElementById('excCamModal').classList.remove('open');
}
function captureExcPhoto() {
    let vid=document.getElementById('excVideo'), canvas=document.getElementById('excCanvas');
    if (!ST.camStr) return;
    canvas.width=vid.videoWidth||640; canvas.height=vid.videoHeight||480;
    canvas.getContext('2d').drawImage(vid,0,0);
    let url=canvas.toDataURL('image/jpeg',0.82);
    document.getElementById('inpNewImgB64').value=url;
    let prev=document.getElementById('excNewImgPreview'); prev.src=url; prev.classList.add('show');
    document.getElementById('excCamSub').textContent='ছবি সেট হয়েছে ✓';
    closeExcCam();
}

function submitExchange() {
    if (!ST.product){Swal.fire({icon:'warning',title:'পণ্য বেছে নিন',text:'আগে পুরানো পণ্য খুঁজুন!'});return;}
    let retPcs=parseInt(document.getElementById('inpRetPcs').value)||0;
    if (retPcs<=0){Swal.fire({icon:'warning',title:'পিস দিন',text:'কত পিস ফেরত দিচ্ছেন লিখুন!'});return;}
    if (!ST.type){Swal.fire({icon:'warning',title:'টাইপ বেছে নিন',text:'এক্সচেঞ্জ টাইপ সিলেক্ট করুন!'});return;}

    // return_only: supplier name বাধ্যতামূলক
    if (ST.type==='return_only') {
        let sname=document.getElementById('inpSupplierName').value.trim();
        if (!sname){Swal.fire({icon:'warning',title:'সাপ্লায়ারের নাম',text:'সাপ্লায়ারের নাম লিখুন!',confirmButtonColor:'#f59e0b'});return;}
    }

    if (ST.type!=='return_only') {
        if (!document.getElementById('inpNewCat').value)
            {Swal.fire({icon:'warning',title:'ক্যাটাগরি',text:'নতুন পণ্যের ক্যাটাগরি বেছে নিন!'});return;}
        if (!(parseInt(document.getElementById('inpNewPcs').value)>0))
            {Swal.fire({icon:'warning',title:'পিস',text:'নতুন পণ্যের পিস সংখ্যা দিন!'});return;}
        if (!(parseFloat(document.getElementById('inpNewBuy').value)>0))
            {Swal.fire({icon:'warning',title:'ক্রয় মূল্য',text:'নতুন পণ্যের ক্রয় মূল্য দিন!'});return;}
        if (!(parseFloat(document.getElementById('inpNewSell').value)>0))
            {Swal.fire({icon:'warning',title:'বিক্রয় মূল্য',text:'নতুন পণ্যের বিক্রয় মূল্য দিন!'});return;}
    }

    let willBePending = !IS_ADMIN || ST.type==='return_only';
    let typeLabels = {
        new_product:  willBePending ? 'নতুন পণ্য → অনুমোদন লাগবে' : 'নতুন পণ্য',
        same_product: willBePending ? 'একই পণ্য আপডেট → অনুমোদন লাগবে' : 'একই পণ্য আপডেট',
        return_only:  'শুধু ফেরত → অনুমোদন লাগবে',
    };
    let confirmHtml = `<b>${ST.product.product_code}</b> থেকে <b>${retPcs} পিস</b> ফেরত<br>ধরন: <b>${typeLabels[ST.type]}</b>`;
    if (willBePending) {
        confirmHtml += `<br><span style="color:#f59e0b;font-size:.85em;">⚠ অ্যাডমিন অনুমোদন না করা পর্যন্ত স্টক/পণ্য আপডেট হবে না</span>`;
    }

    Swal.fire({
        icon:'question', title:'নিশ্চিত করুন?', html:confirmHtml,
        showCancelButton:true,
        confirmButtonText:'<i class="fas fa-check"></i> হ্যাঁ, নিশ্চিত',
        cancelButtonText:'বাতিল',
        confirmButtonColor:'#059669', cancelButtonColor:'#6b7280', reverseButtons:true,
    }).then(function(res){
        if (!res.isConfirmed) return;
        let btn=document.getElementById('excSubmitBtn'), orig=btn.innerHTML;
        btn.disabled=true; btn.innerHTML='<i class="fas fa-circle-notch fa-spin"></i>&nbsp; প্রসেসিং...';
        let data={
            ajax_action:'process_exchange', csrf_token:CSRF,
            old_product_code:ST.product.product_code,
            returned_pieces:retPcs, exchange_type:ST.type,
            note:document.getElementById('inpNote').value.trim(),
            supplier_name:document.getElementById('inpSupplierName').value.trim(),
        };
        if (ST.type!=='return_only') {
            data.new_category_id=document.getElementById('inpNewCat').value;
            data.new_pieces=document.getElementById('inpNewPcs').value;
            data.new_buy_price=document.getElementById('inpNewBuy').value;
            data.new_cost=document.getElementById('inpNewCost').value||15;
            data.new_cash_sell=document.getElementById('inpNewSell').value;
            data.new_image_b64=document.getElementById('inpNewImgB64').value;
            if (ST.type==='new_product') data.new_product_code=document.getElementById('inpNewCode').value.trim();
        }
        $.ajax({url:PAGE,type:'POST',dataType:'json',data:data,
            success:function(r){
                btn.disabled=false; btn.innerHTML=orig;
                if (r.status==='session_expired'){location.href='../index.php';return;}
                if (r.status==='success') {
                    let icon = r.is_pending ? 'info' : 'success';
                    let title = r.is_pending ? 'পাঠানো হয়েছে!' : 'সফল!';
                    Swal.fire({icon:icon,title:title,html:r.message.replace(/\n/g,'<br>'),
                               confirmButtonColor:r.is_pending?'#f59e0b':'#059669'})
                        .then(()=>location.reload());
                } else {
                    Swal.fire({icon:'error',title:'সমস্যা',text:r.message,confirmButtonColor:'#dc3545'});
                }
            },
            error:function(){btn.disabled=false;btn.innerHTML=orig;Swal.fire({icon:'error',title:'সার্ভার এরর',text:'সংযোগ ব্যর্থ!',confirmButtonColor:'#dc3545'});}
        });
    });
}

// ── হিস্টরি পেজিনেশন ──────────────────────────────────────────
let excHistCurPage = 1;
const EXC_HIST_TOTAL_PAGES = <?= $historyPages ?>;

function excHistGo(p) {
    if (p < 1 || p > EXC_HIST_TOTAL_PAGES) return;
    excHistCurPage = p;
    $.post(PAGE, {ajax_action:'load_history', csrf_token:CSRF, page:p}, function(r) {
        if (r.status !== 'success') return;
        let rows = r.rows, html = '';
        if (!rows.length) {
            html = '<tr><td colspan="7" style="padding:1.5rem;text-align:center;color:var(--sk-muted);font-weight:600;">কোনো রেকর্ড নেই</td></tr>';
        } else {
            rows.forEach(h => {
                let typeLbl = {new_product:'নতুন পণ্য', same_product:'একই পণ্য', return_only:'ফেরত'}[h.exchange_type] ?? '—';
                let typeCls = {new_product:'new', same_product:'same', return_only:'ret'}[h.exchange_type] ?? 'ret';
                let stLbl   = {approved:'অনুমোদিত', cancelled:'বাতিল', pending:'পেন্ডিং'}[h.status] ?? h.status;
                let stCls   = {approved:'ret', cancelled:'canc', pending:'pend'}[h.status] ?? 'ret';
                let newCode = (h.new_product_code && h.new_product_code !== h.old_product_code)
                    ? `<div style="color:var(--sk-success);font-size:.66rem;">→ ${h.new_product_code}</div>` : '';
                let newPcs  = parseInt(h.received_pieces||0) > 0 ? `+${h.received_pieces}` : '—';
                let dt      = h.created_at ? h.created_at.substring(2,10).split('-').reverse().join('/') : '';
                html += `<tr style="border-bottom:1px solid var(--sk-border);">
                    <td style="padding:.4rem .35rem;font-weight:800;color:var(--sk-accent);white-space:nowrap;">${h.exchange_no}</td>
                    <td style="padding:.4rem .35rem;max-width:110px;">
                        <div style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${h.old_product_code}</div>${newCode}
                    </td>
                    <td style="padding:.4rem .35rem;text-align:center;"><span class="exc-hist-badge ${typeCls}">${typeLbl}</span></td>
                    <td style="padding:.4rem .35rem;text-align:center;color:var(--sk-danger);font-weight:800;">−${h.returned_pieces}</td>
                    <td style="padding:.4rem .35rem;text-align:center;color:var(--sk-success);font-weight:800;">${newPcs}</td>
                    <td style="padding:.4rem .35rem;text-align:center;"><span class="exc-hist-badge ${stCls}">${stLbl}</span></td>
                    <td style="padding:.4rem .35rem;text-align:right;color:var(--sk-muted);white-space:nowrap;">${dt}</td>
                </tr>`;
            });
        }
        document.getElementById('excHistBody').innerHTML = html;
        document.getElementById('excHistPageInfo').textContent = `পেজ ${p} / ${r.pages}`;
        document.getElementById('excHistTotal').textContent = r.total;
        document.getElementById('excHistPrev').disabled = (p <= 1);
        document.getElementById('excHistNext').disabled = (p >= r.pages);
    }, 'json');
}
</script>

<?php include 'inventory_bottom_nav.php'; ?>
</body>
</html>
