<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Interfaces/StockModelInterface.php';
require_once __DIR__ . '/../Models/Repositories/StockRepository.php';
require_once __DIR__ . '/../Services/StockImageService.php';

class StockController {
    private StockModelInterface $stockModel;
    private StockImageService $imageService;
    public string $userRole;
    public string $username;
    private string $currentPage;

    public function __construct(\PDO $dbConnection) {
        $this->stockModel = new StockRepository($dbConnection);
        $this->imageService = new StockImageService();
        $this->userRole = (string)($_SESSION['role'] ?? 'viewer');
        $this->username = (string)($_SESSION['username'] ?? 'User');
        $this->currentPage = $_SERVER['PHP_SELF'];

        if (empty($_SESSION['form_token'])) {
            $_SESSION['form_token'] = bin2hex(random_bytes(32));
        }
    }

    private function redirectWithError(string $message): void {
        echo "<script>alert('" . addslashes($message) . "'); window.location.href='" . $this->currentPage . "';</script>";
        exit;
    }

    public function handleRequests(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

            try {
                // CSRF Token Validation
                if (empty($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']) {
                    throw new \Exception("Security Violation: Invalid CSRF Token.");
                }

                // 1. Toggle Admin Locks
                if (isset($_POST['toggle_specific_lock']) && $this->userRole === 'admin') {
                    $target = (string)$_POST['toggle_specific_lock'];
                    $currentVal = (int)$_POST['current_state'];
                    $newVal = $currentVal ? 0 : 1;
                    $this->stockModel->toggleSystemLock($target, $newVal);
                    header("Location: " . $this->currentPage . "?status=lock_updated");
                    exit;
                }

                // 2. Save New Stock Entry
                if (isset($_POST['save_stock']) && $this->userRole !== 'viewer') {
                    $sysLocks = $this->stockModel->getSystemLocks();
                    if ($sysLocks['entry'] === 1 && $this->userRole !== 'admin') {
                        $this->redirectWithError('Entry Form is locked by Admin!');
                    }

                    $item = trim(htmlspecialchars((string)($_POST['item_name'] ?? '')));
                    $qty = (int)($_POST['qty'] ?? 0);
                    $bill = (float)($_POST['total_bill'] ?? 0);
                    $photoPath = null;

                    if (!empty($_POST['webcam_image'])) {
                        $photoPath = $this->imageService->saveBase64Image((string)$_POST['webcam_image']);
                        if ($photoPath === null) {
                            throw new \Exception("Invalid Image Format Provided.");
                        }
                    }

                    $this->stockModel->insertStockEntry($item, $qty, $bill, $photoPath, $this->username);
                    $_SESSION['form_token'] = bin2hex(random_bytes(32)); // Rotate Token
                    header("Location: " . $this->currentPage . "?status=success");
                    exit;
                }

                // 3. Admin Secure Delete
                if (isset($_POST['delete_id']) && $this->userRole === 'admin') {
                    $delPass = (string)($_POST['admin_pass'] ?? '');
                    if ($this->stockModel->verifyAdminActionPassword($this->username, $delPass)) {
                        $this->stockModel->deleteStockEntry((int)$_POST['delete_id']);
                        $_SESSION['form_token'] = bin2hex(random_bytes(32)); // Rotate Token
                        header("Location: " . $this->currentPage . "?status=deleted");
                        exit;
                    } else {
                        $this->redirectWithError('ভুল অ্যাকশন পাসওয়ার্ড!');
                    }
                }
            } catch (\Throwable $e) {
                $this->stockModel->logError("Controller Exception Caught", $e);
                $this->redirectWithError('System Error: Could not process request safely. Please try again.');
            }
        }
    }

    public function getViewData(): array {
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');

        if ($this->userRole === 'admin') {
            $fDate = !empty($_GET['from_date']) ? htmlspecialchars((string)$_GET['from_date']) : date('Y-m-01');
            $tDate = !empty($_GET['to_date']) ? htmlspecialchars((string)$_GET['to_date']) : date('Y-m-d');
        } else {
            $dayOfWeek = (int)date('w');
            $fDate = ($dayOfWeek === 6) ? date('Y-m-d') : date('Y-m-d', strtotime('last saturday'));
            $tDate = date('Y-m-d');
        }

        $sysLocks = $this->stockModel->getSystemLocks();
        $metrics = $this->stockModel->getAggregatedMetrics($currentDate, $currentMonth, $currentYear);

        $avgBuyPrice = $metrics['total_buy_qty'] > 0 ? ($metrics['total_buy_val'] / $metrics['total_buy_qty']) : 0;
        $curQty = $metrics['total_buy_qty'] - $metrics['total_sell_qty'];
        $stockValue = round($curQty * $avgBuyPrice);

        $totalNetProfit = round($metrics['total_sell_val'] - ($metrics['total_sell_qty'] * $avgBuyPrice));
        $todayNetProfit = round($metrics['today_sell_val'] - ($metrics['today_sell_qty'] * $avgBuyPrice));
        $monthNetProfit = round($metrics['month_sell_val'] - ($metrics['month_sell_qty'] * $avgBuyPrice));

        $historyList = $this->stockModel->getHistoryData($fDate, $tDate);

        $monthlyGrouped = [];
        foreach ($historyList as $item) {
            $monthKey = date('F Y', strtotime((string)$item['dt']));
            $monthlyGrouped[$monthKey][] = $item;
        }

        $dayOfWeekToday = (int)date('w');
        $lastSaturday = ($dayOfWeekToday === 6) ? date('Y-m-d') : date('Y-m-d', strtotime('last saturday'));
        $weeklyGrouped = [];
        foreach ($historyList as $item) {
            $itemDate = date('Y-m-d', strtotime((string)$item['dt']));
            if ($itemDate >= $lastSaturday && $itemDate <= $currentDate) {
                $weeklyGrouped[$itemDate][] = $item;
            }
        }

        return [
            'role' => $this->userRole,
            'username' => $this->username,
            'f_date' => $fDate,
            't_date' => $tDate,
            'sys_locks' => $sysLocks,
            'csrf_token' => $_SESSION['form_token'],
            'metrics' => [
                'cur_qty' => $curQty,
                'stock_value' => $stockValue,
                'total_sell_val' => $metrics['total_sell_val'],
                'month_net_profit' => $monthNetProfit,
                'today_net_profit' => $todayNetProfit,
                'avg_buy_price' => $avgBuyPrice,
                'total_buy_qty' => $metrics['total_buy_qty'],
                'today_sell_qty' => $metrics['today_sell_qty'],
                'today_add_qty' => $metrics['today_add_qty']
            ],
            'monthly_grouped' => $monthlyGrouped,
            'weekly_grouped' => $weeklyGrouped
        ];
    }
}
