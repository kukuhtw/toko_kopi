<?php

declare(strict_types=1);

namespace App\Agent\Memory;

use App\Config\Database;

final class CustomerMemoryStore implements MemoryStoreInterface
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getMemories(string $scope, string $entityKey, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM agent_memories
             WHERE scope = ? AND entity_key = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$scope, $entityKey, $limit]);
        return $stmt->fetchAll();
    }

    public function remember(string $scope, string $entityKey, string $memoryType, string $content, array $metadata = []): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO agent_memories (scope, entity_key, memory_type, content, metadata_json)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $scope,
            $entityKey,
            $memoryType,
            $content,
            !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function search(string $scope, string $entityKey, string $query, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM agent_memories
             WHERE scope = ? AND entity_key = ? AND content LIKE ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$scope, $entityKey, '%' . $query . '%', $limit]);
        return $stmt->fetchAll();
    }
}
