<?php

declare(strict_types=1);

require_once __DIR__ . '/plugin.php';

$branchId = (int)($argv[1] ?? $_GET['branch'] ?? 1);
$repo = new ShopeeLiveRepository();
$repo->ensureSchema();
$repo->logSync($branchId, 'cron', 'CRON_SYNC', 'success', null, ['message' => 'Shopee cron sync scaffold executed'], [], 'system');

header('Content-Type: application/json');
echo json_encode(['success' => true, 'branch_id' => $branchId, 'message' => 'Shopee cron sync scaffold executed']);
