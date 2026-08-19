<?php
declare(strict_types=1);

require_once MODULE_ROOT . '/Models/CreditCardModel.php';
require_once MODULE_ROOT . '/Models/LedgerModel.php';
require_once MODULE_ROOT . '/Services/Logger.php';

final class CardController
{
    private PDO $conn;
    private CreditCardModel $cards;
    private LedgerModel $ledger;
    private string $username;

    public function __construct(PDO $conn, string $username)
    {
        $this->conn     = $conn;
        $this->cards    = new CreditCardModel($conn);
        $this->ledger   = new LedgerModel($conn);
        $this->username = $username;
    }

    public function handle(): void
    {
        // AJAX
        if (isset($_POST['ajax_action'])) {
            $this->handleAjax();
            return;
        }

        // Form POST
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
            $this->handleForm();
            return;
        }

        // GET — list or view
        $this->renderPage();
    }

    private function verifyCsrf(): void
    {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            if (isset($_POST['ajax_action'])) {
                $this->json(['status' => 'error', 'message' => '❌ সিকিউরিটি টোকেন ইনভ্যালিড!']);
            }
            $_SESSION['error_msg'] = '❌ সিকিউরিটি টোকেন ইনভ্যালিড!';
            $this->redirect('');
        }
    }

    private function verifyAdminPass(string $pass): bool
    {
        try {
            $st = $this->conn->prepare("SELECT password FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
            $st->execute([$this->username]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            $stored = $row['password'];
            if ($stored === $pass) {
                return true;
            }
            if (md5($pass) === $stored) {
                return true;
            }
            if (password_verify($pass, $stored)) {
                return true;
            }
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function handleAjax(): void
    {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        $this->verifyCsrf();

        $pass = trim($_POST['pass'] ?? '');
        if (!$this->verifyAdminPass($pass)) {
            $this->json(['status' => 'error', 'message' => '❌ ভুল পাসওয়ার্ড!']);
        }

        $action = $_POST['ajax_action'] ?? '';
        try {
            switch ($action) {
                case 'unmask_card':
                    $cid = (int) ($_POST['card_id'] ?? 0);
                    $secrets = $this->cards->getDecryptedSecrets($cid);
                    $this->json(['status' => 'success'] + $secrets);

                case 'toggle_status':
                    $cid = (int) ($_POST['card_id'] ?? 0);
                    $this->cards->toggleStatus($cid);
                    $this->json(['status' => 'success', 'message' => '✅ স্ট্যাটাস পরিবর্তন হয়েছে।']);

                case 'delete_card':
                    $cid = (int) ($_POST['card_id'] ?? 0);
                    $this->cards->delete($cid);
                    $this->json(['status' => 'success', 'message' => '✅ কার্ড ডিলিট হয়েছে।']);

                case 'delete_ledger':
                    $lid = (int) ($_POST['ledger_id'] ?? 0);
                    $this->ledger->delete($lid);
                    $this->json(['status' => 'success', 'message' => '✅ এন্ট্রি ডিলিট হয়েছে।']);

                default:
                    $this->json(['status' => 'error', 'message' => 'অজানা অ্যাকশন']);
            }
        } catch (Throwable $e) {
            Logger::error('Ajax Error: ' . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'সার্ভার এরর!']);
        }
    }

    private function handleForm(): void
    {
        $this->verifyCsrf();
        $action = $_POST['action'] ?? '';

        try {
            switch ($action) {
                case 'add_card':
                    if (empty(trim($_POST['card_name'] ?? '')) || empty(trim($_POST['card_number'] ?? ''))) {
                        throw new RuntimeException('কার্ডের নাম ও নাম্বার দিতে হবে।');
                    }
                    $this->cards->create($_POST, $this->username);
                    $_SESSION['success_msg'] = '✅ নতুন ক্রেডিট কার্ড যোগ হয়েছে।';
                    $this->redirect('');

                case 'update_card':
                    $cid = (int) ($_POST['card_id'] ?? 0);
                    $this->cards->update($cid, $_POST);
                    $_SESSION['success_msg'] = '✅ কার্ড আপডেট হয়েছে।';
                    $this->redirect('?view=' . $cid);

                case 'add_transaction':
                    $cid = (int) ($_POST['card_id'] ?? 0);
                    $page = (int) ($_POST['current_page'] ?? 1);
                    $this->ledger->add($cid, $_POST, $this->username);
                    $_SESSION['success_msg'] = '✅ ট্রানজেকশন রেকর্ড হয়েছে।';
                    $this->redirect('?view=' . $cid . '&page=' . $page);

                case 'update_transaction':
                    $lid  = (int) ($_POST['ledger_id'] ?? 0);
                    $cid  = (int) ($_POST['card_id'] ?? 0);
                    $page = (int) ($_POST['current_page'] ?? 1);
                    $this->ledger->update($lid, $cid, $_POST);
                    $_SESSION['success_msg'] = '✅ ট্রানজেকশন আপডেট হয়েছে।';
                    $this->redirect('?view=' . $cid . '&page=' . $page);

                default:
                    throw new RuntimeException('অজানা অ্যাকশন');
            }
        } catch (Throwable $e) {
            Logger::error('Form Action Error: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'সার্ভার এরর! লগে দেখুন।';
            $this->redirect('');
        }
    }

    private function renderPage(): void
    {
        $success_msg = $_SESSION['success_msg'] ?? '';
        $error_msg   = $_SESSION['error_msg'] ?? '';
        unset($_SESSION['success_msg'], $_SESSION['error_msg']);

        $active_cards   = [];
        $inactive_cards = [];
        $g_total_due = $g_total_paid = $g_total_charge = $g_total_used = 0.0;

        $rows = $this->cards->getAll();
        foreach ($rows as $c) {
            $c['summary'] = $this->cards->getSummary($c);
            $c['light']   = $this->cards->getLight($c, $c['summary']['current_due']);
            if ($c['status'] === 'active') {
                $active_cards[] = $c;
                $g_total_due    += $c['summary']['current_due'];
                $g_total_paid   += $c['summary']['total_paid'];
                $g_total_charge += $c['summary']['total_charge'];
                $g_total_used   += $c['summary']['total_used'];
            } else {
                $inactive_cards[] = $c;
            }
        }

        $view_card     = null;
        $view_summary  = null;
        $view_light    = null;
        $grouped_ledger = [];
        $total_pages   = 1;
        $current_page  = 1;
        $vid           = 0;

        if (isset($_GET['view']) && intval($_GET['view']) > 0) {
            $vid = intval($_GET['view']);
            $view_card = $this->cards->find($vid);
            if ($view_card) {
                $view_summary = $this->cards->getSummary($view_card);
                $view_light   = $this->cards->getLight($view_card, $view_summary['current_due']);

                $all_cycles = $this->ledger->getCycles($vid);
                $total_pages = max(1, count($all_cycles));
                $current_page = isset($_GET['page']) ? max(1, min($total_pages, intval($_GET['page']))) : 1;

                if (!empty($all_cycles)) {
                    $active_cycle = $all_cycles[$current_page - 1];
                    $grouped_ledger[$active_cycle] = $this->ledger->getByCycle($vid, $active_cycle);
                }
            }
        }

        $type_labels = [
            'bill_pay'      => 'বিল পরিশোধ',
            'min_pay'       => 'মিনিমাম বিল পরিশোধ',
            'full_pay'      => 'ফুল পরিশোধ',
            'charge_pay'    => 'চার্জ পরিশোধ',
            'cash_advance'  => 'ক্যাশ অ্যাডভান্স',
            'purchase'      => 'কেনাকাটা',
        ];

        $csrf = $_SESSION['csrf_token'];
        $moduleUrl = MODULE_URL;

        // Extract for views
        extract(compact(
            'success_msg', 'error_msg',
            'active_cards', 'inactive_cards',
            'g_total_due', 'g_total_paid', 'g_total_charge', 'g_total_used',
            'view_card', 'view_summary', 'view_light', 'grouped_ledger',
            'total_pages', 'current_page', 'vid',
            'type_labels', 'csrf', 'moduleUrl'
        ));

        require MODULE_ROOT . '/Views/layout.php';
    }

    private function json(array $data): never
    {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function redirect(string $query): never
    {
        $url = MODULE_URL . '/index.php' . $query;
        // PRG — ব্যাক বাটনে পুরনো POST/সাবমিট যেন না আসে
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Location: ' . $url, true, 303);
        exit;
    }
}
