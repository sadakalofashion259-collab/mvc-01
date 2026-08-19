<?php
/**
 * ========================================================
 * HistorysController — বিজনেস লজিক লেয়ার
 * AJAX request ও page render উভয় handle করে।
 * ========================================================
 */
class HistorysController
{
    private PDO           $conn;
    private HistorysModel $model;
    private string        $role;
    private string        $sessUsr;

    public function __construct(PDO $conn)
    {
        $this->conn    = $conn;
        $this->model   = new HistorysModel($conn);
        $this->role    = $_SESSION['role']            ?? '';
        $this->sessUsr = $_SESSION['username']        ?? '';
    }

    // ────────────────────────────────────────────────────
    // dispatch — POST = AJAX, GET = page
    // ────────────────────────────────────────────────────
    public function dispatch(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
            $this->handleAjax();
            return;
        }
        $this->renderPage();
    }

    // ────────────────────────────────────────────────────
    // AJAX Dispatcher
    // ────────────────────────────────────────────────────
    private function handleAjax(): void
    {
        ob_clean();
        header('Content-Type: application/json');

        $action = $_POST['ajax_action'];
        $pass   = trim($_POST['pass'] ?? '');

        // ── পাসওয়ার্ড যাচাই (সব AJAX-এ) ──
        if (!$this->model->verifyAdminPass($this->sessUsr, $pass)) {
            echo json_encode(['status' => 'error', 'message' => '❌ ভুল পাসওয়ার্ড! ডাটাবেজ যাচাই ব্যর্থ হয়েছে।']);
            exit;
        }

        // ── Role চেক ──
        if ($this->role !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'অনুমতি নেই।']);
            exit;
        }

        try {
            switch ($action) {
                case 'item_action':
                    $this->ajaxItemAction();
                    break;
                case 'delete_dps':
                    $this->ajaxDeleteDps();
                    break;
                case 'delete_loan':
                    $this->ajaxDeleteLoan();
                    break;
                case 'delete_card_ledger':
                    $this->ajaxDeleteCardLedger();
                    break;
                default:
                    echo json_encode(['status' => 'error', 'message' => 'অজানা অনুরোধ।']);
            }
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'এরর: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Item Action (edit/delete) ────────────────────────
    private function ajaxItemAction(): void
    {
        $type  = $_POST['type']  ?? '';
        $table = $_POST['table'] ?? '';
        $id    = intval($_POST['id'] ?? 0);
        $field = $_POST['field'] ?? '';
        $val   = $_POST['val']   ?? '';

        $allowed = [
            'sales_entries', 'expense_entries', 'supplier_transactions',
            'stocks', 'stock_entries', 'customer_transactions',
            'collection_entries', 'staff_expenses'
        ];
        if (!in_array($table, $allowed)) {
            echo json_encode(['status' => 'error', 'message' => 'অনুমোদিত নয়।']);
            return;
        }

        if ($type === 'delete') {
            $this->model->deleteItem($table, $id);
            echo json_encode(['status' => 'success', 'message' => '✅ এন্ট্রি ডিলিট হয়েছে।']);
        } elseif ($type === 'edit') {
            $this->model->editItem($table, $field, $val, $id);
            echo json_encode(['status' => 'success', 'message' => '✅ এন্ট্রি আপডেট হয়েছে।']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'অজানা type।']);
        }
    }

    // ── DPS Delete ───────────────────────────────────────
    private function ajaxDeleteDps(): void
    {
        $lid    = intval($_POST['id'] ?? 0);
        $result = $this->model->deleteDpsEntry($lid);
        echo json_encode([
            'status'  => $result['ok'] ? 'success' : 'error',
            'message' => $result['msg'],
        ]);
    }

    // ── Loan Delete ──────────────────────────────────────
    private function ajaxDeleteLoan(): void
    {
        $lid    = intval($_POST['id'] ?? 0);
        $result = $this->model->deleteLoanEntry($lid);
        echo json_encode([
            'status'  => $result['ok'] ? 'success' : 'error',
            'message' => $result['msg'],
        ]);
    }

    // ── Card Ledger Delete ───────────────────────────────
    private function ajaxDeleteCardLedger(): void
    {
        $lid    = intval($_POST['id'] ?? 0);
        $result = $this->model->deleteCardLedgerEntry($lid);
        echo json_encode([
            'status'  => $result['ok'] ? 'success' : 'error',
            'message' => $result['msg'],
        ]);
    }

    // ────────────────────────────────────────────────────
    // Page Render
    // ────────────────────────────────────────────────────
    private function renderPage(): void
    {
        // ── Session Messages ──
        $success_msg = null;
        $error_msg   = null;
        if (isset($_SESSION['success_msg'])) { $success_msg = $_SESSION['success_msg']; unset($_SESSION['success_msg']); }
        if (isset($_SESSION['error_msg']))   { $error_msg   = $_SESSION['error_msg'];   unset($_SESSION['error_msg']); }

        // ── Date Range ──
        $role = $this->role;
        if ($role === 'manager' || $role === 'user') {
            $from_date = date('Y-m-d');
            $to_date   = date('Y-m-d');
        } else {
            $from_date = (isset($_GET['from_date']) && $_GET['from_date'] !== '')
                ? $_GET['from_date'] : date('Y-m-01');
            $to_date   = (isset($_GET['to_date']) && $_GET['to_date'] !== '')
                ? $_GET['to_date'] : date('Y-m-d');
        }

        // ── Model Data ──
        $all_dates       = $this->model->getActiveDates($from_date, $to_date);
        $total_dates     = count($all_dates);
        $summary         = $this->model->getPeriodSummary($from_date, $to_date);
        $ob              = $this->model->getOpeningBalance($from_date);
        $closing         = $ob + $summary['net_cash'];
        $loan_outstanding= $this->model->getLoanOutstanding();
        $dps_total       = $this->model->getDpsTotal();

        // ── প্রতিটি তারিখের ডেটা একসাথে প্রস্তুত (View-এ $dayDataMap হিসেবে যাবে) ──
        $dayDataMap = [];
        foreach ($all_dates as $date) {
            $dayDataMap[$date] = $this->model->getDayData($date);
        }

        $card_type_labels = [
            'bill_pay'     => 'বিল পরিশোধ',
            'min_pay'      => 'মিনিমাম বিল',
            'full_pay'     => 'ফুল পরিশোধ',
            'charge_pay'   => 'চার্জ পরিশোধ',
            'cash_advance' => 'ক্যাশ অ্যাডভান্স',
            'purchase'     => 'কেনাকাটা',
        ];

        // ── Render View ──
        require_once __DIR__ . '/../Views/layout/header.php';
        require_once __DIR__ . '/../Views/historys_view.php';
        require_once __DIR__ . '/../Views/layout/footer.php';
    }
}
