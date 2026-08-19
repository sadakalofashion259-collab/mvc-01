<?php
declare(strict_types=1);

class SupplierTransactionModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getPeriodSum(string $from, string $to): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(payment_given),0) FROM supplier_transactions WHERE tr_date BETWEEN ? AND ?");
            $stmt->execute([$from, $to]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPrePeriodSum(string $from): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(payment_given),0) FROM supplier_transactions WHERE tr_date < ?");
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
                "SELECT st.*, s.name, s.shop_name
                 FROM supplier_transactions st
                 JOIN suppliers s ON st.supplier_id = s.id
                 WHERE st.tr_date = ?"
            );
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
