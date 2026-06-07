<?php

declare(strict_types=1);

namespace KopiBot\Core;

use KopiBot\Contracts\VectorStoreInterface;

class VectorStoreRegistry
{
    public static function all(): array
    {
        $stores = HookManager::applyFilters('vectorstores', []);
        $normalized = [];

        foreach ((array)$stores as $key => $store) {
            if ($store instanceof VectorStoreInterface) {
                $normalized[(string)$key] = $store;
            }
        }

        return $normalized;
    }

    public static function preferred(?string $name = null): ?VectorStoreInterface
    {
        $stores = self::all();

        if ($name !== null && $name !== '' && isset($stores[$name]) && $stores[$name]->isAvailable()) {
            return $stores[$name];
        }

        foreach ($stores as $store) {
            if ($store->isAvailable()) {
                return $store;
            }
        }

        return null;
    }
}
