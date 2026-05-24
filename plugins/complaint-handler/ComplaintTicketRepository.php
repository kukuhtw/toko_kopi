<?php

declare(strict_types=1);

use App\Config\Database;

final class ComplaintTicketRepository
{
    private static bool $schemaReady = false;

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $sqlFile = __DIR__ . '/schema.sql';
        if (!is_file($sqlFile)) {
            throw new RuntimeException('Schema file not found for complaint-handler.');
        }

        $sql = trim((string)file_get_contents($sqlFile));
        if ($sql === '') {
            self::$schemaReady = true;
            return;
        }

        foreach ($this->splitSqlStatements($sql) as $statement) {
            Database::getInstance()->exec($statement);
        }

        self::$schemaReady = true;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $this->ensureSchema();

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO complaint_tickets
            (branch_id, customer_id, conversation_id, order_id, source_channel, status, handling_mode, priority, category, subject, customer_message, ai_reply, internal_note, follow_up_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$data['branch_id'],
            (int)$data['customer_id'],
            (int)$data['conversation_id'],
            !empty($data['order_id']) ? (int)$data['order_id'] : null,
            (string)($data['source_channel'] ?? 'web'),
            (string)($data['status'] ?? 'open'),
            (string)($data['handling_mode'] ?? 'human'),
            (string)($data['priority'] ?? 'medium'),
            (string)($data['category'] ?? 'general'),
            (string)$data['subject'],
            (string)$data['customer_message'],
            isset($data['ai_reply']) ? (string)$data['ai_reply'] : null,
            isset($data['internal_note']) ? (string)$data['internal_note'] : null,
            isset($data['follow_up_reason']) ? (string)$data['follow_up_reason'] : null,
        ]);

        return (int)$db->lastInsertId();
    }

    public function updateStatus(int $ticketId, string $status, ?string $internalNote = null): void
    {
        $this->ensureSchema();

        $resolvedAt = in_array($status, ['resolved', 'closed'], true) ? date('Y-m-d H:i:s') : null;
        Database::getInstance()->prepare(
            'UPDATE complaint_tickets
             SET status = ?, internal_note = ?, resolved_at = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        )->execute([$status, $internalNote, $resolvedAt, $ticketId]);
    }

    public function fetchByBranch(int $branchId, string $status = 'all', int $limit = 100): array
    {
        $this->ensureSchema();

        $params = [$branchId];
        $where = 'WHERE t.branch_id = ?';
        if ($status !== 'all') {
            $where .= ' AND t.status = ?';
            $params[] = $status;
        }
        $params[] = $limit;

        $stmt = Database::getInstance()->prepare(
            "SELECT t.*, c.name AS customer_name, c.identifier AS customer_identifier, o.order_number
             FROM complaint_tickets t
             JOIN customers c ON c.id = t.customer_id
             LEFT JOIN orders o ON o.id = t.order_id
             {$where}
             ORDER BY FIELD(t.status, 'open', 'in_progress', 'resolved', 'closed'),
                      FIELD(t.priority, 'high', 'medium', 'low'),
                      t.created_at DESC
             LIMIT ?"
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function findByIdAndBranch(int $ticketId, int $branchId): array|false
    {
        $this->ensureSchema();

        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM complaint_tickets WHERE id = ? AND branch_id = ? LIMIT 1'
        );
        $stmt->execute([$ticketId, $branchId]);

        return $stmt->fetch() ?: false;
    }

    public function countRecentCustomerComplaints(int $branchId, int $customerId, int $minutes = 1440): int
    {
        $this->ensureSchema();

        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*)
             FROM complaint_tickets
             WHERE branch_id = ? AND customer_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$branchId, $customerId, $minutes]);

        return (int)$stmt->fetchColumn();
    }

    public function getSummaryByBranch(int $branchId): array
    {
        $this->ensureSchema();

        $stmt = Database::getInstance()->prepare(
            'SELECT
                SUM(CASE WHEN status IN ("open", "in_progress") AND handling_mode = "human" THEN 1 ELSE 0 END) AS human_open,
                SUM(CASE WHEN handling_mode = "ai" THEN 1 ELSE 0 END) AS ai_total,
                SUM(CASE WHEN priority = "high" AND status IN ("open", "in_progress") THEN 1 ELSE 0 END) AS urgent_open
             FROM complaint_tickets
             WHERE branch_id = ?'
        );
        $stmt->execute([$branchId]);

        return $stmt->fetch() ?: ['human_open' => 0, 'ai_total' => 0, 'urgent_open' => 0];
    }

    /**
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $prev = $i > 0 ? $sql[$i - 1] : '';

            if ($char === "'" && !$inDouble && $prev !== '\\') {
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle && $prev !== '\\') {
                $inDouble = !$inDouble;
            }

            if (!$inSingle && !$inDouble && $char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
