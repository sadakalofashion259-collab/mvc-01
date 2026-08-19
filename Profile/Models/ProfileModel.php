<?php
declare(strict_types=1);

require_once __DIR__ . '/Interfaces/ProfileModelInterface.php';

class ProfileModel implements ProfileModelInterface {

    private \PDO   $db;
    private string $logFilePath;

    public function __construct(\PDO $db) {
        $this->db          = $db;
        $this->logFilePath = __DIR__ . '/../Logs/error_log.txt';
    }

    /* ── Error Logger ─────────────────────────────────────────── */
    public function logError(string $message, \Throwable $e): void {
        $ts  = date('Y-m-d H:i:s');
        $log = "[{$ts}] PROFILE_ERROR: {$message}"
             . " | " . $e->getMessage()
             . " | File: " . $e->getFile()
             . " | Line: " . $e->getLine()
             . PHP_EOL;
        error_log($log, 3, $this->logFilePath);
    }

    /* ── Get User By ID ───────────────────────────────────────── */
    public function getUserById(int $userId): ?array {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, username, email, phone, mobile, address,
                        role, status, profile_pic, joining_date
                 FROM users
                 WHERE id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->logError("getUserById failed for id={$userId}", $e);
            return null;
        }
    }

    /* ── Check Email Taken ────────────────────────────────────── */
    public function isEmailTaken(string $email, int $excludeUserId): bool {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM users
                 WHERE email = :email AND id != :id"
            );
            $stmt->execute([':email' => $email, ':id' => $excludeUserId]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            $this->logError("isEmailTaken failed for email={$email}", $e);
            return false;
        }
    }

    /* ── Verify Current Password ──────────────────────────────── */
    public function verifyCurrentPassword(int $userId, string $plainPassword): bool {
        try {
            $stmt = $this->db->prepare(
                "SELECT password FROM users WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return false;
            return password_verify($plainPassword, (string) $row['password']);
        } catch (\Throwable $e) {
            $this->logError("verifyCurrentPassword failed for id={$userId}", $e);
            return false;
        }
    }

    /* ── Update Basic Info ────────────────────────────────────── */
    public function updateBasicInfo(
        int    $userId,
        string $email,
        string $phone,
        string $mobile,
        string $address
    ): bool {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users
                 SET email   = :email,
                     phone   = :phone,
                     mobile  = :mobile,
                     address = :address
                 WHERE id = :id"
            );
            return $stmt->execute([
                ':email'   => $email,
                ':phone'   => $phone,
                ':mobile'  => $mobile,
                ':address' => $address,
                ':id'      => $userId,
            ]);
        } catch (\Throwable $e) {
            $this->logError("updateBasicInfo failed for id={$userId}", $e);
            return false;
        }
    }

    /* ── Change Password ──────────────────────────────────────── */
    public function changePassword(int $userId, string $newPasswordHash): bool {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users SET password = :hash WHERE id = :id"
            );
            return $stmt->execute([':hash' => $newPasswordHash, ':id' => $userId]);
        } catch (\Throwable $e) {
            $this->logError("changePassword failed for id={$userId}", $e);
            return false;
        }
    }

    /* ── Update Profile Picture ───────────────────────────────── */
    public function updateProfilePicture(int $userId, string $picturePath): bool {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users SET profile_pic = :pic WHERE id = :id"
            );
            return $stmt->execute([':pic' => $picturePath, ':id' => $userId]);
        } catch (\Throwable $e) {
            $this->logError("updateProfilePicture failed for id={$userId}", $e);
            return false;
        }
    }
}
