<?php

declare(strict_types=1);

namespace KopiBot\Domains\FAQ;

use KopiBot\Core\Database;
use PDO;

class FaqRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(FaqDTO $dto): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO faq_items (tenant_id, branch_id, category, question, answer, tags, is_active, created_at) VALUES (:tenant_id, :branch_id, :category, :question, :answer, :tags, :is_active, NOW())'
        );

        $stmt->execute([
            'tenant_id' => $dto->tenantId,
            'branch_id' => $dto->branchId,
            'category' => $dto->category,
            'question' => $dto->question,
            'answer' => $dto->answer,
            'tags' => $dto->tags,
            'is_active' => $dto->isActive ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function searchKeyword(int $tenantId, ?int $branchId, string $keyword, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM faq_items WHERE tenant_id = :tenant_id AND is_active = 1 AND (branch_id = :branch_id OR branch_id IS NULL) AND (question LIKE :keyword OR answer LIKE :keyword OR tags LIKE :keyword OR category LIKE :keyword) ORDER BY CASE WHEN branch_id = :branch_id THEN 0 ELSE 1 END, id DESC LIMIT :limit'
        );

        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':branch_id', $branchId, $branchId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':keyword', '%' . trim($keyword) . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function logUnanswered(int $tenantId, ?int $branchId, ?int $customerId, string $question): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO faq_unanswered (tenant_id, branch_id, customer_id, question, created_at) VALUES (:tenant_id, :branch_id, :customer_id, :question, NOW())'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'question' => $question,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
