<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/BiometricModel.php';
require_once __DIR__ . '/../Services/WebAuthnService.php';
require_once __DIR__ . '/../Services/SessionGuard.php';
require_once __DIR__ . '/../Services/DeviceInfo.php';
require_once __DIR__ . '/../Services/LoginLogger.php';
// 📧 লগ ইন ইমেইল এলার্ট (নতুন) — ফাইলটি Services/-এ না থাকলেও লগইন কখনো ক্র্যাশ করবে না।
$loginEmailAlertServicePath = __DIR__ . '/../Services/LoginEmailAlert.php';
if (is_file($loginEmailAlertServicePath)) {
    require_once $loginEmailAlertServicePath;
}
unset($loginEmailAlertServicePath);

/**
 * WebAuthnController — বায়োমেট্রিক রেজিস্ট্রেশন ও লগইন (AJAX, JSON)।
 * ────────────────────────────────────────────────────────────────
 *  POST webauthn=reg_options   (লগইন থাকা অবস্থায়)  → ক্রিয়েশন অপশন
 *  POST webauthn=reg_verify    (লগইন থাকা অবস্থায়)  → ক্রেডেনশিয়াল সেভ
 *  POST webauthn=auth_options  (লগইন ছাড়াই)         → অ্যাসারশন অপশন
 *  POST webauthn=auth_verify   (লগইন ছাড়াই)         → যাচাই + সেশন সেট
 *  GET  webauthn=list          (লগইন থাকা অবস্থায়)  → ডিভাইস তালিকা
 *  POST webauthn=delete        (লগইন থাকা অবস্থায়)  → ডিভাইস মুছুন
 */
class WebAuthnController
{
    private \PDO            $db;
    private BiometricModel  $model;
    private WebAuthnService $svc;
    private LoginLogger     $loginLogger;
    private DeviceInfo      $deviceInfo;
    private string          $rpId;
    private string          $rpName = 'Sada Kalo Fashion';

    public function __construct(\PDO $db)
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        $host        = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $this->rpId  = preg_replace('/:\d+$/', '', $host);                 // পোর্ট বাদ
        $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $origins     = [$scheme . '://' . $host];

        $this->db          = $db;
        $this->model       = new BiometricModel($db);
        $this->svc         = new WebAuthnService($this->rpId, $origins);
        $this->loginLogger = new LoginLogger();
        $this->deviceInfo  = DeviceInfo::fromRequest();
    }

    /** index.php থেকে ডাকা হয়: হ্যান্ডল করলে true (এবং exit), নাহলে false */
    public function handle(): bool
    {
        $action = $_POST['webauthn'] ?? $_GET['webauthn'] ?? null;
        if ($action === null) { return false; }

        header('Content-Type: application/json; charset=utf-8');
        if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { ob_end_clean(); } }

        try {
            switch ($action) {
                case 'reg_options':  $this->regOptions();  break;
                case 'reg_verify':   $this->regVerify();   break;
                case 'auth_options': $this->authOptions(); break;
                case 'auth_verify':  $this->authVerify();  break;
                case 'list':         $this->listDevices(); break;
                case 'delete':       $this->deleteDevice();break;
                default: $this->out(['ok' => false, 'msg' => 'অজানা অ্যাকশন।']);
            }
        } catch (\Throwable $e) {
            $this->out(['ok' => false, 'msg' => '❌ ' . $e->getMessage()]);
        }
        return true;
    }

    /* ─────────── helpers ─────────── */
    private function out(array $data): void { echo json_encode($data); exit; }

    private function requireLogin(): int
    {
        if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
            $this->out(['ok' => false, 'msg' => 'লগইন প্রয়োজন।']);
        }
        return (int) $_SESSION['user_id'];
    }

    /**
     * 📧 লগ ইন ইমেইল এলার্ট — ইউজার-সারি থেকে নাম/ইমেইল/ফোন নিয়ে এলার্ট পাঠায়।
     * ইমেইল যায় JSON রেসপন্স ব্রাউজারে পৌঁছানোর পরে (non-blocking)।
     */
    private function fireLoginAlert(string $alertType, array $u, string $note): void
    {
        if (!class_exists('LoginEmailAlert')) { return; } // সার্ভিস ফাইল অনুপস্থিত → নীরবে স্কিপ
        $accountName = (string) ($u['username'] ?? ($u['id'] ?? ''));
        $userEmail   = (string) ($u['email']    ?? '');
        $phoneNumber = (string) ($u['phone']    ?? ($u['mobile'] ?? ''));
        match ($alertType) {
            'success' => LoginEmailAlert::success($accountName, $userEmail, $phoneNumber, $note),
            'blocked' => LoginEmailAlert::blocked($accountName, $userEmail, $phoneNumber, $note),
            default   => null,
        };
    }

    /* ─────────── REGISTRATION ─────────── */
    private function regOptions(): void
    {
        $userId = $this->requireLogin();
        $user   = $this->model->findUserById($userId);
        if ($user === null) { $this->out(['ok' => false, 'msg' => 'ইউজার পাওয়া যায়নি।']); }

        $challenge = WebAuthnService::newChallenge();
        $_SESSION['webauthn_reg_challenge'] = $challenge;

        $exclude = [];
        foreach ($this->model->getCredentialsByUser($userId) as $c) {
            $exclude[] = ['type' => 'public-key', 'id' => $c['credential_id']];
        }

        $this->out([
            'ok'        => true,
            'challenge' => $challenge,
            'rp'        => ['id' => $this->rpId, 'name' => $this->rpName],
            'user'      => [
                'id'          => WebAuthnService::b64uEnc((string) $userId),
                'name'        => (string) ($user['username'] ?? 'user'),
                'displayName' => (string) ($user['username'] ?? 'user'),
            ],
            'pubKeyCredParams'     => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
            'authenticatorSelection' => ['residentKey' => 'preferred', 'userVerification' => 'required'],
            'excludeCredentials'   => $exclude,
            'timeout'              => 60000,
            'attestation'          => 'none',
        ]);
    }

    private function regVerify(): void
    {
        $userId    = $this->requireLogin();
        $challenge = (string) ($_SESSION['webauthn_reg_challenge'] ?? '');
        if ($challenge === '') { $this->out(['ok' => false, 'msg' => 'সেশন মেয়াদ শেষ — আবার চেষ্টা করুন।']); }
        unset($_SESSION['webauthn_reg_challenge']);

        $clientDataJSON    = WebAuthnService::b64uDec((string) ($_POST['clientDataJSON'] ?? ''));
        $attestationObject = WebAuthnService::b64uDec((string) ($_POST['attestationObject'] ?? ''));
        $label             = trim((string) ($_POST['label'] ?? 'আমার ডিভাইস'));
        if ($label === '') { $label = 'আমার ডিভাইস'; }

        $res = $this->svc->verifyRegistration($clientDataJSON, $attestationObject, $challenge);

        if ($this->model->getByCredentialId($res['credentialId']) !== null) {
            $this->out(['ok' => false, 'msg' => 'এই ডিভাইস আগেই রেজিস্টার করা আছে।']);
        }
        $saved = $this->model->saveCredential($userId, $res['credentialId'], $res['publicKey'], (int) $res['signCount'], $label);

        if ($saved) {
            $this->loginLogger->record(
                'BIOMETRIC_ENROLL',
                (string) ($_SESSION['username'] ?? $userId),
                $this->deviceInfo,
                'Device: ' . $label
            );
        }

        $this->out($saved
            ? ['ok' => true, 'msg' => '✅ বায়োমেট্রিক সফলভাবে চালু হয়েছে।']
            : ['ok' => false, 'msg' => 'সেভ করা যায়নি — আবার চেষ্টা করুন।']);
    }

    /* ─────────── AUTHENTICATION (login) ─────────── */
    private function authOptions(): void
    {
        $challenge = WebAuthnService::newChallenge();
        $_SESSION['webauthn_auth_challenge'] = $challenge;

        $allow    = [];
        $username = trim((string) ($_POST['username'] ?? ''));
        if ($username !== '') {
            $user = $this->model->findUserByUsername($username);
            if ($user !== null) {
                foreach ($this->model->getCredentialsByUser((int) $user['id']) as $c) {
                    $allow[] = ['type' => 'public-key', 'id' => $c['credential_id']];
                }
            }
        }

        $this->out([
            'ok'               => true,
            'challenge'        => $challenge,
            'rpId'             => $this->rpId,
            'userVerification' => 'required',
            'timeout'          => 60000,
            'allowCredentials' => $allow,   // খালি হলে ডিসকভারেবল (resident) কি ব্যবহার হয়
        ]);
    }

    private function authVerify(): void
    {
        $challenge = (string) ($_SESSION['webauthn_auth_challenge'] ?? '');
        if ($challenge === '') { $this->out(['ok' => false, 'msg' => 'সেশন মেয়াদ শেষ — আবার চেষ্টা করুন।']); }
        unset($_SESSION['webauthn_auth_challenge']);

        $credId            = (string) ($_POST['id'] ?? '');
        $clientDataJSON    = WebAuthnService::b64uDec((string) ($_POST['clientDataJSON'] ?? ''));
        $authenticatorData = WebAuthnService::b64uDec((string) ($_POST['authenticatorData'] ?? ''));
        $signature         = WebAuthnService::b64uDec((string) ($_POST['signature'] ?? ''));
        if ($credId === '') { $this->out(['ok' => false, 'msg' => 'ক্রেডেনশিয়াল আইডি নেই।']); }

        $cred = $this->model->getByCredentialId($credId);
        if ($cred === null) { $this->out(['ok' => false, 'msg' => 'এই ডিভাইস রেজিস্টার করা নেই।']); }

        $newCount = $this->svc->verifyAuthentication(
            $clientDataJSON, $authenticatorData, $signature, (string) $cred['public_key'], $challenge
        );

        // sign-count anti-clone (lenient): নতুন < পুরোনো হলে সন্দেহজনক
        $old = (int) $cred['sign_count'];
        if ($newCount > 0 && $old > 0 && $newCount <= $old) {
            $this->out(['ok' => false, 'msg' => 'নিরাপত্তা সতর্কতা — আবার চেষ্টা করুন।']);
        }
        $this->model->updateSignCount($credId, $newCount);
        $this->model->touchLastUsed($credId);

        $user = $this->model->findUserById((int) $cred['user_id']);
        if ($user === null) { $this->out(['ok' => false, 'msg' => 'ইউজার পাওয়া যায়নি।']); }

        // স্ট্যাটাস/ব্লক চেক (পাসওয়ার্ড লগইনের মতোই)
        if (($user['status'] ?? 'active') === 'blocked') {
            // 📧 লগ ইন ইমেইল এলার্ট — ব্লক অ্যাকাউন্টে বায়োমেট্রিক লগইনের চেষ্টা (ক্রস ✖)।
            $this->fireLoginAlert('blocked', $user, 'ফিঙ্গারপ্রিন্ট দিয়ে লগইনের চেষ্টা — অ্যাকাউন্ট ব্লক করা');
            $this->out(['ok' => false, 'msg' => 'অ্যাকাউন্ট ব্লক করা — অ্যাডমিনের সাথে যোগাযোগ করুন।']);
        }

        // সেশন সেট — LoginController::processSuccess() এর মতোই
        session_regenerate_id(true);
        $_SESSION['loggedin']    = true;
        $_SESSION['user_id']     = (int) $user['id'];
        $_SESSION['role']        = (string) ($user['role']        ?? 'user');
        $_SESSION['username']    = (string) ($user['username']    ?? '');
        $_SESSION['email']       = (string) ($user['email']       ?? '');
        $_SESSION['profile_pic'] = (string) ($user['profile_pic'] ?? 'default_user.png');

        // Single Active Session — নতুন টোকেন DB + সেশনে (পুরোনো ডিভাইস বাতিল)।
        SessionGuard::issueToken($this->db, (int) $user['id']);

        // Login_Log.txt-এ বায়োমেট্রিক লগইন রেকর্ড।
        $this->loginLogger->record(
            'BIOMETRIC_LOGIN',
            (string) ($user['username'] ?? $user['id']),
            $this->deviceInfo,
            'Fingerprint/Face login'
        );

        // 📧 লগ ইন ইমেইল এলার্ট — সফল বায়োমেট্রিক লগইন (সবুজ ✔): ১ কপি ইউজার + ১ কপি অ্যাডমিন।
        // JSON রেসপন্স আগে ব্রাউজারে যাবে, ইমেইল যাবে তার পরে নীরবে (non-blocking)।
        $this->fireLoginAlert('success', $user, 'ফিঙ্গারপ্রিন্ট (বায়োমেট্রিক) দিয়ে লগইন');

        $this->out(['ok' => true, 'msg' => '✅ লগইন সফল!', 'redirect' => 'dashboard.php']);
    }

    /* ─────────── device management ─────────── */
    private function listDevices(): void
    {
        $userId = $this->requireLogin();
        $this->out(['ok' => true, 'devices' => $this->model->getCredentialsByUser($userId)]);
    }

    private function deleteDevice(): void
    {
        $userId = $this->requireLogin();
        $id     = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) { $this->out(['ok' => false, 'msg' => 'ভুল আইডি।']); }
        $ok = $this->model->deleteCredential($id, $userId);
        $this->out(['ok' => $ok, 'msg' => $ok ? 'ডিভাইস মুছে ফেলা হয়েছে।' : 'মুছতে ব্যর্থ।']);
    }
}