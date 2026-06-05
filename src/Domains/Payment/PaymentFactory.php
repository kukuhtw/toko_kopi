<?php

declare(strict_types=1);

namespace KopiBot\Domains\Payment;

use KopiBot\Plugins\Payment\MockPaymentProvider;
use InvalidArgumentException;

class PaymentFactory
{
    /** @var array<string, callable(): PaymentProviderInterface> */
    private static array $providers = [];

    public static function register(string $gateway, callable $factory): void
    {
        self::$providers[strtolower($gateway)] = $factory;
    }

    public static function make(string $gateway): PaymentProviderInterface
    {
        $gateway = strtolower(trim($gateway));

        if ($gateway === '' || $gateway === 'mock') {
            return new MockPaymentProvider();
        }

        if (isset(self::$providers[$gateway])) {
            $provider = (self::$providers[$gateway])();

            if (!$provider instanceof PaymentProviderInterface) {
                throw new InvalidArgumentException("Payment provider '{$gateway}' must implement PaymentProviderInterface");
            }

            return $provider;
        }

        return new MockPaymentProvider();
    }

    public static function registeredGateways(): array
    {
        return array_keys(self::$providers);
    }

    public static function reset(): void
    {
        self::$providers = [];
    }
}
