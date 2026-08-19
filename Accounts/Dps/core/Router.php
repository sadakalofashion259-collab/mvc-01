<?php
/**
 * core/Router.php
 * ─────────────────────────────────────────
 * action নাম → [Controller, method] ম্যাপ করে।
 * public/api.php এই ক্লাসকে কল করে।
 */
declare(strict_types=1);

final class Router
{
    /** @var array<string, array{0:string,1:string}> */
    private array $routes = [];

    public function map(string $action, string $controllerClass, string $method): void
    {
        $this->routes[$action] = [$controllerClass, $method];
    }

    public function has(string $action): bool
    {
        return isset($this->routes[$action]);
    }

    public function dispatch(string $action, PDO $pdo): void
    {
        if (!$this->has($action)) {
            SecurityHelper::jsonError('অজানা action।', 404);
        }
        [$controllerClass, $method] = $this->routes[$action];
        $controller = new $controllerClass($pdo);
        $controller->$method();
    }
}
