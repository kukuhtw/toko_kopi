<?php

declare(strict_types=1);

/**
 * Shared bootstrap for all API endpoints.
 * Include this at top of every API file.
 */

require_once dirname(__DIR__, 2) . '/app/Config/config.php';

// Prevent any PHP notices/warnings from polluting the JSON response body.
// This must run AFTER config.php (which may re-enable display_errors for dev mode).
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Catch fatal errors (OOM, timeout, etc.) that bypass try-catch blocks.
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $logLine = '[' . date('Y-m-d H:i:s') . '] FATAL ' . $err['type'] . ': ' . $err['message']
                 . ' in ' . $err['file'] . ':' . $err['line'] . "\n";
        @file_put_contents(dirname(__DIR__, 2) . '/storage/logs/php_error.log', $logLine, FILE_APPEND | LOCK_EX);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        if (ob_get_level()) { ob_end_clean(); }
        echo '{"success":false,"message":"Fatal error: ' . addslashes($err['message']) . '","errors":null}';
    }
});

use App\Helpers\Auth;

Auth::startSession();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
