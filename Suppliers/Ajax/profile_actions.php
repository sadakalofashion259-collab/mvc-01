<?php
declare(strict_types=1);

/**
 * AJAX endpoint — সাপ্লায়ার প্রোফাইল পেজের কাজ।
 * হ্যান্ডল করে: লেনদেন সেভ (+auto SMS), লেনদেন এডিট/ডিলিট, নির্দিষ্ট লেনদেনের SMS।
 * URL: /Suppliers/Ajax/profile_actions.php?id=<supplierId>  (শুধু POST)
 */
define('SK_APP', true);
require_once dirname(__DIR__) . '/config.php';

Security::boot();
Security::requireLogin();

$conn = Database::connect();
Security::enforceSession($conn);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'সাপ্লায়ার আইডি নেই।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$controller = new SupplierController($conn);
$controller->handleSupplierProfileRequests($id);   // মিলে গেলে ভেতরেই JSON দিয়ে exit করে

if (ob_get_length()) { ob_clean(); }
http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'msg' => 'অজানা বা ফাঁকা অনুরোধ।'], JSON_UNESCAPED_UNICODE);
