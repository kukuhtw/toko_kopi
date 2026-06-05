<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

class CustomerPreferenceService
{
    public function __construct(
        private FavoriteProductService $favoriteProductService = new FavoriteProductService(),
        private CustomerInsightService $insightService = new CustomerInsightService()
    ) {}

    public function profile(int $tenantId, int $customerId): array
    {
        $favorites = $this->favoriteProductService->topProducts($tenantId, $customerId, 5);
        $lastOrder = $this->insightService->summarizeLastOrder($tenantId, $customerId);

        return [
            'success' => true,
            'customer_id' => $customerId,
            'favorites' => $favorites,
            'favorites_text' => $this->favoriteProductService->formatFavorites($favorites),
            'last_order' => $lastOrder,
        ];
    }
}
