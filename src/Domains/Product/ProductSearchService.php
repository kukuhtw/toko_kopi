<?php

declare(strict_types=1);

namespace KopiBot\Domains\Product;

class ProductSearchService
{
    public function __construct(
        private ProductRepository $repository = new ProductRepository()
    ) {}

    public function search(int $tenantId, int $branchId, string $keyword): array
    {
        $products = $this->repository->search($tenantId, $branchId, trim($keyword));

        return [
            'success' => true,
            'keyword' => $keyword,
            'total' => count($products),
            'data' => $products,
        ];
    }
}
