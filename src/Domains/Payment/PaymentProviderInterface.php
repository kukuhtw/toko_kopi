<?php

declare(strict_types=1);

namespace KopiBot\Domains\Payment;

interface PaymentProviderInterface
{
    public function createPayment(PaymentDTO $dto): array;

    public function checkPayment(string $referenceNo): array;

    public function cancelPayment(string $referenceNo): array;
}
