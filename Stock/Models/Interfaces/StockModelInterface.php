<?php
declare(strict_types=1);

interface StockModelInterface {
    public function getSystemLocks(): array;
    public function toggleSystemLock(string $lockKey, int $newState): bool;
    public function insertStockEntry(string $description, int $qty, float $bill, ?string $imagePath, string $entryBy): bool;
    public function verifyAdminActionPassword(string $username, string $password): bool;
    public function deleteStockEntry(int $id): bool;
    public function getAggregatedMetrics(string $currentDate, string $currentMonth, string $currentYear): array;
    public function getHistoryData(string $fromDate, string $toDate): array;
    public function logError(string $message, \Throwable $exception): void;
}
