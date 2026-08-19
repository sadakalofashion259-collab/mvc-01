<?php
declare(strict_types=1);

class CollectionModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getPeriodSum(string $from, string $to): float
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(c.total_deposit), 0)
                 FROM collection_entries c
                 INNER JOIN daily_reports dr ON c.report_id = dr.id
                 WHERE dr.report_date BETWEEN ? AND ?"
            );
            $stmt->execute([$from, $to]);
            return (float) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPrePeriodSum(string $from): float
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(c.total_deposit), 0)
                 FROM collection_entries c
                 INNER JOIN daily_reports dr ON c.report_id = dr.id
                 WHERE dr.report_date < ?"
            );
            $stmt->execute([$from]);
            return (float) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getByDate(string $date): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT c.*
                 FROM collection_entries c
                 INNER JOIN daily_reports dr ON c.report_id = dr.id
                 WHERE dr.report_date = ?"
            );
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
