<?php

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/plugin.php';

$branchId = (int)($_GET['branch'] ?? 1);

try {
    $query = new ShopeeDashboardQuery();

    echo json_encode([
        'success' => true,
        'metrics' => $query->getLatestMetrics($branchId, 20),
        'orders' => $query->getRecentOrders($branchId, 20),
        'logs' => $query->getSyncLogs($branchId, 20),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
