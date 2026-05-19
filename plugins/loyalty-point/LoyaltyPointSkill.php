<?php

declare(strict_types=1);

use App\Helpers\Currency;
use App\Skills\SkillInterface;

class LoyaltyPointSkill implements SkillInterface
{
    public function canHandle(string $intent): bool
    {
        return in_array($intent, ['cek_poin_loyalty', 'pakai_poin_loyalty', 'hapus_poin_loyalty'], true);
    }

    public function handle(array $context): array
    {
        if (($context['intent'] ?? '') === 'hapus_poin_loyalty') {
            return $this->handleClearRedeem($context);
        }

        if (($context['intent'] ?? '') === 'pakai_poin_loyalty') {
            return $this->handleRedeem($context);
        }

        $lang      = (string)($context['language'] ?? 'id');
        $branchId  = (int)($context['branch_id'] ?? 0);
        $customer  = (array)($context['customer'] ?? []);
        $convCtx   = (array)($context['conv_context'] ?? []);
        $repo      = new LoyaltyPointRepository();
        $balance   = $repo->getBalance($branchId, (int)($customer['id'] ?? 0));

        $reply = $lang === 'en'
            ? "Your loyalty balance is *" . number_format((int)$balance['balance_points']) . "* points.\nLifetime points collected: *" . number_format((int)$balance['lifetime_points']) . "*."
            : "Saldo loyalty kamu saat ini *" . number_format((int)$balance['balance_points']) . "* poin.\nTotal poin yang pernah terkumpul: *" . number_format((int)$balance['lifetime_points']) . "*.";

        $convCtx['last_topic'] = 'loyalty_points';

        return [
            'reply'         => $reply,
            'state'         => 'idle',
            'action_result' => $balance,
            'conv_context'  => $convCtx,
        ];
    }

    private function handleRedeem(array $context): array
    {
        $lang      = (string)($context['language'] ?? 'id');
        $branchId  = (int)($context['branch_id'] ?? 0);
        $customer  = (array)($context['customer'] ?? []);
        $cart      = (array)($context['cart'] ?? []);
        $currency  = (string)($context['currency'] ?? 'IDR');
        $ppnRate   = (float)($context['ppn_rate'] ?? 0);
        $convCtx   = (array)($context['conv_context'] ?? []);
        $repo      = new LoyaltyPointRepository();

        $cartModel = new \App\Models\CartModel();
        $items = $cartModel->getItems((int)($cart['id'] ?? 0));
        if (empty($items) || empty($cart['id'])) {
            $reply = $lang === 'en'
                ? 'Your cart is empty. Add items before redeeming points.'
                : 'Keranjangmu kosong. Tambahkan item dulu sebelum memakai poin.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $balance = $repo->getBalance($branchId, (int)($customer['id'] ?? 0));
        $availablePoints = (int)($balance['balance_points'] ?? 0);
        if ($availablePoints <= 0) {
            $reply = $lang === 'en'
                ? 'You do not have any points to redeem yet.'
                : 'Kamu belum punya poin yang bisa dipakai.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => $balance, 'conv_context' => $convCtx];
        }

        $settings = LoyaltyPointPlugin::getRedeemSettings($branchId);
        $requestedPoints = $this->extractRequestedPoints((string)($context['message'] ?? ''), $availablePoints, $settings['points_unit']);
        $requestedPoints = min($requestedPoints, $availablePoints);

        if ($requestedPoints < $settings['min_points']) {
            $reply = $lang === 'en'
                ? "Minimum redeem is {$settings['min_points']} points."
                : "Minimal redeem adalah {$settings['min_points']} poin.";
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => $balance, 'conv_context' => $convCtx];
        }

        $subtotal         = array_sum(array_map(static fn(array $i): float => (float)$i['quantity'] * (float)$i['unit_price'], $items));
        $currentLoyalty   = (float)($cart['loyalty_discount_amount'] ?? 0);
        $promoDiscount    = max(0.0, (float)($cart['discount_amount'] ?? 0) - $currentLoyalty);
        $maxDiscountBase  = max(0.0, $subtotal - $promoDiscount);
        $discount         = LoyaltyPointPlugin::calculateRedeemDiscount($requestedPoints, $settings['points_unit'], $settings['value_amount']);
        $discount         = min($discount, $maxDiscountBase);

        if ($discount <= 0) {
            $reply = $lang === 'en'
                ? 'Your cart total is not eligible for point redemption.'
                : 'Total keranjang saat ini belum bisa dipotong dengan poin.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $actualPoints = LoyaltyPointPlugin::calculateRedeemPointsForDiscount($discount, $settings['points_unit'], $settings['value_amount']);
        $repo->applyRedemptionToCart((int)$cart['id'], $actualPoints, $discount);

        $updatedCart = $cartModel->getBySession((string)($cart['session_key'] ?? '')) ?: $cart;
        $convCtx['last_topic'] = 'cart';

        $reply = $lang === 'en'
            ? "Redeemed *{$actualPoints}* points for a discount of *" . Currency::format($discount, $currency) . "*.\n\n"
            : "Berhasil memakai *{$actualPoints}* poin untuk diskon *" . Currency::format($discount, $currency) . "*.\n\n";
        $reply .= $this->buildCartSummary($updatedCart, $items, $lang, $currency, $ppnRate);

        return [
            'reply'         => $reply,
            'state'         => 'idle',
            'action_result' => ['points' => $actualPoints, 'discount' => $discount],
            'conv_context'  => $convCtx,
        ];
    }

    private function extractRequestedPoints(string $message, int $availablePoints, int $pointsUnit): int
    {
        $lower = mb_strtolower($message, 'UTF-8');
        if (preg_match('/\b(semua|all)\b/u', $lower)) {
            return $availablePoints;
        }
        if (preg_match('/\b(\d+)\b/u', $lower, $m)) {
            return (int)$m[1];
        }

        return $pointsUnit;
    }

    private function buildCartSummary(array $cart, array $items, string $lang, string $currency, float $ppnRate): string
    {
        $lines = [$lang === 'en' ? "Your cart:\n" : "Keranjang kamu:\n"];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $lineTotal = (float)$item['quantity'] * (float)$item['unit_price'];
            $subtotal += $lineTotal;
            $lines[] = "- {$item['name']} x{$item['quantity']} = " . Currency::format($lineTotal, $currency);
        }

        $discount = (float)($cart['discount_amount'] ?? 0);
        $afterDiscount = max(0.0, $subtotal - $discount);
        $ppnAmount = $ppnRate > 0 ? round($afterDiscount * $ppnRate / 100, 2) : 0.0;
        $total = $afterDiscount + $ppnAmount;

        $lines[] = '';
        $lines[] = 'Subtotal: ' . Currency::format($subtotal, $currency);
        if ($discount > 0) {
            $lines[] = ($lang === 'en' ? 'Discount: -' : 'Diskon: -') . Currency::format($discount, $currency);
        }
        if ($ppnAmount > 0) {
            $lines[] = ($lang === 'en' ? "VAT ({$ppnRate}%): " : "PPN ({$ppnRate}%): ") . Currency::format($ppnAmount, $currency);
        }
        $lines[] = 'Total: ' . Currency::format($total, $currency);

        return implode("\n", $lines);
    }

    private function handleClearRedeem(array $context): array
    {
        $lang      = (string)($context['language'] ?? 'id');
        $cart      = (array)($context['cart'] ?? []);
        $currency  = (string)($context['currency'] ?? 'IDR');
        $ppnRate   = (float)($context['ppn_rate'] ?? 0);
        $convCtx   = (array)($context['conv_context'] ?? []);
        $cartId    = (int)($cart['id'] ?? 0);

        if ($cartId <= 0) {
            return [
                'reply' => $lang === 'en' ? 'Cart not found.' : 'Keranjang tidak ditemukan.',
                'state' => 'idle',
                'action_result' => null,
                'conv_context' => $convCtx,
            ];
        }

        (new LoyaltyPointRepository())->clearRedemptionFromCart($cartId);
        $cartModel = new \App\Models\CartModel();
        $updatedCart = $cartModel->getBySession((string)($cart['session_key'] ?? '')) ?: $cart;
        $items = $cartModel->getItems($cartId);

        $reply = $lang === 'en'
            ? "Point redemption removed from your cart.\n\n"
            : "Pemakaian poin di keranjang berhasil dibatalkan.\n\n";
        $reply .= $this->buildCartSummary($updatedCart, $items, $lang, $currency, $ppnRate);

        return [
            'reply' => $reply,
            'state' => 'idle',
            'action_result' => ['cleared' => true],
            'conv_context' => $convCtx,
        ];
    }
}
