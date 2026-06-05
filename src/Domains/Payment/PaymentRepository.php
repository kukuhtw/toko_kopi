<?php

declare(strict_types=1);

namespace KopiBot\Domains\Payment;

use KopiBot\Core\Database;
use PDO;

class PaymentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payments (tenant_id, branch_id, order_id, payment_gateway, reference_no, amount, status, checkout_url, created_at) VALUES (:tenant_id, :branch_id, :order_id, :payment_gateway, :reference_no, :amount, :status, :checkout_url, NOW())'
        );

        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function findByReferenceNo(int $tenantId, string $referenceNo): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE tenant_id = :tenant_id AND reference_no = :reference_no LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'reference_no' => $referenceNo]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateStatus(int $tenantId, string $referenceNo, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE payments SET status = :status, updated_at = NOW() WHERE tenant_id = :tenant_id AND reference_no = :reference_no');

        return $stmt->execute([
            'tenant_id' => $tenantId,
            'reference_no' => $referenceNo,
            'status' => $status,
        ]);
    }
}
