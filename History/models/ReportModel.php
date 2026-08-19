<?php
declare(strict_types=1);

require_once __DIR__ . '/SalesModel.php';
require_once __DIR__ . '/CollectionModel.php';
require_once __DIR__ . '/ExpenseModel.php';
require_once __DIR__ . '/CustomerTransactionModel.php';
require_once __DIR__ . '/SupplierTransactionModel.php';
require_once __DIR__ . '/StockModel.php';
require_once __DIR__ . '/StaffExpenseModel.php';
require_once __DIR__ . '/DpsModel.php';
require_once __DIR__ . '/LoanModel.php';
require_once __DIR__ . '/CardModel.php';

class ReportModel
{
    private PDO $db;
    private string $from;
    private string $to;
    private SalesModel $salesModel;
    private CollectionModel $collectionModel;
    private ExpenseModel $expenseModel;
    private CustomerTransactionModel $customerModel;
    private SupplierTransactionModel $supplierModel;
    private StockModel $stockModel;
    private StaffExpenseModel $staffModel;
    private DpsModel $dpsModel;
    private LoanModel $loanModel;
    private CardModel $cardModel;

    public function __construct(PDO $db, string $from, string $to)
    {
        $this->db = $db;
        $this->from = $from;
        $this->to = $to;

        // সকল মডেল ইনস্ট্যান্স
        $this->salesModel = new SalesModel($db);
        $this->collectionModel = new CollectionModel($db);
        $this->expenseModel = new ExpenseModel($db);
        $this->customerModel = new CustomerTransactionModel($db);
        $this->supplierModel = new SupplierTransactionModel($db);
        $this->stockModel = new StockModel($db);
        $this->staffModel = new StaffExpenseModel($db);
        $this->dpsModel = new DpsModel($db);
        $this->loanModel = new LoanModel($db);
        $this->cardModel = new CardModel($db);
    }

    public function getAllData(): array
    {
        $data = [];

        // ১. ইউনিক ডেটস
        $data['all_dates'] = $this->getUniqueDates();

        // ২. পিরিয়ড সামারি
        $data['gt_sale_cash'] = $this->salesModel->getPeriodSum($this->from, $this->to, 'paid_amount');
        $data['gt_sale_due']  = $this->salesModel->getPeriodSum($this->from, $this->to, 'due_amount');
        $data['gt_coll']      = $this->collectionModel->getPeriodSum($this->from, $this->to);
        $data['gt_exp']       = $this->expenseModel->getPeriodSum($this->from, $this->to);
        $data['gt_cust_rcv']  = $this->customerModel->getPeriodSum($this->from, $this->to);
        $data['gt_sup_pay']   = $this->supplierModel->getPeriodSum($this->from, $this->to);
        $data['gt_staff']     = $this->staffModel->getPeriodSum($this->from, $this->to);

        // ৩. লোন, DPS, কার্ড সামারি
        $data['gt_loan_in']  = $this->loanModel->getPeriodIn($this->from, $this->to);
        $data['gt_loan_out'] = $this->loanModel->getPeriodOut($this->from, $this->to);
        $data['loan_outstanding'] = $this->loanModel->getOutstanding();

        $data['gt_dps_dep'] = $this->dpsModel->getPeriodDeposit($this->from, $this->to);
        $data['gt_dps_wth'] = $this->dpsModel->getPeriodWithdraw($this->from, $this->to);
        $data['dps_total']  = $this->dpsModel->getTotal();

        $cardData = $this->cardModel->getPeriodSummary($this->from, $this->to);
        $data = array_merge($data, $cardData);

        // ৪. Opening ও Closing
        $data['ob']        = $this->calculateOpeningBalance();
        $data['total_in']  = $this->calculateTotalIn($data);
        $data['total_out'] = $this->calculateTotalOut($data);
        $data['net_cash']  = $data['total_in'] - $data['total_out'];
        $data['closing']   = $data['ob'] + $data['net_cash'];

        // ৫. প্রতিদিনের ডিটেইল ডাটা
        $data['daily_data'] = $this->getDailyDetailedData($data['all_dates']);

        return $data;
    }

    private function getUniqueDates(): array
    {
        // FIX: sales_entries / collection_entries / expense_entries যোগ করা হলো।
        // আগে শুধু daily_reports থেকে date আসতো, তাই কোনো দিনে daily_reports row না থাকলে
        // সেই দিনের sales/collection/expense লুকিয়ে যেত।
        // sales/collection/expense dates come via daily_reports.report_date (report_id link)
        // — same as original history.php (no tr_date on those tables)
        $tables = [
            'daily_reports'          => 'report_date',
            'customer_transactions'  => 'tr_date',
            'supplier_transactions'  => 'tr_date',
            'stock_entries'          => 'tr_date',
            'stocks'                 => 'created_at',
            'staff_expenses'         => 'expense_date',
            'sys_dps_ledger'         => 'txn_date',
            'sys_loan_ledger'        => 'txn_date',
            'sys_card_ledger'        => 'txn_date',
        ];
        $dates = [];
        foreach ($tables as $table => $col) {
            try {
                // FIX: raw interpolation বাদ দিয়ে prepared statement
                $stmt = $this->db->prepare("SELECT DISTINCT {$col} AS d FROM {$table} WHERE {$col} BETWEEN ? AND ?");
                $stmt->execute([$this->from, $this->to]);
                $res = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($res as $d) {
                    if ($d === null || $d === '') continue;
                    if ($table === 'stocks') {
                        $d = explode(' ', (string)$d)[0]; // datetime → date
                    }
                    $dates[] = $d;
                }
            } catch (Exception $e) {}
        }
        $dates = array_values(array_unique($dates));
        rsort($dates);
        return $dates;
    }

    private function calculateOpeningBalance(): float
    {
        $ob = 0.0;
        try {
            $ob += $this->salesModel->getPrePeriodSum($this->from, 'paid_amount');
            $ob += $this->collectionModel->getPrePeriodSum($this->from);
            $ob -= $this->expenseModel->getPrePeriodSum($this->from);
            $ob += $this->customerModel->getPrePeriodSum($this->from);
            $ob -= $this->supplierModel->getPrePeriodSum($this->from);
            $ob -= $this->staffModel->getPrePeriodSum($this->from);
            $ob += $this->loanModel->getPrePeriodIn($this->from);
            $ob -= $this->loanModel->getPrePeriodOut($this->from);
            $ob -= $this->dpsModel->getPrePeriodDeposit($this->from);
            $ob += $this->dpsModel->getPrePeriodWithdraw($this->from);
            $ob += $this->cardModel->getPrePeriodIn($this->from);
            $ob -= $this->cardModel->getPrePeriodOut($this->from);
        } catch (Exception $e) {}
        return $ob;
    }

    private function calculateTotalIn(array $data): float
    {
        return $data['gt_sale_cash'] + $data['gt_coll'] + $data['gt_cust_rcv']
             + $data['gt_loan_in'] + $data['gt_dps_wth'] + ($data['gt_card_advance'] ?? 0);
    }

    private function calculateTotalOut(array $data): float
    {
        return $data['gt_exp'] + $data['gt_sup_pay'] + $data['gt_staff']
             + $data['gt_loan_out'] + $data['gt_dps_dep'] + ($data['gt_card_cash_out'] ?? 0);
    }

    private function getDailyDetailedData(array $dates): array
    {
        $daily = [];
        foreach ($dates as $date) {
            $daily[$date] = [
                'sales'   => $this->salesModel->getByDate($date),
                'colls'   => $this->collectionModel->getByDate($date),
                'exps'    => $this->expenseModel->getByDate($date),
                'custT'   => $this->customerModel->getByDate($date),
                'supT'    => $this->supplierModel->getByDate($date),
                'staffT'  => $this->staffModel->getByDate($date),
                'dpsT'    => $this->dpsModel->getByDate($date),
                'loanT'   => $this->loanModel->getByDate($date),
                'cardOut' => $this->cardModel->getByDate($date, 'out'),
                'cardIn'  => $this->cardModel->getByDate($date, 'in'),
                'stocks'  => $this->stockModel->getByDate($date),
                'ostkT'   => $this->stockModel->getByDateOld($date),
            ];
            $daily[$date]['report_id'] = $this->getDailyReportId($date);
        }
        return $daily;
    }

    private function getDailyReportId(string $date): ?int
    {
        // FIX: prepared statement
        $stmt = $this->db->prepare("SELECT id FROM daily_reports WHERE report_date = ?");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    // ---- CRUD অপারেশন (সকল টেবিলের জন্য) ----

    private const ALLOWED_TABLES = [
        'sales_entries','expense_entries','supplier_transactions','stocks','stock_entries',
        'customer_transactions','collection_entries','staff_expenses',
    ];

    // FIX: প্রতি টেবিলে কোন কোন কলাম এডিট করা যাবে তার whitelist।
    // আগে $field সরাসরি SQL-এ বসতো → SQL injection / যেকোনো কলাম আপডেটের ঝুঁকি ছিল।
    private const ALLOWED_FIELDS = [
        'sales_entries'         => ['total_bill','paid_amount','due_amount','quantity'],
        'expense_entries'       => ['amount','description'],
        'supplier_transactions' => ['bill_received','payment_given','quantity'],
        'stocks'                => ['in_qty','out_qty','total_bill','description'],
        'stock_entries'         => ['stock_in','stock_out','total_bill','description'],
        'customer_transactions' => ['bill_amount','received_amount','description'],
        'collection_entries'    => ['cash_amount','bkash_amount','total_deposit'],
        'staff_expenses'        => ['amount','note'],
    ];

    public function deleteGenericEntry(string $table, int $id): void
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            throw new Exception('অনুমোদিত টেবিল নয়');
        }

        $col = null;
        if (in_array($table, ['sales_entries','expense_entries','supplier_transactions'], true)) $col = 'photo';
        elseif ($table === 'customer_transactions') $col = 'image_path';
        elseif (in_array($table, ['stocks','stock_entries'], true)) $col = 'image';

        if ($col) {
            $stmt = $this->db->prepare("SELECT {$col} FROM {$table} WHERE id = ?");
            $stmt->execute([$id]);
            $file = $stmt->fetchColumn();
            if (!empty($file) && is_file($file)) @unlink($file);
        }

        $stmt = $this->db->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function updateGenericEntry(string $table, int $id, string $field, $value): void
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            throw new Exception('অনুমোদিত টেবিল নয়');
        }
        if (!in_array($field, self::ALLOWED_FIELDS[$table] ?? [], true)) {
            throw new Exception('অনুমোদিত ফিল্ড নয়');
        }
        $stmt = $this->db->prepare("UPDATE {$table} SET {$field} = ? WHERE id = ?");
        $stmt->execute([$value, $id]);
    }
}
