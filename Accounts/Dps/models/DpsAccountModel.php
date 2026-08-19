<?php
/**
 * models/DpsAccountModel.php
 * ─────────────────────────────────────────
 * sys_dps_accounts টেবিলের সব কোয়েরি এখানে। Controller কখনো সরাসরি SQL লিখবে না।
 */
declare(strict_types=1);

final class DpsAccountModel
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM sys_dps_accounts WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function listByStatus(string $status): array
    {
        $sql = "SELECT a.*,
                    DATEDIFF(a.next_deposit_date, CURDATE()) AS days_until_due,
                    COALESCE((SELECT SUM(l.deposit_amount) FROM sys_dps_ledger l WHERE l.dps_id = a.id AND l.description NOT LIKE '%মুনাফা%'), 0)
                      - COALESCE((SELECT SUM(l2.withdraw_amount) FROM sys_dps_ledger l2 WHERE l2.dps_id = a.id), 0) AS principal_only
                FROM sys_dps_accounts a
                WHERE a.status = ?
                ORDER BY
                    CASE WHEN a.next_deposit_date IS NOT NULL AND DATEDIFF(a.next_deposit_date, CURDATE()) BETWEEN 0 AND 2 THEN 0 ELSE 1 END ASC,
                    DATEDIFF(a.next_deposit_date, CURDATE()) ASC,
                    a.id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$status]);
        return $st->fetchAll();
    }

    /** যেসব একাউন্টের কিস্তি আজ/আগামী ২ দিনের মধ্যে বকেয়া — টোস্ট নোটিফিকেশনের জন্য */
    public function dueSoonList(int $withinDays = 2): array
    {
        $sql = "SELECT id, client_name, account_number, next_deposit_date,
                       DATEDIFF(next_deposit_date, CURDATE()) AS days_until_due
                FROM sys_dps_accounts
                WHERE status = 'active'
                  AND next_deposit_date IS NOT NULL
                  AND DATEDIFF(next_deposit_date, CURDATE()) BETWEEN -365 AND ?
                ORDER BY next_deposit_date ASC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$withinDays]);
        return $st->fetchAll();
    }

    public function dropdownActive(): array
    {
        $st = $this->pdo->query("SELECT id, account_number, client_name, total_balance, photo_path FROM sys_dps_accounts WHERE status = 'active' ORDER BY client_name ASC");
        return $st->fetchAll();
    }

    public function accountNumberExists(string $accNo, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM sys_dps_accounts WHERE account_number = ? AND id != ?');
            $st->execute([$accNo, $excludeId]);
        } else {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM sys_dps_accounts WHERE account_number = ?');
            $st->execute([$accNo]);
        }
        return (int)$st->fetchColumn() > 0;
    }

    public function create(array $d): int
    {
        // maturity_date আগে কখনোই সেট হতো না (সবসময় NULL থাকত), যদিও টেবিলে কলামটি আছে।
        // এখন খোলার তারিখ + মোট মেয়াদ (মাস) থেকে হিসেব করে বসানো হয়।
        $maturityDate = date('Y-m-d', strtotime($d['opening_date'] . ' +' . (int)$d['duration_months'] . ' months'));

        $sql = 'INSERT INTO sys_dps_accounts
                    (account_number, client_name, account_type, frequency, installment_amount,
                     interest_rate, duration_months, maturity_amount, total_balance,
                     total_profit_earned, status, opening_date, maturity_date, next_deposit_date, photo_path, created_at)
                VALUES (?,?,?,?,?,?,?,0.00,0.00,?,\'active\',?,?,?,?,NOW())';
        $this->pdo->prepare($sql)->execute([
            $d['account_number'], $d['client_name'], $d['account_type'], $d['frequency'],
            $d['installment_amount'], $d['interest_rate'], $d['duration_months'],
            $d['past_profit'], $d['opening_date'], $maturityDate, $d['next_deposit_date'], $d['photo_path'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateInfo(int $id, array $d): void
    {
        // duration_months null হলে সেই কলামটি UPDATE-এ যোগই করা হয় না — ডাটাবেসের
        // বিদ্যমান মেয়াদ অক্ষত থাকে (নীরবে ওভাররাইট হয়ে যায় না)।
        $sql = 'UPDATE sys_dps_accounts
                SET client_name = ?, account_number = ?, account_type = ?, frequency = ?,
                    installment_amount = ?, interest_rate = ?';
        $params = [
            $d['client_name'], $d['account_number'], $d['account_type'], $d['frequency'],
            $d['installment_amount'], $d['interest_rate'],
        ];

        if (($d['duration_months'] ?? null) !== null) {
            $sql .= ', duration_months = ?';
            $params[] = $d['duration_months'];
        }

        $sql .= ' WHERE id = ?';
        $params[] = $id;

        $this->pdo->prepare($sql)->execute($params);
    }

    public function updatePhoto(int $id, ?string $filename): void
    {
        $this->pdo->prepare('UPDATE sys_dps_accounts SET photo_path = ? WHERE id = ?')->execute([$filename, $id]);
    }

    public function updateNextDepositDate(int $id, string $date): void
    {
        $this->pdo->prepare('UPDATE sys_dps_accounts SET next_deposit_date = ? WHERE id = ?')->execute([$date, $id]);
    }

    public function updateBalanceAndStatus(int $id, float $balance, string $status): void
    {
        $this->pdo->prepare('UPDATE sys_dps_accounts SET total_balance = ?, status = ? WHERE id = ?')
            ->execute([$balance, $status, $id]);
    }

    public function updateBalanceAndProfit(int $id, float $balance, float $profit): void
    {
        $this->pdo->prepare('UPDATE sys_dps_accounts SET total_balance = ?, total_profit_earned = ? WHERE id = ?')
            ->execute([$balance, $profit, $id]);
    }

    public function toggleStatusForUpdate(int $id): array
    {
        $st = $this->pdo->prepare('SELECT status FROM sys_dps_accounts WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            throw new DpsUserException('অ্যাকাউন্ট পাওয়া যায়নি।');
        }
        $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
        $this->pdo->prepare('UPDATE sys_dps_accounts SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        return ['new_status' => $newStatus];
    }

    public function lockForUpdate(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT total_balance, status, frequency, next_deposit_date FROM sys_dps_accounts WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function allActiveForCron(): array
    {
        return $this->pdo->query("SELECT * FROM sys_dps_accounts WHERE status = 'active' FOR UPDATE")->fetchAll();
    }
}
