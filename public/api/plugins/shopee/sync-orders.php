<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json(['success' => false, 'message' => 'Method not allowed'], 405);
    return;
}

$branchId = (int) ($_GET['branch'] ?? 0);
$payload = json_decode(file_get_contents('php://input') ?: '', true);
$payload = is_array($payload) ? $payload : $_POST;

$result = $shopeeService->ingestOrder($branchId, $payload);
$status = !empty($result['success']) ? 200 : 422;

Response::json($result, $status);
