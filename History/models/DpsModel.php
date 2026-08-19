<?php
declare(strict_types=1);

class DpsModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getPeriodDeposit(string $from, string $to): float
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(deposit_amount),0) FROM sys_dps_ledger
                 WHERE txn_date BETWEEN ? AND ?
                 AND description NOT LIKE '%মুনাফা%' AND description NOT LIKE '%Opening%'"
            );
            $stmt->execute([$from, $to]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPeriodWithdraw(string $from, string $to): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE txn_date BETWEEN ? AND ?");
            $stmt->execute([$from, $to]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPrePeriodDeposit(string $from): float
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(deposit_amount),0) FROM sys_dps_ledger
                 WHERE txn_date < ?
                 AND description NOT LIKE '%মুনাফা%' AND description NOT LIKE '%Opening%'"
            );
            $stmt->execute([$from]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPrePeriodWithdraw(string $from): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE txn_date < ?");
            $stmt->execute([$from]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getTotal(): float
    {
        try {
            $stmt = $this->db->query("SELECT COALESCE(SUM(total_balance),0) FROM sys_dps_accounts WHERE status='active'");
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getByDate(string $date): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT l.*, a.client_name, a.account_number, a.account_type, a.id AS acc_id
                 FROM sys_dps_ledger l
                 JOIN sys_dps_accounts a ON l.dps_id = a.id
                 WHERE l.txn_date = ? ORDER BY l.id DESC"
            );
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function deleteEntry(int $id): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT dps_id, description FROM sys_dps_ledger WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('এন্ট্রি পাওয়া যায়নি');
            if (stripos($row['description'], 'Opening') !== false) {
                throw new Exception('Opening Balance ডিলিট করা যাবে না');
            }
            $dps_id = (int)$row['dps_id'];
            $this->db->prepare("DELETE FROM sys_dps_ledger WHERE id = ?")->execute([$id]);

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(deposit_amount),0)-COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE dps_id = ?");
            $stmt->execute([$dps_id]);
            $balance = max(0, (float)$stmt->fetchColumn());
            $status = $balance <= 0.01 ? 'inactive' : 'active';
            $this->db->prepare("UPDATE sys_dps_accounts SET total_balance = ?, status = ? WHERE id = ?")->execute([$balance, $status, $dps_id]);

            $rowsStmt = $this->db->prepare("SELECT id, deposit_amount, withdraw_amount FROM sys_dps_ledger WHERE dps_id = ? ORDER BY txn_date ASC, id ASC");
            $rowsStmt->execute([$dps_id]);
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
            $running = 0.0;
            $upd = $this->db->prepare("UPDATE sys_dps_ledger SET current_balance = ? WHERE id = ?");
            foreach ($rows as $r) {
                $running += floatval($r['deposit_amount']) - floatval($r['withdraw_amount']);
                $upd->execute([round($running, 2), $r['id']]);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
