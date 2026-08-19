<?php
declare(strict_types=1);

class StaffExpenseModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getPeriodSum(string $from, string $to): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM staff_expenses WHERE expense_date BETWEEN ? AND ?");
            $stmt->execute([$from, $to]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPrePeriodSum(string $from): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM staff_expenses WHERE expense_date < ?");
            $stmt->execute([$from]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getByDate(string $date): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT se.*, s.staff_name AS name
                 FROM staff_expenses se
                 JOIN staff_info s ON se.staff_id = s.id
                 WHERE se.expense_date = ?"
            );
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
