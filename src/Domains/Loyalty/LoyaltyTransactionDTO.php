<?php

declare(strict_types=1);

namespace KopiBot\Domains\Loyalty;

class LoyaltyTransactionDTO
{
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public int $customerId,
        public int $point,
        public string $transactionType,
        public string $referenceType,
        public int $referenceId,
        public string $description = ''
    ) {}
}
