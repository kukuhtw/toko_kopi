<?php

declare(strict_types=1);

namespace KopiBot\Domains\Branch;

class BranchDTO
{
    public function __construct(
        public int $tenantId,
        public string $branchCode,
        public string $branchName,
        public string $city,
        public string $timezone = 'Asia/Jakarta'
    ) {}
}
