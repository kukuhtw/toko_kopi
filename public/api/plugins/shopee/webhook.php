<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$branchId = (int) ($_GET['branch'] ?? 0);
$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);

$result = $shopeeService->handleWebhook($branchId, is_array($payload) ? $payload : $rawBody);
$status = !empty($result['success']) ? 200 : 422;

Response::json($result, $status);
