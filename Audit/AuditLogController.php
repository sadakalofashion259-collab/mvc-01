<?php
declare(strict_types=1);

/**
 * public_html/Audit/AuditLogController.php
 *
 * GET /Audit/              → লগ লিস্ট HTML
 * GET /Audit/?detail=123   → JSON (AJAX modal)
 * GET /Audit/?export=csv   → CSV ডাউনলোড
 */
class AuditLogController
{
    private AuditLogModel $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new AuditLogModel($pdo);
    }

    public function handle(): void
    {
        // AJAX — single row detail
        if (isset($_GET['detail']) && ctype_digit((string) $_GET['detail'])) {
            $this->jsonDetail((int) $_GET['detail']);
            return;
        }

        // CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            AuditLogger::export(
                'audit_log', null, null, null,
                'Audit log CSV exported by ' . ($_SESSION['username'] ?? '?')
            );
            $this->exportCsv();
            return;
        }

        // সাধারণ HTML পেজ
        $filters      = $this->sanitise($_GET);
        $result       = $this->model->getAll($filters);
        $modules      = $this->model->getModules();
        $actionCounts = $this->model->getActionCounts();

        require __DIR__ . '/views/audit_log_view.php';
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function jsonDetail(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $row = $this->model->getById($id);
        if ($row === false) {
            http_response_code(404);
            echo json_encode(['error' => 'পাওয়া যায়নি']);
            return;
        }

        // JSON কলামগুলো pretty-print
        foreach (['old_data', 'new_data'] as $col) {
            if ($row[$col] !== null) {
                $decoded = json_decode((string) $row[$col], true);
                $row[$col] = $decoded !== null
                    ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : $row[$col];
            }
        }

        echo json_encode($row, JSON_UNESCAPED_UNICODE);
    }

    private function exportCsv(): void
    {
        $f             = $this->sanitise($_GET);
        $f['per_page'] = 99_999;
        $rows          = $this->model->getAll($f)['data'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM — Excel UTF-8

        fputcsv($out, ['ID', 'তারিখ/সময়', 'ইউজার', 'অ্যাকশন', 'মডিউল', 'Record ID', 'বিবরণ', 'IP']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['created_at'],
                $r['username']    ?? '—',
                $r['action'],
                $r['module'],
                $r['record_id']   ?? '—',
                $r['description'] ?? '—',
                $r['ip_address']  ?? '—',
            ]);
        }

        fclose($out);
        exit;
    }

    private function sanitise(array $in): array
    {
        $validActions = ['', 'CREATE', 'UPDATE', 'DELETE', 'EXPORT'];

        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['date_from'] ?? '')
                        ? $in['date_from'] : '';
        $dateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['date_to'] ?? '')
                        ? $in['date_to'] : '';

        // ডিফল্ট: কোনো তারিখ বা অন্য ফিল্টার না দিলে শুধু আজকের ডেটা দেখাবে
        // (লিস্ট লম্বা হয়ে পেজ স্লো/ক্র্যাশ হওয়া রোধ করতে — প্রতিদিনের ডেটা আলাদা)
        $hasOtherFilter = !empty($in['search']) || !empty($in['action']) || !empty($in['module']);
        if ($dateFrom === '' && $dateTo === '' && !$hasOtherFilter) {
            $today    = date('Y-m-d');
            $dateFrom = $today;
            $dateTo   = $today;
        }

        return [
            'action'    => in_array(strtoupper($in['action'] ?? ''), $validActions, true)
                                ? strtoupper($in['action'] ?? '') : '',
            'module'    => preg_replace('/[^a-z0-9_]/', '', strtolower($in['module'] ?? '')),
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'search'    => htmlspecialchars(substr($in['search'] ?? '', 0, 100), ENT_QUOTES, 'UTF-8'),
            'page'      => isset($in['page']) && ctype_digit((string) $in['page'])
                                ? (int) $in['page'] : 1,
            'per_page'  => 25,
        ];
    }
}
