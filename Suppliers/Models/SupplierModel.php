<?php
declare(strict_types=1);
if (!defined('SK_APP')) { http_response_code(403); exit('403 Forbidden'); }

/**
 * সাপ্লায়ার ও লেনদেনের ডাটা লেয়ার। সব কোয়েরি prepared statement।
 * ছবি ডিলিট ImageUploader দিয়ে (path-traversal নিরাপদ)।
 */
final class SupplierModel implements SupplierModelInterface
{
    public function __construct(private \PDO $db) {}

    public function hasTable(string $tableName): bool
    {
        try {
            $stmt = $this->db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /* ============ Suppliers ============ */

    public function getActiveSuppliers(): array
    {
        try {
            return $this->db->query("
                SELECT s.*, MAX(t.tr_date) AS last_tr_date,
                       COALESCE(SUM(t.bill_received),0) AS total_bill,
                       COALESCE(SUM(t.payment_given),0) AS total_pay
                FROM suppliers s
                LEFT JOIN supplier_transactions t ON t.supplier_id = s.id
                WHERE (s.status IS NULL OR s.status = 'active')
                GROUP BY s.id
                ORDER BY last_tr_date DESC, s.id DESC
            ")->fetchAll() ?: [];
        } catch (\PDOException $e) {
            Logger::error('getActiveSuppliers failed', $e);
            return [];
        }
    }

    public function getInactiveSuppliers(): array
    {
        try {
            return $this->db->query("
                SELECT s.*,
                       COALESCE(SUM(t.bill_received),0) AS total_bill,
                       COALESCE(SUM(t.payment_given),0) AS total_pay
                FROM suppliers s
                LEFT JOIN supplier_transactions t ON t.supplier_id = s.id
                WHERE s.status = 'inactive'
                GROUP BY s.id
                ORDER BY s.id DESC
            ")->fetchAll() ?: [];
        } catch (\PDOException $e) {
            Logger::error('getInactiveSuppliers failed', $e);
            return [];
        }
    }

    public function getSupplierById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        } catch (\PDOException $e) {
            Logger::error("getSupplierById {$id} failed", $e);
            return null;
        }
    }

    /** @return int নতুন ID, ব্যর্থ হলে 0 */
    public function addSupplier(array $data): int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO suppliers (name, shop_name, phone, email, address, opening_balance, photo, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active')"
            );
            $ok = $stmt->execute([
                $data['name'], $data['shop_name'], $data['phone'], $data['email'],
                $data['address'], $data['opening_balance'], $data['photo'],
            ]);
            return $ok ? (int)$this->db->lastInsertId() : 0;
        } catch (\PDOException $e) {
            Logger::error('addSupplier failed', $e);
            return 0;
        }
    }

    public function updateSupplier(int $id, array $data): bool
    {
        try {
            if (($data['photo'] ?? '') !== '') {
                $stmt = $this->db->prepare(
                    "UPDATE suppliers SET name=?, shop_name=?, phone=?, email=?, address=?, opening_balance=?, photo=? WHERE id=?"
                );
                return $stmt->execute([
                    $data['name'], $data['shop_name'], $data['phone'], $data['email'],
                    $data['address'], $data['opening_balance'], $data['photo'], $id,
                ]);
            }
            $stmt = $this->db->prepare(
                "UPDATE suppliers SET name=?, shop_name=?, phone=?, email=?, address=?, opening_balance=? WHERE id=?"
            );
            return $stmt->execute([
                $data['name'], $data['shop_name'], $data['phone'], $data['email'],
                $data['address'], $data['opening_balance'], $id,
            ]);
        } catch (\PDOException $e) {
            Logger::error("updateSupplier {$id} failed", $e);
            return false;
        }
    }

    public function toggleStatus(int $id): string
    {
        try {
            // একটি অ্যাটমিক UPDATE — read-then-write race condition এড়াতে
            $this->db->prepare(
                "UPDATE suppliers
                 SET status = IF(COALESCE(status,'active') = 'active', 'inactive', 'active')
                 WHERE id = ?"
            )->execute([$id]);

            $cur = $this->db->prepare("SELECT status FROM suppliers WHERE id=?");
            $cur->execute([$id]);
            return (string)($cur->fetchColumn() ?: 'active');
        } catch (\PDOException $e) {
            Logger::error("toggleStatus {$id} failed", $e);
            throw $e;
        }
    }

    public function deleteSupplierComplete(int $id): bool
    {
        $filesToDelete = [];
        try {
            $this->db->beginTransaction();

            $pic = $this->db->prepare("SELECT photo FROM suppliers WHERE id=?");
            $pic->execute([$id]);
            $supPhoto = (string)($pic->fetchColumn() ?: '');

            $trs = $this->db->prepare("SELECT id, photo FROM supplier_transactions WHERE supplier_id=?");
            $trs->execute([$id]);
            $rows = $trs->fetchAll();

            $hasStocks = $this->hasTable('stocks');
            foreach ($rows as $tr) {
                if (!empty($tr['photo'])) {
                    $filesToDelete[] = (string)$tr['photo'];
                }
                if ($hasStocks) {
                    $this->db->prepare("DELETE FROM stocks WHERE transaction_id=?")->execute([$tr['id']]);
                }
            }

            $this->db->prepare("DELETE FROM supplier_transactions WHERE supplier_id=?")->execute([$id]);
            if ($supPhoto !== '') {
                $filesToDelete[] = $supPhoto;
            }
            $this->db->prepare("DELETE FROM suppliers WHERE id=?")->execute([$id]);

            $this->db->commit();

            // ফাইল ডিলিট শুধু কমিটের পরে — রোলব্যাক হলে ফাইল অক্ষত থাকে
            foreach ($filesToDelete as $f) {
                ImageUploader::delete($f);
            }
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error("deleteSupplierComplete {$id} failed", $e);
            return false;
        }
    }

    /* ============ Per-supplier SMS ============ */

    public function toggleSmsEnabled(int $id): int
    {
        try {
            $this->db->prepare(
                "UPDATE suppliers
                 SET sms_enabled = IF(COALESCE(sms_enabled,1) = 1, 0, 1)
                 WHERE id = ?"
            )->execute([$id]);

            $cur = $this->db->prepare("SELECT sms_enabled FROM suppliers WHERE id=?");
            $cur->execute([$id]);
            $v = $cur->fetchColumn();
            return ($v === false || $v === null) ? 1 : (int)$v;
        } catch (\PDOException $e) {
            Logger::error("toggleSmsEnabled {$id} failed", $e);
            throw $e;
        }
    }

    public function getSmsEnabled(int $id): int
    {
        try {
            $st = $this->db->prepare("SELECT sms_enabled FROM suppliers WHERE id=?");
            $st->execute([$id]);
            $v = $st->fetchColumn();
            return ($v === false || $v === null) ? 1 : (int)$v;
        } catch (\PDOException $e) {
            return 1;
        }
    }

    /* ============ SMS log & dashboard ============ */

    public function logSms(array $data): void
    {
        if (!$this->hasTable('sms_log')) {
            return;
        }
        try {
            $this->db->prepare(
                "INSERT INTO sms_log (supplier_id, tr_id, phone, message, status, trxn_id, sent_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $data['supplier_id'] ?? null,
                $data['tr_id']       ?? null,
                (string)($data['phone']   ?? ''),
                (string)($data['message'] ?? ''),
                (string)($data['status']  ?? 'pending'),
                (string)($data['trxn_id'] ?? ''),
                (string)($data['sent_by'] ?? ''),
            ]);
        } catch (\PDOException $e) {
            Logger::error('logSms failed', $e);
        }
    }

    public function getAllSuppliersForSms(): array
    {
        try {
            return $this->db->query("
                SELECT id, shop_name, name, phone, status,
                       COALESCE(sms_enabled,1) AS sms_enabled
                FROM suppliers
                WHERE (status IS NULL OR status = 'active')
                ORDER BY shop_name ASC
            ")->fetchAll() ?: [];
        } catch (\PDOException $e) {
            Logger::error('getAllSuppliersForSms failed', $e);
            return [];
        }
    }

    public function getRecentSms(int $limit): array
    {
        if (!$this->hasTable('sms_log')) {
            return [];
        }
        try {
            $limit = max(1, min(200, $limit));
            // LIMIT বাইন্ড করা হয়েছে PARAM_INT দিয়ে (emulate off-এও নিরাপদ)
            $stmt = $this->db->prepare("
                SELECT l.*, s.shop_name
                FROM sms_log l
                LEFT JOIN suppliers s ON s.id = l.supplier_id
                ORDER BY l.id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (\PDOException $e) {
            Logger::error('getRecentSms failed', $e);
            return [];
        }
    }

    public function getSmsStats(): array
    {
        $out = ['total' => 0, 'enabled' => 0, 'disabled' => 0, 'sent' => 0, 'failed' => 0];
        try {
            $row = $this->db->query("
                SELECT COUNT(*) AS total,
                       SUM(CASE WHEN COALESCE(sms_enabled,1)=1 THEN 1 ELSE 0 END) AS enabled
                FROM suppliers WHERE (status IS NULL OR status = 'active')
            ")->fetch();
            $out['total']    = (int)($row['total'] ?? 0);
            $out['enabled']  = (int)($row['enabled'] ?? 0);
            $out['disabled'] = $out['total'] - $out['enabled'];
        } catch (\PDOException $e) { /* ignore */ }

        if ($this->hasTable('sms_log')) {
            try {
                $r = $this->db->query("
                    SELECT SUM(status='sent') AS sent, SUM(status='failed') AS failed FROM sms_log
                ")->fetch();
                $out['sent']   = (int)($r['sent'] ?? 0);
                $out['failed'] = (int)($r['failed'] ?? 0);
            } catch (\PDOException $e) { /* ignore */ }
        }
        return $out;
    }

    /* ============ Transactions ============ */

    public function getTransactions(int $supplierId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM supplier_transactions WHERE supplier_id = ? ORDER BY tr_date DESC, id DESC"
            );
            $stmt->execute([$supplierId]);
            return $stmt->fetchAll() ?: [];
        } catch (\PDOException $e) {
            Logger::error("getTransactions {$supplierId} failed", $e);
            return [];
        }
    }

    public function addTransaction(array $data): int
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO supplier_transactions
                 (supplier_id, tr_date, memo_no, pcs, bill_received, payment_given, photo, entry_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $data['supplier_id'], $data['tr_date'], $data['memo_no'], $data['pcs'],
                $data['bill_received'], $data['payment_given'], $data['photo'], $data['entry_by'],
            ]);
            return (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            Logger::error('addTransaction failed', $e);
            throw $e;
        }
    }

    public function addStockFromTransaction(int $trId, string $desc, int $pcs, float $bill, string $img, string $entryBy): bool
    {
        try {
            return $this->db->prepare(
                "INSERT INTO stocks (description, in_qty, out_qty, total_bill, entry_by, image, transaction_id)
                 VALUES (?, ?, 0, ?, ?, ?, ?)"
            )->execute([$desc, $pcs, $bill, $entryBy, $img, $trId]);
        } catch (\PDOException $e) {
            Logger::error('addStockFromTransaction failed', $e);
            return false;
        }
    }

    public function getTransactionById(int $trId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT tr_date, memo_no, bill_received, photo FROM supplier_transactions WHERE id = ?");
            $stmt->execute([$trId]);
            return $stmt->fetch() ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    public function getTransactionFull(int $trId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM supplier_transactions WHERE id = ?");
            $stmt->execute([$trId]);
            return $stmt->fetch() ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    public function updateTransaction(int $trId, int $supId, array $data, string $newPhoto = ''): bool
    {
        $fileToDelete = '';
        try {
            $this->db->beginTransaction();

            $old = $this->getTransactionFull($trId);
            if (!$old || (int)$old['supplier_id'] !== $supId) {
                $this->db->rollBack();
                return false;
            }

            $photo = (string)($old['photo'] ?? '');
            if ($newPhoto !== '') {
                $fileToDelete = $photo; // old photo — শুধু কমিট সফল হলে ডিলিট হবে
                $photo = $newPhoto;
            }

            $this->db->prepare(
                "UPDATE supplier_transactions
                 SET tr_date=?, memo_no=?, pcs=?, bill_received=?, payment_given=?, photo=?
                 WHERE id=? AND supplier_id=?"
            )->execute([
                $data['tr_date'], $data['memo_no'], $data['pcs'],
                $data['bill_received'], $data['payment_given'], $photo, $trId, $supId,
            ]);

            if ($this->hasTable('stocks')) {
                $newBill = (float)$data['bill_received'];
                $newPcs  = (int)$data['pcs'];
                $chk = $this->db->prepare("SELECT id FROM stocks WHERE transaction_id=? LIMIT 1");
                $chk->execute([$trId]);
                $stockId = $chk->fetchColumn();

                if ($newBill > 0) {
                    if ($stockId) {
                        $this->db->prepare("UPDATE stocks SET in_qty=?, total_bill=?, image=? WHERE transaction_id=?")
                                 ->execute([$newPcs, $newBill, $photo, $trId]);
                    } else {
                        $sup  = $this->getSupplierById($supId);
                        $desc = ($sup['shop_name'] ?? '') . ' — Memo: ' . $data['memo_no']
                              . ' — ' . date('d M Y', strtotime((string)$data['tr_date']));
                        $this->db->prepare(
                            "INSERT INTO stocks (description, in_qty, out_qty, total_bill, entry_by, image, transaction_id)
                             VALUES (?, ?, 0, ?, ?, ?, ?)"
                        )->execute([$desc, $newPcs, $newBill, (string)($old['entry_by'] ?? ''), $photo, $trId]);
                    }
                } elseif ($stockId) {
                    $this->db->prepare("DELETE FROM stocks WHERE transaction_id=?")->execute([$trId]);
                }
            }

            $this->db->commit();

            if ($fileToDelete !== '') {
                ImageUploader::delete($fileToDelete);
            }
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error("updateTransaction {$trId} failed", $e);
            return false;
        }
    }

    public function deleteTransactionComplete(int $trId, int $supId): bool
    {
        $fileToDelete = '';
        try {
            $this->db->beginTransaction();
            $tr = $this->getTransactionById($trId);

            if ($tr) {
                if ((float)$tr['bill_received'] > 0 && $this->hasTable('stocks')) {
                    $this->db->prepare("DELETE FROM stocks WHERE transaction_id = ? LIMIT 1")->execute([$trId]);
                }
                $fileToDelete = (string)($tr['photo'] ?? '');
                $this->db->prepare("DELETE FROM supplier_transactions WHERE id = ? AND supplier_id = ?")
                         ->execute([$trId, $supId]);
            }

            $this->db->commit();

            if ($fileToDelete !== '') {
                ImageUploader::delete($fileToDelete);
            }
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error("deleteTransactionComplete {$trId} failed", $e);
            return false;
        }
    }

    /* ============ Auth ============ */

    /**
     * অ্যাডমিন পাসওয়ার্ড যাচাই।
     * নিরাপত্তা: শুধু password_verify (bcrypt/argon)। পুরোনো md5 হ্যাশ থাকলে
     * একবার মিলিয়ে সাথে সাথে password_hash-এ আপগ্রেড (rehash) করে দেয়।
     */
    public function verifyAdminPassword(string $username, string $password): bool
    {
        try {
            $st = $this->db->prepare("SELECT id, password FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
            $st->execute([$username]);
            $row = $st->fetch();
            if (!$row) {
                return false;
            }
            $stored = (string)$row['password'];

            if (password_verify($password, $stored)) {
                if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                    $this->rehash((int)$row['id'], $password);
                }
                return true;
            }

            // লিগ্যাসি md5 — একবার মিললে নিরাপদ হ্যাশে মাইগ্রেট করি
            if (preg_match('/^[a-f0-9]{32}$/i', $stored) && hash_equals($stored, md5($password))) {
                $this->rehash((int)$row['id'], $password);
                return true;
            }
            return false;
        } catch (\PDOException $e) {
            Logger::error('verifyAdminPassword failed', $e);
            return false;
        }
    }

    private function rehash(int $userId, string $password): void
    {
        try {
            $this->db->prepare("UPDATE users SET password = ? WHERE id = ?")
                     ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        } catch (\PDOException $e) {
            Logger::error("password rehash failed for user {$userId}", $e);
        }
    }
}
