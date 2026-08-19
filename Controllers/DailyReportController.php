<?php
declare(strict_types=1);

class DailyReportController {
    private DailyReportModelInterface $model;

    public function __construct(DailyReportModelInterface $model) {
        $this->model = $model;
    }

    public function logSystemError(Throwable $e): void {
        $logDir = __DIR__ . '/../Logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
        $logFile = $logDir . '/error_log.txt';
        $message = "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
        @error_log($message, 3, $logFile);
    }

    public function handleAjaxRequest(string $role): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajax_action'])) return;
        
        ob_clean();
        header('Content-Type: application/json');

        if ($role !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => '❌ শুধুমাত্র অ্যাডমিন ডিলিট করতে পারবে!']);
            exit;
        }

        $clientToken = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $clientToken)) {
            echo json_encode(['status' => 'error', 'message' => '❌ সিকিউরিটি টোকেন (CSRF) অবৈধ!']);
            exit;
        }

        $action = (string)$_POST['ajax_action'];
        $pass   = trim((string)($_POST['pass'] ?? ''));

        if ($pass !== '@2255') {
            echo json_encode(['status' => 'error', 'message' => '❌ ভুল পাসওয়ার্ড!']); 
            exit;
        }

        try {
            if ($action === 'delete_entry') {
                $table = (string) ($_POST['table'] ?? '');
                $id    = (int) ($_POST['id'] ?? 0);

                $allowed_tables = ['sales_entries', 'expense_entries', 'supplier_transactions', 'customer_transactions', 'collection_entries'];
                if (!in_array($table, $allowed_tables, true)) {
                    throw new Exception("ডাটাবেজ টেবিল অনুমোদিত নয়।");
                }

                $this->model->deleteEntry($table, $id);
                echo json_encode(['status' => 'success', 'message' => '✅ এন্ট্রি ডিলিট হয়েছে।']); 
                exit;
            }
        } catch (Throwable $e) {
            $this->logSystemError($e);
            echo json_encode(['status' => 'error', 'message' => '❌ সার্ভার এরর হয়েছে।']); 
            exit;
        }
    }

    public function getPageData(string $role): array {
        $is_search = false;
        $search = '';

        if ($role === 'admin') {
            $from_date = $_GET['from_date'] ?? date('Y-m-d');
            $to_date   = $_GET['to_date'] ?? date('Y-m-d');
            $date_input_state = '';
        } else {
            $from_date = date('Y-m-d');
            $to_date   = date('Y-m-d');
            $date_input_state = 'readonly';
        }

        if (!empty($_GET['search'])) {
            $search = trim($_GET['search']);
            $is_search = true;
            $filter_msg = "সার্চ রেজাল্ট: '" . htmlspecialchars($search) . "' (সব তারিখ)";
        } else {
            $filter_msg = "তারিখ: " . date('d M, y', strtotime($from_date)) . " হতে " . date('d M, y', strtotime($to_date));
        }

        try {
            $reports = $this->model->getReports($from_date, $to_date, $search);
            $staffSummary = ($role !== 'admin') ? $this->model->getStaff7DaysSummary() : [];
        } catch (Throwable $e) {
            $this->logSystemError($e);
            $reports = ['sales'=>[], 'colls'=>[], 'exps'=>[], 'sup_pay'=>[], 'cust_trans'=>[]];
            $staffSummary = [];
        }

        $gt_sale_cash = 0.0; $gt_coll = 0.0; $gt_cust_rcv = 0.0; $gt_exp = 0.0; $gt_sup_pay = 0.0;
        foreach($reports['sales'] as $i) $gt_sale_cash += (float)($i['paid_amount'] ?? 0);
        foreach($reports['colls'] as $i) $gt_coll += (float)($i['total_deposit'] ?? 0);
        foreach($reports['cust_trans'] as $i) $gt_cust_rcv += (float)($i['received_amount'] ?? 0);
        foreach($reports['exps'] as $i) $gt_exp += (float)($i['amount'] ?? 0);
        foreach($reports['sup_pay'] as $i) $gt_sup_pay += (float)($i['payment_given'] ?? 0);
        $net_cash = ($gt_sale_cash + $gt_coll + $gt_cust_rcv) - ($gt_exp + $gt_sup_pay);

        return [
            'role' => $role,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'date_input_state' => $date_input_state,
            'is_search' => $is_search,
            'search' => $search,
            'filter_msg' => $filter_msg,
            'reports' => $reports,
            'staffSummary' => $staffSummary,
            'gt_sale_cash' => $gt_sale_cash,
            'gt_coll' => $gt_coll,
            'gt_cust_rcv' => $gt_cust_rcv,
            'gt_exp' => $gt_exp,
            'gt_sup_pay' => $gt_sup_pay,
            'net_cash' => $net_cash
        ];
    }
}