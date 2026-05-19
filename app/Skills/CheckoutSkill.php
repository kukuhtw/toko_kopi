<?php

declare(strict_types=1);

namespace App\Skills;

use App\Models\CartModel;
use App\Models\OrderModel;
use App\Models\CustomerModel;
use App\Models\PromoModel;
use App\Helpers\Currency;

class CheckoutSkill implements SkillInterface
{
    private CartModel     $cartModel;
    private OrderModel    $orderModel;
    private CustomerModel $customerModel;
    private PromoModel    $promoModel;

    public function __construct()
    {
        $this->cartModel     = new CartModel();
        $this->orderModel    = new OrderModel();
        $this->customerModel = new CustomerModel();
        $this->promoModel    = new PromoModel();
    }

    public function canHandle(string $intent): bool
    {
        return in_array($intent, [
            'checkout', 'isi_nama', 'isi_email', 'isi_wa',
            'isi_alamat', 'isi_kode_pos', 'konfirmasi_order',
        ]);
    }

    public function handle(array $ctx): array
    {
        $intent = $ctx['intent'];
        $state  = $ctx['conversation']['state'] ?? 'idle';

        // In confirmation state, "ganti X" intents clear that field and re-ask for it
        if ($state === 'awaiting_confirmation'
            && in_array($intent, ['isi_nama', 'isi_email', 'isi_wa', 'isi_alamat', 'isi_kode_pos'])) {
            return $this->clearAndReask($ctx, $intent);
        }

        return match ($intent) {
            'checkout'         => $this->startCheckout($ctx),
            'isi_nama'         => $this->collectName($ctx),
            'isi_email'        => $this->collectEmail($ctx),
            'isi_wa'           => $this->collectWa($ctx),
            'isi_alamat'       => $this->collectAddress($ctx),
            'isi_kode_pos'     => $this->collectPostal($ctx),
            'konfirmasi_order' => $this->confirmOrder($ctx),
            default            => $this->resumeCheckout($ctx),
        };
    }

    private function clearAndReask(array $ctx, string $intent): array
    {
        $convCtx  = $ctx['conv_context'] ?? [];
        $lang     = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';

        $fieldMap = [
            'isi_nama'     => 'checkout_name',
            'isi_email'    => 'checkout_email',
            'isi_wa'       => 'checkout_wa',
            'isi_alamat'   => 'checkout_address',
            'isi_kode_pos' => 'checkout_postal',
        ];

        $field    = $fieldMap[$intent];
        $ppnRate  = (float)($ctx['ppn_rate'] ?? 0);
        $newValue = $this->extractInlineValue($intent, $ctx['message']);

        if ($newValue !== null) {
            // Value was provided inline — update and re-show summary
            $convCtx[$field] = $newValue;
            unset($convCtx['awaiting_confirmation']);
            return $this->showOrderSummary($convCtx, $lang, $ctx['cart'], $currency, $ppnRate);
        }

        // No value — clear field and re-ask for it
        unset($convCtx[$field], $convCtx['awaiting_confirmation']);
        return $this->askNextField($convCtx, $lang, $ctx['cart'], $currency, $ppnRate);
    }

    private function extractInlineValue(string $intent, string $message): ?string
    {
        // Strip change-command words to isolate the new value
        $stripped = preg_replace('/\b(ganti|ubah|change|update|jadi|ke|menjadi|kode\s*pos|postal|nama|name|email|alamat|address|wa|whatsapp|nomor|hp)\b/iu', '', $message);
        $stripped = trim(preg_replace('/\s+/', ' ', $stripped));

        switch ($intent) {
            case 'isi_kode_pos':
                if (preg_match('/\b(\d{4,10})\b/', $stripped, $m)) { return $m[1]; }
                break;
            case 'isi_email':
                if (preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+/', $message, $m)) { return strtolower($m[0]); }
                break;
            case 'isi_wa':
                $wa = preg_replace('/[^0-9+]/', '', $stripped);
                if (strlen($wa) >= 8) { return $wa; }
                break;
            case 'isi_nama':
                if (strlen($stripped) >= 2) { return ucwords(mb_strtolower($stripped, 'UTF-8')); }
                break;
            case 'isi_alamat':
                if (strlen($stripped) >= 10) { return $stripped; }
                break;
            default:
                return null;
        }

        return null;
    }

    private function startCheckout(array $ctx): array
    {
        $cart  = $ctx['cart'];
        $lang  = $ctx['language'] ?? 'id';
        $items = $this->cartModel->getItems($cart['id']);

        if (empty($items)) {
            $reply = $lang === 'id'
                ? 'Keranjangmu kosong. Silakan tambahkan menu terlebih dahulu.'
                : 'Your cart is empty. Please add items first.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null];
        }

        // Auto-apply best promo if cart has no promo yet (period + loyalty + category checks)
        if (empty($cart['promo_code'])) {
            $subtotal  = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));
            $autoPromo = $this->promoModel->getBestAutoApply(
                (int)$ctx['branch_id'],
                $subtotal,
                (int)($ctx['customer']['id'] ?? 0),
                $items,
                $ctx['now_local'] ?? ''
            );
            if ($autoPromo) {
                $discount = $this->promoModel->calculateDiscount($autoPromo, $subtotal);
                $this->cartModel->applyPromo($cart['id'], $autoPromo['promo_code'] ?? '', $discount);
                $cart = $this->cartModel->getBySession($cart['session_key']) ?? $cart;
                $discFmt  = \App\Helpers\Currency::format($discount, $ctx['currency'] ?? 'IDR');
                $autoNote = $lang === 'id'
                    ? "🎉 Promo *{$autoPromo['title']}* otomatis diterapkan! Hemat {$discFmt}.\n\n"
                    : "🎉 Promo *{$autoPromo['title']}* auto-applied! You save {$discFmt}.\n\n";
                $ctx['_auto_promo_note'] = $autoNote;
                $ctx['cart'] = $cart;
            }
        }

        // Check if we already have customer data
        $customer = $ctx['customer'];
        $profile  = $this->customerModel->getProfile($customer['id']);
        $convCtx  = $ctx['conv_context'] ?? [];

        // Pre-fill from existing profile
        if (!empty($customer['name']) && empty($convCtx['checkout_name'])) {
            $convCtx['checkout_name'] = $customer['name'];
        }
        if (!empty($customer['email']) && empty($convCtx['checkout_email'])) {
            $convCtx['checkout_email'] = $customer['email'];
        }
        if (!empty($customer['whatsapp']) && empty($convCtx['checkout_wa'])) {
            $convCtx['checkout_wa'] = $customer['whatsapp'];
        }
        if (!empty($profile['address']) && empty($convCtx['checkout_address'])) {
            $convCtx['checkout_address'] = $profile['address'];
        }
        if (!empty($profile['postal_code']) && empty($convCtx['checkout_postal'])) {
            $convCtx['checkout_postal'] = $profile['postal_code'];
        }

        // Find next missing field — prepend auto-promo notification if just applied
        $result = $this->askNextField($convCtx, $lang, $cart, $ctx['currency'] ?? 'IDR', (float)($ctx['ppn_rate'] ?? 0));
        if (!empty($ctx['_auto_promo_note'])) {
            $result['reply'] = $ctx['_auto_promo_note'] . $result['reply'];
        }
        $result['conv_context'] = $this->mergeCheckoutMeta($result['conv_context'] ?? $convCtx, $cart);
        return $result;
    }

    private function collectName(array $ctx): array
    {
        $lang    = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $name    = trim(preg_replace('/\b(nama saya|my name is|nama:|panggil saya)\b/i', '', $ctx['message']));

        if (strlen($name) < 2) {
            $reply = $lang === 'id' ? 'Nama tidak valid. Silakan masukkan nama lengkap kamu.' : 'Invalid name. Please enter your full name.';
            return ['reply' => $reply, 'state' => 'awaiting_name', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_name'] = ucwords($name);
        $result = $this->askNextField($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', (float)($ctx['ppn_rate'] ?? 0));
        $result['conv_context'] = $this->mergeCheckoutMeta($result['conv_context'] ?? $convCtx, $ctx['cart']);
        return $result;
    }

    private function collectEmail(array $ctx): array
    {
        $lang    = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $email   = filter_var(trim($ctx['message']), FILTER_VALIDATE_EMAIL);

        if (!$email) {
            if (mb_strtolower(trim($ctx['message'])) === 'skip' || str_contains(mb_strtolower($ctx['message']), 'skip')) {
                $convCtx['checkout_email'] = '';
                return $this->askNextField($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', (float)($ctx['ppn_rate'] ?? 0));
            }
            $reply = $lang === 'id' ? 'Format email tidak valid. Coba lagi atau ketik *skip* untuk melewati.' : 'Invalid email format. Try again or type *skip* to skip.';
            return ['reply' => $reply, 'state' => 'awaiting_email', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_email'] = $email;
        $result = $this->askNextField($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', (float)($ctx['ppn_rate'] ?? 0));
        $result['conv_context'] = $this->mergeCheckoutMeta($result['conv_context'] ?? $convCtx, $ctx['cart']);
        return $result;
    }

    private function collectWa(array $ctx): array
    {
        $lang    = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $wa      = preg_replace('/[^0-9+]/', '', $ctx['message']);

        if (strlen($wa) < 8) {
            $reply = $lang === 'id' ? 'Nomor WhatsApp tidak valid. Contoh: 081234567890' : 'Invalid WhatsApp number. Example: 081234567890';
            return ['reply' => $reply, 'state' => 'awaiting_wa', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_wa'] = $wa;
        $result = $this->askNextField($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', (float)($ctx['ppn_rate'] ?? 0));
        $result['conv_context'] = $this->mergeCheckoutMeta($result['conv_context'] ?? $convCtx, $ctx['cart']);
        return $result;
    }

    private function collectAddress(array $ctx): array
    {
        $lang    = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $address = trim($ctx['message']);

        if (strlen($address) < 10) {
            $reply = $lang === 'id' ? 'Alamat terlalu pendek. Masukkan alamat lengkap termasuk jalan dan nomor rumah.' : 'Address too short. Please enter a complete address.';
            return ['reply' => $reply, 'state' => 'awaiting_address', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_address'] = $address;
        $result = $this->askNextField($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', (float)($ctx['ppn_rate'] ?? 0));
        $result['conv_context'] = $this->mergeCheckoutMeta($result['conv_context'] ?? $convCtx, $ctx['cart']);
        return $result;
    }

    private function collectPostal(array $ctx): array
    {
        $lang    = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $postal  = preg_replace('/[^0-9]/', '', $ctx['message']);

        if (strlen($postal) < 4 || strlen($postal) > 10) {
            $reply = $lang === 'id' ? 'Kode pos tidak valid. Contoh: 12345' : 'Invalid postal code. Example: 12345';
            return ['reply' => $reply, 'state' => 'awaiting_postal', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_postal'] = $postal;
        $result = $this->askNextField($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', (float)($ctx['ppn_rate'] ?? 0));
        $result['conv_context'] = $this->mergeCheckoutMeta($result['conv_context'] ?? $convCtx, $ctx['cart']);
        return $result;
    }

    private function askNextField(array $convCtx, string $lang, array $cart, string $currency, float $ppnRate = 0.0): array
    {
        // Determine next missing required field
        if (empty($convCtx['checkout_name'])) {
            $q = $lang === 'id' ? "Boleh tahu nama kamu? 😊" : "May I know your name? 😊";
            return ['reply' => $q, 'state' => 'awaiting_name', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (!isset($convCtx['checkout_email'])) {
            $q = $lang === 'id'
                ? "Alamat email kamu? (ketik *skip* jika tidak punya)"
                : "Your email address? (type *skip* to skip)";
            return ['reply' => $q, 'state' => 'awaiting_email', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (empty($convCtx['checkout_wa'])) {
            $q = $lang === 'id' ? "Nomor WhatsApp kamu? (untuk konfirmasi order)" : "Your WhatsApp number? (for order confirmation)";
            return ['reply' => $q, 'state' => 'awaiting_wa', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (empty($convCtx['checkout_address'])) {
            $q = $lang === 'id' ? "Alamat pengiriman lengkap kamu?" : "Your complete delivery address?";
            return ['reply' => $q, 'state' => 'awaiting_address', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (empty($convCtx['checkout_postal'])) {
            $q = $lang === 'id' ? "Kode pos daerah kamu?" : "Your postal code?";
            return ['reply' => $q, 'state' => 'awaiting_postal', 'action_result' => null, 'conv_context' => $convCtx];
        }

        // All fields collected — show summary
        return $this->showOrderSummary($convCtx, $lang, $cart, $currency, $ppnRate);
    }

    private function showOrderSummary(array $convCtx, string $lang, array $cart, string $currency, float $ppnRate = 0.0): array
    {
        $items        = $this->cartModel->getItems($cart['id']);
        $subtotal     = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));
        $discount     = (float)$cart['discount_amount'];
        $afterDiscount = max(0.0, $subtotal - $discount);
        $ppnAmount    = $ppnRate > 0 ? round($afterDiscount * $ppnRate / 100, 2) : 0.0;
        $total        = $afterDiscount + $ppnAmount;

        $lines = [$lang === 'id' ? "📋 *Ringkasan Order*\n" : "📋 *Order Summary*\n"];

        foreach ($items as $item) {
            $lines[] = "• {$item['name']} x{$item['quantity']} = " . Currency::format($item['quantity'] * $item['unit_price'], $currency);
            if (!empty($item['notes'])) {
                $lines[] = '  ' . $item['notes'];
            }
        }
        $lines[] = '';
        $lines[] = 'Subtotal: ' . Currency::format($subtotal, $currency);
        if ($discount > 0) {
            $lines[] = ($lang === 'id' ? 'Diskon: -' : 'Discount: -') . Currency::format($discount, $currency);
        }
        if ($ppnAmount > 0) {
            $ppnLabel = $lang === 'id' ? "PPN ({$ppnRate}%): " : "VAT ({$ppnRate}%): ";
            $lines[]  = $ppnLabel . Currency::format($ppnAmount, $currency);
        }
        $lines[] = '*Total: ' . Currency::format($total, $currency) . '*';
        $lines[] = '';
        $lines[] = ($lang === 'id' ? '👤 Nama: ' : '👤 Name: ') . $convCtx['checkout_name'];
        $lines[] = '📧 Email: ' . ($convCtx['checkout_email'] ?: '-');
        $lines[] = '📱 WhatsApp: ' . ($convCtx['checkout_wa'] ?? '-');
        $lines[] = ($lang === 'id' ? '📍 Alamat: ' : '📍 Address: ') . $convCtx['checkout_address'];
        $lines[] = ($lang === 'id' ? '📮 Kode Pos: ' : '📮 Postal: ') . $convCtx['checkout_postal'];
        $lines[] = '';
        $confirm = $lang === 'id'
            ? "Ketik *ya* untuk konfirmasi order atau *batal* untuk membatalkan."
            : "Type *yes* to confirm order or *cancel* to cancel.";
        $lines[] = $confirm;

        $convCtx['awaiting_confirmation'] = true;

        return ['reply' => implode("\n", $lines), 'state' => 'awaiting_confirmation', 'action_result' => null, 'conv_context' => $convCtx];
    }

    private function confirmOrder(array $ctx): array
    {
        $lang    = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $cart    = $ctx['cart'];
        $msg     = mb_strtolower(trim($ctx['message']), 'UTF-8');

        // Detect cancellation
        if (str_contains($msg, 'batal') || str_contains($msg, 'cancel') || $msg === 'tidak') {
            $convCtx = [];
            $reply = $lang === 'id' ? '❌ Order dibatalkan. Ketik *menu* untuk memulai lagi.' : '❌ Order cancelled. Type *menu* to start over.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (!($convCtx['awaiting_confirmation'] ?? false)) {
            return $this->startCheckout($ctx);
        }

        $items = $this->cartModel->getItems($cart['id']);
        if (empty($items)) {
            $reply = $lang === 'id' ? 'Keranjang kosong. Tidak bisa checkout.' : 'Cart is empty. Cannot checkout.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null];
        }

        $customerData = [
            'name'        => $convCtx['checkout_name'] ?? '',
            'email'       => $convCtx['checkout_email'] ?? '',
            'whatsapp'    => $convCtx['checkout_wa'] ?? '',
            'address'     => $convCtx['checkout_address'] ?? '',
            'postal_code' => $convCtx['checkout_postal'] ?? '',
        ];

        $cartWithChannel = array_merge($cart, ['channel' => $ctx['channel']]);
        $ppnRate = (float)($ctx['ppn_rate'] ?? 0);
        try {
            $orderId = $this->orderModel->createFromCart($cartWithChannel, $items, $customerData, $ctx['customer']['id'], $ppnRate);
        } catch (\RuntimeException $e) {
            return [
                'reply' => $e->getMessage(),
                'state' => 'idle',
                'action_result' => null,
                'conv_context' => $convCtx,
            ];
        }

        // Update customer profile
        $this->customerModel->updateInfo($ctx['customer']['id'], [
            'name'      => $customerData['name'],
            'email'     => $customerData['email'],
            'whatsapp'  => $customerData['whatsapp'],
        ]);
        $this->customerModel->updateProfile($ctx['customer']['id'], [
            'address'     => $customerData['address'],
            'postal_code' => $customerData['postal_code'],
        ]);

        // Update favorite items
        $favIds = array_column($items, 'menu_item_id');
        $this->customerModel->updateFavorites($ctx['customer']['id'], $favIds);

        // Clear cart
        $this->cartModel->clearCart($cart['id']);

        $order = $this->orderModel->find($orderId);
        $reply = $lang === 'id'
            ? "✅ *Order berhasil dibuat!*\n\n📦 Nomor Order: *{$order['order_number']}*\n💳 Status Pembayaran: Unpaid\n\nTerima kasih, {$customerData['name']}! Admin kami akan segera memproses pesananmu. Ketik *status order* untuk cek status pesanan."
            : "✅ *Order placed successfully!*\n\n📦 Order Number: *{$order['order_number']}*\n💳 Payment Status: Unpaid\n\nThank you, {$customerData['name']}! Our admin will process your order soon.";

        return [
            'reply' => $reply,
            'state' => 'idle',
            'action_result' => $order,
            'conv_context' => [
                'last_topic' => 'order_history',
                'last_orders' => [(string)($order['order_number'] ?? '')],
                'last_order_number' => (string)($order['order_number'] ?? ''),
            ],
        ];
    }

    private function resumeCheckout(array $ctx): array
    {
        $convCtx = $ctx['conv_context'] ?? [];
        $lang    = $ctx['language'] ?? 'id';

        $result = $this->askNextField($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', (float)($ctx['ppn_rate'] ?? 0));
        $result['conv_context'] = $this->mergeCheckoutMeta($result['conv_context'] ?? $convCtx, $ctx['cart']);
        return $result;
    }

    private function mergeCheckoutMeta(array $convCtx, array $cart): array
    {
        $items = $this->cartModel->getItems($cart['id']);
        $convCtx['last_topic'] = 'checkout';
        $convCtx['last_cart_items'] = array_map(static fn(array $item): array => [
            'id' => (int)($item['menu_item_id'] ?? 0),
            'name' => (string)($item['name'] ?? ''),
            'qty' => (int)($item['quantity'] ?? 0),
        ], $items);
        if (!empty($cart['promo_code'])) {
            $convCtx['last_promo_code'] = (string)$cart['promo_code'];
        }
        return $convCtx;
    }
}
