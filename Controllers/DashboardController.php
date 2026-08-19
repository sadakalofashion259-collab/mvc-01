<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/DashboardModel.php';

class DashboardController {

    private DashboardModelInterface $dashboardModel;

    public readonly string $userRole;
    public readonly int    $userId;
    public readonly string $userName;

    public function __construct(\PDO $dbConnection) {
        $this->dashboardModel = new DashboardModel($dbConnection);
        $this->bootSessionAndSecurity();
        date_default_timezone_set('Asia/Dhaka');

        $this->userRole = (string) ($_SESSION['role']     ?? 'viewer');
        $this->userId   = (int)    ($_SESSION['user_id']  ?? 0);
        $this->userName = (string) ($_SESSION['username'] ?? '');

        // Session-এ profile_pic না থাকলে DB থেকে একবারই লোড
        $this->syncProfilePicToSession();
    }

    /* ── Session & Security ───────────────────────────────────── */
    private function bootSessionAndSecurity(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
            session_unset();
            session_destroy();
            echo "<script>alert('Session Expired! Auto Logout.'); window.location.href='index.php';</script>";
            exit;
        }
        $_SESSION['last_activity'] = time();

        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            echo "<script>window.location.href='index.php';</script>";
            exit;
        }
    }

    /* ── Profile Pic Session Sync ─────────────────────────────── */
    private function syncProfilePicToSession(): void {
        if (empty($_SESSION['profile_pic']) && $this->userId > 0) {
            $_SESSION['profile_pic'] = $this->dashboardModel->getUserProfilePic($this->userId);
        }
    }

    /* ── CSRF Token ───────────────────────────────────────────── */
    public function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_dashboard_token'])) {
            $_SESSION['csrf_dashboard_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_dashboard_token'];
    }

    private function verifyCsrfToken(): bool {
        $incoming = trim((string) ($_POST['csrf_token'] ?? ''));
        $stored   = (string) ($_SESSION['csrf_dashboard_token'] ?? '');
        return $stored !== '' && hash_equals($stored, $incoming);
    }

    /* ── Handle POST Requests ─────────────────────────────────── */
    public function handlePostRequests(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        if (isset($_POST['send_msg_to_admin'])) {
            if (!$this->verifyCsrfToken()) {
                $_SESSION['error_msg'] = '❌ সিকিউরিটি টোকেন অবৈধ।';
                header('Location: dashboard.php');
                exit;
            }
            $rawMessage = trim((string) ($_POST['msg_text'] ?? ''));
            if ($rawMessage !== '') {
                $sanitizedMessage = htmlspecialchars($rawMessage, ENT_QUOTES, 'UTF-8');
                $isSuccess = $this->dashboardModel->saveAdminNotification($this->userId, $sanitizedMessage);
                if ($isSuccess) {
                    $_SESSION['success_msg'] = '✅ Message sent to Admin!';
                } else {
                    $_SESSION['error_msg'] = '❌ System Error: Message sending failed.';
                }
            }
            header('Location: dashboard.php');
            exit;
        }
    }

    /* ── Get View Data ────────────────────────────────────────── */
    public function getViewData(): array {
        $todayDate             = date('Y-m-d');
        $adminBroadcastNotices = $this->dashboardModel->getBroadcastNotices();
        $collectionAlerts      = $this->dashboardModel->getTodayCollectionAlerts($todayDate);
        $notificationBadgeHtml = '';

        if ($this->userRole === 'admin') {
            $pendingCount = $this->dashboardModel->getPendingNotificationCount();
            if ($pendingCount > 0) {
                $notificationBadgeHtml = "<span class='nav-badge'>{$pendingCount}</span>";
            }
        } else {
            $noticeCount = count($adminBroadcastNotices);
            if ($noticeCount > 0) {
                $notificationBadgeHtml = "<span class='nav-badge'>{$noticeCount}</span>";
            }
        }

        // Session থেকে profile_pic (syncProfilePicToSession ইতিমধ্যে set করেছে)
        $rawProfilePic  = (string) ($_SESSION['profile_pic'] ?? 'default_user.png');
        $safeProfilePic = htmlspecialchars($rawProfilePic, ENT_QUOTES, 'UTF-8');

        return [
            'nextMemoNumber'        => $this->dashboardModel->getNextMemoNumber(),
            'activeCustomersList'   => $this->dashboardModel->getActiveCustomers(),
            'collectionAlerts'      => $collectionAlerts,
            'adminBroadcastNotices' => $adminBroadcastNotices,
            'notificationBadgeHtml' => $notificationBadgeHtml,
            'userRole'              => $this->userRole,
            'userName'              => htmlspecialchars($this->userName, ENT_QUOTES, 'UTF-8'),
            'userProfilePic'        => $safeProfilePic,
            'csrfToken'             => $this->generateCsrfToken(),
        ];
    }
}
