<?php

declare(strict_types=1);

final class TikTokShopLiveService
{
    public function __construct(
        private TikTokShopLiveRepository $repo,
        private TikTokShopLiveClient $client
    ){}

    public function syncLiveMetrics(int $branchId): array
    {
        return $this->client->getLiveMetrics();
    }
}
