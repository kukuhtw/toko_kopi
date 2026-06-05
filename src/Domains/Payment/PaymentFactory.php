<?php

declare(strict_types=1);

namespace KopiBot\Domains\Payment;

use KopiBot\Plugins\Payment\MockPaymentProvider;

class PaymentFactory
{
    public static function make(string $gateway): PaymentProviderInterface
    {
        return match ($gateway) {
            'mock' => new MockPaymentProvider(),
            default => new MockPaymentProvider(),
        };
    }
}
