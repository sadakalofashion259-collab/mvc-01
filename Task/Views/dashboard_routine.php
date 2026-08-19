<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Dhaka'); 

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Models/TaskModelInterface.php';
require_once __DIR__ . '/../Models/TaskModel.php';
require_once __DIR__ . '/../Controllers/TaskController.php';

$taskModel = new TaskModel($conn);
$taskController = new TaskController($taskModel);

$userRole = (string)($_SESSION['role'] ?? 'user');
$currentUsername = (string)($_SESSION['username'] ?? 'Unknown');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['routine_action'])) {
    $taskController->handleRequest($_POST, $userRole, $currentUsername);
}

$routineTasks = $taskController->getTasksForView($currentUsername, $userRole);

$todayDate = date('Y-m-d');
$activeTasks = array_filter($routineTasks, function($task) use ($todayDate) {
    return (string)($task['status'] ?? '') === 'active' && (string)($task['task_date'] ?? '') <= $todayDate;
});
?>

<?php if (!empty($activeTasks)): ?>
<div class="coll-slider-wrap">
    <div class="coll-slider-title">
        <i class="fas fa-clipboard-list"></i> 📋 আপনার আজকের রুটিন (<?php echo count($activeTasks); ?>)
    </div>
    <div class="coll-track" id="routineTrack">
        <?php foreach($activeTasks as $task): 
            $rawTitle = htmlspecialchars_decode((string)($task['task_title'] ?? 'শিরোনাম নেই'), ENT_QUOTES);
            $rawDesc = htmlspecialchars_decode((string)($task['task_description'] ?? 'এই কাজের কোনো বিস্তারিত বিবরণ দেওয়া নেই।'), ENT_QUOTES);
            $safeTime = date('h:i A', strtotime((string)($task['task_time'] ?? '00:00')));
            
            $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
            $jsTitle = htmlspecialchars((string)json_encode($rawTitle, $jsonFlags), ENT_QUOTES, 'UTF-8');
            $jsDesc = htmlspecialchars((string)json_encode($rawDesc, $jsonFlags), ENT_QUOTES, 'UTF-8');
            $jsTime = htmlspecialchars((string)json_encode($safeTime, $jsonFlags), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="coll-card" onclick="showTaskModal(<?php echo $jsTitle; ?>, <?php echo $jsDesc; ?>, <?php echo $jsTime; ?>)">
            <div class="coll-icon">
                <i class="fas fa-tasks"></i>
            </div>
            
            <div class="coll-info">
                <div class="coll-shop">
                    <?php echo htmlspecialchars($rawTitle, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="coll-name">
                    <i class="fas fa-clock"></i> <?php echo $safeTime; ?>
                </div>
            </div>

            <form method="POST" class="coll-doneform">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="routine_action" value="mark_done">
                <input type="hidden" name="task_id" value="<?php echo (int)($task['id'] ?? 0); ?>">
                <button type="submit" class="coll-done" onclick="event.stopPropagation(); return confirm('কাজটি সম্পন্ন হয়েছে?');">
                    <i class="fas fa-check"></i>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="taskDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: white; width: 90%; max-width: 400px; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden; animation: fadeIn 0.3s ease;">
        <div style="background: linear-gradient(135deg, #6d5bf6, #3b82f6); color: white; padding: 15px 18px; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalSubject" style="margin: 0; font-size: 16px; font-weight: bold;">বিষয়</h3>
            <button onclick="closeTaskModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 15px; cursor: pointer; width: 30px; height: 30px; border-radius: 50%; display:flex; align-items:center; justify-content:center;"><i class="fas fa-times"></i></button>
        </div>
        <div style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div style="font-size: 12px; color: #6b7280; font-weight: bold;">
                    <i class="fas fa-clock"></i> সময়: <span id="modalTime"></span>
                </div>
                <button onclick="readModalText()" style="background: #eef0fb; border: none; padding: 6px 12px; border-radius: 20px; font-size: 11px; cursor: pointer; color: #4f46e5; display: flex; align-items: center; gap: 4px; font-weight: bold;">
                    <i class="fas fa-volume-up"></i> শুনুন
                </button>
            </div>
            <div style="font-size: 14px; color: #374151; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;" id="modalDescription">
                বিবরণ এখানে দেখাবে
            </div>
        </div>
        <div style="padding: 15px; background: #f9fafb; text-align: right; border-top: 1px solid #e5e7eb;">
            <button onclick="closeTaskModal()" style="background: #ef4444; color: white; border: none; padding: 9px 18px; border-radius: 20px; font-size: 14px; cursor: pointer; font-weight: bold;">বন্ধ করুন</button>
        </div>
    </div>
</div>

<style>
    /* ===== স্লাইডার র‍্যাপার ও টাইটেল ===== */
    .coll-slider-wrap {
        margin-top: 10px;
        position: relative;
    }
    .coll-slider-title {
        color: #4f46e5;
        font-weight: 800;
        font-size: 13px;
        margin-bottom: 8px;
        padding-left: 2px;
    }

    /* ===== স্লাইডার ট্র্যাক (অনুভূমিক স্ক্রল) ===== */
    .coll-track {
        display: flex;
        overflow-x: auto;
        scrollbar-width: none;
        gap: 8px;
        padding: 2px 2px 6px;
    }
    .coll-track::-webkit-scrollbar { display: none; }

    /* ===== স্লিম রাউন্ড পিল কার্ড (কম্প্যাক্ট ও চিকন) ===== */
    .coll-card {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 9px;
        background: #fff;
        border: 1px solid #eef0f6;
        border-radius: 999px;          /* সম্পূর্ণ রাউন্ড পিল */
        padding: 4px 5px 4px 5px;
        box-shadow: 0 4px 12px -6px rgba(40,44,90,0.18);
        cursor: pointer;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .coll-card:active {
        transform: scale(0.97);
        box-shadow: 0 6px 14px -6px rgba(79,70,229,0.4);
    }

    /* বাম পাশের গোল আইকন */
    .coll-icon {
        width: 30px;
        height: 30px;
        flex-shrink: 0;
        border-radius: 50%;
        background: linear-gradient(135deg, #6d5bf6, #3b82f6);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        box-shadow: 0 4px 8px -3px rgba(109,91,246,0.6);
    }

    /* শিরোনাম ও সময় (চিকন রাখতে এক লাইনে) */
    .coll-info {
        padding-right: 4px;
        min-width: 0;
    }
    .coll-shop {
        color: #1f2433;
        font-size: 12.5px;
        font-weight: 800;
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 150px;
    }
    .coll-name {
        font-size: 10px;
        color: #8a90a2;
        font-weight: 600;
        margin-top: 1px;
        white-space: nowrap;
    }

    /* ডান পাশের সম্পন্ন বাটন */
    .coll-doneform { margin: 0; padding: 0; display: flex; align-items: center; }
    .coll-done {
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff;
        border: none;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        flex-shrink: 0;
        box-shadow: 0 4px 8px -3px rgba(16,185,129,0.6);
        transition: transform 0.15s ease;
    }
    .coll-done:active { transform: scale(0.85); }

    @keyframes fadeIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
</style>

<script>
    const modalSynth = window.speechSynthesis;

    document.addEventListener("DOMContentLoaded", function() {
        const rTrack = document.getElementById('routineTrack');
        if (rTrack) {
            let scrollAmt = 1;
            let autoScroll = setInterval(() => {
                rTrack.scrollLeft += scrollAmt;
                if (rTrack.scrollLeft >= (rTrack.scrollWidth - rTrack.clientWidth)) scrollAmt = -1;
                else if (rTrack.scrollLeft <= 0) scrollAmt = 1;
            }, 20);
            rTrack.addEventListener('mouseenter', () => clearInterval(autoScroll));
        }
    });

    function showTaskModal(title, description, time) {
        document.getElementById('modalSubject').innerText = title;
        document.getElementById('modalDescription').innerText = description;
        document.getElementById('modalTime').innerText = time;
        document.getElementById('taskDetailsModal').style.display = 'flex';
    }

    function closeTaskModal() {
        modalSynth.cancel();
        document.getElementById('taskDetailsModal').style.display = 'none';
    }

    function readModalText() {
        const title = document.getElementById('modalSubject').innerText;
        const desc = document.getElementById('modalDescription').innerText;
        modalSynth.cancel();
        const utterance = new SpeechSynthesisUtterance(title + "। " + desc);
        utterance.lang = 'bn-BD';
        utterance.rate = 0.85; // সুন্দর ও ধীর লয়ে
        utterance.pitch = 1.1; 
        modalSynth.speak(utterance);
    }
</script>
<?php endif; ?>
