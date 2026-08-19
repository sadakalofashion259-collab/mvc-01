<?php

declare(strict_types=1);

/**
 * ReportController — তারিখ অনুযায়ী রিপোর্ট।
 */
final class ReportController extends BaseController
{
    protected array $publicActions = ['index'];

    private ReportModel $reports;

    public function __construct()
    {
        parent::__construct();
        $this->reports = new ReportModel($this->db);
    }

    public function index(): void
    {
        $this->render('Report/Views/index', [
            'pageTitle' => 'রিপোর্ট',
            'fromDate'  => date('Y-m-01'),
            'toDate'    => date('Y-m-d'),
        ]);
    }

    protected function handleApi(string $action, array $postData): void
    {
        match ($action) {
            'fetch_report' => $this->apiReport($postData),
            default        => $this->fail('অজানা অনুরোধ।'),
        };
    }

    private function apiReport(array $postData): void
    {
        $from = Request::text($postData, 'from_date', date('Y-m-01'));
        $to   = Request::text($postData, 'to_date', date('Y-m-d'));
        if (!Security::isValidDate($from)) { $from = date('Y-m-01'); }
        if (!Security::isValidDate($to))   { $to = date('Y-m-d'); }
        if ($from > $to) { [$from, $to] = [$to, $from]; }

        $byLoan = array_map(static function (array $r): array {
            $r['due_badge'] = DueDate::badge($r['due_date'] ?? null, (string)$r['status']);
            return $r;
        }, $this->reports->byLoan($from, $to));

        $this->success([
            'range'    => ['from' => $from, 'to' => $to],
            'overview' => $this->reports->overview($from, $to),
            'daily'    => $this->reports->daily($from, $to),
            'byLoan'   => $byLoan,
        ]);
    }
}
