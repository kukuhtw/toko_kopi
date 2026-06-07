<?php

declare(strict_types=1);

namespace KopiBot\Domains\Knowledge;

use KopiBot\Core\DatabaseConnection;

class KnowledgeRepository
{
    public function search(int $tenantId, ?int $branchId, string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $terms = $this->extractTerms($query);
        if ($terms === []) {
            return [];
        }

        $sources = [];
        foreach ($this->candidateTables() as $table) {
            if (!$this->tableExists($table['name'])) {
                continue;
            }

            $rows = $this->searchTable($table, $tenantId, $branchId, $terms, $limit);
            foreach ($rows as $row) {
                $sources[] = $row;
            }
        }

        usort($sources, static fn(array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return array_slice($sources, 0, $limit);
    }

    private function candidateTables(): array
    {
        return [
            [
                'name' => 'knowledge_base',
                'title' => 'title',
                'content' => 'content',
                'tenant' => 'tenant_id',
                'branch' => 'branch_id',
            ],
            [
                'name' => 'knowledge_documents',
                'title' => 'title',
                'content' => 'content',
                'tenant' => 'tenant_id',
                'branch' => 'branch_id',
            ],
            [
                'name' => 'faqs',
                'title' => 'question',
                'content' => 'answer',
                'tenant' => 'tenant_id',
                'branch' => 'branch_id',
            ],
            [
                'name' => 'faq',
                'title' => 'question',
                'content' => 'answer',
                'tenant' => 'tenant_id',
                'branch' => 'branch_id',
            ],
        ];
    }

    private function searchTable(array $table, int $tenantId, ?int $branchId, array $terms, int $limit): array
    {
        $db = DatabaseConnection::getInstance();
        $columns = $this->columns($table['name']);

        if (!in_array($table['title'], $columns, true) || !in_array($table['content'], $columns, true)) {
            return [];
        }

        $where = [];
        $params = [];

        if (in_array($table['tenant'], $columns, true)) {
            $where[] = "({$table['tenant']} = ? OR {$table['tenant']} IS NULL OR {$table['tenant']} = 0)";
            $params[] = $tenantId;
        }

        if ($branchId !== null && $branchId > 0 && in_array($table['branch'], $columns, true)) {
            $where[] = "({$table['branch']} = ? OR {$table['branch']} IS NULL OR {$table['branch']} = 0)";
            $params[] = $branchId;
        }

        $likeParts = [];
        foreach ($terms as $term) {
            $likeParts[] = "({$table['title']} LIKE ? OR {$table['content']} LIKE ?)";
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
        }

        $where[] = '(' . implode(' OR ', $likeParts) . ')';

        $sql = sprintf(
            'SELECT %s AS title, %s AS content FROM %s WHERE %s LIMIT %d',
            $table['title'],
            $table['content'],
            $table['name'],
            implode(' AND ', $where),
            max(1, $limit)
        );

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }

        return array_map(function (array $row) use ($table, $terms): array {
            $title = (string)($row['title'] ?? '');
            $content = (string)($row['content'] ?? '');
            return [
                'source' => $table['name'],
                'title' => $title,
                'content' => mb_substr($content, 0, 1500),
                'score' => $this->score($title . ' ' . $content, $terms),
            ];
        }, $rows);
    }

    private function extractTerms(string $query): array
    {
        $normalized = mb_strtolower($query, 'UTF-8');
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [];
        $stopWords = ['yang', 'dan', 'atau', 'apa', 'bagaimana', 'berapa', 'untuk', 'dengan', 'saya', 'mau', 'ingin', 'the', 'and', 'or', 'how', 'what'];

        $terms = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 3 || in_array($part, $stopWords, true)) {
                continue;
            }
            $terms[] = $part;
        }

        return array_values(array_unique(array_slice($terms, 0, 8)));
    }

    private function score(string $text, array $terms): int
    {
        $haystack = mb_strtolower($text, 'UTF-8');
        $score = 0;
        foreach ($terms as $term) {
            $score += substr_count($haystack, $term);
        }
        return $score;
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = DatabaseConnection::getInstance()->prepare(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
            );
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function columns(string $table): array
    {
        try {
            $stmt = DatabaseConnection::getInstance()->prepare(
                'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $stmt->execute([$table]);
            return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);
        } catch (\Throwable) {
            return [];
        }
    }
}
