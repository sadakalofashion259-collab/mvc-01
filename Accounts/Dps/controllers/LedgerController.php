<?php
/**
 * controllers/LedgerController.php
 * ─────────────────────────────────────────
 * জমা/উত্তোলন এন্ট্রি তৈরি, লেজার এডিট/ডিলিট, লেজার টেবিল ফেচ।
 * AccountController থেকে সম্পূর্ণ আলাদা — MVC আলাদা concern।
 */
declare(strict_types=1);

final class LedgerController
{
    private DpsAccountModel $accounts;
    private DpsLedgerModel  $ledger;

    public function __construct(private PDO $pdo)
    {
        $this->accounts = new DpsAccountModel($pdo);
        $this->ledger   = new DpsLedgerModel($pdo);
    }

    public function deposit(): void
    {
        $dpsId  = (int)($_POST['deposit_dps_id'] ?? 0);
        $amount = round((float)($_POST['dps_deposit_amount'] ?? 0), 2);
        $txnDate = SecurityHelper::validateDate((string)($_POST['txn_date'] ?? '')) ?? date('Y-m-d');
        $nextDep = !empty($_POST['next_deposit_date'])
            ? SecurityHelper::validateDate((string)$_POST['next_deposit_date'])
            : null;

        if ($amount <= 0 || $dpsId <= 0) {
            SecurityHelper::jsonError('সঠিক তথ্য দিন।');
        }

        $this->pdo->beginTransaction();
        try {
            $dps = $this->accounts->lockForUpdate($dpsId);
            if (!$dps) {
                SecurityHelper::jsonError('অ্যাকাউন্ট পাওয়া যায়নি।');
            }

            $this->ledger->insertEntry($dpsId, $txnDate, 'জমা (Deposit)', $amount, 0.00);

            if ($nextDep) {
                $this->accounts->updateNextDepositDate($dpsId, $nextDep);
            } else {
                $base     = !empty($dps['next_deposit_date']) ? $dps['next_deposit_date'] : $txnDate;
                $advanced = DateHelper::advanceByFrequency($base, (string)$dps['frequency']);
                $this->accounts->updateNextDepositDate($dpsId, $advanced);
            }

            $this->ledger->recalculate($dpsId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        SecurityHelper::jsonSuccess(['message' => '৳' . number_format($amount, 2) . ' সফলভাবে জমা হয়েছে।']);
    }

    public function withdraw(): void
    {
        $dpsId  = (int)($_POST['withdraw_dps_id'] ?? 0);
        $amount = round((float)($_POST['dps_withdraw_amount'] ?? 0), 2);
        $txnDate = SecurityHelper::validateDate((string)($_POST['txn_date'] ?? '')) ?? date('Y-m-d');

        if ($amount <= 0 || $dpsId <= 0) {
            SecurityHelper::jsonError('সঠিক তথ্য দিন।');
        }

        $this->pdo->beginTransaction();
        try {
            $dps = $this->accounts->lockForUpdate($dpsId);
            if (!$dps) {
                SecurityHelper::jsonError('অ্যাকাউন্ট পাওয়া যায়নি।');
            }
            if ((float)$dps['total_balance'] < $amount) {
                SecurityHelper::jsonError('অ্যাকাউন্টে পর্যাপ্ত ব্যালেন্স নেই!');
            }

            $this->ledger->insertEntry($dpsId, $txnDate, 'উত্তোলন (Withdraw)', 0.00, $amount);
            $this->ledger->recalculate($dpsId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        SecurityHelper::jsonSuccess(['message' => '৳' . number_format($amount, 2) . ' উত্তোলন সম্পন্ন।']);
    }

    public function editEntry(): void
    {
        $id      = (int)($_POST['id'] ?? 0);
        $newDesc = trim((string)($_POST['desc'] ?? ''));
        $newAmt  = round((float)($_POST['amount'] ?? 0), 2);
        if ($newAmt < 0) {
            SecurityHelper::jsonError('পরিমাণ ঋণাত্মক হতে পারে না।');
        }

        $this->pdo->beginTransaction();
        try {
            $l = $this->ledger->find($id);
            if (!$l) {
                SecurityHelper::jsonError('এন্ট্রি পাওয়া যায়নি।');
            }
            if (stripos($l['description'], 'Opening') !== false) {
                SecurityHelper::jsonError('Opening Balance এডিট করা যাবে না।');
            }

            $isWithdraw = (float)$l['withdraw_amount'] > 0;
            $this->ledger->updateEntry($id, $newAmt, $newDesc, $isWithdraw);
            $this->ledger->recalculate((int)$l['dps_id']);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        SecurityHelper::jsonSuccess(['message' => 'হিসাব আপডেট হয়েছে।']);
    }

    public function deleteEntry(): void
    {
        $id = (int)($_POST['id'] ?? 0);

        $this->pdo->beginTransaction();
        try {
            $l = $this->ledger->find($id);
            if (!$l) {
                SecurityHelper::jsonError('এন্ট্রি পাওয়া যায়নি।');
            }
            if (stripos($l['description'], 'Opening') !== false) {
                SecurityHelper::jsonError('Opening Balance মুছা যাবে না।');
            }
            $this->ledger->deleteEntry($id);
            $this->ledger->recalculate((int)$l['dps_id']);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        SecurityHelper::jsonSuccess(['message' => 'এন্ট্রি মুছা হয়েছে ও ব্যালেন্স রিক্যালকুলেট হয়েছে।']);
    }

    public function accountLedger(): void
    {
        $dpsId = (int)($_POST['dps_id'] ?? 0);
        $page  = max(1, (int)($_POST['page'] ?? 1));
        if ($dpsId <= 0) {
            SecurityHelper::jsonError('অ্যাকাউন্ট ID দিন।');
        }

        $result = $this->ledger->accountLedgerWindow($dpsId, $page);
        foreach ($result['ledger'] as &$l) {
            $l['description'] = SecurityHelper::safeOut($l['description']);
        }
        unset($l);

        SecurityHelper::jsonSuccess($result);
    }

    public function globalLedger(): void
    {
        $dpsId = isset($_POST['dps_id']) && $_POST['dps_id'] !== 'all' ? (int)$_POST['dps_id'] : 'all';
        $page  = max(1, (int)($_POST['page'] ?? 1));

        $result = $this->ledger->globalLedgerPaginated($dpsId, $page);
        foreach ($result['rows'] as &$r) {
            $r['client_name']    = SecurityHelper::safeOut($r['client_name']);
            $r['account_number'] = SecurityHelper::safeOut($r['account_number'] ?? '');
            $r['description']    = SecurityHelper::safeOut($r['description']);
            $r['photo_url']      = ImageUploader::urlFor($r['photo_path'] ?? null);
        }
        unset($r);

        SecurityHelper::jsonSuccess([
            'ledger'      => $result['rows'],
            'totalPages'  => $result['totalPages'],
            'currentPage' => $result['currentPage'],
        ]);
    }
}
