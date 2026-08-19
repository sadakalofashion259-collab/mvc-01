<?php
declare(strict_types=1);

require_once __DIR__ . '/CustomerModelInterface.php';
require_once dirname(__DIR__) . '/Helpers/ImageUploader.php';

/**
 * CustomerModel — Data-access layer for customers & their transactions.
 *
 * Every query uses PDO prepared statements. No credentials, no HTTP calls
 * and no e-mail logic live here — those belong to Services/.
 */
class CustomerModel implements CustomerModelInterface
{
    private \PDO $dbConnection;
    private string $logFilePath;
    private ImageUploader $imageUploader;

    public function __construct(\PDO $dbConnection)
    {
        $this->dbConnection  = $dbConnection;
        $this->logFilePath   = dirname(__DIR__) . '/Logs/error_log.txt';
        $this->imageUploader = new ImageUploader();
    }

    // =============================================
    // Error logging
    // =============================================
    public function logError(string $message, \Throwable $exception): void
    {
        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $entry = '[' . date('Y-m-d H:i:s') . "] CUSTOMER_ERROR: {$message}"
               . ' | Details: ' . $exception->getMessage()
               . ' | File: '    . $exception->getFile()
               . ' | Line: '    . $exception->getLine() . PHP_EOL;
        error_log($entry, 3, $this->logFilePath);
    }

    // =============================================
    // Financial summary
    // =============================================
    public function getCustomerFinancialSummary(int $customerId): array
    {
        try {
            $custStmt = $this->dbConnection->prepare(
                'SELECT opening_balance FROM customers WHERE id = ?'
            );
            $custStmt->execute([$customerId]);
            $cust           = $custStmt->fetch(\PDO::FETCH_ASSOC);
            $openingBalance = (float)($cust['opening_balance'] ?? 0);

            $trStmt = $this->dbConnection->prepare(
                'SELECT COALESCE(SUM(bill_amount), 0)     AS total_bill,
                        COALESCE(SUM(received_amount), 0) AS total_rec
                   FROM customer_transactions
                  WHERE customer_id = ?'
            );
            $trStmt->execute([$customerId]);
            $tr = $trStmt->fetch(\PDO::FETCH_ASSOC);

            $totalBill = (float)$tr['total_bill'];
            $totalRec  = (float)$tr['total_rec'];

            return [
                'opening_balance' => $openingBalance,
                'total_bill'      => $totalBill,
                'total_rec'       => $totalRec,
                'net_due'         => ($openingBalance + $totalBill) - $totalRec,
            ];
        } catch (\PDOException $e) {
            $this->logError("getCustomerFinancialSummary failed for {$customerId}", $e);
            return ['opening_balance' => 0, 'total_bill' => 0, 'total_rec' => 0, 'net_due' => 0];
        }
    }

    // =============================================
    // Customers — CRUD
    // =============================================
    public function getActiveCustomers(): array
    {
        try {
            $stmt = $this->dbConnection->query(
                'SELECT c.*,
                        t.last_tr_date,
                        COALESCE(t.total_bill, 0) AS total_bill,
                        COALESCE(t.total_rec, 0)  AS total_rec
                   FROM customers c
                   LEFT JOIN (
                        SELECT customer_id,
                               MAX(tr_date)         AS last_tr_date,
                               SUM(bill_amount)     AS total_bill,
                               SUM(received_amount) AS total_rec
                          FROM customer_transactions
                         GROUP BY customer_id
                   ) t ON t.customer_id = c.id
                  WHERE c.is_active = 1
                  ORDER BY t.last_tr_date DESC, c.id DESC'
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            $this->logError('Failed to fetch active customers', $e);
            return [];
        }
    }

    public function getInactiveCustomers(): array
    {
        try {
            $stmt = $this->dbConnection->query(
                'SELECT c.*,
                        COALESCE(t.total_bill, 0) AS total_bill,
                        COALESCE(t.total_rec, 0)  AS total_rec
                   FROM customers c
                   LEFT JOIN (
                        SELECT customer_id,
                               SUM(bill_amount)     AS total_bill,
                               SUM(received_amount) AS total_rec
                          FROM customer_transactions
                         GROUP BY customer_id
                   ) t ON t.customer_id = c.id
                  WHERE c.is_active = 0
                  ORDER BY c.id DESC'
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            $this->logError('Failed to fetch inactive customers', $e);
            return [];
        }
    }

    public function getCustomerById(int $id): ?array
    {
        try {
            $stmt = $this->dbConnection->prepare('SELECT * FROM customers WHERE id = ?');
            $stmt->execute([$id]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $data ?: null;
        } catch (\PDOException $e) {
            $this->logError("Failed to fetch customer {$id}", $e);
            return null;
        }
    }

    /** @return int নতুন ID, ব্যর্থ হলে 0 */
    public function addCustomer(array $data): int
    {
        try {
            $stmt = $this->dbConnection->prepare(
                'INSERT INTO customers
                    (shop_name, customer_name, phone, address,
                     credit_limit, opening_balance, profile_pic, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $ok = $stmt->execute([
                $data['shop_name'], $data['customer_name'], $data['phone'],
                $data['address'], $data['credit_limit'],
                $data['opening_balance'], $data['profile_pic'],
            ]);
            return $ok ? (int)$this->dbConnection->lastInsertId() : 0;
        } catch (\PDOException $e) {
            $this->logError('Failed to add customer', $e);
            return 0;
        }
    }

    public function updateCustomer(int $id, array $data): bool
    {
        try {
            if ($data['profile_pic'] !== '') {
                $stmt = $this->dbConnection->prepare(
                    'UPDATE customers
                        SET shop_name = ?, customer_name = ?, phone = ?, address = ?,
                            credit_limit = ?, opening_balance = ?, profile_pic = ?
                      WHERE id = ?'
                );
                return $stmt->execute([
                    $data['shop_name'], $data['customer_name'], $data['phone'],
                    $data['address'], $data['credit_limit'],
                    $data['opening_balance'], $data['profile_pic'], $id,
                ]);
            }

            $stmt = $this->dbConnection->prepare(
                'UPDATE customers
                    SET shop_name = ?, customer_name = ?, phone = ?, address = ?,
                        credit_limit = ?, opening_balance = ?
                  WHERE id = ?'
            );
            return $stmt->execute([
                $data['shop_name'], $data['customer_name'], $data['phone'],
                $data['address'], $data['credit_limit'],
                $data['opening_balance'], $id,
            ]);
        } catch (\PDOException $e) {
            $this->logError("Failed to update customer {$id}", $e);
            return false;
        }
    }

    public function toggleActiveStatus(int $id): int
    {
        try {
            $cur = $this->dbConnection->prepare('SELECT is_active FROM customers WHERE id = ?');
            $cur->execute([$id]);
            $newState = ((int)$cur->fetchColumn()) ? 0 : 1;

            $this->dbConnection
                ->prepare('UPDATE customers SET is_active = ? WHERE id = ?')
                ->execute([$newState, $id]);

            return $newState;
        } catch (\PDOException $e) {
            $this->logError("Failed to toggle customer {$id}", $e);
            throw $e;
        }
    }

    public function toggleBillLock(int $id): int
    {
        try {
            $cur = $this->dbConnection->prepare('SELECT bill_locked FROM customers WHERE id = ?');
            $cur->execute([$id]);
            $newState = ((int)$cur->fetchColumn()) ? 0 : 1;

            $this->dbConnection
                ->prepare('UPDATE customers SET bill_locked = ? WHERE id = ?')
                ->execute([$newState, $id]);

            return $newState;
        } catch (\PDOException $e) {
            $this->logError("Failed to toggle bill lock {$id}", $e);
            throw $e;
        }
    }

    public function updateProfilePic(int $id, string $path): bool
    {
        try {
            $stmt = $this->dbConnection->prepare('UPDATE customers SET profile_pic = ? WHERE id = ?');
            return $stmt->execute([$path, $id]);
        } catch (\PDOException $e) {
            $this->logError("Failed to update profile pic {$id}", $e);
            return false;
        }
    }

    public function updateCollectionDates(int $id, ?string $d1, ?string $d2): bool
    {
        try {
            $stmt = $this->dbConnection->prepare(
                'UPDATE customers
                    SET next_collection_date = ?, next_collection_date_2 = ?
                  WHERE id = ?'
            );
            return $stmt->execute([$d1, $d2, $id]);
        } catch (\PDOException $e) {
            $this->logError("Failed to update collection dates {$id}", $e);
            return false;
        }
    }

    /**
     * Verify the admin password. Legacy MD5 hashes are still accepted but are
     * transparently upgraded to password_hash() on first successful use.
     */
    public function verifyAdminPassword(string $username, string $password): bool
    {
        try {
            $stmt = $this->dbConnection->prepare(
                "SELECT id, password FROM users WHERE username = ? AND role = 'admin' LIMIT 1"
            );
            $stmt->execute([$username]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }

            $storedHash = (string)$row['password'];

            if (password_verify($password, $storedHash)) {
                if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                    $this->upgradePasswordHash((int)$row['id'], $password);
                }
                return true;
            }

            // Legacy MD5 support with automatic upgrade.
            if (hash_equals($storedHash, md5($password))) {
                $this->upgradePasswordHash((int)$row['id'], $password);
                return true;
            }

            return false;
        } catch (\PDOException $e) {
            $this->logError('Failed to verify admin password', $e);
            return false;
        }
    }

    private function upgradePasswordHash(int $userId, string $password): void
    {
        try {
            $this->dbConnection
                ->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        } catch (\PDOException $e) {
            $this->logError("Password hash upgrade failed for user {$userId}", $e);
        }
    }

    /**
     * Delete a customer together with all transactions and stored images.
     * Files are removed only AFTER the DB transaction commits, so a rollback
     * never leaves the ledger pointing at deleted files.
     */
    public function deleteCustomerComplete(int $id): bool
    {
        $filesToRemove = [];

        try {
            $this->dbConnection->beginTransaction();

            $picStmt = $this->dbConnection->prepare('SELECT profile_pic FROM customers WHERE id = ?');
            $picStmt->execute([$id]);
            $custPic = (string)$picStmt->fetchColumn();
            if ($custPic !== '') {
                $filesToRemove[] = $custPic;
            }

            $trStmt = $this->dbConnection->prepare(
                'SELECT image_path FROM customer_transactions WHERE customer_id = ?'
            );
            $trStmt->execute([$id]);
            foreach ($trStmt->fetchAll(\PDO::FETCH_ASSOC) as $tr) {
                if (!empty($tr['image_path'])) {
                    $filesToRemove[] = $tr['image_path'];
                }
            }

            $this->dbConnection
                ->prepare('DELETE FROM customer_transactions WHERE customer_id = ?')
                ->execute([$id]);
            $this->dbConnection
                ->prepare('DELETE FROM customers WHERE id = ?')
                ->execute([$id]);

            $this->dbConnection->commit();
        } catch (\Throwable $e) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            $this->logError("Cascading delete failed for customer {$id}", $e);
            return false;
        }

        foreach ($filesToRemove as $file) {
            $this->imageUploader->deleteStoredImage($file);
        }

        return true;
    }

    // =============================================
    // Transactions
    // =============================================
    public function getCustomerTransactions(int $customerId): array
    {
        try {
            $stmt = $this->dbConnection->prepare(
                'SELECT * FROM customer_transactions
                  WHERE customer_id = ?
                  ORDER BY tr_date DESC, id DESC'
            );
            $stmt->execute([$customerId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            $this->logError("Fetch transactions failed for {$customerId}", $e);
            return [];
        }
    }

    /** @return int নতুন ID, ব্যর্থ হলে 0 */
    public function addTransaction(array $data): int
    {
        try {
            $stmt = $this->dbConnection->prepare(
                'INSERT INTO customer_transactions
                    (customer_id, tr_date, description, bill_amount,
                     received_amount, entry_by, image_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $ok = $stmt->execute([
                $data['customer_id'], $data['tr_date'], $data['description'],
                $data['bill_amount'], $data['received_amount'],
                $data['entry_by'], $data['image_path'],
            ]);
            return $ok ? (int)$this->dbConnection->lastInsertId() : 0;
        } catch (\PDOException $e) {
            $this->logError('Add transaction failed', $e);
            return 0;
        }
    }

    public function getTransactionById(int $trId): ?array
    {
        try {
            $stmt = $this->dbConnection->prepare('SELECT * FROM customer_transactions WHERE id = ?');
            $stmt->execute([$trId]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $data ?: null;
        } catch (\PDOException $e) {
            $this->logError("Fetch transaction failed: {$trId}", $e);
            return null;
        }
    }

    public function updateTransaction(int $trId, int $custId, array $data): bool
    {
        try {
            $stmt = $this->dbConnection->prepare(
                'UPDATE customer_transactions
                    SET bill_amount = ?, received_amount = ?, description = ?
                  WHERE id = ? AND customer_id = ?'
            );
            return $stmt->execute([
                $data['bill_amount'], $data['received_amount'],
                $data['description'], $trId, $custId,
            ]);
        } catch (\PDOException $e) {
            $this->logError("Update transaction failed: {$trId}", $e);
            return false;
        }
    }

    /**
     * Delete one transaction (ownership enforced via customer_id).
     * Returns false when the row does not exist or does not belong to
     * the given customer. Image removal happens after commit.
     */
    public function deleteTransactionComplete(int $trId, int $custId): bool
    {
        $imageToRemove = null;

        try {
            $trRow = $this->getTransactionById($trId);
            if (!$trRow || (int)$trRow['customer_id'] !== $custId) {
                return false;
            }
            $imageToRemove = $trRow['image_path'] ?? null;

            $this->dbConnection->beginTransaction();
            $stmt = $this->dbConnection->prepare(
                'DELETE FROM customer_transactions WHERE id = ? AND customer_id = ?'
            );
            $stmt->execute([$trId, $custId]);
            $deleted = $stmt->rowCount() > 0;
            $this->dbConnection->commit();

            if (!$deleted) {
                return false;
            }
        } catch (\Throwable $e) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            $this->logError("Delete transaction failed: {$trId}", $e);
            return false;
        }

        $this->imageUploader->deleteStoredImage($imageToRemove);
        return true;
    }
}
