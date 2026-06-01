<?php

declare(strict_types=1);

final class TikTokProductMapper
{
    public function mapMenuItem(array $menuItem): array
    {
        return [
            'name' => (string)($menuItem['name'] ?? ''),
            'description' => (string)($menuItem['description'] ?? ''),
            'price' => (float)($menuItem['price'] ?? 0),
            'sku' => (string)($menuItem['slug'] ?? ''),
            'stock' => (int)($menuItem['stock'] ?? 0),
        ];
    }
}
