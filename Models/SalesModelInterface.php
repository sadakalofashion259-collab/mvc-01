<?php
declare(strict_types=1);

interface SalesModelInterface {
    public function beginTransaction(): void;
    public function commitTransaction(): void;
    public function rollBackTransaction(): void;
    public function getOrCreateDailyReportId(string $date, string $preparedBy): int;
    public function getCustomerNameById(int $customerId): string;
    public function getNextMemoNumber(): int;
    public function insertSaleEntry(int $reportId, int $memoNo, string $customerName, float $qty, float $bill, float $paid, float $due, string $photo, string $entryBy): bool;
    public function insertCustomerTransaction(int $customerId, string $date, string $description, float $billAmount, float $receivedAmount, string $entryBy): bool;
    public function logError(string $message, \Throwable $exception): void;
}
?>