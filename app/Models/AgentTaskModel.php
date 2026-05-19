<?php

declare(strict_types=1);

namespace App\Models;

use PDOException;

final class AgentTaskModel extends BaseModel
{
    protected string $table = 'agent_tasks';
    private ?bool $available = null;

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            $taskTable = $this->query("SHOW TABLES LIKE 'agent_tasks'")->fetchColumn();
            $stepTable = $this->query("SHOW TABLES LIKE 'agent_task_steps'")->fetchColumn();
            $memoryTable = $this->query("SHOW TABLES LIKE 'agent_memories'")->fetchColumn();
            $this->available = (bool)$taskTable && (bool)$stepTable && (bool)$memoryTable;
        } catch (PDOException) {
            $this->available = false;
        }

        return $this->available;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createTask(array $data): ?int
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            return $this->insert([
                'scope' => (string)($data['scope'] ?? 'customer'),
                'entity_key' => (string)($data['entity_key'] ?? ''),
                'channel' => $data['channel'] ?? null,
                'branch_id' => !empty($data['branch_id']) ? (int)$data['branch_id'] : null,
                'conversation_id' => !empty($data['conversation_id']) ? (int)$data['conversation_id'] : null,
                'intent' => $data['intent'] ?? null,
                'mode' => in_array(($data['mode'] ?? 'advisory'), ['transactional', 'advisory', 'handoff'], true)
                    ? (string)$data['mode']
                    : 'advisory',
                'status' => in_array(($data['status'] ?? 'completed'), ['queued', 'running', 'completed', 'failed'], true)
                    ? (string)$data['status']
                    : 'completed',
                'goal' => (string)($data['goal'] ?? 'Customer conversation'),
                'summary' => $data['summary'] ?? null,
            ]);
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addStep(array $data): ?int
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $this->query(
                'INSERT INTO agent_task_steps (task_id, step_index, step_type, tool_name, input_json, output_json, status, error_message)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int)($data['task_id'] ?? 0),
                    (int)($data['step_index'] ?? 0),
                    (string)($data['step_type'] ?? 'tool_call'),
                    $data['tool_name'] ?? null,
                    !empty($data['input']) ? json_encode($data['input'], JSON_UNESCAPED_UNICODE) : null,
                    array_key_exists('output', $data) ? json_encode($data['output'], JSON_UNESCAPED_UNICODE) : null,
                    in_array(($data['status'] ?? 'completed'), ['planned', 'running', 'completed', 'blocked', 'failed'], true)
                        ? (string)$data['status']
                        : 'completed',
                    $data['error_message'] ?? null,
                ]
            );

            return (int)$this->db->lastInsertId();
        } catch (PDOException) {
            return null;
        }
    }

    public function getStats(?int $branchId = null): array
    {
        if (!$this->isAvailable()) {
            return [
                'total_tasks' => 0,
                'advisory_tasks' => 0,
                'transactional_tasks' => 0,
                'failed_tasks' => 0,
                'today_tasks' => 0,
            ];
        }

        $params = [];
        $where = '';
        if ($branchId !== null && $branchId > 0) {
            $where = 'WHERE branch_id = ?';
            $params[] = $branchId;
        }

        $row = $this->query(
            "SELECT
                COUNT(*) AS total_tasks,
                SUM(CASE WHEN mode = 'advisory' THEN 1 ELSE 0 END) AS advisory_tasks,
                SUM(CASE WHEN mode = 'transactional' THEN 1 ELSE 0 END) AS transactional_tasks,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_tasks,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_tasks
             FROM agent_tasks
             {$where}",
            $params
        )->fetch();

        return $row ?: [];
    }

    public function getRecentTasks(int $limit = 50, ?int $branchId = null): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $params = [];
        $where = [];
        if ($branchId !== null && $branchId > 0) {
            $where[] = 't.branch_id = ?';
            $params[] = $branchId;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $params[] = $limit;

        return $this->query(
            "SELECT
                t.*,
                b.name AS branch_name,
                c.customer_id,
                cust.name AS customer_name,
                cust.identifier AS customer_identifier,
                COUNT(s.id) AS step_count
             FROM agent_tasks t
             LEFT JOIN branches b ON b.id = t.branch_id
             LEFT JOIN conversations c ON c.id = t.conversation_id
             LEFT JOIN customers cust ON cust.id = c.customer_id
             LEFT JOIN agent_task_steps s ON s.task_id = t.id
             {$whereSql}
             GROUP BY t.id, b.name, c.customer_id, cust.name, cust.identifier
             ORDER BY t.created_at DESC
             LIMIT ?",
            $params
        )->fetchAll();
    }

    public function findTaskWithSteps(int $taskId, ?int $branchId = null): array|false
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $params = [$taskId];
        $branchSql = '';
        if ($branchId !== null && $branchId > 0) {
            $branchSql = ' AND t.branch_id = ?';
            $params[] = $branchId;
        }

        $task = $this->query(
            "SELECT
                t.*,
                b.name AS branch_name,
                c.customer_id,
                cust.name AS customer_name,
                cust.identifier AS customer_identifier
             FROM agent_tasks t
             LEFT JOIN branches b ON b.id = t.branch_id
             LEFT JOIN conversations c ON c.id = t.conversation_id
             LEFT JOIN customers cust ON cust.id = c.customer_id
             WHERE t.id = ?{$branchSql}
             LIMIT 1",
            $params
        )->fetch();

        if (!$task) {
            return false;
        }

        $task['steps'] = $this->query(
            'SELECT * FROM agent_task_steps WHERE task_id = ? ORDER BY step_index ASC, id ASC',
            [$taskId]
        )->fetchAll();

        return $task;
    }
}
