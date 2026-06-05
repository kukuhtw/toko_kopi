<?php

declare(strict_types=1);

namespace KopiBot\Domains\Product;

use KopiBot\Core\Database;
use PDO;

class ProductRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(ProductDTO $dto): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO products (tenant_id, branch_id, sku, name, category, description, base_price, image_url, is_active, created_at) VALUES (:tenant_id, :branch_id, :sku, :name, :category, :description, :base_price, :image_url, :is_active, NOW())'
        );

        $stmt->execute([
            'tenant_id' => $dto->tenantId,
            'branch_id' => $dto->branchId,
            'sku' => $dto->sku,
            'name' => $dto->name,
            'category' => $dto->category,
            'description' => $dto->description,
            'base_price' => $dto->basePrice,
            'image_url' => $dto->imageUrl,
            'is_active' => $dto->isActive ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $productId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $productId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findActiveByBranch(int $tenantId, int $branchId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE tenant_id = :tenant_id AND branch_id = :branch_id AND is_active = 1 ORDER BY name ASC');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
        ]);

        return $stmt->fetchAll();
    }

    public function search(int $tenantId, int $branchId, string $keyword): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM products WHERE tenant_id = :tenant_id AND branch_id = :branch_id AND is_active = 1 AND (name LIKE :keyword OR category LIKE :keyword OR description LIKE :keyword) ORDER BY name ASC LIMIT 20'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'keyword' => '%' . $keyword . '%',
        ]);

        return $stmt->fetchAll();
    }
}
