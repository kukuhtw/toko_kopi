<?php

declare(strict_types=1);

final class TikTokShopLiveClient
{
    public function __construct(private array $config = []){}

    public function getProducts(): array
    {
        return [];
    }

    public function getOrders(): array
    {
        return [];
    }

    public function getLiveMetrics(): array
    {
        return [];
    }
}
