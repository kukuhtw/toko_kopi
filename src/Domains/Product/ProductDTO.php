<?php

declare(strict_types=1);

namespace KopiBot\Domains\Product;

class ProductDTO
{
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public string $name,
        public string $category,
        public string $description,
        public float $basePrice,
        public ?string $sku = null,
        public ?string $imageUrl = null,
        public bool $isActive = true
    ) {}
}
