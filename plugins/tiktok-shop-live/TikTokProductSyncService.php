<?php

declare(strict_types=1);

final class TikTokProductSyncService
{
    public function __construct(
        private TikTokShopLiveRepository $repo,
        private TikTokShopLiveClient $client
    ) {}

    public function syncFromTikTok(int $branchId): array
    {
        $products = $this->client->getProducts();
        $this->repo->logSync($branchId, 'product', 'PRODUCT_PULL', 'success', null, ['count' => count($products)], [], 'inbound');
        return $products;
    }

    public function syncToTikTok(int $branchId, array $localProducts): array
    {
        $this->repo->logSync($branchId, 'product', 'PRODUCT_PUSH', 'pending', null, ['count' => count($localProducts)], [], 'outbound');
        return ['success' => true, 'count' => count($localProducts)];
    }
}
