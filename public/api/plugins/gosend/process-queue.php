<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/Config/config.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryRepository.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryClient.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryService.php';

$repo = new GoSendDeliveryRepository();
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$expectedToken = $repo->getGlobalSetting('runner_token');

if ($expectedToken !== '' && !hash_equals($expectedToken, $token)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'invalid token']);
    exit;
}

$branchId = (int)($_GET['branch'] ?? $_POST['branch'] ?? 0);
$limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 20);

$service = new GoSendDeliveryService($repo);
$result = $service->processPendingQueue($branchId > 0 ? $branchId : null, $limit);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $result]);
