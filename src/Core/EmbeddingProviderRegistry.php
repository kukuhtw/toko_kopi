<?php

declare(strict_types=1);

namespace KopiBot\Core;

use KopiBot\Contracts\EmbeddingProviderInterface;

class EmbeddingProviderRegistry
{
    public static function all(): array
    {
        $providers = HookManager::applyFilters('embedding.providers', []);
        $normalized = [];

        foreach ((array)$providers as $key => $provider) {
            if ($provider instanceof EmbeddingProviderInterface) {
                $normalized[(string)$key] = $provider;
            }
        }

        return $normalized;
    }

    public static function preferred(?string $name = null): ?EmbeddingProviderInterface
    {
        $providers = self::all();

        if ($name !== null && $name !== '' && isset($providers[$name]) && $providers[$name]->isAvailable()) {
            return $providers[$name];
        }

        foreach ($providers as $provider) {
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        return null;
    }
}
