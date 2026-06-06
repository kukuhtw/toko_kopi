<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

use KopiBot\Core\DatabaseConnection;
use PDO;

class CustomerRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DatabaseConnection::getInstance();
    }

    public function find(int $customerId): ?array
    {
        if ($customerId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$customerId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByEmail(int $tenantId, string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE tenant_id = :tenant_id AND email = :email LIMIT 1');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'email' => CustomerNormalizer::email($email),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByPhone(int $tenantId, string $phone): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE tenant_id = :tenant_id AND (phone = :phone OR whatsapp = :phone) LIMIT 1');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'phone' => CustomerNormalizer::whatsapp($phone),
        ]);
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
            'email' => $dto->email !== null ? CustomerNormalizer::email($dto->email) : null,
            'phone' => $dto->phone !== null ? CustomerNormalizer::whatsapp($dto->phone) : null,
            'whatsapp' => $dto->whatsapp !== null ? CustomerNormalizer::whatsapp($dto->whatsapp) : null,
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
            'email' => $dto->email !== null ? CustomerNormalizer::email($dto->email) : null,
            'phone' => $dto->phone !== null ? CustomerNormalizer::whatsapp($dto->phone) : null,
            'whatsapp' => $dto->whatsapp !== null ? CustomerNormalizer::whatsapp($dto->whatsapp) : null,
            'address' => $dto->address,
        ]);
    }

    public function normalizeWhatsApp(string $number, string $defaultCountryCode = '+62'): string
    {
        return CustomerNormalizer::whatsapp($number, $defaultCountryCode);
    }

    public static function normalizeEmail(string $email): string
    {
        return CustomerNormalizer::email($email);
    }
}
