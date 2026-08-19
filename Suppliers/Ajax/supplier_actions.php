<?php
declare(strict_types=1);

/**
 * AJAX endpoint — সাপ্লায়ার তালিকা পেজের কাজ।
 * হ্যান্ডল করে: এডিট, স্ট্যাটাস টগল, SMS টগল, ডিলিট (সব JSON রিটার্ন)।
 * URL: /Suppliers/Ajax/supplier_actions.php  (শুধু POST)
 */
define('SK_APP', true);
require_once dirname(__DIR__) . '/config.php';

Security::boot();
Security::requireLogin();

$conn = Database::connect();
Security::enforceSession($conn);

$controller = new SupplierController($conn);
$controller->handleSuppliersListRequests();   // মিলে গেলে ভেতরেই JSON দিয়ে exit করে

// কোনো অ্যাকশন না মিললে:
if (ob_get_length()) { ob_clean(); }
http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'msg' => 'অজানা বা ফাঁকা অনুরোধ।'], JSON_UNESCAPED_UNICODE);
