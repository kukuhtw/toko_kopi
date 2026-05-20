<?php

declare(strict_types=1);

namespace App\Models;

class ConversationModel extends BaseModel
{
    protected string $table = 'conversations';
    private const OUT_OF_SCOPE_WINDOW_SECONDS = 180;
    private const OUT_OF_SCOPE_THRESHOLD = 5;
    private const SUSPEND_SECONDS = 600;

    public function getOrCreate(int $branchId, int $customerId, string $channel, string $sessionKey): array
    {
        $conv = $this->query(
            'SELECT * FROM conversations WHERE session_key = ? AND ended_at IS NULL LIMIT 1',
            [$sessionKey]
        )->fetch();

        if (!$conv) {
            $id = $this->insert([
                'branch_id'   => $branchId,
                'customer_id' => $customerId,
                'channel'     => $channel,
                'session_key' => $sessionKey,
                'state'       => 'idle',
            ]);
            $conv = $this->find($id);
        } elseif ((int)($conv['customer_id'] ?? 0) !== $customerId && $customerId > 0) {
            $this->update((int)$conv['id'], [
                'customer_id' => $customerId,
                'last_activity' => date('Y-m-d H:i:s'),
            ]);
            $conv = $this->find((int)$conv['id']);
        }

        return $conv;
    }

    public function updateState(int $convId, string $state, array $contextData = []): void
    {
        $this->update($convId, [
            'state'        => $state,
            'context_data' => !empty($contextData) ? json_encode($contextData) : null,
            'last_activity'=> date('Y-m-d H:i:s'),
        ]);
    }

    public function getContext(int $convId): array
    {
        $conv = $this->find($convId);
        if (!$conv || empty($conv['context_data'])) return [];
        return json_decode($conv['context_data'], true) ?? [];
    }

    public function getActiveSuspension(int $convId, ?array $contextData = null): ?array
    {
        $contextData = $this->normalizeModerationContext($contextData ?? $this->getContext($convId));
        $moderation = (array)($contextData['moderation'] ?? []);
        $until = (string)($moderation['suspended_until'] ?? '');
        $untilTs = $until !== '' ? strtotime($until) : false;
        if ($untilTs === false) {
            return null;
        }

        if ($untilTs <= time()) {
            unset($contextData['moderation']['suspended_until'], $contextData['moderation']['suspend_reason']);
            $this->updateState($convId, 'idle', $contextData);
            return null;
        }

        return [
            'until' => $until,
            'reason' => (string)($moderation['suspend_reason'] ?? 'too_many_out_of_scope'),
            'remaining_seconds' => max(0, $untilTs - time()),
            'context' => $contextData,
        ];
    }

    public function registerOutOfScopeStrike(int $convId, array $contextData): array
    {
        $contextData = $this->normalizeModerationContext($contextData);
        $now = date('Y-m-d H:i:s');
        $hits = (array)($contextData['moderation']['out_of_scope_hits'] ?? []);
        $hits[] = $now;
        $hits = $this->pruneRecentTimestamps($hits, self::OUT_OF_SCOPE_WINDOW_SECONDS);
        $contextData['moderation']['out_of_scope_hits'] = $hits;
        $contextData['moderation']['last_out_of_scope_at'] = $now;

        if (count($hits) < self::OUT_OF_SCOPE_THRESHOLD) {
            return [
                'triggered' => false,
                'context' => $contextData,
                'threshold' => self::OUT_OF_SCOPE_THRESHOLD,
                'window_seconds' => self::OUT_OF_SCOPE_WINDOW_SECONDS,
                'suspend_seconds' => self::SUSPEND_SECONDS,
            ];
        }

        $suspendedUntil = date('Y-m-d H:i:s', time() + self::SUSPEND_SECONDS);
        $contextData['moderation']['suspended_until'] = $suspendedUntil;
        $contextData['moderation']['suspend_reason'] = 'too_many_out_of_scope';
        $contextData['moderation']['last_suspended_at'] = $now;

        return [
            'triggered' => true,
            'context' => $contextData,
            'threshold' => self::OUT_OF_SCOPE_THRESHOLD,
            'window_seconds' => self::OUT_OF_SCOPE_WINDOW_SECONDS,
            'suspend_seconds' => self::SUSPEND_SECONDS,
            'suspended_until' => $suspendedUntil,
        ];
    }

    public function addMessage(int $convId, string $sender, string $message, string $intent = ''): int
    {
        $this->query('UPDATE conversations SET last_activity = NOW() WHERE id = ?', [$convId]);
        return (int) $this->query(
            'INSERT INTO conversation_messages (conversation_id, sender, message, intent) VALUES (?, ?, ?, ?)',
            [$convId, $sender, $message, $intent]
        )->rowCount();
    }

    public function getMessages(int $convId, int $limit = 50): array
    {
        return $this->query(
            'SELECT * FROM conversation_messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT ?',
            [$convId, $limit]
        )->fetchAll();
    }

    public function endConversation(int $convId): void
    {
        $this->update($convId, ['ended_at' => date('Y-m-d H:i:s')]);
    }

    public function getRecentByBranch(int $branchId, int $limit = 20): array
    {
        return $this->query(
            'SELECT conv.*, c.name AS customer_name, c.identifier AS customer_identifier,
                    (SELECT COUNT(*) FROM conversation_messages cm WHERE cm.conversation_id = conv.id) AS msg_count
             FROM conversations conv
             JOIN customers c ON conv.customer_id = c.id
             WHERE conv.branch_id = ?
             ORDER BY conv.last_activity DESC
             LIMIT ?',
            [$branchId, $limit]
        )->fetchAll();
    }

    public function getVisibleByBranch(int $branchId, int $limit = 20): array
    {
        $rows = $this->fetchVisibleCandidatesByBranch($branchId, max($limit * 4, 50));
        return array_slice($this->mergeVisibleRows($rows, $branchId), 0, $limit);
    }

    public function findVisibleByBranch(int $convId, int $branchId): array|false
    {
        foreach ($this->mergeVisibleRows($this->fetchVisibleCandidatesByBranch($branchId, 200), $branchId) as $row) {
            if ((int) ($row['id'] ?? 0) === $convId || (int) ($row['shared_conversation_id'] ?? 0) === $convId) {
                return $row;
            }
        }

        return false;
    }

    public function getMergedVisibleMessages(int $convId, int $branchId, int $limit = 200): array
    {
        $conversation = $this->findVisibleByBranch($convId, $branchId);
        if (!$conversation) {
            return [];
        }

        $conversationIds = [(int) $conversation['id']];
        $sharedConversationId = (int) ($conversation['shared_conversation_id'] ?? 0);
        if ($sharedConversationId > 0 && $sharedConversationId !== (int) $conversation['id']) {
            array_unshift($conversationIds, $sharedConversationId);
        }

        $placeholders = implode(', ', array_fill(0, count($conversationIds), '?'));
        $rows = $this->query(
            "SELECT conversation_id, sender, message, intent, created_at
             FROM conversation_messages
             WHERE conversation_id IN ({$placeholders})
             ORDER BY created_at ASC, conversation_id ASC
             LIMIT ?",
            array_merge($conversationIds, [$limit])
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['is_shared_inbox_message'] = ((int) $row['conversation_id'] === $sharedConversationId);
        }
        unset($row);

        return $rows;
    }

    public function getAll(int $limit = 50): array
    {
        return $this->query(
            'SELECT conv.*, b.name AS branch_name, c.name AS customer_name,
                    (SELECT COUNT(*) FROM conversation_messages cm WHERE cm.conversation_id = conv.id) AS msg_count
             FROM conversations conv
             JOIN branches b ON conv.branch_id = b.id
             JOIN customers c ON conv.customer_id = c.id
             ORDER BY conv.last_activity DESC
             LIMIT ?',
            [$limit]
        )->fetchAll();
    }

    private function buildSelectedBranchPatterns(int $branchId): array
    {
        return [
            '%"selected_branch_id":' . $branchId . ',%',
            '%"selected_branch_id":' . $branchId . '}%',
        ];
    }

    private function fetchVisibleCandidatesByBranch(int $branchId, int $limit): array
    {
        [$sameA, $sameB] = $this->buildSelectedBranchPatterns($branchId);

        return $this->query(
            'SELECT conv.*, c.name AS customer_name, c.identifier AS customer_identifier,
                    b.name AS source_branch_name,
                    (SELECT COUNT(*) FROM conversation_messages cm WHERE cm.conversation_id = conv.id) AS msg_count,
                    CASE WHEN conv.branch_id = ? THEN 0 ELSE 1 END AS is_shared_routed
             FROM conversations conv
             JOIN customers c ON conv.customer_id = c.id
             JOIN branches b ON conv.branch_id = b.id
             WHERE (
                    conv.branch_id = ?
                    AND (
                        conv.context_data IS NULL
                        OR conv.context_data = ""
                        OR conv.context_data NOT LIKE ?
                        OR conv.context_data LIKE ?
                        OR conv.context_data LIKE ?
                    )
                  )
                OR conv.context_data LIKE ?
                OR conv.context_data LIKE ?
             ORDER BY conv.last_activity DESC
             LIMIT ?',
            [
                $branchId,
                $branchId,
                '%"selected_branch_id":%',
                $sameA,
                $sameB,
                $sameA,
                $sameB,
                $limit,
            ]
        )->fetchAll();
    }

    private function mergeVisibleRows(array $rows, int $branchId): array
    {
        $orderedKeys = [];
        $entries = [];

        foreach ($rows as $row) {
            $selectedBranchId = $this->extractSelectedBranchId((string) ($row['context_data'] ?? ''));
            $isGateway = $selectedBranchId === $branchId;
            $key = ((int) ($row['customer_id'] ?? 0)) . '|' . (string) ($row['channel'] ?? '');

            if (!isset($entries[$key])) {
                $entries[$key] = $this->prepareVisibleEntry($row, $isGateway);
                $orderedKeys[] = $key;
                continue;
            }

            $existing = $entries[$key];
            $existingIsGateway = !empty($existing['shared_conversation_id']) && (int) $existing['shared_conversation_id'] === (int) $existing['id'];

            if (!$isGateway && $existingIsGateway) {
                $entries[$key] = $this->prepareVisibleEntry($row, false, (int) $existing['id'], (string) ($existing['source_branch_name'] ?? ''));
                continue;
            }

            if ($isGateway && empty($existing['shared_conversation_id'])) {
                $existing['shared_conversation_id'] = (int) $row['id'];
                $existing['shared_source_branch_name'] = (string) ($row['source_branch_name'] ?? '');
                $entries[$key] = $existing;
                continue;
            }

            if (strtotime((string) ($row['last_activity'] ?? '')) > strtotime((string) ($existing['last_activity'] ?? ''))) {
                if ($isGateway) {
                    $existing['last_activity'] = $row['last_activity'];
                    $existing['msg_count'] = ((int) ($existing['msg_count'] ?? 0)) + ((int) ($row['msg_count'] ?? 0));
                    $entries[$key] = $existing;
                } else {
                    $sharedId = (int) ($existing['shared_conversation_id'] ?? 0);
                    $sharedName = (string) ($existing['shared_source_branch_name'] ?? '');
                    $entries[$key] = $this->prepareVisibleEntry($row, false, $sharedId, $sharedName);
                }
            }
        }

        $merged = [];
        foreach ($orderedKeys as $key) {
            $entry = $entries[$key];
            if (!empty($entry['shared_conversation_id']) && (int) $entry['shared_conversation_id'] !== (int) $entry['id']) {
                $entry['is_shared_routed'] = 1;
                $entry['source_branch_name'] = $entry['shared_source_branch_name'] ?: $entry['source_branch_name'];
            } else {
                $entry['is_shared_routed'] = !empty($entry['is_shared_routed']) ? 1 : 0;
            }
            $merged[] = $entry;
        }

        usort($merged, fn(array $a, array $b) => strtotime((string) $b['last_activity']) <=> strtotime((string) $a['last_activity']));
        return $merged;
    }

    private function prepareVisibleEntry(array $row, bool $isGateway, int $sharedConversationId = 0, string $sharedSourceBranchName = ''): array
    {
        if ($isGateway) {
            $row['shared_conversation_id'] = (int) $row['id'];
            $row['shared_source_branch_name'] = (string) ($row['source_branch_name'] ?? '');
            $row['is_shared_routed'] = 1;
            return $row;
        }

        $row['shared_conversation_id'] = $sharedConversationId;
        $row['shared_source_branch_name'] = $sharedSourceBranchName;
        $row['is_shared_routed'] = $sharedConversationId > 0 ? 1 : 0;
        return $row;
    }

    private function extractSelectedBranchId(string $contextData): ?int
    {
        if ($contextData === '') {
            return null;
        }

        $decoded = json_decode($contextData, true);
        if (!is_array($decoded)) {
            return null;
        }

        $value = $decoded['selected_branch_id'] ?? null;
        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeModerationContext(array $contextData): array
    {
        $moderation = isset($contextData['moderation']) && is_array($contextData['moderation'])
            ? $contextData['moderation']
            : [];

        $moderation['out_of_scope_hits'] = $this->pruneRecentTimestamps(
            is_array($moderation['out_of_scope_hits'] ?? null) ? $moderation['out_of_scope_hits'] : [],
            self::OUT_OF_SCOPE_WINDOW_SECONDS
        );

        $contextData['moderation'] = $moderation;
        return $contextData;
    }

    private function pruneRecentTimestamps(array $timestamps, int $windowSeconds): array
    {
        $cutoff = time() - $windowSeconds;
        $kept = [];

        foreach ($timestamps as $timestamp) {
            $ts = is_string($timestamp) ? strtotime($timestamp) : false;
            if ($ts === false || $ts < $cutoff) {
                continue;
            }
            $kept[] = date('Y-m-d H:i:s', $ts);
        }

        return array_values($kept);
    }
}
