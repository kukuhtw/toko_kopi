<?php

declare(strict_types=1);

namespace KopiBot\Domains\Customer;

use KopiBot\Domains\Cart\CartItemDTO;
use KopiBot\Domains\Cart\CartService;

class RepeatOrderService
{
    public function __construct(
        private CustomerOrderHistoryService $history = new CustomerOrderHistoryService(),
        private CartService $cartService = new CartService()
    ) {}

    public function repeatLastOrder(int $tenantId, int $branchId, int $customerId, string $sessionId): array
    {
        $lastOrder = $this->history->lastOrder($tenantId, $customerId);

        if (!$lastOrder || empty($lastOrder['items'])) {
            return [
                'success' => false,
                'message' => 'Belum ada riwayat order untuk diulang.',
            ];
        }

        $results = [];
        foreach ($lastOrder['items'] as $item) {
            $results[] = $this->cartService->addItem(
                tenantId: $tenantId,
                branchId: $branchId,
                customerId: $customerId,
                sessionId: $sessionId,
                item: new CartItemDTO(
                    productId: (int) $item['product_id'],
                    productName: (string) $item['product_name'],
                    qty: (int) $item['qty'],
                    price: (float) $item['price']
                )
            );
        }

        return [
            'success' => true,
            'message' => 'Pesanan terakhir sudah dimasukkan ke keranjang. Ketik checkout untuk lanjut pembayaran.',
            'last_order' => $lastOrder,
            'cart_results' => $results,
        ];
    }
}
