<?php
declare(strict_types=1);

class UserModel {
    private PDO $db;

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    // username, email, phone, mobile — যেকোনো একটায় খোঁজে
    public function findByIdentifier(string $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM users
             WHERE username = ? OR email = ? OR phone = ? OR mobile = ?
             LIMIT 1"
        );
        $stmt->execute([$id, $id, $id, $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateLastLogin(int $userId): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET last_login_date = CURDATE(),
             last_active = NOW(),
             login_count_today = login_count_today + 1
             WHERE id = ?"
        );
        return $stmt->execute([$userId]);
    }

    // ✅ otp_requests এ email + phone + otp সেভ
    public function createOtp(string $email, string $otp, string $phone = ''): bool {
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $stmt = $this->db->prepare(
            "INSERT INTO otp_requests (email, otp, expires_at, phone, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        return $stmt->execute([$email, $otp, $expires, $phone]);
    }

    // ✅ OTP যাচাই
    public function verifyOtp(string $email, string $otp): bool {
        $stmt = $this->db->prepare(
            "SELECT id FROM otp_requests
             WHERE email = ? AND otp = ?
             AND is_used = 0 AND expires_at >= NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$email, $otp]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->db->prepare(
                "UPDATE otp_requests SET is_used = 1 WHERE id = ?"
            )->execute([$row['id']]);
            return true;
        }
        return false;
    }

    public function markUserAsVerified(int $userId): bool {
        return $this->db->prepare(
            "UPDATE users SET is_verified = 1 WHERE id = ?"
        )->execute([$userId]);
    }
}
?>
