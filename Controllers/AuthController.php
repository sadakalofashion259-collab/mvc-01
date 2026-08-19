<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/UserModel.php';
require_once __DIR__ . '/../Models/MailService.php';

class AuthController {
    private UserModel   $userModel;
    private MailService $mailService;
    private PDO         $db;

    public function __construct(PDO $db) {
        $this->db          = $db;
        $this->userModel   = new UserModel($db);
        $this->mailService = new MailService();
    }

    private function log(string $msg): void {
        $dir = __DIR__ . '/../Logs';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/error_log.txt',
            '[' . date('Y-m-d H:i:s') . '] AUTH: ' . $msg . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    // =============================================
    // ৫ মিনিটে একটাই OTP — চেক করা
    // =============================================
    private function hasActiveOtp(string $email): bool {
        $stmt = $this->db->prepare(
            "SELECT id FROM otp_requests
             WHERE email = ? AND is_used = 0
             AND expires_at >= NOW()
             AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$email]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    // বাকি সময় কত সেকেন্ড
    private function getOtpCooldownSeconds(string $email): int {
        $stmt = $this->db->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(created_at, INTERVAL 5 MINUTE)) as remaining
             FROM otp_requests
             WHERE email = ? AND is_used = 0 AND expires_at >= NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? max(0, (int)$row['remaining']) : 0;
    }

    // =============================================
    // OTP Request — Rate limit + otp_requests সেভ + SMS
    // =============================================
    public function processOtpRequest(string $identifier, bool $isSetup, ?int $setupId): array
    {
        if (empty(trim($identifier))) {
            return ['success' => false, 'message' => "<div class='alert error'>❌ মোবাইল নম্বর, ইমেইল বা ইউজারনেম দিন!</div>"];
        }

        try {
            $this->log("OTP request: {$identifier}");

            $user = $this->userModel->findByIdentifier($identifier);

            if (!$user) {
                $this->log("User NOT found: {$identifier}");
                return ['success' => false, 'message' => "<div class='alert error'>❌ এই তথ্য দিয়ে কোনো অ্যাকাউন্ট পাওয়া যায়নি!</div>"];
            }

            if (empty($user['email'])) {
                return ['success' => false, 'message' => "<div class='alert error'>❌ এই অ্যাকাউন্টে ইমেইল নেই! অ্যাডমিনের সাথে যোগাযোগ করুন।</div>"];
            }

            // ✅ ৫ মিনিট rate limit চেক
            if ($this->hasActiveOtp($user['email'])) {
                $remaining = $this->getOtpCooldownSeconds($user['email']);
                $minutes   = ceil($remaining / 60);
                $this->log("Rate limit hit for: {$user['email']} | Remaining: {$remaining}s");
                return ['success' => false, 'message' => "<div class='alert error'>⏳ OTP ইতোমধ্যে পাঠানো হয়েছে। আরও <b>{$remaining}</b> সেকেন্ড পর আবার চেষ্টা করুন।</div>"];
            }

            // phone নেওয়া
            $phone = !empty($user['phone'])   ? $user['phone']
                   : (!empty($user['mobile']) ? $user['mobile'] : '');

            $otp  = (string)random_int(100000, 999999);

            // otp_requests এ email + phone + otp সেভ
            $saved = $this->userModel->createOtp($user['email'], $otp, $phone);
            $this->log("OTP saved: " . ($saved ? 'YES' : 'NO') . " | OTP: {$otp} | Phone: {$phone}");

            if (!$saved) {
                return ['success' => false, 'message' => "<div class='alert error'>❌ OTP তৈরিতে সমস্যা হয়েছে!</div>"];
            }

            // ✅ Session এ step + email রাখা — refresh হলেও step 2 এ থাকবে
            $_SESSION['auth_email']    = $user['email'];
            $_SESSION['otp_sent_at']   = time();
            $_SESSION['otp_step']      = 2;

            // Email
            $name      = $user['username'] ?? 'User';
            $emailSent = $this->mailService->sendOtpEmail($user['email'], $name, $otp);
            $this->log("Email: " . ($emailSent ? 'OK' : 'FAIL'));

            // SMS
            $smsSent = false;
            if (!empty($phone)) {
                $smsSent = $this->mailService->sendOtpSms($phone, $otp);
                $this->log("SMS to {$phone}: " . ($smsSent ? 'OK' : 'FAIL'));
            } else {
                $this->log("No phone for: {$user['email']}");
            }

            $channel = ($emailSent && $smsSent) ? 'ইমেইল ও SMS-এ'
                     : ($smsSent   ? 'SMS-এ'
                     : ($emailSent ? 'ইমেইলে'
                     : 'তৈরি হয়েছে'));

            return ['success' => true, 'message' => "<div class='alert success'>✅ OTP {$channel} পাঠানো হয়েছে! ৫ মিনিটের মধ্যে কোড দিন।</div>"];

        } catch (\PDOException $e) {
            $this->log("DB ERROR: " . $e->getMessage());
            return ['success' => false, 'message' => "<div class='alert error'>❌ সার্ভার এরর! কিছুক্ষণ পর আবার চেষ্টা করুন।</div>"];
        } catch (\Throwable $e) {
            $this->log("ERROR: " . $e->getMessage() . " L:" . $e->getLine());
            return ['success' => false, 'message' => "<div class='alert error'>❌ সার্ভার এরর! কিছুক্ষণ পর আবার চেষ্টা করুন।</div>"];
        }
    }

    // =============================================
    // OTP Verify + Password Reset
    // =============================================
    public function verifyAndReset(string $email, string $otp, ?string $pass = null): array
    {
        try {
            if ($pass === null) {
                if ($this->userModel->verifyOtp($email, $otp)) {
                    $_SESSION['otp_step'] = 3;
                    return ['success' => true, 'message' => "✅ Verification Successful!"];
                }
                return ['success' => false, 'message' => "<div class='alert error'>❌ ভুল ওটিপি কোড অথবা মেয়াদ শেষ!</div>"];
            }

            if ($otp === 'forced_skip' && $pass !== null) {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $this->db->beginTransaction();

                $stmt = $this->db->prepare(
                    "UPDATE users
                     SET password = ?, is_verified = 1,
                         failed_attempts = 0, lock_until = NULL
                     WHERE email = ?"
                );

                if ($stmt->execute([$hashed, $email])) {
                    $this->db->commit();

                    $user  = $this->userModel->findByIdentifier($email);
                    $name  = $user['username'] ?? 'User';
                    $this->mailService->sendProfileUpdateConfirm($email, $name);

                    $phone = !empty($user['phone'])   ? $user['phone']
                           : (!empty($user['mobile']) ? $user['mobile'] : '');
                    if (!empty($phone)) {
                        $this->mailService->sendOtpSms($phone, 'PwdChanged');
                    }

                    // Session পরিষ্কার
                    unset($_SESSION['auth_email'], $_SESSION['otp_sent_at'], $_SESSION['otp_step']);

                    $this->log("Password reset OK: {$email}");
                    return ['success' => true, 'message' => "✅ পাসওয়ার্ড সফলভাবে আপডেট হয়েছে!"];
                }
                $this->db->rollBack();
            }

            return ['success' => false, 'message' => "<div class='alert error'>❌ সিস্টেম এরর!</div>"];

        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->log("Reset DB Error: " . $e->getMessage());
            return ['success' => false, 'message' => "<div class='alert error'>❌ ডাটাবেজ এরর!</div>"];
        }
    }
}
?>
