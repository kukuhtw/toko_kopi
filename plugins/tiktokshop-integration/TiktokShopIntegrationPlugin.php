<?php

declare(strict_types=1);

final class TiktokShopIntegrationPlugin
{
    public function getName(): string
    {
        return 'TikTok Shop Integration';
    }

    public function getCode(): string
    {
        return 'tiktokshop-integration';
    }

    public function getDescription(): string
    {
        return 'TikTok Shop marketplace integration plugin with auth product stock and order sync.';
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
