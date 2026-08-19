<?php
declare(strict_types=1);

require_once __DIR__ . '/Interfaces/BiometricModelInterface.php';

/**
 * BiometricModel — user_credentials টেবিলের ডাটা লেয়ার।
 * LoginModel এর মতোই PDO ব্যবহার করে (prepared statements)।
 */
class BiometricModel implements BiometricModelInterface
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function saveCredential(int $userId, string $credId, string $publicKey, int $signCount, string $label): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO user_credentials (user_id, credential_id, public_key, sign_count, device_label)
             VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$userId, $credId, $publicKey, $signCount, $label]);
    }

    public function getCredentialsByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, credential_id, device_label, created_at, last_used_at
               FROM user_credentials WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getByCredentialId(string $credId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, user_id, credential_id, public_key, sign_count
               FROM user_credentials WHERE credential_id = ? LIMIT 1"
        );
        $stmt->execute([$credId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateSignCount(string $credId, int $count): bool
    {
        $stmt = $this->db->prepare("UPDATE user_credentials SET sign_count = ? WHERE credential_id = ?");
        return $stmt->execute([$count, $credId]);
    }

    public function touchLastUsed(string $credId): bool
    {
        $stmt = $this->db->prepare("UPDATE user_credentials SET last_used_at = NOW() WHERE credential_id = ?");
        return $stmt->execute([$credId]);
    }

    public function deleteCredential(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_credentials WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    public function findUserById(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findUserByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
