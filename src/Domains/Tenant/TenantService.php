<?php

declare(strict_types=1);

namespace KopiBot\Domains\Tenant;

class TenantService
{
    public function __construct(
        private TenantRepository $repository = new TenantRepository()
    ) {}

    public function getTenant(string $tenantCode): ?array
    {
        return $this->repository->findByCode($tenantCode);
    }
}
