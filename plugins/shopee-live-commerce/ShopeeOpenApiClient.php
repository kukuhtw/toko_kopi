<?php

declare(strict_types=1);

final class ShopeeOpenApiClient
{
    public function __construct(
        private string $partnerId = '',
        private string $partnerKey = '',
        private string $accessToken = '',
        private string $shopId = ''
    ) {}

    public function getShopInfo(): array
    {
        return [
            'connected' => $this->accessToken !== '' && $this->shopId !== '',
            'partner_id' => $this->partnerId,
            'shop_id' => $this->shopId,
        ];
    }

    public function getOrders(): array
    {
        return [];
    }

    public function getProducts(): array
    {
        return [];
    }

    public function getLiveMetrics(): array
    {
        return [];
    }
}
