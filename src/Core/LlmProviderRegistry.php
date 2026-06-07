<?php

declare(strict_types=1);

namespace KopiBot\Core;

use KopiBot\Contracts\LlmProviderInterface;

class LlmProviderRegistry
{
    public static function all(): array
    {
        $providers = HookManager::applyFilters('llm.providers', []);
        $normalized = [];

        foreach ((array)$providers as $key => $provider) {
            if ($provider instanceof LlmProviderInterface) {
                $normalized[(string)$key] = $provider;
            }
        }

        return $normalized;
    }

    public static function preferred(?string $name = null): ?LlmProviderInterface
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
