<?php
session_start();

// আপনার ডাটাবেজ কানেকশন ফাইল
include 'db_connect.php';

// লগইন চেক (নিরাপত্তা)
if (!isset($_SESSION['loggedin'])) { header("Location: index.php"); exit; }

// ==========================================
// কনফিগারেশন এরিয়া
// ==========================================
$to_email = "sajpoint99@gmail.com"; 
$from_email = "backup-mydatabase@sadakalofashion.com";
$email_subject = "ডাটাবেজ ব্যাকআপ হচ্ছে";

// অটো সিরিয়াল নম্বর জেনারেট (V-01, V-02...)
$counter_file = 'backup_counter.txt';
if (!file_exists($counter_file)) {
    file_put_contents($counter_file, 1);
    $current_count = 1;
} else {
    $current_count = (int)file_get_contents($counter_file);
    $current_count++;
    file_put_contents($counter_file, $current_count);
}

// সিরিয়াল ফরম্যাটিং (যেমন: 01, 02)
$serial_no = str_pad($current_count, 2, '0', STR_PAD_LEFT);
$filename = "Dokan-Hisab- backup_V-" . $serial_no . ".sql";

// ==========================================
// ব্যাকআপ প্রসেস শুরু
// ==========================================

// এরর রিপোর্টিং হাইড করা এবং বাফার স্টার্ট
error_reporting(0);
ob_start();

try {
    // কানেকশন চেক
    if (!$conn) {
        throw new Exception("ডাটাবেজ কানেকশন ব্যর্থ হয়েছে (Connection Failed).");
    }

    // বাংলা ফন্ট সাপোর্ট
    $conn->exec("SET NAMES 'utf8mb4'");

    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while($row = $result->fetch(PDO::FETCH_NUM)){ $tables[] = $row[0]; }

    $return = "";
    
    // রিস্টোর করার সময় যাতে এরর না হয় (Safety Headers)
    $return .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $return .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $return .= "SET time_zone = '+06:00';\n\n";

    foreach($tables as $table){
        // ১. আগের টেবিল ডিলিট করার কমান্ড (রিস্টোর ফিক্স)
        $return .= "DROP TABLE IF EXISTS `$table`;\n";

        // ২. টেবিল স্ট্রাকচার তৈরি
        $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
        $return .= "\n\n".$row2[1].";\n\n";

        // ৩. ডাটা ইনসার্ট করা
        $result = $conn->query("SELECT * FROM $table");
        while($row = $result->fetch(PDO::FETCH_NUM)){
            $return .= "INSERT INTO `$table` VALUES(";
            for($j=0; $j<count($row); $j++){
                // নাল (NULL) ভ্যালু হ্যান্ডলিং (খুবই গুরুত্বপূর্ণ রিস্টোরের জন্য)
                if (is_null($row[$j])) {
                    $return .= "NULL";
                } else {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = str_replace("\n","\\n",$row[$j]); 
                    $return .= '"'.$row[$j].'"';
                }
                
                if($j<(count($row)-1)){ $return.= ','; }
            }
            $return .= ");\n";
        }
    }
    
    // রিস্টোর শেষে ফরেইন কি আবার চালু করা
    $return .= "\nSET FOREIGN_KEY_CHECKS=1;";

    // ==========================================
    // সফল হলে: ইমেইল পাঠানো
    // ==========================================
    if (!empty($to_email)) {
        $separator = md5(time());
        $eol = "\r\n";

        // ইমেইল হেডার
        $headers = "From: Dokan System <" . $from_email . ">" . $eol;
        $headers .= "Reply-To: " . $from_email . $eol;
        $headers .= "MIME-Version: 1.0" . $eol;
        $headers .= "Content-Type: multipart/mixed; boundary=\"" . $separator . "\"" . $eol;

        // ইমেইল বডি (টেক্সট)
        $body = "--" . $separator . $eol;
        $body .= "Content-Type: text/plain; charset=\"UTF-8\"" . $eol;
        $body .= "Content-Transfer-Encoding: 7bit" . $eol . $eol;
        $body .= "আপনার Sada Kalo FashionHISAB_DOKAN-ব্যাকআপ ফাইলটি🕵️ (Serial: V-" . $serial_no . ") সফলভাবে তৈরি এবং সংযুক্ত করা হলো।" . $eol . $eol;

        // ইমেইল এটাচমেন্ট (ব্যাকআপ ফাইল)
        $body .= "--" . $separator . $eol;
        $body .= "Content-Type: application/octet-stream; name=\"" . $filename . "\"" . $eol;
        $body .= "Content-Transfer-Encoding: base64" . $eol;
        $body .= "Content-Disposition: attachment; filename=\"" . $filename . "\"" . $eol . $eol;
        $body .= chunk_split(base64_encode($return)) . $eol . $eol;
        $body .= "--" . $separator . "--";

        // মেইল ফাংশন কল
        mail($to_email, $email_subject, $body, $headers);
    }

    // ==========================================
    // ব্রাউজারে ডাউনলোড শুরু
    // ==========================================
    ob_clean();
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary"); 
    header("Content-disposition: attachment; filename=\"".$filename."\""); 
    echo $return; 
    exit;

} catch (Exception $e) {
    // ==========================================
    // ফেইল হলে: এরর হ্যান্ডলিং এবং ইমেইল
    // ==========================================
    ob_end_clean();
    
    $error_msg = $e->getMessage();
    $err_subject = "Error: ডাটাবেজ ব্যাকআপ ফেইল হয়েছে (V-" . $serial_no . ")";
    $err_body = "সতর্কবার্তা: আপনার ডাটাবেজ ব্যাকআপ নেওয়ার সময় একটি সমস্যা হয়েছে।\n\nএরর বিস্তারিত: " . $error_msg;
    $err_headers = "From: " . $from_email;

    // এরর মেইল পাঠানো
    mail($to_email, $err_subject, $err_body, $err_headers);

    // স্ক্রিনে এরর দেখানো (যাতে ইউজার বুঝতে পারে)
    echo "<div style='color:red; text-align:center; padding:50px; font-family:sans-serif;'>";
    echo "<h1 style='border-bottom:1px solid red; display:inline-block; padding-bottom:10px;'>Backup Failed!</h1>";
    echo "<h3>ব্যাকআপ নেওয়া সম্ভব হয়নি।</h3>";
    echo "<p>আপনার ইমেইলে এরর রিপোর্ট পাঠানো হয়েছে।</p>";
    echo "<div style='background:#ffe6e6; padding:15px; display:inline-block; border-radius:5px;'>";
    echo "<strong>Error Code:</strong> " . $error_msg;
    echo "</div><br><br>";
    echo "<a href='index.php' style='text-decoration:none; background:#333; color:#fff; padding:10px 20px; border-radius:5px;'>ড্যাশবোর্ডে ফিরে যান</a>";
    echo "</div>";
}
?>