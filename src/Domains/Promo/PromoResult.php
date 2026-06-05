<?php

declare(strict_types=1);

namespace KopiBot\Domains\Promo;

class PromoResult
{
    public function __construct(
        public bool $success,
        public float $discountAmount = 0.0,
        public string $message = '',
        public ?array $promo = null
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'discount_amount' => $this->discountAmount,
            'message' => $this->message,
            'promo' => $this->promo,
        ];
    }
}
