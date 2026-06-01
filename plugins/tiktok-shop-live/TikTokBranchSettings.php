<?php

declare(strict_types=1);

final class TikTokBranchSettings
{
    public function __construct(private TikTokShopLiveRepository $repo) {}

    public function getConfig(int $branchId): array
    {
        return [
            'app_key' => $this->repo->getBranchSetting($branchId, 'app_key'),
            'app_secret' => $this->repo->getBranchSetting($branchId, 'app_secret'),
            'access_token' => $this->repo->getBranchSetting($branchId, 'access_token'),
            'refresh_token' => $this->repo->getBranchSetting($branchId, 'refresh_token'),
            'shop_cipher' => $this->repo->getBranchSetting($branchId, 'shop_cipher'),
            'webhook_secret' => $this->repo->getBranchSetting($branchId, 'webhook_secret'),
            'sync_orders' => $this->repo->getBranchSetting($branchId, 'sync_orders', '1') === '1',
            'sync_products' => $this->repo->getBranchSetting($branchId, 'sync_products', '1') === '1',
            'notify_whatsapp' => $this->repo->getBranchSetting($branchId, 'notify_whatsapp', '0') === '1',
        ];
    }
}
