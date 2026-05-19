<?php

declare(strict_types=1);

namespace App\Models;

class UserModel extends BaseModel
{
    protected string $table = 'users';

    public function findByEmail(string $email): array|false
    {
        return $this->query('SELECT * FROM users WHERE email = ? LIMIT 1', [$email])->fetch();
    }

    public function createUser(string $name, string $email, string $password, string $role, ?int $branchId = null): int
    {
        return $this->insert([
            'name'      => $name,
            'email'     => $email,
            'password'  => password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
            'role'      => $role,
            'branch_id' => $branchId,
        ]);
    }

    public function getByBranch(int $branchId): array
    {
        return $this->findAll('branch_id = ? AND is_active = 1', [$branchId], 'name ASC');
    }

    public function getAllAdmins(): array
    {
        return $this->query(
            'SELECT u.*, b.name AS branch_name FROM users u
             LEFT JOIN branches b ON u.branch_id = b.id
             ORDER BY u.role, u.name'
        )->fetchAll();
    }
}
