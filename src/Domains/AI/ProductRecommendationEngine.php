<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

use KopiBot\Domains\Product\ProductService;

class ProductRecommendationEngine
{
    public function __construct(
        private ProductService $productService = new ProductService()
    ) {}

    public function recommendFromMenu(int $tenantId, int $branchId, int $limit = 5): array
    {
        $menu = $this->productService->getMenu($tenantId, $branchId);
        $products = $menu['data'] ?? [];

        return array_slice($products, 0, $limit);
    }

    public function formatOptions(array $products): string
    {
        if (empty($products)) {
            return 'Belum ada rekomendasi produk.';
        }

        $lines = [];
        foreach (array_values($products) as $index => $product) {
            $lines[] = sprintf(
                '%d. %s Rp %s',
                $index + 1,
                $product['name'] ?? '-',
                number_format((float) ($product['base_price'] ?? 0), 0, ',', '.')
            );
        }

        return implode("\n", $lines);
    }
}
