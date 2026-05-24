<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/Config/config.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryRepository.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryClient.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryService.php';

$branchId = (int)($_GET['branch'] ?? 0);
if ($branchId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'branch is required']);
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);

$repo = new GoSendDeliveryRepository();
$client = new GoSendDeliveryClient($repo);
$service = new GoSendDeliveryService($repo, $client);

$signature = $_SERVER['HTTP_X_GOSEND_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? null;
if (!$client->verifyWebhookSignature($branchId, $rawBody, $signature ? (string)$signature : null)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'invalid signature']);
    exit;
}

try {
    $result = $service->handleInboundWebhook($branchId, is_array($payload) ? $payload : $rawBody);
    header('Content-Type: application/json');
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
