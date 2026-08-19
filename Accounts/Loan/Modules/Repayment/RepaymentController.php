<?php

declare(strict_types=1);

require_once APP_ROOT . '/Helpers/ImageUpload.php';

/**
 * RepaymentController — কিস্তি শোধের বোর্ড।
 * GET  /repayment  → বোর্ড (বকেয়া / আজকের / আগামী)
 * POST /repayment  → JSON API
 */
final class RepaymentController extends BaseController
{
    protected array $publicActions = ['index'];

    private RepaymentModel $repay;
    private LoanModel $loans;

    public function __construct()
    {
        parent::__construct();
        require_once APP_ROOT . '/Modules/Loan/LoanModel.php';
        $this->repay = new RepaymentModel($this->db);
        $this->loans = new LoanModel($this->db);
    }

    public function index(): void
    {
        $this->render('Repayment/Views/index', ['pageTitle' => 'কিস্তি শোধ']);
    }

    protected function handleApi(string $action, array $postData): void
    {
        match ($action) {
            'fetch_board'    => $this->apiBoard($postData),
            'quick_pay'      => $this->apiQuickPay($postData),
            'send_reminders' => $this->apiSendReminders($postData),
            default          => $this->fail('অজানা অনুরোধ।'),
        };
    }

    private function apiBoard(array $postData): void
    {
        $date = Request::text($postData, 'date', date('Y-m-d'));
        if (!Security::isValidDate($date)) { $date = date('Y-m-d'); }

        $decorate = static function (array $rows): array {
            return array_map(static function (array $r): array {
                $r['due_badge'] = DueDate::badge($r['due_date'] ?? null, 'active');
                return $r;
            }, $rows);
        };

        $this->success([
            'overdue'  => $decorate($this->repay->overdue()),
            'dueToday' => $decorate($this->repay->dueToday()),
            'upcoming' => $decorate($this->repay->upcoming(7)),
            'paid'     => $this->repay->paidOn($date),
        ]);
    }

    private function apiQuickPay(array $postData): void
    {
        $loanId = Request::int($postData, 'loan_id');
        $amount = Request::float($postData, 'amount');
        $date   = Request::text($postData, 'txn_date', date('Y-m-d'));
        $note   = mb_substr(Request::text($postData, 'note'), 0, 500);

        if ($loanId <= 0 || $amount <= 0) { $this->fail('তথ্য সঠিক নয়।'); return; }
        if (!Security::isValidDate($date)) { $date = date('Y-m-d'); }

        $photoError = null;
        $photo = ImageUpload::handle($_FILES['photo'] ?? null, $photoError);
        if ($photoError !== null) { $this->fail($photoError); return; }

        $ok = $this->loans->recordPayment($loanId, $amount, $date, 'কিস্তি পরিশোধ', $photo, $note !== '' ? $note : null);
        if (!$ok) {
            if ($photo !== null) { @unlink(APP_ROOT . '/Uploads/' . $photo); }
            $this->fail('শোধের পরিমাণ বাকি টাকার চেয়ে বেশি হতে পারবে না।');
            return;
        }
        $this->success([], 'কিস্তি শোধ রেকর্ড হয়েছে।');
    }

    /**
     * বকেয়া + আজকের + আগামী N দিনের কিস্তিতে SMS রিমাইন্ডার পাঠায়।
     * scope: overdue | today | upcoming | all
     */
    private function apiSendReminders(array $postData): void
    {
        $scope = Request::text($postData, 'scope', 'all');
        $days  = Request::int($postData, 'days', 3);

        require_once APP_ROOT . '/Helpers/SmsService.php';

        $list = [];
        if ($scope === 'overdue' || $scope === 'all') {
            $list = array_merge($list, $this->repay->overdue());
        }
        if ($scope === 'today' || $scope === 'all') {
            $list = array_merge($list, $this->repay->dueToday());
        }
        if ($scope === 'upcoming' || $scope === 'all') {
            $list = array_merge($list, $this->repay->upcoming(max(1, min($days, 7))));
        }

        $sent = 0;
        $fail = 0;
        $skip = 0;
        foreach ($list as $loan) {
            if (empty($loan['mobile'])) {
                $skip++;
                continue;
            }
            $r = SmsService::sendDueReminder($loan);
            if ($r['success']) {
                $sent++;
            } else {
                $fail++;
            }
        }

        $this->success(
            ['sent' => $sent, 'failed' => $fail, 'skipped' => $skip],
            "রিমাইন্ডার: {$sent} পাঠানো, {$fail} ব্যর্থ, {$skip} মোবাইল নেই।"
        );
    }
}
