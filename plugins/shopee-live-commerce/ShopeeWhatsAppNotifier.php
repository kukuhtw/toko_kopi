<?php

declare(strict_types=1);

final class ShopeeWhatsAppNotifier
{
    public function notifyNewOrder(array $order): string
    {
        $orderSn = (string)($order['order_sn'] ?? $order['ordersn'] ?? '-');
        $customer = (string)($order['customer_name'] ?? 'Shopee Customer');
        $amount = (float)($order['total_amount'] ?? 0);

        return "🛒 Order Baru Shopee Live\nOrder SN: {$orderSn}\nCustomer: {$customer}\nTotal: Rp " . number_format($amount, 0, ',', '.');
    }

    public function notifyLiveInsight(string $message): string
    {
        return "📈 Shopee Live Insight\n" . $message;
    }
}
