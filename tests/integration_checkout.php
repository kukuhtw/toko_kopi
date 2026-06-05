<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/runtime.php';

use KopiBot\Domains\Cart\CartItemDTO;
use KopiBot\Domains\Order\OrderDTO;
use KopiBot\Domains\Order\OrderItemDTO;
use KopiBot\Domains\Payment\PaymentDTO;
use KopiBot\Domains\Payment\PaymentFactory;
use KopiBot\Domains\Payment\PaymentProviderInterface;

final class CheckoutSmokePaymentProvider implements PaymentProviderInterface
{
    public function createPayment(PaymentDTO $dto): array
    {
        return [
            'success' => true,
            'reference_no' => 'PAY-' . $dto->orderNo,
            'checkout_url' => 'https://example.test/checkout/' . $dto->orderNo,
        ];
    }
}

PaymentFactory::reset();
PaymentFactory::register('smoke-checkout', fn() => new CheckoutSmokePaymentProvider());

$cartItem = new CartItemDTO(
    productId: 101,
    productName: 'Cappuccino',
    qty: 2,
    price: 25000
);

$orderItem = new OrderItemDTO(
    productId: $cartItem->productId,
    productName: $cartItem->productName,
    qty: $cartItem->qty,
    price: $cartItem->price
);

$orderDto = new OrderDTO(
    tenantId: 1,
    branchId: 1,
    customerId: 77,
    items: [$orderItem],
    channel: 'integration_checkout'
);

$subtotal = array_reduce(
    $orderDto->items,
    static fn (float $carry, OrderItemDTO $item): float => $carry + $item->subtotal(),
    0.0
);

$orderNo = 'ORD-SMOKE-001';
$paymentDto = new PaymentDTO(
    tenantId: $orderDto->tenantId,
    branchId: $orderDto->branchId,
    orderId: 555,
    orderNo: $orderNo,
    amount: $subtotal,
    customerName: 'Smoke Customer',
    customerEmail: 'smoke@example.test',
    customerPhone: '0800000000',
    gateway: 'smoke-checkout'
);

$provider = PaymentFactory::make($paymentDto->gateway);
$payment = $provider->createPayment($paymentDto);

$checks = [
    'cart_subtotal' => $cartItem->subtotal() === 50000.0,
    'order_subtotal' => $subtotal === 50000.0,
    'payment_provider_registered' => in_array('smoke-checkout', PaymentFactory::registeredGateways(), true),
    'payment_success' => ($payment['success'] ?? false) === true,
    'payment_reference' => ($payment['reference_no'] ?? '') === 'PAY-' . $orderNo,
    'payment_checkout_url' => ($payment['checkout_url'] ?? '') === 'https://example.test/checkout/' . $orderNo,
];

$success = !in_array(false, $checks, true);

echo json_encode([
    'success' => $success,
    'checks' => $checks,
], JSON_PRETTY_PRINT) . PHP_EOL;

exit($success ? 0 : 1);
