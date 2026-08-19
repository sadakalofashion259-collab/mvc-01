<?php
declare(strict_types=1);

require_once MODULE_ROOT . '/Services/BillingService.php';
require_once MODULE_ROOT . '/Services/Logger.php';

final class LedgerModel
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getCycles(int $cardId): array
    {
        $st = $this->conn->prepare(
            "SELECT DISTINCT billing_cycle FROM sys_card_ledger WHERE card_id = ? ORDER BY billing_cycle DESC"
        );
        $st->execute([$cardId]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getByCycle(int $cardId, string $cycle): array
    {
        $st = $this->conn->prepare(
            "SELECT * FROM sys_card_ledger WHERE card_id = ? AND billing_cycle = ? ORDER BY txn_date DESC, id DESC"
        );
        $st->execute([$cardId, $cycle]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add(int $cardId, array $data, string $entryBy): void
    {
        $type     = $data['txn_type'] ?? '';
        $amount   = floatval($data['amount'] ?? 0);
        $charge   = floatval($data['charge_amount'] ?? 0);
        $txnDate  = $data['txn_date'] ?? date('Y-m-d');
        $note     = trim($data['description'] ?? '');

        $stC = $this->conn->prepare("SELECT billing_date FROM sys_credit_cards WHERE id = ?");
        $stC->execute([$cardId]);
        $bDate = (int) $stC->fetchColumn();
        $billingCycle = BillingService::getBillingCycle($txnDate, $bDate);

        $receipt = $this->handleReceiptUpload($_FILES['receipt_image'] ?? null);

        [$dueImp, $cashImp] = $this->calcImpacts($type, $amount, $charge);

        $this->conn->beginTransaction();
        try {
            $st = $this->conn->prepare(
                "INSERT INTO sys_card_ledger 
                (card_id, txn_date, billing_cycle, txn_type, amount, charge_amount, card_due_impact, cash_impact, running_due, description, receipt_image, entry_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)"
            );
            $st->execute([
                $cardId, $txnDate, $billingCycle, $type, $amount, $charge,
                $dueImp, $cashImp, $note, $receipt, $entryBy
            ]);
            $this->recalculateRunningDue($cardId);
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            Logger::error('Add transaction failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function update(int $ledgerId, int $cardId, array $data): void
    {
        $type     = $data['txn_type'] ?? '';
        $amount   = floatval($data['amount'] ?? 0);
        $charge   = floatval($data['charge_amount'] ?? 0);
        $txnDate  = $data['txn_date'] ?? date('Y-m-d');
        $note     = trim($data['description'] ?? '');

        $stC = $this->conn->prepare("SELECT billing_date FROM sys_credit_cards WHERE id = ?");
        $stC->execute([$cardId]);
        $bDate = (int) $stC->fetchColumn();
        $billingCycle = BillingService::getBillingCycle($txnDate, $bDate);

        [$dueImp, $cashImp] = $this->calcImpacts($type, $amount, $charge);

        $this->conn->beginTransaction();
        try {
            $st = $this->conn->prepare(
                "UPDATE sys_card_ledger SET txn_date=?, billing_cycle=?, txn_type=?, amount=?, charge_amount=?, card_due_impact=?, cash_impact=?, description=? WHERE id=? AND card_id=?"
            );
            $st->execute([
                $txnDate, $billingCycle, $type, $amount, $charge,
                $dueImp, $cashImp, $note, $ledgerId, $cardId
            ]);
            $this->recalculateRunningDue($cardId);
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            Logger::error('Update transaction failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(int $ledgerId): void
    {
        $this->conn->beginTransaction();
        try {
            $st = $this->conn->prepare("SELECT card_id, receipt_image FROM sys_card_ledger WHERE id = ?");
            $st->execute([$ledgerId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('এন্ট্রি পাওয়া যায়নি।');
            }
            if (!empty($row['receipt_image'])) {
                $path = MODULE_ROOT . '/' . ltrim($row['receipt_image'], '/');
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            $this->conn->prepare("DELETE FROM sys_card_ledger WHERE id = ?")->execute([$ledgerId]);
            $this->recalculateRunningDue((int) $row['card_id']);
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            Logger::error('Delete ledger failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function recalculateRunningDue(int $cardId): void
    {
        $st = $this->conn->prepare(
            "SELECT id, card_due_impact FROM sys_card_ledger WHERE card_id = ? ORDER BY txn_date ASC, id ASC"
        );
        $st->execute([$cardId]);
        $run = 0.0;
        $upd = $this->conn->prepare("UPDATE sys_card_ledger SET running_due = ? WHERE id = ?");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $run += floatval($r['card_due_impact']);
            $upd->execute([round($run, 2), $r['id']]);
        }
    }

    private function calcImpacts(string $type, float $amount, float $charge): array
    {
        $dueImp = 0.0;
        $cashImp = 0.0;
        switch ($type) {
            case 'purchase':
                $dueImp = $amount + $charge;
                $cashImp = 0;
                break;
            case 'cash_advance':
                $dueImp = $amount + $charge;
                $cashImp = $amount;
                break;
            case 'bill_pay':
            case 'min_pay':
            case 'full_pay':
                $dueImp = -$amount;
                $cashImp = -($amount + $charge);
                break;
            case 'charge_pay':
                $dueImp = 0;
                $cashImp = -$amount;
                break;
        }
        return [$dueImp, $cashImp];
    }

    private function handleReceiptUpload(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            return null;
        }
        $folder = MODULE_ROOT . '/uploads/card_receipts/';
        if (!is_dir($folder)) {
            @mkdir($folder, 0755, true);
        }
        $fname = 'rcp_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $folder . $fname)) {
            return 'uploads/card_receipts/' . $fname;
        }
        return null;
    }
}
