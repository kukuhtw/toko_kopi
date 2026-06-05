<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/runtime.php';

use KopiBot\Domains\Payment\PaymentDTO;
use KopiBot\Domains\Payment\PaymentFactory;
use KopiBot\Domains\Payment\PaymentProviderInterface;

final class SmokePaymentProvider implements PaymentProviderInterface
{
    public function createPayment(PaymentDTO $dto): array
    {
        return [
            'success' => true,
            'reference_no' => 'SMOKE-001',
            'checkout_url' => 'https://example.test/pay',
        ];
    }
}

PaymentFactory::reset();
PaymentFactory::register('smoke', fn() => new SmokePaymentProvider());

$provider = PaymentFactory::make('smoke');

$checks = [
    'provider_registered' => in_array('smoke', PaymentFactory::registeredGateways(), true),
    'provider_instance' => $provider instanceof PaymentProviderInterface,
];

$success = !in_array(false, $checks, true);

echo json_encode([
    'success' => $success,
    'checks' => $checks,
], JSON_PRETTY_PRINT) . PHP_EOL;

exit($success ? 0 : 1);
