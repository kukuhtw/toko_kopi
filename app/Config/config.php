<?php

declare(strict_types=1);

// ============================================================
// Application Configuration
// ============================================================

// Load .env before any $_ENV reads (values from server env take priority)
require_once __DIR__ . '/Env.php';
\App\Config\Env::load(dirname(__DIR__, 2) . '/.env');

// --- Database ---
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'toko_kopi');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// --- App ---
define('APP_NAME',    'Toko Kopi');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    rtrim($_ENV['BASE_URL'] ?? 'http://localhost/toko_kopi/public', '/'));
define('BASE_PATH',   dirname(__DIR__, 2));
define('APP_PATH',    BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('STORAGE_PATH',BASE_PATH . '/storage');
define('LOG_PATH',    STORAGE_PATH . '/logs');

// --- Session ---
define('SESSION_NAME',     'toko_kopi_sess');
define('SESSION_LIFETIME', 7200);

// --- Security ---
define('CSRF_TOKEN_NAME', '_csrf_token');
define('BCRYPT_COST',     12);

// --- Timezone ---
date_default_timezone_set('Asia/Jakarta');

// --- Supported currencies ---
define('SUPPORTED_CURRENCIES', ['IDR', 'USD', 'SGD', 'AUD']);
define('SUPPORTED_LANGUAGES',  ['id', 'en']);

// --- Error handling ---
if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php_error.log');

// --- Autoloader ---
spl_autoload_register(function (string $class): void {
    // Convert namespace to file path
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// --- Plugin System ---
\App\Plugin\PluginLoader::init(BASE_PATH . '/plugins');
