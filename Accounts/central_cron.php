<?php
declare(strict_types=1);

// =============================================================
// 🚀 সেন্ট্রাল অটোমেশন (Cron Job) - লোন এবং ডিপিএস
// ফাইল লোকেশন: Sadakalo_Account/central_cron.php
// ==============================================================
date_default_timezone_set('Asia/Dhaka'); 

// মূল ফোল্ডারের ডাটাবেস লিংক (Cron job এর জন্য __DIR__ ব্যবহার করে Absolute Path দেওয়া হলো)
include __DIR__ . '/../db_connect.php'; 

if(isset($conn)) { $pdo = $conn; }

class CronAutomationService {
    private PDO $db;
    private string $todayDateStr;
    private float $totalDailyInterest = 0.0;
    private string $logMsg = "";

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->todayDateStr = date('Y-m-d');
    }

    public function runCron(): string {
        try {
            // ১. দৈনিক ক্রন লগ টেবিল চেক ও আপডেট
            $stmtCheck = $this->db->prepare("SELECT * FROM daily_cron_log WHERE run_date = ?");
            $stmtCheck->execute([$this->todayDateStr]);
            $cronLog = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$cronLog) {
                $this->db->prepare("INSERT INTO daily_cron_log (run_date, loans_processed, dps_processed, total_interest, created_at) VALUES (?, 0, 0, 0.00, NOW())")
                    ->execute([$this->todayDateStr]);
                $cronLog = ['loans_processed' => 0, 'dps_processed' => 0, 'total_interest' => 0.0];
            }

            $this->totalDailyInterest = (float)$cronLog['total_interest'];

            // পার্ট ১: লোন প্রসেসিং
            if ((int)$cronLog['loans_processed'] === 0) {
                $this->processLoans();
            }

            // পার্ট ২: ডিপিএস প্রসেসিং
            if ((int)$cronLog['dps_processed'] === 0) {
                $this->processDps();
            }

            return $this->logMsg !== "" ? $this->logMsg : "Already Processed Today.";

        } catch (Throwable $e) { 
            return "Cron System Error: " . $e->getMessage(); 
        }
    }

    private function processLoans(): void {
        $activeLoans = $this->db->query("SELECT * FROM sys_loans WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
        
        // Optimize N+1: লুপের বাইরে Prepared Statement রাখা হয়েছে
        // ব্র্যাকেট বা অটো/লোড সবকিছুর জন্যই %মুনাফা% এবং %Profit% চেক করবে
        $checkLoanSync = $this->db->prepare("SELECT COUNT(*) FROM sys_loan_ledger WHERE loan_id = ? AND txn_date = ? AND (description LIKE '%মুনাফা%' OR description LIKE '%Profit%')");
        $stmtAdded = $this->db->prepare("SELECT COALESCE(SUM(debit_amount), 0) FROM sys_loan_ledger WHERE loan_id = ? AND (description LIKE '%মুনাফা%' OR description LIKE '%Profit%')");
        $stmtUpdateLoan = $this->db->prepare("UPDATE sys_loans SET current_balance = ? WHERE id = ?");
        $stmtInsertLedger = $this->db->prepare("INSERT INTO sys_loan_ledger (loan_id, txn_date, description, debit_amount, credit_amount, balance, created_at) VALUES (?, ?, ?, ?, 0.00, ?, NOW())");
        
        $processedCount = 0;

        foreach ($activeLoans as $activeLoan) {
            try {
                $this->db->beginTransaction(); // প্রতিটি লোনের জন্য আলাদা ট্রানজেকশন

                $loanId = (int)$activeLoan['id'];
                $checkLoanSync->execute([$loanId, $this->todayDateStr]);
                
                if ((int)$checkLoanSync->fetchColumn() === 0) {
                    $totalExpectedInterest = (float)$activeLoan['total_interest'];
                    
                    $stmtAdded->execute([$loanId]);
                    $alreadyAddedInterest = (float)$stmtAdded->fetchColumn();
                    
                    if ($alreadyAddedInterest < $totalExpectedInterest) {
                        $totalDays = ($activeLoan['frequency'] === 'daily') ? (int)$activeLoan['total_installments'] : 
                                     (($activeLoan['frequency'] === 'weekly') ? (int)$activeLoan['total_installments'] * 7 : (int)$activeLoan['duration'] * 30);
                        
                        $dailyProfitAmt = ($totalDays > 0) ? ($totalExpectedInterest / $totalDays) : 0.0;
                        $dailyProfitAmt = round($dailyProfitAmt, 2); 
                        
                        // অতিরিক্ত যোগ হওয়া ঠেকাতে
                        if (($alreadyAddedInterest + $dailyProfitAmt) > $totalExpectedInterest) {
                            $dailyProfitAmt = $totalExpectedInterest - $alreadyAddedInterest;
                        }
                        
                        if ($dailyProfitAmt > 0) {
                            $newBalance = round((float)$activeLoan['current_balance'] + $dailyProfitAmt, 2);
                            $rateText = number_format((float)$activeLoan['interest_rate'], 2);
                            $profitText = number_format($dailyProfitAmt, 2);
                            
                            // ডিপিএস এর মতো হুবহু ব্র্যাকেট ফরম্যাট
                            $desc = "মুনাফা (অটো) ({$rateText}%), ৳{$profitText}";

                            $stmtUpdateLoan->execute([$newBalance, $loanId]);
                            $stmtInsertLedger->execute([$loanId, $this->todayDateStr, $desc, $dailyProfitAmt, $newBalance]);
                                
                            $this->totalDailyInterest += $dailyProfitAmt;
                            $processedCount++;
                        }
                    }
                }
                $this->db->commit();
            } catch (Exception $e) {
                if ($this->db->inTransaction()) { $this->db->rollBack(); }
                // কোনো একটি লোনে সমস্যা হলে সেটি লগ করবে, কিন্তু অন্য লোনগুলোর কাজ বন্ধ করবে না
                error_log("Cron Error for Loan ID {$activeLoan['id']}: " . $e->getMessage());
                continue;
            }
        }
        
        $this->db->prepare("UPDATE daily_cron_log SET loans_processed = 1, total_interest = ? WHERE run_date = ?")
                 ->execute([$this->totalDailyInterest, $this->todayDateStr]);
        $this->logMsg .= "Loan Profit Processed ({$processedCount} profiles). ";
    }

    private function processDps(): void {
        $activeAccounts = $this->db->query("SELECT * FROM sys_dps_accounts WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
        
        $checkAccSync = $this->db->prepare("SELECT COUNT(*) FROM sys_dps_ledger WHERE dps_id = ? AND txn_date = ? AND (description LIKE '%মুনাফা%' OR description LIKE '%Profit%')");
        $stmtUpdateDps = $this->db->prepare("UPDATE sys_dps_accounts SET total_balance = ?, total_profit_earned = ? WHERE id = ?");
        $stmtInsertDpsLedger = $this->db->prepare("INSERT INTO sys_dps_ledger (dps_id, txn_date, description, deposit_amount, withdraw_amount, current_balance, created_at) VALUES (?, ?, ?, ?, 0.00, ?, NOW())");

        $processedCount = 0;

        foreach ($activeAccounts as $acc) {
            try {
                $this->db->beginTransaction();

                $accId = (int)$acc['id'];
                $currentBalance = round((float)$acc['total_balance'], 2);
                $interestRate = (float)$acc['interest_rate'];
                
                $checkAccSync->execute([$accId, $this->todayDateStr]);
                
                if ((int)$checkAccSync->fetchColumn() === 0 && $currentBalance > 0 && $interestRate > 0) {
                    
                    $dailyProfitAmt = round(($currentBalance * ($interestRate / 100)) / 365, 2);
                    
                    if ($dailyProfitAmt > 0) {
                        $newBalance = round($currentBalance + $dailyProfitAmt, 2);
                        $newEarned = round((float)$acc['total_profit_earned'] + $dailyProfitAmt, 2);
                        
                        $rateText = $interestRate;
                        $profitText = number_format($dailyProfitAmt, 2);
                        
                        $desc = "মুনাফা (অটো) ({$rateText}%), ৳{$profitText}";

                        $stmtUpdateDps->execute([$newBalance, $newEarned, $accId]);
                        $stmtInsertDpsLedger->execute([$accId, $this->todayDateStr, $desc, $dailyProfitAmt, $newBalance]);
                        
                        $processedCount++;
                    }
                }
                $this->db->commit();
            } catch (Exception $e) {
                if ($this->db->inTransaction()) { $this->db->rollBack(); }
                error_log("Cron Error for DPS ID {$acc['id']}: " . $e->getMessage());
                continue;
            }
        }
        
        $this->db->prepare("UPDATE daily_cron_log SET dps_processed = 1 WHERE run_date = ?")->execute([$this->todayDateStr]);
        $this->logMsg .= "DPS Profit Processed ({$processedCount} profiles).";
    }
}
//
$cronService = new CronAutomationService($pdo);
echo $cronService->runCron();
?>