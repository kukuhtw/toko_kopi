<?php

declare(strict_types=1);

namespace KopiBot\Domains\Order;

class OrderItemDTO
{
    public function __construct(
        public int $productId,
        public string $productName,
        public int $qty,
        public float $price
    ) {}

    public function subtotal(): float
    {
        return $this->qty * $this->price;
    }
}
