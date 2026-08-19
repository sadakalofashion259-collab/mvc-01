<?php

declare(strict_types=1);

/**
 * LoanModel — আপনি যেসব লোন *নিয়েছেন* তার হিসাব।
 *
 * হিসাবের দিক (আপনি ঋণগ্রহীতা):
 *   Debit (debit_amount)   → আপনার দায় বাড়ে   = মূল টাকা নেওয়া + প্রতিদিনের সুদ (cron)
 *   Credit (credit_amount) → আপনার দায় কমে     = কিস্তি শোধ করা
 *   current_balance        → এখনো যত শোধ করতে হবে (মোট Debit − মোট Credit)
 *
 * টেবিল/কলাম আপনার বর্তমান স্কিমার সাথে মিলিয়ে:
 *   sys_loans:       id, borrower_name, mobile, account_number, loan_category,
 *                    principal_amount, interest_rate, duration, frequency,
 *                    installment_amount, total_installments, total_payable,
 *                    total_interest, current_balance, due_date, status,
 *                    created_at, effective_rate
 *   sys_loan_ledger: id, loan_id, txn_date, description, debit_amount,
 *                    credit_amount, balance, photo_path, note, created_at
 *
 * দ্রষ্টব্য: borrower_name কলামটি এখানে "পাওনাদারের নাম" হিসেবে ব্যবহৃত
 * হয় — অর্থাৎ যার কাছ থেকে আপনি লোন নিয়েছেন (ব্যাংক/NGO/ব্যক্তি)।
 * কলামের নাম বদলাতে হয়নি, শুধু অর্থ বদলেছে।
 *
 * সুদের হিসাব (accrual-based — central_cron.php এর সাথে সামঞ্জস্যপূর্ণ):
 *   লোন খোলার সময় শুধু মূল টাকাই Debit হয়। total_interest কলামে
 *   শুধু "ডিজাইন করা লক্ষ্য" (target) সেভ থাকে — সেটা লেজারে বসে না।
 *   central_cron.php প্রতিদিন সামান্য একটু সুদ ('মুনাফা (অটো)' বর্ণনায়)
 *   Debit হিসেবে যোগ করে, যতক্ষণ না মোট যোগফল total_interest এর সমান হয়।
 *   ফলে কেউ আগেভাগে সম্পূর্ণ শোধ করে দিলে (লোন 'inactive' হয়ে গেলে),
 *   বাকি দিনগুলোর সুদ আর কখনো ধার্য হবে না।
 */
final class LoanModel extends BaseModel
{
    public const PREFERRED_CATEGORY = 'loan';
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    /** সুদ/মুনাফার এন্ট্রি চেনার জন্য (cron যেভাবে যোগ করে সেই বর্ণনার প্যাটার্ন)। */
    private const INTEREST_MATCH = "(description LIKE '%মুনাফা%' OR description LIKE '%সুদ%' OR description LIKE '%Interest%' OR description LIKE '%Profit%')";

    private static ?string $resolvedCategory = null;

    // ---------------------------------------------------------------
    // ব্যালেন্স ইঞ্জিন
    // ---------------------------------------------------------------

    /**
     * একটি লোনের প্রতিটি লেজার সারির চলমান ব্যালেন্স নতুন করে হিসাব করে
     * এবং লোনের current_balance ও status হালনাগাদ করে।
     * সবসময় ট্রানজেকশনের ভিতরে ডাকতে হবে (FOR UPDATE লক নেয়)।
     */
    public function recalculate(int $loanId): void
    {
        $this->execute('SELECT id FROM sys_loans WHERE id = ? FOR UPDATE', [$loanId]);

        $balance = Money::round((float)$this->fetchValue(
            'SELECT COALESCE(SUM(debit_amount),0) - COALESCE(SUM(credit_amount),0)
             FROM sys_loan_ledger WHERE loan_id = ?',
            [$loanId]
        ));

        $currentStatus = (string)$this->fetchValue('SELECT status FROM sys_loans WHERE id = ?', [$loanId]);

        $newStatus = $currentStatus;
        if ($balance <= 0.01) {
            $newStatus = 'inactive';           // সব শোধ হয়ে গেছে
        } elseif ($currentStatus === 'inactive') {
            $newStatus = 'active';
        }

        $this->execute(
            'UPDATE sys_loans SET current_balance = ?, status = ? WHERE id = ?',
            [$balance, $newStatus, $loanId]
        );

        $rows = $this->fetchAll(
            'SELECT id, debit_amount, credit_amount FROM sys_loan_ledger
             WHERE loan_id = ? ORDER BY txn_date ASC, id ASC FOR UPDATE',
            [$loanId]
        );

        $running = 0.0;
        foreach ($rows as $row) {
            $running += (float)$row['debit_amount'] - (float)$row['credit_amount'];
            $this->execute('UPDATE sys_loan_ledger SET balance = ? WHERE id = ?', [Money::round($running), $row['id']]);
        }
    }

    // ---------------------------------------------------------------
    // পড়া (Reads)
    // ---------------------------------------------------------------

    public function summary(): array
    {
        $today = date('Y-m-d');

        return [
            // আজ cron যত সুদ (দায়) যোগ করেছে — 'মুনাফা (অটো)' এন্ট্রিগুলো Debit হিসেবে বসে
            'todayInterest' => (float)$this->fetchValue(
                'SELECT COALESCE(SUM(debit_amount),0) FROM sys_loan_ledger
                 WHERE txn_date = ? AND ' . self::INTEREST_MATCH,
                [$today]
            ),
            // এখন পর্যন্ত প্রকৃতপক্ষে (ledger অনুযায়ী) যত সুদ ধার্য হয়েছে —
            // ভবিষ্যতের/এখনো-cron-না-করা সুদ এখানে যোগ হয় না
            'totalInterest' => (float)$this->fetchValue(
                'SELECT COALESCE(SUM(debit_amount),0) FROM sys_loan_ledger WHERE ' . self::INTEREST_MATCH
            ),
            // মোট কত এখনো শোধ করতে হবে
            'totalOutstanding' => (float)$this->fetchValue(
                "SELECT COALESCE(SUM(current_balance),0) FROM sys_loans WHERE status = 'active'"
            ),
            'activeCount' => (int)$this->fetchValue("SELECT COUNT(*) FROM sys_loans WHERE status = 'active'"),
            'closedCount' => (int)$this->fetchValue("SELECT COUNT(*) FROM sys_loans WHERE status = 'inactive'"),
        ];
    }

    public function activeLoans(): array
    {
        return $this->fetchAll(
            "SELECT id, borrower_name, account_number, current_balance, mobile
             FROM sys_loans WHERE status = 'active' ORDER BY borrower_name ASC"
        );
    }

    public function profiles(string $status, int $page = 1): array
    {
        $status = Security::allow($status, ['active', 'inactive'], 'active');

        $total = (int)$this->fetchValue('SELECT COUNT(*) FROM sys_loans WHERE status = ?', [$status]);
        $pg = $this->buildPagination($total, $page);

        $rows = $this->fetchAll(
            'SELECT id, borrower_name, mobile, account_number, principal_amount, interest_rate,
                    frequency, installment_amount, total_payable, current_balance, due_date, status,
                    (SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE loan_id = sys_loans.id) AS total_paid
             FROM sys_loans WHERE status = ?
             ORDER BY (due_date IS NULL), due_date ASC, id DESC
             LIMIT ' . $pg['limit'] . ' OFFSET ' . $pg['offset'],
            [$status]
        );

        return ['rows' => $rows, 'meta' => ['page' => $pg['page'], 'pages' => $pg['pages'], 'total' => $pg['total']]];
    }

    public function find(int $loanId): ?array
    {
        return $this->fetchOne('SELECT * FROM sys_loans WHERE id = ?', [$loanId]);
    }

    /** প্রতিটি লোনের প্রোফাইলের হিসাব: কত শোধ করলাম, কত সুদ দিলাম। */
    public function totalsFor(int $loanId): array
    {
        return [
            // মোট কত শোধ করেছি (সব credit)
            'totalPaid' => (float)$this->fetchValue(
                'SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE loan_id = ?',
                [$loanId]
            ),
            // এই লোনে এখন পর্যন্ত প্রকৃতপক্ষে কত সুদ ধার্য হয়েছে (cron/ম্যানুয়াল মিলিয়ে)
            'interestPaid' => (float)$this->fetchValue(
                'SELECT COALESCE(SUM(debit_amount),0) FROM sys_loan_ledger
                 WHERE loan_id = ? AND ' . self::INTEREST_MATCH,
                [$loanId]
            ),
        ];
    }

    public function ledgerForLoan(int $loanId, int $page): array
    {
        $total = (int)$this->fetchValue('SELECT COUNT(*) FROM sys_loan_ledger WHERE loan_id = ?', [$loanId]);
        $pg = $this->buildPagination($total, $page);

        $rows = $this->fetchAll(
            'SELECT * FROM sys_loan_ledger WHERE loan_id = ?
             ORDER BY txn_date DESC, id DESC
             LIMIT ' . $pg['limit'] . ' OFFSET ' . $pg['offset'],
            [$loanId]
        );

        return ['rows' => $rows, 'meta' => ['page' => $pg['page'], 'pages' => $pg['pages'], 'total' => $pg['total']]];
    }

    public function ledgerAll(?int $loanId, int $page): array
    {
        $where  = $loanId !== null ? 'WHERE l.loan_id = ?' : '';
        $params = $loanId !== null ? [$loanId] : [];

        $total = (int)$this->fetchValue("SELECT COUNT(*) FROM sys_loan_ledger l {$where}", $params);
        $pg = $this->buildPagination($total, $page);

        $rows = $this->fetchAll(
            "SELECT l.*, s.borrower_name, s.account_number
             FROM sys_loan_ledger l
             INNER JOIN sys_loans s ON s.id = l.loan_id
             {$where}
             ORDER BY l.txn_date DESC, l.id DESC
             LIMIT {$pg['limit']} OFFSET {$pg['offset']}",
            $params
        );

        return ['rows' => $rows, 'meta' => ['page' => $pg['page'], 'pages' => $pg['pages'], 'total' => $pg['total']]];
    }

    public function fullLedger(int $loanId): array
    {
        return $this->fetchAll(
            'SELECT * FROM sys_loan_ledger WHERE loan_id = ? ORDER BY txn_date ASC, id ASC',
            [$loanId]
        );
    }

    public function findLedgerEntry(int $ledgerId): ?array
    {
        return $this->fetchOne(
            'SELECT id, loan_id, debit_amount, credit_amount, photo_path FROM sys_loan_ledger WHERE id = ? FOR UPDATE',
            [$ledgerId]
        );
    }

    // ---------------------------------------------------------------
    // লেখা (Writes)
    // ---------------------------------------------------------------

    /**
     * নতুন লোন (নেওয়া) যোগ করে।
     *
     * installmentAmount দিলে সেটিই স্থির কিস্তি (ফ্ল্যাট)। নাহলে
     * reducing-balance পদ্ধতিতে EMI হিসাব হয়।
     *
     * খোলার সময় শুধু একটি এন্ট্রি বসে (Debit = আপনার দায়):
     *   মূল টাকা — "লোন গ্রহণ (মূল)"
     * সুদ এখানে বসে না — central_cron.php প্রতিদিন ধীরে ধীরে সুদ
     * যোগ করবে, যতক্ষণ না মোট যোগফল total_interest এর সমান হয়।
     * এতে current_balance শুরুতে শুধু principal-এর সমান থাকবে,
     * এবং আগেভাগে শোধ করে দিলে বাকি দিনের সুদ আর ধার্য হবে না।
     */
    public function create(array $input): array
    {
        return $this->transaction(function () use ($input): array {
            $principal    = (float)$input['principal_amount'];
            $rate         = (float)$input['interest_rate'];
            $installments = (int)$input['total_installments'];
            $freq         = LoanFrequency::fromSafe((string)$input['frequency']);
            $manualEmi    = (float)$input['installment_amount'];
            $dueDate      = $input['due_date'];
            $openDate     = $input['open_date'] ?? date('Y-m-d');

            if ($manualEmi > 0) {
                $emi = Money::round($manualEmi);
            } else {
                $periodRate = ($rate / 100) / $freq->periodsPerYear();
                $emi = $periodRate <= 0.0
                    ? $principal / $installments
                    : ($principal * $periodRate * (1 + $periodRate) ** $installments)
                        / ((1 + $periodRate) ** $installments - 1);
                $emi = Money::round($emi);
            }

            $totalPayable  = Money::round($emi * $installments);
            $totalInterest = Money::round($totalPayable - $principal);
            $effectiveRate = $principal > 0 ? Money::round(($totalInterest / $principal) * 100) : 0.0;
            $duration      = $freq->toMonths($installments);
            $accountNumber = $this->generateAccountNumber();
            $category      = $this->resolveCategory();
            $mobile        = isset($input['mobile']) ? trim((string)$input['mobile']) : null;
            if ($mobile === '') {
                $mobile = null;
            }

            // দ্রষ্টব্য: total_payable ও total_interest এখানে "ডিজাইন করা লক্ষ্য"
            // হিসেবে সেভ থাকে — central_cron.php এই কলাম দুটো দেখেই প্রতিদিন
            // কতটুকু সুদ যোগ করবে তা হিসাব করে। current_balance শুরুতে 0.00,
            // নিচের addLedgerEntry (শুধু মূল টাকা) এর পর recalculate() করলে
            // তা principal-এর সমান হয়ে যাবে।
            $columns = 'borrower_name, mobile, account_number, principal_amount, interest_rate,
                        duration, frequency, installment_amount, total_installments,
                        total_payable, total_interest, current_balance, due_date,
                        status, created_at, effective_rate';
            $values  = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, \'active\', NOW(), ?';
            $params  = [
                $input['borrower_name'], $mobile, $accountNumber, $principal, $rate,
                $duration, $freq->value, $emi, $installments,
                $totalPayable, $totalInterest, $dueDate, $effectiveRate,
            ];

            if ($category !== '') {
                $columns = 'borrower_name, mobile, account_number, loan_category, principal_amount, interest_rate,
                            duration, frequency, installment_amount, total_installments,
                            total_payable, total_interest, current_balance, due_date,
                            status, created_at, effective_rate';
                $values  = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, \'active\', NOW(), ?';
                $params  = [
                    $input['borrower_name'], $mobile, $accountNumber, $category, $principal, $rate,
                    $duration, $freq->value, $emi, $installments,
                    $totalPayable, $totalInterest, $dueDate, $effectiveRate,
                ];
            }

            $this->execute("INSERT INTO sys_loans ({$columns}) VALUES ({$values})", $params);
            $loanId = $this->lastInsertId();

            // শুধুমাত্র মূল টাকা — Debit (দায় বাড়ল)। সুদ এখানে বসানো হয় না;
            // central_cron.php প্রতিদিন 'মুনাফা (অটো)' এন্ট্রি হিসেবে
            // ধীরে ধীরে যোগ করবে, যতক্ষণ না total_interest এ পৌঁছায়।
            $this->addLedgerEntry($loanId, $openDate, 'লোন গ্রহণ (মূল)', $principal, 0.0);

            $this->recalculate($loanId);

            // SMS — নতুন লোন খোলা (ব্যর্থ হলে লোন তৈরি বাতিল হয় না)
            if ($mobile !== null) {
                try {
                    require_once APP_ROOT . '/Helpers/SmsService.php';
                    SmsService::sendLoanOpened(
                        $mobile,
                        (string)$input['borrower_name'],
                        $principal,
                        $accountNumber
                    );
                } catch (Throwable $e) {
                    Logger::warning('SMS on create failed: ' . $e->getMessage());
                }
            }

            return [
                'id'             => $loanId,
                'account_number' => $accountNumber,
                'installment'    => $emi,
                'total_payable'  => $totalPayable,
                'total_interest' => $totalInterest,
            ];
        });
    }

    /**
     * কিস্তি শোধ করা (Credit — দায় কমে)। ছবি ও কমেন্ট সহ।
     * শোধের পরিমাণ বাকির চেয়ে বেশি হলে false।
     */
    public function recordPayment(
        int $loanId,
        float $amount,
        string $txnDate,
        string $description,
        ?string $photoPath = null,
        ?string $note = null
    ): bool {
        $ok = (bool)$this->transaction(function () use ($loanId, $amount, $txnDate, $description, $photoPath, $note): bool {
            $loan = $this->fetchOne(
                'SELECT current_balance, due_date, frequency, borrower_name, mobile
                 FROM sys_loans WHERE id = ? FOR UPDATE',
                [$loanId]
            );

            if ($loan === null || $amount > (float)$loan['current_balance'] + 0.01) {
                return false;
            }

            $this->addLedgerEntry($loanId, $txnDate, $description, 0.0, $amount, $photoPath, $note);

            // শোধের পর কিস্তির তারিখ এক ধাপ এগিয়ে যায়
            if (!empty($loan['due_date'])) {
                $next = DueDate::advance((string)$loan['due_date'], (string)$loan['frequency']);
                if ($next !== null) {
                    $this->execute('UPDATE sys_loans SET due_date = ? WHERE id = ?', [$next, $loanId]);
                }
            }

            $this->recalculate($loanId);
            return true;
        });

        // ট্রানজেকশন সফল হলে SMS পাঠাও (ব্যর্থ হলে পেমেন্ট বাতিল হয় না)
        if ($ok) {
            try {
                $row = $this->fetchOne(
                    'SELECT borrower_name, current_balance, mobile FROM sys_loans WHERE id = ?',
                    [$loanId]
                );
                if ($row && !empty($row['mobile'])) {
                    require_once APP_ROOT . '/Helpers/SmsService.php';
                    SmsService::sendPaymentConfirm(
                        (string)$row['mobile'],
                        (string)$row['borrower_name'],
                        $amount,
                        (float)$row['current_balance']
                    );
                }
            } catch (Throwable $e) {
                Logger::warning('SMS on payment failed: ' . $e->getMessage());
            }
        }

        return $ok;
    }

    public function addLedgerEntry(
        int $loanId,
        string $txnDate,
        string $description,
        float $debit,
        float $credit,
        ?string $photoPath = null,
        ?string $note = null
    ): void {
        $this->execute(
            'INSERT INTO sys_loan_ledger
                (loan_id, txn_date, description, debit_amount, credit_amount, balance, photo_path, note, created_at)
             VALUES (?, ?, ?, ?, ?, 0.00, ?, ?, NOW())',
            [$loanId, $txnDate, $description, Money::round($debit), Money::round($credit), $photoPath, $note]
        );
    }

    public function updateLedgerEntry(
        int $ledgerId,
        string $description,
        float $amount,
        string $txnDate,
        ?string $note = null,
        ?string $newPhotoPath = null
    ): bool {
        return (bool)$this->transaction(function () use ($ledgerId, $description, $amount, $txnDate, $note, $newPhotoPath): bool {
            $entry = $this->findLedgerEntry($ledgerId);
            if ($entry === null) {
                return false;
            }

            $column = (float)$entry['debit_amount'] > 0 ? 'debit_amount' : 'credit_amount';
            $dateOk = ($txnDate !== '' && Security::isValidDate($txnDate));

            // নতুন ছবি দিলে পুরনোটা বদলাও, নাহলে আগেরটাই রাখো
            $photoClause = '';
            $params = [Money::round($amount), $description];
            if ($note !== null) { $photoClause .= ', note = ?'; $params[] = $note; }
            if ($newPhotoPath !== null) { $photoClause .= ', photo_path = ?'; $params[] = $newPhotoPath; }
            if ($dateOk) { $photoClause .= ', txn_date = ?'; $params[] = $txnDate; }
            $params[] = $ledgerId;

            $this->execute(
                "UPDATE sys_loan_ledger SET {$column} = ?, description = ?{$photoClause} WHERE id = ?",
                $params
            );

            // পুরনো ছবি ফাইল মুছে ফেলা
            if ($newPhotoPath !== null && !empty($entry['photo_path'])) {
                $this->deletePhotoFile((string)$entry['photo_path']);
            }

            $this->recalculate((int)$entry['loan_id']);
            return true;
        });
    }

    public function deleteLedgerEntry(int $ledgerId): bool
    {
        return (bool)$this->transaction(function () use ($ledgerId): bool {
            $entry = $this->findLedgerEntry($ledgerId);
            if ($entry === null) {
                return false;
            }
            $this->execute('DELETE FROM sys_loan_ledger WHERE id = ?', [$ledgerId]);
            if (!empty($entry['photo_path'])) {
                $this->deletePhotoFile((string)$entry['photo_path']);
            }
            $this->recalculate((int)$entry['loan_id']);
            return true;
        });
    }

    public function updateLenderName(int $loanId, string $name): bool
    {
        return $this->execute('UPDATE sys_loans SET borrower_name = ? WHERE id = ?', [$name, $loanId]) > 0;
    }

    public function updateMobile(int $loanId, ?string $mobile): bool
    {
        $mobile = $mobile !== null ? trim($mobile) : null;
        if ($mobile === '') {
            $mobile = null;
        }
        return $this->execute('UPDATE sys_loans SET mobile = ? WHERE id = ?', [$mobile, $loanId]) > 0;
    }

    public function updateDueDate(int $loanId, string $dueDate): bool
    {
        return $this->execute('UPDATE sys_loans SET due_date = ? WHERE id = ?', [$dueDate, $loanId]) > 0;
    }

    /**
     * লোনের মূল তথ্য সংশোধন করে — মূল টাকা, সুদের হার, কিস্তির সংখ্যা,
     * ধরন ও প্রতি কিস্তির টাকা। মোট শোধ্য/সুদ/কার্যকর হার নতুন করে হিসাব
     * হয়, "লোন গ্রহণ (মূল)" লেজার এন্ট্রিও আপডেট হয়, তারপর recalculate।
     */
    public function updateLoan(int $loanId, array $input): bool
    {
        return (bool)$this->transaction(function () use ($loanId, $input): bool {
            $loan = $this->fetchOne('SELECT id FROM sys_loans WHERE id = ? FOR UPDATE', [$loanId]);
            if ($loan === null) {
                return false;
            }

            $principal    = (float)$input['principal_amount'];
            $rate         = (float)$input['interest_rate'];
            $installments = max(1, (int)$input['total_installments']);
            $freq         = LoanFrequency::fromSafe((string)$input['frequency']);
            $manualEmi    = (float)$input['installment_amount'];

            if ($manualEmi > 0) {
                $emi = Money::round($manualEmi);
            } else {
                $periodRate = ($rate / 100) / $freq->periodsPerYear();
                $emi = $periodRate <= 0.0
                    ? $principal / $installments
                    : ($principal * $periodRate * (1 + $periodRate) ** $installments)
                        / ((1 + $periodRate) ** $installments - 1);
                $emi = Money::round($emi);
            }

            $totalPayable  = Money::round($emi * $installments);
            $totalInterest = Money::round($totalPayable - $principal);
            $effectiveRate = $principal > 0 ? Money::round(($totalInterest / $principal) * 100) : 0.0;
            $duration      = $freq->toMonths($installments);

            $this->execute(
                'UPDATE sys_loans SET principal_amount = ?, interest_rate = ?, duration = ?,
                        frequency = ?, installment_amount = ?, total_installments = ?,
                        total_payable = ?, total_interest = ?, effective_rate = ?
                 WHERE id = ?',
                [$principal, $rate, $duration, $freq->value, $emi, $installments,
                 $totalPayable, $totalInterest, $effectiveRate, $loanId]
            );

            // মূল টাকার লেজার এন্ট্রি (থাকলে) নতুন principal-এ আপডেট
            $this->execute(
                "UPDATE sys_loan_ledger SET debit_amount = ?
                 WHERE loan_id = ? AND description LIKE 'লোন গ্রহণ%'",
                [Money::round($principal), $loanId]
            );

            $this->recalculate($loanId);
            return true;
        });
    }

    public function toggleStatus(int $loanId): ?string
    {
        $current = $this->fetchValue('SELECT status FROM sys_loans WHERE id = ?', [$loanId]);
        if ($current === false || $current === null) {
            return null;
        }
        $new = $current === 'active' ? 'inactive' : 'active';
        $this->execute('UPDATE sys_loans SET status = ? WHERE id = ?', [$new, $loanId]);
        return $new;
    }

    public function deleteLoan(int $loanId): bool
    {
        return (bool)$this->transaction(function () use ($loanId): bool {
            if ($this->find($loanId) === null) {
                return false;
            }
            // সব ছবি মুছে ফেলা
            foreach ($this->fetchAll('SELECT photo_path FROM sys_loan_ledger WHERE loan_id = ? AND photo_path IS NOT NULL', [$loanId]) as $r) {
                $this->deletePhotoFile((string)$r['photo_path']);
            }
            $this->execute('DELETE FROM sys_loan_ledger WHERE loan_id = ?', [$loanId]);
            $this->execute('DELETE FROM sys_loans WHERE id = ?', [$loanId]);
            return true;
        });
    }

    // ---------------------------------------------------------------
    // অভ্যন্তরীণ
    // ---------------------------------------------------------------

    private function deletePhotoFile(string $relativePath): void
    {
        // শুধু Uploads ফোল্ডারের ভিতরের ফাইল — path traversal ঠেকাতে
        $safe = basename($relativePath);
        $full = APP_ROOT . '/Uploads/' . $safe;
        if (is_file($full)) {
            @unlink($full);
        }
    }

        private function generateAccountNumber(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $candidate = 'LN-' . date('ymd') . '-' . random_int(1000, 9999);
            if ((int)$this->fetchValue('SELECT COUNT(*) FROM sys_loans WHERE account_number = ?', [$candidate]) === 0) {
                return $candidate;
            }
        }
        return 'LN-' . date('ymdHis');
    }

    private function resolveCategory(): string
    {
        // loan_category কলাম ENUM('bank','ngo') হলে 'loan' বসানো যায় না।
        // কলামের ধরন দেখে নিরাপদ ভ্যালু রিটার্ন করা
        if (self::$resolvedCategory !== null) {
            return self::$resolvedCategory;
        }

        try {
            $type = (string)$this->fetchValue(
                "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = 'sys_loans' AND COLUMN_NAME = 'loan_category'"
            );
            if ($type !== '' && stripos($type, "'loan'") === false) {
                self::$resolvedCategory = '';
                return '';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        self::$resolvedCategory = self::PREFERRED_CATEGORY;
        return self::$resolvedCategory;
    }
}
