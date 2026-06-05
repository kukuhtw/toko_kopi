<?php

declare(strict_types=1);

namespace KopiBot\Domains\Payment;

class PaymentService
{
    public function __construct(
        private PaymentRepository $repository = new PaymentRepository(),
        private ?PaymentProviderInterface $provider = null
    ) {}

    public function createCheckout(PaymentDTO $dto): array
    {
        $provider = $this->provider ?? PaymentFactory::make($dto->gateway);
        $gatewayResult = $provider->createPayment($dto);

        if (empty($gatewayResult['success'])) {
            return [
                'success' => false,
                'error' => $gatewayResult['error'] ?? 'Payment gateway failed',
            ];
        }

        $paymentId = $this->repository->create([
            'tenant_id' => $dto->tenantId,
            'branch_id' => $dto->branchId,
            'order_id' => $dto->orderId,
            'payment_gateway' => $dto->gateway,
            'reference_no' => $gatewayResult['reference_no'],
            'amount' => $dto->amount,
            'status' => PaymentStatus::PENDING,
            'checkout_url' => $gatewayResult['checkout_url'],
        ]);

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'order_id' => $dto->orderId,
            'reference_no' => $gatewayResult['reference_no'],
            'checkout_url' => $gatewayResult['checkout_url'],
            'status' => PaymentStatus::PENDING,
        ];
    }

    public function markAsPaid(int $tenantId, string $referenceNo): array
    {
        return [
            'success' => $this->repository->updateStatus($tenantId, $referenceNo, PaymentStatus::PAID),
            'reference_no' => $referenceNo,
            'status' => PaymentStatus::PAID,
        ];
    }
}
