<?php

declare(strict_types=1);

namespace KopiBot\Domains\Loyalty;

use KopiBot\Core\Database;
use PDO;

class LoyaltyRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function addTransaction(LoyaltyTransactionDTO $dto): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO loyalty_transactions (tenant_id, branch_id, customer_id, point, transaction_type, reference_type, reference_id, description, created_at) VALUES (:tenant_id, :branch_id, :customer_id, :point, :transaction_type, :reference_type, :reference_id, :description, NOW())'
        );

        $stmt->execute([
            'tenant_id' => $dto->tenantId,
            'branch_id' => $dto->branchId,
            'customer_id' => $dto->customerId,
            'point' => $dto->point,
            'transaction_type' => $dto->transactionType,
            'reference_type' => $dto->referenceType,
            'reference_id' => $dto->referenceId,
            'description' => $dto->description,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function addBalance(int $tenantId, int $customerId, int $point): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE customers SET loyalty_point_balance = loyalty_point_balance + :point, updated_at = NOW() WHERE tenant_id = :tenant_id AND id = :customer_id'
        );

        return $stmt->execute([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'point' => $point,
        ]);
    }

    public function reduceBalance(int $tenantId, int $customerId, int $point): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE customers SET loyalty_point_balance = loyalty_point_balance - :point, updated_at = NOW() WHERE tenant_id = :tenant_id AND id = :customer_id AND loyalty_point_balance >= :point'
        );

        return $stmt->execute([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'point' => $point,
        ]);
    }

    public function getBalance(int $tenantId, int $customerId): int
    {
        $stmt = $this->db->prepare('SELECT loyalty_point_balance FROM customers WHERE tenant_id = :tenant_id AND id = :customer_id LIMIT 1');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
        ]);

        return (int) $stmt->fetchColumn();
    }
}
