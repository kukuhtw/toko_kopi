<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$branchId = (int) ($_GET['branch'] ?? 0);
$payload = [
    'shop_id' => $_GET['shop_id'] ?? null,
    'merchant_name' => $_GET['merchant_name'] ?? null,
    'access_token' => $_GET['access_token'] ?? null,
    'refresh_token' => $_GET['refresh_token'] ?? null,
    'expire_in' => $_GET['expire_in'] ?? null,
    'refresh_token_expire_in' => $_GET['refresh_token_expire_in'] ?? null,
];

$result = $shopeeService->handleCallback($branchId, $payload);
$status = !empty($result['success']) ? 200 : 422;

Response::json($result, $status);
