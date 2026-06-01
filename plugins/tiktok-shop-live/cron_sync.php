<?php

declare(strict_types=1);

require_once __DIR__ . '/plugin.php';

$branchId = (int)($argv[1] ?? $_GET['branch'] ?? 1);

try {
    $repo = new TikTokShopLiveRepository();
    $client = new TikTokShopLiveClient([]);

    $orderImporter = new TikTokOrderImportService($repo, $client);
    $liveService = new TikTokShopLiveService($repo, $client);

    $orderResult = $orderImporter->importOrders($branchId);
    $metrics = $liveService->syncLiveMetrics($branchId);

    echo json_encode([
        'success' => true,
        'branch_id' => $branchId,
        'orders' => $orderResult,
        'metrics_count' => count($metrics),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
