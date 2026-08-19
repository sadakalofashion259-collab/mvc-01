<?php
declare(strict_types=1);

class CustomerTransactionModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getPeriodSum(string $from, string $to): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(received_amount),0) FROM customer_transactions WHERE tr_date BETWEEN ? AND ?");
            $stmt->execute([$from, $to]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPrePeriodSum(string $from): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(received_amount),0) FROM customer_transactions WHERE tr_date < ?");
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
                "SELECT ct.*, c.customer_name, c.shop_name, c.phone
                 FROM customer_transactions ct
                 JOIN customers c ON ct.customer_id = c.id
                 WHERE ct.tr_date = ?"
            );
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
