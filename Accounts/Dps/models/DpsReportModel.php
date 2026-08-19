<?php
/**
 * models/DpsReportModel.php
 * ─────────────────────────────────────────
 * মাসভিত্তিক ও সপ্তাহভিত্তিক জমা/উত্তোলন/মুনাফা রিপোর্ট — প্রতিটা সারিতে কোন
 * অ্যাকাউন্টের ডেটা তা স্পষ্ট করার জন্য প্রতি-অ্যাকাউন্ট ব্রেকডাউন সহ (per-period, per-account)।
 */
declare(strict_types=1);

final class DpsReportModel
{
    public function __construct(private PDO $pdo) {}

    /**
     * মাসভিত্তিক রিপোর্ট — 'all' হলে প্রতিটা একাউন্ট আলাদা সারিতে দেখাবে,
     * নির্দিষ্ট একাউন্ট হলে শুধু সেটার সারি। পেজিনেটেড (ledger-এর মতোই page-based)।
     * @param int|string $dpsId  'all' বা একাউন্ট আইডি
     */
    public function monthly(int|string $dpsId, int $monthsBack = 12, int $page = 1, int $limit = 15): array
    {
        $hasFilter = ($dpsId !== 'all');
        $where     = $hasFilter ? 'AND l.dps_id = :dpsId' : '';
        $bind      = [':from' => date('Y-m-d', strtotime("-{$monthsBack} months"))];
        if ($hasFilter) {
            $bind[':dpsId'] = $dpsId;
        }

        $grouped = "SELECT DATE_FORMAT(l.txn_date, '%Y-%m') AS period,
                       l.dps_id, a.client_name, a.account_number,
                       COALESCE(SUM(CASE WHEN l.description NOT LIKE '%মুনাফা%' AND l.description NOT LIKE '%Opening%' THEN l.deposit_amount ELSE 0 END), 0) AS deposit_total,
                       COALESCE(SUM(CASE WHEN l.description LIKE '%মুনাফা%' THEN l.deposit_amount ELSE 0 END), 0) AS profit_total,
                       COALESCE(SUM(l.withdraw_amount), 0) AS withdraw_total,
                       COUNT(*) AS entry_count
                FROM sys_dps_ledger l
                JOIN sys_dps_accounts a ON l.dps_id = a.id
                WHERE l.txn_date >= :from $where
                GROUP BY period, l.dps_id";

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ($grouped) t");
        $cntStmt->execute($bind);
        $total = (int)$cntStmt->fetchColumn();

        $pages  = max(1, (int)ceil($total / $limit));
        $page   = min(max(1, $page), $pages);
        $offset = ($page - 1) * $limit;
        $limit  = (int)$limit;   // LIMIT/OFFSET সবসময় int cast — SQL injection ঝুঁকি নেই
        $offset = (int)$offset;

        $sql = "SELECT * FROM ($grouped) t ORDER BY period DESC, client_name ASC LIMIT $limit OFFSET $offset";
        $st = $this->pdo->prepare($sql);
        $st->execute($bind);

        return ['rows' => $st->fetchAll(), 'totalPages' => $pages, 'currentPage' => $page];
    }

    /**
     * সাপ্তাহিক রিপোর্ট (ISO সপ্তাহ ভিত্তিক) — একই নীতিতে প্রতি-অ্যাকাউন্ট ব্রেকডাউন সহ, পেজিনেটেড।
     */
    public function weekly(int|string $dpsId, int $weeksBack = 12, int $page = 1, int $limit = 15): array
    {
        $hasFilter = ($dpsId !== 'all');
        $where     = $hasFilter ? 'AND l.dps_id = :dpsId' : '';
        $bind      = [':from' => date('Y-m-d', strtotime("-{$weeksBack} weeks"))];
        if ($hasFilter) {
            $bind[':dpsId'] = $dpsId;
        }

        $grouped = "SELECT YEARWEEK(l.txn_date, 3) AS yw,
                       MIN(l.txn_date) AS week_start,
                       MAX(l.txn_date) AS week_end,
                       l.dps_id, a.client_name, a.account_number,
                       COALESCE(SUM(CASE WHEN l.description NOT LIKE '%মুনাফা%' AND l.description NOT LIKE '%Opening%' THEN l.deposit_amount ELSE 0 END), 0) AS deposit_total,
                       COALESCE(SUM(CASE WHEN l.description LIKE '%মুনাফা%' THEN l.deposit_amount ELSE 0 END), 0) AS profit_total,
                       COALESCE(SUM(l.withdraw_amount), 0) AS withdraw_total,
                       COUNT(*) AS entry_count
                FROM sys_dps_ledger l
                JOIN sys_dps_accounts a ON l.dps_id = a.id
                WHERE l.txn_date >= :from $where
                GROUP BY yw, l.dps_id";

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ($grouped) t");
        $cntStmt->execute($bind);
        $total = (int)$cntStmt->fetchColumn();

        $pages  = max(1, (int)ceil($total / $limit));
        $page   = min(max(1, $page), $pages);
        $offset = ($page - 1) * $limit;
        $limit  = (int)$limit;
        $offset = (int)$offset;

        $sql = "SELECT * FROM ($grouped) t ORDER BY yw DESC, client_name ASC LIMIT $limit OFFSET $offset";
        $st = $this->pdo->prepare($sql);
        $st->execute($bind);

        return ['rows' => $st->fetchAll(), 'totalPages' => $pages, 'currentPage' => $page];
    }
}
