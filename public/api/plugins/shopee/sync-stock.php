<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$menuItemId = (int) ($_GET['menu_item_id'] ?? 0);
if ($menuItemId <= 0) {
    Response::json(['success' => false, 'message' => 'menu_item_id required.'], 422);
    return;
}

$result = $shopeeService->buildStockPayload($menuItemId);
$status = !empty($result['success']) ? 200 : 422;

Response::json($result, $status);
