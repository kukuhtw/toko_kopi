<?php

declare(strict_types=1);

namespace App\Services;

final class IntentPatternRegistry
{
    /**
     * @param array<string, array<int, string>> $patterns
     * @param array<int, string> $keywords
     * @return array<string, array<int, string>>
     */
    public static function extend(array $patterns, string $intent, array $keywords): array
    {
        $existing = $patterns[$intent] ?? [];
        $patterns[$intent] = array_values(array_unique(array_merge($existing, $keywords)));
        return $patterns;
    }
}
