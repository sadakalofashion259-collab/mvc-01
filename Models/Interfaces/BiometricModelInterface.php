<?php
declare(strict_types=1);

interface BiometricModelInterface
{
    public function saveCredential(int $userId, string $credId, string $publicKey, int $signCount, string $label): bool;
    /** @return array<int,array<string,mixed>> */
    public function getCredentialsByUser(int $userId): array;
    /** @return array<string,mixed>|null */
    public function getByCredentialId(string $credId): ?array;
    public function updateSignCount(string $credId, int $count): bool;
    public function touchLastUsed(string $credId): bool;
    public function deleteCredential(int $id, int $userId): bool;
    public function findUserById(int $userId): ?array;
    public function findUserByUsername(string $username): ?array;
}
