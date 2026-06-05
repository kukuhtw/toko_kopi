<?php

declare(strict_types=1);

namespace KopiBot\Domains\Tenant;

class TenantDTO
{
    public function __construct(
        public string $tenantCode,
        public string $tenantName,
        public string $businessType,
        public string $ownerName,
        public string $ownerEmail,
        public string $ownerPhone
    ) {}
}
