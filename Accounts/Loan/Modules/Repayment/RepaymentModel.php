<?php

declare(strict_types=1);

/**
 * RepaymentModel — কোন লোনের কিস্তি কবে শোধ করতে হবে তার হিসাব।
 * (আপনি ঋণগ্রহীতা — এগুলো আপনার শোধ করার তালিকা।)
 */
final class RepaymentModel extends BaseModel
{
    /** আজ যেসব কিস্তির তারিখ। */
    public function dueToday(): array
    {
        return $this->fetchAll(
            "SELECT id, borrower_name, mobile, account_number, installment_amount, current_balance, due_date, frequency
             FROM sys_loans
             WHERE status = 'active' AND due_date = CURDATE()
             ORDER BY borrower_name ASC"
        );
    }

    /** তারিখ পার হয়ে গেছে (শোধ করা হয়নি)। */
    public function overdue(): array
    {
        return $this->fetchAll(
            "SELECT id, borrower_name, mobile, account_number, installment_amount, current_balance, due_date, frequency,
                    DATEDIFF(CURDATE(), due_date) AS days_late
             FROM sys_loans
             WHERE status = 'active' AND due_date IS NOT NULL AND due_date < CURDATE()
             ORDER BY due_date ASC"
        );
    }

    /** আগামী N দিনে যেসব কিস্তি। */
    public function upcoming(int $days = 7): array
    {
        $days = max(1, min($days, 90));
        return $this->fetchAll(
            "SELECT id, borrower_name, mobile, account_number, installment_amount, current_balance, due_date, frequency,
                    DATEDIFF(due_date, CURDATE()) AS days_left
             FROM sys_loans
             WHERE status = 'active' AND due_date IS NOT NULL
               AND due_date > CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL {$days} DAY)
             ORDER BY due_date ASC"
        );
    }

    /** একটি তারিখে মোট কত শোধ করা হয়েছে। */
    public function paidOn(string $date): array
    {
        return [
            'date'  => $date,
            'total' => (float)$this->fetchValue(
                'SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE txn_date = ?',
                [$date]
            ),
            'count' => (int)$this->fetchValue(
                'SELECT COUNT(*) FROM sys_loan_ledger WHERE txn_date = ? AND credit_amount > 0',
                [$date]
            ),
        ];
    }
}
