<?php

declare(strict_types=1);

final class ShopeeLiveService
{
    public function __construct(private ShopeeLiveRepository $repo) {}

    public function processWebhook(int $branchId, array $payload): array
    {
        $event = strtoupper((string)($payload['event'] ?? $payload['type'] ?? 'UNKNOWN'));
        $reference = (string)($payload['order_sn'] ?? $payload['live_id'] ?? '');

        if (str_contains($event, 'ORDER')) {
            $this->repo->saveOrder($branchId, $payload);
            $this->repo->logSync($branchId, 'order', $event, 'success', $reference, $payload);
            return ['success' => true, 'entity' => 'order', 'event' => $event];
        }

        if (str_contains($event, 'LIVE') || isset($payload['viewers'])) {
            $this->repo->saveLiveMetric($branchId, $payload);
            $this->repo->logSync($branchId, 'live', $event, 'success', $reference, $payload);
            return ['success' => true, 'entity' => 'live', 'event' => $event];
        }

        $this->repo->logSync($branchId, 'webhook', $event, 'ignored', $reference, $payload);
        return ['success' => true, 'entity' => 'webhook', 'event' => $event, 'status' => 'ignored'];
    }
}
