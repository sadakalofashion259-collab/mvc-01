<?php

declare(strict_types=1);

require_once APP_ROOT . '/Helpers/ImageUpload.php';

/**
 * LoanController — আপনি যেসব লোন নিয়েছেন তার নিয়ন্ত্রণ।
 *
 * GET  /loan/dashboard   → মূল প্যানেল
 * GET  /loan/print/{id}  → প্রিন্ট স্টেটমেন্ট
 * GET  /loan/photo/{f}   → রসিদের ছবি (লগইন করা ব্যবহারকারীর জন্য)
 * POST /loan             → JSON API
 */
final class LoanController extends BaseController
{
    private const DELETE_ROLES = ['admin'];

    protected array $publicActions = ['dashboard', 'print', 'photo'];

    private LoanModel $loans;

    public function __construct()
    {
        parent::__construct();
        $this->loans = new LoanModel($this->db);
    }

    // ---------------------------------------------------------------
    // পেজ
    // ---------------------------------------------------------------

    public function dashboard(): void
    {
        $this->render('Loan/Views/dashboard', [
            'pageTitle'   => 'লোন ম্যানেজমেন্ট',
            'bannerTitle' => 'লোন ম্যানেজমেন্ট',
            'bannerSub'   => 'SADA KALO LOAN MANAGE',
            'canDelete'   => in_array($this->currentRole(), self::DELETE_ROLES, true),
        ]);
    }

    public function print(?string $param = null): void
    {
        $loanId = (int)$param;
        $loan   = $loanId > 0 ? $this->loans->find($loanId) : null;

        if ($loan === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8"><title>404</title></head>'
               . '<body style="font-family:sans-serif;text-align:center;padding:60px;">লোন পাওয়া যায়নি।</body></html>';
            return;
        }

        $this->render('Loan/Views/print', [
            'loan'   => $loan,
            'totals' => $this->loans->totalsFor($loanId),
            'ledger' => $this->loans->fullLedger($loanId),
        ], false);
    }

    /**
     * রসিদের ছবি সার্ভ করে। শুধু লগইন করা ব্যবহারকারী দেখতে পারবে,
     * আর basename() দিয়ে path traversal ঠেকানো হয়।
     */
    public function photo(?string $param = null): void
    {
        $name = basename((string)$param);
        $path = APP_ROOT . '/Uploads/' . $name;

        if ($name === '' || !is_file($path)) {
            http_response_code(404);
            echo 'ছবি পাওয়া যায়নি।';
            return;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            http_response_code(404);
            return;
        }

        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $info['mime']);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
    }

    // ---------------------------------------------------------------
    // JSON API
    // ---------------------------------------------------------------

    protected function handleApi(string $action, array $postData): void
    {
        match ($action) {
            'fetch_summary'      => $this->apiSummary(),
            'fetch_active_loans' => $this->apiActiveLoans(),
            'fetch_profiles'     => $this->apiProfiles($postData),
            'fetch_profile'      => $this->apiProfile($postData),
            'fetch_ledger'       => $this->apiLedger($postData),
            'create_loan'        => $this->apiCreateLoan($postData),
            'add_payment'        => $this->apiAddPayment($postData),
            'add_interest'       => $this->apiAddInterest($postData),
            'update_name'        => $this->apiUpdateName($postData),
            'update_mobile'      => $this->apiUpdateMobile($postData),
            'update_due_date'    => $this->apiUpdateDueDate($postData),
            'update_loan'        => $this->apiUpdateLoan($postData),
            'toggle_status'      => $this->apiToggleStatus($postData),
            'update_ledger'      => $this->apiUpdateLedger($postData),
            'delete_ledger'      => $this->apiDeleteLedger($postData),
            'send_sms_test'      => $this->apiSendSmsTest($postData),
            default              => $this->fail('অজানা অনুরোধ।'),
        };
    }

    private function apiSummary(): void
    {
        $this->success($this->loans->summary());
    }

    private function apiActiveLoans(): void
    {
        $this->success(['loans' => $this->loans->activeLoans()]);
    }

    private function apiProfiles(array $postData): void
    {
        $status = Request::text($postData, 'status', 'active');
        $paged  = $this->loans->profiles($status, Request::int($postData, 'page', 1));
        $profiles = array_map(static function (array $p): array {
            $p['due_badge'] = DueDate::badge($p['due_date'] ?? null, (string)$p['status']);
            return $p;
        }, $paged['rows']);

        $this->success(['profiles' => $profiles, 'meta' => $paged['meta']]);
    }

    private function apiProfile(array $postData): void
    {
        $loanId = Request::int($postData, 'id');
        $loan   = $this->loans->find($loanId);
        if ($loan === null) {
            $this->fail('লোন পাওয়া যায়নি।');
            return;
        }

        $paged = $this->loans->ledgerForLoan($loanId, Request::int($postData, 'page', 1));
        $loan['due_badge'] = DueDate::badge($loan['due_date'] ?? null, (string)$loan['status']);

        // ছবির URL যোগ
        $rows = array_map(function (array $row): array {
            $row['photo_url'] = !empty($row['photo_path'])
                ? APP_BASE_URL . '/loan/photo/' . rawurlencode((string)$row['photo_path'])
                : null;
            return $row;
        }, $paged['rows']);

        $this->success([
            'info'   => $loan,
            'totals' => $this->loans->totalsFor($loanId),
            'ledger' => $rows,
            'meta'   => $paged['meta'],
        ]);
    }

    private function apiLedger(array $postData): void
    {
        $raw = Request::text($postData, 'loan_id', 'all');
        $loanId = ($raw === 'all' || $raw === '') ? null : (int)$raw;

        $paged = $this->loans->ledgerAll($loanId, Request::int($postData, 'page', 1));

        $rows = array_map(function (array $row): array {
            $row['photo_url'] = !empty($row['photo_path'])
                ? APP_BASE_URL . '/loan/photo/' . rawurlencode((string)$row['photo_path'])
                : null;
            return $row;
        }, $paged['rows']);

        $this->success(['ledger' => $rows, 'meta' => $paged['meta']]);
    }

    private function apiCreateLoan(array $postData): void
    {
        $name         = Request::text($postData, 'borrower_name');
        $mobile       = Request::text($postData, 'mobile');
        $principal    = Request::float($postData, 'principal_amount');
        $installments = Request::int($postData, 'total_installments');
        $rate         = Request::float($postData, 'interest_rate');
        $manualEmi    = Request::float($postData, 'installment_amount');
        $frequency    = Request::text($postData, 'frequency', 'monthly');
        $dueDate      = Request::text($postData, 'due_date');
        $openDate     = Request::text($postData, 'open_date', date('Y-m-d'));

        if ($name === '' || mb_strlen($name) > 150) {
            $this->fail('পাওনাদারের নাম সঠিকভাবে দিন।'); return;
        }
        if ($principal <= 0) { $this->fail('মূল টাকার পরিমাণ ০ এর বেশি হতে হবে।'); return; }
        if ($installments <= 0 || $installments > 1000) { $this->fail('কিস্তির সংখ্যা ১–১০০০ এর মধ্যে হতে হবে।'); return; }
        if ($rate < 0 || $rate > 500) { $this->fail('সুদের হার সঠিক নয়।'); return; }
        if ($dueDate !== '' && !Security::isValidDate($dueDate)) { $this->fail('কিস্তির তারিখ সঠিক নয়।'); return; }
        if (!Security::isValidDate($openDate)) { $openDate = date('Y-m-d'); }
        if ($mobile !== '' && !preg_match('/^(\+?88)?01[3-9]\d{8}$/', preg_replace('/[\s\-]/', '', $mobile) ?? '')) {
            $this->fail('মোবাইল নম্বর সঠিক নয় (যেমন: 017XXXXXXXX)।'); return;
        }

        $result = $this->loans->create([
            'borrower_name'      => $name,
            'mobile'             => $mobile !== '' ? $mobile : null,
            'principal_amount'   => $principal,
            'interest_rate'      => $rate,
            'total_installments' => $installments,
            'installment_amount' => $manualEmi,
            'frequency'          => $frequency,
            'due_date'           => $dueDate !== '' ? $dueDate : null,
            'open_date'          => $openDate,
        ]);

        $this->success(['loan' => $result], 'লোন যোগ হয়েছে। অ্যাকাউন্ট: ' . $result['account_number']);
    }

    /**
     * কিস্তি শোধ — ছবি (ঐচ্ছিক) ও কমেন্ট সহ।
     * multipart/form-data হিসেবে আসে, তাই $_FILES ব্যবহার হয়।
     */
    private function apiAddPayment(array $postData): void
    {
        $loanId  = Request::int($postData, 'loan_id');
        $amount  = Request::float($postData, 'amount');
        $txnDate = Request::text($postData, 'txn_date', date('Y-m-d'));
        $note    = mb_substr(Request::text($postData, 'note'), 0, 500);

        if ($loanId <= 0 || $amount <= 0) { $this->fail('শোধের তথ্য সঠিক নয়।'); return; }
        if (!Security::isValidDate($txnDate)) { $txnDate = date('Y-m-d'); }

        // ছবি (ঐচ্ছিক)
        $photoError = null;
        $photo = ImageUpload::handle($_FILES['photo'] ?? null, $photoError);
        if ($photoError !== null) { $this->fail($photoError); return; }

        $desc = 'কিস্তি পরিশোধ';
        $ok = $this->loans->recordPayment($loanId, $amount, $txnDate, $desc, $photo, $note !== '' ? $note : null);

        if (!$ok) {
            // শোধ ব্যর্থ হলে আপলোড করা ছবি মুছে ফেলা
            if ($photo !== null) { @unlink(APP_ROOT . '/Uploads/' . $photo); }
            $this->fail('শোধের পরিমাণ বাকি টাকার চেয়ে বেশি হতে পারবে না।');
            return;
        }

        $this->success([], 'কিস্তি শোধ সফলভাবে রেকর্ড হয়েছে।');
    }

    /** বাড়তি সুদ / লেট ফি যোগ (Debit — দায় বাড়ে)। */
    private function apiAddInterest(array $postData): void
    {
        $loanId  = Request::int($postData, 'loan_id');
        $amount  = Request::float($postData, 'amount');
        $txnDate = Request::text($postData, 'txn_date', date('Y-m-d'));
        $note    = Request::text($postData, 'description', 'সুদ');

        if ($loanId <= 0 || $amount <= 0) { $this->fail('তথ্য সঠিক নয়।'); return; }
        if (!Security::isValidDate($txnDate)) { $txnDate = date('Y-m-d'); }
        if ($this->loans->find($loanId) === null) { $this->fail('লোন পাওয়া যায়নি।'); return; }

        $desc = $note !== '' ? $note : 'সুদ';
        // সুদ চেনার জন্য শব্দ নিশ্চিত করা
        if (!preg_match('/সুদ|মুনাফা|Interest|Profit/u', $desc)) {
            $desc = 'সুদ — ' . $desc;
        }

        $this->loans->addLedgerEntry($loanId, $txnDate, $desc, $amount, 0.0);
        $this->db->beginTransaction();
        $this->loans->recalculate($loanId);
        $this->db->commit();

        $this->success([], 'সুদ যোগ হয়েছে।');
    }

    private function apiUpdateName(array $postData): void
    {
        $loanId = Request::int($postData, 'id');
        $name   = Request::text($postData, 'name');
        if ($loanId <= 0 || $name === '' || mb_strlen($name) > 150) { $this->fail('নাম সঠিকভাবে দিন।'); return; }
        $this->loans->updateLenderName($loanId, $name);
        $this->success([], 'নাম আপডেট হয়েছে।');
    }

    private function apiUpdateMobile(array $postData): void
    {
        $loanId = Request::int($postData, 'id');
        $mobile = Request::text($postData, 'mobile');
        if ($loanId <= 0) { $this->fail('লোন পাওয়া যায়নি।'); return; }
        if ($mobile !== '' && !preg_match('/^(\+?88)?01[3-9]\d{8}$/', preg_replace('/[\s\-]/', '', $mobile) ?? '')) {
            $this->fail('মোবাইল নম্বর সঠিক নয় (যেমন: 017XXXXXXXX)।'); return;
        }
        $this->loans->updateMobile($loanId, $mobile !== '' ? $mobile : null);
        $this->success([], 'মোবাইল নম্বর আপডেট হয়েছে।');
    }

    /** টেস্ট SMS — ক্রেডেনশিয়াল/IP চেক করার জন্য। */
    private function apiSendSmsTest(array $postData): void
    {
        $mobile  = Request::text($postData, 'mobile');
        $message = Request::text($postData, 'message', 'সাদা কালো: টেস্ট SMS সফল।');
        if ($mobile === '') { $this->fail('মোবাইল নম্বর দিন।'); return; }

        require_once APP_ROOT . '/Helpers/SmsService.php';
        $result = SmsService::send($mobile, $message);
        if ($result['success']) {
            $this->success(['response' => $result['response']], 'SMS পাঠানো হয়েছে।');
        } else {
            $this->fail('SMS ব্যর্থ: ' . ($result['error'] ?? 'unknown'));
        }
    }

    private function apiUpdateDueDate(array $postData): void
    {
        $loanId  = Request::int($postData, 'id');
        $dueDate = Request::text($postData, 'due_date');
        if ($loanId <= 0 || !Security::isValidDate($dueDate)) { $this->fail('তারিখ সঠিক নয়।'); return; }
        $this->loans->updateDueDate($loanId, $dueDate);
        $this->success([], 'কিস্তির তারিখ আপডেট হয়েছে।');
    }

    private function apiUpdateLoan(array $postData): void
    {
        $loanId       = Request::int($postData, 'id');
        $principal    = Request::float($postData, 'principal_amount');
        $installments = Request::int($postData, 'total_installments');
        $rate         = Request::float($postData, 'interest_rate');
        $manualEmi    = Request::float($postData, 'installment_amount');
        $frequency    = Request::text($postData, 'frequency', 'monthly');

        if ($loanId <= 0) { $this->fail('লোন পাওয়া যায়নি।'); return; }
        if ($principal <= 0) { $this->fail('মূল টাকার পরিমাণ ০ এর বেশি হতে হবে।'); return; }
        if ($installments <= 0 || $installments > 1000) { $this->fail('কিস্তির সংখ্যা ১–১০০০ এর মধ্যে হতে হবে।'); return; }
        if ($rate < 0 || $rate > 500) { $this->fail('সুদের হার সঠিক নয়।'); return; }

        $ok = $this->loans->updateLoan($loanId, [
            'principal_amount'   => $principal,
            'interest_rate'      => $rate,
            'total_installments' => $installments,
            'installment_amount' => $manualEmi,
            'frequency'          => $frequency,
        ]);
        if (!$ok) { $this->fail('আপডেট করা যায়নি।'); return; }
        $this->success([], 'লোনের তথ্য আপডেট হয়েছে।');
    }

    private function apiToggleStatus(array $postData): void
    {
        $loanId = Request::int($postData, 'id');
        $status = $this->loans->toggleStatus($loanId);
        if ($status === null) { $this->fail('লোন পাওয়া যায়নি।'); return; }
        $this->success(['new_status' => $status], 'স্ট্যাটাস পরিবর্তন হয়েছে।');
    }

    private function apiUpdateLedger(array $postData): void
    {
        $ledgerId    = Request::int($postData, 'id');
        $description = Request::text($postData, 'description');
        $amount      = Request::float($postData, 'amount');
        $txnDate     = Request::text($postData, 'txn_date');
        $note        = mb_substr(Request::text($postData, 'note'), 0, 500);

        if ($ledgerId <= 0 || $description === '' || $amount < 0) { $this->fail('বিবরণ ও পরিমাণ সঠিকভাবে দিন।'); return; }

        // নতুন ছবি (ঐচ্ছিক)
        $photoError = null;
        $newPhoto = ImageUpload::handle($_FILES['photo'] ?? null, $photoError);
        if ($photoError !== null) { $this->fail($photoError); return; }

        $ok = $this->loans->updateLedgerEntry(
            $ledgerId, $description, $amount, $txnDate,
            $note !== '' ? $note : null,
            $newPhoto
        );
        if (!$ok) {
            if ($newPhoto !== null) { @unlink(APP_ROOT . '/Uploads/' . $newPhoto); }
            $this->fail('এন্ট্রি পাওয়া যায়নি।');
            return;
        }
        $this->success([], 'হিসাব আপডেট হয়েছে।');
    }

    private function apiDeleteLedger(array $postData): void
    {
        if (!$this->canDelete()) { $this->fail('এই কাজের অনুমতি নেই।', 403); return; }
        if (!$this->loans->deleteLedgerEntry(Request::int($postData, 'id'))) { $this->fail('এন্ট্রি পাওয়া যায়নি।'); return; }
        $this->success([], 'এন্ট্রি মুছে ফেলা হয়েছে।');
    }

    private function canDelete(): bool
    {
        return in_array($this->currentRole(), self::DELETE_ROLES, true);
    }
}
