<?php

declare(strict_types=1);

require_once __DIR__ . '/plugin.php';

$branchId = (int)($_GET['branch'] ?? 1);
$payload = json_decode(file_get_contents('php://input'), true) ?: [];

$service = new ShopeeLiveService(new ShopeeLiveRepository());

header('Content-Type: application/json');
echo json_encode($service->processWebhook($branchId, $payload));
