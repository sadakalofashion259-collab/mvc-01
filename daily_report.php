<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Dhaka');

// 1. Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. Includes
require_once 'db_connect.php';

require_once 'Models/DailyReportModel.php';
require_once 'Controllers/DailyReportController.php';

if (isset($conn)) {
    $conn->exec("SET NAMES 'utf8'");
}

// 3. Auth Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

$role = (string) ($_SESSION['role'] ?? '');

// 4. Initialize MVC
$model = new DailyReportModel($conn);
$controller = new DailyReportController($model);

// 5. Handle AJAX Delete Request
$controller->handleAjaxRequest($role);

// 6. Fetch Data for View
$viewData = $controller->getPageData($role);

// 7. Load View
require_once 'Views/daily_report_view.php';