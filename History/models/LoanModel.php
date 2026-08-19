<?php
declare(strict_types=1);

class LoanModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getPeriodIn(string $from, string $to): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(debit_amount),0) FROM sys_loan_ledger WHERE txn_date BETWEEN ? AND ? AND description NOT LIKE '%মুনাফা%'");
            $stmt->execute([$from, $to]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPeriodOut(string $from, string $to): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE txn_date BETWEEN ? AND ?");
            $stmt->execute([$from, $to]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPrePeriodIn(string $from): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(debit_amount),0) FROM sys_loan_ledger WHERE txn_date < ? AND description NOT LIKE '%মুনাফা%'");
            $stmt->execute([$from]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPrePeriodOut(string $from): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE txn_date < ?");
            $stmt->execute([$from]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getOutstanding(): float
    {
        try {
            $stmt = $this->db->query("SELECT COALESCE(SUM(current_balance),0) FROM sys_loans WHERE status='active'");
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getByDate(string $date): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT l.*, s.borrower_name, s.loan_category
                 FROM sys_loan_ledger l
                 JOIN sys_loans s ON l.loan_id = s.id
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
            $stmt = $this->db->prepare("SELECT loan_id FROM sys_loan_ledger WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('এন্ট্রি পাওয়া যায়নি');
            $loan_id = (int)$row['loan_id'];
            $this->db->prepare("DELETE FROM sys_loan_ledger WHERE id = ?")->execute([$id]);

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(debit_amount),0)-COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE loan_id = ?");
            $stmt->execute([$loan_id]);
            $balance = max(0, (float)$stmt->fetchColumn());
            $status = $balance <= 0.01 ? 'inactive' : 'active';
            $this->db->prepare("UPDATE sys_loans SET current_balance = ?, status = ? WHERE id = ?")->execute([$balance, $status, $loan_id]);

            $rowsStmt = $this->db->prepare("SELECT id, debit_amount, credit_amount FROM sys_loan_ledger WHERE loan_id = ? ORDER BY txn_date ASC, id ASC");
            $rowsStmt->execute([$loan_id]);
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
            $running = 0.0;
            $upd = $this->db->prepare("UPDATE sys_loan_ledger SET balance = ? WHERE id = ?");
            foreach ($rows as $r) {
                $running += floatval($r['debit_amount']) - floatval($r['credit_amount']);
                $upd->execute([round($running, 2), $r['id']]);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
