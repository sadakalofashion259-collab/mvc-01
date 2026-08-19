<?php

declare(strict_types=1);

/**
 * ReportModel — তারিখ অনুযায়ী রিপোর্ট (আপনি ঋণগ্রহীতা)।
 */
final class ReportModel extends BaseModel
{
    private const INTEREST_MATCH = "(description LIKE '%মুনাফা%' OR description LIKE '%সুদ%' OR description LIKE '%Interest%' OR description LIKE '%Profit%')";

    public function overview(string $from, string $to): array
    {
        return [
            // এই সময়ে কত টাকা লোন নিয়েছি
            'borrowed' => (float)$this->fetchValue(
                "SELECT COALESCE(SUM(debit_amount),0) FROM sys_loan_ledger
                 WHERE txn_date BETWEEN ? AND ? AND description LIKE '%লোন গ্রহণ%'",
                [$from, $to]
            ),
            // এই সময়ে কত শোধ করেছি
            'repaid' => (float)$this->fetchValue(
                'SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE txn_date BETWEEN ? AND ?',
                [$from, $to]
            ),
            // এই সময়ে কত সুদ শোধ করেছি
            'interestPaid' => (float)$this->fetchValue(
                'SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger
                 WHERE txn_date BETWEEN ? AND ? AND ' . self::INTEREST_MATCH,
                [$from, $to]
            ),
            'newLoans' => (int)$this->fetchValue(
                'SELECT COUNT(*) FROM sys_loans WHERE DATE(created_at) BETWEEN ? AND ?',
                [$from, $to]
            ),
            // মোট এখনো বাকি
            'outstanding' => (float)$this->fetchValue(
                "SELECT COALESCE(SUM(current_balance),0) FROM sys_loans WHERE status = 'active'"
            ),
        ];
    }

    public function daily(string $from, string $to): array
    {
        return $this->fetchAll(
            'SELECT txn_date,
                    COALESCE(SUM(credit_amount),0) AS repaid,
                    COALESCE(SUM(debit_amount),0)  AS added
             FROM sys_loan_ledger
             WHERE txn_date BETWEEN ? AND ?
             GROUP BY txn_date ORDER BY txn_date ASC',
            [$from, $to]
        );
    }

    public function byLoan(string $from, string $to): array
    {
        return $this->fetchAll(
            'SELECT s.id, s.borrower_name, s.account_number, s.principal_amount,
                    s.total_payable, s.current_balance, s.status, s.due_date,
                    COALESCE(SUM(l.credit_amount),0) AS total_paid,
                    COALESCE(SUM(CASE WHEN l.txn_date BETWEEN ? AND ? THEN l.credit_amount ELSE 0 END),0) AS paid_in_range
             FROM sys_loans s
             LEFT JOIN sys_loan_ledger l ON l.loan_id = s.id
             GROUP BY s.id, s.borrower_name, s.account_number, s.principal_amount,
                      s.total_payable, s.current_balance, s.status, s.due_date
             ORDER BY s.current_balance DESC',
            [$from, $to]
        );
    }
}
