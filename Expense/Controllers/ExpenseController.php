<?php
declare(strict_types=1);

class ExpenseController {
    private $repo;
    private $imageService;

    public function __construct(ExpenseRepoInterface $repo, ImageService $imageService) {
        $this->repo         = $repo;
        $this->imageService = $imageService;
    }

    private function sendJsonResponse(array $data): void {
        if (ob_get_level() > 0) { @ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    public function handleRequest(): void {
        // ============================================================
        //  🚫 AJAX কলেও লগইন চেক — লগইন না থাকলে JSON এরর
        // ============================================================
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
            if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
                $this->sendJsonResponse(['status' => 'error', 'msg' => 'দয়া করে লগইন করুন!']);
            }
        }

        $role      = $_SESSION['role']       ?? 'viewer';
        $username  = $_SESSION['username']   ?? ''; // লগইন থাকলে সর্বদা সেট থাকবে
        $csrfToken = $_SESSION['csrf_token'] ?? '';
        $todayDate = date('Y-m-d');

        // ===== AJAX ACTIONS =====
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
            if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
                $this->sendJsonResponse(['status' => 'error', 'msg' => 'সিকিউরিটি এরর (CSRF)!']);
            }

            $action = $_POST['ajax_action'];

            // === সেভ ===
            if ($action === 'save_expense') {
                if ($role === 'viewer') {
                    $this->sendJsonResponse(['status' => 'error', 'msg' => 'অনুমতি নেই!']);
                }
                $expDate  = ($role === 'manager') ? $todayDate : trim($_POST['expense_date']);
                $category = htmlspecialchars(strip_tags(trim($_POST['category'])));
                $amount   = floatval($_POST['amount']);
                $note     = isset($_POST['expense_note']) ? mb_substr(htmlspecialchars(strip_tags(trim($_POST['expense_note']))), 0, MAX_NOTE_LENGTH) : null;
                if ($note === '') { $note = null; }

                if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $expDate)) {
                    $this->sendJsonResponse(['status' => 'error', 'msg' => 'ভুল তারিখ ফরম্যাট!']);
                }
                if ($role !== 'admin' && $expDate < $todayDate) {
                    $this->sendJsonResponse(['status' => 'error', 'msg' => 'পেছনের তারিখ দেওয়া যাবে না!']);
                }
                if (empty($category) || $amount <= 0 || $amount > MAX_AMOUNT || mb_strlen($category) > 150) {
                    $this->sendJsonResponse(['status' => 'error', 'msg' => 'ভুল তথ্য দেওয়া হয়েছে!']);
                }

                $photoPath = null;
                if ($role !== 'viewer' && !empty($_POST['webcam_image'])) {
                    $photoPath = $this->imageService->processAndSaveImage($_POST['webcam_image'], $expDate);
                    if (!$photoPath) {
                        $this->sendJsonResponse(['status' => 'error', 'msg' => 'ছবি প্রসেস করা যায়নি!']);
                    }
                }

                $newId = $this->repo->saveExpense($expDate, $category, $amount, $photoPath, $username, $note);
                if ($newId > 0) {
                    if (class_exists('AuditLogger')) {
                        AuditLogger::create('expenses', $newId, null, [
                            'expense_date' => $expDate,
                            'category'     => $category,
                            'amount'       => $amount,
                            'note'         => $note,
                            'photo'        => $photoPath,
                            'entry_by'     => $username,
                        ]);
                    }
                    $this->renderAjaxHistoryRow($role, 'খরচ সফলভাবে সেভ হয়েছে!');
                } else {
                    $this->sendJsonResponse(['status' => 'error', 'msg' => 'ডাটাবেজ এরর!']);
                }
            }

            // === এডিট ও ডিলিট ===
            if (in_array($action, ['edit_expense', 'delete_expense'])) {
                if ($role !== 'admin') {
                    $this->sendJsonResponse(['status' => 'error', 'msg' => 'অনুমতি নেই!']);
                }
                if (!$this->repo->verifyAdmin($username, $_POST['admin_pass'] ?? '')) {
                    $this->sendJsonResponse(['status' => 'error', 'msg' => 'ভুল পাসওয়ার্ড!']);
                }
                $id = (int)$_POST['action_id'];

                if ($action === 'delete_expense') {
                    $old = $this->repo->getExpenseById($id);
                    $photo = $this->repo->getPhotoPath($id);
                    if ($photo) {
                        $p = (strpos($photo, 'http') !== 0 && strpos($photo, '../') !== 0)
                            ? __DIR__ . '/../../' . ltrim($photo, '/') : $photo;
                        if (file_exists($p)) unlink($p);
                    }
                    if ($this->repo->deleteExpense($id)) {
                        if (class_exists('AuditLogger') && $old) {
                            AuditLogger::delete('expenses', $id, $old);
                        }
                        $this->renderAjaxHistoryRow($role, 'খরচ সফলভাবে ডিলিট করা হয়েছে!');
                    } else {
                        $this->sendJsonResponse(['status' => 'error', 'msg' => 'ডিলিট করা যায়নি!']);
                    }
                }

                if ($action === 'edit_expense') {
                    $amount   = floatval($_POST['edit_amount']);
                    $cat      = htmlspecialchars(strip_tags(trim($_POST['edit_category'])));
                    $editNote = isset($_POST['edit_note']) ? mb_substr(htmlspecialchars(strip_tags(trim($_POST['edit_note']))), 0, MAX_NOTE_LENGTH) : null;
                    if ($editNote === '') { $editNote = null; }
                    if (empty($cat) || $amount <= 0 || $amount > MAX_AMOUNT) {
                        $this->sendJsonResponse(['status' => 'error', 'msg' => 'সঠিক তথ্য দিন!']);
                    }
                    $old = $this->repo->getExpenseById($id);
                    if ($this->repo->updateExpense($id, $amount, $cat, $editNote)) {
                        if (class_exists('AuditLogger')) {
                            AuditLogger::update('expenses', $id, $old, [
                                'amount'      => $amount,
                                'description' => $cat,
                                'note'        => $editNote,
                            ]);
                        }
                        $this->renderAjaxHistoryRow($role, 'খরচ সফলভাবে আপডেট করা হয়েছে!');
                    } else {
                        $this->sendJsonResponse(['status' => 'error', 'msg' => 'আপডেট করা যায়নি!']);
                    }
                }
            }

            // === ফিল্টার ===
            if ($action === 'filter_expenses') {
                $category = isset($_POST['filter_category']) ? htmlspecialchars(strip_tags(trim($_POST['filter_category']))) : '';
                $dateFrom = isset($_POST['filter_date_from']) ? trim($_POST['filter_date_from']) : '';
                $dateTo   = isset($_POST['filter_date_to'])   ? trim($_POST['filter_date_to'])   : '';

                if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $dateFrom) ||
                    !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $dateTo)) {
                    $this->sendJsonResponse(['status' => 'error', 'msg' => 'ভুল তারিখ ফরম্যাট!']);
                }

                $rows = $this->repo->filterExpenses($dateFrom, $dateTo, $category);
                $this->sendJsonResponse(['status' => 'success', 'rows' => $rows]);
            }
        }

        // ===== CATEGORY MANAGEMENT (form POST) =====
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin'
            && isset($_POST['csrf_token']) && hash_equals($csrfToken, $_POST['csrf_token'])) {
            if (isset($_POST['add_new_category_submit'])) {
                $catName = htmlspecialchars(strip_tags(trim($_POST['add_new_category_name'])));
                if ($this->repo->addCategory($catName) && class_exists('AuditLogger')) {
                    AuditLogger::create('expense_categories', $catName, null, ['category_name' => $catName], "ক্যাটাগরি যোগ: {$catName}");
                }
            }
            if (isset($_POST['edit_cat_submit'])) {
                $cid  = (int)$_POST['edit_cat_id'];
                $name = htmlspecialchars(strip_tags(trim($_POST['edit_cat_name'])));
                $old  = $this->repo->getCategoryById($cid);
                if ($this->repo->updateCategory($cid, $name) && class_exists('AuditLogger')) {
                    AuditLogger::update('expense_categories', $cid, $old, ['category_name' => $name]);
                }
            }
            if (isset($_POST['delete_cat_submit'])) {
                $cid = (int)$_POST['delete_cat_id'];
                $old = $this->repo->getCategoryById($cid);
                if ($this->repo->deleteCategory($cid) && class_exists('AuditLogger') && $old) {
                    AuditLogger::delete('expense_categories', $cid, $old);
                }
            }
            if (isset($_POST['toggle_cat_submit'])) {
                $cid = (int)$_POST['toggle_cat_id'];
                $old = $this->repo->getCategoryById($cid);
                if ($this->repo->toggleCategoryStatus($cid) && class_exists('AuditLogger') && $old) {
                    $newActive = ((int)($old['is_active'] ?? 1) === 1) ? 0 : 1;
                    AuditLogger::update(
                        'expense_categories',
                        $cid,
                        ['is_active' => (int)($old['is_active'] ?? 1)],
                        ['is_active' => $newActive],
                        "ক্যাটাগরি #{$cid} স্ট্যাটাস টগল"
                    );
                }
            }
        }

        // ===== RENDER VIEW =====
        $folderMonth  = isset($_GET['folder_month']) ? preg_replace('/[^0-9\-]/', '', $_GET['folder_month']) : '';
        $sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));

        $viewData = [
            'role'              => $role,
            'today_date'        => $todayDate,
            'csrf_token'        => $csrfToken,
            'folder_month'      => $folderMonth,
            'all_categories'    => $this->repo->getCategories(),
            'active_categories' => $this->repo->getActiveCategories(),
            'grouped_expenses'  => $this->repo->getExpenses($sevenDaysAgo, $folderMonth),
            'folder_stats'      => $this->repo->getFolderStats(date('Y')),
        ];

        extract($viewData);
        require_once __DIR__ . '/../Views/expense_view.php';
    }

    private function renderAjaxHistoryRow(string $role, string $successMessage): void {
        $folderMonthAjax = isset($_POST['folder_month']) ? preg_replace('/[^0-9\-]/', '', $_POST['folder_month']) : '';
        $dateLimit       = date('Y-m-d', strtotime('-6 days'));
        $ajax_grouped    = $this->repo->getExpenses($dateLimit, $folderMonthAjax);

        ob_start();
        if (!empty($ajax_grouped)):
            foreach ($ajax_grouped as $date => $expenses): ?>
                <div class="day-group">
                    <div class="day-head"><i class="far fa-calendar"></i> <?php echo date('d-M-Y', strtotime($date)); ?></div>
                    <div style="overflow-x:auto;">
                        <table class="exp-table">
                            <tr><th>ক্যাটাগরি</th><th>টাকা</th><th style="text-align:center;">ছবি</th><th style="text-align:right;">অ্যাকশন</th></tr>
                            <?php foreach ($expenses as $exp):
                                $safe_desc  = htmlspecialchars($exp['description'] ?? '');
                                $safe_note  = htmlspecialchars($exp['note'] ?? '');
                                $safe_amt   = number_format((float)($exp['amount'] ?? 0));
                                $disp_photo = '';
                                if (!empty($exp['photo'])) {
                                    $disp_photo = (strpos($exp['photo'], 'http') !== 0 && strpos($exp['photo'], '../') !== 0)
                                        ? '../' . ltrim($exp['photo'], '/') : $exp['photo'];
                                    $disp_photo = htmlspecialchars($disp_photo);
                                }
                            ?>
                            <tr>
                                <td>
                                    <span class="exp-cat"><?php echo $safe_desc; ?></span>
                                    <?php if (!empty($safe_note)): ?>
                                        <div class="exp-note"><i class="fas fa-align-left"></i><span><?php echo $safe_note; ?></span></div>
                                    <?php endif; ?>
                                    <br><span class="exp-by">By: <?php echo htmlspecialchars($exp['entry_by'] ?? ''); ?></span>
                                </td>
                                <td><span class="exp-amt">৳<?php echo $safe_amt; ?></span></td>
                                <td style="text-align:center;">
                                    <?php if (!empty($disp_photo)): ?>
                                        <img src="<?php echo $disp_photo; ?>" onclick="viewImage(this.src)" class="thumb">
                                    <?php else: ?><span style="color:var(--muted);">-</span><?php endif; ?>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <?php if ($role === 'admin'): ?>
                                        <button class="act-btn act-edit" onclick="triggerAjaxEdit(<?php echo $exp['id']; ?>, '<?php echo addslashes($safe_desc); ?>', <?php echo (float)($exp['amount'] ?? 0); ?>, '<?php echo addslashes($safe_note); ?>')"><i class="fas fa-pen"></i></button>
                                        <button class="act-btn act-del" onclick="triggerAjaxDelete(<?php echo $exp['id']; ?>)"><i class="fas fa-trash-can"></i></button>
                                    <?php else: ?>
                                        <i class="fas fa-lock" style="color:var(--muted); font-size:12px;"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            <?php endforeach;
        else: ?>
            <div class="empty-state"><i class="fas fa-receipt"></i> কোনো হিস্ট্রি ডাটা নেই।</div>
        <?php endif;

        $html = ob_get_clean();
        $this->sendJsonResponse(['status' => 'success', 'msg' => $successMessage, 'history_html' => $html]);
    }
}