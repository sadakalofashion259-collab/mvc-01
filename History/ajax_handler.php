<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/../db_connect.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'লগইন প্রয়োজন']);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'অনুমতি নেই']);
    exit;
}

header('Content-Type: application/json');
$sess_usr = $_SESSION['username'] ?? '';

function verifyAdminPass(PDO $conn, string $username, string $pass): bool
{
    try {
        $stmt = $conn->prepare("SELECT password FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $stored = (string) $row['password'];
        if (hash_equals($stored, $pass)) {
            return true;
        }
        if (hash_equals($stored, md5($pass))) {
            return true;
        }
        if (password_verify($pass, $stored)) {
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

$action = $_POST['ajax_action'] ?? '';

if ($action === 'item_action') {
    $pass = trim($_POST['pass'] ?? '');
    if (!verifyAdminPass($conn, $sess_usr, $pass)) {
        echo json_encode(['status' => 'error', 'message' => '❌ ভুল পাসওয়ার্ড!']);
        exit;
    }

    $type  = $_POST['type']  ?? '';
    $table = $_POST['table'] ?? '';
    $id    = (int) ($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $val   = $_POST['val']   ?? '';

    try {
        require_once __DIR__ . '/models/ReportModel.php';
        $model = new ReportModel($conn, '', '');

        if ($type === 'delete') {
            $model->deleteGenericEntry($table, $id);
        } elseif ($type === 'edit') {
            $model->updateGenericEntry($table, $id, $field, $val);
        } else {
            throw new Exception('অজ্ঞাত টাইপ');
        }
        echo json_encode(['status' => 'success', 'message' => '✅ অপারেশন সফল।']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'এরর: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_dps') {
    $pass = trim($_POST['pass'] ?? '');
    if (!verifyAdminPass($conn, $sess_usr, $pass)) {
        echo json_encode(['status' => 'error', 'message' => '❌ ভুল পাসওয়ার্ড!']);
        exit;
    }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        require_once __DIR__ . '/models/DpsModel.php';
        (new DpsModel($conn))->deleteEntry($id);
        echo json_encode(['status' => 'success', 'message' => '✅ DPS এন্ট্রি ডিলিট হয়েছে।']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'এরর: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_loan') {
    $pass = trim($_POST['pass'] ?? '');
    if (!verifyAdminPass($conn, $sess_usr, $pass)) {
        echo json_encode(['status' => 'error', 'message' => '❌ ভুল পাসওয়ার্ড!']);
        exit;
    }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        require_once __DIR__ . '/models/LoanModel.php';
        (new LoanModel($conn))->deleteEntry($id);
        echo json_encode(['status' => 'success', 'message' => '✅ লোন এন্ট্রি ডিলিট হয়েছে।']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'এরর: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_card_ledger') {
    $pass = trim($_POST['pass'] ?? '');
    if (!verifyAdminPass($conn, $sess_usr, $pass)) {
        echo json_encode(['status' => 'error', 'message' => '❌ ভুল পাসওয়ার্ড!']);
        exit;
    }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        require_once __DIR__ . '/models/CardModel.php';
        (new CardModel($conn))->deleteLedgerEntry($id);
        echo json_encode(['status' => 'success', 'message' => '✅ কার্ড এন্ট্রি ডিলিট হয়েছে।']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'এরর: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'অজ্ঞাত অ্যাকশন']);
