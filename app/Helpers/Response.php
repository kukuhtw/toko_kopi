<?php

declare(strict_types=1);

namespace App\Helpers;

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            $errMsg  = json_last_error_msg();
            $logLine = '[' . date('Y-m-d H:i:s') . '] json_encode failed: ' . $errMsg . "\n";
            @file_put_contents(dirname(__DIR__, 2) . '/storage/logs/php_error.log', $logLine, FILE_APPEND | LOCK_EX);
            http_response_code(500);
            echo '{"success":false,"message":"Response encoding error: ' . addslashes($errMsg) . '","errors":null}';
            exit;
        }
        echo $encoded;
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK'): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): never
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}
