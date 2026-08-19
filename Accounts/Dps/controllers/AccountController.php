<?php
/**
 * controllers/AccountController.php
 * ─────────────────────────────────────────
 * একাউন্ট তৈরি/এডিট/ছবি আপলোড/স্ট্যাটাস — ডিপোজিট-উইথড্র এখানে নেই (LedgerController-এ)।
 */
declare(strict_types=1);

final class AccountController
{
    private DpsAccountModel $accounts;
    private DpsLedgerModel  $ledger;

    public function __construct(private PDO $pdo)
    {
        $this->accounts = new DpsAccountModel($pdo);
        $this->ledger   = new DpsLedgerModel($pdo);
    }

    public function list(): void
    {
        $statusFilter = ($_POST['status_filter'] ?? '') === 'inactive' ? 'inactive' : 'active';
        $rows = $this->accounts->listByStatus($statusFilter);

        foreach ($rows as &$a) {
            $a['client_name']    = SecurityHelper::safeOut($a['client_name']);
            $a['account_number'] = SecurityHelper::safeOut($a['account_number'] ?? '');
            $a['photo_url']      = ImageUploader::urlFor($a['photo_path'] ?? null);
        }
        unset($a);

        SecurityHelper::jsonSuccess(['accounts' => $rows, 'status_filter' => $statusFilter]);
    }

    public function detail(): void
    {
        $dpsId = (int)($_POST['dps_id'] ?? 0);
        if ($dpsId <= 0) {
            SecurityHelper::jsonError('অ্যাকাউন্ট ID দিন।');
        }
        $acc = $this->accounts->findById($dpsId);
        if (!$acc) {
            SecurityHelper::jsonError('অ্যাকাউন্ট পাওয়া যায়নি।');
        }

        $pdo = $this->pdo;
        $principalStmt = $pdo->prepare("SELECT COALESCE(SUM(deposit_amount),0) FROM sys_dps_ledger WHERE dps_id = ? AND description NOT LIKE '%মুনাফা%'");
        $principalStmt->execute([$dpsId]);
        $grossPrincipal = round((float)$principalStmt->fetchColumn(), 2);

        $wdStmt = $pdo->prepare('SELECT COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE dps_id = ?');
        $wdStmt->execute([$dpsId]);
        $totalWithdrawn = round((float)$wdStmt->fetchColumn(), 2);

        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM sys_dps_ledger WHERE dps_id = ?');
        $cntStmt->execute([$dpsId]);

        $acc['client_name']    = SecurityHelper::safeOut($acc['client_name']);
        $acc['account_number'] = SecurityHelper::safeOut($acc['account_number'] ?? '');
        $acc['photo_url']      = ImageUploader::urlFor($acc['photo_path'] ?? null);

        SecurityHelper::jsonSuccess([
            'account'             => $acc,
            'principal_deposited' => round($grossPrincipal - $totalWithdrawn, 2),
            'total_withdrawn'     => $totalWithdrawn,
            'total_profit'        => round((float)$acc['total_profit_earned'], 2),
            'total_entries'       => (int)$cntStmt->fetchColumn(),
        ]);
    }

    public function dropdown(): void
    {
        $rows = $this->accounts->dropdownActive();
        foreach ($rows as &$r) {
            $r['client_name']    = SecurityHelper::safeOut($r['client_name']);
            $r['account_number'] = SecurityHelper::safeOut($r['account_number'] ?? '');
            $r['photo_url']      = ImageUploader::urlFor($r['photo_path'] ?? null);
        }
        unset($r);
        SecurityHelper::jsonSuccess(['accounts' => $rows]);
    }

    public function create(): void
    {
        $clientName = trim((string)($_POST['dps_client_name'] ?? ''));
        $accNo      = trim((string)($_POST['dps_account_number'] ?? ''));
        $accType    = in_array($_POST['dps_account_type'] ?? '', ['DPS', 'FDR'], true) ? $_POST['dps_account_type'] : 'DPS';
        $freq       = in_array($_POST['dps_frequency'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $_POST['dps_frequency'] : 'monthly';
        $amount     = round((float)($_POST['dps_installment_amount'] ?? 0), 2);
        $rate       = round((float)($_POST['dps_interest_rate'] ?? 0), 2);
        $durationYr = max(0, (int)($_POST['dps_duration_years'] ?? 0));
        $durationMoExtra = max(0, min(11, (int)($_POST['dps_duration_months'] ?? 0)));
        $durationMonths = max(1, ($durationYr * 12) + $durationMoExtra);
        $openBal    = round((float)($_POST['dps_opening_balance'] ?? 0), 2);

        $openDate = SecurityHelper::validateDate((string)($_POST['txn_date'] ?? '')) ?? date('Y-m-d');
        $nextDep  = !empty($_POST['next_deposit_date'])
            ? SecurityHelper::validateDate((string)$_POST['next_deposit_date'])
            : null;

        if (empty($clientName) || empty($accNo)) {
            SecurityHelper::jsonError('নাম ও অ্যাকাউন্ট নম্বর আবশ্যক।');
        }
        if (!$nextDep) {
            $nextDep = DateHelper::advanceByFrequency($openDate, $freq);
        }
        if ($this->accounts->accountNumberExists($accNo)) {
            SecurityHelper::jsonError('এই অ্যাকাউন্ট নম্বর আগেই আছে।');
        }

        // ছবি (ঐচ্ছিক) — ফর্মের সাথে একসাথে আপলোড হতে পারে
        $photoFilename = null;
        if (!empty($_FILES['account_photo']['name'])) {
            $photoFilename = ImageUploader::handle($_FILES['account_photo']);
        }

        $this->pdo->beginTransaction();
        try {
            $today      = date('Y-m-d');
            $pastProfit = 0.0;
            if (strtotime($openDate) < strtotime($today) && $openBal > 0 && $rate > 0) {
                $days       = (int)floor((strtotime($today) - strtotime($openDate)) / 86400);
                $pastProfit = round(($openBal * ($rate / 100) / 365) * $days, 2);
            }

            $newId = $this->accounts->create([
                'account_number'     => $accNo,
                'client_name'        => $clientName,
                'account_type'       => $accType,
                'frequency'          => $freq,
                'installment_amount' => $amount,
                'interest_rate'      => $rate,
                'duration_months'    => $durationMonths,
                'past_profit'        => $pastProfit,
                'opening_date'       => $openDate,
                'next_deposit_date'  => $nextDep,
                'photo_path'         => $photoFilename,
            ]);

            if ($openBal > 0) {
                $this->ledger->insertEntry($newId, $openDate, 'Opening Balance', $openBal, 0.00);
            }
            if ($pastProfit > 0) {
                $desc = "মুনাফা ({$rate}%), ৳" . number_format($pastProfit, 2);
                $this->ledger->insertEntry($newId, $today, $desc, $pastProfit, 0.00);
            }

            // BUGFIX: প্রারম্ভিক জমা ০ (শূন্য) হলে কোনো লেজার এন্ট্রি তৈরি হয় না, আর recalculate()
            // ব্যালেন্স ০ দেখে অ্যাকাউন্টকে সাথে সাথে 'inactive' করে দিত — ফলে নতুন খোলা
            // অ্যাকাউন্ট "সক্রিয়" ট্যাবে দেখাই যেত না, মনে হতো একাউন্ট খোলাই হয়নি।
            // তাই এন্ট্রি থাকলে তবেই recalculate() চালানো হচ্ছে; নাহলে create()-এ সেট করা
            // 'active' স্ট্যাটাস ও ০.০০ ব্যালেন্স অপরিবর্তিত থাকবে।
            if ($openBal > 0 || $pastProfit > 0) {
                $this->ledger->recalculate($newId);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($photoFilename) {
                ImageUploader::delete($photoFilename);
            }
            throw $e;
        }

        SecurityHelper::jsonSuccess(['message' => 'অ্যাকাউন্ট সফলভাবে খোলা হয়েছে।', 'new_id' => $newId]);
    }

    public function updateInfo(): void
    {
        $id         = (int)($_POST['acc_id'] ?? 0);
        $clientName = trim((string)($_POST['client_name'] ?? ''));
        $accNo      = trim((string)($_POST['account_number'] ?? ''));
        $accType    = in_array($_POST['account_type'] ?? '', ['DPS', 'FDR'], true) ? $_POST['account_type'] : 'DPS';
        $freq       = in_array($_POST['frequency'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $_POST['frequency'] : 'monthly';
        $amount     = round((float)($_POST['installment_amount'] ?? 0), 2);
        $rate       = round((float)($_POST['interest_rate'] ?? 0), 2);

        // মেয়াদ — বছর ও মাস দুটো ফিল্ড মিলিয়ে মোট মাস। ফিল্ড দুটো একেবারেই না এলে
        // (পুরনো ক্যাশড ফর্ম ইত্যাদি) ডাটাবেসের বিদ্যমান মান অপরিবর্তিত রাখা হয় —
        // আগে এখানে চুপচাপ ১ মাস বসে যেত, ফলে প্রতিবার এডিট করলেই মেয়াদ মুছে যেত।
        $durationMo = null;
        if (isset($_POST['duration_years']) || isset($_POST['duration_extra_months'])) {
            $yr    = max(0, (int)($_POST['duration_years'] ?? 0));
            $moExt = max(0, min(11, (int)($_POST['duration_extra_months'] ?? 0)));
            $durationMo = max(1, ($yr * 12) + $moExt);
        } elseif (isset($_POST['duration_months'])) {
            $durationMo = max(1, (int)$_POST['duration_months']);
        }

        if ($id <= 0) {
            SecurityHelper::jsonError('অ্যাকাউন্ট ID দিন।');
        }
        if (empty($clientName) || empty($accNo)) {
            SecurityHelper::jsonError('নাম ও অ্যাকাউন্ট নম্বর আবশ্যক।');
        }
        if ($this->accounts->accountNumberExists($accNo, $id)) {
            SecurityHelper::jsonError('এই অ্যাকাউন্ট নম্বর অন্য অ্যাকাউন্টে আছে।');
        }

        $this->accounts->updateInfo($id, [
            'client_name'        => $clientName,
            'account_number'     => $accNo,
            'account_type'       => $accType,
            'frequency'          => $freq,
            'installment_amount' => $amount,
            'interest_rate'      => $rate,
            'duration_months'    => $durationMo,
        ]);

        SecurityHelper::jsonSuccess(['message' => 'অ্যাকাউন্ট তথ্য আপডেট হয়েছে।']);
    }

    /** আলাদা এন্ডপয়েন্ট — শুধু ছবি আপলোড/পরিবর্তনের জন্য (ক্যামেরা বা গ্যালারি) */
    public function uploadPhoto(): void
    {
        $id = (int)($_POST['acc_id'] ?? 0);
        if ($id <= 0) {
            SecurityHelper::jsonError('অ্যাকাউন্ট ID দিন।');
        }
        $acc = $this->accounts->findById($id);
        if (!$acc) {
            SecurityHelper::jsonError('অ্যাকাউন্ট পাওয়া যায়নি।');
        }
        if (empty($_FILES['account_photo']['name'])) {
            SecurityHelper::jsonError('কোনো ছবি পাওয়া যায়নি।');
        }

        $newFilename = ImageUploader::handle($_FILES['account_photo']);
        $oldFilename = $acc['photo_path'] ?? null;

        $this->accounts->updatePhoto($id, $newFilename);
        if ($oldFilename) {
            ImageUploader::delete($oldFilename);
        }

        SecurityHelper::jsonSuccess([
            'message'   => 'ছবি আপডেট হয়েছে।',
            'photo_url' => ImageUploader::urlFor($newFilename),
        ]);
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['acc_id'] ?? 0);
        if ($id <= 0) {
            SecurityHelper::jsonError('অ্যাকাউন্ট ID দিন।');
        }
        $this->pdo->beginTransaction();
        try {
            $result = $this->accounts->toggleStatusForUpdate($id);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        SecurityHelper::jsonSuccess($result);
    }

    public function updateNextDeposit(): void
    {
        $id      = (int)($_POST['acc_id'] ?? 0);
        $nextDep = SecurityHelper::validateDate((string)($_POST['next_deposit_date'] ?? ''));
        if ($id <= 0 || $nextDep === null) {
            SecurityHelper::jsonError('তারিখের ফরম্যাট সঠিক নয় বা তথ্য অসম্পূর্ণ।');
        }
        $this->accounts->updateNextDepositDate($id, $nextDep);
        SecurityHelper::jsonSuccess(['message' => 'পরবর্তী জমার তারিখ আপডেট হয়েছে।']);
    }

    /** টোস্ট নোটিফিকেশনের জন্য — কার কবে কিস্তি বকেয়া/আজ */
    public function dueSoon(): void
    {
        $rows = $this->accounts->dueSoonList(2);
        foreach ($rows as &$r) {
            $r['client_name']    = SecurityHelper::safeOut($r['client_name']);
            $r['account_number'] = SecurityHelper::safeOut($r['account_number'] ?? '');
        }
        unset($r);
        SecurityHelper::jsonSuccess(['due_soon' => $rows]);
    }
}
