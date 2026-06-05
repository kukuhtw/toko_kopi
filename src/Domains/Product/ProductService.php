<?php

declare(strict_types=1);

namespace KopiBot\Domains\Product;

class ProductService
{
    public function __construct(
        private ProductRepository $repository = new ProductRepository()
    ) {}

    public function create(ProductDTO $dto): array
    {
        $productId = $this->repository->create($dto);

        return [
            'success' => true,
            'product_id' => $productId,
        ];
    }

    public function getMenu(int $tenantId, int $branchId): array
    {
        $products = $this->repository->findActiveByBranch($tenantId, $branchId);

        return [
            'success' => true,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'total' => count($products),
            'data' => $products,
        ];
    }
}
