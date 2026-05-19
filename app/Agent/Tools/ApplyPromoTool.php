<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\ToolInterface;
use App\Helpers\Currency;
use App\Models\CartModel;
use App\Models\PromoModel;

final class ApplyPromoTool implements ToolInterface
{
    private CartModel $cartModel;
    private PromoModel $promoModel;

    public function __construct()
    {
        $this->cartModel = new CartModel();
        $this->promoModel = new PromoModel();
    }

    public function getName(): string
    {
        return 'apply_promo';
    }

    public function getDescription(): string
    {
        return 'Apply an explicit promo code to the current customer cart.';
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'promo_code' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $input, array $context = []): array
    {
        $cart = (array)($context['cart'] ?? []);
        $cartId = (int)($cart['id'] ?? 0);
        $sessionKey = (string)($cart['session_key'] ?? '');
        $branchId = (int)($context['branch_id'] ?? 0);
        $currency = (string)($context['currency'] ?? 'IDR');
        $lang = (string)($context['language'] ?? 'id');
        $message = (string)($context['message'] ?? '');

        if ($cartId <= 0 || $branchId <= 0 || $sessionKey === '') {
            return [
                'status' => 'failed',
                'message' => $lang === 'id'
                    ? 'Keranjang belum siap diproses untuk promo.'
                    : 'Cart is not ready for promo processing.',
            ];
        }

        $promoCode = $this->resolvePromoCode($input, $message);
        if ($promoCode === '') {
            return [
                'status' => 'needs_clarification',
                'message' => $lang === 'id'
                    ? 'Sebutkan kode promo secara eksplisit, misalnya `KOPI10`.'
                    : 'Please mention the promo code explicitly, for example `KOPI10`.',
            ];
        }

        $subtotal = $this->cartModel->getTotal($cartId);
        if ($subtotal <= 0) {
            return [
                'status' => 'failed',
                'message' => $lang === 'id'
                    ? 'Keranjang kamu masih kosong. Tambahkan item dulu sebelum memakai promo.'
                    : 'Your cart is empty. Add items before applying a promo.',
            ];
        }

        $promo = $this->promoModel->findByCode($promoCode, $branchId, (string)($context['now_local'] ?? ''));
        if (!$promo) {
            return [
                'status' => 'failed',
                'promo_code' => $promoCode,
                'message' => $lang === 'id'
                    ? "Kode promo {$promoCode} tidak ditemukan atau sudah tidak aktif."
                    : "Promo code {$promoCode} was not found or is no longer active.",
            ];
        }

        $cartItems = $this->cartModel->getItems($cartId);
        $discount = $this->promoModel->calculateDiscount($promo, $subtotal, $cartItems);
        if ($discount <= 0) {
            return [
                'status' => 'failed',
                'promo_code' => $promoCode,
                'message' => $this->buildRejectedMessage($promo, $promoCode, $currency, $lang),
            ];
        }

        $this->cartModel->applyPromo($cartId, $promoCode, $discount);
        $freshCart = $this->cartModel->getBySession($sessionKey) ?: $cart;

        return [
            'status' => 'applied',
            'promo_code' => $promoCode,
            'discount_amount' => $discount,
            'discount_formatted' => Currency::format($discount, $currency),
            'cart' => $freshCart,
            'cart_items' => $this->cartModel->getItems((int)($freshCart['id'] ?? $cartId)),
            'promo' => $promo,
        ];
    }

    private function resolvePromoCode(array $input, string $message): string
    {
        $promoCode = strtoupper(trim((string)($input['promo_code'] ?? '')));
        if ($promoCode !== '') {
            return preg_replace('/[^A-Z0-9_-]/', '', $promoCode) ?? $promoCode;
        }

        if (preg_match('/\b([A-Z0-9_-]{4,20})\b/u', strtoupper($message), $matches) === 1) {
            return preg_replace('/[^A-Z0-9_-]/', '', $matches[1]) ?? $matches[1];
        }

        return '';
    }

    private function buildRejectedMessage(array $promo, string $promoCode, string $currency, string $lang): string
    {
        if ((int)($promo['applies_to_category_id'] ?? 0) > 0) {
            return $lang === 'id'
                ? "Kode promo {$promoCode} hanya berlaku untuk kategori tertentu yang belum ada di keranjang kamu."
                : "Promo code {$promoCode} only applies to a category that is not in your cart yet.";
        }

        $minFormatted = Currency::format((float)($promo['min_order'] ?? 0), $currency);
        return $lang === 'id'
            ? "Kode promo {$promoCode} memerlukan minimum order {$minFormatted}."
            : "Promo code {$promoCode} requires a minimum order of {$minFormatted}.";
    }
}
