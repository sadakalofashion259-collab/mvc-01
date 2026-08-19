<?php
/**
 * controllers/DashboardController.php
 * ─────────────────────────────────────────
 * সামারি কার্ড ডেটা + দৈনিক অটো-মুনাফা cron।
 */
declare(strict_types=1);

final class DashboardController
{
    public function __construct(private PDO $pdo) {}

    public function summary(): void
    {
        $today = date('Y-m-d');
        $sumByDate = function (string $sql, string $today): float {
            $st = $this->pdo->prepare($sql);
            $st->execute([$today]);
            return (float)$st->fetchColumn();
        };

        $r = [
            'todayDeposit'  => $sumByDate("SELECT COALESCE(SUM(deposit_amount),0) FROM sys_dps_ledger WHERE txn_date = ? AND description NOT LIKE '%মুনাফা%' AND description NOT LIKE '%Opening%'", $today),
            'todayProfit'   => $sumByDate("SELECT COALESCE(SUM(deposit_amount),0) FROM sys_dps_ledger WHERE txn_date = ? AND description LIKE '%মুনাফা%'", $today),
            'todayWithdraw' => $sumByDate("SELECT COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE txn_date = ?", $today),
            'totalBalance'  => (float)$this->pdo->query("SELECT COALESCE(SUM(total_balance),0) FROM sys_dps_accounts WHERE status='active'")->fetchColumn(),
            'totalProfit'   => (float)$this->pdo->query("SELECT COALESCE(SUM(total_profit_earned),0) FROM sys_dps_accounts")->fetchColumn(),
            'activeCount'   => (int)$this->pdo->query("SELECT COUNT(id) FROM sys_dps_accounts WHERE status='active'")->fetchColumn(),
            'inactiveCount' => (int)$this->pdo->query("SELECT COUNT(id) FROM sys_dps_accounts WHERE status='inactive'")->fetchColumn(),
        ];
        SecurityHelper::jsonSuccess($r);
    }

    /**
     * প্রতিদিন সক্রিয় একাউন্টে সুদ যোগ করে — duplicate-safe (daily_cron_log টেবিল দিয়ে চেক)।
     * Production-এ এটাকে সার্ভার crontab-এ সরানোই ভালো (এখানে page-load ভিত্তিক fallback)।
     */
    public function runDailyInterestCron(): void
    {
        $todayStr = date('Y-m-d');
        $stmtCron = $this->pdo->prepare('SELECT dps_processed FROM daily_cron_log WHERE run_date = ?');
        $stmtCron->execute([$todayStr]);
        $cronLog = $stmtCron->fetch();

        if ($cronLog && (int)$cronLog['dps_processed'] === 1) {
            return; // আজকের জন্য ইতিমধ্যে চলেছে
        }

        $accounts = new DpsAccountModel($this->pdo);
        $ledger   = new DpsLedgerModel($this->pdo);

        $this->pdo->beginTransaction();
        try {
            foreach ($accounts->allActiveForCron() as $acc) {
                $accId = (int)$acc['id'];
                $bal   = round((float)$acc['total_balance'], 2);
                $rate  = (float)$acc['interest_rate'];

                if ($ledger->duplicateProfitToday($accId, $todayStr) || $bal <= 0 || $rate <= 0) {
                    continue;
                }

                $daily = round(($bal * ($rate / 100)) / 365, 2);
                if ($daily <= 0) {
                    continue;
                }

                $newBal  = round($bal + $daily, 2);
                $newEarn = round((float)$acc['total_profit_earned'] + $daily, 2);
                $desc    = "মুনাফা (অটো) ({$rate}%), ৳" . number_format($daily, 2);

                $accounts->updateBalanceAndProfit($accId, $newBal, $newEarn);
                $ledger->insertEntry($accId, $todayStr, $desc, $daily, 0.00);
                // BUGFIX: insertEntry() সবসময় current_balance = 0.00 দিয়ে সারি তৈরি করে।
                // recalculate() না চালালে এই মুনাফা এন্ট্রির "ব্যালেন্স" কলাম লেজারে ০ থেকে যেত,
                // যদিও sys_dps_accounts.total_balance ঠিক থাকত — টেবিল ভিউতে ভুল ব্যালেন্স দেখাত।
                $ledger->recalculate($accId);
            }

            if (!$cronLog) {
                $this->pdo->prepare('INSERT INTO daily_cron_log (run_date, loans_processed, dps_processed, total_interest, created_at) VALUES (?,0,1,0.00,NOW())')
                    ->execute([$todayStr]);
            } else {
                $this->pdo->prepare('UPDATE daily_cron_log SET dps_processed = 1 WHERE run_date = ?')->execute([$todayStr]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            SecurityHelper::logError('DPS_DAILY_CRON', $e);
        }
    }
}
