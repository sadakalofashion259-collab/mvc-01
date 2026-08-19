<?php
declare(strict_types=1);

interface ExpenseRepoInterface {
    public function getExpenses(string $date, string $folderMonth): array;
    public function getCategories(): array;
    public function getActiveCategories(): array;
    public function getFolderStats(string $currentYear): array;
    /** @return int নতুন expense ID, ব্যর্থ হলে 0 */
    public function saveExpense(string $date, string $category, float $amount, ?string $photoPath, string $entryBy, ?string $note = null): int;
    public function deleteExpense(int $id): bool;
    public function updateExpense(int $id, float $amount, string $category, ?string $note = null): bool;
    public function getExpenseById(int $id): ?array;
    public function addCategory(string $name): bool;
    public function updateCategory(int $id, string $name): bool;
    public function deleteCategory(int $id): bool;
    public function toggleCategoryStatus(int $id): bool;
    public function getCategoryById(int $id): ?array;
    public function verifyAdmin(string $username, string $password): bool;
    public function getPhotoPath(int $id): ?string;
    public function filterExpenses(string $dateFrom, string $dateTo, string $category = ''): array;
}
