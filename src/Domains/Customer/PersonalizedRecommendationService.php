<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

use KopiBot\Domains\Product\ProductService;

class PersonalizedRecommendationService
{
    public function __construct(
        private FavoriteProductService $favoriteProductService = new FavoriteProductService(),
        private ProductService $productService = new ProductService()
    ) {}

    public function recommend(int $tenantId, int $branchId, int $customerId, int $limit = 5): array
    {
        $favorites = $this->favoriteProductService->topProducts($tenantId, $customerId, 3);
        $menu = $this->productService->getMenu($tenantId, $branchId);
        $products = $menu['data'] ?? [];

        $favoriteProductIds = array_map(fn (array $item): int => (int) $item['product_id'], $favorites);
        $recommendations = [];

        foreach ($products as $product) {
            if (in_array((int) $product['id'], $favoriteProductIds, true)) {
                continue;
            }

            $recommendations[] = [
                'product_id' => (int) $product['id'],
                'product_name' => $product['name'],
                'category' => $product['category'] ?? null,
                'base_price' => (float) $product['base_price'],
                'reason' => $this->buildReason($favorites, $product),
            ];

            if (count($recommendations) >= $limit) {
                break;
            }
        }

        return [
            'success' => true,
            'customer_id' => $customerId,
            'favorites' => $favorites,
            'recommendations' => $recommendations,
            'message' => $this->formatRecommendations($recommendations),
        ];
    }

    private function buildReason(array $favorites, array $product): string
    {
        if (empty($favorites)) {
            return 'Rekomendasi dari menu tersedia.';
        }

        $favoriteName = $favorites[0]['product_name'] ?? 'produk favorit Anda';

        return 'Cocok sebagai tambahan untuk pelanggan yang sering membeli ' . $favoriteName . '.';
    }

    private function formatRecommendations(array $recommendations): string
    {
        if (empty($recommendations)) {
            return 'Belum ada rekomendasi personal. Coba lihat menu yang tersedia.';
        }

        $lines = ['Rekomendasi untuk Anda:'];
        foreach (array_values($recommendations) as $index => $item) {
            $lines[] = sprintf(
                '%d. %s Rp %s, %s',
                $index + 1,
                $item['product_name'],
                number_format((float) $item['base_price'], 0, ',', '.'),
                $item['reason']
            );
        }

        return implode("\n", $lines);
    }
}
