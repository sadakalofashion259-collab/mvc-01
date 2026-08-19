<?php
declare(strict_types=1);
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function nsLogError(Throwable $e): void {
    $d = __DIR__ . '/../Logs';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    @file_put_contents($d . '/error_log.txt',
        "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage()
        . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL,
        FILE_APPEND);
}

$isAjax = !empty($_POST['ajax_action']);

// ── সেশন/অথ (supplier_exchange.php-এর মতোই) ──────────────────
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
if ($role !== 'admin') {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>'error','message'=>'শুধু অ্যাডমিন এই পেজ ব্যবহার করতে পারেন!']); exit; }
    header("Location: inventory_dashboard.php"); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

$uid      = isset($_SESSION['user_id']) && is_scalar($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$username = isset($_SESSION['username']) && is_string($_SESSION['username']) ? $_SESSION['username'] : 'admin';
date_default_timezone_set('Asia/Dhaka');

// ════════════════════════════════════════════════════════════════
// CONFIG / ENV HELPERS
// ════════════════════════════════════════════════════════════════
$NOTIF_CONFIG_PATH = __DIR__ . '/notification_config.json';

function nsLoadConfig(string $path): array {
    if (!file_exists($path)) return [];
    $raw = @file_get_contents($path);
    $cfg = $raw !== false ? @json_decode($raw, true) : null;
    return is_array($cfg) ? $cfg : [];
}

function nsSaveConfig(string $path, array $cfg): bool {
    return file_put_contents($path, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function nsGetEnvVal(string $key): string {
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

function nsNormalizeBDPhone(string $phone): string {
    $p = preg_replace('/\D/', '', $phone) ?? '';
    if (str_starts_with($p, '880')) return $p;
    if (str_starts_with($p, '0'))   return '880' . substr($p, 1);
    return '880' . $p;
}

function nsSendSms(string $phone, string $message): array {
    $apiKey   = nsGetEnvVal('SMS_APIKEY') ?: nsGetEnvVal('SMS_API_KEY');
    $userName = nsGetEnvVal('S_USERNAME') ?: nsGetEnvVal('SMS_USERNAME');
    $senderId = nsGetEnvVal('SMS_SENDER') ?: nsGetEnvVal('SMS_SENDER_ID');
    if (empty($apiKey) || empty($userName) || empty($senderId)) {
        return [false, '.env-এ SMS_APIKEY / S_USERNAME / SMS_SENDER সেট করা নেই'];
    }
    $payload = json_encode([
        'apiKey'          => $apiKey,
        'userName'        => $userName,
        'senderName'      => $senderId,
        'transactionType' => 'T',
        'mobileNumber'    => nsNormalizeBDPhone($phone),
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

    if ($raw === false) return [false, "cURL এরর: {$err}"];
    $res = json_decode((string)$raw, true);
    $ok  = is_array($res) && (($res['status'] ?? '') === 'Success' || ($res['statusCode'] ?? '') === '200');
    $msg = is_array($res) ? (string)($res['responseResult'] ?? $raw) : (string)$raw;
    return [$ok, $msg];
}

function nsSendEmail(string $to, string $subject, string $htmlBody): array {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'ইমেইল ঠিকানা সঠিক নয়'];
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Sada Kalo Inventory <inventory@sadakalo.com>\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
    return [$ok, $ok ? 'mail() কল সফল হয়েছে' : 'mail() কল ব্যর্থ — সার্ভারের মেইল কনফিগারেশন চেক করুন'];
}

// ════════════════════════════════════════════════════════════════
// AJAX
// ════════════════════════════════════════════════════════════════
if ($isAjax) {
    header('Content-Type: application/json');
    $postCsrf = is_string($_POST['csrf_token'] ?? '') ? (string)$_POST['csrf_token'] : '';
    if ($postCsrf === '' || !hash_equals($csrfToken, $postCsrf)) {
        echo json_encode(['status'=>'error','message'=>'সেশন মেয়াদ শেষ, পেজ রিফ্রেশ করুন!']); exit;
    }
    $action = (string)($_POST['ajax_action'] ?? '');

    try {
        // ── কনফিগ সেভ ──────────────────────────────────────────
        if ($action === 'save_config') {
            $phone = trim((string)($_POST['admin_phone'] ?? ''));
            $email = trim((string)($_POST['admin_email'] ?? ''));
            $baseUrl = trim((string)($_POST['approval_base_url'] ?? ''));

            if ($phone !== '' && !preg_match('/^\d{6,15}$/', preg_replace('/\D/', '', $phone) ?? '')) {
                throw new Exception('ফোন নম্বর সঠিক নয়!');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('ইমেইল ঠিকানা সঠিক নয়!');
            }

            $cfg = nsLoadConfig($NOTIF_CONFIG_PATH);
            $cfg['sms_enabled']       = !empty($_POST['sms_enabled']);
            $cfg['email_enabled']     = !empty($_POST['email_enabled']);
            $cfg['admin_phone']       = $phone;
            $cfg['admin_email']       = $email;
            $cfg['approval_base_url'] = $baseUrl;

            if (!nsSaveConfig($NOTIF_CONFIG_PATH, $cfg)) {
                throw new Exception('কনফিগ ফাইলে লেখা যায়নি — ফাইল পারমিশন চেক করুন!');
            }
            echo json_encode(['status'=>'success','message'=>'সেটিংস সেভ হয়েছে!']); exit;
        }

        // ── টেস্ট SMS ──────────────────────────────────────────
        elseif ($action === 'test_sms') {
            $phone = trim((string)($_POST['phone'] ?? ''));
            if ($phone === '') throw new Exception('ফোন নম্বর দিন!');
            [$ok, $msg] = nsSendSms($phone, 'সাদা কালো ফ্যাশন — এটি একটি টেস্ট SMS। সময়: ' . date('h:i A'));
            echo json_encode(['status'=>$ok?'success':'error','message'=>$ok?"SMS পাঠানো হয়েছে! ({$msg})":"ব্যর্থ: {$msg}"]); exit;
        }

        // ── টেস্ট ইমেইল ────────────────────────────────────────
        elseif ($action === 'test_email') {
            $email = trim((string)($_POST['email'] ?? ''));
            if ($email === '') throw new Exception('ইমেইল ঠিকানা দিন!');
            [$ok, $msg] = nsSendEmail($email, 'সাদা কালো — টেস্ট ইমেইল',
                '<p>এটি সাদা কালো ফ্যাশন ইনভেন্টরি সিস্টেম থেকে পাঠানো একটি টেস্ট ইমেইল।</p><p>সময়: ' . date('d M Y, h:i A') . '</p>');
            echo json_encode(['status'=>$ok?'success':'error','message'=>$ok?"ইমেইল পাঠানো হয়েছে! ({$msg})":"ব্যর্থ: {$msg}"]); exit;
        }

        else {
            echo json_encode(['status'=>'error','message'=>'অজানা অ্যাকশন!']); exit;
        }

    } catch (Throwable $e) {
        nsLogError($e);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
    }
}

// ════════════════════════════════════════════════════════════════
// পেজ রেন্ডার
// ════════════════════════════════════════════════════════════════
$cfg = nsLoadConfig($NOTIF_CONFIG_PATH);

$smsApiKey   = nsGetEnvVal('SMS_APIKEY') ?: nsGetEnvVal('SMS_API_KEY');
$smsUserName = nsGetEnvVal('S_USERNAME') ?: nsGetEnvVal('SMS_USERNAME');
$smsSenderId = nsGetEnvVal('SMS_SENDER') ?: nsGetEnvVal('SMS_SENDER_ID');
$smsConfigured = ($smsApiKey !== '' && $smsUserName !== '' && $smsSenderId !== '');

$mailFuncOk = function_exists('mail');

$csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>নোটিফিকেশন সেটিংস — সাদা কালো ফ্যাশন</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{
    --sk-accent:#4f46e5; --sk-ink:#1e293b; --sk-muted:#64748b;
    --sk-success:#059669; --sk-danger:#dc2626; --sk-warning:#f59e0b;
    --sk-bg:#f4f5fb; --sk-card:#ffffff; --sk-border:#e5e7eb;
}
* { box-sizing:border-box; }
body{
    margin:0; background:var(--sk-bg); color:var(--sk-ink);
    font-family:'Segoe UI',system-ui,sans-serif;
    padding-bottom:2rem;
}
.ns-topbar{
    background:linear-gradient(135deg,#4f46e5,#6366f1);
    color:#fff; padding:1rem 1.1rem 1.4rem;
    border-radius:0 0 18px 18px;
}
.ns-topbar a{ color:#fff; text-decoration:none; font-size:.8rem; opacity:.9; }
.ns-topbar h1{ font-size:1.15rem; margin:.4rem 0 0; font-weight:800; }
.ns-topbar p{ margin:.2rem 0 0; font-size:.75rem; opacity:.85; }

.ns-wrap{ max-width:640px; margin:0 auto; padding:0 .9rem; }
.ns-card{
    background:var(--sk-card); border-radius:14px; padding:1rem 1.1rem;
    margin-top:1rem; box-shadow:0 1px 3px rgba(0,0,0,.06); border:1px solid var(--sk-border);
}
.ns-card-title{
    font-size:.92rem; font-weight:800; display:flex; align-items:center; gap:.5rem;
    margin-bottom:.7rem;
}
.ns-status{
    display:inline-flex; align-items:center; gap:.3rem;
    font-size:.68rem; font-weight:800; padding:.2rem .55rem; border-radius:99px;
    margin-left:auto;
}
.ns-status.ok  { background:rgba(5,150,105,.12); color:var(--sk-success); }
.ns-status.bad { background:rgba(220,38,38,.1);  color:var(--sk-danger); }

.ns-row-title{ display:flex; align-items:center; }

.ns-field{ margin-bottom:.7rem; }
.ns-label{ font-size:.72rem; font-weight:700; color:var(--sk-muted); display:block; margin-bottom:.25rem; }
.ns-input{
    width:100%; padding:.55rem .7rem; border:1.5px solid var(--sk-border); border-radius:9px;
    font-size:.85rem; font-family:inherit; background:#fbfbfe;
}
.ns-input:focus{ outline:none; border-color:var(--sk-accent); background:#fff; }

.ns-switch-row{
    display:flex; align-items:center; justify-content:space-between;
    padding:.5rem 0; border-bottom:1px solid #f1f1f5;
}
.ns-switch-row:last-child{ border-bottom:none; }
.ns-switch-label{ font-size:.82rem; font-weight:700; }
.ns-switch-sub{ font-size:.68rem; color:var(--sk-muted); font-weight:500; }
.ns-switch{ position:relative; width:44px; height:24px; flex-shrink:0; }
.ns-switch input{ opacity:0; width:0; height:0; }
.ns-slider{
    position:absolute; cursor:pointer; inset:0; background:#cbd5e1;
    border-radius:99px; transition:.2s;
}
.ns-slider:before{
    content:''; position:absolute; height:18px; width:18px; left:3px; bottom:3px;
    background:#fff; border-radius:50%; transition:.2s;
}
.ns-switch input:checked + .ns-slider{ background:var(--sk-success); }
.ns-switch input:checked + .ns-slider:before{ transform:translateX(20px); }

.ns-env-item{
    display:flex; align-items:center; gap:.5rem; padding:.4rem 0;
    font-size:.76rem; font-weight:600;
}
.ns-env-item i{ width:16px; }
.ns-env-item.set   i{ color:var(--sk-success); }
.ns-env-item.unset i{ color:var(--sk-danger); }
.ns-env-hint{
    font-size:.7rem; color:var(--sk-muted); margin-top:.5rem; line-height:1.5;
    background:#f8f8fc; padding:.6rem .7rem; border-radius:9px;
}
.ns-env-hint code{ background:#eceffc; padding:.1rem .35rem; border-radius:5px; font-size:.68rem; }

.ns-btn{
    border:none; border-radius:10px; padding:.65rem 1rem; font-size:.82rem; font-weight:800;
    cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; width:100%;
    justify-content:center;
}
.ns-btn--save{ background:var(--sk-accent); color:#fff; margin-top:.4rem; }
.ns-btn--test{ background:#eef0fd; color:var(--sk-accent); margin-top:.5rem; }
.ns-btn:disabled{ opacity:.6; cursor:not-allowed; }

.ns-test-row{ display:flex; gap:.5rem; margin-top:.5rem; }
.ns-test-row .ns-input{ flex:1; }
.ns-test-row .ns-btn{ width:auto; white-space:nowrap; padding:.55rem .9rem; margin-top:0; }
</style>
</head>
<body>

<div class="ns-topbar">
    <a href="supplier_exchange.php"><i class="fas fa-arrow-left"></i> ফিরে যান</a>
    <h1><i class="fas fa-bell"></i> নোটিফিকেশন সেটিংস</h1>
    <p>SMS ও ইমেইল অ্যালার্ট কনফিগার ও টেস্ট করুন</p>
</div>

<div class="ns-wrap">

    <!-- ══ চালু/বন্ধ + যোগাযোগ তথ্য ══ -->
    <div class="ns-card">
        <div class="ns-card-title"><i class="fas fa-sliders-h" style="color:var(--sk-accent);"></i> সাধারণ সেটিংস</div>

        <div class="ns-switch-row">
            <div>
                <div class="ns-switch-label">SMS নোটিফিকেশন</div>
                <div class="ns-switch-sub">পেন্ডিং রিকোয়েস্ট হলে অ্যাডমিনকে SMS যাবে</div>
            </div>
            <label class="ns-switch">
                <input type="checkbox" id="smsEnabled" <?= !empty($cfg['sms_enabled']) ? 'checked' : '' ?>>
                <span class="ns-slider"></span>
            </label>
        </div>
        <div class="ns-switch-row">
            <div>
                <div class="ns-switch-label">ইমেইল নোটিফিকেশন</div>
                <div class="ns-switch-sub">পেন্ডিং রিকোয়েস্ট হলে অ্যাডমিনকে ইমেইল যাবে</div>
            </div>
            <label class="ns-switch">
                <input type="checkbox" id="emailEnabled" <?= !empty($cfg['email_enabled']) ? 'checked' : '' ?>>
                <span class="ns-slider"></span>
            </label>
        </div>

        <div class="ns-field" style="margin-top:.9rem;">
            <label class="ns-label"><i class="fas fa-phone"></i> অ্যাডমিনের ফোন নম্বর</label>
            <input type="text" class="ns-input" id="adminPhone" placeholder="01XXXXXXXXX"
                   value="<?= htmlspecialchars((string)($cfg['admin_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="ns-field">
            <label class="ns-label"><i class="fas fa-envelope"></i> অ্যাডমিনের ইমেইল</label>
            <input type="email" class="ns-input" id="adminEmail" placeholder="admin@example.com"
                   value="<?= htmlspecialchars((string)($cfg['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="ns-field" style="margin-bottom:0;">
            <label class="ns-label"><i class="fas fa-link"></i> অনুমোদন পেজের লিংক (ইমেইলে দেখানো হয়)</label>
            <input type="text" class="ns-input" id="baseUrl" placeholder="https://sadakalofashion.com/Inventory"
                   value="<?= htmlspecialchars((string)($cfg['approval_base_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <button class="ns-btn ns-btn--save" id="btnSaveConfig">
            <i class="fas fa-save"></i> সেটিংস সেভ করুন
        </button>
    </div>

    <!-- ══ SMS স্ট্যাটাস + টেস্ট ══ -->
    <div class="ns-card">
        <div class="ns-card-title ns-row-title">
            <i class="fas fa-sms" style="color:var(--sk-accent);"></i> SMS (MiMSMS)
            <span class="ns-status <?= $smsConfigured ? 'ok' : 'bad' ?>">
                <i class="fas <?= $smsConfigured ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <?= $smsConfigured ? 'কনফিগার করা আছে' : 'কনফিগার করা নেই' ?>
            </span>
        </div>

        <div class="ns-env-item <?= $smsApiKey   !== '' ? 'set' : 'unset' ?>">
            <i class="fas <?= $smsApiKey !== '' ? 'fa-check' : 'fa-xmark' ?>"></i> SMS_APIKEY
            <?php if ($smsApiKey !== ''): ?>
            <span style="color:var(--sk-muted);font-weight:500;">— <?= htmlspecialchars(substr($smsApiKey,0,4).str_repeat('•',max(0,strlen($smsApiKey)-8)).substr($smsApiKey,-4),ENT_QUOTES,'UTF-8') ?></span>
            <?php endif; ?>
        </div>
        <div class="ns-env-item <?= $smsUserName !== '' ? 'set' : 'unset' ?>">
            <i class="fas <?= $smsUserName !== '' ? 'fa-check' : 'fa-xmark' ?>"></i> S_USERNAME
            <?php if ($smsUserName !== ''): ?>
            <span style="color:var(--sk-muted);font-weight:500;">— <?= htmlspecialchars($smsUserName,ENT_QUOTES,'UTF-8') ?></span>
            <?php endif; ?>
        </div>
        <div class="ns-env-item <?= $smsSenderId !== '' ? 'set' : 'unset' ?>">
            <i class="fas <?= $smsSenderId !== '' ? 'fa-check' : 'fa-xmark' ?>"></i> SMS_SENDER
            <?php if ($smsSenderId !== ''): ?>
            <span style="color:var(--sk-muted);font-weight:500;">— <?= htmlspecialchars($smsSenderId,ENT_QUOTES,'UTF-8') ?></span>
            <?php endif; ?>
        </div>
        <?php if ($smsConfigured): ?>
        <div class="ns-env-hint">
            উপরের username/key ঠিক আপনার MiMSMS প্যানেলের সাথে মিলিয়ে দেখুন (sms.mimsms.com → Utility → Developer)। ভুল থাকলে <b>.env</b> ফাইলে ঠিক করে আবার টেস্ট SMS পাঠান।
        </div>
        <?php endif; ?>

        <?php if (!$smsConfigured): ?>
        <div class="ns-env-hint">
            <b>.env</b> ফাইলে (<code>/home/sadakalo/App/.env</code>) এই ৩টা লাইন যোগ করুন:<br>
            <code>SMS_APIKEY=...</code><br>
            <code>S_USERNAME=আপনার MiMSMS লগইন ইমেইল</code><br>
            <code>SMS_SENDER=...</code><br>
            এগুলো পাবেন sms.mimsms.com → Utility → Developer থেকে। আর সার্ভারের IP/ডোমেইন সেখানে whitelist করা আছে কিনা যাচাই করুন।
        </div>
        <?php endif; ?>

        <div class="ns-test-row">
            <input type="text" class="ns-input" id="testPhone" placeholder="টেস্ট নম্বর (01XXXXXXXXX)"
                   value="<?= htmlspecialchars((string)($cfg['admin_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <button class="ns-btn ns-btn--test" id="btnTestSms" <?= $smsConfigured ? '' : 'disabled' ?>>
                <i class="fas fa-paper-plane"></i> টেস্ট SMS
            </button>
        </div>
    </div>

    <!-- ══ ইমেইল স্ট্যাটাস + টেস্ট ══ -->
    <div class="ns-card">
        <div class="ns-card-title ns-row-title">
            <i class="fas fa-envelope-open-text" style="color:var(--sk-accent);"></i> ইমেইল
            <span class="ns-status <?= $mailFuncOk ? 'ok' : 'bad' ?>">
                <i class="fas <?= $mailFuncOk ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <?= $mailFuncOk ? 'mail() উপলব্ধ' : 'mail() নেই' ?>
            </span>
        </div>
        <div class="ns-env-hint" style="margin-top:0;">
            হোস্টিং সার্ভারের PHP <code>mail()</code> ফাংশন ব্যবহার হয়। অনেক শেয়ার্ড হোস্টিং-এ এটা কাজ করলেও মেইল স্প্যাম ফোল্ডারে যেতে পারে — SPF/DKIM ঠিকভাবে সেট করা থাকলে ভালো ডেলিভারি হয়।
        </div>
        <div class="ns-test-row">
            <input type="email" class="ns-input" id="testEmail" placeholder="টেস্ট ইমেইল ঠিকানা"
                   value="<?= htmlspecialchars((string)($cfg['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <button class="ns-btn ns-btn--test" id="btnTestEmail">
                <i class="fas fa-paper-plane"></i> টেস্ট ইমেইল
            </button>
        </div>
    </div>

</div>

<script>
const CSRF = '<?= $csrf ?>';
const PAGE = 'notification_settings.php';

function post(data) {
    return fetch(PAGE, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data).toString()
    }).then(r => r.json());
}

document.getElementById('btnSaveConfig').addEventListener('click', function () {
    let btn = this, orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> সেভ হচ্ছে...';
    post({
        ajax_action: 'save_config', csrf_token: CSRF,
        sms_enabled: document.getElementById('smsEnabled').checked ? '1' : '',
        email_enabled: document.getElementById('emailEnabled').checked ? '1' : '',
        admin_phone: document.getElementById('adminPhone').value.trim(),
        admin_email: document.getElementById('adminEmail').value.trim(),
        approval_base_url: document.getElementById('baseUrl').value.trim(),
    }).then(r => {
        btn.disabled = false; btn.innerHTML = orig;
        if (r.status === 'session_expired') { location.reload(); return; }
        Swal.fire({icon: r.status==='success'?'success':'error', title: r.status==='success'?'সফল!':'ত্রুটি', text: r.message, timer: r.status==='success'?1600:undefined});
    }).catch(() => { btn.disabled = false; btn.innerHTML = orig; Swal.fire({icon:'error', title:'সার্ভার ত্রুটি!'}); });
});

document.getElementById('btnTestSms').addEventListener('click', function () {
    let phone = document.getElementById('testPhone').value.trim();
    if (!phone) { Swal.fire({icon:'warning', title:'নম্বর দিন!'}); return; }
    let btn = this, orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> পাঠানো হচ্ছে...';
    post({ajax_action:'test_sms', csrf_token:CSRF, phone}).then(r => {
        btn.disabled = false; btn.innerHTML = orig;
        if (r.status === 'session_expired') { location.reload(); return; }
        Swal.fire({icon: r.status==='success'?'success':'error', title: r.status==='success'?'পাঠানো হয়েছে!':'ব্যর্থ', text: r.message});
    }).catch(() => { btn.disabled = false; btn.innerHTML = orig; Swal.fire({icon:'error', title:'সার্ভার ত্রুটি!'}); });
});

document.getElementById('btnTestEmail').addEventListener('click', function () {
    let email = document.getElementById('testEmail').value.trim();
    if (!email) { Swal.fire({icon:'warning', title:'ইমেইল দিন!'}); return; }
    let btn = this, orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> পাঠানো হচ্ছে...';
    post({ajax_action:'test_email', csrf_token:CSRF, email}).then(r => {
        btn.disabled = false; btn.innerHTML = orig;
        if (r.status === 'session_expired') { location.reload(); return; }
        Swal.fire({icon: r.status==='success'?'success':'error', title: r.status==='success'?'পাঠানো হয়েছে!':'ব্যর্থ', text: r.message});
    }).catch(() => { btn.disabled = false; btn.innerHTML = orig; Swal.fire({icon:'error', title:'সার্ভার ত্রুটি!'}); });
});
</script>
</body>
</html>
