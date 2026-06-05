<?php

declare(strict_types=1);

namespace KopiBot\Domains\Auth;

use KopiBot\Core\Database;
use PDO;

class RolePermissionService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function userHasPermission(int $tenantId, int $userId, string $permissionCode): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.tenant_id = u.tenant_id
             LEFT JOIN roles r ON r.id = ur.role_id OR r.role_code = u.role
             LEFT JOIN role_permissions rp ON rp.role_id = r.id
             LEFT JOIN permissions p ON p.id = rp.permission_id
             WHERE u.tenant_id = :tenant_id
               AND u.id = :user_id
               AND u.is_active = 1
               AND p.permission_code = :permission_code'
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'permission_code' => $permissionCode,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
