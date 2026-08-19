<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    // ২০ মিনিট (২০ * ৬০ = ১২০০ সেকেন্ড) সেট করা হলো
    ini_set('session.gc_maxlifetime', '1200');
    session_set_cookie_params(1200);
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/Controllers/StockController.php';

if (!isset($_SESSION['loggedin'])) {
    header("Location: ../index.php");
    exit;
}
$conn->exec("SET NAMES utf8mb4");
date_default_timezone_set('Asia/Dhaka');

$stockController = new StockController($conn);
$stockController->handleRequests();
$viewData = $stockController->getViewData();

$role = $viewData['role'];
$username = $viewData['username'];
$f_date = $viewData['f_date'];
$t_date = $viewData['t_date'];
$sys_locks = $viewData['sys_locks'];
$csrf_token = $viewData['csrf_token'];
$metrics = $viewData['metrics'];
$monthly_grouped = $viewData['monthly_grouped'];
$weekly_grouped = $viewData['weekly_grouped'];
$avg_buy_price = $metrics['avg_buy_price'];

require __DIR__ . '/Views/stock_view.php';
