<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/plugin.php';

$branchId = (int)($_GET['branch'] ?? 0);
$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

try {
    $repo = new TikTokShopLiveRepository();
    $client = new TikTokShopLiveClient([]);
    $service = new TikTokShopLiveService($repo, $client);
    $result = $service->processWebhook($branchId, $payload);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
