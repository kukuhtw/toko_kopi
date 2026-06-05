<?php

declare(strict_types=1);

namespace KopiBot\Domains\Order;

class OrderDTO
{
    /**
     * @param OrderItemDTO[] $items
     */
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public int $customerId,
        public array $items,
        public ?string $promoCode = null,
        public string $channel = 'web'
    ) {}
}
