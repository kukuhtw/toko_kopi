<?php

declare(strict_types=1);

final class TikTokOrderToCoreOrder
{
    public function __construct(
        private TikTokInternalOrderMapper $mapper,
        private TikTokOrderRepository $repository
    ) {}

    public function process(array $payload, int $branchId): array
    {
        $mapped = $this->mapper->map($payload, $branchId);
        $saved = $this->repository->saveExternalOrder($mapped);

        return [
            'success' => $saved,
            'external_channel' => 'tiktok_shop_live',
            'external_order_id' => $mapped['external_order_id'] ?? '',
            'mapped_order' => $mapped,
            'note' => 'Saved to TikTok bridge table. Core orders table integration should be enabled after confirming core schema fields.'
        ];
    }
}
