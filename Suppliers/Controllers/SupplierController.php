<?php
declare(strict_types=1);
if (!defined('SK_APP')) { http_response_code(403); exit('403 Forbidden'); }

/**
 * সাপ্লায়ার লিস্ট ও প্রোফাইল পেজের সব রিকোয়েস্ট হ্যান্ডলার।
 * ছবি → ImageUploader | SMS → SmsService (DB টেমপ্লেট) | নিরাপত্তা → Security
 */
final class SupplierController
{
    private SupplierModelInterface $model;
    private SmsService $sms;
    public string $userRole;
    public string $username;

    public function __construct(\PDO $conn)
    {
        $this->model    = new SupplierModel($conn);
        $gateway        = SmsGateway::fromDb($conn);
        $this->sms      = new SmsService($conn, $gateway, $this->model);
        $this->userRole = Security::role();
        $this->username = Security::username();

        // Audit Logger প্রস্তুত (একবার) — এরপর AuditLogger::create/update/delete ব্যবহার করা যাবে
        Audit::init($conn);
    }

    private function jsonExit(array $data): never
    {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** সাপ্লায়ার যোগ ও SMS ব্যবস্থাপনা — অ্যাডমিন ও ম্যানেজার */
    private function isEntryRole(): bool
    {
        return in_array($this->userRole, ['admin', 'manager'], true);
    }

    /** লেনদেন (বিল/জমা) এন্ট্রি — অ্যাডমিন, ম্যানেজার ও ইউজার সবাই দিতে পারবে (এডিট/ডিলিট শুধু অ্যাডমিন) */
    private function canRecordTransaction(): bool
    {
        return in_array($this->userRole, ['admin', 'manager', 'viewer'], true);
    }

    /**
     * সাপ্লায়ার ফর্ম ইনপুট যাচাই — client-side HTML5 validation বাইপাসযোগ্য,
     * তাই সার্ভার সাইডে বাধ্যতামূলক length cap ও ফরম্যাট চেক।
     * @return array{0:string,1:string,2:string,3:string,4:string,5:string} [name, shop, phone, email, address, error]
     */
    private function validateSupplierInput(
        string $name,
        string $shop,
        string $phone,
        string $email,
        string $address
    ): array {
        $name    = trim($name);
        $shop    = trim($shop);
        $phone   = trim($phone);
        $email   = trim($email);
        $address = trim($address);

        if ($name === '' || mb_strlen($name) > 150) {
            return [$name, $shop, $phone, $email, $address, 'মালিকের নাম আবশ্যক (সর্বোচ্চ ১৫০ অক্ষর)।'];
        }
        if ($shop === '' || mb_strlen($shop) > 150) {
            return [$name, $shop, $phone, $email, $address, 'দোকানের নাম আবশ্যক (সর্বোচ্চ ১৫০ অক্ষর)।'];
        }
        $digitsOnly = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        if ($digitsOnly === '' || mb_strlen($phone) > 25) {
            return [$name, $shop, $phone, $email, $address, 'সঠিক মোবাইল নম্বর দিন।'];
        }
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) {
            return [$name, $shop, $phone, $email, $address, 'ইমেইল ফরম্যাট সঠিক নয়।'];
        }
        if (mb_strlen($address) > 500) {
            return [$name, $shop, $phone, $email, $address, 'ঠিকানা অনেক বড় (সর্বোচ্চ ৫০০ অক্ষর)।'];
        }

        return [$name, $shop, $phone, $email, $address, ''];
    }

    // =========================================================
    //  সাপ্লায়ার লিস্ট পেজ — POST হ্যান্ডলার
    // =========================================================
    public function handleSuppliersListRequests(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        /* ১. নতুন সাপ্লায়ার (form POST) */
        if (isset($_POST['add_supplier'])) {
            if (!Security::verifyCsrf()) {
                http_response_code(400);
                exit('Security Error: CSRF token validation failed.');
            }
            if (!$this->isEntryRole()) {
                header('Location: /Suppliers/suppliers.php');
                exit;
            }

            [$name, $shop, $phone, $email, $address, $err] = $this->validateSupplierInput(
                (string)($_POST['name'] ?? ''),
                (string)($_POST['shop_name'] ?? ''),
                (string)($_POST['phone'] ?? ''),
                (string)($_POST['email'] ?? ''),
                (string)($_POST['address'] ?? '')
            );
            if ($err !== '') {
                header('Location: /Suppliers/suppliers.php?error=' . rawurlencode($err));
                exit;
            }

            $photo = ImageUploader::saveBase64((string)($_POST['photo_data'] ?? ''), 'suppliers', 'sup');
            $newData = [
                'name'            => $name,
                'shop_name'       => $shop,
                'phone'           => $phone,
                'email'           => $email,
                'address'         => $address,
                'opening_balance' => (float)($_POST['opening_balance'] ?? 0),
                'photo'           => $photo,
            ];
            $newId = $this->model->addSupplier($newData);
            if ($newId > 0) {
                AuditLogger::create('suppliers', $newId, null, $newData);
            }
            header('Location: /Suppliers/suppliers.php');
            exit;
        }

        /* ২. সাপ্লায়ার এডিট (AJAX — admin) */
        if (isset($_POST['ajax_edit_supplier'])) {
            if (!Security::verifyCsrf()) {
                $this->jsonExit(['ok' => false, 'msg' => 'Security Error: Invalid CSRF Token.']);
            }
            if ($this->userRole !== 'admin') {
                $this->jsonExit(['ok' => false, 'msg' => 'অ্যাডমিন একসেস প্রয়োজন।']);
            }
            try {
                $editId = (int)($_POST['edit_id'] ?? 0);
                $old    = $this->model->getSupplierById($editId);
                if (!$old) {
                    $this->jsonExit(['ok' => false, 'msg' => 'সাপ্লায়ার পাওয়া যায়নি।']);
                }

                [$name, $shop, $phone, $email, $address, $err] = $this->validateSupplierInput(
                    (string)($_POST['e_name'] ?? ''),
                    (string)($_POST['e_shop_name'] ?? ''),
                    (string)($_POST['e_phone'] ?? ''),
                    (string)($_POST['e_email'] ?? ''),
                    (string)($_POST['e_address'] ?? '')
                );
                if ($err !== '') {
                    $this->jsonExit(['ok' => false, 'msg' => $err]);
                }

                $newPhoto = '';
                if (!empty($_POST['e_photo_data'])) {
                    $newPhoto = ImageUploader::saveBase64((string)$_POST['e_photo_data'], 'suppliers', 'sup_edit');
                }

                $newData = [
                    'name'            => $name,
                    'shop_name'       => $shop,
                    'phone'           => $phone,
                    'email'           => $email,
                    'address'         => $address,
                    'opening_balance' => (float)($_POST['e_opening_balance'] ?? 0),
                    'photo'           => $newPhoto !== '' ? $newPhoto : ($old['photo'] ?? ''),
                ];
                $ok = $this->model->updateSupplier($editId, [
                    'name'            => $name,
                    'shop_name'       => $shop,
                    'phone'           => $phone,
                    'email'           => $email,
                    'address'         => $address,
                    'opening_balance' => (float)($_POST['e_opening_balance'] ?? 0),
                    'photo'           => $newPhoto,
                ]);

                // পুরনো ছবি শুধু DB আপডেট সফল হলেই ডিলিট — নাহলে ব্যর্থ আপডেটে ছবি হারানোর ঝুঁকি
                if ($ok && $newPhoto !== '') {
                    ImageUploader::delete((string)($old['photo'] ?? ''));
                }

                if ($ok) {
                    AuditLogger::update('suppliers', $editId, $old, $newData);
                }

                $this->jsonExit([
                    'ok'  => $ok,
                    'msg' => $ok ? 'সফলভাবে আপডেট হয়েছে।' : 'সার্ভার এরর, আপডেট হয়নি।',
                ]);
            } catch (\Throwable $e) {
                Logger::error('ajax_edit_supplier failed', $e);
                $this->jsonExit(['ok' => false, 'msg' => 'সার্ভার এরর।']);
            }
        }

        /* ৩. স্ট্যাটাস টগল (AJAX — admin) */
        if (isset($_POST['ajax_toggle_status'])) {
            if (ob_get_length()) { ob_clean(); }
            header('Content-Type: text/plain; charset=utf-8');
            if (!Security::verifyCsrf() || $this->userRole !== 'admin') {
                echo 'error';
                exit;
            }
            try {
                $tid = (int)($_POST['toggle_id'] ?? 0);
                $old = $this->model->getSupplierById($tid);
                $newStatus = $this->model->toggleStatus($tid);
                if ($old) {
                    AuditLogger::update(
                        'suppliers',
                        $tid,
                        ['status' => $old['status'] ?? 'active'],
                        ['status' => $newStatus],
                        "সাপ্লায়ার #{$tid} স্ট্যাটাস: " . ($old['status'] ?? '?') . " → {$newStatus}"
                    );
                }
                echo $newStatus;
            } catch (\Throwable $e) {
                echo 'error';
            }
            exit;
        }

        /* ৪. প্রতি সাপ্লায়ারে SMS টগল (AJAX — admin/manager) */
        if (isset($_POST['ajax_toggle_sms'])) {
            if (!Security::verifyCsrf()) {
                $this->jsonExit(['ok' => false, 'msg' => 'Security Error: Invalid CSRF Token.']);
            }
            if (!$this->isEntryRole()) {
                $this->jsonExit(['ok' => false, 'msg' => 'অনুমতি নেই।']);
            }
            try {
                $smsId  = (int)($_POST['sms_id'] ?? 0);
                $old    = $this->model->getSupplierById($smsId);
                $newVal = $this->model->toggleSmsEnabled($smsId);
                if ($old) {
                    AuditLogger::update(
                        'suppliers',
                        $smsId,
                        ['sms_enabled' => (int)($old['sms_enabled'] ?? 1)],
                        ['sms_enabled' => $newVal],
                        "সাপ্লায়ার #{$smsId} SMS: " . ((int)($old['sms_enabled'] ?? 1) === 1 ? 'চালু' : 'বন্ধ')
                        . " → " . ($newVal === 1 ? 'চালু' : 'বন্ধ')
                    );
                }
                $this->jsonExit(['ok' => true, 'enabled' => $newVal]);
            } catch (\Throwable $e) {
                $this->jsonExit(['ok' => false, 'msg' => 'সার্ভার এরর।']);
            }
        }

        /* ৫. সাপ্লায়ার ডিলিট (AJAX — admin + পাসওয়ার্ড) */
        if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'verify_delete') {
            if (!Security::verifyCsrf()) {
                $this->jsonExit(['ok' => false, 'msg' => 'Security Error: Invalid CSRF Token.']);
            }
            if ($this->userRole !== 'admin') {
                $this->jsonExit(['ok' => false, 'msg' => 'অ্যাডমিন একসেস প্রয়োজন।']);
            }
            $sid  = (int)($_POST['sid'] ?? 0);
            $pass = trim((string)($_POST['password'] ?? ''));

            if ($sid <= 0 || $pass === '') {
                $this->jsonExit(['ok' => false, 'msg' => 'তথ্য অসম্পূর্ণ।']);
            }
            if (!$this->model->verifyAdminPassword($this->username, $pass)) {
                $this->jsonExit(['ok' => false, 'msg' => 'ভুল পাসওয়ার্ড! ডিলিট করা হয়নি।']);
            }
            $old = $this->model->getSupplierById($sid);
            $ok  = $this->model->deleteSupplierComplete($sid);
            if ($ok && $old) {
                AuditLogger::delete('suppliers', $sid, $old);
            }
            $this->jsonExit([
                'ok'  => $ok,
                'msg' => $ok ? 'সাপ্লায়ার এবং সমস্ত ডেটা সফলভাবে ডিলিট হয়েছে।' : 'সার্ভার এরর, ডিলিট হয়নি।',
            ]);
        }
    }

    // =========================================================
    //  সাপ্লায়ার প্রোফাইল পেজ — POST হ্যান্ডলার
    // =========================================================
    public function handleSupplierProfileRequests(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (!isset($_POST['ajax_save_tr']) && !isset($_POST['ajax_action'])) {
            return;
        }
        if (!Security::verifyCsrf()) {
            $this->jsonExit(['ok' => false, 'msg' => 'Security Error: Invalid CSRF Token.']);
        }

        /* ১. লেনদেন সেভ (+ auto SMS) */
        if (isset($_POST['ajax_save_tr'])) {
            if (!$this->canRecordTransaction()) {
                $this->jsonExit(['ok' => false, 'msg' => 'এন্ট্রির অনুমতি নেই।']);
            }

            $bill = (float)($_POST['bill'] ?? 0);
            $pay  = (float)($_POST['pay'] ?? 0);
            $pcs  = (int)($_POST['pcs'] ?? 0);
            $memo = trim((string)($_POST['memo'] ?? ''));
            // শুধু অ্যাডমিন তারিখ বেছে নিতে পারবে; ম্যানেজার/ইউজার-এর ক্ষেত্রে আজকের তারিখ ফিক্সড
            $dt   = ($this->userRole === 'admin')
                ? trim((string)($_POST['dt'] ?? date('Y-m-d')))
                : date('Y-m-d');

            if ($bill <= 0 && $pay <= 0)   { $this->jsonExit(['ok' => false, 'msg' => 'বিল অথবা জমা — যেকোনো একটি লিখুন!']); }
            if ($bill > 0 && $pcs <= 0)    { $this->jsonExit(['ok' => false, 'msg' => 'বিলের ক্ষেত্রে পিস বাধ্যতামূলক।']); }
            if ($bill > 0 && $memo === '') { $this->jsonExit(['ok' => false, 'msg' => 'মেমো নম্বর দিন।']); }
            if ($memo === '') { $memo = '-'; }

            try {
                $subDir  = 'memos/' . date('Y') . '/' . date('m');
                $imgPath = ImageUploader::saveBase64((string)($_POST['t_photo'] ?? ''), $subDir, 'memo');

                $trData = [
                    'supplier_id'   => $id,
                    'tr_date'       => $dt,
                    'memo_no'       => $memo,
                    'pcs'           => $pcs,
                    'bill_received' => $bill,
                    'payment_given' => $pay,
                    'photo'         => $imgPath,
                    'entry_by'      => $this->username,
                ];
                $trId = $this->model->addTransaction($trData);

                if ($trId > 0) {
                    AuditLogger::create(
                        'supplier_transactions',
                        $trId,
                        null,
                        $trData,
                        "সাপ্লায়ার #{$id} — লেনদেন তৈরি (মেমো: {$memo})"
                    );
                }

                $supplier  = $this->model->getSupplierById($id);
                $stockDone = false;

                if ($bill > 0 && $supplier && $this->model->hasTable('stocks')) {
                    $desc = (string)$supplier['shop_name'] . ' — Memo: ' . $memo . ' — ' . date('d M Y', strtotime($dt));
                    $stockDone = $this->model->addStockFromTransaction($trId, $desc, $pcs, $bill, $imgPath, $this->username);
                }

                if ($supplier) {
                    $this->sendEntryEmail($supplier, $dt, $memo, $pcs, $bill, $pay);
                }

                /* auto SMS — টগল চালু ও গেটওয়ে রেডি হলে */
                $smsAuto = ['tried' => false, 'ok' => false, 'msg' => ''];
                if ($supplier
                    && (int)($supplier['sms_enabled'] ?? 1) === 1
                    && $this->sms->gateway()->isReady()) {

                    $smsAuto['tried'] = true;

                    $txns = $this->model->getTransactions($id);
                    $pBill = 0.0; $pPay = 0.0;
                    foreach ($txns as $x) {
                        $pBill += (float)$x['bill_received'];
                        $pPay  += (float)$x['payment_given'];
                    }
                    $netDue = ((float)$supplier['opening_balance'] + $pBill) - $pPay;

                    $res = $this->sms->sendTransactionSms($id, $supplier, [
                        'tr_date'       => $dt,
                        'memo_no'       => $memo,
                        'bill_received' => $bill,
                        'payment_given' => $pay,
                    ], $netDue, $trId);
                    $smsAuto['ok']  = (bool)$res['ok'];
                    $smsAuto['msg'] = (string)$res['msg'];
                }

                $okMsg = 'এন্ট্রি সফলভাবে সেভ হয়েছে।';
                if ($smsAuto['tried']) {
                    $okMsg .= $smsAuto['ok'] ? ' SMS পাঠানো হয়েছে।' : ' (তবে SMS যায়নি: ' . $smsAuto['msg'] . ')';
                }

                $this->jsonExit([
                    'ok'            => true,
                    'msg'           => $okMsg,
                    'stock_updated' => $stockDone,
                    'sms_sent'      => $smsAuto['ok'],
                    'tr_id'         => $trId,
                ]);
            } catch (\Throwable $e) {
                Logger::error('ajax_save_tr failed', $e);
                $this->jsonExit(['ok' => false, 'msg' => 'ডেটাবেজ সমস্যা। আবার চেষ্টা করুন।']);
            }
        }

        /* ২. নির্দিষ্ট লেনদেনের SMS পাঠানো (AJAX — admin/manager) */
        if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'send_sms') {
            $trId = (int)($_POST['tr_id'] ?? 0);
            if (!$this->isEntryRole()) {
                $this->jsonExit(['ok' => false, 'msg' => 'SMS পাঠানোর অনুমতি নেই।']);
            }
            $supplier = $this->model->getSupplierById($id);
            if (!$supplier) {
                $this->jsonExit(['ok' => false, 'msg' => 'সাপ্লায়ার পাওয়া যায়নি।']);
            }
            if (!$this->sms->gateway()->isReady()) {
                $this->jsonExit(['ok' => false, 'msg' => 'SMS পরিষেবা বন্ধ অথবা API তথ্য বসানো হয়নি।']);
            }
            if ((int)($supplier['sms_enabled'] ?? 1) !== 1) {
                $this->jsonExit(['ok' => false, 'msg' => 'এই সাপ্লায়ারের জন্য SMS বন্ধ আছে।']);
            }
            $tr = $this->model->getTransactionFull($trId);
            if (!$tr || (int)$tr['supplier_id'] !== $id) {
                $this->jsonExit(['ok' => false, 'msg' => "লেনদেন পাওয়া যায়নি। (ID: {$trId})"]);
            }

            $txns = $this->model->getTransactions($id);
            $pBill = 0.0; $pPay = 0.0;
            foreach ($txns as $x) { $pBill += (float)$x['bill_received']; $pPay += (float)$x['payment_given']; }
            $netDue = ((float)$supplier['opening_balance'] + $pBill) - $pPay;

            $res = $this->sms->sendTransactionSms($id, $supplier, $tr, $netDue, $trId);
            $this->jsonExit(['ok' => (bool)$res['ok'], 'msg' => (string)$res['msg']]);
        }

        /* ৩. লেনদেন ডিলিট (AJAX — admin + পাসওয়ার্ড) */
        if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'verify_delete_tr') {
            if ($this->userRole !== 'admin') {
                $this->jsonExit(['ok' => false, 'msg' => 'শুধুমাত্র অ্যাডমিন ডিলিট করতে পারবেন।']);
            }
            $trId = (int)($_POST['tr_id'] ?? 0);
            $pass = trim((string)($_POST['password'] ?? ''));
            if ($trId <= 0 || $pass === '') {
                $this->jsonExit(['ok' => false, 'msg' => 'তথ্য অসম্পূর্ণ।']);
            }
            if (!$this->model->verifyAdminPassword($this->username, $pass)) {
                $this->jsonExit(['ok' => false, 'msg' => 'ভুল পাসওয়ার্ড! ডিলিট করা হয়নি।']);
            }
            $oldTr = $this->model->getTransactionFull($trId);
            $ok    = $this->model->deleteTransactionComplete($trId, $id);
            if ($ok && $oldTr) {
                AuditLogger::delete(
                    'supplier_transactions',
                    $trId,
                    $oldTr,
                    "সাপ্লায়ার #{$id} — লেনদেন #{$trId} ডিলিট"
                );
            }
            $this->jsonExit(['ok' => $ok, 'msg' => $ok ? 'এন্ট্রি এবং স্টক ডিলিট হয়েছে।' : 'সার্ভার এরর, ডিলিট হয়নি।']);
        }

        /* ৪. লেনদেন এডিট (AJAX — admin) */
        if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'edit_tr') {
            if ($this->userRole !== 'admin') {
                $this->jsonExit(['ok' => false, 'msg' => 'শুধুমাত্র অ্যাডমিন এডিট করতে পারবেন।']);
            }
            $trId = (int)($_POST['tr_id'] ?? 0);
            $bill = (float)($_POST['bill'] ?? 0);
            $pay  = (float)($_POST['pay'] ?? 0);
            $pcs  = (int)($_POST['pcs'] ?? 0);
            $memo = trim((string)($_POST['memo'] ?? ''));
            $dt   = trim((string)($_POST['dt'] ?? date('Y-m-d')));

            if ($trId <= 0)                { $this->jsonExit(['ok' => false, 'msg' => 'লেনদেন পাওয়া যায়নি।']); }
            if ($bill <= 0 && $pay <= 0)   { $this->jsonExit(['ok' => false, 'msg' => 'বিল অথবা জমা — যেকোনো একটি লিখুন!']); }
            if ($bill > 0 && $pcs <= 0)    { $this->jsonExit(['ok' => false, 'msg' => 'বিলের ক্ষেত্রে পিস বাধ্যতামূলক।']); }
            if ($bill > 0 && $memo === '') { $this->jsonExit(['ok' => false, 'msg' => 'মেমো নম্বর দিন।']); }
            if ($memo === '') { $memo = '-'; }

            $oldTr = $this->model->getTransactionFull($trId);
            $newImg = '';
            if (!empty($_POST['t_photo'])) {
                $subDir = 'memos/' . date('Y') . '/' . date('m');
                $newImg = ImageUploader::saveBase64((string)$_POST['t_photo'], $subDir, 'memo');
            }

            $newTr = [
                'tr_date'       => $dt,
                'memo_no'       => $memo,
                'pcs'           => $pcs,
                'bill_received' => $bill,
                'payment_given' => $pay,
            ];
            $ok = $this->model->updateTransaction($trId, $id, $newTr, $newImg);
            if ($ok) {
                if ($newImg !== '') {
                    $newTr['photo'] = $newImg;
                }
                AuditLogger::update(
                    'supplier_transactions',
                    $trId,
                    $oldTr ?: null,
                    $newTr,
                    "সাপ্লায়ার #{$id} — লেনদেন #{$trId} আপডেট"
                );
            }
            $this->jsonExit(['ok' => $ok, 'msg' => $ok ? 'এন্ট্রি আপডেট হয়েছে।' : 'আপডেট করা যায়নি।']);
        }
    }

    /** এন্ট্রি নোটিফিকেশন ইমেইল (কোর PHP mail) */
    private function sendEntryEmail(array $supplier, string $dt, string $memo, int $pcs, float $bill, float $pay): void
    {
        $shop = htmlspecialchars((string)$supplier['shop_name'], ENT_QUOTES, 'UTF-8');
        $subj = ($bill > 0) ? "{$shop} সাপ্লাইবিল এন্ট্রি" : "{$shop} পেমেন্ট জমা";
        $body  = "<div style='font-family:Arial;padding:15px'><h3>{$shop}</h3>";
        $body .= "<p><b>তারিখ:</b> " . date('d M Y', strtotime($dt)) . "</p>";
        $body .= "<p><b>মেমো:</b> " . htmlspecialchars($memo, ENT_QUOTES, 'UTF-8') . "</p>";
        if ($bill > 0) { $body .= "<p style='color:green'><b>পিস:</b> {$pcs} | <b>বিল:</b> ৳" . number_format($bill) . "</p>"; }
        if ($pay > 0)  { $body .= "<p style='color:red'><b>জমা:</b> ৳" . number_format($pay) . "</p>"; }
        $body .= "<p><b>এন্ট্রি:</b> " . htmlspecialchars($this->username, ENT_QUOTES, 'UTF-8') . "</p></div>";

        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\n"
                 . "From: suppliers<noreply@sadakalofashion.com>\r\n";
        @mail('hisabkhata24@gmail.com', '=?UTF-8?B?' . base64_encode($subj) . '?=', $body, $headers);
    }

    // =========================================================
    //  ভিউ ডেটা
    // =========================================================
    public function getSuppliersListData(): array
    {
        $active   = $this->model->getActiveSuppliers();
        $inactive = ($this->userRole === 'admin') ? $this->model->getInactiveSuppliers() : [];

        $totalPayable = 0.0;
        foreach ($active as $s) {
            $totalPayable += ((float)$s['opening_balance'] + (float)$s['total_bill']) - (float)$s['total_pay'];
        }

        return [
            'role'              => $this->userRole,
            'username'          => $this->username,
            'activeSuppliers'   => $active,
            'inactiveSuppliers' => $inactive,
            'total_payable'     => $totalPayable,
            'total_active'      => count($active),
            'total_inactive'    => count($inactive),
        ];
    }

    public function getSupplierProfileData(int $id): array
    {
        $supplier = $this->model->getSupplierById($id);
        if (!$supplier) {
            return ['supplier' => null];
        }

        $transactions = $this->model->getTransactions($id);
        $periodBill = 0.0; $periodPay = 0.0;
        foreach ($transactions as $t) {
            $periodBill += (float)$t['bill_received'];
            $periodPay  += (float)$t['payment_given'];
        }

        $netDue = ((float)$supplier['opening_balance'] + $periodBill) - $periodPay;
        $clnPhn = SmsGateway::normalizePhone((string)$supplier['phone']);

        return [
            'role'         => $this->userRole,
            'username'     => $this->username,
            'supplier'     => $supplier,
            'transactions' => $transactions,
            'period_bill'  => $periodBill,
            'period_pay'   => $periodPay,
            'net_due'      => $netDue,
            'hue'          => ($id * 55) % 360,
            'cln_phn'      => $clnPhn,
            'sms_enabled'  => (int)($supplier['sms_enabled'] ?? 1),
        ];
    }
}
