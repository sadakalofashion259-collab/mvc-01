<?php
declare(strict_types=1);

class CardModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getPeriodSummary(string $from, string $to): array
    {
        $data = [
            'gt_card_cash_out'    => 0.0,
            'gt_card_advance'     => 0.0,
            'gt_card_pay'         => 0.0,
            'gt_card_outstanding' => 0.0,
            'card_active_count'   => 0,
            'card_inactive_count' => 0,
        ];
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(ABS(cash_impact)),0) FROM sys_card_ledger WHERE txn_date BETWEEN ? AND ? AND cash_impact < 0");
            $stmt->execute([$from, $to]);
            $data['gt_card_cash_out'] = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(cash_impact),0) FROM sys_card_ledger WHERE txn_date BETWEEN ? AND ? AND cash_impact > 0");
            $stmt->execute([$from, $to]);
            $data['gt_card_advance'] = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM sys_card_ledger WHERE txn_date BETWEEN ? AND ? AND txn_type IN ('bill_pay','min_pay','full_pay')");
            $stmt->execute([$from, $to]);
            $data['gt_card_pay'] = (float)$stmt->fetchColumn();

            $stmt = $this->db->query("SELECT COALESCE(SUM(card_balance_change),0) FROM sys_card_ledger WHERE card_id IN (SELECT id FROM sys_credit_cards WHERE status='active')");
            $data['gt_card_outstanding'] = max(0, (float)$stmt->fetchColumn());

            $stmt = $this->db->query("SELECT COUNT(*) FROM sys_credit_cards WHERE status='active'");
            $data['card_active_count'] = (int)$stmt->fetchColumn();

            $stmt = $this->db->query("SELECT COUNT(*) FROM sys_credit_cards WHERE status='inactive'");
            $data['card_inactive_count'] = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            // ডিফল্ট 0 রয়েই যাবে
        }
        return $data;
    }

    public function getPrePeriodIn(string $from): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(cash_impact),0) FROM sys_card_ledger WHERE txn_date < ? AND cash_impact > 0");
            $stmt->execute([$from]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function getPrePeriodOut(string $from): float
    {
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(ABS(cash_impact)),0) FROM sys_card_ledger WHERE txn_date < ? AND cash_impact < 0");
            $stmt->execute([$from]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0.0;
        }
    }

    /**
     * FIX: আগে $condition হিসেবে raw SQL ('cash_impact < 0') পাস হতো — SQL injection surface।
     * এখন শুধু 'in' / 'out' নেয়, ভেতরে হার্ডকোডেড শর্ত বসে।
     */
    public function getByDate(string $date, string $direction): array
    {
        $cond = $direction === 'in' ? 'cl.cash_impact > 0' : 'cl.cash_impact < 0';
        try {
            $stmt = $this->db->prepare(
                "SELECT cl.*, cc.card_name, cc.card_last4
                 FROM sys_card_ledger cl
                 JOIN sys_credit_cards cc ON cl.card_id = cc.id
                 WHERE cl.txn_date = ? AND {$cond} ORDER BY cl.id DESC"
            );
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function deleteLedgerEntry(int $id): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT card_id, receipt_image FROM sys_card_ledger WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('এন্ট্রি পাওয়া যায়নি');
            $card_id = (int)$row['card_id'];
            if (!empty($row['receipt_image']) && is_file($row['receipt_image'])) @unlink($row['receipt_image']);
            $this->db->prepare("DELETE FROM sys_card_ledger WHERE id = ?")->execute([$id]);

            $rowsStmt = $this->db->prepare("SELECT id, card_balance_change FROM sys_card_ledger WHERE card_id = ? ORDER BY txn_date ASC, id ASC");
            $rowsStmt->execute([$card_id]);
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
            $running = 0.0;
            $upd = $this->db->prepare("UPDATE sys_card_ledger SET running_balance = ? WHERE id = ?");
            foreach ($rows as $r) {
                $running += floatval($r['card_balance_change']);
                $upd->execute([round($running, 2), $r['id']]);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
