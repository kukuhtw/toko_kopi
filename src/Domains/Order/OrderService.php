<?php

declare(strict_types=1);

namespace KopiBot\Domains\Order;

use Throwable;

class OrderService
{
    public function __construct(
        private OrderRepository $repository = new OrderRepository()
    ) {}

    public function createOrder(OrderDTO $dto): array
    {
        if (empty($dto->items)) {
            return [
                'success' => false,
                'errors' => ['Order items cannot be empty'],
            ];
        }

        foreach ($dto->items as $item) {
            if (!$item instanceof OrderItemDTO) {
                return [
                    'success' => false,
                    'errors' => ['Invalid order item payload'],
                ];
            }

            if ($item->qty <= 0 || $item->price < 0) {
                return [
                    'success' => false,
                    'errors' => ['Invalid item qty or price'],
                ];
            }
        }

        try {
            $this->repository->beginTransaction();

            $subtotal = $this->calculateSubtotal($dto->items);
            $discountTotal = 0.0;
            $grandTotal = max(0, $subtotal - $discountTotal);
            $orderNo = $this->generateOrderNo();

            $orderId = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'branch_id' => $dto->branchId,
                'customer_id' => $dto->customerId,
                'order_no' => $orderNo,
                'status' => OrderStatus::WAITING_PAYMENT,
                'channel' => $dto->channel,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
            ]);

            foreach ($dto->items as $item) {
                $this->repository->createItem($dto->tenantId, $dto->branchId, $orderId, $item);
            }

            $this->repository->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'status' => OrderStatus::WAITING_PAYMENT,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
            ];
        } catch (Throwable $e) {
            $this->repository->rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function markAsPaid(int $tenantId, int $orderId): array
    {
        return [
            'success' => $this->repository->updateStatus($tenantId, $orderId, OrderStatus::PAID),
            'order_id' => $orderId,
            'status' => OrderStatus::PAID,
        ];
    }

    private function calculateSubtotal(array $items): float
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += $item->subtotal();
        }

        return $subtotal;
    }

    private function generateOrderNo(): string
    {
        return 'ORD' . date('YmdHis') . random_int(100, 999);
    }
}
