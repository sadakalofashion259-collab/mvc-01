<?php
declare(strict_types=1);
if (!defined('SK_APP')) { http_response_code(403); exit('403 Forbidden'); }

interface SupplierModelInterface
{
    public function hasTable(string $tableName): bool;

    /* Suppliers */
    public function getActiveSuppliers(): array;
    public function getInactiveSuppliers(): array;
    public function getSupplierById(int $id): ?array;
    /** @return int নতুন ID, ব্যর্থ হলে 0 */
    public function addSupplier(array $data): int;
    public function updateSupplier(int $id, array $data): bool;
    public function toggleStatus(int $id): string;
    public function deleteSupplierComplete(int $id): bool;

    /* SMS per-supplier */
    public function toggleSmsEnabled(int $id): int;
    public function getSmsEnabled(int $id): int;

    /* SMS logging & dashboard */
    public function logSms(array $data): void;
    public function getAllSuppliersForSms(): array;
    public function getRecentSms(int $limit): array;
    public function getSmsStats(): array;

    /* Transactions */
    public function getTransactions(int $supplierId): array;
    public function addTransaction(array $data): int;
    public function addStockFromTransaction(int $trId, string $desc, int $pcs, float $bill, string $img, string $entryBy): bool;
    public function getTransactionById(int $trId): ?array;
    public function getTransactionFull(int $trId): ?array;
    public function updateTransaction(int $trId, int $supId, array $data, string $newPhoto): bool;
    public function deleteTransactionComplete(int $trId, int $supId): bool;

    /* Auth */
    public function verifyAdminPassword(string $username, string $password): bool;
}
