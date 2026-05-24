<?php

declare(strict_types=1);

use App\Config\Database;

final class FaqRepository
{
    private static bool $schemaReady = false;
    private FaqVectorService $vectors;

    public function __construct(?FaqVectorService $vectors = null)
    {
        $this->vectors = $vectors ?? new FaqVectorService();
    }

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $sql = trim((string)file_get_contents(__DIR__ . '/schema.sql'));
        foreach ($this->splitSqlStatements($sql) as $statement) {
            Database::getInstance()->exec($statement);
        }
        $this->ensureColumn('faq_entries', 'parent_global_id', 'INT UNSIGNED NULL AFTER branch_id');
        $this->ensureIndex('faq_entries', 'idx_faq_parent_global', 'CREATE INDEX idx_faq_parent_global ON faq_entries (parent_global_id)');
        $this->ensureTableForeignKey();
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
            'INSERT INTO faq_entries (scope, branch_id, parent_global_id, question, answer, tags, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (string)$data['scope'],
            $data['branch_id'] ?? null,
            $data['parent_global_id'] ?? null,
            trim((string)$data['question']),
            trim((string)$data['answer']),
            trim((string)($data['tags'] ?? '')),
            !empty($data['is_active']) ? 1 : 0,
        ]);

        $id = (int)$db->lastInsertId();
        $this->refreshVector($id);
        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateEntry(int $id, array $data): void
    {
        $this->ensureSchema();

        Database::getInstance()->prepare(
            'UPDATE faq_entries
             SET parent_global_id = ?, question = ?, answer = ?, tags = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        )->execute([
            $data['parent_global_id'] ?? null,
            trim((string)$data['question']),
            trim((string)$data['answer']),
            trim((string)($data['tags'] ?? '')),
            !empty($data['is_active']) ? 1 : 0,
            $id,
        ]);

        $this->refreshVector($id);
    }

    public function toggleActive(int $id, bool $active): void
    {
        $this->ensureSchema();
        Database::getInstance()->prepare(
            'UPDATE faq_entries SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$active ? 1 : 0, $id]);
    }

    public function findById(int $id): array|false
    {
        $this->ensureSchema();
        $stmt = Database::getInstance()->prepare('SELECT * FROM faq_entries WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public function findGlobal(int $id): array|false
    {
        $row = $this->findById($id);
        return $row && (string)$row['scope'] === 'global' ? $row : false;
    }

    public function findBranch(int $id, int $branchId): array|false
    {
        $row = $this->findById($id);
        return $row && (string)$row['scope'] === 'branch' && (int)$row['branch_id'] === $branchId ? $row : false;
    }

    public function getGlobalFaqs(bool $includeInactive = true): array
    {
        $this->ensureSchema();
        $sql = 'SELECT * FROM faq_entries WHERE scope = "global" AND parent_global_id IS NULL';
        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY is_active DESC, updated_at DESC, id DESC';
        return Database::getInstance()->query($sql)->fetchAll() ?: [];
    }

    public function countGlobalFaqs(): int
    {
        $this->ensureSchema();
        return (int)Database::getInstance()->query(
            'SELECT COUNT(*) FROM faq_entries WHERE scope = "global" AND parent_global_id IS NULL'
        )->fetchColumn();
    }

    public function getBranchFaqs(int $branchId, bool $includeInactive = true): array
    {
        $this->ensureSchema();
        $sql = 'SELECT * FROM faq_entries WHERE scope = "branch" AND branch_id = ?';
        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY is_active DESC, updated_at DESC, id DESC';
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([$branchId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getInheritedGlobalFaqs(): array
    {
        return $this->getGlobalFaqs(false);
    }

    public function getGlobalFaqsWithBranchOverrideStatus(int $branchId): array
    {
        $this->ensureSchema();
        $stmt = Database::getInstance()->prepare(
            'SELECT g.*,
                    bo.id AS branch_override_id,
                    bo.answer AS branch_override_answer,
                    bo.tags AS branch_override_tags,
                    bo.is_active AS branch_override_active,
                    bo.updated_at AS branch_override_updated_at
             FROM faq_entries g
             LEFT JOIN faq_entries bo
               ON bo.parent_global_id = g.id
              AND bo.scope = "branch"
              AND bo.branch_id = ?
             WHERE g.scope = "global" AND g.parent_global_id IS NULL AND g.is_active = 1
             ORDER BY g.updated_at DESC, g.id DESC'
        );
        $stmt->execute([$branchId]);
        return $stmt->fetchAll() ?: [];
    }

    public function countBranchCustomFaqs(int $branchId): int
    {
        $this->ensureSchema();
        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*) FROM faq_entries WHERE scope = "branch" AND branch_id = ?'
        );
        $stmt->execute([$branchId]);
        return (int)$stmt->fetchColumn();
    }

    public function getCombinedFaqs(int $branchId): array
    {
        $this->ensureSchema();
        $stmt = Database::getInstance()->prepare(
            'SELECT e.*, v.vector_json, v.normalized_text
             FROM faq_entries e
             JOIN faq_vectors v ON v.faq_id = e.id
             WHERE e.is_active = 1
               AND (
                   (e.scope = "global" AND e.parent_global_id IS NULL AND e.id NOT IN (
                       SELECT parent_global_id
                       FROM faq_entries
                       WHERE scope = "branch" AND branch_id = ? AND parent_global_id IS NOT NULL AND is_active = 1
                   ))
                   OR (e.scope = "branch" AND e.branch_id = ?)
               )'
        );
        $stmt->execute([$branchId, $branchId]);
        return $stmt->fetchAll() ?: [];
    }

    public function searchRelevant(string $query, int $branchId, int $limit = 4, float $minScore = 0.34): array
    {
        $this->ensureSchema();

        $normalizedQuery = $this->vectors->normalizeText($query);
        if ($normalizedQuery === '') {
            return [];
        }

        $queryVector = $this->vectors->embed($normalizedQuery);
        $queryTokens = $this->vectors->tokenize($normalizedQuery);
        $rows = $this->getCombinedFaqs($branchId);
        $ranked = [];

        foreach ($rows as $row) {
            $vector = json_decode((string)$row['vector_json'], true);
            if (!is_array($vector)) {
                continue;
            }
            $score = $this->vectors->cosine($queryVector, array_map('floatval', $vector));
            $score += $this->keywordBonus($queryTokens, $row);
            if ((string)$row['scope'] === 'branch') {
                $score += 0.03;
            }
            if ($score < $minScore) {
                continue;
            }
            $row['_score'] = round($score, 5);
            $ranked[] = $row;
        }

        usort($ranked, static function (array $a, array $b): int {
            $scoreCompare = (($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }
            if ((string)($a['scope'] ?? '') !== (string)($b['scope'] ?? '')) {
                return (string)$a['scope'] === 'branch' ? -1 : 1;
            }
            return (int)$b['id'] <=> (int)$a['id'];
        });

        return array_slice($ranked, 0, $limit);
    }

    public function refreshVector(int $faqId): void
    {
        $this->ensureSchema();
        $faq = $this->findById($faqId);
        if (!$faq) {
            return;
        }

        $source = trim((string)$faq['question'] . ' ' . (string)$faq['answer'] . ' ' . (string)($faq['tags'] ?? ''));
        $normalized = $this->vectors->normalizeText($source);
        $vector = $this->vectors->embed($source);
        $checksum = $this->vectors->checksum($source);

        Database::getInstance()->prepare(
            'INSERT INTO faq_vectors (faq_id, vector_dim, vector_json, normalized_text, checksum)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                vector_dim = VALUES(vector_dim),
                vector_json = VALUES(vector_json),
                normalized_text = VALUES(normalized_text),
                checksum = VALUES(checksum),
                updated_at = CURRENT_TIMESTAMP'
        )->execute([
            $faqId,
            $this->vectors->dimension(),
            json_encode($vector, JSON_UNESCAPED_UNICODE),
            $normalized,
            $checksum,
        ]);
    }

    public function rebuildAllVectors(): int
    {
        $this->ensureSchema();
        $rows = Database::getInstance()->query('SELECT id FROM faq_entries')->fetchAll() ?: [];
        foreach ($rows as $row) {
            $this->refreshVector((int)$row['id']);
        }
        return count($rows);
    }

    public function logQuery(
        int $branchId,
        ?int $customerId,
        ?int $conversationId,
        string $queryText,
        ?int $faqId,
        ?float $matchedScore,
        ?string $matchedScope
    ): void {
        $this->ensureSchema();
        Database::getInstance()->prepare(
            'INSERT INTO faq_query_logs
             (branch_id, customer_id, conversation_id, faq_id, query_text, matched_score, matched_scope)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $branchId,
            $customerId,
            $conversationId,
            $faqId,
            trim($queryText),
            $matchedScore,
            $matchedScope,
        ]);
    }

    public function getAnalyticsSummary(int $branchId, int $days = 30): array
    {
        $this->ensureSchema();
        $stmt = Database::getInstance()->prepare(
            'SELECT
                COUNT(*) AS total_questions,
                COUNT(DISTINCT conversation_id) AS unique_conversations,
                COUNT(DISTINCT faq_id) AS matched_faqs
             FROM faq_query_logs
             WHERE branch_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$branchId, $days]);
        return $stmt->fetch() ?: ['total_questions' => 0, 'unique_conversations' => 0, 'matched_faqs' => 0];
    }

    public function getTopAskedFaqs(int $branchId, int $days = 30, int $limit = 10): array
    {
        $this->ensureSchema();
        $stmt = Database::getInstance()->prepare(
            'SELECT l.faq_id, e.question, e.scope, COUNT(*) AS total_asked, MAX(l.created_at) AS last_asked_at, AVG(l.matched_score) AS avg_score
             FROM faq_query_logs l
             LEFT JOIN faq_entries e ON e.id = l.faq_id
             WHERE l.branch_id = ?
               AND l.faq_id IS NOT NULL
               AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY l.faq_id, e.question, e.scope
             ORDER BY total_asked DESC, last_asked_at DESC
             LIMIT ?'
        );
        $stmt->execute([$branchId, $days, $limit]);
        return $stmt->fetchAll() ?: [];
    }

    public function getTopUnmatchedQueries(int $branchId, int $days = 30, int $limit = 10): array
    {
        $this->ensureSchema();
        $stmt = Database::getInstance()->prepare(
            'SELECT query_text, COUNT(*) AS total_asked, MAX(created_at) AS last_asked_at
             FROM faq_query_logs
             WHERE branch_id = ?
               AND faq_id IS NULL
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY query_text
             ORDER BY total_asked DESC, last_asked_at DESC
             LIMIT ?'
        );
        $stmt->execute([$branchId, $days, $limit]);
        return $stmt->fetchAll() ?: [];
    }

    public function exportRows(string $scope, ?int $branchId = null): array
    {
        $this->ensureSchema();
        if ($scope === 'global') {
            $rows = $this->getGlobalFaqs(true);
        } else {
            $rows = $this->getBranchFaqs((int)$branchId, true);
        }

        return array_map(static function (array $row): array {
            return [
                'faq_id' => (int)$row['id'],
                'scope' => (string)$row['scope'],
                'branch_id' => $row['branch_id'] !== null ? (int)$row['branch_id'] : '',
                'parent_global_id' => $row['parent_global_id'] !== null ? (int)$row['parent_global_id'] : '',
                'question' => (string)$row['question'],
                'answer' => (string)$row['answer'],
                'tags' => (string)($row['tags'] ?? ''),
                'is_active' => !empty($row['is_active']) ? '1' : '0',
                'updated_at' => (string)$row['updated_at'],
            ];
        }, $rows);
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    public function importRows(string $scope, ?int $branchId, array $rows): array
    {
        $this->ensureSchema();

        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $question = trim((string)($row['question'] ?? ''));
            $answer = trim((string)($row['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }

            $faqId = (int)($row['faq_id'] ?? 0);
            $parentGlobalId = trim((string)($row['parent_global_id'] ?? ''));
            $data = [
                'scope' => $scope,
                'branch_id' => $scope === 'branch' ? $branchId : null,
                'parent_global_id' => $parentGlobalId !== '' ? (int)$parentGlobalId : null,
                'question' => $question,
                'answer' => $answer,
                'tags' => trim((string)($row['tags'] ?? '')),
                'is_active' => ((string)($row['is_active'] ?? '1')) !== '0',
            ];

            if ($faqId > 0) {
                $existing = $scope === 'global' ? $this->findGlobal($faqId) : $this->findBranch($faqId, (int)$branchId);
                if ($existing) {
                    $this->updateEntry($faqId, $data);
                    $updated++;
                    continue;
                }
            }

            $this->create($data);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    public function findBranchOverrideForGlobal(int $branchId, int $globalFaqId): array|false
    {
        $this->ensureSchema();
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM faq_entries
             WHERE scope = "branch" AND branch_id = ? AND parent_global_id = ?
             LIMIT 1'
        );
        $stmt->execute([$branchId, $globalFaqId]);
        return $stmt->fetch() ?: false;
    }

    /**
     * @param list<string> $queryTokens
     * @param array<string, mixed> $row
     */
    private function keywordBonus(array $queryTokens, array $row): float
    {
        if (empty($queryTokens)) {
            return 0.0;
        }

        $haystack = mb_strtolower(
            trim((string)($row['question'] ?? '') . ' ' . (string)($row['answer'] ?? '') . ' ' . (string)($row['tags'] ?? '')),
            'UTF-8'
        );
        $bonus = 0.0;
        foreach ($queryTokens as $token) {
            if (str_contains($haystack, $token)) {
                $bonus += 0.06;
            }
        }

        return min(0.28, $bonus);
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        Database::getInstance()->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    private function ensureIndex(string $table, string $indexName, string $createSql): void
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*)
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $stmt->execute([$table, $indexName]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }
        Database::getInstance()->exec($createSql);
    }

    private function ensureTableForeignKey(): void
    {
        try {
            Database::getInstance()->exec(
                'ALTER TABLE faq_entries
                 ADD CONSTRAINT fk_faq_parent_global
                 FOREIGN KEY (parent_global_id) REFERENCES faq_entries(id) ON DELETE SET NULL'
            );
        } catch (\Throwable) {
            // ignore if already exists
        }
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
