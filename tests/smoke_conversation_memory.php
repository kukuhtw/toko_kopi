<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\AI\ConversationContext;
use KopiBot\Domains\AI\ConversationMemoryService;

$context = new ConversationContext(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: (int) ($_GET['branch_id'] ?? 1),
    channel: (string) ($_GET['channel'] ?? 'web'),
    senderId: (string) ($_GET['sender_id'] ?? 'guest'),
    customerId: isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null
);

$service = new ConversationMemoryService();
$service->rememberUserMessage($context, (string) ($_GET['message'] ?? 'Saya mau cappuccino'), ['source' => 'smoke_test']);
$service->rememberAssistantMessage($context, 'Berapa jumlahnya?', ['source' => 'smoke_test']);

$result = $service->recent($context, 10);

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
