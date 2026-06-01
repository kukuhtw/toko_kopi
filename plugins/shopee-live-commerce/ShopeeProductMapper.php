<?php

declare(strict_types=1);

final class ShopeeProductMapper
{
    public function mapMenuItem(array $menuItem): array
    {
        return [
            'item_name' => (string)($menuItem['name'] ?? ''),
            'description' => (string)($menuItem['description'] ?? ''),
            'price' => (float)($menuItem['price'] ?? 0),
            'item_sku' => (string)($menuItem['slug'] ?? ''),
            'stock' => (int)($menuItem['stock'] ?? 0),
        ];
    }

    public function mapShopeeItem(array $item): array
    {
        return [
            'name' => (string)($item['item_name'] ?? $item['name'] ?? ''),
            'description' => (string)($item['description'] ?? ''),
            'price' => (float)($item['price'] ?? 0),
            'slug' => (string)($item['item_sku'] ?? $item['sku'] ?? ''),
            'stock' => (int)($item['stock'] ?? 0),
            'raw_payload' => $item,
        ];
    }
}
