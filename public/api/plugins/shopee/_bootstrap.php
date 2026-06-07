<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/runtime.php';
require_once BASE_PATH . '/plugins/shopee-integration/ShopeeIntegrationRepository.php';
require_once BASE_PATH . '/plugins/shopee-integration/ShopeeIntegrationService.php';

use KopiBot\Core\Response;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$shopeeRepo = new ShopeeIntegrationRepository();
$shopeeRepo->ensureSchema();
$shopeeService = new ShopeeIntegrationService($shopeeRepo);
