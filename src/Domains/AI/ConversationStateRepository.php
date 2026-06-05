<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

use KopiBot\Core\Database;
use PDO;

class ConversationStateRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getState(ConversationContext $context): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM chat_states WHERE tenant_id = :tenant_id AND branch_id = :branch_id AND session_key = :session_key LIMIT 1');
        $stmt->execute([
            'tenant_id' => $context->tenantId,
            'branch_id' => $context->branchId,
            'session_key' => $context->sessionKey(),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function setState(ConversationContext $context, string $state, array $payload = []): void
    {
        $existing = $this->getState($context);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($existing) {
            $stmt = $this->db->prepare('UPDATE chat_states SET state = :state, payload_json = :payload_json, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'id' => $existing['id'],
                'state' => $state,
                'payload_json' => $payloadJson,
            ]);
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO chat_states (tenant_id, branch_id, customer_id, session_key, state, payload_json, created_at) VALUES (:tenant_id, :branch_id, :customer_id, :session_key, :state, :payload_json, NOW())');
        $stmt->execute([
            'tenant_id' => $context->tenantId,
            'branch_id' => $context->branchId,
            'customer_id' => $context->customerId,
            'session_key' => $context->sessionKey(),
            'state' => $state,
            'payload_json' => $payloadJson,
        ]);
    }

    public function clearState(ConversationContext $context): void
    {
        $this->setState($context, ConversationState::IDLE, []);
    }
}
