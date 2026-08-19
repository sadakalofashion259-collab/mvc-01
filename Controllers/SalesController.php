<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/SalesModel.php';

class SalesController {
 private SalesModelInterface $salesModel;
 public function __construct(\PDO $dbConnection) {
  $this->salesModel = new SalesModel($dbConnection);
  date_default_timezone_set('Asia/Dhaka');
 }
 public function processSaleRequest(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'save') {
$this->sendResponse(false, 'Invalid request method.');
  }

  $preparedBy = isset($_SESSION['username']) ? (string)$_SESSION['username'] : trim((string)($_POST['prepared_by'] ?? 'Unknown'));
  $todayDate = date('Y-m-d');
  
  $customerIds = $_POST['customer_id'] ?? [];
  $customNames = $_POST['custom_customer_name'] ?? [];
  $quantities = $_POST['sale_qty'] ?? [];
  $bills = $_POST['sale_bill'] ?? [];
  $paids = $_POST['sale_paid'] ?? [];
  $photos = $_POST['sale_photo'] ?? [];

  if (empty($quantities)) {
$this->sendResponse(false, 'No sales data found to save.');
  }

  try {
$this->salesModel->beginTransaction();

$reportId = $this->salesModel->getOrCreateDailyReportId($todayDate, $preparedBy);
$dynamicMemo = $this->salesModel->getNextMemoNumber();

$totalRows = count($quantities);

$salesRowsHtml = "";
$totalPaidAmount = 0;

for ($index = 0; $index < $totalRows; $index++) {
 $qty = (float)($quantities[$index] ?? 0);
 $bill = (float)($bills[$index] ?? 0);
 $paid = (float)($paids[$index] ?? 0);
 $due = $bill - $paid;

 $cid = (string)($customerIds[$index] ?? '');
 $customName = trim((string)($customNames[$index] ?? ''));
 $isNosto = ($customName === 'বাদ' || $customName === 'নষ্ট');

 if ($qty > 0 || $bill > 0 || $paid > 0 || $isNosto) {
  
  $dynamicMemo++;
  $actualMemoToSave = $dynamicMemo;

  $finalCustomerName = 'SKF';
  $isCustomCustomer = false;

  if ($isNosto) {
$finalCustomerName = $customName;
$isCustomCustomer = true;
  } else if ($cid === 'custom' && !empty($customName)) {
$finalCustomerName = htmlspecialchars($customName, ENT_QUOTES, 'UTF-8') . " (অস্থায়ী)";
$isCustomCustomer = true;
  } else if (!empty($cid) && $cid !== 'custom' && is_numeric($cid)) {
$dbName = $this->salesModel->getCustomerNameById((int)$cid);
if (!empty($dbName)) {
 $finalCustomerName = htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8');
}
  }

  $base64Photo = $photos[$index] ?? '';
  $photoPath = $this->saveImageToFolder($base64Photo, 'SKF_sales');

  // ১. ডাটাবেজে সেভ করা
  $this->salesModel->insertSaleEntry(
$reportId, $actualMemoToSave, $finalCustomerName, $qty, $bill, $paid, $due, $photoPath, $preparedBy
  );

  // ২. কাস্টমারের ব্যালেন্স আপডেট করা
  if ($due > 0 && !$isCustomCustomer && is_numeric($cid)) {
$desc = "মেমো: {$actualMemoToSave} (বাকি বিক্রি)";
$this->salesModel->insertCustomerTransaction((int)$cid, $todayDate, $desc, $due, 0.0, $preparedBy);
  }

  // ৩. ইমেইলের জন্য HTML টেবিল রো তৈরি করা
  $salesRowsHtml .= "<tr>
<td style='border:1px solid #ddd;padding:5px;'>{$actualMemoToSave}</td>
<td style='border:1px solid #ddd;padding:5px;'>{$finalCustomerName}</td>
<td style='border:1px solid #ddd;padding:5px;'>{$qty}</td>
<td style='border:1px solid #ddd;padding:5px;'>{$bill}</td>
<td style='border:1px solid #ddd;padding:5px;color:green;'>{$paid}</td>
<td style='border:1px solid #ddd;padding:5px;color:red;'>{$due}</td>
<td style='border:1px solid #ddd;padding:5px;font-size:10px;'>{$preparedBy}</td>
  </tr>";

  $totalPaidAmount += $paid;
 }
}

// ট্রানজেকশন সফল হলে ডাটাবেজে সেভ করুন
$this->salesModel->commitTransaction();

// ডাটাবেজে সেভ হওয়ার পর ইমেইল পাঠানো
$this->sendEmailReport($salesRowsHtml, $totalPaidAmount, $preparedBy);

$this->sendResponse(true, 'success');

  } catch (\Exception $exception) {
$this->salesModel->rollBackTransaction();
$this->salesModel->logError("Transaction Failed", $exception);
$this->sendResponse(false, 'Server Error: ' . $exception->getMessage());
  }
 }

 // আপনার পুরনো ইমেইল টেমপ্লেট
 private function sendEmailReport(string $salesRows, float $totalPaid, string $preparedBy): bool {
  if (empty($salesRows)) return false;

  $today = date('Y-m-d');
  $time_now = date("d-M-Y h:i A");
  $day_name = date('l');
  $serial = "Sada-Kalo-" . date('YmdHi');
  
  $to = "hisabkhata24@gmail.com";
  $subject = "Sada Kalo Fashion - Sales Report - $serial";
  $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Digital Hisab <no-reply_Hisab@sadakalofashion.com>";

  $msg = "
  <div style='background-color: #cbd5e1; padding: 20px; font-family: Arial, sans-serif;'>
<div style='max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.3); border: 2px solid #94a3b8;'>
 
 <div style='background-color: #0f172a; color: #ffffff; text-align: center; padding: 30px 20px;'>
  <h1 style='margin: 0; font-size: 34px; color: #38bdf8; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); font-weight: 900;'>সাদাকালো ফ্যাশন</h1>
  <h2 style='margin: 5px 0 20px 0; font-size: 22px; color: #e2e8f0; border-bottom: 1px solid #334155; display: inline-block; padding-bottom: 5px;'>দৈনিক ক্যাশ বিক্রি</h2>
  
  <div style='background: rgba(255,255,255,0.08); display: inline-block; padding: 12px 25px; border-radius: 8px; font-size: 15px; border: 1px solid #334155;'>
<strong>তারিখ:</strong> $today, $day_name<br>
<strong>সময়:</strong> $time_now<br>
<strong>রিপোর্ট আইডি:</strong> <span style='color: #fbbf24;'>$serial</span>
  </div>
 </div>

 <div style='padding: 30px 25px;'>
  <div style='background-color: #0e8388; color: #ffffff; text-align: center; padding: 20px 10px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(14,131,136,0.4); border: 2px solid #0b696d;'>
<div style='font-size: 16px; opacity: 0.9; margin-bottom: 8px;'>মোট ক্যাশ বিক্রি (জমা)</div>
<div style='font-size: 28px; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);'>TK $totalPaid</div>
  </div>

  <h3 style='color: #0e8388; border-bottom: 3px solid #0e8388; padding-bottom: 5px; margin-top: 0; font-size: 20px;'>বিক্রির বিস্তারিত</h3>
  <table width='100%' style='border-collapse: collapse; font-size: 14px; margin-bottom: 30px; text-align: center;'>
<tr style='background-color: #0e8388; color: #ffffff;'>
 <th style='padding: 10px;'>মেমো</th><th style='padding: 10px;'>কাস্টমার</th><th style='padding: 10px;'>পিস</th><th style='padding: 10px;'>বিল</th><th style='padding: 10px;'>জমা</th><th style='padding: 10px;'>বাকি</th><th style='padding: 10px;'>By</th>
</tr>
$salesRows
  </table>

  <div style='text-align: right; margin-top: 30px; font-size: 17px; color: #334155; border-top: 2px dashed #94a3b8; padding-top: 20px;'>
<strong>এন্ট্রি বাই:</strong> <span style='color: #0284c7; font-weight: 900;'>$preparedBy</span>
  </div>
 </div>

 <div style='background-color: #1e293b; color: #94a3b8; text-align: center; padding: 20px; font-size: 16px; font-weight: bold; border-top: 5px solid #38bdf8;'>
  ইমেইলে গ্রহণকারী হিসেবে <span style='color: #ffffff;'>সাদা কালো ফ্যাশন</span>
 </div>
</div>
  </div>
  ";

  return mail($to, $subject, $msg, $headers);
 }

 private function saveImageToFolder(string $base64, string $prefix): string {
  if (empty($base64) || strpos($base64, 'data:image') === false) return ''; 
  
  $year = date('Y');
  $monthName = date('F'); 
  $targetDir = __DIR__ . "/../uploads/Memo/{$year}/{$monthName}"; 

  if (!file_exists($targetDir)) { 
mkdir($targetDir, 0777, true); 
  }
  
  $data = explode(',', $base64);
  if (!isset($data[1])) return '';
  
  $dbRelativePath = "uploads/Memo/{$year}/{$monthName}/{$prefix}_" . date('His') . '_' . rand(100,999) . '.jpg';
  $absolutePath = __DIR__ . '/../' . $dbRelativePath;
  
  if (file_put_contents($absolutePath, base64_decode($data[1])) !== false) {
return $dbRelativePath; 
  }
  return '';
 }

 private function sendResponse(bool $isSuccess, string $message): void {
  if ($isSuccess) {
http_response_code(200);
echo $message; 
  } else {
http_response_code(400);
echo $message;
  }
  exit;
 }
}
?>