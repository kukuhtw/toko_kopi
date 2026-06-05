<?php

declare(strict_types=1);

namespace KopiBot\Domains\Promo;

class PromoDTO
{
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public string $promoCode,
        public float $subtotal,
        public ?int $customerId = null,
        public ?int $orderId = null
    ) {}
}
