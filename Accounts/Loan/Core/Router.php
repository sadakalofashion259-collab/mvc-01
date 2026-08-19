<?php

declare(strict_types=1);

/**
 * Router
 *
 * Maps a clean URL (module/action/param) onto a module controller.
 * Only modules explicitly registered in index.php can ever be reached,
 * so a crafted URL cannot pull in an arbitrary file from the server.
 */
final class Router
{
    /** @var array<string, array{controller:string, defaultAction:string}> */
    private array $routes = [];

    private string $defaultModule = 'loan';
    private string $defaultAction = 'dashboard';

    public function register(string $module, string $controllerClass, string $defaultAction): void
    {
        $this->routes[strtolower($module)] = [
            'controller'    => $controllerClass,
            'defaultAction' => $defaultAction,
        ];
    }

    public function setDefaultRoute(string $module, string $action): void
    {
        $this->defaultModule = strtolower($module);
        $this->defaultAction = $action;
    }

    public function dispatch(string $url): void
    {
        $segments = array_values(array_filter(explode('/', trim($url, '/')), static fn($s) => $s !== ''));

        $module = strtolower($segments[0] ?? $this->defaultModule);
        $action = $segments[1] ?? '';
        $param  = $segments[2] ?? null;

        if (!isset($this->routes[$module])) {
            $this->notFound();
            return;
        }

        $route          = $this->routes[$module];
        $controllerFile = APP_ROOT . '/Modules/' . ucfirst($module) . '/' . $route['controller'] . '.php';
        $modelFile      = APP_ROOT . '/Modules/' . ucfirst($module) . '/' . ucfirst($module) . 'Model.php';

        if (!is_file($controllerFile)) {
            $this->notFound();
            return;
        }

        if (is_file($modelFile)) {
            require_once $modelFile;
        }
        require_once $controllerFile;

        $controllerClass = $route['controller'];
        if (!class_exists($controllerClass)) {
            $this->notFound();
            return;
        }

        /** @var BaseController $controller */
        $controller = new $controllerClass();

        // POST requests always go through the module's api() handler.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->api($_POST);
            return;
        }

        $action = $this->sanitiseAction($action !== '' ? $action : $route['defaultAction']);

        if (!method_exists($controller, $action) || !$controller->isPublicAction($action)) {
            $this->notFound();
            return;
        }

        $controller->{$action}($param);
    }

    /** Strips anything that isn't a plain method-name character. */
    private function sanitiseAction(string $action): string
    {
        return (string)preg_replace('/[^a-zA-Z0-9_]/', '', $action);
    }

    private function notFound(): void
    {
        http_response_code(404);
        if (Request::isAjax()) {
            Response::json(['status' => 'error', 'message' => 'Requested resource was not found.'], 404);
        }
        echo '<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8"><title>404</title></head>'
           . '<body style="font-family:sans-serif;text-align:center;padding:60px;">'
           . '<h1 style="font-size:3rem;margin:0;color:#4f46e5;">404</h1>'
           . '<p>পেজটি খুঁজে পাওয়া যায়নি।</p>'
           . '<a href="' . APP_BASE_URL . '/loan/dashboard" style="color:#4f46e5;font-weight:700;">ড্যাশবোর্ডে ফিরে যান</a>'
           . '</body></html>';
    }
}
