<?php
declare(strict_types=1);

require_once __DIR__ . '/../Core/Bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../index.php');
    exit;
}

$role     = $_SESSION['role'] ?? 'viewer';
$sess_usr = $_SESSION['username'] ?? '';

if ($role === 'manager' || $role === 'user') {
    $from_date = date('Y-m-d');
    $to_date   = date('Y-m-d');
} else {
    $from_date = (isset($_GET['from_date']) && $_GET['from_date'] !== '')
        ? $_GET['from_date'] : date('y-m-01');
    $to_date   = (isset($_GET['to_date']) && $_GET['to_date'] !== '')
        ? $_GET['to_date'] : date('y-m-d');
}

require_once __DIR__ . '/models/ReportModel.php';
require_once __DIR__ . '/helpers/ReportHelper.php';

$reportModel = new ReportModel($conn, $from_date, $to_date);
$data = $reportModel->getAllData();

$success_msg = $_SESSION['success_msg'] ?? null;
$error_msg   = $_SESSION['error_msg']   ?? null;
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

extract($data);
require_once __DIR__ . '/views/history_view.php';
