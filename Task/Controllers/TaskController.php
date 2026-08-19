<?php
declare(strict_types=1);
require_once __DIR__ . '/../Models/TaskModelInterface.php';
require_once __DIR__ . '/../Models/TaskModel.php';

/**
 * ============================================================
 *  TaskController — রুটিন/টাস্ক অনুরোধ নিয়ন্ত্রক
 * ------------------------------------------------------------
 *  View ও Model-এর মধ্যে সেতু। POST অ্যাকশন (add / mark_done /
 *  toggle / delete) হ্যান্ডেল করে, CSRF যাচাই ও ভূমিকা (role)
 *  ভিত্তিক অনুমতি নিশ্চিত করে এবং PRG প্যাটার্নে রিডাইরেক্ট দেয়।
 * ============================================================
 */
class TaskController
{
    /** @var TaskModelInterface */
    private TaskModelInterface $taskModel;

    public function __construct(TaskModelInterface $taskModel)
    {
        $this->taskModel = $taskModel;
    }

    /* --------------------------------------------------------
     *  View-এর জন্য ডেটা সরবরাহ
     * ------------------------------------------------------ */

    /** ভিউতে দেখানোর জন্য ব্যবহারকারীভিত্তিক টাস্ক তালিকা */
    public function getTasksForView(string $username, string $role): array
    {
        try {
            return $this->taskModel->getTasks($username, $role);
        } catch (Exception $e) {
            return [];
        }
    }

    /** অ্যাডমিন ফর্মের ড্রপডাউনের জন্য সব ব্যবহারকারী */
    public function getUsersForDropdown(): array
    {
        try {
            return $this->taskModel->getAllUsers();
        } catch (Exception $e) {
            return [];
        }
    }

    /* --------------------------------------------------------
     *  POST অনুরোধ হ্যান্ডলিং (CSRF + role যাচাই সহ)
     * ------------------------------------------------------ */
    public function handleRequest(array $postData, string $userRole, string $currentUsername): void
    {
        if (!isset($postData['routine_action'])) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // CSRF টোকেন যাচাই
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'] ?? '')) {
            die("<script>alert('Security Error! Invalid CSRF Token.'); window.location.reload();</script>");
        }

        $action = $postData['routine_action'];

        try {
            if ($action === 'add' && $userRole === 'admin') {
                $title       = htmlspecialchars(trim($postData['task_title']));
                $date        = $postData['task_date'];
                $time        = $postData['task_time'];
                $description = isset($postData['task_description'])
                    ? htmlspecialchars(trim($postData['task_description']))
                    : '';

                // একাধিক ইউজার সিলেক্ট লজিক (Array → String)
                $assignedToArray = $postData['assigned_to'] ?? [];
                if (empty($assignedToArray)) {
                    $_SESSION['error_message'] = "অন্তত একজনকে সিলেক্ট করতে হবে!";
                } else {
                    $assignedTo = in_array('all', $assignedToArray, true)
                        ? 'all'
                        : implode(',', $assignedToArray);
                    if ($title && $date && $time) {
                        $this->taskModel->addTask($title, $date, $time, $assignedTo, $description);
                        $_SESSION['success_message'] = "নতুন রুটিন সফলভাবে যোগ হয়েছে!";
                    }
                }
            } elseif ($action === 'mark_done') {
                $this->taskModel->markTaskAsDone((int)$postData['task_id'], $currentUsername);
            } elseif ($action === 'toggle' && $userRole === 'admin') {
                $this->taskModel->toggleTaskStatus((int)$postData['task_id'], $postData['new_status']);
            } elseif ($action === 'delete' && $userRole === 'admin') {
                $this->taskModel->deleteTask((int)$postData['task_id']);
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "দুঃখিত, একটি সমস্যা হয়েছে। কিছুক্ষণ পর আবার চেষ্টা করুন।";
        }

        // Post/Redirect/Get — ডাবল সাবমিট প্রতিরোধ
        if (!headers_sent()) {
            header("Location: " . $_SERVER['PHP_SELF']);
        } else {
            echo "<script>window.location.replace('" . $_SERVER['PHP_SELF'] . "');</script>";
        }
        exit;
    }
}
