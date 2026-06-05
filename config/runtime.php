<?php

declare(strict_types=1);

use KopiBot\App;

require_once dirname(__DIR__) . '/bootstrap.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}

if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', BASE_PATH . '/uploads');
}

if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', BASE_PATH . '/storage');
}

if (!defined('LOG_PATH')) {
    define('LOG_PATH', STORAGE_PATH . '/logs');
}

if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0775, true);
}

if (!defined('APP_NAME')) {
    define('APP_NAME', (string) env_value('APP_NAME', 'KopiBot'));
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', (string) env_value('APP_VERSION', '1.0.0'));
}

if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim((string) env_value('APP_URL', env_value('BASE_URL', 'http://localhost:8000')), '/'));
}

if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', (string) env_value('SESSION_NAME', 'kopibot_sess'));
}

if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', (int) env_value('SESSION_LIFETIME', 7200));
}

if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', '_csrf_token');
}

if (!defined('BCRYPT_COST')) {
    define('BCRYPT_COST', (int) env_value('BCRYPT_COST', 12));
}

if (!defined('SUPPORTED_CURRENCIES')) {
    define('SUPPORTED_CURRENCIES', ['IDR', 'USD', 'SGD', 'AUD']);
}

if (!defined('SUPPORTED_LANGUAGES')) {
    define('SUPPORTED_LANGUAGES', ['id', 'en']);
}

if (!defined('DB_HOST')) {
    define('DB_HOST', (string) env_value('DB_HOST', env_value('DB_HOSTNAME', '127.0.0.1')));
}

if (!defined('DB_PORT')) {
    define('DB_PORT', (string) env_value('DB_PORT', '3306'));
}

if (!defined('DB_NAME')) {
    define('DB_NAME', (string) env_value('DB_DATABASE', env_value('DB_NAME', 'kopibot')));
}

if (!defined('DB_USER')) {
    define('DB_USER', (string) env_value('DB_USERNAME', env_value('DB_USER', 'root')));
}

if (!defined('DB_PASS')) {
    define('DB_PASS', (string) env_value('DB_PASSWORD', env_value('DB_PASS', '')));
}

date_default_timezone_set((string) env_value('APP_TIMEZONE', 'Asia/Jakarta'));

if ((string) env_value('APP_DEBUG', 'false') === 'true' || (string) env_value('APP_ENV', 'production') === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php_error.log');
