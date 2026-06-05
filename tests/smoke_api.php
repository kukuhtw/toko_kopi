<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/runtime.php';

use KopiBot\Core\Router;

$router = new Router();

$router->get('/api/health', fn() => ['success' => true]);
$router->post('/api/auth/register', fn() => []);
$router->post('/api/auth/login', fn() => []);
$router->get('/api/products', fn() => []);
$router->post('/api/cart/add', fn() => []);
$router->post('/api/cart/checkout', fn() => []);
$router->post('/api/chatbot/message', fn() => []);

$checks = [
    'health_route' => $router->hasRoute('GET', '/api/health'),
    'register_route' => $router->hasRoute('POST', '/api/auth/register'),
    'login_route' => $router->hasRoute('POST', '/api/auth/login'),
    'products_route' => $router->hasRoute('GET', '/api/products'),
    'cart_add_route' => $router->hasRoute('POST', '/api/cart/add'),
    'cart_checkout_route' => $router->hasRoute('POST', '/api/cart/checkout'),
    'chatbot_route' => $router->hasRoute('POST', '/api/chatbot/message'),
];

$success = !in_array(false, $checks, true);

echo json_encode([
    'success' => $success,
    'checks' => $checks,
], JSON_PRETTY_PRINT) . PHP_EOL;

exit($success ? 0 : 1);
