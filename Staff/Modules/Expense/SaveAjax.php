<?php
/**
 * SaveAjax.php
 * Expense Save Handler — SADA KALO FASHION
 * Location: Staff/Modules/Expense/SaveAjax.php
 *
 * SMS পরিবর্তন করতে → staff_expens_sms.php
 * Email পরিবর্তন করতে → staff_expens_email.php
 */

session_start();
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access!']);
    exit;
}

include __DIR__ . '/../../db_connect.php';
date_default_timezone_set('Asia/Dhaka');

// =============================================
// SMS ও Email সার্ভিস ফাইল লোড করা
// =============================================
require_once __DIR__ . '/../../Services/SmsService.php';
require_once __DIR__ . '/../../Services/EmailService.php';


// =============================================
// এরর লগ ফাংশন (DB/General এরর)
// =============================================
function logError(string $message): void
{
    $logDir  = __DIR__ . '/../../Logs';
    $logFile = $logDir . '/error_log.txt';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(
        $logFile,
        "[{$timestamp}] [EXPENSE] {$message}" . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}


// =============================================
// মেইন লজিক
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $staff_id     = filter_input(INPUT_POST, 'staff_id', FILTER_SANITIZE_NUMBER_INT);
    $expense_type = htmlspecialchars(trim($_POST['expense_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $amount       = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $details      = htmlspecialchars(trim($_POST['details'] ?? ''), ENT_QUOTES, 'UTF-8');
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $entry_by     = $_SESSION['username'] ?? 'Admin';

    // ইনপুট ভ্যালিডেশন
    if (empty($staff_id) || empty($expense_type) || empty($amount)) {
        echo json_encode(['status' => 'error', 'message' => 'ফিল্ডগুলো সঠিকভাবে পূরণ করুন!']);
        exit;
    }

    if ((float)$amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'টাকার পরিমাণ ০ (শূন্য) এর চেয়ে বেশি হতে হবে!']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // ১. স্টাফের বর্তমান তথ্য আনা
        $stmt = $conn->prepare("SELECT staff_name, staff_email, staff_phone, running_balance FROM staff_info WHERE id = ?");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$staff) {
            throw new Exception("স্টাফ খুঁজে পাওয়া যায়নি!");
        }

        // ২. খরচের লেজারে এন্ট্রি করা
        $ins = $conn->prepare("INSERT INTO staff_expenses (staff_id, expense_type, description, amount, expense_date, entry_by) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([$staff_id, $expense_type, $details, $amount, $expense_date, $entry_by]);

        // ৩. রানিং ব্যালেন্স আপডেট
        $new_balance = $staff['running_balance'] - (float)$amount;
        $upd = $conn->prepare("UPDATE staff_info SET running_balance = ? WHERE id = ?");
        $upd->execute([$new_balance, $staff_id]);

        $conn->commit();

        // ৪. SMS পাঠানো — staff_expens_sms.php থেকে
        //    (শুধু expense-এর SMS, balance মাইনাসের না)
        $staffPhone = $staff['staff_phone'] ?? '';
        sendExpenseSMS(
            staffPhone:   $staffPhone,
            expenseDate:  $expense_date,
            amount:       (float)$amount,
            staffId:      (int)$staff_id
        );

        // ৫. Email পাঠানো — staff_expens_email.php থেকে
        $staffEmail = $staff['staff_email'] ?? '';
        sendExpenseEmail(
            staffEmail:   $staffEmail,
            staffName:    $staff['staff_name'],
            expenseType:  $expense_type,
            expenseDate:  $expense_date,
            amount:       (float)$amount,
            details:      $details,
            entryBy:      $entry_by,
            staffId:      (int)$staff_id
        );

        echo json_encode([
            'status'  => 'success',
            'message' => 'খরচ সেভ হয়েছে, ব্যালেন্স আপডেট ও SMS পাঠানো হয়েছে!'
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        logError("Expense save error for Staff ID {$staff_id}: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'সার্ভার এরর হয়েছে! পুনরায় চেষ্টা করুন।']);
    }
}
