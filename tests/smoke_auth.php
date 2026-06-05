<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\Auth\AuthService;
use KopiBot\Domains\Auth\UserDTO;

$action = (string) ($_GET['action'] ?? 'register');
$service = new AuthService();

if ($action === 'login') {
    $result = $service->login(
        tenantId: (int) ($_GET['tenant_id'] ?? 1),
        email: (string) ($_GET['email'] ?? 'admin@example.com'),
        password: (string) ($_GET['password'] ?? 'secret123')
    );
} else {
    $result = $service->register(new UserDTO(
        tenantId: (int) ($_GET['tenant_id'] ?? 1),
        name: (string) ($_GET['name'] ?? 'Demo Admin'),
        email: (string) ($_GET['email'] ?? 'admin@example.com'),
        password: (string) ($_GET['password'] ?? 'secret123'),
        role: (string) ($_GET['role'] ?? 'merchant_admin')
    ));
}

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
