<?php
session_start();
include 'db_connect.php';

// লগইন চেক (এডমিন ছাড়া কেউ ঢুকতে পারবে না)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: index.php");
    exit;
}

$msg = "";

// ১. লাল নোটিশ আপডেট করার লজিক
if (isset($_POST['update_notice'])) {
    $notice_text = trim($_POST['notice_text']);
    // ডাটাবেসে বাংলা ঠিক রাখতে utf8 এনকোডিং নিশ্চিত করা
    $stmt = $conn->prepare("UPDATE site_notice SET notice_text = ? WHERE id = 1");
    if ($stmt->execute([$notice_text])) {
        $msg = "✅ নোটিশ আপডেট হয়েছে!";
    } else {
        $msg = "❌ সমস্যা হয়েছে!";

    }
}

// ২. স্লাইডার পোস্ট আপলোড করার লজিক
if (isset($_POST['upload_post'])) {
    $title = trim($_POST['title']);
    
    // ছবি আপলোড প্রসেস
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "uploads/slide/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); } // ফোল্ডার তৈরি
        
        $fileName = time() . "_" . basename($_FILES["image"]["name"]); // ইউনিক নাম
        $target_file = $target_dir . $fileName;
        
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if($check !== false) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $stmt = $conn->prepare("INSERT INTO slider_posts (title, image_path) VALUES (?, ?)");
                $stmt->execute([$title, $target_file]);
                $msg = "✅ পোস্ট স্লাইডারে যুক্ত হয়েছে!";
            } else {
                $msg = "❌ ছবি আপলোড হয়নি!";
            }
        } else {
            $msg = "❌ এটি কোনো ছবি নয়!";
        }
    }
}

// ৩. পোস্ট ডিলিট করার লজিক
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // ছবি ফাইল ডিলিট করার জন্য আগের পাথ নেওয়া
    $stmt = $conn->prepare("SELECT image_path FROM slider_posts WHERE id = ?");
    $stmt->execute([$id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);
    {
        unlink($img['image_path']); // ফোল্ডার থেকে ছবি ডিলিট
    }

    $stmt = $conn->prepare("DELETE FROM slider_posts WHERE id = ?");
    $stmt->execute([$id]);
    header("location: post.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>পোস্ট কন্ট্রোল প্যানেল</title>
    <style>
        body { font-family: 'SolaimanLipi', sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input[type="text"], input[type="file"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; box-sizing: border-box; }
        button { background: #004e92; color: white; padding: 10px 20px; border: none; cursor: pointer; width: 100%; font-weight: bold; }
        button:hover { background: #003366; }
        .msg { color: green; text-align: center; font-weight: bold; }
        .post-item { background: #fff; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border-radius: 5px; }
        .post-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; border: 1px solid #ccc; }
        .delete-btn { background: #d9534f; color: white; padding: 5px 10px; text-decoration: none; font-size: 12px; border-radius: 3px; }
        .back-btn { display: inline-block; margin-bottom: 15px; color: #333; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-btn">⬅ 🏠ড্যাশবোর্ড</a>
    <h2 style="text-align:center; color:#004e92;">নোটিশ ও স্লাইডার পোস্ট</h2>
    
    <?php if($msg) echo "<p class='msg'>$msg</p>"; ?>

    <fieldset style="border: 1px solid #004e92; padding: 10px; border-radius: 5px;">
        <legend style="color:#d9534f; font-weight:bold;">🔴 লাল নোটিশ আপডেট</legend>
        <form method="post">
            <input type="text" name="notice_text" placeholder="নোটিশ লিখুন🖍️..." required>
            <button type="submit" name="update_notice">নোটিশ আপডেট 🔔করুন</button>
        </form>
    </fieldset>

    <br>

    <fieldset style="border: 1px solid #004e92; padding: 10px; border-radius: 5px;">
        <legend style="color:#004e92; font-weight:bold;">🖼️ স্লাইডার পোস্ট যুক্ত করুন</legend>
        <form method="post" enctype="multipart/form-data">
            <input type="text" name="title" placeholder="ছবির ক্যাপশন লিখুন..." required>
            <label style="display:block; margin-top:5px;">ছবি সিলেক্ট করুন:</label>
            <input type="file" name="image" required>
            <button type="submit" name="upload_post">পোস্ট করুন</button>
        </form>
    </fieldset>

    <div style="margin-top: 20px;">
        <h3>বর্তমান স্লাইডার লিস্ট:</h3>
        <?php
        $stmt = $conn->query("SELECT * FROM slider_posts ORDER BY id DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<div class='post-item'>
                    <div style='display:flex; align-items:center; gap:10px;'>
                        <img src='{$row['image_path']}' alt='img'>
                        <span style='font-weight:bold;'>{$row['title']}</span>
                    </div>
                    <a href='post.php?delete={$row['id']}' class='delete-btn' onclick='return confirm(\"আপনি কি নিশ্চিত এটি মুছে ফেলতে চান?\")'>মুছে ফেলুন</a>
                  </div>";
        }
        ?>
    </div>
</div>

</body>
</html>
