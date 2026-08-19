<?php
/**
 * SaveAjax.php
 * Attendance Save Handler — SADA KALO FASHION
 * Location: Staff/Modules/Attendance/SaveAjax.php
 *
 * SMS পরিবর্তন করতে  → Staff/Services/SmsService.php
 * Email পরিবর্তন করতে → Staff/Services/EmailService.php
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
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
        "[{$timestamp}] [ATTENDANCE] {$message}" . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}


// =============================================
// মেইন লজিক
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bulk_attendance') {

    $attendance_list = json_decode($_POST['attendance_data'], true);
    $entryUser       = $_SESSION['username'] ?? 'Admin';
    $recordDate      = date('Y-m-d');
    $success_count   = 0;

    if (!is_array($attendance_list) || empty($attendance_list)) {
        echo json_encode(['status' => 'error', 'msg' => 'কোনো ডাটা পাওয়া যায়নি!']);
        exit;
    }

    try {
        $conn->beginTransaction();

        foreach ($attendance_list as $row) {
            $staffId     = filter_var($row['staff_id'],    FILTER_SANITIZE_NUMBER_INT);
            $status      = htmlspecialchars(trim($row['status']),     ENT_QUOTES, 'UTF-8');
            $lateMinutes = intval($row['late_time'] ?? 0);
            $leaveNote   = htmlspecialchars(trim($row['leave_note']), ENT_QUOTES, 'UTF-8');
            $staffEmail  = filter_var($row['staff_email'], FILTER_SANITIZE_EMAIL);

            if (empty($lateMinutes)) $lateMinutes = 0;

            // ১. ডাবল এন্ট্রি চেক
            $check = $conn->prepare("SELECT id FROM staff_attendance WHERE staff_id = ? AND DATE(attendance_date) = ?");
            $check->execute([$staffId, $recordDate]);
            if ($check->rowCount() > 0) continue;

            // ২. হাজিরা সেভ করা
            $ins = $conn->prepare("INSERT INTO staff_attendance (staff_id, status, late_time, leave_note, attendance_date, entry_by) VALUES (?, ?, ?, ?, NOW(), ?)");
            $ins->execute([$staffId, $status, $lateMinutes, $leaveNote, $entryUser]);

            // ৩. স্টাফের নাম, ফোন ও carry_forward তথ্য আনা
            $infoQ = $conn->prepare("SELECT staff_name, staff_phone, carry_forward_amount, carry_forward_applied, previous_staff_id FROM staff_info WHERE id = ?");
            $infoQ->execute([$staffId]);
            $staffInfo  = $infoQ->fetch(PDO::FETCH_ASSOC);
            $staffName  = $staffInfo['staff_name']  ?? 'Staff';
            $staffPhone = $staffInfo['staff_phone'] ?? '';

            // ৪. প্রথম হাজিরায় পূর্বের ঋণ Advance হিসেবে auto-apply
            $carry_alert = '';
            if (
                !empty($staffInfo['carry_forward_amount']) &&
                $staffInfo['carry_forward_amount'] > 0 &&
                $staffInfo['carry_forward_applied'] == 0
            ) {
                $cf_amount = floatval($staffInfo['carry_forward_amount']);
                $prev_id   = $staffInfo['previous_staff_id'] ?? 0;
                $cf_note   = "ঋণ বাবদ অ্যাডভান্স (Balance Forward" . ($prev_id ? " from ID #{$prev_id}" : "") . ")";

                $cf_ins = $conn->prepare("INSERT INTO staff_expenses (staff_id, expense_type, description, amount, expense_date, entry_by) VALUES (?, 'Balance Forward', ?, ?, ?, ?)");
                $cf_ins->execute([$staffId, $cf_note, $cf_amount, $recordDate, $entryUser]);

                $conn->prepare("UPDATE staff_info SET carry_forward_applied = 1 WHERE id = ?")->execute([$staffId]);

                $carry_alert = " | ⚠️ {$staffName}-এর পূর্বের ঋণ Tk. " . number_format($cf_amount, 2) . " Advance হিসেবে যোগ হয়েছে।";
            }

            // ৫. SMS পাঠানো — staff_attend_sms.php থেকে
            if (!empty($staffPhone)) {
                sendAttendanceSMS(
                    staffPhone:   $staffPhone,
                    status:       $status,
                    lateMinutes:  $lateMinutes,
                    staffId:      (int)$staffId
                );
            }

            // ৬. Email পাঠানো — staff_attend_email.php থেকে
            if (!empty($staffEmail)) {
                sendAttendanceEmail(
                    staffEmail:   $staffEmail,
                    staffName:    $staffName,
                    status:       $status,
                    lateMinutes:  $lateMinutes,
                    leaveNote:    $leaveNote,
                    staffId:      (int)$staffId
                );
            }

            $success_count++;
        }

        $conn->commit();
        echo json_encode([
            'status' => 'success',
            'msg'    => "Success! {$success_count} জনের হাজিরা সেভ হয়েছে।" . $carry_alert
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        logError("Attendance save error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'msg' => 'সার্ভার এরর হয়েছে! পুনরায় চেষ্টা করুন।']);
    }

} else {
    echo json_encode(['status' => 'error', 'msg' => 'অবৈধ রিকোয়েস্ট!']);
}
?>
