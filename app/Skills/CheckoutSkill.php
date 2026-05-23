<?php

declare(strict_types=1);

namespace App\Skills;

use App\Helpers\Currency;
use App\Models\CartModel;
use App\Models\CustomerModel;
use App\Models\OrderModel;
use App\Models\PromoModel;
use App\Plugin\HookManager;

class CheckoutSkill implements SkillInterface
{
    private CartModel $cartModel;
    private OrderModel $orderModel;
    private CustomerModel $customerModel;
    private PromoModel $promoModel;

    public function __construct()
    {
        $this->cartModel = new CartModel();
        $this->orderModel = new OrderModel();
        $this->customerModel = new CustomerModel();
        $this->promoModel = new PromoModel();
    }

    public function canHandle(string $intent): bool
    {
        return in_array($intent, [
            'checkout', 'isi_nama', 'isi_email', 'isi_wa',
            'isi_fulfillment', 'isi_nomor_meja', 'isi_alamat', 'isi_kode_pos',
            'konfirmasi_order',
        ], true);
    }

    public function handle(array $ctx): array
    {
        $intent = $ctx['intent'];
        $state = $ctx['conversation']['state'] ?? 'idle';

        if (
            $state === 'awaiting_confirmation'
            && in_array($intent, [
                'isi_nama', 'isi_email', 'isi_wa', 'isi_fulfillment',
                'isi_nomor_meja', 'isi_alamat', 'isi_kode_pos',
            ], true)
        ) {
            return $this->clearAndReask($ctx, $intent);
        }

        return match ($intent) {
            'checkout' => $this->startCheckout($ctx),
            'isi_nama' => $this->collectName($ctx),
            'isi_email' => $this->collectEmail($ctx),
            'isi_wa' => $this->collectWa($ctx),
            'isi_fulfillment' => $this->collectFulfillment($ctx),
            'isi_nomor_meja' => $this->collectTableNumber($ctx),
            'isi_alamat' => $this->collectAddress($ctx),
            'isi_kode_pos' => $this->collectPostal($ctx),
            'konfirmasi_order' => $this->confirmOrder($ctx),
            default => $this->resumeCheckout($ctx),
        };
    }

    private function clearAndReask(array $ctx, string $intent): array
    {
        $convCtx = $ctx['conv_context'] ?? [];
        $lang = $ctx['language'] ?? 'id';
        $ppnRate = (float)($ctx['ppn_rate'] ?? 0);

        $fieldMap = [
            'isi_nama' => 'checkout_name',
            'isi_email' => 'checkout_email',
            'isi_wa' => 'checkout_wa',
            'isi_fulfillment' => 'checkout_fulfillment',
            'isi_nomor_meja' => 'checkout_table_number',
            'isi_alamat' => 'checkout_address',
            'isi_kode_pos' => 'checkout_postal',
        ];

        $field = $fieldMap[$intent] ?? '';
        if ($field === '') {
            return $this->resumeCheckout($ctx);
        }

        $newValue = $this->extractInlineValue($intent, (string)$ctx['message']);
        if ($intent === 'isi_email' && $this->isSkipMessage((string)$ctx['message'])) {
            $convCtx[$field] = '';
            unset($convCtx['awaiting_confirmation']);
            return $this->showOrderSummary($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', $ppnRate);
        }

        if ($newValue !== null) {
            $convCtx[$field] = $newValue;
            if ($intent === 'isi_fulfillment') {
                $convCtx = $this->resetFulfillmentDependentFields($convCtx, $newValue);
            }
            unset($convCtx['awaiting_confirmation']);
            return $this->askNextField($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', $ppnRate);
        }

        unset($convCtx[$field], $convCtx['awaiting_confirmation']);
        if ($intent === 'isi_fulfillment') {
            unset($convCtx['checkout_table_number'], $convCtx['checkout_address'], $convCtx['checkout_postal']);
        }

        return $this->askNextField($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', $ppnRate);
    }

    private function extractInlineValue(string $intent, string $message): ?string
    {
        $stripped = preg_replace('/\b(ganti|ubah|change|update|jadi|ke|menjadi|mode|metode|opsi|fulfillment|pengambilan|pengiriman|nomor\s*meja|table\s*number|kode\s*pos|postal|nama|name|email|alamat|address|wa|whatsapp|nomor|hp)\b/iu', '', $message);
        $stripped = trim((string)preg_replace('/\s+/', ' ', (string)$stripped));

        return match ($intent) {
            'isi_kode_pos' => preg_match('/\b(\d{4,10})\b/', $stripped, $m) ? $m[1] : null,
            'isi_email' => preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+/', $message, $m) ? strtolower($m[0]) : null,
            'isi_wa' => (($wa = preg_replace('/[^0-9+]/', '', $stripped)) && strlen($wa) >= 8) ? $wa : null,
            'isi_nama' => strlen($stripped) >= 2 ? ucwords(mb_strtolower($stripped, 'UTF-8')) : null,
            'isi_alamat' => strlen($stripped) >= 8 ? $stripped : null,
            'isi_nomor_meja' => $this->extractTableNumber($message),
            'isi_fulfillment' => $this->detectFulfillmentType($message),
            default => null,
        };
    }

    private function startCheckout(array $ctx): array
    {
        $cart = $ctx['cart'];
        $lang = $ctx['language'] ?? 'id';
        $items = $this->cartModel->getItems($cart['id']);

        if (empty($items)) {
            $reply = $lang === 'id'
                ? 'Keranjangmu kosong. Silakan tambahkan menu terlebih dahulu.'
                : 'Your cart is empty. Please add items first.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null];
        }

        if (empty($cart['promo_code'])) {
            $subtotal = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));
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
                $discFmt = Currency::format($discount, $ctx['currency'] ?? 'IDR');
                $ctx['_auto_promo_note'] = $lang === 'id'
                    ? "🎉 Promo *{$autoPromo['title']}* otomatis diterapkan! Hemat {$discFmt}.\n\n"
                    : "🎉 Promo *{$autoPromo['title']}* auto-applied! You save {$discFmt}.\n\n";
                $ctx['cart'] = $cart;
            }
        }

        $customer = $ctx['customer'];
        $profile = $this->customerModel->getProfile($customer['id']);
        $convCtx = $ctx['conv_context'] ?? [];

        if (!empty($customer['name']) && empty($convCtx['checkout_name'])) {
            $convCtx['checkout_name'] = $customer['name'];
        }
        if (!empty($customer['email']) && !isset($convCtx['checkout_email'])) {
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

        $result = $this->askNextField($convCtx, $lang, $cart, $ctx['currency'] ?? 'IDR', (float)($ctx['ppn_rate'] ?? 0));
        if (!empty($ctx['_auto_promo_note'])) {
            $result['reply'] = $ctx['_auto_promo_note'] . $result['reply'];
        }
        $result['conv_context'] = $this->mergeCheckoutMeta($result['conv_context'] ?? $convCtx, $cart);
        return $result;
    }

    private function collectName(array $ctx): array
    {
        $lang = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $name = trim((string)preg_replace('/\b(nama saya|my name is|nama:|panggil saya)\b/i', '', (string)$ctx['message']));

        if (strlen($name) < 2 || $this->looksLikeCommand($name)) {
            $reply = $lang === 'id'
                ? 'Boleh tahu nama kamu? 😊'
                : 'May I know your name? 😊';
            return ['reply' => $reply, 'state' => 'awaiting_name', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_name'] = ucwords($name);
        return $this->continueCheckout($ctx, $convCtx);
    }

    private function looksLikeCommand(string $text): bool
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $keywords = [
            'promo', 'menu', 'checkout', 'keranjang', 'pesanan', 'order',
            'batal', 'cancel', 'hari ini', 'delivery', 'ambil', 'bayar',
            'skip', 'lewati', 'rekomendasi', 'lihat', 'tambah', 'hapus',
        ];
        foreach ($keywords as $kw) {
            if (mb_strpos($lower, $kw, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }

    private function collectEmail(array $ctx): array
    {
        $lang = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $email = filter_var(trim((string)$ctx['message']), FILTER_VALIDATE_EMAIL);

        if (!$email) {
            if ($this->isSkipMessage((string)$ctx['message'])) {
                $convCtx['checkout_email'] = '';
                return $this->continueCheckout($ctx, $convCtx);
            }
            $reply = $lang === 'id'
                ? 'Format email tidak valid. Coba lagi atau ketik *skip* untuk melewati.'
                : 'Invalid email format. Try again or type *skip* to skip.';
            return ['reply' => $reply, 'state' => 'awaiting_email', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_email'] = $email;
        return $this->continueCheckout($ctx, $convCtx);
    }

    private function collectWa(array $ctx): array
    {
        $lang = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $wa = preg_replace('/[^0-9+]/', '', (string)$ctx['message']);

        if (strlen($wa) < 8) {
            $reply = $lang === 'id'
                ? 'Nomor WhatsApp tidak valid. Contoh: 081234567890'
                : 'Invalid WhatsApp number. Example: 081234567890';
            return ['reply' => $reply, 'state' => 'awaiting_wa', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_wa'] = $wa;
        return $this->continueCheckout($ctx, $convCtx);
    }

    private function collectFulfillment(array $ctx): array
    {
        $lang = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $fulfillment = $this->detectFulfillmentType((string)$ctx['message']);

        if ($fulfillment === null) {
            $reply = $lang === 'id'
                ? "Pilih salah satu ya: *ambil di toko*, *delivery ke meja*, atau *delivery ke alamat*."
                : "Please choose one: *pickup in store*, *deliver to table*, or *deliver to address*.";
            return ['reply' => $reply, 'state' => 'awaiting_fulfillment', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_fulfillment'] = $fulfillment;
        $convCtx = $this->resetFulfillmentDependentFields($convCtx, $fulfillment);
        return $this->continueCheckout($ctx, $convCtx);
    }

    private function collectTableNumber(array $ctx): array
    {
        $lang = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $tableNumber = $this->extractTableNumber((string)$ctx['message']);

        if ($tableNumber === null) {
            $reply = $lang === 'id'
                ? 'Nomor meja belum terbaca. Contoh: *meja 12* atau *12*.'
                : 'I could not read the table number. Example: *table 12* or *12*.';
            return ['reply' => $reply, 'state' => 'awaiting_table', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_table_number'] = $tableNumber;
        return $this->continueCheckout($ctx, $convCtx);
    }

    private function collectAddress(array $ctx): array
    {
        $lang = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $address = trim((string)$ctx['message']);

        if (strlen($address) < 8) {
            $reply = $lang === 'id'
                ? 'Alamat terlalu pendek. Masukkan alamat lengkap termasuk jalan dan nomor lokasi.'
                : 'Address is too short. Please enter the full address.';
            return ['reply' => $reply, 'state' => 'awaiting_address', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_address'] = $address;
        return $this->continueCheckout($ctx, $convCtx);
    }

    private function collectPostal(array $ctx): array
    {
        $lang = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $postal = preg_replace('/[^0-9]/', '', (string)$ctx['message']);

        if (strlen($postal) < 4 || strlen($postal) > 10) {
            $reply = $lang === 'id'
                ? 'Kode pos tidak valid. Contoh: 12345'
                : 'Invalid postal code. Example: 12345';
            return ['reply' => $reply, 'state' => 'awaiting_postal', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $convCtx['checkout_postal'] = $postal;
        return $this->continueCheckout($ctx, $convCtx);
    }

    private function askNextField(array $convCtx, string $lang, array $cart, string $currency, float $ppnRate = 0.0): array
    {
        if (empty($convCtx['checkout_name'])) {
            $q = $lang === 'id' ? 'Boleh tahu nama kamu? 😊' : 'May I know your name? 😊';
            return ['reply' => $q, 'state' => 'awaiting_name', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (!isset($convCtx['checkout_email'])) {
            $q = $lang === 'id'
                ? 'Alamat email kamu? (ketik *skip* jika tidak punya)'
                : 'Your email address? (type *skip* to skip)';
            return ['reply' => $q, 'state' => 'awaiting_email', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (empty($convCtx['checkout_wa'])) {
            $q = $lang === 'id'
                ? 'Nomor WhatsApp kamu? (untuk konfirmasi order)'
                : 'Your WhatsApp number? (for order confirmation)';
            return ['reply' => $q, 'state' => 'awaiting_wa', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (empty($convCtx['checkout_fulfillment'])) {
            $q = $lang === 'id'
                ? "Metode pesanan yang kamu mau?\n1. *Ambil di toko*\n2. *Delivery ke nomor meja*\n3. *Delivery ke alamat*"
                : "Which fulfillment method do you want?\n1. *Pickup in store*\n2. *Deliver to a table number*\n3. *Deliver to address*";
            return ['reply' => $q, 'state' => 'awaiting_fulfillment', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (($convCtx['checkout_fulfillment'] ?? '') === 'table' && empty($convCtx['checkout_table_number'])) {
            $q = $lang === 'id'
                ? 'Nomor meja kamu berapa?'
                : 'What is your table number?';
            return ['reply' => $q, 'state' => 'awaiting_table', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (($convCtx['checkout_fulfillment'] ?? '') === 'delivery' && empty($convCtx['checkout_address'])) {
            $q = $lang === 'id'
                ? 'Alamat pengiriman lengkap kamu?'
                : 'Your complete delivery address?';
            return ['reply' => $q, 'state' => 'awaiting_address', 'action_result' => null, 'conv_context' => $convCtx];
        }

        if (($convCtx['checkout_fulfillment'] ?? '') === 'delivery' && empty($convCtx['checkout_postal'])) {
            $q = $lang === 'id'
                ? 'Kode pos daerah kamu?'
                : 'Your postal code?';
            return ['reply' => $q, 'state' => 'awaiting_postal', 'action_result' => null, 'conv_context' => $convCtx];
        }

        return $this->showOrderSummary($convCtx, $lang, $cart, $currency, $ppnRate);
    }

    private function showOrderSummary(array $convCtx, string $lang, array $cart, string $currency, float $ppnRate = 0.0): array
    {
        $items = $this->cartModel->getItems($cart['id']);
        $subtotal = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));
        $discount = (float)$cart['discount_amount'];
        $afterDiscount = max(0.0, $subtotal - $discount);
        $ppnAmount = $ppnRate > 0 ? round($afterDiscount * $ppnRate / 100, 2) : 0.0;
        $total = $afterDiscount + $ppnAmount;

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
            $lines[] = ($lang === 'id' ? "PPN ({$ppnRate}%): " : "VAT ({$ppnRate}%): ") . Currency::format($ppnAmount, $currency);
        }
        $lines[] = '*Total: ' . Currency::format($total, $currency) . '*';
        $lines[] = '';
        $lines[] = ($lang === 'id' ? '👤 Nama: ' : '👤 Name: ') . ($convCtx['checkout_name'] ?? '-');
        $lines[] = '📧 Email: ' . (($convCtx['checkout_email'] ?? '') ?: '-');
        $lines[] = '📱 WhatsApp: ' . ($convCtx['checkout_wa'] ?? '-');
        $lines[] = ($lang === 'id' ? '🛍️ Metode: ' : '🛍️ Method: ') . $this->formatFulfillmentLabel((string)($convCtx['checkout_fulfillment'] ?? 'delivery'), $lang);

        $fulfillment = (string)($convCtx['checkout_fulfillment'] ?? 'delivery');
        if ($fulfillment === 'table') {
            $lines[] = ($lang === 'id' ? '🪑 Nomor Meja: ' : '🪑 Table Number: ') . ($convCtx['checkout_table_number'] ?? '-');
        } elseif ($fulfillment === 'delivery') {
            $lines[] = ($lang === 'id' ? '📍 Alamat: ' : '📍 Address: ') . ($convCtx['checkout_address'] ?? '-');
            $lines[] = ($lang === 'id' ? '📮 Kode Pos: ' : '📮 Postal: ') . ($convCtx['checkout_postal'] ?? '-');
        }

        $lines[] = '';
        $lines[] = $lang === 'id'
            ? 'Ketik *ya* untuk konfirmasi order atau *batal* untuk membatalkan.'
            : 'Type *yes* to confirm order or *cancel* to cancel.';

        $convCtx['awaiting_confirmation'] = true;
        return ['reply' => implode("\n", $lines), 'state' => 'awaiting_confirmation', 'action_result' => null, 'conv_context' => $convCtx];
    }

    private function confirmOrder(array $ctx): array
    {
        $lang = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $cart = $ctx['cart'];
        $msg = mb_strtolower(trim((string)$ctx['message']), 'UTF-8');

        if (str_contains($msg, 'batal') || str_contains($msg, 'cancel') || $msg === 'tidak') {
            return [
                'reply' => $lang === 'id'
                    ? '❌ Order dibatalkan. Ketik *menu* untuk memulai lagi.'
                    : '❌ Order cancelled. Type *menu* to start over.',
                'state' => 'idle',
                'action_result' => null,
                'conv_context' => [],
            ];
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
            'name' => $convCtx['checkout_name'] ?? '',
            'email' => $convCtx['checkout_email'] ?? '',
            'whatsapp' => $convCtx['checkout_wa'] ?? '',
            'fulfillment_type' => $convCtx['checkout_fulfillment'] ?? 'delivery',
            'table_number' => $convCtx['checkout_table_number'] ?? '',
            'address' => $convCtx['checkout_address'] ?? '',
            'postal_code' => $convCtx['checkout_postal'] ?? '',
        ];
        $customerData = HookManager::applyFilters(
            'cart.before_checkout',
            $customerData,
            $cart,
            $items,
            (int)($ctx['branch_id'] ?? 0)
        );

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

        $this->customerModel->updateInfo((int)$ctx['customer']['id'], [
            'name' => $customerData['name'],
            'email' => $customerData['email'],
            'whatsapp' => $customerData['whatsapp'],
        ]);
        if (($customerData['fulfillment_type'] ?? 'delivery') === 'delivery') {
            $this->customerModel->updateProfile((int)$ctx['customer']['id'], [
                'address' => $customerData['address'],
                'postal_code' => $customerData['postal_code'],
            ]);
        }

        $favIds = array_column($items, 'menu_item_id');
        $this->customerModel->updateFavorites((int)$ctx['customer']['id'], $favIds);
        $this->cartModel->clearCart($cart['id']);

        $order = $this->orderModel->find($orderId);
        $methodLabel = $this->formatFulfillmentLabel((string)($customerData['fulfillment_type'] ?? 'delivery'), $lang);
        $reply = $lang === 'id'
            ? "✅ *Order berhasil dibuat!*\n\n📦 Nomor Order: *{$order['order_number']}*\n🛍️ Metode: *{$methodLabel}*\n💳 Status Pembayaran: Unpaid\n\nTerima kasih, {$customerData['name']}! Admin kami akan segera memproses pesananmu. Ketik *status order* untuk cek status pesanan."
            : "✅ *Order placed successfully!*\n\n📦 Order Number: *{$order['order_number']}*\n🛍️ Method: *{$methodLabel}*\n💳 Payment Status: Unpaid\n\nThank you, {$customerData['name']}! Our admin will process your order soon.";

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
        $lang = $ctx['language'] ?? 'id';
        $result = $this->askNextField($convCtx, $lang, $ctx['cart'], $ctx['currency'] ?? 'IDR', (float)($ctx['ppn_rate'] ?? 0));
        $result['conv_context'] = $this->mergeCheckoutMeta($result['conv_context'] ?? $convCtx, $ctx['cart']);
        return $result;
    }

    private function continueCheckout(array $ctx, array $convCtx): array
    {
        $result = $this->askNextField(
            $convCtx,
            $ctx['language'] ?? 'id',
            $ctx['cart'],
            $ctx['currency'] ?? 'IDR',
            (float)($ctx['ppn_rate'] ?? 0)
        );
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

    private function isSkipMessage(string $message): bool
    {
        return preg_match('/^\s*(skip|lewati)\s*$/iu', $message) === 1;
    }

    private function detectFulfillmentType(string $message): ?string
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        if ($lower === '1' || str_contains($lower, 'ambil di toko') || str_contains($lower, 'pickup') || str_contains($lower, 'pick up')) {
            return 'pickup';
        }
        if ($lower === '2' || str_contains($lower, 'nomor meja') || str_contains($lower, 'antar ke meja') || preg_match('/\bmeja\b/u', $lower) || preg_match('/\btable\b/u', $lower)) {
            return 'table';
        }
        if ($lower === '3' || str_contains($lower, 'alamat') || str_contains($lower, 'delivery') || str_contains($lower, 'antar ke alamat') || str_contains($lower, 'kirim ke alamat')) {
            return 'delivery';
        }

        return null;
    }

    private function extractTableNumber(string $message): ?string
    {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        if (preg_match('/\b(?:meja|table)\s*([a-z0-9-]{1,20})\b/iu', $message, $m)) {
            return strtoupper($m[1]);
        }

        if (preg_match('/^[a-z0-9-]{1,20}$/iu', $message) === 1) {
            return strtoupper($message);
        }

        return null;
    }

    private function resetFulfillmentDependentFields(array $convCtx, string $fulfillment): array
    {
        if ($fulfillment === 'pickup') {
            unset($convCtx['checkout_table_number'], $convCtx['checkout_address'], $convCtx['checkout_postal']);
        } elseif ($fulfillment === 'table') {
            unset($convCtx['checkout_address'], $convCtx['checkout_postal']);
        } elseif ($fulfillment === 'delivery') {
            unset($convCtx['checkout_table_number']);
        }

        return $convCtx;
    }

    private function formatFulfillmentLabel(string $fulfillment, string $lang): string
    {
        return match ($fulfillment) {
            'pickup' => $lang === 'id' ? 'Ambil di toko' : 'Pickup in store',
            'table' => $lang === 'id' ? 'Delivery ke meja' : 'Deliver to table',
            default => $lang === 'id' ? 'Delivery ke alamat' : 'Deliver to address',
        };
    }
}
