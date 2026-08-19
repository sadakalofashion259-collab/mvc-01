<?php
declare(strict_types=1);

interface DashboardModelInterface {
    public function getNextMemoNumber(): int;
    public function getActiveCustomers(): array;
    public function saveAdminNotification(int $userId, string $messageText): bool;
    public function getBroadcastNotices(int $limit = 3): array;
    public function getPendingNotificationCount(): int;
    public function getTodayCollectionAlerts(string $todayDate): array;
    public function getUserProfilePic(int $userId): string;
    public function logError(string $message, \Throwable $exception): void;
}
?>
