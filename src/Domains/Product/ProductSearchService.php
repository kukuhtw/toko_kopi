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
        $keyword = trim($keyword);
        $queries = $this->buildSearchQueries($keyword);
        $products = [];
        $matchedKeyword = $keyword;

        foreach ($queries as $query) {
            $products = $this->repository->search($tenantId, $branchId, $query);
            if (!empty($products)) {
                $matchedKeyword = $query;
                break;
            }
        }

        return [
            'success' => true,
            'keyword' => $keyword,
            'matched_keyword' => $matchedKeyword,
            'searched_keywords' => $queries,
            'total' => count($products),
            'data' => $products,
        ];
    }

    private function buildSearchQueries(string $keyword): array
    {
        $queries = [$keyword];
        $normalized = strtolower($keyword);

        $map = [
            'manis' => ['kopi susu', 'latte', 'coklat'],
            'dingin' => ['ice', 'es', 'cold brew'],
            'panas' => ['hot', 'americano', 'kopi'],
            'anak' => ['coklat', 'susu', 'non coffee'],
            'tidak kopi' => ['coklat', 'tea', 'susu'],
            'non kopi' => ['coklat', 'tea', 'susu'],
            'murah' => ['kopi', 'tea'],
            'segar' => ['tea', 'lemon', 'ice'],
        ];

        foreach ($map as $needle => $alternatives) {
            if (str_contains($normalized, $needle)) {
                foreach ($alternatives as $alternative) {
                    $queries[] = $alternative;
                }
            }
        }

        return array_values(array_unique(array_filter($queries)));
    }
}
