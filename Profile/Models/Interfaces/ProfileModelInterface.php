<?php
declare(strict_types=1);

interface ProfileModelInterface {
    public function getUserById(int $userId): ?array;
    public function isEmailTaken(string $email, int $excludeUserId): bool;
    public function verifyCurrentPassword(int $userId, string $plainPassword): bool;
    public function updateBasicInfo(int $userId, string $email, string $phone, string $mobile, string $address): bool;
    public function changePassword(int $userId, string $newPasswordHash): bool;
    public function updateProfilePicture(int $userId, string $picturePath): bool;
    public function logError(string $message, \Throwable $exception): void;
}
