<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
}

include __DIR__ . '/../../db_connect.php';
date_default_timezone_set('Asia/Dhaka');

$action = $_POST['action'] ?? '';

// =============================================
// ১. EXPENSE DELETE
// =============================================
if ($action === 'delete_expense') {
    $expense_id = intval($_POST['expense_id'] ?? 0);
    if (!$expense_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']); exit;
    }

    try {
        $conn->beginTransaction();

        // আগে expense টা পড়ি
        $sel = $conn->prepare("SELECT staff_id, amount FROM staff_expenses WHERE id = ?");
        $sel->execute([$expense_id]);
        $exp = $sel->fetch(PDO::FETCH_ASSOC);

        if (!$exp) {
            echo json_encode(['status' => 'error', 'message' => 'Expense খুঁজে পাওয়া যায়নি!']); exit;
        }

        // Expense delete
        $del = $conn->prepare("DELETE FROM staff_expenses WHERE id = ?");
        $del->execute([$expense_id]);

        // running_balance ঠিক করা (টাকাটা ফেরত)
        $upd = $conn->prepare("UPDATE staff_info SET running_balance = running_balance + ? WHERE id = ?");
        $upd->execute([$exp['amount'], $exp['staff_id']]);

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'খরচ সফলভাবে ডিলিট হয়েছে!']);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'এরর: ' . $e->getMessage()]);
    }

// =============================================
// ২. EXPENSE EDIT
// =============================================
} elseif ($action === 'edit_expense') {
    $expense_id   = intval($_POST['expense_id'] ?? 0);
    $expense_type = htmlspecialchars(trim($_POST['expense_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $new_amount   = floatval($_POST['amount'] ?? 0);
    $expense_date = $_POST['expense_date'] ?? '';
    $description  = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (!$expense_id || $new_amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'সঠিক তথ্য দিন']); exit;
    }

    try {
        $conn->beginTransaction();

        // পুরনো amount পড়ি
        $sel = $conn->prepare("SELECT staff_id, amount FROM staff_expenses WHERE id = ?");
        $sel->execute([$expense_id]);
        $exp = $sel->fetch(PDO::FETCH_ASSOC);

        if (!$exp) {
            echo json_encode(['status' => 'error', 'message' => 'Expense খুঁজে পাওয়া যায়নি!']); exit;
        }

        $diff = $new_amount - $exp['amount']; // পার্থক্য

        // Expense update
        $upd = $conn->prepare("UPDATE staff_expenses SET expense_type=?, amount=?, expense_date=?, description=? WHERE id=?");
        $upd->execute([$expense_type, $new_amount, $expense_date, $description, $expense_id]);

        // running_balance adjust (পার্থক্য অনুযায়ী)
        if ($diff != 0) {
            $conn->prepare("UPDATE staff_info SET running_balance = running_balance - ? WHERE id = ?")
                 ->execute([$diff, $exp['staff_id']]);
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'খরচ সফলভাবে আপডেট হয়েছে!']);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'এরর: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>
