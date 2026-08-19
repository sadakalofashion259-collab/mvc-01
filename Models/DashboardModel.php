<?php
declare(strict_types=1);

require_once __DIR__ . '/Interfaces/DashboardModelInterface.php';

class DashboardModel implements DashboardModelInterface {

    private \PDO   $dbConnection;
    private string $logFilePath;

    public function __construct(\PDO $dbConnection) {
        $this->dbConnection = $dbConnection;
        $this->logFilePath  = __DIR__ . '/../../Logs/error_log.txt';
    }

    /* ── Error Logger ─────────────────────────────────────────── */
    public function logError(string $message, \Throwable $exception): void {
        $timestamp  = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] DASHBOARD_ERROR: {$message}"
            . " | Details: "  . $exception->getMessage()
            . " | File: "     . $exception->getFile()
            . " | Line: "     . $exception->getLine()
            . PHP_EOL;
        error_log($logMessage, 3, $this->logFilePath);
    }

    /* ── User Profile Pic ─────────────────────────────────────── */
    public function getUserProfilePic(int $userId): string {
        try {
            $stmt = $this->dbConnection->prepare(
                "SELECT profile_pic FROM users WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['profile_pic'])) {
                return (string) $row['profile_pic'];
            }
            return 'default_user.png';
        } catch (\Throwable $e) {
            $this->logError("getUserProfilePic failed for id={$userId}", $e);
            return 'default_user.png';
        }
    }

    /* ── Next Memo Number ─────────────────────────────────────── */
    public function getNextMemoNumber(): int {
        try {
            $query = $this->dbConnection->query(
                "SELECT MAX(CAST(memo_no AS UNSIGNED)) AS max_memo FROM sales_entries"
            );
            $data = $query->fetch(\PDO::FETCH_ASSOC);
            return ($data && $data['max_memo']) ? ((int) $data['max_memo'] + 1) : 1;
        } catch (\Throwable $e) {
            $this->logError("getNextMemoNumber failed", $e);
            return 1;
        }
    }

    /* ── Active Customers ─────────────────────────────────────── */
    public function getActiveCustomers(): array {
        try {
            $query = $this->dbConnection->query(
                "SELECT id, customer_name, shop_name
                 FROM customers
                 WHERE is_active = 1
                 ORDER BY shop_name ASC"
            );
            return $query ? $query->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {
            $this->logError("getActiveCustomers failed", $e);
            return [];
        }
    }

    /* ── Today Collection Alerts ──────────────────────────────── */
    public function getTodayCollectionAlerts(string $todayDate): array {
        try {
            $stmt = $this->dbConnection->prepare(
                "SELECT id, shop_name, customer_name, phone, profile_pic
                 FROM customers
                 WHERE is_active = 1
                   AND (next_collection_date = ? OR next_collection_date_2 = ?)"
            );
            $stmt->execute([$todayDate, $todayDate]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->logError("getTodayCollectionAlerts failed for date={$todayDate}", $e);
            return [];
        }
    }

    /* ── Save Admin Notification ──────────────────────────────── */
    public function saveAdminNotification(int $userId, string $messageText): bool {
        try {
            $stmt = $this->dbConnection->prepare(
                "INSERT INTO notifications (user_id, message, status) VALUES (?, ?, 'pending')"
            );
            return $stmt->execute([$userId, $messageText]);
        } catch (\Throwable $e) {
            $this->logError("saveAdminNotification failed for userId={$userId}", $e);
            return false;
        }
    }

    /* ── Broadcast Notices ────────────────────────────────────── */
    public function getBroadcastNotices(int $limit = 3): array {
        try {
            $stmt = $this->dbConnection->prepare(
                "SELECT * FROM notifications
                 WHERE status = 'broadcast'
                 ORDER BY id DESC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->logError("getBroadcastNotices failed", $e);
            return [];
        }
    }

    /* ── Pending Notification Count ───────────────────────────── */
    public function getPendingNotificationCount(): int {
        try {
            $count = $this->dbConnection->query(
                "SELECT COUNT(*) FROM notifications WHERE status = 'pending'"
            )->fetchColumn();
            return (int) $count;
        } catch (\Throwable $e) {
            $this->logError("getPendingNotificationCount failed", $e);
            return 0;
        }
    }
}
