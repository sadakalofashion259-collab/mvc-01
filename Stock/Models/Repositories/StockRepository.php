<?php
declare(strict_types=1);

require_once __DIR__ . '/../Interfaces/StockModelInterface.php';

class StockRepository implements StockModelInterface {
    private \PDO $dbConnection;
    private string $logFileDirectory;

    public function __construct(\PDO $dbConnection) {
        $this->dbConnection = $dbConnection;
        $logDir = __DIR__ . '/../../Logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logFileDirectory = $logDir . '/error_log.txt';
    }

    public function logError(string $message, \Throwable $exception): void {
        $timestamp = date('Y-m-d H:i:s');
        $safeMessage = "[{$timestamp}] {$message} | " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine() . PHP_EOL;
        error_log($safeMessage, 3, $this->logFileDirectory);
    }

    public function getSystemLocks(): array {
        $sys_locks = ['box' => 0, 'folder' => 0, '7day' => 0, 'entry' => 0];
        try {
            $stmt = $this->dbConnection->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('lock_box', 'lock_folder', 'lock_7day', 'lock_entry')");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $k = str_replace('lock_', '', (string)$row['setting_key']);
                if (isset($sys_locks[$k])) {
                    $sys_locks[$k] = (int)$row['setting_value'];
                }
            }
        } catch (\PDOException $e) {
            $this->logError("Failed to fetch system locks", $e);
        }
        return $sys_locks;
    }

    public function toggleSystemLock(string $lockKey, int $newState): bool {
        try {
            $this->dbConnection->beginTransaction();
            $dbKey = 'lock_' . preg_replace('/[^a-z0-9_]/i', '', $lockKey);
            $stmt = $this->dbConnection->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $result = $stmt->execute([$dbKey, (string)$newState, (string)$newState]);
            $this->dbConnection->commit();
            return $result;
        } catch (\PDOException $e) {
            $this->dbConnection->rollBack();
            $this->logError("Failed to toggle system lock: {$lockKey}", $e);
            throw $e;
        }
    }

    public function insertStockEntry(string $description, int $qty, float $bill, ?string $imagePath, string $entryBy): bool {
        try {
            $this->dbConnection->beginTransaction();
            $stmt = $this->dbConnection->prepare("INSERT INTO stocks (description, in_qty, out_qty, total_bill, image, entry_by) VALUES (?, ?, 0, ?, ?, ?)");
            $result = $stmt->execute([$description, $qty, $bill, $imagePath, $entryBy]);
            $this->dbConnection->commit();
            return $result;
        } catch (\PDOException $e) {
            $this->dbConnection->rollBack();
            $this->logError("Failed to insert stock entry", $e);
            throw $e;
        }
    }

    public function verifyAdminActionPassword(string $username, string $password): bool {
        try {
            $stmt = $this->dbConnection->prepare("SELECT action_pass FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
            $stmt->execute([$username]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($data && password_verify($password, (string)$data['action_pass'])) {
                return true;
            }
            return false;
        } catch (\PDOException $e) {
            $this->logError("Failed to verify admin action password", $e);
            throw $e;
        }
    }

    public function deleteStockEntry(int $id): bool {
        try {
            $this->dbConnection->beginTransaction();
            $stmt = $this->dbConnection->prepare("DELETE FROM stocks WHERE id = ?");
            $result = $stmt->execute([$id]);
            $this->dbConnection->commit();
            return $result;
        } catch (\PDOException $e) {
            $this->dbConnection->rollBack();
            $this->logError("Failed to delete stock entry ID: {$id}", $e);
            throw $e;
        }
    }

    public function getAggregatedMetrics(string $currentDate, string $currentMonth, string $currentYear): array {
        $metrics = [
            'total_buy_qty' => 0, 'total_buy_val' => 0.0,
            'total_sell_qty' => 0, 'total_sell_val' => 0.0,
            'today_add_qty' => 0,
            'today_sell_qty' => 0, 'today_sell_val' => 0.0,
            'month_sell_qty' => 0, 'month_sell_val' => 0.0
        ];

        try {
            $buy = $this->dbConnection->query("SELECT SUM(in_qty) as q, SUM(total_bill) as v FROM stocks")->fetch(\PDO::FETCH_ASSOC);
            $metrics['total_buy_qty'] = (int)($buy['q'] ?? 0);
            $metrics['total_buy_val'] = (float)($buy['v'] ?? 0);

            $sell = $this->dbConnection->query("SELECT SUM(quantity) as q, SUM(total_bill) as v FROM sales_entries")->fetch(\PDO::FETCH_ASSOC);
            $metrics['total_sell_qty'] = (int)($sell['q'] ?? 0);
            $metrics['total_sell_val'] = (float)($sell['v'] ?? 0);

            $stmtTdyIn = $this->dbConnection->prepare("SELECT SUM(in_qty) as q FROM stocks WHERE DATE(created_at) = ?");
            $stmtTdyIn->execute([$currentDate]);
            $metrics['today_add_qty'] = (int)($stmtTdyIn->fetch(\PDO::FETCH_ASSOC)['q'] ?? 0);

            $stmtTdySell = $this->dbConnection->prepare("SELECT SUM(s.quantity) as q, SUM(s.total_bill) as v FROM sales_entries s JOIN daily_reports d ON s.report_id = d.id WHERE d.report_date = ?");
            $stmtTdySell->execute([$currentDate]);
            $todaySell = $stmtTdySell->fetch(\PDO::FETCH_ASSOC);
            $metrics['today_sell_qty'] = (int)($todaySell['q'] ?? 0);
            $metrics['today_sell_val'] = (float)($todaySell['v'] ?? 0);

            $stmtMoSell = $this->dbConnection->prepare("SELECT SUM(s.quantity) as q, SUM(s.total_bill) as v FROM sales_entries s JOIN daily_reports d ON s.report_id = d.id WHERE MONTH(d.report_date) = ? AND YEAR(d.report_date) = ?");
            $stmtMoSell->execute([$currentMonth, $currentYear]);
            $monthSell = $stmtMoSell->fetch(\PDO::FETCH_ASSOC);
            $metrics['month_sell_qty'] = (int)($monthSell['q'] ?? 0);
            $metrics['month_sell_val'] = (float)($monthSell['v'] ?? 0);

        } catch (\PDOException $e) {
            $this->logError("Failed to fetch aggregated metrics", $e);
        }
        return $metrics;
    }

    public function getHistoryData(string $fromDate, string $toDate): array {
        $list = [];
        try {
            $stmtStk = $this->dbConnection->prepare("SELECT id, created_at as sort_time, DATE(created_at) as dt, description as info, '' as memo, in_qty as q, total_bill as b, image as img, entry_by as eb, 'IN' as type, 'stocks' as tbl FROM stocks WHERE in_qty > 0 AND DATE(created_at) BETWEEN ? AND ?");
            $stmtStk->execute([$fromDate, $toDate]);
            foreach ($stmtStk->fetchAll(\PDO::FETCH_ASSOC) as $r) { $list[] = $r; }

            $stmtSel = $this->dbConnection->prepare("SELECT s.id, d.created_at as sort_time, d.report_date as dt, s.customer_name as info, s.memo_no as memo, s.quantity as q, s.total_bill as b, s.photo as img, s.entry_by as eb, 'OUT' as type, 'sales' as tbl FROM sales_entries s JOIN daily_reports d ON s.report_id = d.id WHERE d.report_date BETWEEN ? AND ?");
            $stmtSel->execute([$fromDate, $toDate]);
            foreach ($stmtSel->fetchAll(\PDO::FETCH_ASSOC) as $r) { $list[] = $r; }

            usort($list, function($a, $b) { return strtotime((string)$b['sort_time']) - strtotime((string)$a['sort_time']); });
        } catch (\PDOException $e) {
            $this->logError("Failed to fetch history data", $e);
        }
        return $list;
    }
}
