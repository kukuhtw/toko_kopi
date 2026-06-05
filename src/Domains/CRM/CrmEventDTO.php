<?php

declare(strict_types=1);

namespace KopiBot\Domains\CRM;

class CrmEventDTO
{
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public int $customerId,
        public string $eventType,
        public array $payload = [],
        public string $source = 'system'
    ) {}
}
