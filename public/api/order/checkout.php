<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/runtime.php';

use KopiBot\Core\Response;
use KopiBot\Domains\Order\CheckoutService;

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json([
        'success' => false,
        'message' => 'Method not allowed',
    ], 405);
    return;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    $body = $_POST;
}

$service = new CheckoutService();
$result = $service->checkoutWebCart($body);

if (!empty($result['success'])) {
    Response::json([
        'success' => true,
        'message' => (string) ($result['message'] ?? 'Order created successfully'),
        'data' => $result['data'] ?? null,
    ]);
    return;
}

$message = (string) ($result['message'] ?? 'Failed to create order');
$status = 422;

if ($message === 'branch_id is required' || $message === 'Cart not found' || $message === 'Cart is empty') {
    $status = 400;
}

Response::json([
    'success' => false,
    'message' => $message,
    'error' => $result['error'] ?? null,
], $status);
