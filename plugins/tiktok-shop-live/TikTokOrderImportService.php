<?php

declare(strict_types=1);

final class TikTokOrderImportService
{
    public function __construct(
        private TikTokShopLiveRepository $repo,
        private TikTokShopLiveClient $client
    ) {}

    public function importOrders(int $branchId): array
    {
        $orders = $this->client->getOrders();
        foreach ($orders as $order) {
            if (is_array($order)) {
                $this->repo->saveOrder($branchId, $order);
            }
        }
        $this->repo->logSync($branchId, 'order', 'ORDER_IMPORT', 'success', null, ['count' => count($orders)], [], 'inbound');
        return ['success' => true, 'count' => count($orders)];
    }
}
