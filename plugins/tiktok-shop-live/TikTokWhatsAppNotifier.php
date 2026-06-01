<?php

declare(strict_types=1);

final class TikTokWhatsAppNotifier
{
    public function notifyNewOrder(array $order): string
    {
        $orderId = (string)($order['order_id'] ?? '-');
        $customer = (string)($order['customer_name'] ?? '-');
        $amount = (float)($order['total_amount'] ?? 0);

        return "🛒 Order Baru TikTok Shop\nOrder ID: {$orderId}\nCustomer: {$customer}\nTotal: Rp " . number_format($amount, 0, ',', '.');
    }

    public function notifyLiveInsight(string $message): string
    {
        return "📈 TikTok Live Insight\n" . $message;
    }
}
