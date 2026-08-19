<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
include __DIR__ . '/../../db_connect.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$name        = htmlspecialchars(trim($_POST['staff_name']));
$email       = filter_var($_POST['staff_email'], FILTER_SANITIZE_EMAIL);
$phone       = htmlspecialchars(trim($_POST['staff_phone']));
$salary      = floatval($_POST['staff_salary']);
$join_date   = $_POST['staff_join_date'];
$address     = htmlspecialchars(trim($_POST['staff_address']));

// ছবি হ্যান্ডলিং
$photo_name = "default.png";
$upload_dir = "../../Uploads/profiles/";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

if (!empty($_POST['captured_image'])) {
    $data = $_POST['captured_image'];
    list($type, $data) = explode(';', $data);
    list(, $data)      = explode(',', $data);
    $data = base64_decode($data);
    $photo_name = time() . "_staff.jpg";
    file_put_contents($upload_dir . $photo_name, $data);
} elseif (!empty($_FILES['staff_photo']['name'])) {
    $photo_name = time() . "_" . $_FILES['staff_photo']['name'];
    move_uploaded_file($_FILES['staff_photo']['tmp_name'], $upload_dir . $photo_name);
}

try {
    $conn->beginTransaction();

    // ১. নতুন staff insert — previous_staff_id ও carry_forward_amount সহ
    // ✅ Auto-verify: form submit হলেই email_verified=1 — কোনো token বা link লাগবে না
    $stmt = $conn->prepare("INSERT INTO staff_info 
        (staff_name, staff_email, staff_phone, staff_address, staff_salary, staff_join_date, 
         profile_pic, email_verified) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)");

    $stmt->execute([
        $name, $email, $phone, $address, $salary, $join_date, $photo_name
    ]);

    $new_staff_id = $conn->lastInsertId();

    $conn->commit();

    // ✅ Welcome SMS পাঠানো (verification link নয়, শুধু স্বাগত বার্তা)
    // API কনফিগ → Staff/Core/Config.php, প্রেরণ লজিক → Staff/Services/SmsService.php
    if (!empty($phone)) {
        require_once __DIR__ . '/../../Services/SmsService.php';

        $mobile = preg_replace('/\D/', '', $phone);
        if (strlen($mobile) === 11 && $mobile[0] === '0') $mobile = '88' . $mobile;
        elseif (strlen($mobile) === 10) $mobile = '880' . $mobile;

        if (strlen($mobile) >= 13) {
            $sms_body  = "SADA KALO FASHION\n";
            $sms_body .= "স্বাগতম, {$name}!\n";
            $sms_body .= "আপনার স্টাফ প্রোফাইল সক্রিয় হয়েছে।\n";
            $sms_body .= "বেতন: Tk. " . number_format($salary, 0);
            sendSMS($mobile, $sms_body);
        }
    }

    $msg_text = 'স্টাফ সফলভাবে অ্যাড হয়েছে এবং সক্রিয় করা হয়েছে!';

    echo json_encode(['status' => 'success', 'message' => $msg_text, 'new_id' => $new_staff_id]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
