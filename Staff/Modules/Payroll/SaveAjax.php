<?php
session_start();
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']); exit;
}

include __DIR__ . '/../../db_connect.php';
date_default_timezone_set('Asia/Dhaka');

// SMS Config → Staff/Core/Config.php থেকে
require_once __DIR__ . '/../../Core/Config.php';

function sendSMS(string $mobile, string $msg): void {
    // ✅ অ্যাডমিন টগল চেক — SMS বন্ধ থাকলে কিছুই পাঠাবে না
    if (!staff_sms_enabled()) { logErr("Payslip SMS skipped (SMS notifications OFF) | Mobile: {$mobile}"); return; }
    // ✅ .env কনফিগ চেক — SMS কী অনুপস্থিত থাকলে নিরাপদে বন্ধ
    if (!staff_sms_configured()) { logErr("Payslip SMS skipped (SMS credentials missing in .env) | Mobile: {$mobile}"); return; }
    $m = preg_replace('/\D/', '', $mobile);
    if (strlen($m)===11 && $m[0]==='0') $m='88'.$m;
    elseif (strlen($m)===10) $m='880'.$m;
    if (strlen($m)<13) return;
    $pl = json_encode(['UserName'=>SMS_API_USERNAME,'Apikey'=>SMS_API_KEY,
        'MobileNumber'=>$m,'SenderName'=>SMS_SENDER_NAME,
        'TransactionType'=>SMS_TYPE,'CampaignId'=>'null','Message'=>$msg]);
    $ch = curl_init(SMS_API_URL);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$pl,CURLOPT_TIMEOUT=>8,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Authorization: bearer'],
        CURLOPT_SSL_VERIFYPEER=>true]);
    curl_exec($ch); curl_close($ch);
}

function logErr(string $msg): void {
    $d = __DIR__.'/../../Logs';
    if (!is_dir($d)) mkdir($d,0755,true);
    file_put_contents($d.'/error_log.txt','['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL,FILE_APPEND|LOCK_EX);
}

$action = $_POST['action'] ?? '';

// =============================================
// SAVE PAYSLIP
// =============================================
if ($action === 'save_simple_payslip') {
    try {
        $staff_id        = intval($_POST['staff_id'] ?? 0);
        $to_date         = $_POST['to_date'] ?? date('Y-m-d');
        $leave_approved  = floatval($_POST['leave_approved']  ?? 0);
        $absent_approved = floatval($_POST['absent_approved'] ?? 0);
        $late_fine_mins  = floatval($_POST['late_fine_mins']  ?? 0);
        $salary_bonus    = floatval($_POST['salary_bonus']    ?? 0);
        $other_bonus     = floatval($_POST['other_bonus']     ?? 0);
        $cash_paid       = floatval($_POST['cash_paid']       ?? 0);

        if (!$staff_id) {
            echo json_encode(['status'=>'error','message'=>'Staff ID দিন']); exit;
        }

        // Staff তথ্য
        $stmt = $conn->prepare("SELECT * FROM staff_info WHERE id=? AND staff_status='Active'");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$staff) {
            echo json_encode(['status'=>'error','message'=>'Staff পাওয়া যায়নি']); exit;
        }

        $daily_salary = $staff['staff_salary'] / 30;

        // ✅ Attendance period = প্রথম হাজিরা → to_date
        $fa = $conn->prepare("SELECT MIN(DATE(attendance_date)) FROM staff_attendance WHERE staff_id=?");
        $fa->execute([$staff_id]); $att_from = $fa->fetchColumn();

        // ✅ Expense period = প্রথম খরচ → to_date
        $fe = $conn->prepare("SELECT MIN(expense_date) FROM staff_expenses WHERE staff_id=?");
        $fe->execute([$staff_id]); $exp_from = $fe->fetchColumn();

        // payslip-এ দেখানোর জন্য period start (যেটা আগে), না থাকলে joining
        $dates     = array_filter([$att_from, $exp_from]);
        $from_date = !empty($dates) ? min($dates) : $staff['staff_join_date'];

        // Attendance summary — ✅ প্রথম হাজিরা থেকে to_date পর্যন্ত
        $att = ['P'=>0,'H'=>0,'L'=>0,'A'=>0,'Late'=>0];
        if ($att_from) {
            $aq = $conn->prepare("SELECT status, SUM(late_time) as late, COUNT(*) as cnt FROM staff_attendance WHERE staff_id=? AND attendance_date BETWEEN ? AND ? GROUP BY status");
            $aq->execute([$staff_id, $att_from, $to_date]);
            while ($r=$aq->fetch(PDO::FETCH_ASSOC)) {
                if ($r['status']==='Present') $att['P']=$r['cnt'];
                if ($r['status']==='Absent')  $att['A']=$r['cnt'];
                if ($r['status']==='Half')    $att['H']=$r['cnt'];
                if ($r['status']==='Leave')   $att['L']=$r['cnt'];
                $att['Late'] += $r['late'];
            }
        }

        // Expense summary — ✅ প্রথম খরচ থেকে to_date পর্যন্ত
        $exp_adv=$exp_week=$exp_month=$exp_other=0;
        if ($exp_from) {
            $eq = $conn->prepare("SELECT expense_type, SUM(amount) as total FROM staff_expenses WHERE staff_id=? AND expense_date BETWEEN ? AND ? GROUP BY expense_type");
            $eq->execute([$staff_id, $exp_from, $to_date]);
            while ($r=$eq->fetch(PDO::FETCH_ASSOC)) {
                if ($r['expense_type']==='Emergency Advance')   $exp_adv   += $r['total'];
                elseif ($r['expense_type']==='Weekly Expense')  $exp_week  += $r['total'];
                elseif ($r['expense_type']==='Monthly Expense') $exp_month += $r['total'];
                else                                            $exp_other += $r['total'];
            }
        }

        // হিসাব
        $leaveCutDays    = max(0, $att['L'] - $leave_approved);
        $leaveCutAmt     = $leaveCutDays * $daily_salary;
        $absentCutDays   = max(0, $att['A'] - $absent_approved);
        $absentCutAmt    = $absentCutDays * $daily_salary;
        $lateDeduction   = $late_fine_mins * ($daily_salary / 480);
        $payableDays     = $att['P'] + ($att['H'] * 0.5) + $leave_approved + $absent_approved;
        $basicEarned     = $payableDays * $daily_salary;
        $totalEarnings   = $basicEarned - $leaveCutAmt - $absentCutAmt - $lateDeduction + $salary_bonus + $other_bonus;
        $totalExpenses   = $exp_adv + $exp_week + $exp_month + $exp_other;
        $payable         = $totalEarnings - $totalExpenses;
        $finalBalance    = $payable - $cash_paid;

        // =============================================
        // LOGIC: Staff পাবে নাকি কোম্পানি পাবে?
        // =============================================
        $staff_gets    = max(0,  $finalBalance);   // staff-এর পাওনা
        $company_gets  = max(0, -$finalBalance);   // কোম্পানির পাওনা

        // Staff টাকা পাওয়ার কথা কিন্তু cash_paid কম দেওয়া হয়েছে — block করব না, শুধু note করব
        $unpaid_to_staff = max(0, $payable - $cash_paid);

        // =============================================
        // TRANSACTION
        // =============================================
        $conn->beginTransaction();

        // Settlement INSERT
        $ins = $conn->prepare("INSERT INTO staff_settlements (
            staff_id, settlement_date, from_date, to_date,
            present_days, half_days, leave_days, absent_days, late_mins,
            leave_approved, absent_approved, late_deducted_mins,
            daily_salary, basic_earned,
            leave_cut_days, leave_cut_amount, absent_cut_days, absent_cut_amount,
            late_deduction_amount, salary_bonus, other_bonus, total_earnings,
            advance_expenses, weekly_expenses, monthly_expenses, other_expenses, total_expenses,
            payable_amount, cash_paid, final_balance, carry_forward_balance, created_at
        ) VALUES (?,NOW(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");

        $ins->execute([
            $staff_id, $from_date, $to_date,
            $att['P'], $att['H'], $att['L'], $att['A'], $att['Late'],
            $leave_approved, $absent_approved, $late_fine_mins,
            $daily_salary, $basicEarned,
            $leaveCutDays, $leaveCutAmt, $absentCutDays, $absentCutAmt,
            $lateDeduction, $salary_bonus, $other_bonus, $totalEarnings,
            $exp_adv, $exp_week, $exp_month, $exp_other, $totalExpenses,
            $payable, $cash_paid, $finalBalance, $company_gets
        ]);
        $settlement_id = $conn->lastInsertId();

        // Cash paid → expense record
        if ($cash_paid > 0) {
            $conn->prepare("INSERT INTO staff_expenses (staff_id, expense_type, description, amount, expense_date, entry_by) VALUES (?, 'Payslip Payment', ?, ?, ?, ?)")
                ->execute([$staff_id, "Payslip Payment — Settlement #{$settlement_id}", $cash_paid, $to_date, $_SESSION['username'] ?? 'Admin']);
        }

        // ✅ কোম্পানি টাকা পেলে — staff_expenses-এ "পাওনা আদায়" entry + running_balance-এ যোগ
        if ($company_gets > 0) {
            $desc = "{$staff['staff_name']} এর কাছ থেকে পাওনা আদায় — Settlement #{$settlement_id}";
            $conn->prepare("INSERT INTO staff_expenses (staff_id, expense_type, description, amount, expense_date, entry_by) VALUES (?, 'পাওনা আদায়', ?, ?, ?, ?)")
                ->execute([$staff_id, $desc, $company_gets, $to_date, $_SESSION['username'] ?? 'Admin']);
            // running_balance-এ যোগ (টাকা ফিরে এলো)
            $conn->prepare("UPDATE staff_info SET running_balance = running_balance + ? WHERE id = ?")
                ->execute([$company_gets, $staff_id]);
        }

        // Staff Inactive
        $conn->prepare("UPDATE staff_info SET staff_status='Inactive' WHERE id=?")->execute([$staff_id]);

        $conn->commit();

        // SMS
        $phone = $staff['staff_phone'] ?? '';
        if (!empty($phone)) {
            $fd = date('d/m/Y', strtotime($from_date));
            $td = date('d/m/Y', strtotime($to_date));
            $sms = "SADA KALO FASHION\n";
            $sms .= "চূড়ান্ত হিসাব সম্পন্ন!\n";
            $sms .= "সময়কাল: {$fd} - {$td}\n";
            $sms .= "মোট প্রাপ্য: Tk. ".number_format($payable,2)."\n";
            $sms .= "নগদ প্রদান: Tk. ".number_format($cash_paid,2)."\n";
            if ($company_gets > 0)
                $sms .= "কোম্পানির পাওনা: Tk. ".number_format($company_gets,2);
            elseif ($staff_gets > 0)
                $sms .= "আপনার পাওনা: Tk. ".number_format($staff_gets,2);
            sendSMS($phone, $sms);
        }

        // Response
        $msg = 'Payslip সেভ হয়েছে! Staff Inactive করা হয়েছে।';
        if ($company_gets > 0)
            $msg .= " | ✅ কোম্পানির পাওনা Tk. ".number_format($company_gets,2)." আদায় করা হয়েছে।";
        if ($cash_paid > 0 && $staff_gets >= 0)
            $msg .= " | 💸 Staff-কে Tk. ".number_format($cash_paid,2)." প্রদান করা হয়েছে।";

        echo json_encode([
            'status'        => 'success',
            'message'       => $msg,
            'company_gets'  => $company_gets,
            'staff_gets'    => $staff_gets,
            'cash_paid'     => $cash_paid,
            'final_balance' => $finalBalance,
        ]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        logErr("Payslip save error: ".$e->getMessage());
        echo json_encode(['status'=>'error','message'=>'সার্ভার এরর: '.$e->getMessage()]);
    }
} else {
    echo json_encode(['status'=>'error','message'=>'Invalid action']);
}
?>
