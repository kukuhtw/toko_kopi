<?php

declare(strict_types=1);

namespace KopiBot\Domains\Auth;

class UserDTO
{
    public function __construct(
        public int $tenantId,
        public string $name,
        public string $email,
        public string $password,
        public string $role = 'merchant_admin'
    ) {}
}
