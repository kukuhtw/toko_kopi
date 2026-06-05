<?php

declare(strict_types=1);

namespace KopiBot\Domains\Cart;

use KopiBot\Domains\Order\OrderDTO;
use KopiBot\Domains\Order\OrderItemDTO;
use KopiBot\Domains\Order\OrderService;
use KopiBot\Domains\Payment\PaymentDTO;
use KopiBot\Domains\Payment\PaymentService;

class CartService
{
    public function __construct(
        private CartRepository $repository = new CartRepository(),
        private OrderService $orderService = new OrderService(),
        private PaymentService $paymentService = new PaymentService()
    ) {}

    public function addItem(int $tenantId, int $branchId, ?int $customerId, string $sessionId, CartItemDTO $item): array
    {
        if ($item->qty <= 0 || $item->price < 0) {
            return ['success' => false, 'message' => 'Invalid cart item'];
        }

        $cartId = $this->repository->findOrCreateCart($tenantId, $branchId, $customerId, $sessionId);
        $cartItemId = $this->repository->addItem($tenantId, $branchId, $cartId, $item);
        $items = $this->repository->getItems($tenantId, $cartId);

        return [
            'success' => true,
            'cart_id' => $cartId,
            'cart_item_id' => $cartItemId,
            'total_items' => count($items),
            'subtotal' => $this->calculateSubtotal($items),
        ];
    }

    public function checkout(int $tenantId, int $branchId, int $customerId, string $sessionId, string $customerName = 'Customer', ?string $customerEmail = null, ?string $customerPhone = null): array
    {
        $cartId = $this->repository->findOrCreateCart($tenantId, $branchId, $customerId, $sessionId);
        $items = $this->repository->getItems($tenantId, $cartId);

        if (empty($items)) {
            return ['success' => false, 'message' => 'Cart is empty'];
        }

        $orderItems = array_map(
            fn (array $item): OrderItemDTO => new OrderItemDTO((int) $item['product_id'], (string) $item['product_name'], (int) $item['qty'], (float) $item['price']),
            $items
        );

        $order = $this->orderService->createOrder(new OrderDTO(
            tenantId: $tenantId,
            branchId: $branchId,
            customerId: $customerId,
            items: $orderItems,
            channel: 'cart_checkout'
        ));

        if (empty($order['success'])) {
            return $order;
        }

        $payment = $this->paymentService->createCheckout(new PaymentDTO(
            tenantId: $tenantId,
            branchId: $branchId,
            orderId: (int) $order['order_id'],
            orderNo: (string) $order['order_no'],
            amount: (float) $order['grand_total'],
            customerName: $customerName,
            customerEmail: $customerEmail,
            customerPhone: $customerPhone,
            gateway: 'mock'
        ));

        $this->repository->markCheckedOut($tenantId, $cartId, (int) $order['order_id']);

        return [
            'success' => true,
            'cart_id' => $cartId,
            'order' => $order,
            'payment' => $payment,
        ];
    }

    private function calculateSubtotal(array $items): float
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) $item['subtotal'];
        }

        return $subtotal;
    }
}
