<?php

declare(strict_types=1);

namespace KopiBot\Domains\AI;

use KopiBot\Domains\Product\ProductService;

class ProductRecommendationEngine
{
    public function __construct(
        private ProductService $productService = new ProductService()
    ) {}

    public function recommendFromMenu(int $tenantId, int $branchId, int $limit = 5, string $query = ''): array
    {
        $menu = $this->productService->getMenu($tenantId, $branchId);
        $products = $menu['data'] ?? [];

        if ($query !== '') {
            $products = $this->rankByQuery($products, $query);
        }

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
                number_format((float)($product['base_price'] ?? 0), 0, ',', '.')
            );
        }

        return implode("\n", $lines);
    }

    private function rankByQuery(array $products, string $query): array
    {
        $query = mb_strtolower($query, 'UTF-8');
        $signals = $this->signals($query);

        usort($products, function (array $a, array $b) use ($signals, $query): int {
            return $this->score($b, $signals, $query) <=> $this->score($a, $signals, $query);
        });

        return $products;
    }

    private function signals(string $query): array
    {
        $signals = [$query];
        $map = [
            'manis' => ['susu', 'latte', 'coklat', 'caramel', 'vanilla'],
            'dingin' => ['ice', 'iced', 'es', 'cold'],
            'panas' => ['hot', 'kopi', 'americano'],
            'anak' => ['susu', 'coklat', 'chocolate', 'non coffee'],
            'segar' => ['tea', 'lemon', 'ice', 'yakult'],
            'murah' => ['kopi', 'tea'],
            'kopi' => ['coffee', 'espresso', 'americano', 'latte'],
            'teh' => ['tea'],
        ];

        foreach ($map as $needle => $terms) {
            if (str_contains($query, $needle)) {
                array_push($signals, ...$terms);
            }
        }

        return array_values(array_unique(array_filter($signals)));
    }

    private function score(array $product, array $signals, string $query): int
    {
        $haystack = mb_strtolower(implode(' ', [
            (string)($product['name'] ?? ''),
            (string)($product['description'] ?? ''),
            (string)($product['category_name'] ?? ''),
            (string)($product['sku'] ?? ''),
        ]), 'UTF-8');

        $score = 0;
        foreach ($signals as $signal) {
            if ($signal !== '' && str_contains($haystack, $signal)) {
                $score += $signal === $query ? 5 : 2;
            }
        }

        return $score;
    }
}
