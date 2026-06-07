<?php

declare(strict_types=1);

namespace KopiBot\Domains\Order;

use App\Helpers\Sanitize;
use App\Models\{BranchModel, CartModel, CustomerModel, OrderModel, PromoModel};
use KopiBot\Core\HookManager;

class CheckoutService
{
    public function checkoutWebCart(array $input): array
    {
        $rawSession = (string)($input['session_id'] ?? $input['session_key'] ?? session_id());
        $branchId = (int)($input['branch_id'] ?? 0);

        if ($branchId <= 0) {
            return ['success' => false, 'message' => 'branch_id is required'];
        }

        $sessionKey = hash('sha256', "web:{$branchId}:{$rawSession}");
        return $this->checkoutSession($branchId, $sessionKey, $rawSession, $input, 'web');
    }

    public function checkoutSession(int $branchId, string $sessionKey, string $rawSession, array $input, string $channel = 'web'): array
    {
        $cartModel = new CartModel();
        $cart = $cartModel->getBySession($sessionKey);

        if (!$cart) {
            return ['success' => false, 'message' => 'Cart not found'];
        }

        $items = $cartModel->getItems($cart['id']);
        if ($items === []) {
            return ['success' => false, 'message' => 'Cart is empty'];
        }

        $branchModel = new BranchModel();
        $this->applyPromoIfNeeded($branchId, $cart, $items, $input, $cartModel, $branchModel);
        $cart = $cartModel->getBySession($sessionKey) ?: $cart;

        $fulfillmentType = strtolower(trim((string)($input['fulfillment_type'] ?? 'delivery')));
        if (!in_array($fulfillmentType, ['pickup', 'table', 'delivery'], true)) {
            $fulfillmentType = 'delivery';
        }

        $validation = $this->validateCustomerInput($input, $fulfillmentType);
        if ($validation !== null) {
            return ['success' => false, 'message' => $validation];
        }

        $customerModel = new CustomerModel();
        $resolvedCustomer = $customerModel->resolveWebCustomer(
            $rawSession,
            Sanitize::string((string)($input['name'] ?? '')),
            (string)($input['email'] ?? ''),
            (string)($input['whatsapp'] ?? '')
        );

        $customerId = (int)($resolvedCustomer['id'] ?? $cart['customer_id'] ?? 0);
        $cartModel->getOrCreate($sessionKey, $branchId, $customerId);
        $cart = $cartModel->getBySession($sessionKey) ?: $cart;
        $items = $cartModel->getItems($cart['id']);
        $customer = $customerModel->find($customerId);

        if (!$customer) {
            return ['success' => false, 'message' => 'Customer not found'];
        }

        $customerData = [
            'name' => Sanitize::string((string)($input['name'] ?? '')),
            'email' => filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
            'whatsapp' => preg_replace('/[^0-9+]/', '', (string)($input['whatsapp'] ?? '')),
            'fulfillment_type' => $fulfillmentType,
            'address' => $fulfillmentType === 'delivery' ? Sanitize::string((string)($input['address'] ?? '')) : '',
            'postal_code' => $fulfillmentType === 'delivery' ? preg_replace('/\D/', '', (string)($input['postal_code'] ?? '')) : '',
            'table_number' => $fulfillmentType === 'table' ? Sanitize::string((string)($input['table_number'] ?? '')) : '',
        ];

        $customerData = HookManager::applyFilters('cart.before_checkout', $customerData, $cart, $items, $branchId);

        try {
            $cartWithChannel = array_merge($cart, ['channel' => $channel]);
            $orderModel = new OrderModel();
            $ppnRate = $branchModel->getPpnRate($branchId);
            $orderId = $orderModel->createFromCart($cartWithChannel, $items, $customerData, $customerId, $ppnRate);
            $order = $orderModel->getWithItems($orderId);
            $orderResponse = HookManager::applyFilters('order.checkout_response', $order, $order, $branchId);

            $cartModel->clearCart($cart['id']);

            $customerModel->updateInfo($customerId, [
                'name' => $customerData['name'],
                'email' => $customerData['email'],
            ]);

            if ($customerData['fulfillment_type'] === 'delivery') {
                $customerModel->updateProfile($customerId, [
                    'address' => $customerData['address'],
                    'postal_code' => $customerData['postal_code'],
                ]);
            }

            return [
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $orderResponse,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e instanceof \RuntimeException ? $e->getMessage() : 'Failed to create order',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function validateCustomerInput(array $input, string $fulfillmentType): ?string
    {
        foreach (['name', 'whatsapp'] as $field) {
            if (empty($input[$field])) {
                return "Field '{$field}' is required";
            }
        }

        if ($fulfillmentType === 'delivery' && empty($input['address'])) {
            return "Field 'address' is required";
        }

        if ($fulfillmentType === 'table' && trim((string)($input['table_number'] ?? '')) === '') {
            return "Field 'table_number' is required";
        }

        return null;
    }

    private function applyPromoIfNeeded(int $branchId, array $cart, array $items, array $input, CartModel $cartModel, BranchModel $branchModel): void
    {
        $promoCode = strtoupper(trim((string)($input['promo_code'] ?? '')));
        $subtotal = array_sum(array_map(static fn($item) => $item['quantity'] * $item['unit_price'], $items));
        $tz = $branchModel->getTimezone($branchId);
        $nowLocal = (new \DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d H:i:s');
        $promoModel = new PromoModel();

        if ($promoCode !== '' && ($cart['promo_code'] ?? '') !== $promoCode) {
            $promo = $promoModel->findByCode($promoCode, $branchId, $nowLocal);
            if ($promo && $subtotal >= (float)$promo['min_order']) {
                $discount = $promoModel->calculateDiscount($promo, $subtotal, $items);
                $cartModel->applyPromo($cart['id'], $promoCode, $discount);
            }
            return;
        }

        if (!empty($cart['promo_code'])) {
            return;
        }

        $autoPromo = $promoModel->getBestAutoApply(
            $branchId,
            $subtotal,
            (int)($cart['customer_id'] ?? 0),
            $items,
            $nowLocal
        );

        if ($autoPromo) {
            $discount = $promoModel->calculateDiscount($autoPromo, $subtotal, $items);
            $cartModel->applyPromo($cart['id'], (string)($autoPromo['promo_code'] ?? ''), $discount);
        }
    }
}
