<?php
declare(strict_types=1);

require_once __DIR__ . '/Interfaces/LoginModelInterface.php';

class LoginModel implements LoginModelInterface {

    private \PDO   $db;
    private string $logFilePath;

    private const LOCK_DURATION_MINUTES = 30;
    private const MAX_FAILED_ATTEMPTS   = 3;

    public function __construct(\PDO $db) {
        $this->db          = $db;
        $this->logFilePath = __DIR__ . '/../../Logs/error_log.txt';
    }

    public function logError(string $message, \Throwable $exception): void {
        $ts  = date('Y-m-d H:i:s');
        $log = "[{$ts}] LOGIN_ERROR: {$message} | "
             . $exception->getMessage()
             . " | File: " . $exception->getFile()
             . " | Line: " . $exception->getLine() . PHP_EOL;
        @error_log($log, 3, $this->logFilePath);
    }

    public function findByIdentifier(string $identifier): ?array {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM users
                 WHERE username = ? OR email = ? OR phone = ? OR mobile = ?
                 LIMIT 1"
            );
            $stmt->execute([$identifier, $identifier, $identifier, $identifier]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            $this->logError("findByIdentifier failed: {$identifier}", $e);
            return null;
        }
    }

    /**
     * শুধুমাত্র মোবাইল/ফোন নম্বর দিয়ে ইউজার খোঁজে।
     * (ইউজারনেম/ইমেইল/আইডি দিয়ে লগইন বন্ধ — নিরাপত্তা ও নীতি অনুযায়ী।)
     */
    public function findByPhone(string $phone): ?array {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM users
                 WHERE phone = ? OR mobile = ?
                 LIMIT 1"
            );
            $stmt->execute([$phone, $phone]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            $this->logError("findByPhone failed: {$phone}", $e);
            return null;
        }
    }

    public function updateLastLogin(int $userId): bool {
        try {
            return $this->db->prepare(
                "UPDATE users SET last_login_date = CURDATE(),
                 last_active = NOW(),
                 login_count_today = login_count_today + 1
                 WHERE id = ?"
            )->execute([$userId]);
        } catch (\Throwable $e) {
            $this->logError("updateLastLogin failed id={$userId}", $e);
            return false;
        }
    }

    public function incrementFailedAttempts(int $userId): bool {
        try {
            return $this->db->prepare(
                "UPDATE users SET failed_attempts = failed_attempts + 1,
                 last_failed_time = NOW() WHERE id = ?"
            )->execute([$userId]);
        } catch (\Throwable $e) {
            $this->logError("incrementFailedAttempts failed id={$userId}", $e);
            return false;
        }
    }

    public function lockAccount(int $userId, string $lockUntil): bool {
        try {
            return $this->db->prepare(
                "UPDATE users SET failed_attempts = failed_attempts + 1,
                 last_failed_time = NOW(), lock_until = ? WHERE id = ?"
            )->execute([$lockUntil, $userId]);
        } catch (\Throwable $e) {
            $this->logError("lockAccount failed id={$userId}", $e);
            return false;
        }
    }

    public function resetFailedAttempts(int $userId): bool {
        try {
            return $this->db->prepare(
                "UPDATE users SET failed_attempts = 0,
                 last_failed_time = NULL, lock_until = NULL WHERE id = ?"
            )->execute([$userId]);
        } catch (\Throwable $e) {
            $this->logError("resetFailedAttempts failed id={$userId}", $e);
            return false;
        }
    }

    public function autoUnblockIfExpired(int $userId, string $blockEnd): bool {
        try {
            if (strtotime($blockEnd) <= time()) {
                return $this->db->prepare(
                    "UPDATE users SET status = 'active', block_end = NULL WHERE id = ?"
                )->execute([$userId]);
            }
            return false;
        } catch (\Throwable $e) {
            $this->logError("autoUnblockIfExpired failed id={$userId}", $e);
            return false;
        }
    }

    public function getSiteNotice(): string {
        try {
            $stmt = $this->db->query("SELECT notice_text FROM site_notice WHERE id = 1");
            $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
            return ($row && !empty($row['notice_text'])) ? (string)$row['notice_text'] : 'সিস্টেমে স্বাগতম';
        } catch (\Throwable $e) {
            $this->logError("getSiteNotice failed", $e);
            return 'সিস্টেমে স্বাগতম';
        }
    }

    public function getSliderPosts(): array {
        try {
            $stmt = $this->db->query("SELECT * FROM slider_posts ORDER BY RAND()");
            return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {
            $this->logError("getSliderPosts failed", $e);
            return [];
        }
    }

    public static function getMaxFailedAttempts(): int { return self::MAX_FAILED_ATTEMPTS; }
    public static function getLockDurationMinutes(): int { return self::LOCK_DURATION_MINUTES; }
}
