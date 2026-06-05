<?php

declare(strict_types=1);

namespace KopiBot\Domains\Payment;

class PaymentDTO
{
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public int $orderId,
        public string $orderNo,
        public float $amount,
        public string $customerName,
        public ?string $customerEmail = null,
        public ?string $customerPhone = null,
        public string $gateway = 'mock'
    ) {}
}
