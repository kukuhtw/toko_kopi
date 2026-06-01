<?php

declare(strict_types=1);

final class TikTokShopLiveService
{
    public function __construct(
        private TikTokShopLiveRepository $repo,
        private TikTokShopLiveClient $client
    ) {}

    public function processWebhook(int $branchId, array $payload): array
    {
        $event = strtoupper((string)($payload['event'] ?? $payload['type'] ?? $payload['event_type'] ?? 'UNKNOWN'));
        $referenceId = (string)($payload['order_id'] ?? $payload['live_id'] ?? $payload['room_id'] ?? '');

        if (str_contains($event, 'ORDER')) {
            $this->repo->saveOrder($branchId, $payload);
            $this->repo->logSync($branchId, 'order', $event, 'success', $referenceId, $payload);
            return ['success' => true, 'entity' => 'order', 'event' => $event];
        }

        if (str_contains($event, 'LIVE') || isset($payload['viewers']) || isset($payload['room_id'])) {
            $this->repo->saveLiveMetric($branchId, $payload);
            $recommendation = $this->buildLiveRecommendation($payload);
            $this->repo->saveRecommendation($branchId, (string)($payload['live_id'] ?? $payload['room_id'] ?? ''), $recommendation, $payload);
            $this->repo->logSync($branchId, 'live', $event, 'success', $referenceId, $payload, ['recommendation' => $recommendation]);
            return ['success' => true, 'entity' => 'live', 'event' => $event, 'recommendation' => $recommendation];
        }

        $this->repo->logSync($branchId, 'webhook', $event, 'ignored', $referenceId, $payload);
        return ['success' => true, 'entity' => 'webhook', 'event' => $event, 'status' => 'ignored'];
    }

    public function syncLiveMetrics(int $branchId): array
    {
        $metrics = $this->client->getLiveMetrics();
        foreach ($metrics as $metric) {
            if (is_array($metric)) {
                $this->repo->saveLiveMetric($branchId, $metric);
            }
        }
        return $metrics;
    }

    private function buildLiveRecommendation(array $payload): string
    {
        $viewers = (int)($payload['viewers'] ?? 0);
        $orders = (int)($payload['orders_count'] ?? $payload['orders'] ?? 0);
        $comments = (int)($payload['comments_count'] ?? $payload['comments'] ?? 0);
        $revenue = (float)($payload['revenue_amount'] ?? $payload['revenue'] ?? 0);
        $lines = [];

        if ($viewers > 1000 && $orders < 20) {
            $lines[] = 'Viewer tinggi tetapi order masih rendah. Jelaskan ulang benefit produk, promo, dan cara checkout.';
        }
        if ($comments > 100) {
            $lines[] = 'Komentar ramai. Prioritaskan jawaban tentang harga, stok, ongkir, dan promo.';
        }
        if ($orders > 50) {
            $lines[] = 'Order kuat. Tampilkan kembali produk terlaris dan buat countdown flash sale singkat.';
        }
        if ($revenue > 0) {
            $lines[] = 'Revenue sudah terbentuk. Dorong bundle dan upselling untuk menaikkan nilai order rata-rata.';
        }
        if ($lines === []) {
            $lines[] = 'Pantau viewer, komentar, klik produk, dan order. Mulai dengan produk hero lalu ajak checkout.';
        }

        return implode("\n", $lines);
    }
}
