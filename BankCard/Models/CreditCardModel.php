<?php
declare(strict_types=1);

require_once MODULE_ROOT . '/Services/EncryptionService.php';
require_once MODULE_ROOT . '/Services/BillingService.php';
require_once MODULE_ROOT . '/Services/Logger.php';

final class CreditCardModel
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getAll(): array
    {
        return $this->conn->query("SELECT * FROM sys_credit_cards ORDER BY status ASC, id DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $st = $this->conn->prepare("SELECT * FROM sys_credit_cards WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data, string $entryBy): int
    {
        $cardNumber = preg_replace('/\s+/', '', $data['card_number'] ?? '');
        $encNum     = EncryptionService::encrypt($cardNumber);
        $encPin     = !empty($data['card_pin']) ? EncryptionService::encrypt(trim($data['card_pin'])) : null;

        $imgPath = $this->handleImageUpload($_FILES['card_image'] ?? null, 'credit_cards', 'card_');

        $st = $this->conn->prepare(
            "INSERT INTO sys_credit_cards 
            (card_name, card_number_enc, card_last4, card_pin_enc, card_expiry, credit_limit, billing_date, grace_days, card_image, notes, status, entry_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)"
        );
        $st->execute([
            trim($data['card_name'] ?? ''),
            $encNum,
            substr($cardNumber, -4),
            $encPin,
            trim($data['card_expiry'] ?? ''),
            floatval($data['credit_limit'] ?? 0),
            intval($data['billing_date'] ?? 1),
            intval($data['grace_days'] ?? 15),
            $imgPath,
            trim($data['notes'] ?? ''),
            $entryBy
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $imgPath   = null;
        $updateImg = false;

        if (isset($_FILES['card_image']) && $_FILES['card_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['card_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $folder = MODULE_ROOT . '/uploads/credit_cards/';
                if (!is_dir($folder)) {
                    @mkdir($folder, 0755, true);
                }
                $fname = 'card_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['card_image']['tmp_name'], $folder . $fname)) {
                    $old = $this->find($id);
                    if ($old && !empty($old['card_image'])) {
                        $oldPath = MODULE_ROOT . '/' . ltrim($old['card_image'], '/');
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    // Store relative path from module root for web serving
                    $imgPath   = 'uploads/credit_cards/' . $fname;
                    $updateImg = true;
                }
            }
        }

        if ($updateImg) {
            $st = $this->conn->prepare(
                "UPDATE sys_credit_cards SET card_name=?, card_expiry=?, billing_date=?, grace_days=?, credit_limit=?, notes=?, card_image=? WHERE id=?"
            );
            $st->execute([
                trim($data['card_name'] ?? ''),
                trim($data['card_expiry'] ?? ''),
                intval($data['billing_date'] ?? 1),
                intval($data['grace_days'] ?? 15),
                floatval($data['credit_limit'] ?? 0),
                trim($data['notes'] ?? ''),
                $imgPath,
                $id
            ]);
        } else {
            $st = $this->conn->prepare(
                "UPDATE sys_credit_cards SET card_name=?, card_expiry=?, billing_date=?, grace_days=?, credit_limit=?, notes=? WHERE id=?"
            );
            $st->execute([
                trim($data['card_name'] ?? ''),
                trim($data['card_expiry'] ?? ''),
                intval($data['billing_date'] ?? 1),
                intval($data['grace_days'] ?? 15),
                floatval($data['credit_limit'] ?? 0),
                trim($data['notes'] ?? ''),
                $id
            ]);
        }
    }

    public function toggleStatus(int $id): string
    {
        $st = $this->conn->prepare("SELECT status FROM sys_credit_cards WHERE id = ?");
        $st->execute([$id]);
        $current = $st->fetchColumn();
        $new = ($current === 'active') ? 'inactive' : 'active';
        $this->conn->prepare("UPDATE sys_credit_cards SET status = ? WHERE id = ?")->execute([$new, $id]);
        return $new;
    }

    public function delete(int $id): void
    {
        $this->conn->beginTransaction();
        try {
            $card = $this->find($id);
            if ($card && !empty($card['card_image'])) {
                $path = MODULE_ROOT . '/' . ltrim($card['card_image'], '/');
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            $st = $this->conn->prepare("SELECT receipt_image FROM sys_card_ledger WHERE card_id = ? AND receipt_image IS NOT NULL");
            $st->execute([$id]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $r) {
                if (!empty($r)) {
                    $path = MODULE_ROOT . '/' . ltrim($r, '/');
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }

            $this->conn->prepare("DELETE FROM sys_card_ledger WHERE card_id = ?")->execute([$id]);
            $this->conn->prepare("DELETE FROM sys_credit_cards WHERE id = ?")->execute([$id]);
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            Logger::error('Delete card failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getDecryptedSecrets(int $id): array
    {
        $st = $this->conn->prepare("SELECT card_number_enc, card_pin_enc FROM sys_credit_cards WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('কার্ড পাওয়া যায়নি।');
        }
        return [
            'card_number' => EncryptionService::decrypt($row['card_number_enc'] ?? ''),
            'card_pin'    => EncryptionService::decrypt($row['card_pin_enc'] ?? ''),
        ];
    }

    public function getSummary(array $card): array
    {
        $cid   = (int) $card['id'];
        $limit = floatval($card['credit_limit']);

        $st = $this->conn->prepare("SELECT 
            COALESCE(SUM(CASE WHEN txn_type IN ('purchase','cash_advance') THEN amount ELSE 0 END), 0) AS total_used,
            COALESCE(SUM(CASE WHEN txn_type IN ('bill_pay','min_pay','full_pay') THEN amount ELSE 0 END), 0) AS total_paid,
            COALESCE(SUM(charge_amount), 0) AS total_charge,
            COALESCE(SUM(card_due_impact), 0) AS raw_due
        FROM sys_card_ledger WHERE card_id = ?");
        $st->execute([$cid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        $rawDue     = floatval($r['raw_due']);
        $currentDue = max(0, $rawDue);
        $available  = $limit - $currentDue;
        $isOver     = $available < 0;

        return [
            'total_used'        => floatval($r['total_used']),
            'total_paid'        => floatval($r['total_paid']),
            'total_charge'      => floatval($r['total_charge']),
            'current_due'       => $currentDue,
            'available_balance' => $isOver ? 0 : $available,
            'is_overlimit'      => $isOver,
            'overlimit_amt'     => $isOver ? abs($available) : 0,
            'limit'             => $limit,
        ];
    }

    public function getLight(array $card, float $currentDue): array
    {
        $today = date('Y-m-d');
        if ($currentDue <= 0.01) {
            return ['color' => 'green', 'label' => 'সম্পূর্ণ পরিশোধিত', 'pulse' => true];
        }

        $currentCycle = BillingService::getBillingCycle($today, (int) $card['billing_date']);
        $st = $this->conn->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM sys_card_ledger 
             WHERE card_id=? AND txn_type IN ('bill_pay','min_pay','full_pay') AND billing_cycle=?"
        );
        $st->execute([$card['id'], $currentCycle]);
        $paidThisCycle = floatval($st->fetchColumn());

        $lastCycleEnd = date('Y-m-d', strtotime($currentCycle . '-' . str_pad((string) $card['billing_date'], 2, '0', STR_PAD_LEFT)));
        $dueDate      = date('Y-m-d', strtotime($lastCycleEnd . " + {$card['grace_days']} days"));

        if ($today > $dueDate && $currentDue > 0 && $paidThisCycle <= 0) {
            return ['color' => 'red', 'label' => 'ওভারডিউ! চার্জ যুক্ত হবে', 'pulse' => true];
        }
        if ($paidThisCycle > 0) {
            return ['color' => 'yellow', 'label' => 'আংশিক/মিনিমাম পরিশোধ', 'pulse' => true];
        }
        return ['color' => 'red', 'label' => 'বিল বাকি আছে', 'pulse' => false];
    }

    private function handleImageUpload(?array $file, string $subDir, string $prefix): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return null;
        }
        $folder = MODULE_ROOT . '/uploads/' . $subDir . '/';
        if (!is_dir($folder)) {
            @mkdir($folder, 0755, true);
        }
        $fname = $prefix . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $folder . $fname)) {
            return 'uploads/' . $subDir . '/' . $fname;
        }
        return null;
    }
}
