<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit;
}
include __DIR__ . '/../../db_connect.php';

$action   = $_POST['action'] ?? '';
$staff_id = intval($_POST['staff_id'] ?? 0);
if (!$staff_id) { echo json_encode(['status'=>'error','message'=>'Staff ID দিন']); exit; }

$stmt = $conn->prepare("SELECT id, staff_name FROM staff_info WHERE id = ?");
$stmt->execute([$staff_id]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$staff) { echo json_encode(['status'=>'error','message'=>'Staff পাওয়া যায়নি']); exit; }

if ($action === 'force_verify') {
    $conn->prepare("UPDATE staff_info SET email_verified=1 WHERE id=?")->execute([$staff_id]);
    echo json_encode(['status'=>'success','message'=>$staff['staff_name'].' কে Verified করা হয়েছে!']);
    exit;
}

echo json_encode(['status'=>'error','message'=>'Invalid action']);
?>
