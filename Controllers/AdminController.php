<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/UserModel.php';
require_once __DIR__ . '/../Models/NotificationService.php';

class AdminController {
    private UserModel $userModel;
    private NotificationService $notif;
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->userModel = new UserModel($db);
        $this->notif = new NotificationService();
    }

    public function setTimedBlock(int $userId, string $endTime): bool {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("UPDATE users SET status = 'blocked', block_end = ? WHERE id = ?");
            $result = $stmt->execute([$endTime, $userId]);
            $this->db->commit();
            return $result;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            error_log("Timed Block Error: " . $e->getMessage(), 3, __DIR__ . '/../Logs/error_log.txt');
            return false;
        }
    }

    public function toggleUserStatus(int $userId, string $current): bool {
        try {
            $new = ($current === 'blocked') ? 'active' : 'blocked';
            if ($new === 'active') {
                return $this->db->prepare("UPDATE users SET status = ?, block_end = NULL WHERE id = ?")->execute([$new, $userId]);
            }
            return $this->db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new, $userId]);
        } catch (PDOException $e) {
            error_log("Toggle Status Error: " . $e->getMessage(), 3, __DIR__ . '/../Logs/error_log.txt');
            return false;
        }
    }

    public function broadcastMessage(int $adminId, string $msg): bool {
        try {
            $stmt = $this->db->prepare("INSERT INTO notifications (user_id, message, status) VALUES (?, ?, 'broadcast')");
            if ($stmt->execute([$adminId, $msg])) {
                $this->notif->sendPush($msg);
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Broadcast Error: " . $e->getMessage(), 3, __DIR__ . '/../Logs/error_log.txt');
            return false;
        }
    }

    public function deleteUser(int $userId, int $adminId): bool {
        try {
            $stmt = $this->db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() !== '@sadakalo' && $userId !== $adminId) {
                return $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            }
            return false;
        } catch (PDOException $e) {
            error_log("Delete User Error: " . $e->getMessage(), 3, __DIR__ . '/../Logs/error_log.txt');
            return false;
        }
    }

    public function verifyActionAuth(string $uname, string $pass): bool {
        try {
            $stmt = $this->db->prepare("SELECT action_pass, password FROM users WHERE username = ?");
            $stmt->execute([$uname]);
            $u = $stmt->fetch();
            $check = !empty($u['action_pass']) ? $u['action_pass'] : ($u['password'] ?? '');
            return password_verify($pass, $check) || $pass === $check;
        } catch (PDOException $e) {
            return false;
        }
    }
}
