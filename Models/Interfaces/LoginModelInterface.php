<?php
declare(strict_types=1);

interface LoginModelInterface {
    public function findByIdentifier(string $identifier): ?array;
    public function updateLastLogin(int $userId): bool;
    public function incrementFailedAttempts(int $userId): bool;
    public function lockAccount(int $userId, string $lockUntil): bool;
    public function resetFailedAttempts(int $userId): bool;
    public function autoUnblockIfExpired(int $userId, string $blockEnd): bool;
    public function getSiteNotice(): string;
    public function getSliderPosts(): array;
    public function logError(string $message, \Throwable $exception): void;
}
