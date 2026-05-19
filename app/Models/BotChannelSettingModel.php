<?php

declare(strict_types=1);

namespace App\Models;

class BotChannelSettingModel extends BaseModel
{
    protected string $table = 'branch_bot_settings';

    public function getActiveByBranchAndPlatform(int $branchId, string $platform): array|false
    {
        return $this->query(
            'SELECT * FROM branch_bot_settings
             WHERE branch_id = ? AND platform = ? AND is_active = 1
             LIMIT 1',
            [$branchId, $platform]
        )->fetch();
    }

    public function getActiveByPlatformAndWebhookToken(string $platform, string $webhookToken): array|false
    {
        return $this->query(
            'SELECT * FROM branch_bot_settings
             WHERE platform = ? AND webhook_token = ? AND is_active = 1
             LIMIT 1',
            [$platform, $webhookToken]
        )->fetch();
    }

    public function decodeExtraConfig(array $setting): array
    {
        $raw = $setting['extra_config'] ?? '';
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
