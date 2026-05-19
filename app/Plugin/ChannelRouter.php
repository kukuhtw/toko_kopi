<?php

declare(strict_types=1);

namespace App\Plugin;

/**
 * Registry channel yang didaftarkan oleh plugin.
 *
 * Channel yang berjalan sebagai plugin didaftarkan di sini.
 * Channel legacy yang masih punya endpoint sendiri tidak wajib masuk registry ini.
 *
 * Untuk channel baru dari plugin, daftarkan via filter 'channel.registered':
 *
 *   HookManager::addFilter('channel.registered', function(array $channels): array {
 *       $channels['line'] = new LineChannelProvider($config);
 *       return $channels;
 *   });
 *
 * Webhook URL untuk channel plugin:
 *   POST /api/channel/webhook.php?channel=line&branch=1
 */
final class ChannelRouter
{
    /** @var array<string, ChannelInterface>|null */
    private static ?array $channels = null;

    /**
     * Resolve semua channel yang terdaftar via filter.
     * Hasilnya di-cache untuk durasi satu request.
     *
     * @return array<string, ChannelInterface>
     */
    public static function all(): array
    {
        if (self::$channels === null) {
            $raw = HookManager::applyFilters('channel.registered', []);
            self::$channels = [];

            foreach ((array) $raw as $name => $channel) {
                if ($channel instanceof ChannelInterface) {
                    self::$channels[(string) $name] = $channel;
                }
            }
        }

        return self::$channels;
    }

    /** Ambil channel berdasarkan nama. Null jika tidak ditemukan. */
    public static function get(string $name): ?ChannelInterface
    {
        return self::all()[$name] ?? null;
    }

    /** Cek apakah channel dengan nama tertentu sudah terdaftar. */
    public static function has(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    /** Reset cache — berguna untuk testing. */
    public static function reset(): void
    {
        self::$channels = null;
    }
}
