<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

use KopiBot\Core\Database;
use PDO;

class CustomerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByEmail(int $tenantId, string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE tenant_id = :tenant_id AND email = :email LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'email' => $email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByPhone(int $tenantId, string $phone): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE tenant_id = :tenant_id AND (phone = :phone OR whatsapp = :phone) LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'phone' => $phone]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(CustomerDTO $dto): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO customers (tenant_id, branch_id, name, email, phone, whatsapp, address, source, loyalty_point_balance, created_at) VALUES (:tenant_id, :branch_id, :name, :email, :phone, :whatsapp, :address, :source, 0, NOW())'
        );

        $stmt->execute([
            'tenant_id' => $dto->tenantId,
            'branch_id' => $dto->branchId,
            'name' => $dto->name,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'whatsapp' => $dto->whatsapp,
            'address' => $dto->address,
            'source' => $dto->source,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateProfile(int $customerId, CustomerDTO $dto): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE customers SET name = COALESCE(:name, name), email = COALESCE(:email, email), phone = COALESCE(:phone, phone), whatsapp = COALESCE(:whatsapp, whatsapp), address = COALESCE(:address, address), updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id'
        );

        return $stmt->execute([
            'id' => $customerId,
            'tenant_id' => $dto->tenantId,
            'name' => $dto->name,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'whatsapp' => $dto->whatsapp,
            'address' => $dto->address,
        ]);
    }
}
