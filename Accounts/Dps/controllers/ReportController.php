<?php
/**
 * controllers/ReportController.php
 * ─────────────────────────────────────────
 * মাসভিত্তিক ও সাপ্তাহিক জমা রিপোর্ট এন্ডপয়েন্ট — প্রতিটা সারিতে কোন অ্যাকাউন্ট তা এস্কেপ করে পাঠায়।
 */
declare(strict_types=1);

final class ReportController
{
    private DpsReportModel $reports;

    public function __construct(private PDO $pdo)
    {
        $this->reports = new DpsReportModel($pdo);
    }

    public function monthly(): void
    {
        $dpsId = isset($_POST['dps_id']) && $_POST['dps_id'] !== 'all' ? (int)$_POST['dps_id'] : 'all';
        $page  = max(1, (int)($_POST['page'] ?? 1));
        $res   = $this->reports->monthly($dpsId, 12, $page);

        foreach ($res['rows'] as &$r) {
            $r['label']          = date('F Y', strtotime($r['period'] . '-01'));
            $r['client_name']    = SecurityHelper::safeOut($r['client_name']);
            $r['account_number'] = SecurityHelper::safeOut($r['account_number'] ?? '');
        }
        unset($r);

        SecurityHelper::jsonSuccess(['report' => $res['rows'], 'totalPages' => $res['totalPages'], 'currentPage' => $res['currentPage']]);
    }

    public function weekly(): void
    {
        $dpsId = isset($_POST['dps_id']) && $_POST['dps_id'] !== 'all' ? (int)$_POST['dps_id'] : 'all';
        $page  = max(1, (int)($_POST['page'] ?? 1));
        $res   = $this->reports->weekly($dpsId, 12, $page);

        foreach ($res['rows'] as &$r) {
            $r['label']          = date('d M', strtotime($r['week_start'])) . ' – ' . date('d M', strtotime($r['week_end']));
            $r['client_name']    = SecurityHelper::safeOut($r['client_name']);
            $r['account_number'] = SecurityHelper::safeOut($r['account_number'] ?? '');
        }
        unset($r);

        SecurityHelper::jsonSuccess(['report' => $res['rows'], 'totalPages' => $res['totalPages'], 'currentPage' => $res['currentPage']]);
    }
}
