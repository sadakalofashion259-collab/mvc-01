<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Models/CustomerModel.php';
require_once dirname(__DIR__) . '/Models/SettingsModel.php';
require_once dirname(__DIR__) . '/Services/SmsService.php';
require_once dirname(__DIR__) . '/Services/MailService.php';
require_once dirname(__DIR__) . '/Helpers/ImageUploader.php';

/**
 * CustomerController — Request handling for the customer list & profile pages.
 *
 * Security model:
 *   - Every state-changing POST validates the CSRF token (hash_equals).
 *   - Role gates:  admin  → delete / edit / toggle / dates / bulk actions
 *                  admin, manager → create customers, add transactions, send SMS
 *   - All numeric input is cast + range-checked; dates are format-validated.
 */
class CustomerController
{
    private const ROLE_ADMIN   = 'admin';
    private const ROLE_MANAGER = 'manager';

    /** Maximum recipients accepted by one bulk-SMS batch. */
    private const BULK_SMS_BATCH_LIMIT = 30;

    private CustomerModelInterface $model;
    private SettingsModel $settings;
    private SmsService $smsService;
    private MailService $mailService;
    private ImageUploader $imageUploader;

    public string $userRole;
    public string $username;

    public function __construct(\PDO $dbConnection)
    {
        $this->model         = new CustomerModel($dbConnection);
        $this->settings      = new SettingsModel($dbConnection);
        $this->smsService    = new SmsService();
        $this->mailService   = new MailService();
        $this->imageUploader = new ImageUploader();

        $this->userRole = (string)($_SESSION['role']     ?? 'viewer');
        $this->username = (string)($_SESSION['username'] ?? $this->userRole);
    }

    /**
     * Central SMS gate — every outgoing SMS passes through here so the
     * global on/off switch (app_settings.sms_enabled) applies everywhere:
     * welcome, transaction, single and bulk collection messages alike.
     *
     * @return array{success: bool, error?: string, response?: mixed}
     */
    private function sendSms(string $phone, string $message): array
    {
        if (!$this->settings->isSmsEnabled()) {
            return ['success' => false, 'error' => 'SMS globally disabled'];
        }
        return $this->smsService->send($phone, $message);
    }

    // =============================================
    // Small internal helpers
    // =============================================
    private function jsonResponse(array $payload): never
    {
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function isCsrfValid(string $csrfToken): bool
    {
        return hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''));
    }

    private function requireCsrfJson(string $csrfToken): void
    {
        if (!$this->isCsrfValid($csrfToken)) {
            $this->jsonResponse(['ok' => false, 'msg' => 'সিকিউরিটি টোকেন এরর।']);
        }
    }

    private function isAdmin(): bool
    {
        return $this->userRole === self::ROLE_ADMIN;
    }

    private function isStaff(): bool
    {
        return in_array($this->userRole, [self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }

    /** Validate a Y-m-d date string; returns today's date when invalid. */
    private function sanitizeDate(?string $value): string
    {
        $value = trim((string)$value);
        $dt    = \DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : date('Y-m-d');
    }

    /** Validate an optional Y-m-d date string; returns null when invalid/empty. */
    private function sanitizeOptionalDate(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
    }

    /** Cast to float and clamp negatives to zero (ledger amounts are never negative). */
    private function sanitizeAmount(mixed $value): float
    {
        return max(0.0, (float)$value);
    }

    // =============================================
    // SMS message builders
    // =============================================
    private function buildCollectionSms(array $customer, array $summary): string
    {
        $netDue = number_format(abs((float)$summary['net_due']), 2);

        return "দোকান: {$customer['shop_name']}\n"
             . "বর্তমান বাকি: Tk. {$netDue} অনুগ্রহ পরিশোধ করুন। সাদা কালো ফ্যাশন।";
    }

    private function buildTransactionSms(
        array  $customer,
        array  $summary,
        string $description,
        float  $billAmount,
        float  $receivedAmount
    ): string {
        $netDue = number_format(abs((float)$summary['net_due']), 2);

        $msg  = "দোকান: {$customer['shop_name']}\n";
        $msg .= "মেমো: {$description}\n";
        if ($billAmount > 0) {
            $msg .= 'বিল: Tk. ' . number_format($billAmount, 2) . "\n";
        }
        if ($receivedAmount > 0) {
            $msg .= 'জমা: Tk. ' . number_format($receivedAmount, 2) . "\n";
        }
        $msg .= "সর্বমোট বাকি: Tk. {$netDue}\n";
        $msg .= 'সাদা কালো ফ্যাশন।';

        return $msg;
    }

    private function buildWelcomeSms(array $data): string
    {
        $msg  = "দোকান: {$data['shop_name']}\n";
        $msg .= "আপনার একাউন্ট খোলা হয়েছে।\n";
        if ((float)$data['opening_balance'] > 0) {
            $msg .= 'পুরনো বাকি: Tk. ' . number_format((float)$data['opening_balance'], 2) . "\n";
        }
        $msg .= 'সাদা কালো ফ্যাশন।';
        return $msg;
    }

    // =============================================
    // Customers list page — POST handling
    // =============================================
    public function handleCustomersListRequests(string $csrfToken): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $ajaxAction = (string)($_POST['ajax_action'] ?? '');

        // ===== Global SMS on/off switch (AJAX, admin only) =====
        if ($ajaxAction === 'toggle_sms') {
            $this->requireCsrfJson($csrfToken);
            if (!$this->isAdmin()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'অ্যাডমিন একসেস প্রয়োজন।']);
            }

            $newState = $this->settings->toggleSmsEnabled();
            $this->jsonResponse([
                'ok'    => true,
                'state' => $newState,
                'msg'   => $newState ? 'SMS চালু করা হয়েছে।' : 'SMS বন্ধ করা হয়েছে।',
            ]);
        }

        // ===== Toggle active status (AJAX, admin only) =====
        if ($ajaxAction === 'toggle_active') {
            $this->requireCsrfJson($csrfToken);
            if (!$this->isAdmin()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'অ্যাডমিন একসেস প্রয়োজন।']);
            }

            $cid = (int)($_POST['cid'] ?? 0);
            if ($cid <= 0) {
                $this->jsonResponse(['ok' => false, 'msg' => 'কাস্টমার ID নেই।']);
            }

            try {
                $old = $this->model->getCustomerById($cid);
                $newState = $this->model->toggleActiveStatus($cid);
                if (class_exists('AuditLogger') && $old) {
                    AuditLogger::update(
                        'customers',
                        $cid,
                        ['is_active' => (int)($old['is_active'] ?? 1)],
                        ['is_active' => $newState],
                        "কাস্টমার #{$cid} স্ট্যাটাস টগল"
                    );
                }
                $this->jsonResponse([
                    'ok'    => true,
                    'state' => $newState,
                    'msg'   => $newState ? 'কাস্টমার Active করা হয়েছে।' : 'কাস্টমার Inactive করা হয়েছে।',
                ]);
            } catch (\Throwable) {
                $this->jsonResponse(['ok' => false, 'msg' => 'সার্ভার এরর, পরিবর্তন হয়নি।']);
            }
        }

        // ===== Save / update customer (admin + manager) =====
        if (isset($_POST['action']) && $this->isStaff()) {
            if (!$this->isCsrfValid($csrfToken)) {
                http_response_code(403);
                die('CSRF Token Validation Failed');
            }

            $data = [
                'shop_name'       => trim((string)($_POST['shop_name']       ?? '')),
                'customer_name'   => trim((string)($_POST['customer_name']   ?? '')),
                'phone'           => trim((string)($_POST['phone']           ?? '')),
                'address'         => trim((string)($_POST['address']         ?? '')),
                'credit_limit'    => $this->sanitizeAmount($_POST['credit_limit']    ?? 0),
                'opening_balance' => (float)($_POST['opening_balance'] ?? 0),
                'profile_pic'     => '',
            ];

            if ($data['shop_name'] === '') {
                header('Location: customers.php?msg=invalid');
                exit;
            }

            if (!empty($_POST['profile_pic'])) {
                $data['profile_pic'] = $this->imageUploader->saveBase64Image(
                    (string)$_POST['profile_pic'],
                    'uploads/profile/' . date('Y-m') . '/',
                    'cust'
                );
            }

            if ($_POST['action'] === 'save') {
                $newId = $this->model->addCustomer($data);

                if ($newId > 0) {
                    if (class_exists('AuditLogger')) {
                        AuditLogger::create('customers', $newId, null, $data);
                    }
                    if ($data['phone'] !== '') {
                        $this->sendSms($data['phone'], $this->buildWelcomeSms($data));
                    }
                }
            } elseif ($_POST['action'] === 'update' && $this->isAdmin()) {
                $cid = (int)($_POST['cust_id'] ?? 0);
                if ($cid > 0) {
                    $old = $this->model->getCustomerById($cid);
                    if ($data['profile_pic'] !== '') {
                        $oldPic = $old['profile_pic'] ?? '';
                        $this->imageUploader->deleteStoredImage($oldPic);
                    }
                    if ($this->model->updateCustomer($cid, $data) && class_exists('AuditLogger')) {
                        $newData = $data;
                        if ($newData['profile_pic'] === '' && $old) {
                            $newData['profile_pic'] = $old['profile_pic'] ?? '';
                        }
                        AuditLogger::update('customers', $cid, $old, $newData);
                    }
                }
            }

            header('Location: customers.php?msg=cust_saved');
            exit;
        }

        // ===== Single collection SMS (AJAX, admin + manager) =====
        if ($ajaxAction === 'send_collection_sms') {
            $this->requireCsrfJson($csrfToken);
            if (!$this->isStaff()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'SMS পাঠানোর অনুমতি নেই।']);
            }
            if (!$this->settings->isSmsEnabled()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'SMS বর্তমানে বন্ধ আছে। হেডারের SMS বাটন থেকে চালু করুন।']);
            }

            $cid = (int)($_POST['cid'] ?? 0);
            if ($cid <= 0) {
                $this->jsonResponse(['ok' => false, 'msg' => 'কাস্টমার ID নেই।']);
            }

            $customer = $this->model->getCustomerById($cid);
            if (!$customer || empty($customer['phone'])) {
                $this->jsonResponse(['ok' => false, 'msg' => 'কাস্টমারের ফোন নম্বর নেই।']);
            }

            $summary = $this->model->getCustomerFinancialSummary($cid);
            $result  = $this->sendSms(
                (string)$customer['phone'],
                $this->buildCollectionSms($customer, $summary)
            );

            $this->jsonResponse(
                !empty($result['success'])
                    ? ['ok' => true,  'msg' => 'SMS সফলভাবে পাঠানো হয়েছে!']
                    : ['ok' => false, 'msg' => 'SMS পাঠাতে ব্যর্থ হয়েছে।']
            );
        }

        // ===== Bulk collection SMS (AJAX, admin + manager, batch-capped) =====
        if ($ajaxAction === 'send_bulk_collection_sms') {
            $this->requireCsrfJson($csrfToken);
            if (!$this->isStaff()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'SMS পাঠানোর অনুমতি নেই।']);
            }
            if (!$this->settings->isSmsEnabled()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'SMS বর্তমানে বন্ধ আছে। হেডারের SMS বাটন থেকে চালু করুন।']);
            }

            @set_time_limit(60);

            $cids = $_POST['cids'] ?? [];
            if (!is_array($cids) || count($cids) === 0) {
                $this->jsonResponse(['ok' => false, 'msg' => 'কোনো কাস্টমার নির্বাচন করা হয়নি।']);
            }
            $cids = array_slice($cids, 0, self::BULK_SMS_BATCH_LIMIT);

            $sent = 0;
            $fail = 0;

            foreach ($cids as $rawId) {
                $cid = (int)$rawId;
                if ($cid <= 0) {
                    $fail++;
                    continue;
                }

                $customer = $this->model->getCustomerById($cid);
                if (!$customer || empty($customer['phone'])) {
                    $fail++;
                    continue;
                }

                $summary = $this->model->getCustomerFinancialSummary($cid);
                $result  = $this->sendSms(
                    (string)$customer['phone'],
                    $this->buildCollectionSms($customer, $summary)
                );

                if (!empty($result['success'])) {
                    $sent++;
                } else {
                    $fail++;
                }

                usleep(150000); // 0.15s — gateway rate-limit safety
            }

            $this->jsonResponse([
                'ok'   => true,
                'msg'  => "গ্রুপ SMS সম্পন্ন — পাঠানো: {$sent} জন" . ($fail ? ", ব্যর্থ: {$fail} জন" : ''),
                'sent' => $sent,
                'fail' => $fail,
            ]);
        }

        // ===== Verify password & delete customer (AJAX, admin only) =====
        if ($ajaxAction === 'verify_delete') {
            $this->requireCsrfJson($csrfToken);
            if (!$this->isAdmin()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'অ্যাডমিন একসেস প্রয়োজন।']);
            }

            $cid  = (int)($_POST['cid'] ?? 0);
            $pass = trim((string)($_POST['password'] ?? ''));

            if ($cid <= 0 || $pass === '') {
                $this->jsonResponse(['ok' => false, 'msg' => 'তথ্য অসম্পূর্ণ।']);
            }
            if (!$this->model->verifyAdminPassword($this->username, $pass)) {
                $this->jsonResponse(['ok' => false, 'msg' => 'ভুল পাসওয়ার্ড! ডিলিট করা হয়নি।']);
            }

            $old = $this->model->getCustomerById($cid);
            $ok  = $this->model->deleteCustomerComplete($cid);
            if ($ok && class_exists('AuditLogger') && $old) {
                AuditLogger::delete('customers', $cid, $old);
            }
            $this->jsonResponse(
                $ok
                    ? ['ok' => true,  'msg' => 'কাস্টমার সফলভাবে ডিলিট হয়েছে।']
                    : ['ok' => false, 'msg' => 'সার্ভার এরর, ডিলিট হয়নি।']
            );
        }
    }

    // =============================================
    // Customer profile page — POST handling
    // =============================================
    public function handleCustomerProfileRequests(int $id, string $csrfToken): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $ajaxAction = (string)($_POST['ajax_action'] ?? '');

        // ===== Toggle bill lock (AJAX, admin only) =====
        if ($ajaxAction === 'toggle_bill') {
            $this->requireCsrfJson($csrfToken);
            if (!$this->isAdmin()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'অ্যাডমিন একসেস প্রয়োজন।']);
            }

            try {
                $oldCust = $this->model->getCustomerById($id);
                $newState = $this->model->toggleBillLock($id);
                if (class_exists('AuditLogger') && $oldCust) {
                    AuditLogger::update(
                        'customers',
                        $id,
                        ['bill_locked' => (int)($oldCust['bill_locked'] ?? 0)],
                        ['bill_locked' => $newState],
                        "কাস্টমার #{$id} বিল লক টগল"
                    );
                }
                $this->jsonResponse([
                    'ok'    => true,
                    'state' => $newState,
                    'msg'   => $newState ? 'বিল এন্ট্রি লক করা হয়েছে।' : 'বিল এন্ট্রি আনলক করা হয়েছে।',
                ]);
            } catch (\Throwable) {
                $this->jsonResponse(['ok' => false, 'msg' => 'সার্ভার এরর, পরিবর্তন হয়নি।']);
            }
        }

        // ===== Update profile picture (admin + manager) =====
        if (isset($_POST['update_profile_pic'])) {
            if (!$this->isCsrfValid($csrfToken)) {
                http_response_code(403);
                die('CSRF Token Validation Failed');
            }
            if (!$this->isStaff()) {
                header("Location: customer_profile.php?id={$id}");
                exit;
            }

            if (!empty($_POST['new_profile_pic_data'])) {
                $imgPath = $this->imageUploader->saveBase64Image(
                    (string)$_POST['new_profile_pic_data'],
                    'uploads/profile_pictures/',
                    'cust_' . $id
                );
                if ($imgPath !== '') {
                    $oldPic = $this->model->getCustomerById($id)['profile_pic'] ?? '';
                    $this->imageUploader->deleteStoredImage($oldPic);
                    $this->model->updateProfilePic($id, $imgPath);
                }
            }
            header("Location: customer_profile.php?id={$id}&msg=pic_saved");
            exit;
        }

        // ===== Set collection dates (admin only) =====
        if (isset($_POST['set_coll_date']) && $this->isAdmin()) {
            if (!$this->isCsrfValid($csrfToken)) {
                http_response_code(403);
                die('CSRF Token Validation Failed');
            }

            $this->model->updateCollectionDates(
                $id,
                $this->sanitizeOptionalDate($_POST['next_date']   ?? null),
                $this->sanitizeOptionalDate($_POST['next_date_2'] ?? null)
            );
            header("Location: customer_profile.php?id={$id}&msg=date_saved");
            exit;
        }

        // ===== Save transaction (admin + manager) =====
        if (isset($_POST['save_tr']) && $this->isStaff()) {
            if (!$this->isCsrfValid($csrfToken)) {
                http_response_code(403);
                die('CSRF Token Validation Failed');
            }

            $customer = $this->model->getCustomerById($id);
            $isLocked = (int)($customer['bill_locked'] ?? 0);

            $imgPath = '';
            if (!empty($_POST['captured_image'])) {
                $imgPath = $this->imageUploader->saveBase64Image(
                    (string)$_POST['captured_image'],
                    'uploads/customer_transactions/' . date('Y-m') . '/',
                    'TR_' . $id
                );
            }

            // Managers can only post for today; admins may back-date (validated).
            $trDate = ($this->userRole === self::ROLE_MANAGER)
                ? date('Y-m-d')
                : $this->sanitizeDate($_POST['tr_date'] ?? null);

            $billAmount     = $isLocked ? 0.0 : $this->sanitizeAmount($_POST['bill_amount'] ?? 0);
            $receivedAmount = $this->sanitizeAmount($_POST['rec_amount'] ?? 0);
            $description    = trim((string)($_POST['desc'] ?? ''));
            $description    = $description !== '' ? $description : 'N/A';

            // At least one amount must be present.
            if ($billAmount <= 0 && $receivedAmount <= 0) {
                header("Location: customer_profile.php?id={$id}&msg=invalid_amount");
                exit;
            }

            $trData = [
                'customer_id'     => $id,
                'tr_date'         => $trDate,
                'description'     => $description,
                'bill_amount'     => $billAmount,
                'received_amount' => $receivedAmount,
                'entry_by'        => $this->username,
                'image_path'      => $imgPath,
            ];
            $trId = $this->model->addTransaction($trData);

            if ($trId > 0 && class_exists('AuditLogger')) {
                AuditLogger::create(
                    'customer_transactions',
                    $trId,
                    null,
                    $trData,
                    "কাস্টমার #{$id} — লেনদেন তৈরি"
                );
            }

            if ($trId > 0 && $customer) {
                // E-mail notification (recipient configured in App/.env).
                $this->mailService->sendTransactionNotice(
                    (string)$customer['shop_name'],
                    $trDate,
                    $description,
                    $billAmount,
                    $receivedAmount,
                    $this->username
                );

                // Transaction SMS.
                if (!empty($customer['phone'])) {
                    $summary = $this->model->getCustomerFinancialSummary($id);
                    $this->sendSms(
                        (string)$customer['phone'],
                        $this->buildTransactionSms($customer, $summary, $description, $billAmount, $receivedAmount)
                    );
                }
            }

            header("Location: customer_profile.php?id={$id}&msg=tr_saved");
            exit;
        }

        // ===== Profile page — collection SMS (AJAX, admin + manager) =====
        if ($ajaxAction === 'send_collection_sms_profile') {
            $this->requireCsrfJson($csrfToken);
            if (!$this->isStaff()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'SMS পাঠানোর অনুমতি নেই।']);
            }
            if (!$this->settings->isSmsEnabled()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'SMS বর্তমানে বন্ধ আছে। হেডারের SMS বাটন থেকে চালু করুন।']);
            }

            $customer = $this->model->getCustomerById($id);
            if (!$customer || empty($customer['phone'])) {
                $this->jsonResponse(['ok' => false, 'msg' => 'ফোন নম্বর পাওয়া যায়নি।']);
            }

            $summary = $this->model->getCustomerFinancialSummary($id);
            $result  = $this->sendSms(
                (string)$customer['phone'],
                $this->buildCollectionSms($customer, $summary)
            );

            $this->jsonResponse(
                !empty($result['success'])
                    ? ['ok' => true,  'msg' => 'কালেকশন SMS পাঠানো হয়েছে!']
                    : ['ok' => false, 'msg' => 'SMS পাঠাতে ব্যর্থ।']
            );
        }

        // ===== Verify & delete transaction (AJAX, admin only) =====
        if ($ajaxAction === 'verify_delete_tr') {
            $this->requireCsrfJson($csrfToken);
            if (!$this->isAdmin()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'শুধুমাত্র অ্যাডমিন ডিলিট করতে পারবেন।']);
            }

            $trId = (int)($_POST['tr_id'] ?? 0);
            $pass = trim((string)($_POST['password'] ?? ''));

            if ($trId <= 0 || $pass === '') {
                $this->jsonResponse(['ok' => false, 'msg' => 'তথ্য অসম্পূর্ণ।']);
            }
            if (!$this->model->verifyAdminPassword($this->username, $pass)) {
                $this->jsonResponse(['ok' => false, 'msg' => 'ভুল পাসওয়ার্ড! ডিলিট করা হয়নি।']);
            }

            $oldTr = $this->model->getTransactionById($trId);
            $ok    = $this->model->deleteTransactionComplete($trId, $id);
            if ($ok && class_exists('AuditLogger') && $oldTr) {
                AuditLogger::delete(
                    'customer_transactions',
                    $trId,
                    $oldTr,
                    "কাস্টমার #{$id} — লেনদেন #{$trId} ডিলিট"
                );
            }
            $this->jsonResponse(
                $ok
                    ? ['ok' => true,  'msg' => 'এন্ট্রি ডিলিট হয়েছে।']
                    : ['ok' => false, 'msg' => 'সার্ভার এরর, ডিলিট হয়নি।']
            );
        }

        // ===== Verify & edit transaction (AJAX, admin only) =====
        if ($ajaxAction === 'verify_edit_tr') {
            $this->requireCsrfJson($csrfToken);
            if (!$this->isAdmin()) {
                $this->jsonResponse(['ok' => false, 'msg' => 'শুধুমাত্র অ্যাডমিন এডিট করতে পারবেন।']);
            }

            $trId = (int)($_POST['tr_id'] ?? 0);
            $pass = trim((string)($_POST['password'] ?? ''));
            $bill = (float)($_POST['bill_amount'] ?? 0);
            $recv = (float)($_POST['received_amount'] ?? 0);
            $desc = trim((string)($_POST['description'] ?? ''));

            if ($trId <= 0 || $pass === '') {
                $this->jsonResponse(['ok' => false, 'msg' => 'তথ্য অসম্পূর্ণ।']);
            }
            if ($bill < 0 || $recv < 0) {
                $this->jsonResponse(['ok' => false, 'msg' => 'অঙ্ক ঋণাত্মক হতে পারে না।']);
            }
            if ($bill == 0.0 && $recv == 0.0) {
                $this->jsonResponse(['ok' => false, 'msg' => 'বিল অথবা জমা — অন্তত একটি দিন।']);
            }
            if (!$this->model->verifyAdminPassword($this->username, $pass)) {
                $this->jsonResponse(['ok' => false, 'msg' => 'ভুল পাসওয়ার্ড! এডিট করা হয়নি।']);
            }

            $transaction = $this->model->getTransactionById($trId);
            if (!$transaction || (int)$transaction['customer_id'] !== $id) {
                $this->jsonResponse(['ok' => false, 'msg' => 'এন্ট্রি খুঁজে পাওয়া যায়নি।']);
            }

            $newTr = [
                'bill_amount'     => $bill,
                'received_amount' => $recv,
                'description'     => $desc,
            ];
            $ok = $this->model->updateTransaction($trId, $id, $newTr);
            if ($ok && class_exists('AuditLogger')) {
                AuditLogger::update(
                    'customer_transactions',
                    $trId,
                    $transaction,
                    $newTr,
                    "কাস্টমার #{$id} — লেনদেন #{$trId} আপডেট"
                );
            }
            $this->jsonResponse(
                $ok
                    ? ['ok' => true,  'msg' => 'এন্ট্রি আপডেট হয়েছে।']
                    : ['ok' => false, 'msg' => 'সার্ভার এরর, আপডেট হয়নি।']
            );
        }
    }

    // =============================================
    // View data — customers list
    // =============================================
    public function getCustomersListData(): array
    {
        $activeCustomers   = $this->model->getActiveCustomers();
        $inactiveCustomers = $this->isAdmin() ? $this->model->getInactiveCustomers() : [];

        $totalMarketDue   = 0.0;
        $noEntry10days    = [];
        $limitCrossed     = [];
        $collectionAlerts = [];
        $todayDate        = date('Y-m-d');
        $tenDaysAgo       = date('Y-m-d', strtotime('-10 days'));

        foreach ($activeCustomers as $c) {
            $due             = (float)$c['opening_balance'] + ((float)$c['total_bill'] - (float)$c['total_rec']);
            $totalMarketDue += max(0.0, $due);

            if (empty($c['last_tr_date']) || $c['last_tr_date'] < $tenDaysAgo) {
                $noEntry10days[] = htmlspecialchars((string)$c['shop_name'], ENT_QUOTES, 'UTF-8');
            }
            if ((float)$c['credit_limit'] > 0 && $due > (float)$c['credit_limit']) {
                $limitCrossed[] = htmlspecialchars((string)$c['shop_name'], ENT_QUOTES, 'UTF-8');
            }
            if (!empty($c['next_collection_date']) && $c['next_collection_date'] === $todayDate) {
                $collectionAlerts[] = htmlspecialchars((string)$c['shop_name'], ENT_QUOTES, 'UTF-8');
            }
        }

        return [
            'role'              => $this->userRole,
            'username'          => $this->username,
            'sms_enabled'       => $this->settings->isSmsEnabled(),
            'activeCustomers'   => $activeCustomers,
            'inactiveCustomers' => $inactiveCustomers,
            'total_market_due'  => $totalMarketDue,
            'total_active'      => count($activeCustomers),
            'total_inactive'    => count($inactiveCustomers),
            'no_entry_10days'   => $noEntry10days,
            'limit_crossed'     => $limitCrossed,
            'collection_alerts' => $collectionAlerts,
        ];
    }

    // =============================================
    // View data — customer profile
    // =============================================
    public function getCustomerProfileData(int $id): array
    {
        $customer = $this->model->getCustomerById($id);
        if (!$customer || (int)$customer['is_active'] === 0) {
            return ['customer' => null];
        }

        $transactions = $this->model->getCustomerTransactions($id);
        $periodBill   = 0.0;
        $periodRec    = 0.0;

        foreach ($transactions as $t) {
            $periodBill += (float)$t['bill_amount'];
            $periodRec  += (float)$t['received_amount'];
        }

        $netDue     = ((float)$customer['opening_balance'] + $periodBill) - $periodRec;
        $limitAlert = ((float)$customer['credit_limit'] > 0 && $netDue > (float)$customer['credit_limit']);
        $hue        = ($id * 55) % 360;

        return [
            'role'         => $this->userRole,
            'username'     => $this->username,
            'customer'     => $customer,
            'transactions' => $transactions,
            'period_bill'  => $periodBill,
            'period_rec'   => $periodRec,
            'net_due'      => $netDue,
            'limit_alert'  => $limitAlert,
            'hue'          => $hue,
            'theme'        => "hsl({$hue}, 70%, 45%)",
        ];
    }
}
