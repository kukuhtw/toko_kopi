<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/runtime.php';

use KopiBot\Domains\AI\ConversationContext;
use KopiBot\Domains\AI\ConversationMemoryRepository;
use KopiBot\Domains\AI\ConversationMemoryService;

final class InMemoryConversationMemoryRepository extends ConversationMemoryRepository
{
    private int $nextSessionId = 1;
    private int $nextMessageId = 1;
    private array $sessions = [];
    private array $messages = [];

    public function __construct()
    {
    }

    public function findOrCreateSession(ConversationContext $context): int
    {
        $key = $context->tenantId . ':' . $context->branchId . ':' . $context->sessionKey();

        if (!isset($this->sessions[$key])) {
            $this->sessions[$key] = $this->nextSessionId++;
        }

        return $this->sessions[$key];
    }

    public function addMessage(int $tenantId, int $sessionId, string $role, string $message, array $meta = []): int
    {
        $messageId = $this->nextMessageId++;

        $this->messages[$sessionId][] = [
            'id' => $messageId,
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'role' => $role,
            'message' => $message,
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        return $messageId;
    }

    public function recentMessages(int $tenantId, int $sessionId, int $limit = 10): array
    {
        return array_slice($this->messages[$sessionId] ?? [], -$limit);
    }
}

$repository = new InMemoryConversationMemoryRepository();
$service = new ConversationMemoryService($repository);
$context = new ConversationContext(
    tenantId: 1,
    branchId: 1,
    channel: 'api',
    senderId: 'guest-001',
    customerId: null
);

$user = $service->rememberUserMessage($context, 'Saya mau pesan cappuccino', [
    'source' => 'integration_test',
]);

$assistant = $service->rememberAssistantMessage($context, 'Baik, cappuccino ditambahkan.', [
    'intent' => 'order_intent',
]);

$recent = $service->recent($context, 10);

$checks = [
    'session_key' => $context->sessionKey() === 'api:guest-001',
    'same_session' => $user['session_id'] === $assistant['session_id'],
    'user_message_id' => $user['message_id'] === 1,
    'assistant_message_id' => $assistant['message_id'] === 2,
    'recent_count' => count($recent['messages']) === 2,
    'recent_first_role' => ($recent['messages'][0]['role'] ?? '') === 'user',
    'recent_second_role' => ($recent['messages'][1]['role'] ?? '') === 'assistant',
];

$success = !in_array(false, $checks, true);

echo json_encode([
    'success' => $success,
    'checks' => $checks,
], JSON_PRETTY_PRINT) . PHP_EOL;

exit($success ? 0 : 1);
