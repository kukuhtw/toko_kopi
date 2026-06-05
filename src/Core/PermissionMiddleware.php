<?php

declare(strict_types=1);

namespace KopiBot\Core;

use KopiBot\Domains\Auth\RolePermissionService;

class PermissionMiddleware
{
    public function __construct(
        private RolePermissionService $service = new RolePermissionService()
    ) {}

    public function check(array $user, string $permissionCode): bool
    {
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $userId = (int) ($user['user_id'] ?? 0);

        if ($tenantId <= 0 || $userId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid auth context'], 403);
            return false;
        }

        if (!$this->service->userHasPermission($tenantId, $userId, $permissionCode)) {
            Response::json(['success' => false, 'message' => 'Forbidden', 'permission' => $permissionCode], 403);
            return false;
        }

        return true;
    }
}
