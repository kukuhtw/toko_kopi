<?php

declare(strict_types=1);

final class TikTokLiveAnalyticsService
{
    public function summarize(array $metric): array
    {
        $viewers = max(0, (int)($metric['viewers'] ?? 0));
        $orders = max(0, (int)($metric['orders_count'] ?? $metric['orders'] ?? 0));
        $revenue = max(0, (float)($metric['revenue_amount'] ?? $metric['revenue'] ?? 0));
        $comments = max(0, (int)($metric['comments_count'] ?? $metric['comments'] ?? 0));

        $conversionRate = $viewers > 0 ? round(($orders / $viewers) * 100, 2) : 0.0;
        $averageOrderValue = $orders > 0 ? round($revenue / $orders, 2) : 0.0;

        return [
            'viewers' => $viewers,
            'orders' => $orders,
            'comments' => $comments,
            'revenue' => $revenue,
            'conversion_rate' => $conversionRate,
            'average_order_value' => $averageOrderValue,
            'insight' => $this->buildInsight($viewers, $orders, $comments, $revenue, $conversionRate),
        ];
    }

    private function buildInsight(int $viewers, int $orders, int $comments, float $revenue, float $conversionRate): string
    {
        if ($viewers > 1000 && $conversionRate < 1) {
            return 'Traffic live tinggi, tetapi conversion rendah. Perkuat call to action, tampilkan harga, stok, promo, dan cara checkout.';
        }

        if ($orders > 50 && $revenue > 0) {
            return 'Order dan revenue sedang baik. Dorong bundle, upselling, dan countdown promo agar momentum live tidak turun.';
        }

        if ($comments > 100 && $orders < 10) {
            return 'Komentar ramai tetapi order masih rendah. Host perlu menjawab keberatan customer seperti harga, ongkir, stok, dan trust.';
        }

        return 'Live masih perlu dipantau. Tampilkan produk hero, jelaskan benefit utama, lalu ajak audience checkout.';
    }
}
