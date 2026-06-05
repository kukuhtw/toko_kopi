<?php

declare(strict_types=1);

namespace KopiBot\Domains\CRM;

use KopiBot\Core\Database;
use PDO;

class CrmRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function logEvent(CrmEventDTO $dto): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO crm_events (tenant_id, branch_id, customer_id, event_type, payload_json, source, created_at) VALUES (:tenant_id, :branch_id, :customer_id, :event_type, :payload_json, :source, NOW())'
        );

        $stmt->execute([
            'tenant_id' => $dto->tenantId,
            'branch_id' => $dto->branchId,
            'customer_id' => $dto->customerId,
            'event_type' => $dto->eventType,
            'payload_json' => json_encode($dto->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source' => $dto->source,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByCustomerId(int $tenantId, int $customerId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM crm_events WHERE tenant_id = :tenant_id AND customer_id = :customer_id ORDER BY id DESC LIMIT :limit'
        );

        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
