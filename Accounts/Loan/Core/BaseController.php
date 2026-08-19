<?php

declare(strict_types=1);

/**
 * BaseController
 *
 * Everything every module controller needs: login enforcement, CSRF
 * verification for writes, view rendering with a shared layout, and
 * uniform JSON responses. Module controllers only implement business
 * actions — never plumbing.
 */
abstract class BaseController
{
    protected PDO $db;

    /** Actions reachable directly from a URL. Anything not listed is 404. */
    protected array $publicActions = [];

    public function __construct()
    {
        $this->requireLogin();
        $this->db = Database::getConnection();
    }

    public function isPublicAction(string $action): bool
    {
        return in_array($action, $this->publicActions, true);
    }

    /**
     * Handles every POST request for the module. Verifies CSRF once here
     * so no individual action can forget to do it.
     */
    public function api(array $postData): void
    {
        if (!Security::verifyCsrf($postData['csrf_token'] ?? null)) {
            Response::json(['status' => 'error', 'message' => 'Security Error. Please refresh the page and try again.'], 403);
        }

        $action = (string)($postData['action'] ?? '');

        try {
            $this->handleApi($action, $postData);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error(static::class . ' :: ' . $action . ' :: ' . $e->getMessage());
            Response::json(['status' => 'error', 'message' => 'Server encountered a temporary error.'], 500);
        }
    }

    /** Each module defines its own POST actions. */
    abstract protected function handleApi(string $action, array $postData): void;

    protected function requireLogin(): void
    {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            if (Request::isAjax()) {
                Response::json(['status' => 'error', 'message' => 'Session expired. Please log in again.'], 401);
            }
            header('Location: /index.php');
            exit;
        }
    }

    /** আপনার সিস্টেমের ডিফল্ট রোল 'viewer' (db_connect.php অনুযায়ী)। */
    protected function currentRole(): string
    {
        return (string)($_SESSION['role'] ?? 'viewer');
    }

    protected function currentUsername(): string
    {
        return (string)($_SESSION['username'] ?? '');
    }

    /**
     * কন্ট্রোলার ক্লাসের নাম থেকে URL-এর মডিউল অংশ বের করে।
     * LoanController → 'loan', RepaymentController → 'repayment'।
     */
    protected function moduleSlug(): string
    {
        $short = (new ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/Controller$/', '', $short));
    }

    /**
     * Renders a module view inside the shared header/footer layout.
     *
     * @param string $viewPath e.g. 'Loan/Views/dashboard'
     * @param array<string, mixed> $data extracted into the view's scope
     */
    protected function render(string $viewPath, array $data = [], bool $withLayout = true): void
    {
        $file = APP_ROOT . '/Modules/' . $viewPath . '.php';
        if (!is_file($file)) {
            Logger::error('View not found: ' . $file);
            http_response_code(500);
            echo 'ভিউ ফাইল পাওয়া যায়নি।';
            return;
        }

        $data['csrfToken'] = Security::token();
        $data['baseUrl']   = APP_BASE_URL;
        $data['userRole']  = $this->currentRole();

        // মডিউলের POST endpoint সার্ভারেই ঠিক করা হয় (যেমন
        // '/Accounts/Loan/loan')। ফলে ফ্রন্ট-এন্ডকে ব্রাউজারের URL
        // থেকে অনুমান করতে হয় না — ট্রেইলিং স্ল্যাশ বা যেকোনো পাথেই
        // AJAX সঠিক জায়গায় যায়।
        $data['moduleEndpoint'] = APP_BASE_URL . '/' . $this->moduleSlug();

        extract($data, EXTR_SKIP);

        if ($withLayout) {
            require APP_ROOT . '/Views/layouts/header.php';
        }
        require $file;
        if ($withLayout) {
            require APP_ROOT . '/Views/layouts/footer.php';
        }
    }

    protected function success(array $payload = [], string $message = ''): void
    {
        Response::json(array_merge(['status' => 'success', 'message' => $message], $payload));
    }

    protected function fail(string $message, int $httpCode = 200): void
    {
        Response::json(['status' => 'error', 'message' => $message], $httpCode);
    }
}
