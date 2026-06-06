<?php

declare(strict_types=1);

namespace KopiBot\Intent;

final class IntentPatternRegistry
{
    public static function extend(array $patterns, string $intent, array $keywords): array
    {
        $existing = $patterns[$intent] ?? [];
        $patterns[$intent] = array_values(array_unique(array_merge($existing, $keywords)));
        return $patterns;
    }
}
