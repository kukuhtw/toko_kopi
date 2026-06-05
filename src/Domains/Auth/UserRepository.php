<?php

declare(strict_types=1);

namespace KopiBot\Domains\Auth;

use KopiBot\Core\Database;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(UserDTO $dto, string $passwordHash): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (tenant_id, name, email, password_hash, role, is_active, created_at) VALUES (:tenant_id, :name, :email, :password_hash, :role, 1, NOW())');
        $stmt->execute([
            'tenant_id' => $dto->tenantId,
            'name' => $dto->name,
            'email' => strtolower($dto->email),
            'password_hash' => $passwordHash,
            'role' => $dto->role,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByEmail(int $tenantId, string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE tenant_id = :tenant_id AND email = :email LIMIT 1');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'email' => strtolower($email),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
