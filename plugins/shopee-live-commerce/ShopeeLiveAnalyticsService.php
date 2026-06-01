<?php

declare(strict_types=1);

final class ShopeeLiveAnalyticsService
{
    public function summarize(array $metric): array
    {
        $viewers = max(0, (int)($metric['viewers'] ?? 0));
        $orders = max(0, (int)($metric['orders'] ?? $metric['orders_count'] ?? 0));
        $revenue = max(0, (float)($metric['revenue'] ?? $metric['revenue_amount'] ?? 0));
        $comments = max(0, (int)($metric['comments'] ?? $metric['comments_count'] ?? 0));

        $conversion = $viewers > 0 ? round(($orders / $viewers) * 100, 2) : 0.0;
        $aov = $orders > 0 ? round($revenue / $orders, 2) : 0.0;

        return [
            'viewers' => $viewers,
            'orders' => $orders,
            'comments' => $comments,
            'revenue' => $revenue,
            'conversion_rate' => $conversion,
            'average_order_value' => $aov,
            'insight' => $this->buildInsight($viewers, $orders, $comments, $conversion),
        ];
    }

    private function buildInsight(int $viewers, int $orders, int $comments, float $conversion): string
    {
        if ($viewers > 1000 && $conversion < 1) {
            return 'Viewer Shopee Live tinggi, tetapi conversion rendah. Tekankan voucher, gratis ongkir, stok terbatas, dan instruksi checkout.';
        }
        if ($orders > 50) {
            return 'Order Shopee Live sedang kuat. Dorong bundle, flash sale, dan reminder voucher sebelum habis.';
        }
        if ($comments > 100 && $orders < 10) {
            return 'Komentar ramai tetapi order rendah. Host perlu menjawab harga, voucher, ongkir, dan trust secara berulang.';
        }
        return 'Pantau performa live. Tampilkan produk hero, voucher, gratis ongkir, dan ajak penonton klik keranjang.';
    }
}
