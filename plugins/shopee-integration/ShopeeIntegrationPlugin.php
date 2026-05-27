<?php

declare(strict_types=1);

final class ShopeeIntegrationPlugin
{
    public function getName(): string
    {
        return 'Shopee Integration';
    }

    public function getCode(): string
    {
        return 'shopee-integration';
    }

    public function getDescription(): string
    {
        return 'Shopee marketplace integration plugin with auth product stock and order sync.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getFeatures(): array
    {
        return [
            'oauth',
            'product-sync',
            'stock-sync',
            'order-sync',
            'webhook',
            'sync-logs',
        ];
    }
}
