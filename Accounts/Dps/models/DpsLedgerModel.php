<?php
/**
 * models/DpsLedgerModel.php
 * ─────────────────────────────────────────
 * sys_dps_ledger টেবিলের সব কোয়েরি + ব্যালেন্স রিক্যালকুলেট ইঞ্জিন।
 */
declare(strict_types=1);

final class DpsLedgerModel
{
    public function __construct(private PDO $pdo) {}

    /** মূল ফাইন্যান্সিয়াল ইঞ্জিন — ledger থেকে ব্যালেন্স রিবিল্ড করে, running balance আপডেট করে */
    public function recalculate(int $dpsId): void
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(deposit_amount),0) - COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE dps_id = ?');
        $stmt->execute([$dpsId]);
        $trueBalance = round((float)$stmt->fetchColumn(), 2);

        if ($trueBalance < 0) {
            throw new DpsUserException('ব্যালেন্স শূন্যের নিচে যেতে পারে না!');
        }

        $stmtStatus = $this->pdo->prepare('SELECT status FROM sys_dps_accounts WHERE id = ?');
        $stmtStatus->execute([$dpsId]);
        $currentStatus = (string)$stmtStatus->fetchColumn();

        $newStatus = $currentStatus;
        if ($trueBalance <= 0.01) {
            $newStatus = 'inactive';
        } elseif ($trueBalance > 0.01 && $currentStatus === 'inactive') {
            $newStatus = 'active';
        }

        (new DpsAccountModel($this->pdo))->updateBalanceAndStatus($dpsId, $trueBalance, $newStatus);

        $ledgers = $this->pdo->prepare('SELECT id, deposit_amount, withdraw_amount FROM sys_dps_ledger WHERE dps_id = ? ORDER BY txn_date ASC, id ASC');
        $ledgers->execute([$dpsId]);
        $runBal  = 0.0;
        $stmtUpd = $this->pdo->prepare('UPDATE sys_dps_ledger SET current_balance = ? WHERE id = ?');
        foreach ($ledgers->fetchAll() as $l) {
            $runBal += (float)$l['deposit_amount'] - (float)$l['withdraw_amount'];
            $stmtUpd->execute([round($runBal, 2), $l['id']]);
        }
    }

    public function insertEntry(int $dpsId, string $txnDate, string $desc, float $deposit, float $withdraw): void
    {
        $this->pdo->prepare(
            'INSERT INTO sys_dps_ledger (dps_id, txn_date, description, deposit_amount, withdraw_amount, current_balance, created_at)
             VALUES (?,?,?,?,?,0.00,NOW())'
        )->execute([$dpsId, $txnDate, $desc, $deposit, $withdraw]);
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT dps_id, deposit_amount, withdraw_amount, description FROM sys_dps_ledger WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function updateEntry(int $id, float $amount, string $desc, bool $isWithdraw): void
    {
        $col = $isWithdraw ? 'withdraw_amount' : 'deposit_amount';
        $this->pdo->prepare("UPDATE sys_dps_ledger SET {$col} = ?, description = ? WHERE id = ?")
            ->execute([$amount, $desc, $id]);
    }

    public function deleteEntry(int $id): void
    {
        $this->pdo->prepare('DELETE FROM sys_dps_ledger WHERE id = ?')->execute([$id]);
    }

    public function accountLedgerWindow(int $dpsId, int $page): array
    {
        $rangeStmt = $this->pdo->prepare('SELECT MAX(txn_date) AS max_d, MIN(txn_date) AS min_d FROM sys_dps_ledger WHERE dps_id = ?');
        $rangeStmt->execute([$dpsId]);
        $range = $rangeStmt->fetch();

        if (empty($range['max_d'])) {
            return ['ledger' => [], 'totalPages' => 1, 'currentPage' => 1, 'window_label' => ''];
        }

        $maxDate = $range['max_d'];
        $minDate = $range['min_d'];

        $spanDays   = (int)floor((strtotime($maxDate) - strtotime($minDate)) / 86400);
        $totalPages = max(1, (int)ceil(($spanDays + 1) / 7));
        $page       = min(max(1, $page), $totalPages);

        $windowEnd   = date('Y-m-d', strtotime("$maxDate -" . (($page - 1) * 7) . " days"));
        $windowStart = date('Y-m-d', strtotime("$windowEnd -6 days"));

        $ledStmt = $this->pdo->prepare('SELECT * FROM sys_dps_ledger WHERE dps_id = ? AND txn_date BETWEEN ? AND ? ORDER BY txn_date DESC, id DESC');
        $ledStmt->execute([$dpsId, $windowStart, $windowEnd]);

        $fmtBn = fn($d) => date('d M y', strtotime($d));

        return [
            'ledger'       => $ledStmt->fetchAll(),
            'totalPages'   => $totalPages,
            'currentPage'  => $page,
            'window_label' => $fmtBn($windowStart) . ' — ' . $fmtBn($windowEnd),
        ];
    }

    public function globalLedgerPaginated(int|string $dpsId, int $page, int $limit = 20): array
    {
        $hasFilter = ($dpsId !== 'all');
        $where     = $hasFilter ? 'WHERE l.dps_id = :dpsId' : '';
        $bind      = $hasFilter ? [':dpsId' => $dpsId] : [];

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM sys_dps_ledger l $where");
        $cntStmt->execute($bind);
        $total = (int)$cntStmt->fetchColumn();

        $pages  = max(1, (int)ceil($total / $limit));
        $page   = min(max(1, $page), $pages);
        $offset = ($page - 1) * $limit;

        // $limit ও $offset সর্বদা (int) cast করা — নিরাপদ, SQL Injection ঝুঁকি নেই
        $limit  = (int)$limit;
        $offset = (int)$offset;

        $sql = "SELECT l.*, a.client_name, a.account_number, a.account_type, a.photo_path, a.id AS acc_id
                FROM sys_dps_ledger l
                JOIN sys_dps_accounts a ON l.dps_id = a.id
                $where
                ORDER BY l.txn_date DESC, l.id DESC
                LIMIT $limit OFFSET $offset";
        $rowStmt = $this->pdo->prepare($sql);
        $rowStmt->execute($bind);

        return ['rows' => $rowStmt->fetchAll(), 'totalPages' => $pages, 'currentPage' => $page];
    }

    public function duplicateProfitToday(int $dpsId, string $today): bool
    {
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM sys_dps_ledger WHERE dps_id = ? AND txn_date = ? AND description LIKE 'মুনাফা%'");
        $st->execute([$dpsId, $today]);
        return (int)$st->fetchColumn() > 0;
    }
}
