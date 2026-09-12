<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    /** @param array{0: class-string, 1: string} $handler */
    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = App::currentPath();
        $h = $this->routes[$method][$path] ?? null;
        if ($h === null) {
            http_response_code(404);
            View::render('errors/404', ['path' => $path]);
            return;
        }
        [$class, $m] = $h;
        $class::$m();
    }
}
