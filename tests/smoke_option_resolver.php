<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\AI\ConversationContext;
use KopiBot\Domains\AI\ConversationOptionResolver;

$context = new ConversationContext(
    tenantId: (int) ($_GET['tenant_id'] ?? 1),
    branchId: (int) ($_GET['branch_id'] ?? 1),
    channel: (string) ($_GET['channel'] ?? 'whatsapp'),
    senderId: (string) ($_GET['sender_id'] ?? '628123456789')
);

$resolver = new ConversationOptionResolver();
$resolver->waitForProductOption($context, [
    ['id' => 1, 'name' => 'Cappuccino', 'base_price' => 25000],
    ['id' => 2, 'name' => 'Latte', 'base_price' => 28000],
]);

$result = $resolver->resolveSelection($context, (string) ($_GET['reply'] ?? '2'));

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
