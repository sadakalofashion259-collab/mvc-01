<?php
declare(strict_types=1);

/**
 * CustomerModelInterface — Contract for the customer data layer.
 * (SMS and e-mail concerns live in Services/, not in the model.)
 */
interface CustomerModelInterface
{
    public function logError(string $message, \Throwable $exception): void;

    // ---- Customers ----
    public function getActiveCustomers(): array;
    public function getInactiveCustomers(): array;
    public function getCustomerById(int $id): ?array;
    /** @return int নতুন ID, ব্যর্থ হলে 0 */
    public function addCustomer(array $data): int;
    public function updateCustomer(int $id, array $data): bool;
    public function toggleActiveStatus(int $id): int;
    public function toggleBillLock(int $id): int;
    public function updateProfilePic(int $id, string $path): bool;
    public function updateCollectionDates(int $id, ?string $d1, ?string $d2): bool;
    public function verifyAdminPassword(string $username, string $password): bool;
    public function deleteCustomerComplete(int $id): bool;

    // ---- Transactions ----
    public function getCustomerTransactions(int $customerId): array;
    /** @return int নতুন ID, ব্যর্থ হলে 0 */
    public function addTransaction(array $data): int;
    public function getTransactionById(int $trId): ?array;
    public function updateTransaction(int $trId, int $custId, array $data): bool;
    public function deleteTransactionComplete(int $trId, int $custId): bool;

    // ---- Aggregates ----
    public function getCustomerFinancialSummary(int $customerId): array;
}
