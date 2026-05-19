<?php

declare(strict_types=1);

namespace App\Models;

use PDOException;

final class AgentMemoryModel extends BaseModel
{
    protected string $table = 'agent_memories';
    private ?bool $available = null;

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            $memoryTable = $this->query("SHOW TABLES LIKE 'agent_memories'")->fetchColumn();
            $this->available = (bool)$memoryTable;
        } catch (PDOException) {
            $this->available = false;
        }

        return $this->available;
    }

    public function getByEntityKey(string $scope, string $entityKey, int $limit = 50): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        return $this->query(
            'SELECT * FROM agent_memories
             WHERE scope = ? AND entity_key = ?
             ORDER BY created_at DESC
             LIMIT ?',
            [$scope, $entityKey, $limit]
        )->fetchAll();
    }

    public function getByEntityKeyAndType(string $scope, string $entityKey, string $memoryType, int $limit = 50): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        return $this->query(
            'SELECT * FROM agent_memories
             WHERE scope = ? AND entity_key = ? AND memory_type = ?
             ORDER BY created_at DESC
             LIMIT ?',
            [$scope, $entityKey, $memoryType, $limit]
        )->fetchAll();
    }

    public function getGroupedCounts(string $scope, string $entityKey): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        return $this->query(
            'SELECT memory_type, COUNT(*) AS total
             FROM agent_memories
             WHERE scope = ? AND entity_key = ?
             GROUP BY memory_type
             ORDER BY total DESC, memory_type ASC',
            [$scope, $entityKey]
        )->fetchAll();
    }
}
