<?php
declare(strict_types=1);
require_once __DIR__ . '/TaskModelInterface.php';

/**
 * ============================================================
 *  TaskModel — দৈনিক রুটিন/টাস্কের ডেটা অ্যাক্সেস স্তর (PDO)
 * ------------------------------------------------------------
 *  TaskModelInterface বাস্তবায়ন করে। প্রতিটি লেখার অপারেশন
 *  ট্রানজ্যাকশনে মোড়ানো এবং ত্রুটি/অ্যাকশন আলাদা লগ ফাইলে রাখা হয়।
 * ============================================================
 */
class TaskModel implements TaskModelInterface
{
    /** @var PDO ডেটাবেস কানেকশন */
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /* --------------------------------------------------------
     *  লগিং সহায়ক পদ্ধতি
     * ------------------------------------------------------ */

    /** ডেটাবেস ত্রুটি Task/Logs/error_log.txt-এ লেখে */
    private function logError(string $message): void
    {
        $logFile = __DIR__ . '/../Logs/error_log.txt';
        $logDir  = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $date = date('Y-m-d H:i:s');
        error_log("[{$date}] DB Error: {$message}" . PHP_EOL, 3, $logFile);
    }

    /** সফল কার্যক্রম Task/Logs/action_log.txt-এ লেখে */
    private function logAction(string $actionMessage): void
    {
        $logFile = __DIR__ . '/../Logs/action_log.txt';
        $logDir  = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $date = date('Y-m-d H:i:s');
        error_log("[{$date}] Action: {$actionMessage}" . PHP_EOL, 3, $logFile);
    }

    /* --------------------------------------------------------
     *  পঠন (Read) অপারেশন
     * ------------------------------------------------------ */

    public function getAllUsers(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT username, role FROM users ORDER BY role ASC, username ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("getAllUsers: " . $e->getMessage());
            throw new Exception("Database error occurred.");
        }
    }

    public function getTasks(string $username, string $role): array
    {
        try {
            if ($role === 'admin') {
                // অ্যাডমিন সব টাস্ক দেখবে
                $stmt = $this->db->query(
                    "SELECT * FROM daily_tasks ORDER BY status ASC, task_date ASC"
                );
            } else {
                // সাধারণ ইউজার: কমা-সেপারেটেড assigned_to-তে FIND_IN_SET অথবা 'all'
                $stmt = $this->db->prepare(
                    "SELECT * FROM daily_tasks
                     WHERE (FIND_IN_SET(?, assigned_to) > 0 OR assigned_to = 'all')
                     ORDER BY status ASC, task_date ASC"
                );
                $stmt->execute([$username]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("getTasks: " . $e->getMessage());
            throw new Exception("Database error occurred.");
        }
    }

    /* --------------------------------------------------------
     *  লেখা (Write) অপারেশন — সবই ট্রানজ্যাকশনে
     * ------------------------------------------------------ */

    public function addTask(string $title, string $date, string $time, string $assignedTo, string $description = ''): bool
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "INSERT INTO daily_tasks
                    (task_title, task_date, task_time, assigned_to, task_description, status)
                 VALUES (?, ?, ?, ?, ?, 'active')"
            );
            $result = $stmt->execute([$title, $date, $time, $assignedTo, $description]);
            $this->db->commit();
            $this->logAction("New task added assigned to '{$assignedTo}'");
            return $result;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError("addTask: " . $e->getMessage());
            throw new Exception("Database error occurred.");
        }
    }

    public function markTaskAsDone(int $id, string $username): bool
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "UPDATE daily_tasks
                 SET status = 'inactive', completed_by = ?, completed_at = NOW()
                 WHERE id = ?"
            );
            $result = $stmt->execute([$username, $id]);
            $this->db->commit();
            $this->logAction("Task ID {$id} marked done by {$username}");
            return $result;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError("markTaskAsDone: " . $e->getMessage());
            throw new Exception("Database error occurred.");
        }
    }

    public function toggleTaskStatus(int $id, string $newStatus): bool
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "UPDATE daily_tasks SET status = ? WHERE id = ?"
            );
            $result = $stmt->execute([$newStatus, $id]);
            $this->db->commit();
            $this->logAction("Task {$id} status to {$newStatus}");
            return $result;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError("toggleTaskStatus: " . $e->getMessage());
            throw new Exception("Database error occurred.");
        }
    }

    public function deleteTask(int $id): bool
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "DELETE FROM daily_tasks WHERE id = ?"
            );
            $result = $stmt->execute([$id]);
            $this->db->commit();
            $this->logAction("Task {$id} deleted");
            return $result;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError("deleteTask: " . $e->getMessage());
            throw new Exception("Database error occurred.");
        }
    }
}
