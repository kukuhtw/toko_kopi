<?php

declare(strict_types=1);

namespace KopiBot\Plugins\Payment;

use KopiBot\Domains\Payment\PaymentDTO;
use KopiBot\Domains\Payment\PaymentProviderInterface;

class MockPaymentProvider implements PaymentProviderInterface
{
    public function createPayment(PaymentDTO $dto): array
    {
        $referenceNo = 'PAY' . date('YmdHis') . random_int(100, 999);

        return [
            'success' => true,
            'reference_no' => $referenceNo,
            'checkout_url' => sprintf('https://example.com/mock-payment/%s', $referenceNo),
        ];
    }

    public function checkPayment(string $referenceNo): array
    {
        return [
            'success' => true,
            'reference_no' => $referenceNo,
            'status' => 'pending',
        ];
    }

    public function cancelPayment(string $referenceNo): array
    {
        return [
            'success' => true,
            'reference_no' => $referenceNo,
            'status' => 'cancelled',
        ];
    }
}
