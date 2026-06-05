<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

use KopiBot\Core\Database;
use PDO;

class ConversationMemoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findOrCreateSession(ConversationContext $context): int
    {
        $stmt = $this->db->prepare('SELECT id FROM chat_sessions WHERE tenant_id = :tenant_id AND branch_id = :branch_id AND session_key = :session_key LIMIT 1');
        $stmt->execute([
            'tenant_id' => $context->tenantId,
            'branch_id' => $context->branchId,
            'session_key' => $context->sessionKey(),
        ]);

        $sessionId = $stmt->fetchColumn();

        if ($sessionId) {
            return (int) $sessionId;
        }

        $stmt = $this->db->prepare('INSERT INTO chat_sessions (tenant_id, branch_id, customer_id, channel, sender_id, session_key, created_at) VALUES (:tenant_id, :branch_id, :customer_id, :channel, :sender_id, :session_key, NOW())');
        $stmt->execute([
            'tenant_id' => $context->tenantId,
            'branch_id' => $context->branchId,
            'customer_id' => $context->customerId,
            'channel' => $context->channel,
            'sender_id' => $context->senderId,
            'session_key' => $context->sessionKey(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function addMessage(int $tenantId, int $sessionId, string $role, string $message, array $meta = []): int
    {
        $stmt = $this->db->prepare('INSERT INTO chat_messages (tenant_id, session_id, role, message, meta_json, created_at) VALUES (:tenant_id, :session_id, :role, :message, :meta_json, NOW())');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'role' => $role,
            'message' => $message,
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function recentMessages(int $tenantId, int $sessionId, int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT * FROM chat_messages WHERE tenant_id = :tenant_id AND session_id = :session_id ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_reverse($stmt->fetchAll());
    }
}
