<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once 'db_connect.php';
require_once __DIR__ . '/Controllers/SalesController.php';

try {
    $salesController = new SalesController($conn);
    $salesController->processSaleRequest();
} catch (\Exception $e) {
    http_response_code(500);
    echo "Fatal System Error: " . $e->getMessage();
    exit;
}
?>