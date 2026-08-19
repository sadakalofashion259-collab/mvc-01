<?php
declare(strict_types=1);

class StockModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // নতুন স্টক টেবিল (stocks) — created_at datetime
    public function getByDate(string $date): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM stocks WHERE DATE(created_at) = ?");
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    // পুরনো স্টক টেবিল (stock_entries)
    public function getByDateOld(string $date): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM stock_entries WHERE tr_date = ?");
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
