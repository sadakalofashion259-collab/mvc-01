<?php
declare(strict_types=1);

require_once __DIR__ . '/../Interfaces/ExpenseRepoInterface.php';

class ExpenseRepository implements ExpenseRepoInterface {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getExpenses(string $date, string $folderMonth): array {
        $grouped = [];
        if (!empty($folderMonth)) {
            $stmt = $this->db->prepare("SELECT e.*, d.report_date FROM expense_entries e JOIN daily_reports d ON e.report_id = d.id WHERE DATE_FORMAT(d.report_date, '%Y-%m') = ? ORDER BY d.report_date DESC, e.id DESC LIMIT 500");
            $stmt->execute([$folderMonth]);
        } else {
            $stmt = $this->db->prepare("SELECT e.*, d.report_date FROM expense_entries e JOIN daily_reports d ON e.report_id = d.id WHERE d.report_date >= ? ORDER BY d.report_date DESC, e.id DESC LIMIT 500");
            $stmt->execute([$date]);
        }
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $row) { $grouped[$row['report_date']][] = $row; }
        return $grouped;
    }

    public function getCategories(): array {
        $stmt = $this->db->query("SELECT id, category_name, COALESCE(is_active, 1) as is_active FROM expense_categories ORDER BY COALESCE(is_active, 1) DESC, category_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveCategories(): array {
        $stmt = $this->db->query("SELECT id, category_name FROM expense_categories WHERE COALESCE(is_active, 1) = 1 ORDER BY category_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFolderStats(string $currentYear): array {
        $stmt = $this->db->query("SELECT DATE_FORMAT(d.report_date, '%Y') as year, DATE_FORMAT(d.report_date, '%m') as month, DATE_FORMAT(d.report_date, '%M') as month_name, SUM(e.amount) as total FROM expense_entries e JOIN daily_reports d ON e.report_id = d.id GROUP BY year, month ORDER BY year DESC, month DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return int নতুন expense ID, ব্যর্থ হলে 0 */
    public function saveExpense(string $date, string $category, float $amount, ?string $photoPath, string $entryBy, ?string $note = null): int {
        try {
            $this->db->beginTransaction();
            $check = $this->db->prepare("SELECT id FROM daily_reports WHERE report_date = ? FOR UPDATE");
            $check->execute([$date]);
            $report = $check->fetch(PDO::FETCH_ASSOC);
            if ($report) {
                $report_id = $report['id'];
            } else {
                $ins = $this->db->prepare("INSERT INTO daily_reports (report_date, prepared_by) VALUES (?, ?)");
                $ins->execute([$date, $entryBy]);
                $report_id = (int)$this->db->lastInsertId();
            }
            $stmt = $this->db->prepare("INSERT INTO expense_entries (report_id, description, amount, photo, entry_by, note) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$report_id, $category, $amount, $photoPath, $entryBy, $note]);
            $newId = (int)$this->db->lastInsertId();
            $this->db->commit();
            return $newId > 0 ? $newId : 0;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Expense Save DB Error: " . $e->getMessage());
            return 0;
        }
    }

    public function getExpenseById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT e.*, d.report_date
             FROM expense_entries e
             JOIN daily_reports d ON e.report_id = d.id
             WHERE e.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getCategoryById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT id, category_name, COALESCE(is_active, 1) AS is_active FROM expense_categories WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deleteExpense(int $id): bool {
        return $this->db->prepare("DELETE FROM expense_entries WHERE id = ?")->execute([$id]);
    }

    public function updateExpense(int $id, float $amount, string $category, ?string $note = null): bool {
        return $this->db->prepare("UPDATE expense_entries SET amount = ?, description = ?, note = ? WHERE id = ?")->execute([$amount, $category, $note, $id]);
    }

    public function addCategory(string $name): bool {
        return $this->db->prepare("INSERT IGNORE INTO expense_categories (category_name, is_active) VALUES (?, 1)")->execute([$name]);
    }

    public function updateCategory(int $id, string $name): bool {
        return $this->db->prepare("UPDATE expense_categories SET category_name = ? WHERE id = ?")->execute([$name, $id]);
    }

    public function deleteCategory(int $id): bool {
        return $this->db->prepare("DELETE FROM expense_categories WHERE id = ?")->execute([$id]);
    }

    public function toggleCategoryStatus(int $id): bool {
        return $this->db->prepare("UPDATE expense_categories SET is_active = IF(COALESCE(is_active, 1) = 1, 0, 1) WHERE id = ?")->execute([$id]);
    }

    public function verifyAdmin(string $username, string $password): bool {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            $p = $admin['password'];
            return (password_verify($password, $p) || $p === md5($password) || $p === $password);
        }
        return false;
    }

    public function getPhotoPath(int $id): ?string {
        $stmt = $this->db->prepare("SELECT photo FROM expense_entries WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $data['photo'] : null;
    }

    public function filterExpenses(string $dateFrom, string $dateTo, string $category = ''): array {
        $rows = [];
        if (!empty($category)) {
            $stmt = $this->db->prepare(
                "SELECT e.id, d.report_date as date, e.description as category,
                        e.amount, e.note, e.entry_by
                 FROM expense_entries e
                 JOIN daily_reports d ON e.report_id = d.id
                 WHERE d.report_date BETWEEN ? AND ?
                   AND e.description = ?
                 ORDER BY d.report_date DESC, e.id DESC"
            );
            $stmt->execute([$dateFrom, $dateTo, $category]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT e.id, d.report_date as date, e.description as category,
                        e.amount, e.note, e.entry_by
                 FROM expense_entries e
                 JOIN daily_reports d ON e.report_id = d.id
                 WHERE d.report_date BETWEEN ? AND ?
                 ORDER BY d.report_date DESC, e.id DESC"
            );
            $stmt->execute([$dateFrom, $dateTo]);
        }

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $row) {
            $rows[] = [
                'id'         => $row['id'],
                'date'       => date('d-M-Y', strtotime($row['date'])),
                'category'   => htmlspecialchars($row['category'] ?? ''),
                'amount'     => $row['amount'],
                'amount_fmt' => number_format((float)$row['amount']),
                'note'       => htmlspecialchars($row['note'] ?? ''),
                'entry_by'   => htmlspecialchars($row['entry_by'] ?? ''),
            ];
        }
        return $rows;
    }
}
