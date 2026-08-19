<?php
declare(strict_types=1);

class SalesModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Period sum — original history.php uses report_id via daily_reports
     */
    public function getPeriodSum(string $from, string $to, string $column): float
    {
        $allowed = ['paid_amount', 'due_amount', 'total_bill', 'quantity'];
        if (!in_array($column, $allowed, true)) {
            return 0.0;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(s.{$column}), 0)
                 FROM sales_entries s
                 INNER JOIN daily_reports dr ON s.report_id = dr.id
                 WHERE dr.report_date BETWEEN ? AND ?"
            );
            $stmt->execute([$from, $to]);
            return (float) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPrePeriodSum(string $from, string $column): float
    {
        $allowed = ['paid_amount', 'due_amount', 'total_bill', 'quantity'];
        if (!in_array($column, $allowed, true)) {
            return 0.0;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(s.{$column}), 0)
                 FROM sales_entries s
                 INNER JOIN daily_reports dr ON s.report_id = dr.id
                 WHERE dr.report_date < ?"
            );
            $stmt->execute([$from]);
            return (float) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    /** Daily rows by report_date (via report_id) */
    public function getByDate(string $date): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT s.*
                 FROM sales_entries s
                 INNER JOIN daily_reports dr ON s.report_id = dr.id
                 WHERE dr.report_date = ?"
            );
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
