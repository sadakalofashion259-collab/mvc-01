<?php
declare(strict_types=1);

/**
 * SMS Admin Dashboard — entry point.
 * URL: /Suppliers/Sms/
 * শুধু admin / manager অ্যাক্সেস করতে পারবে।
 */
define('SK_APP', true);
require_once dirname(__DIR__) . '/config.php';

Security::boot();
Security::requireLogin();

$conn = Database::connect();
Security::enforceSession($conn);
Security::requireRole(['admin', 'manager'], '/Suppliers/suppliers.php');

$model   = new SupplierModel($conn);
$gateway = SmsGateway::fromDb($conn);
$sms     = new SmsService($conn, $gateway, $model);

$csrf = Security::csrfToken();
$role = Security::role();

/* ---------------- POST (AJAX/JSON) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) { ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $reply = static function (array $d): never {
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (!Security::verifyCsrf()) {
        $reply(['ok' => false, 'msg' => 'নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করুন।']);
    }

    $action = (string)($_POST['action'] ?? '');

    switch ($action) {
        case 'toggle_sms': {
            try {
                $enabled = $model->toggleSmsEnabled((int)($_POST['sms_id'] ?? 0));
                $reply(['ok' => true, 'enabled' => $enabled]);
            } catch (\Throwable $e) {
                $reply(['ok' => false, 'msg' => 'সার্ভার ত্রুটি।']);
            }
            break;
        }

        case 'test_sms': {
            $phone = trim((string)($_POST['phone'] ?? ''));
            $digitsOnly = preg_replace('/[^0-9+]/', '', $phone) ?? '';
            if ($phone === '' || $digitsOnly === '' || mb_strlen($phone) > 25) {
                $reply(['ok' => false, 'msg' => 'সঠিক মোবাইল নম্বর দিন।']);
            }
            $res = $sms->sendTest($phone, trim((string)($_POST['message'] ?? '')));
            $reply($res);
        }

        case 'save_template': { // admin only
            if ($role !== 'admin') {
                $reply(['ok' => false, 'msg' => 'শুধু অ্যাডমিন টেমপ্লেট এডিট করতে পারবেন।']);
            }
            $ok = $sms->saveTemplate(
                (int)($_POST['tpl_id'] ?? 0),
                trim((string)($_POST['title'] ?? '')),
                (string)($_POST['body'] ?? ''),
                (string)($_POST['is_active'] ?? '1') === '1'
            );
            $reply(['ok' => $ok, 'msg' => $ok ? 'টেমপ্লেট সেভ হয়েছে।' : 'সেভ করা যায়নি।']);
        }

        case 'save_gateway': { // admin only
            if ($role !== 'admin') {
                $reply(['ok' => false, 'msg' => 'শুধু অ্যাডমিন গেটওয়ে এডিট করতে পারবেন।']);
            }
            $ok = $sms->saveGateway(
                trim((string)($_POST['sender'] ?? '')),
                (string)($_POST['trans_type'] ?? 'T'),
                (string)($_POST['enabled'] ?? '1') === '1'
            );
            $reply(['ok' => $ok, 'msg' => $ok ? 'গেটওয়ে সেটিং সেভ হয়েছে।' : 'সেভ করা যায়নি।']);
        }

        default:
            $reply(['ok' => false, 'msg' => 'অজানা অনুরোধ।']);
    }
}

/* ---------------- View data ---------------- */
$cfg       = $gateway->config();
$ready     = $gateway->isReady();
$maskedKey = $gateway->maskedKey();
$suppliers = array_values(array_filter(
    $model->getAllSuppliersForSms(),
    static fn ($s) => ($s['status'] ?? 'active') !== 'inactive'
));
$templates = $sms->allTemplates();
$recent    = $model->getRecentSms(40);
$stats     = $model->getSmsStats();

require SK_BASE . '/Views/sms_dashboard.view.php';
