<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Interfaces/ProfileModelInterface.php';
require_once __DIR__ . '/../Models/ProfileModel.php';
require_once __DIR__ . '/../Services/ImageResizer.php';

// ═══════════════════════════════════════════════════════════════
// PHP 8.1 ENUM — ProfileUpdateSection
// ═══════════════════════════════════════════════════════════════
enum ProfileUpdateSection: string {
    case BasicInfo = 'basic_info';
    case Password  = 'password';
    case Picture   = 'picture';

    public function successMessage(): string {
        return match($this) {
            self::BasicInfo => '✅ প্রোফাইল তথ্য সফলভাবে আপডেট হয়েছে!',
            self::Password  => '✅ পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে!',
            self::Picture   => '✅ প্রোফাইল ছবি সফলভাবে আপডেট হয়েছে!',
        };
    }

    public function tabIndex(): int {
        return match($this) {
            self::BasicInfo => 0,
            self::Password  => 1,
            self::Picture   => 2,
        };
    }
}

// ═══════════════════════════════════════════════════════════════
// PHP 8.1 READONLY CLASS — ProfileActionResult
// ═══════════════════════════════════════════════════════════════
readonly class ProfileActionResult {
    public function __construct(
        public bool                 $success,
        public string               $message,
        public ProfileUpdateSection $section
    ) {}
}

// ═══════════════════════════════════════════════════════════════
// CONTROLLER — ProfileController
// ═══════════════════════════════════════════════════════════════
class ProfileController {

    private ProfileModelInterface $model;
    private ImageResizer          $imageResizer;

    public readonly int    $userId;
    public readonly string $userRole;

    // ─────────────────────────────────────────────────────────────
    // FIX 1: projectRoot = public_html/ (যেখানে uploads/ ফোল্ডার আছে)
    // Profile/Controllers/ ফোল্ডার থেকে dirname(__DIR__, 2) = public_html/
    // ─────────────────────────────────────────────────────────────
    private readonly string $publicHtmlRoot;

    private const MAX_UPLOAD_BYTES = 20 * 1024 * 1024; // 20 MB

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
    ];

    private const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'png',
        'image/webp' => 'jpg',
    ];

    public function __construct(\PDO $db) {
        $this->model          = new ProfileModel($db);
        $this->imageResizer   = new ImageResizer();
        // FIX 1 প্রয়োগ: dirname(__DIR__, 2) = public_html/ (Profile/Controllers/ থেকে)
        $this->publicHtmlRoot = dirname(__DIR__, 2);
        $this->bootSession();
        $this->userId   = (int)    ($_SESSION['user_id'] ?? 0);
        $this->userRole = (string) ($_SESSION['role']    ?? 'staff');

        // FIX 2: syncProfilePicToSession() এখন শুধু session EMPTY থাকলেই
        // DB থেকে পড়ে। ছবি আপলোডের পর session-এ নতুন path বসানো হয়
        // processPictureUpdate()-এর ভেতরেই — redirect-এর আগেই।
        // পরের request-এ constructor আবার চললে session খালি না থাকায়
        // DB call হবে না, তাই নতুন path overwrite হবে না।
        $this->syncProfilePicToSession();
    }

    // ─────────────────────────────────────────────────────────────
    // FIX 2: session EMPTY হলেই DB থেকে পড়ো, otherwise skip।
    // এতে ছবি upload → session set → redirect → নতুন request এ
    // session-এর নতুন path টিকে থাকে।
    // ─────────────────────────────────────────────────────────────
    private function syncProfilePicToSession(): void {
        if ($this->userId <= 0) return;
        // session-এ ইতিমধ্যে value আছে → DB call করা দরকার নেই
        if (!empty($_SESSION['profile_pic'])) return;

        try {
            $userData = $this->model->getUserById($this->userId);
            if ($userData !== null && !empty($userData['profile_pic'])) {
                $_SESSION['profile_pic'] = (string) $userData['profile_pic'];
            }
        } catch (\Throwable $e) {
            $this->model->logError("syncProfilePicToSession failed userId={$this->userId}", $e);
        }
    }

    /* ── Session ──────────────────────────────────────────────── */
    private function bootSession(): void {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header('Location: index.php');
            exit;
        }
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
            session_unset();
            session_destroy();
            header('Location: index.php?reason=timeout');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }

    /* ── CSRF ─────────────────────────────────────────────────── */
    public function generateCsrfToken(): string {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return (string) ($_SESSION['csrf_profile_token'] ?? '');
        }
        $_SESSION['csrf_profile_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_profile_token'];
    }

    private function verifyCsrf(): bool {
        $incoming = trim((string) ($_POST['csrf_token'] ?? ''));
        $stored   = (string) ($_SESSION['csrf_profile_token'] ?? '');
        return $stored !== '' && hash_equals($stored, $incoming);
    }

    /* ── Public API ───────────────────────────────────────────── */
    public function getUserData(): ?array {
        return $this->model->getUserById($this->userId);
    }

    public function handlePost(): ?ProfileActionResult {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
        if (!$this->verifyCsrf()) {
            return new ProfileActionResult(
                false,
                '❌ সিকিউরিটি টোকেন অবৈধ। পেজ রিফ্রেশ করুন।',
                ProfileUpdateSection::BasicInfo
            );
        }
        return match((string) ($_POST['action'] ?? '')) {
            'update_basic'    => $this->processBasicInfoUpdate(),
            'change_password' => $this->processPasswordChange(),
            'update_picture'  => $this->processPictureUpdate(),
            default           => null,
        };
    }

    /* ── Basic Info Update ────────────────────────────────────── */
    private function processBasicInfoUpdate(): ProfileActionResult {
        $email   = trim((string) ($_POST['email']   ?? ''));
        $phone   = trim((string) ($_POST['phone']   ?? ''));
        $mobile  = trim((string) ($_POST['mobile']  ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new ProfileActionResult(false, '❌ সঠিক ইমেইল ঠিকানা দিন।', ProfileUpdateSection::BasicInfo);
        }
        if ($email !== '' && $this->model->isEmailTaken($email, $this->userId)) {
            return new ProfileActionResult(false, '❌ এই ইমেইল ইতিমধ্যে অন্য অ্যাকাউন্টে ব্যবহৃত হচ্ছে।', ProfileUpdateSection::BasicInfo);
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            return new ProfileActionResult(false, '❌ সঠিক ফোন নম্বর দিন (৭-২০ ডিজিট)।', ProfileUpdateSection::BasicInfo);
        }
        if ($mobile !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $mobile)) {
            return new ProfileActionResult(false, '❌ সঠিক মোবাইল নম্বর দিন (৭-২০ ডিজিট)।', ProfileUpdateSection::BasicInfo);
        }

        $oldUser = $this->model->getUserById($this->userId);
        $isUpdated = $this->model->updateBasicInfo($this->userId, $email, $phone, $mobile, $address);
        if ($isUpdated) {
            $_SESSION['email'] = $email;
            if (class_exists('AuditLogger')) {
                AuditLogger::update(
                    'profile',
                    $this->userId,
                    $oldUser ? [
                        'email'   => $oldUser['email'] ?? null,
                        'phone'   => $oldUser['phone'] ?? null,
                        'mobile'  => $oldUser['mobile'] ?? null,
                        'address' => $oldUser['address'] ?? null,
                    ] : null,
                    [
                        'email'   => $email,
                        'phone'   => $phone,
                        'mobile'  => $mobile,
                        'address' => $address,
                    ],
                    "ইউজার #{$this->userId} প্রোফাইল তথ্য আপডেট"
                );
            }
            return new ProfileActionResult(true, ProfileUpdateSection::BasicInfo->successMessage(), ProfileUpdateSection::BasicInfo);
        }
        return new ProfileActionResult(false, '❌ তথ্য আপডেট করতে সমস্যা হয়েছে।', ProfileUpdateSection::BasicInfo);
    }

    /* ── Password Change ──────────────────────────────────────── */
    private function processPasswordChange(): ProfileActionResult {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword     = (string) ($_POST['new_password']     ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return new ProfileActionResult(false, '❌ সব পাসওয়ার্ড ফিল্ড পূরণ করা আবশ্যক।', ProfileUpdateSection::Password);
        }
        if (!$this->model->verifyCurrentPassword($this->userId, $currentPassword)) {
            return new ProfileActionResult(false, '❌ বর্তমান পাসওয়ার্ড সঠিক নয়।', ProfileUpdateSection::Password);
        }
        if ($newPassword !== $confirmPassword) {
            return new ProfileActionResult(false, '❌ নতুন পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মিলছে না।', ProfileUpdateSection::Password);
        }
        if (strlen($newPassword) < 6) {
            return new ProfileActionResult(false, '❌ পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।', ProfileUpdateSection::Password);
        }
        if ($newPassword === $currentPassword) {
            return new ProfileActionResult(false, '❌ নতুন পাসওয়ার্ড আগেরটার মতো হতে পারবে না।', ProfileUpdateSection::Password);
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $isChanged      = $this->model->changePassword($this->userId, $hashedPassword);
        if ($isChanged) {
            // পাসওয়ার্ড/হ্যাশ কখনো অডিটে সংরক্ষণ করা হয় না
            if (class_exists('AuditLogger')) {
                AuditLogger::update(
                    'profile',
                    $this->userId,
                    ['password' => '[REDACTED]'],
                    ['password' => '[CHANGED]'],
                    "ইউজার #{$this->userId} পাসওয়ার্ড পরিবর্তন"
                );
            }
            return new ProfileActionResult(true, ProfileUpdateSection::Password->successMessage(), ProfileUpdateSection::Password);
        }
        return new ProfileActionResult(false, '❌ পাসওয়ার্ড পরিবর্তন করতে সমস্যা হয়েছে।', ProfileUpdateSection::Password);
    }

    /* ── Picture Upload ───────────────────────────────────────── */
    private function processPictureUpdate(): ProfileActionResult {
        if (!isset($_FILES['profile_pic'])) {
            return new ProfileActionResult(false, '❌ ফাইল ডেটা পাওয়া যায়নি।', ProfileUpdateSection::Picture);
        }

        $uploadErrorCode = (int) ($_FILES['profile_pic']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadErrorCode !== UPLOAD_ERR_OK) {
            $errorMessage = match($uploadErrorCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '❌ ছবির সাইজ সার্ভারের সীমার বেশি।',
                UPLOAD_ERR_PARTIAL    => '❌ ছবি আংশিক আপলোড হয়েছে। আবার চেষ্টা করুন।',
                UPLOAD_ERR_NO_FILE    => '❌ কোনো ছবি নির্বাচন করা হয়নি।',
                UPLOAD_ERR_NO_TMP_DIR => '❌ সার্ভারে temporary ফোল্ডার নেই।',
                UPLOAD_ERR_CANT_WRITE => '❌ সার্ভারে লেখার অনুমতি নেই।',
                default               => "❌ আপলোড সমস্যা (error code: {$uploadErrorCode})।",
            };
            $this->model->logError(
                "Upload error={$uploadErrorCode} userId={$this->userId}",
                new \RuntimeException($errorMessage)
            );
            return new ProfileActionResult(false, $errorMessage, ProfileUpdateSection::Picture);
        }

        $uploadedFile = $_FILES['profile_pic'];

        if (empty($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            return new ProfileActionResult(false, '❌ Temporary ফাইল পাওয়া যায়নি।', ProfileUpdateSection::Picture);
        }

        $fileSize = (int) $uploadedFile['size'];
        if ($fileSize <= 0) {
            return new ProfileActionResult(false, '❌ ছবিটি খালি (0 বাইট)।', ProfileUpdateSection::Picture);
        }
        if ($fileSize > self::MAX_UPLOAD_BYTES) {
            $fileSizeMb = round($fileSize / 1024 / 1024, 2);
            return new ProfileActionResult(
                false,
                "❌ ছবির সাইজ ({$fileSizeMb} MB) সর্বোচ্চ ২০ MB-এর বেশি।",
                ProfileUpdateSection::Picture
            );
        }

        $fileInfoReader = new \finfo(FILEINFO_MIME_TYPE);
        $realMimeType   = $fileInfoReader->file($uploadedFile['tmp_name']);
        if ($realMimeType === false || !in_array($realMimeType, self::ALLOWED_MIME_TYPES, strict: true)) {
            $this->model->logError(
                "Invalid MIME='{$realMimeType}' userId={$this->userId}",
                new \RuntimeException('Rejected')
            );
            return new ProfileActionResult(
                false,
                '❌ শুধুমাত্র JPG, PNG, GIF বা WEBP ছবি আপলোড করা যাবে।',
                ProfileUpdateSection::Picture
            );
        }

        // ─────────────────────────────────────────────────────────
        // FIX 1 প্রয়োগ: $this->publicHtmlRoot = public_html/
        // uploads/ ফোল্ডার public_html/ এর ভেতরে থাকে।
        // ─────────────────────────────────────────────────────────
        $uploadDirectory = $this->publicHtmlRoot
            . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'profile_pics'
            . DIRECTORY_SEPARATOR;

        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            $this->model->logError(
                "Cannot create dir={$uploadDirectory}",
                new \RuntimeException('mkdir failed')
            );
            return new ProfileActionResult(false, '❌ আপলোড ফোল্ডার তৈরি করা যায়নি।', ProfileUpdateSection::Picture);
        }
        if (!is_writable($uploadDirectory)) {
            $this->model->logError(
                "Dir not writable={$uploadDirectory}",
                new \RuntimeException('Permission denied')
            );
            return new ProfileActionResult(false, '❌ আপলোড ফোল্ডারে লেখার অনুমতি নেই।', ProfileUpdateSection::Picture);
        }

        $fileExtension   = self::MIME_TO_EXTENSION[$realMimeType] ?? 'jpg';
        $uniqueFileName  = 'user_' . $this->userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
        $destinationPath = $uploadDirectory . $uniqueFileName;
        $publicFilePath  = 'uploads/profile_pics/' . $uniqueFileName;

        if (!$this->imageResizer->resizeAndSave($uploadedFile['tmp_name'], $realMimeType, $destinationPath)) {
            $this->model->logError(
                "ImageResizer failed userId={$this->userId} dest={$destinationPath}",
                new \RuntimeException('resizeAndSave returned false')
            );
            return new ProfileActionResult(
                false,
                '❌ ছবি প্রক্রিয়া করতে ব্যর্থ। সার্ভারে GD extension আছে কিনা চেক করুন।',
                ProfileUpdateSection::Picture
            );
        }

        // পুরনো path DB update-এর আগেই সংরক্ষণ করো
        $currentUserData = $this->model->getUserById($this->userId);
        $oldPicturePath  = ($currentUserData !== null && !empty($currentUserData['profile_pic']))
            ? (string) $currentUserData['profile_pic']
            : '';

        $isDbUpdated = $this->model->updateProfilePicture($this->userId, $publicFilePath);
        if (!$isDbUpdated) {
            // DB ব্যর্থ → নতুন ফাইল rollback করো
            if (file_exists($destinationPath)) unlink($destinationPath);
            $this->model->logError(
                "DB update failed userId={$this->userId}",
                new \RuntimeException('updateProfilePicture returned false')
            );
            return new ProfileActionResult(
                false,
                '❌ ডেটাবেসে ছবির পথ সেভ করতে সমস্যা হয়েছে।',
                ProfileUpdateSection::Picture
            );
        }

        // ─────────────────────────────────────────────────────────
        // FIX 2 প্রয়োগ: DB সফলের পরপরই session-এ নতুন path বসাও।
        // Redirect-এর পর নতুন request-এ syncProfilePicToSession()
        // session non-empty দেখে DB call skip করবে — overwrite হবে না।
        // ─────────────────────────────────────────────────────────
        $_SESSION['profile_pic'] = $publicFilePath;

        // DB সফলের পরেই পুরনো ছবি delete করো (default_user.png বাদে)
        if ($oldPicturePath !== '' && $oldPicturePath !== 'default_user.png') {
            $this->deleteOldPictureSafely($oldPicturePath);
        }

        if (class_exists('AuditLogger')) {
            AuditLogger::update(
                'profile',
                $this->userId,
                ['profile_pic' => $oldPicturePath !== '' ? $oldPicturePath : null],
                ['profile_pic' => $publicFilePath],
                "ইউজার #{$this->userId} প্রোফাইল ছবি আপডেট"
            );
        }

        return new ProfileActionResult(true, ProfileUpdateSection::Picture->successMessage(), ProfileUpdateSection::Picture);
    }

    // ─────────────────────────────────────────────────────────────
    // FIX 3: $this->publicHtmlRoot ব্যবহার করে সঠিক path check
    // ─────────────────────────────────────────────────────────────
    private function deleteOldPictureSafely(string $oldPath): void {
        if ($oldPath === '' || str_starts_with($oldPath, 'http')) return;
        if (!str_starts_with($oldPath, 'uploads/')) return;

        $fullPath    = $this->publicHtmlRoot . DIRECTORY_SEPARATOR . $oldPath;
        $resolved    = realpath($fullPath);
        $allowedDir  = realpath(
            $this->publicHtmlRoot
            . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'profile_pics'
        );

        if ($resolved === false || $allowedDir === false) return;

        // Path Traversal Protection
        if (!str_starts_with($resolved, $allowedDir . DIRECTORY_SEPARATOR)) {
            $this->model->logError(
                "Path traversal blocked: {$oldPath}",
                new \RuntimeException('Outside allowed dir')
            );
            return;
        }
        if (!is_file($resolved)) return;
        if (!unlink($resolved)) {
            $this->model->logError("unlink failed: {$resolved}", new \RuntimeException('Cannot delete'));
        }
    }
}
