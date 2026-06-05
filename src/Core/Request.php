<?php

declare(strict_types=1);

namespace KopiBot\Core;

class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');

        if ($scriptName !== '/' && str_starts_with($uri, $scriptName)) {
            $uri = substr($uri, strlen($scriptName));
        }

        return '/' . trim($uri, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $data = $this->all();

        return $data[$key] ?? $default;
    }

    public function all(): array
    {
        $json = json_decode(file_get_contents('php://input') ?: '', true);

        if (is_array($json)) {
            return array_merge($_GET, $_POST, $json);
        }

        return array_merge($_GET, $_POST);
    }
}
