<?php
declare(strict_types=1);

require_once __DIR__ . '/SalesModelInterface.php';

class SalesModel implements SalesModelInterface {
    // পুরনো PHP-এর জন্য টাইপ হিটিং ( \PDO, string ) রিমুভ করা হয়েছে
    private \PDO $dbConnection;
    private string $logFileDirectory;

    public function __construct(\PDO $dbConnection) {
        $this->dbConnection = $dbConnection;
        $this->logFileDirectory = __DIR__ . '/../Logs/error_log.txt';
    }

    public function logError(string $message, \Throwable $exception): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] SALES_ERROR: {$message} | Details: " . $exception->getMessage() . " | File: " . $exception->getFile() . " | Line: " . $exception->getLine() . PHP_EOL;
        error_log($logMessage, 3, $this->logFileDirectory);
    }

    public function beginTransaction(): void {
        if (!$this->dbConnection->inTransaction()) {
            $this->dbConnection->beginTransaction();
        }
    }

    public function commitTransaction(): void {
        if ($this->dbConnection->inTransaction()) {
            $this->dbConnection->commit();
        }
    }

    public function rollBackTransaction(): void {
        if ($this->dbConnection->inTransaction()) {
            $this->dbConnection->rollBack();
        }
    }

    public function getOrCreateDailyReportId(string $date, string $preparedBy): int {
        $checkStmt = $this->dbConnection->prepare("SELECT id FROM daily_reports WHERE report_date = ? LIMIT 1");
        $checkStmt->execute([$date]);
        $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing && isset($existing['id'])) {
            return (int)$existing['id'];
        }

        $stmt = $this->dbConnection->prepare("INSERT INTO daily_reports (report_date, prepared_by) VALUES (?, ?)");
        $stmt->execute([$date, $preparedBy]);
        return (int)$this->dbConnection->lastInsertId();
    }

    public function getCustomerNameById(int $customerId): string {
        $stmt = $this->dbConnection->prepare("SELECT customer_name, shop_name FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($data) {
            return !empty($data['shop_name']) ? (string)$data['shop_name'] : (string)$data['customer_name'];
        }
        return '';
    }

    public function getNextMemoNumber(): int {
        $stmt = $this->dbConnection->query("SELECT MAX(CAST(memo_no AS UNSIGNED)) as max_memo FROM sales_entries");
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        return ($data && $data['max_memo']) ? (int)$data['max_memo'] : 0;
    }

    public function insertSaleEntry(int $reportId, int $memoNo, string $customerName, float $qty, float $bill, float $paid, float $due, string $photo, string $entryBy): bool {
        try {
            $query = "INSERT INTO sales_entries (report_id, memo_no, customer_name, quantity, total_bill, paid_amount, due_amount, photo, entry_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $statement = $this->dbConnection->prepare($query);
            return $statement->execute([$reportId, $memoNo, $customerName, $qty, $bill, $paid, $due, $photo, $entryBy]);
        } catch (\PDOException $exception) {
            $this->logError("Failed to insert sale entry for Memo: {$memoNo}", $exception);
            throw $exception; 
        }
    }

    public function insertCustomerTransaction(int $customerId, string $date, string $description, float $billAmount, float $receivedAmount, string $entryBy): bool {
        try {
            $query = "INSERT INTO customer_transactions (customer_id, tr_date, description, bill_amount, received_amount, entry_by) VALUES (?, ?, ?, ?, ?, ?)";
            $statement = $this->dbConnection->prepare($query);
            return $statement->execute([$customerId, $date, $description, $billAmount, $receivedAmount, $entryBy]);
        } catch (\PDOException $exception) {
            $this->logError("Failed to insert transaction for Customer: {$customerId}", $exception);
            throw $exception;
        }
    }
}
?>