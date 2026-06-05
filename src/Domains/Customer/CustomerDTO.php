<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

class CustomerDTO
{
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $whatsapp = null,
        public ?string $address = null,
        public string $source = 'web'
    ) {}
}
