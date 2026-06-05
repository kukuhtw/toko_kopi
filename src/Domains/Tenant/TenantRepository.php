<?php

declare(strict_types=1);

namespace KopiBot\Domains\Tenant;

use KopiBot\Core\Database;
use PDO;

class TenantRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByCode(string $tenantCode): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tenants WHERE tenant_code = :tenant_code LIMIT 1');
        $stmt->execute(['tenant_code' => $tenantCode]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
