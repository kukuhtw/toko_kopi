<?php

declare(strict_types=1);

namespace KopiBot\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$method]['/' . trim($path, '/')] = $handler;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();
        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler) {
            Response::json([
                'success' => false,
                'message' => 'Route not found',
                'method' => $method,
                'path' => $path,
            ], 404);
            return;
        }

        $result = $handler($request);

        if (is_array($result) && !headers_sent()) {
            Response::json($result);
        }
    }

    public function auth(Request $request): ?array
    {
        $user = (new AuthMiddleware())->user();

        if (!$user) {
            Response::json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
            return null;
        }

        return $user;
    }

    public function permission(array $user, string $permissionCode): bool
    {
        return (new PermissionMiddleware())->check($user, $permissionCode);
    }
}
