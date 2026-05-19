<?php

declare(strict_types=1);

namespace App\WhatsAppProviders;

use App\Config\Database;

class ProviderFactory
{
    private static array $map = [
        'FonnteProvider'        => FonnteProvider::class,
        'WablasProvider'        => WablasProvider::class,
        'MetaCloudApiProvider'  => MetaCloudApiProvider::class,
        'GenericWebhookProvider'=> GenericWebhookProvider::class,
        'VonageProvider'        => VonageProvider::class,
    ];

    public static function forBranch(int $branchId, string $waNumber): ?WhatsAppProviderInterface
    {
        $db = Database::getInstance();
        $row = $db->prepare(
            'SELECT bws.*, wp.adapter_class
             FROM branch_whatsapp_settings bws
             JOIN whatsapp_providers wp ON bws.provider_id = wp.id
             WHERE bws.branch_id = ? AND bws.wa_number = ? AND bws.is_active = 1
             LIMIT 1'
        );
        $row->execute([$branchId, $waNumber]);
        $setting = $row->fetch();

        if (!$setting) return null;

        return self::make($setting['adapter_class'], $setting);
    }

    public static function forBranchAny(int $branchId): ?WhatsAppProviderInterface
    {
        $db = Database::getInstance();
        $row = $db->prepare(
            'SELECT bws.*, wp.adapter_class
             FROM branch_whatsapp_settings bws
             JOIN whatsapp_providers wp ON bws.provider_id = wp.id
             WHERE bws.branch_id = ? AND bws.is_active = 1
             LIMIT 1'
        );
        $row->execute([$branchId]);
        $setting = $row->fetch();

        if (!$setting) return null;

        return self::make($setting['adapter_class'], $setting);
    }

    public static function make(string $adapterClass, array $config): ?WhatsAppProviderInterface
    {
        $class = self::$map[$adapterClass] ?? null;
        if (!$class) {
            error_log("[ProviderFactory] Unknown adapter: {$adapterClass}");
            return null;
        }
        return new $class($config);
    }

    /**
     * Find branch by incoming WA number across all settings.
     * Returns ['branch_id', 'adapter_class', ...config] or null.
     */
    public static function findByWaNumber(string $waNumber): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT bws.*, wp.adapter_class
             FROM branch_whatsapp_settings bws
             JOIN whatsapp_providers wp ON bws.provider_id = wp.id
             WHERE bws.wa_number = ? AND bws.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$waNumber]);
        return $stmt->fetch() ?: null;
    }
}
