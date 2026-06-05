<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

class CustomerInsightService
{
    public function __construct(
        private CustomerOrderHistoryService $history = new CustomerOrderHistoryService()
    ) {}

    public function summarizeLastOrder(int $tenantId, int $customerId): array
    {
        $lastOrder = $this->history->lastOrder($tenantId, $customerId);

        if (!$lastOrder) {
            return [
                'success' => false,
                'message' => 'Belum ada riwayat order.',
            ];
        }

        $lines = [];
        foreach ($lastOrder['items'] as $item) {
            $lines[] = sprintf('%s x%d', $item['product_name'], (int) $item['qty']);
        }

        return [
            'success' => true,
            'order_id' => (int) $lastOrder['id'],
            'order_no' => $lastOrder['order_no'],
            'summary' => implode("\n", $lines),
            'grand_total' => (float) $lastOrder['grand_total'],
        ];
    }
}
