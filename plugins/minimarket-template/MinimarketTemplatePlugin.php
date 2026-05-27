<?php

declare(strict_types=1);

final class MinimarketTemplatePlugin
{
    public function getName(): string
    {
        return 'Minimarket Template';
    }

    public function getCode(): string
    {
        return 'minimarket-template';
    }

    public function getDescription(): string
    {
        return 'Retail minimarket template with inventory stock POS barcode and analytics.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getFeatures(): array
    {
        return [
            'inventory',
            'stock-movement',
            'barcode',
            'pos',
            'receipt-print',
            'low-stock',
            'expired-products',
            'analytics',
            'semantic-search',
        ];
    }
}
