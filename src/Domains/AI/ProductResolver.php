<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

use KopiBot\Domains\Product\ProductSearchService;

class ProductResolver
{
    public function __construct(
        private ProductSearchService $productSearchService = new ProductSearchService()
    ) {}

    public function resolve(int $tenantId, int $branchId, string $query): array
    {
        $result = $this->productSearchService->search($tenantId, $branchId, $query);
        $items = $result['data'] ?? [];

        if (count($items) === 1) {
            return [
                'status' => 'resolved',
                'product' => $items[0],
                'options' => [],
            ];
        }

        if (count($items) > 1) {
            return [
                'status' => 'ambiguous',
                'product' => null,
                'options' => array_slice($items, 0, 5),
            ];
        }

        return [
            'status' => 'not_found',
            'product' => null,
            'options' => [],
        ];
    }
}
