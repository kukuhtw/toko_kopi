<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\Chatbot\ChatbotService;
use KopiBot\Domains\Chatbot\ChatMessageDTO;

$result = (new ChatbotService())->process(new ChatMessageDTO(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: (int) ($_GET['branch_id'] ?? 1),
    channel: (string) ($_GET['channel'] ?? 'web'),
    senderId: (string) ($_GET['sender_id'] ?? 'guest'),
    message: (string) ($_GET['message'] ?? 'pesan cappuccino 2'),
    customerId: isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null
));

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
