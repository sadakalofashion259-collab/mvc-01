<?php
/**
 * ========================================================
 * HistorysModel — ডেটাবেজ লেয়ার
 * সমস্ত SQL query এখানে।
 * ========================================================
 */
class HistorysModel
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    // ────────────────────────────────────────────────────
    // হেল্পার — Admin পাসওয়ার্ড যাচাই
    // ────────────────────────────────────────────────────
    public function verifyAdminPass(string $username, string $pass): bool
    {
        try {
            $st = $this->conn->prepare(
                "SELECT password FROM users WHERE username = ? AND role = 'admin' LIMIT 1"
            );
            $st->execute([$username]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) return false;
            $stored = $row['password'];
            if ($stored === $pass)            return true;
            if (md5($pass) === $stored)       return true;
            if (password_verify($pass, $stored)) return true;
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    // ────────────────────────────────────────────────────
    // AJAX — সাধারণ Delete/Edit
    // ────────────────────────────────────────────────────
    public function deleteItem(string $table, int $id): void
    {
        $col = null;
        if (in_array($table, ['sales_entries', 'expense_entries', 'supplier_transactions'])) {
            $col = 'photo';
        } elseif ($table === 'customer_transactions') {
            $col = 'image_path';
        } elseif (in_array($table, ['stocks', 'stock_entries'])) {
            $col = 'image';
        }

        if ($col) {
            $f = $this->conn->query("SELECT $col FROM $table WHERE id=$id")->fetchColumn();
            if (!empty($f) && file_exists($f)) unlink($f);
        }
        $this->conn->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
    }

    public function editItem(string $table, string $field, $val, int $id): void
    {
        $this->conn->prepare("UPDATE $table SET $field=? WHERE id=?")->execute([$val, $id]);
    }

    // ────────────────────────────────────────────────────
    // AJAX — DPS Ledger Delete
    // ────────────────────────────────────────────────────
    public function deleteDpsEntry(int $lid): array
    {
        $this->conn->beginTransaction();
        $row = $this->conn->query(
            "SELECT dps_id, description FROM sys_dps_ledger WHERE id=$lid"
        )->fetch();
        if (!$row) {
            $this->conn->rollBack();
            return ['ok' => false, 'msg' => 'এন্ট্রি পাওয়া যায়নি।'];
        }
        if (stripos($row['description'], 'Opening') !== false) {
            $this->conn->rollBack();
            return ['ok' => false, 'msg' => 'Opening Balance ডিলিট করা যাবে না।'];
        }
        $did = $row['dps_id'];
        $this->conn->prepare("DELETE FROM sys_dps_ledger WHERE id=?")->execute([$lid]);

        $b  = $this->conn->query(
            "SELECT COALESCE(SUM(deposit_amount),0)-COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE dps_id=$did"
        )->fetchColumn();
        $nb = max(0, round(floatval($b), 2));
        $ns = $nb <= 0.01 ? 'inactive' : 'active';
        $this->conn->prepare(
            "UPDATE sys_dps_accounts SET total_balance=?,status=? WHERE id=?"
        )->execute([$nb, $ns, $did]);

        $rs  = $this->conn->query(
            "SELECT id,deposit_amount,withdraw_amount FROM sys_dps_ledger WHERE dps_id=$did ORDER BY txn_date ASC,id ASC"
        )->fetchAll();
        $run = 0;
        foreach ($rs as $r) {
            $run += floatval($r['deposit_amount']) - floatval($r['withdraw_amount']);
            $this->conn->prepare(
                "UPDATE sys_dps_ledger SET current_balance=? WHERE id=?"
            )->execute([round($run, 2), $r['id']]);
        }
        $this->conn->commit();
        return ['ok' => true, 'msg' => '✅ DPS এন্ট্রি ডিলিট ও ব্যালেন্স আপডেট।'];
    }

    // ────────────────────────────────────────────────────
    // AJAX — Loan Ledger Delete
    // ────────────────────────────────────────────────────
    public function deleteLoanEntry(int $lid): array
    {
        $this->conn->beginTransaction();
        $row = $this->conn->query(
            "SELECT loan_id FROM sys_loan_ledger WHERE id=$lid"
        )->fetch();
        if (!$row) {
            $this->conn->rollBack();
            return ['ok' => false, 'msg' => 'এন্ট্রি পাওয়া যায়নি।'];
        }
        $loid = $row['loan_id'];
        $this->conn->prepare("DELETE FROM sys_loan_ledger WHERE id=?")->execute([$lid]);

        $b  = $this->conn->query(
            "SELECT COALESCE(SUM(debit_amount),0)-COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE loan_id=$loid"
        )->fetchColumn();
        $nb  = max(0, round(floatval($b), 2));
        $ns  = $nb <= 0.01 ? 'inactive' : 'active';
        $this->conn->prepare(
            "UPDATE sys_loans SET current_balance=?,status=? WHERE id=?"
        )->execute([$nb, $ns, $loid]);

        $rs  = $this->conn->query(
            "SELECT id,debit_amount,credit_amount FROM sys_loan_ledger WHERE loan_id=$loid ORDER BY id ASC"
        )->fetchAll();
        $run = 0;
        foreach ($rs as $r) {
            $run += floatval($r['debit_amount']) - floatval($r['credit_amount']);
            $this->conn->prepare(
                "UPDATE sys_loan_ledger SET balance=? WHERE id=?"
            )->execute([round($run, 2), $r['id']]);
        }
        $this->conn->commit();
        return ['ok' => true, 'msg' => '✅ লোন এন্ট্রি ডিলিট ও রিক্যালকুলেট।'];
    }

    // ────────────────────────────────────────────────────
    // AJAX — Credit Card Ledger Delete
    // ────────────────────────────────────────────────────
    public function deleteCardLedgerEntry(int $lid): array
    {
        $this->conn->beginTransaction();
        $row = $this->conn->query(
            "SELECT card_id, receipt_image FROM sys_card_ledger WHERE id=$lid"
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->conn->rollBack();
            return ['ok' => false, 'msg' => 'এন্ট্রি পাওয়া যায়নি।'];
        }
        $card_id = $row['card_id'];
        if (!empty($row['receipt_image']) && file_exists($row['receipt_image'])) {
            unlink($row['receipt_image']);
        }
        $this->conn->prepare("DELETE FROM sys_card_ledger WHERE id=?")->execute([$lid]);

        $rs  = $this->conn->query(
            "SELECT id, card_balance_change FROM sys_card_ledger WHERE card_id=$card_id ORDER BY txn_date ASC, id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $run = 0;
        foreach ($rs as $r) {
            $run += floatval($r['card_balance_change']);
            $this->conn->prepare(
                "UPDATE sys_card_ledger SET running_balance=? WHERE id=?"
            )->execute([round($run, 2), $r['id']]);
        }
        $this->conn->commit();
        return ['ok' => true, 'msg' => '✅ কার্ড এন্ট্রি ডিলিট হয়েছে।'];
    }

    // ────────────────────────────────────────────────────
    // তারিখ ফিল্টার ও ডেট লিস্ট
    // ────────────────────────────────────────────────────
    public function getActiveDates(string $from, string $to): array
    {
        $d = [];
        $d[] = $this->conn->query(
            "SELECT DISTINCT report_date FROM daily_reports WHERE report_date BETWEEN '$from' AND '$to'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $d[] = $this->conn->query(
            "SELECT DISTINCT tr_date FROM customer_transactions WHERE tr_date BETWEEN '$from' AND '$to'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $d[] = $this->conn->query(
            "SELECT DISTINCT tr_date FROM supplier_transactions WHERE tr_date BETWEEN '$from' AND '$to'"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ([
            "SELECT DISTINCT DATE(created_at) FROM stocks WHERE DATE(created_at) BETWEEN '$from' AND '$to'",
            "SELECT DISTINCT tr_date FROM stock_entries WHERE tr_date BETWEEN '$from' AND '$to'",
            "SELECT DISTINCT expense_date FROM staff_expenses WHERE expense_date BETWEEN '$from' AND '$to'",
            "SELECT DISTINCT txn_date FROM sys_dps_ledger WHERE txn_date BETWEEN '$from' AND '$to'",
            "SELECT DISTINCT txn_date FROM sys_loan_ledger WHERE txn_date BETWEEN '$from' AND '$to'",
            "SELECT DISTINCT txn_date FROM sys_card_ledger WHERE txn_date BETWEEN '$from' AND '$to'",
        ] as $sql) {
            try { $d[] = $this->conn->query($sql)->fetchAll(PDO::FETCH_COLUMN); }
            catch (Exception $e) {}
        }

        $all = array_values(array_unique(array_merge(...$d)));
        rsort($all);
        return $all;
    }

    // ────────────────────────────────────────────────────
    // Period Summary
    // ────────────────────────────────────────────────────
    public function getPeriodSummary(string $from, string $to): array
    {
        $c  = $this->conn;
        $s  = [];

        // Report-based (sales, collection, expense)
        $rids = $c->query(
            "SELECT id FROM daily_reports WHERE report_date BETWEEN '$from' AND '$to'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if ($rids) {
            $in = implode(',', $rids);
            $s['sale_cash'] = floatval($c->query("SELECT COALESCE(SUM(paid_amount),0)  FROM sales_entries WHERE report_id IN ($in)")->fetchColumn());
            $s['sale_due']  = floatval($c->query("SELECT COALESCE(SUM(due_amount),0)   FROM sales_entries WHERE report_id IN ($in)")->fetchColumn());
            $s['coll']      = floatval($c->query("SELECT COALESCE(SUM(total_deposit),0) FROM collection_entries WHERE report_id IN ($in)")->fetchColumn());
            $s['exp']       = floatval($c->query("SELECT COALESCE(SUM(amount),0)       FROM expense_entries WHERE report_id IN ($in)")->fetchColumn());
        } else {
            $s['sale_cash'] = $s['sale_due'] = $s['coll'] = $s['exp'] = 0;
        }

        $s['cust_rcv'] = floatval($c->query("SELECT COALESCE(SUM(received_amount),0) FROM customer_transactions WHERE tr_date BETWEEN '$from' AND '$to'")->fetchColumn());
        $s['sup_pay']  = floatval($c->query("SELECT COALESCE(SUM(payment_given),0)   FROM supplier_transactions WHERE tr_date BETWEEN '$from' AND '$to'")->fetchColumn());

        $s['staff'] = 0;
        try { $s['staff'] = floatval($c->query("SELECT COALESCE(SUM(amount),0) FROM staff_expenses WHERE expense_date BETWEEN '$from' AND '$to'")->fetchColumn()); }
        catch (Exception $e) {}

        // Loan
        $s['loan_in'] = $s['loan_out'] = 0;
        try {
            $s['loan_in']  = floatval($c->query("SELECT COALESCE(SUM(debit_amount),0) FROM sys_loan_ledger WHERE txn_date BETWEEN '$from' AND '$to' AND description NOT LIKE '%মুনাফা%'")->fetchColumn());
            $s['loan_out'] = floatval($c->query("SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE txn_date BETWEEN '$from' AND '$to'")->fetchColumn());
        } catch (Exception $e) {}

        // DPS
        $s['dps_dep'] = $s['dps_wth'] = 0;
        try {
            $s['dps_dep'] = floatval($c->query("SELECT COALESCE(SUM(deposit_amount),0)  FROM sys_dps_ledger WHERE txn_date BETWEEN '$from' AND '$to' AND description NOT LIKE '%মুনাফা%' AND description NOT LIKE '%Opening%'")->fetchColumn());
            $s['dps_wth'] = floatval($c->query("SELECT COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE txn_date BETWEEN '$from' AND '$to'")->fetchColumn());
        } catch (Exception $e) {}

        // Credit Card
        $s['card_cash_out'] = $s['card_advance'] = $s['card_pay'] = $s['card_charge'] = 0;
        $s['card_outstanding'] = $s['card_lifetime_charge'] = 0;
        $s['card_active_count'] = $s['card_inactive_count'] = 0;
        try {
            $s['card_cash_out']       = floatval($c->query("SELECT COALESCE(SUM(ABS(cash_impact)),0) FROM sys_card_ledger WHERE txn_date BETWEEN '$from' AND '$to' AND cash_impact < 0")->fetchColumn());
            $s['card_advance']        = floatval($c->query("SELECT COALESCE(SUM(cash_impact),0)      FROM sys_card_ledger WHERE txn_date BETWEEN '$from' AND '$to' AND cash_impact > 0")->fetchColumn());
            $s['card_pay']            = floatval($c->query("SELECT COALESCE(SUM(amount),0) FROM sys_card_ledger WHERE txn_date BETWEEN '$from' AND '$to' AND txn_type IN ('bill_pay','min_pay','full_pay')")->fetchColumn());
            $s['card_charge']         = floatval($c->query("SELECT COALESCE(SUM(amount),0) FROM sys_card_ledger WHERE txn_date BETWEEN '$from' AND '$to' AND txn_type='charge_pay'")->fetchColumn());
            $raw_outstanding          = floatval($c->query("SELECT COALESCE(SUM(card_balance_change),0) FROM sys_card_ledger WHERE card_id IN (SELECT id FROM sys_credit_cards WHERE status='active')")->fetchColumn());
            $s['card_outstanding']    = $raw_outstanding < 0 ? 0 : $raw_outstanding;
            $s['card_lifetime_charge']= floatval($c->query("SELECT COALESCE(SUM(charge_amount),0) FROM sys_card_ledger")->fetchColumn());
            $s['card_active_count']   = intval($c->query("SELECT COUNT(*) FROM sys_credit_cards WHERE status='active'")->fetchColumn());
            $s['card_inactive_count'] = intval($c->query("SELECT COUNT(*) FROM sys_credit_cards WHERE status='inactive'")->fetchColumn());
        } catch (Exception $e) {}

        // Totals
        $s['total_in']  = $s['sale_cash'] + $s['coll'] + $s['cust_rcv'] + $s['loan_in']  + $s['dps_wth'] + $s['card_advance'];
        $s['total_out'] = $s['exp']       + $s['sup_pay'] + $s['staff'] + $s['loan_out'] + $s['dps_dep'] + $s['card_cash_out'];
        $s['net_cash']  = $s['total_in'] - $s['total_out'];

        return $s;
    }

    // ────────────────────────────────────────────────────
    // Opening Balance (পিরিয়ডের আগে)
    // ────────────────────────────────────────────────────
    public function getOpeningBalance(string $from): float
    {
        $c = $this->conn;
        try {
            $ob_rids = $c->query("SELECT id FROM daily_reports WHERE report_date < '$from'")->fetchAll(PDO::FETCH_COLUMN);
            $ob_sale = $ob_coll = $ob_exp = 0;
            if ($ob_rids) {
                $ors     = implode(',', $ob_rids);
                $ob_sale = floatval($c->query("SELECT COALESCE(SUM(paid_amount),0)   FROM sales_entries       WHERE report_id IN ($ors)")->fetchColumn());
                $ob_coll = floatval($c->query("SELECT COALESCE(SUM(total_deposit),0) FROM collection_entries  WHERE report_id IN ($ors)")->fetchColumn());
                $ob_exp  = floatval($c->query("SELECT COALESCE(SUM(amount),0)        FROM expense_entries     WHERE report_id IN ($ors)")->fetchColumn());
            }
            $ob_cust  = floatval($c->query("SELECT COALESCE(SUM(received_amount),0) FROM customer_transactions WHERE tr_date < '$from'")->fetchColumn());
            $ob_sup   = floatval($c->query("SELECT COALESCE(SUM(payment_given),0)   FROM supplier_transactions WHERE tr_date < '$from'")->fetchColumn());
            $ob_staff = 0;
            try { $ob_staff = floatval($c->query("SELECT COALESCE(SUM(amount),0) FROM staff_expenses WHERE expense_date < '$from'")->fetchColumn()); } catch (Exception $e) {}

            $ob_lin = $ob_lout = 0;
            try {
                $ob_lin  = floatval($c->query("SELECT COALESCE(SUM(debit_amount),0)  FROM sys_loan_ledger WHERE txn_date < '$from' AND description NOT LIKE '%মুনাফা%'")->fetchColumn());
                $ob_lout = floatval($c->query("SELECT COALESCE(SUM(credit_amount),0) FROM sys_loan_ledger WHERE txn_date < '$from'")->fetchColumn());
            } catch (Exception $e) {}

            $ob_dd = $ob_dw = 0;
            try {
                $ob_dd = floatval($c->query("SELECT COALESCE(SUM(deposit_amount),0)  FROM sys_dps_ledger WHERE txn_date < '$from' AND description NOT LIKE '%মুনাফা%' AND description NOT LIKE '%Opening%'")->fetchColumn());
                $ob_dw = floatval($c->query("SELECT COALESCE(SUM(withdraw_amount),0) FROM sys_dps_ledger WHERE txn_date < '$from'")->fetchColumn());
            } catch (Exception $e) {}

            $ob_card_in = $ob_card_out = 0;
            try {
                $ob_card_out = floatval($c->query("SELECT COALESCE(SUM(ABS(cash_impact)),0) FROM sys_card_ledger WHERE txn_date < '$from' AND cash_impact < 0")->fetchColumn());
                $ob_card_in  = floatval($c->query("SELECT COALESCE(SUM(cash_impact),0)      FROM sys_card_ledger WHERE txn_date < '$from' AND cash_impact > 0")->fetchColumn());
            } catch (Exception $e) {}

            return ($ob_sale + $ob_coll + $ob_cust + $ob_lin + $ob_dw + $ob_card_in)
                 - ($ob_exp  + $ob_sup  + $ob_staff + $ob_lout + $ob_dd + $ob_card_out);
        } catch (Exception $e) {
            return 0;
        }
    }

    // ────────────────────────────────────────────────────
    // Loan & DPS Outstanding
    // ────────────────────────────────────────────────────
    public function getLoanOutstanding(): float
    {
        try {
            return floatval($this->conn->query("SELECT COALESCE(SUM(current_balance),0) FROM sys_loans WHERE status='active'")->fetchColumn());
        } catch (Exception $e) { return 0; }
    }

    public function getDpsTotal(): float
    {
        try {
            return floatval($this->conn->query("SELECT COALESCE(SUM(total_balance),0) FROM sys_dps_accounts WHERE status='active'")->fetchColumn());
        } catch (Exception $e) { return 0; }
    }

    // ────────────────────────────────────────────────────
    // Daily Entries — একটি নির্দিষ্ট তারিখের সব ডেটা
    // ────────────────────────────────────────────────────
    public function getDayData(string $date): array
    {
        $c   = $this->conn;
        $day = [];

        // Report-linked entries
        $dr = $c->query("SELECT * FROM daily_reports WHERE report_date='$date'")->fetch();
        $day['sales'] = $day['colls'] = $day['exps'] = [];
        if ($dr) {
            $rid          = $dr['id'];
            $day['sales'] = $c->query("SELECT * FROM sales_entries WHERE report_id=$rid")->fetchAll();
            $day['colls'] = $c->query("SELECT * FROM collection_entries WHERE report_id=$rid")->fetchAll();
            $day['exps']  = $c->query("SELECT * FROM expense_entries WHERE report_id=$rid")->fetchAll();
        }

        // Customer & Supplier
        $day['custT'] = $c->query(
            "SELECT ct.*,c.customer_name,c.shop_name,c.phone FROM customer_transactions ct JOIN customers c ON ct.customer_id=c.id WHERE ct.tr_date='$date'"
        )->fetchAll();
        $day['supT']  = $c->query(
            "SELECT st.*,s.name,s.shop_name FROM supplier_transactions st JOIN suppliers s ON st.supplier_id=s.id WHERE st.tr_date='$date'"
        )->fetchAll();

        // Stock (old table & new table)
        $day['ostkT'] = [];
        try { $day['ostkT'] = $c->query("SELECT * FROM stocks_ WHERE tr_date='$date'")->fetchAll(); }
        catch (Exception $e) {}

        $day['nstkT'] = [];
        try { $day['nstkT'] = $c->query("SELECT * FROM stocks WHERE DATE(created_at)='$date'")->fetchAll(); }
        catch (Exception $e) {}

        // Staff
        $day['staffT'] = [];
        try {
            $day['staffT'] = $c->query(
                "SELECT se.*,s.staff_name name FROM staff_expenses se JOIN staff_info s ON se.staff_id=s.id WHERE se.expense_date='$date'"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // DPS Ledger
        $day['dpsT'] = [];
        try {
            $day['dpsT'] = $c->query(
                "SELECT l.*,a.client_name,a.account_number,a.account_type,a.id acc_id
                 FROM sys_dps_ledger l JOIN sys_dps_accounts a ON l.dps_id=a.id
                 WHERE l.txn_date='$date' ORDER BY l.id DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // Loan Ledger
        $day['loanT'] = [];
        try {
            $day['loanT'] = $c->query(
                "SELECT l.*,s.borrower_name,s.loan_category
                 FROM sys_loan_ledger l JOIN sys_loans s ON l.loan_id=s.id
                 WHERE l.txn_date='$date' ORDER BY l.id DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // Credit Card Ledger (OUT & IN)
        $day['cardOutT'] = $day['cardInT'] = [];
        try {
            $day['cardOutT'] = $c->query(
                "SELECT cl.*,cc.card_name,cc.card_last4 FROM sys_card_ledger cl
                 JOIN sys_credit_cards cc ON cl.card_id=cc.id
                 WHERE cl.txn_date='$date' AND cl.cash_impact < 0 ORDER BY cl.id DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
            $day['cardInT']  = $c->query(
                "SELECT cl.*,cc.card_name,cc.card_last4 FROM sys_card_ledger cl
                 JOIN sys_credit_cards cc ON cl.card_id=cc.id
                 WHERE cl.txn_date='$date' AND cl.cash_impact > 0 ORDER BY cl.id DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        return $day;
    }
}
