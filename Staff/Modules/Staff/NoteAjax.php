<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit;
}
include __DIR__ . '/../../db_connect.php';
date_default_timezone_set('Asia/Dhaka');

$action   = $_POST['action'] ?? '';
$admin    = $_SESSION['username'] ?? 'Admin';
$note_dir = "../../Uploads/notes/";
if (!is_dir($note_dir)) mkdir($note_dir, 0777, true);

// ADD NOTE
if ($action === 'add_note') {
    $staff_id  = intval($_POST['staff_id'] ?? 0);
    $note_text = htmlspecialchars(trim($_POST['note_text'] ?? ''), ENT_QUOTES, 'UTF-8');
    if (!$staff_id || empty($note_text)) {
        echo json_encode(['status'=>'error','message'=>'Staff ID ও Note দিন']); exit;
    }
    $photo_path = null;
    // Photo handle
    if (!empty($_POST['captured_image'])) {
        $data = $_POST['captured_image'];
        list(, $data) = explode(',', $data);
        $photo_path = $note_dir . time() . '_note.jpg';
        file_put_contents($photo_path, base64_decode($data));
    } elseif (!empty($_FILES['note_photo']['name'])) {
        $ext = pathinfo($_FILES['note_photo']['name'], PATHINFO_EXTENSION);
        $photo_path = $note_dir . time() . '_note.' . $ext;
        move_uploaded_file($_FILES['note_photo']['tmp_name'], $photo_path);
    }
    try {
        $ins = $conn->prepare("INSERT INTO staff_notes (staff_id, note_text, photo_path, created_by) VALUES (?,?,?,?)");
        $ins->execute([$staff_id, $note_text, $photo_path, $admin]);
        echo json_encode(['status'=>'success','message'=>'Note সেভ হয়েছে!','id'=>$conn->lastInsertId()]);
    } catch(Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

// DELETE NOTE
} elseif ($action === 'delete_note') {
    $note_id = intval($_POST['note_id'] ?? 0);
    try {
        $sel = $conn->prepare("SELECT photo_path FROM staff_notes WHERE id=?");
        $sel->execute([$note_id]);
        $note = $sel->fetch(PDO::FETCH_ASSOC);
        if ($note && $note['photo_path']) {
            // পুরনো DB রেকর্ডের 'uploads/notes/...' পাথকে নতুন Uploads/notes/ লোকেশনে ম্যাপ করা
            $del_path = $note['photo_path'];
            if (stripos($del_path, 'uploads/') === 0) {
                $del_path = '../../Uploads/notes/' . basename($del_path);
            }
            if (file_exists($del_path)) unlink($del_path);
        }
        $conn->prepare("DELETE FROM staff_notes WHERE id=?")->execute([$note_id]);
        echo json_encode(['status'=>'success','message'=>'Note মুছে গেছে!']);
    } catch(Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

// EDIT NOTE
} elseif ($action === 'edit_note') {
    $note_id   = intval($_POST['note_id'] ?? 0);
    $note_text = htmlspecialchars(trim($_POST['note_text'] ?? ''), ENT_QUOTES, 'UTF-8');
    try {
        $conn->prepare("UPDATE staff_notes SET note_text=?, updated_at=NOW() WHERE id=?")->execute([$note_text, $note_id]);
        echo json_encode(['status'=>'success','message'=>'Note আপডেট হয়েছে!']);
    } catch(Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
} else {
    echo json_encode(['status'=>'error','message'=>'Invalid action']);
}
?>
