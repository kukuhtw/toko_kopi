<?php

declare(strict_types=1);

namespace App\Plugin;

use KopiBot\Contracts\ChannelInterface as ComposerChannelInterface;

final class ChannelRouter
{
    private static ?array $channels = null;

    public static function all(): array
    {
        if (self::$channels === null) {
            $raw = HookManager::applyFilters('channel.registered', []);
            self::$channels = [];

            foreach ((array)$raw as $name => $channel) {
                if ($channel instanceof ChannelInterface || $channel instanceof ComposerChannelInterface) {
                    self::$channels[(string)$name] = $channel;
                }
            }
        }

        return self::$channels;
    }

    public static function get(string $name): ChannelInterface|ComposerChannelInterface|null
    {
        return self::all()[$name] ?? null;
    }

    public static function has(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    public static function reset(): void
    {
        self::$channels = null;
    }
}
