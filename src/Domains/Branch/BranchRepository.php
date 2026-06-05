<?php

declare(strict_types=1);

namespace KopiBot\Domains\Branch;

use KopiBot\Core\Database;
use PDO;

class BranchRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByCode(string $branchCode): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM branches WHERE branch_code = :branch_code LIMIT 1');
        $stmt->execute(['branch_code' => $branchCode]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
