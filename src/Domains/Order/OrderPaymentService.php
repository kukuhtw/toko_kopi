<?php

declare(strict_types=1);

namespace KopiBot\Domains\Order;

final class OrderPaymentService
{
    public function __construct(
        private readonly ?OrderRepository $repository = null
    ) {
    }

    private function repo(): OrderRepository
    {
        return $this->repository ?? new OrderRepository();
    }

    public function findByOrderNumber(string $orderNumber): ?array
    {
        return $this->repo()->findByOrderNumber($orderNumber);
    }

    public function markPaid(int $orderId): bool
    {
        return $this->repo()->updatePaymentStatus($orderId, 'paid');
    }

    public function markFailed(int $orderId): bool
    {
        return $this->repo()->updatePaymentStatus($orderId, 'failed');
    }

    public function markUnpaid(int $orderId): bool
    {
        return $this->repo()->updatePaymentStatus($orderId, 'unpaid');
    }

    public function updatePaymentStatus(int $orderId, string $status): bool
    {
        return $this->repo()->updatePaymentStatus($orderId, $status);
    }
}
