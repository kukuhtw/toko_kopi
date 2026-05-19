<?php

declare(strict_types=1);

namespace App\Skills;

use App\Models\CartModel;
use App\Models\MenuModel;
use App\Models\PromoModel;
use App\Services\IntentDetector;
use App\Helpers\Currency;

class CartSkill implements SkillInterface
{
    private CartModel    $cartModel;
    private MenuModel    $menuModel;
    private PromoModel   $promoModel;
    private IntentDetector $detector;

    public function __construct()
    {
        $this->cartModel  = new CartModel();
        $this->menuModel  = new MenuModel();
        $this->promoModel = new PromoModel();
        $this->detector   = new IntentDetector();
    }

    public function canHandle(string $intent): bool
    {
        return in_array($intent, ['tambah_item', 'ubah_item', 'hapus_item', 'clear_cart', 'lihat_cart', 'pakai_promo']);
    }

    public function handle(array $ctx): array
    {
        if (($ctx['conversation']['state'] ?? '') === 'awaiting_item_notes') {
            return $this->handleItemNotes($ctx);
        }
        if (($ctx['conversation']['state'] ?? '') === 'awaiting_variant') {
            return $this->handleVariantSelection($ctx);
        }
        if (($ctx['conversation']['state'] ?? '') === 'awaiting_toppings') {
            return $this->handleToppingSelection($ctx);
        }
        if (($ctx['conversation']['state'] ?? '') === 'awaiting_remove_variant') {
            return $this->handleRemoveVariantSelection($ctx);
        }

        return match ($ctx['intent']) {
            'tambah_item' => $this->handleAdd($ctx),
            'ubah_item'   => $this->handleUpdate($ctx),
            'hapus_item'  => $this->handleRemove($ctx),
            'clear_cart'  => $this->handleClear($ctx),
            'lihat_cart'  => $this->handleView($ctx),
            'pakai_promo' => $this->handleApplyPromo($ctx),
            default       => ['reply' => 'Hmm, saya kurang mengerti.', 'state' => 'idle', 'action_result' => null],
        };
    }

    private function handleAdd(array $ctx): array
    {
        $branchId = $ctx['branch_id'];
        $cart     = $ctx['cart'];
        $lang     = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';

        $parsed = $this->detector->extractMultipleItems($ctx['message']);
        $parsed = $this->resolveReferencedItems($parsed, $ctx['conv_context'] ?? []);

        if (empty($parsed) || ($parsed[0]['item_query'] === '')) {
            return ['reply' => $this->t($lang, 'specify_item'), 'state' => 'idle', 'action_result' => null];
        }

        $added  = [];
        $failed = [];

        foreach ($parsed as $parsedItem) {
            $query = $parsedItem['item_query'] ?? '';
            $qty = (int)($parsedItem['qty'] ?? 1);
            $variantLabel = $parsedItem['variant_label'] ?? null;
            if ($query === '') { continue; }

            $normalizedQuery = $this->normalizeYoghurtQuery($query, (string)$ctx['message']);
            $item = $this->findAvailableItem($normalizedQuery, $branchId);
            if ($item === null) {
                $failed[] = $query;
                continue;
            }

            $selectedToppings = $this->extractSelectedToppings($item, $ctx['message']);
            $variant = $this->resolveVariant($item, $variantLabel);
            if (!empty($item['has_variants']) && $variant === null) {
                return $this->askForVariant($item, $qty, $ctx, $selectedToppings);
            }

            if (!empty($item['has_toppings']) && !$this->hasValidToppingCount($item, $selectedToppings)) {
                return $this->askForToppings($item, $qty, $variant, $selectedToppings, $ctx);
            }

            $unitPrice    = (float)($variant['effective_price'] ?? $item['effective_price']);
            $notes        = $this->buildToppingNote($selectedToppings);
            $cartItemId   = $this->cartModel->addItem(
                $cart['id'],
                (int)$item['id'],
                $qty,
                $unitPrice,
                $notes,
                $variant['id'] ?? null,
                $variant['label'] ?? null
            );
            $added[] = ['item' => $item, 'qty' => $qty, 'variant' => $variant, 'notes' => $notes, 'cart_item_id' => $cartItemId];
        }

        if (empty($added)) {
            $q     = implode(', ', $failed);
            $reply = $lang === 'id'
                ? "Maaf, menu \"{$q}\" tidak ditemukan. Ketik *menu* untuk melihat daftar menu."
                : "Sorry, \"{$q}\" not found. Type *menu* to see our menu.";
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null];
        }

        $lines = [];
        foreach ($added as ['item' => $item, 'qty' => $qty, 'variant' => $variant, 'notes' => $notes]) {
            $displayName = $variant ? "{$item['name']} - {$variant['label']}" : $item['name'];
            $lineTotal = (float)($variant['effective_price'] ?? $item['effective_price']) * $qty;
            $lines[]   = "✅ *{$qty}x {$displayName}* (" . Currency::format($lineTotal, $currency) . ")";
        }

        $reply = implode("\n", $lines);

        if (!empty($failed)) {
            $q     = implode(', ', $failed);
            $reply .= "\n\n" . ($lang === 'id' ? "⚠️ Tidak ditemukan: {$q}" : "⚠️ Not found: {$q}");
        }

        $convCtx      = $this->buildCartContextFromAdded($added, $ctx['conv_context'] ?? []);
        $cartItemIds  = array_column($added, 'cart_item_id');
        $addedNames   = array_map(fn($a) => $a['variant']
            ? "{$a['item']['name']} - {$a['variant']['label']}"
            : $a['item']['name'], $added);
        $itemsStr     = implode(', ', $addedNames);

        $convCtx['pending_note_cart_item_ids'] = $cartItemIds;

        $notesQ = $lang === 'id'
            ? "📝 Ada catatan khusus untuk *{$itemsStr}*?\n_(contoh: sedikit gula, tanpa es, extra shot)_\nKetik *-* jika tidak ada."
            : "📝 Any special notes for *{$itemsStr}*?\n_(e.g. less sugar, no ice, extra shot)_\nType *-* to skip.";

        $reply .= "\n\n" . $this->buildCartSummary($cart, $lang, $currency, (float)($ctx['ppn_rate'] ?? 0));
        $reply .= "\n\n" . $notesQ;

        return [
            'reply'         => $reply,
            'state'         => 'awaiting_item_notes',
            'action_result' => $added,
            'conv_context'  => $convCtx,
        ];
    }

    private function findAvailableItem(string $query, int $branchId): ?array
    {
        $matches = $this->menuModel->searchRelevantByName($query, $branchId, 5);
        foreach ($matches as $m) {
            if ((bool)$m['effective_available']) {
                return $m;
            }
        }
        return null;
    }

    private function handleUpdate(array $ctx): array
    {
        $cart     = $ctx['cart'];
        $lang     = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';
        $message  = $ctx['message'];

        // "semua item jadi 2" — bulk update all cart items to a new quantity
        if (preg_match('/\b(semua|all\s+items?)\b/iu', $message)) {
            return $this->handleUpdateAll($cart, $lang, $currency, $message, (float)($ctx['ppn_rate'] ?? 0));
        }

        $parsed = $this->detector->extractMultipleItems($message);

        if (empty($parsed) || $parsed[0]['item_query'] === '') {
            return ['reply' => $this->t($lang, 'specify_item'), 'state' => 'idle', 'action_result' => null];
        }

        $lines      = [];
        $notInCart  = [];
        $notFound   = [];

        foreach ($parsed as $parsedItem) {
            $query = $parsedItem['item_query'] ?? '';
            $qty = (int)($parsedItem['qty'] ?? 1);
            $variantLabel = $parsedItem['variant_label'] ?? null;
            if ($query === '') { continue; }

            $matches = $this->menuModel->searchByName($query, $ctx['branch_id']);
            if (empty($matches)) {
                $notFound[] = $query;
                continue;
            }

            $item    = $matches[0];
            $variant = $this->resolveVariant($item, $variantLabel);
            $updated = $this->cartModel->updateItem($cart['id'], (int)$item['id'], $qty, $variant['id'] ?? null);

            if (!$updated) {
                $notInCart[] = $variant ? "{$item['name']} - {$variant['label']}" : $item['name'];
                continue;
            }

            $displayName = $variant ? "{$item['name']} - {$variant['label']}" : $item['name'];
            $lines[] = $qty === 0
                ? ($lang === 'id' ? "✅ *{$displayName}* dihapus dari keranjang." : "✅ *{$displayName}* removed from cart.")
                : ($lang === 'id' ? "✅ *{$displayName}* diubah menjadi {$qty}x." : "✅ *{$displayName}* updated to {$qty}x.");
        }

        if (empty($lines)) {
            $reply = !empty($notInCart)
                ? ($lang === 'id' ? 'Item tidak ada di keranjang.' : 'Item not in cart.')
                : $this->t($lang, 'item_not_found');
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null];
        }

        if (!empty($notFound)) {
            $lines[] = ($lang === 'id' ? '⚠️ Tidak ditemukan: ' : '⚠️ Not found: ') . implode(', ', $notFound);
        }
        if (!empty($notInCart)) {
            $lines[] = ($lang === 'id' ? '⚠️ Tidak ada di keranjang: ' : '⚠️ Not in cart: ') . implode(', ', $notInCart);
        }

        $reply = implode("\n", $lines) . "\n\n" . $this->buildCartSummary($cart, $lang, $currency, (float)($ctx['ppn_rate'] ?? 0));

        return [
            'reply' => $reply,
            'state' => 'idle',
            'action_result' => null,
            'conv_context' => $this->buildCartContextFromCurrentCart($cart['id'], $ctx['conv_context'] ?? []),
        ];
    }

    private function handleUpdateAll(array $cart, string $lang, string $currency, string $message, float $ppnRate = 0.0): array
    {
        $wordNums = ['satu'=>1,'dua'=>2,'tiga'=>3,'empat'=>4,'lima'=>5,
                     'enam'=>6,'tujuh'=>7,'delapan'=>8,'sembilan'=>9,'sepuluh'=>10];

        $qty = 1;
        if (preg_match('/\b(\d+)\b/', $message, $m)) {
            $qty = (int)$m[1];
        } else {
            $lower = mb_strtolower($message, 'UTF-8');
            foreach ($wordNums as $word => $val) {
                if (str_contains($lower, $word)) { $qty = $val; break; }
            }
        }

        $items = $this->cartModel->getItems($cart['id']);
        if (empty($items)) {
            $reply = $lang === 'id' ? 'Keranjangmu masih kosong.' : 'Your cart is empty.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null];
        }

        foreach ($items as $item) {
            $this->cartModel->updateItem($cart['id'], $item['menu_item_id'], $qty);
        }

        $reply = ($lang === 'id'
            ? "✅ Semua item diubah menjadi {$qty}x."
            : "✅ All items updated to {$qty}x.")
            . "\n\n" . $this->buildCartSummary($cart, $lang, $currency, $ppnRate);

        return [
            'reply' => $reply,
            'state' => 'idle',
            'action_result' => null,
            'conv_context' => $this->buildCartContextFromCurrentCart($cart['id'], []),
        ];
    }

    private function handleRemove(array $ctx): array
    {
        $cart = $ctx['cart'];
        $lang = $ctx['language'] ?? 'id';

        $query   = preg_replace('/\b(hapus|remove|delete|cancel item|batalkan|take out|take off|get rid of|drop the)\b/ui', '', $ctx['message']);
        $query   = trim($query);
        $matches = $this->menuModel->searchByName($query, $ctx['branch_id']);

        if (empty($matches)) {
            return ['reply' => $this->t($lang, 'item_not_found'), 'state' => 'idle', 'action_result' => null];
        }

        $item    = $matches[0];
        $parsed  = $this->detector->extractOrderIntent($ctx['message']);
        $variant = $this->resolveVariant($item, $parsed['variant_label'] ?? null);
        $removed = $this->cartModel->removeItem($cart['id'], (int)$item['id'], $variant['id'] ?? null);

        $displayName = $variant ? "{$item['name']} - {$variant['label']}" : $item['name'];

        // Variant not specified in message — fall back: check how many entries for this item are in cart
        if (!$removed && $variant === null) {
            $entries = $this->cartModel->getItemsForMenu($cart['id'], (int)$item['id']);
            if (count($entries) === 1) {
                $this->cartModel->removeItemById((int)$entries[0]['id']);
                $removed     = true;
                $displayName = $entries[0]['variant_label']
                    ? "{$item['name']} - {$entries[0]['variant_label']}"
                    : $item['name'];
            } elseif (count($entries) > 1) {
                $labels = implode(', ', array_filter(array_column($entries, 'variant_label')));
                $reply  = $lang === 'id'
                    ? "Ada beberapa ukuran *{$item['name']}* di keranjang ({$labels}). Mau hapus yang mana?"
                    : "There are multiple sizes of *{$item['name']}* in cart ({$labels}). Which one to remove?";
                return [
                    'reply'        => $reply,
                    'state'        => 'awaiting_remove_variant',
                    'action_result'=> null,
                    'conv_context' => array_merge($ctx['conv_context'] ?? [], [
                        'pending_remove_item_id'   => (int)$item['id'],
                        'pending_remove_item_name' => $item['name'],
                    ]),
                ];
            }
        }

        if (!$removed) {
            $reply = $lang === 'id' ? "Item tidak ada di keranjang." : "Item not in cart.";
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null];
        }

        $currency = $ctx['currency'] ?? 'IDR';
        $reply    = ($lang === 'id' ? "✅ *{$displayName}* dihapus dari keranjang." : "✅ *{$displayName}* removed from cart.")
                  . "\n\n" . $this->buildCartSummary($cart, $lang, $currency, (float)($ctx['ppn_rate'] ?? 0));

        return [
            'reply' => $reply,
            'state' => 'idle',
            'action_result' => null,
            'conv_context' => $this->buildCartContextFromCurrentCart($cart['id'], $ctx['conv_context'] ?? []),
        ];
    }

    private function handleRemoveVariantSelection(array $ctx): array
    {
        $lang    = $ctx['language'] ?? 'id';
        $convCtx = $ctx['conv_context'] ?? [];
        $itemId  = (int)($convCtx['pending_remove_item_id'] ?? 0);
        $itemName = (string)($convCtx['pending_remove_item_name'] ?? '');

        if (!$itemId) {
            return ['reply' => $this->t($lang, 'item_not_found'), 'state' => 'idle', 'action_result' => null];
        }

        $entries = $this->cartModel->getItemsForMenu($ctx['cart']['id'], $itemId);
        $query   = mb_strtolower(trim($ctx['message']), 'UTF-8');

        $matched = null;
        foreach ($entries as $entry) {
            if ($entry['variant_label'] && stripos($entry['variant_label'], $query) !== false) {
                $matched = $entry;
                break;
            }
        }

        if ($matched === null) {
            $labels = implode(', ', array_filter(array_column($entries, 'variant_label')));
            $reply  = $lang === 'id'
                ? "Ukuran tidak ditemukan. Pilih salah satu: {$labels}"
                : "Size not found. Choose one: {$labels}";
            return ['reply' => $reply, 'state' => 'awaiting_remove_variant', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $this->cartModel->removeItemById((int)$matched['id']);
        $displayName = $matched['variant_label'] ? "{$itemName} - {$matched['variant_label']}" : $itemName;
        $currency    = $ctx['currency'] ?? 'IDR';
        $reply = ($lang === 'id' ? "✅ *{$displayName}* dihapus dari keranjang." : "✅ *{$displayName}* removed from cart.")
               . "\n\n" . $this->buildCartSummary($ctx['cart'], $lang, $currency, (float)($ctx['ppn_rate'] ?? 0));

        return [
            'reply'         => $reply,
            'state'         => 'idle',
            'action_result' => null,
            'conv_context'  => $this->buildCartContextFromCurrentCart($ctx['cart']['id'], []),
        ];
    }

    private function handleClear(array $ctx): array
    {
        $this->cartModel->clearCart($ctx['cart']['id']);
        $lang  = $ctx['language'] ?? 'id';
        $reply = $lang === 'id'
            ? '🗑️ Keranjang dikosongkan. Ketik *menu* untuk mulai belanja lagi.'
            : '🗑️ Cart cleared. Type *menu* to start shopping again.';
        return [
            'reply' => $reply,
            'state' => 'idle',
            'action_result' => null,
            'conv_context' => ['last_topic' => 'cart', 'last_cart_items' => [], 'last_promo_code' => ''],
        ];
    }

    private function handleApplyPromo(array $ctx): array
    {
        $cart     = $ctx['cart'];
        $lang     = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';

        $code = $this->extractPromoCode($ctx['message']);
        if ($code === '') {
            $reply = $lang === 'id'
                ? "Silakan ketik kode promonya, contoh: *KOPI10*"
                : "Please type your promo code, e.g. *KOPI10*";
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null, 'conv_context' => $ctx['conv_context'] ?? []];
        }

        $subtotal = $this->cartModel->getTotal($cart['id']);
        if ($subtotal <= 0) {
            $reply = $lang === 'id'
                ? 'Keranjangmu kosong. Tambahkan item dulu sebelum memakai kode promo.'
                : 'Your cart is empty. Add items before applying a promo code.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null, 'conv_context' => $ctx['conv_context'] ?? []];
        }

        $promo = $this->promoModel->findByCode($code, $ctx['branch_id'], $ctx['now_local'] ?? '');
        if (!$promo) {
            $reply = $lang === 'id'
                ? "Kode promo *{$code}* tidak ditemukan atau sudah tidak aktif."
                : "Promo code *{$code}* not found or no longer active.";
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => null, 'conv_context' => $ctx['conv_context'] ?? []];
        }

        $cartItems = $this->cartModel->getItems($cart['id']);
        $discount  = $this->promoModel->calculateDiscount($promo, $subtotal, $cartItems);
        if ($discount <= 0) {
            return ['reply' => $this->promoRejectedMsg($promo, $code, $currency, $lang), 'state' => 'idle', 'action_result' => null, 'conv_context' => $ctx['conv_context'] ?? []];
        }

        $this->cartModel->applyPromo($cart['id'], $code, $discount);
        $cart    = $this->cartModel->getBySession($cart['session_key']) ?? $cart;
        $discFmt = Currency::format($discount, $currency);
        $header  = $lang === 'id'
            ? "✅ Kode promo *{$code}* berhasil! Diskon {$discFmt} diterapkan.\n\n"
            : "✅ Promo code *{$code}* applied! Discount of {$discFmt} added.\n\n";

        $convContext = $this->buildCartContextFromCurrentCart($cart['id'], $ctx['conv_context'] ?? []);
        $convContext['last_promo_code'] = $code;
        return [
            'reply' => $header . $this->buildCartSummary($cart, $lang, $currency, (float)($ctx['ppn_rate'] ?? 0)),
            'state' => 'idle',
            'action_result' => $promo,
            'conv_context' => $convContext,
        ];
    }

    private function extractPromoCode(string $message): string
    {
        $stripped = preg_replace(
            '/\b(pakai|gunakan|apply|use|kode|code|promo|voucher|diskon|discount|redeem|tukar|saya|punya|ada)\b/iu',
            '',
            $message
        );
        return strtoupper(trim(preg_replace('/\s+/', ' ', $stripped)));
    }

    private function promoRejectedMsg(array $promo, string $code, string $currency, string $lang): string
    {
        if ((int)($promo['applies_to_category_id'] ?? 0) > 0) {
            return $lang === 'id'
                ? "Kode promo *{$code}* hanya berlaku untuk kategori tertentu yang tidak ada di keranjangmu."
                : "Promo code *{$code}* applies to a specific category not in your cart.";
        }
        $minFmt = Currency::format((float)$promo['min_order'], $currency);
        return $lang === 'id'
            ? "Kode promo *{$code}* memerlukan minimum order {$minFmt}."
            : "Promo code *{$code}* requires a minimum order of {$minFmt}.";
    }

    private function buildCartSummary(array $cart, string $lang, string $currency, float $ppnRate = 0.0): string
    {
        $items = $this->cartModel->getItems($cart['id']);

        if (empty($items)) {
            return $lang === 'id'
                ? 'Keranjangmu masih kosong.'
                : 'Your cart is empty.';
        }

        $header = $lang === 'id' ? "🛒 *Keranjang kamu:*\n" : "🛒 *Your cart:*\n";
        $lines  = [$header];

        $subtotal = 0.0;
        foreach ($items as $item) {
            $lineTotal  = $item['quantity'] * $item['unit_price'];
            $subtotal  += $lineTotal;
            $lines[]    = "• {$item['name']} x{$item['quantity']} — " . Currency::format($lineTotal, $currency);
            if (!empty($item['notes'])) {
                $lines[] = '  ' . $item['notes'];
            }
        }

        $lines[] = str_repeat('─', 28);

        $discount = (float)$cart['discount_amount'];
        if ($discount > 0) {
            $lines[] = 'Subtotal: ' . Currency::format($subtotal, $currency);
            $discLabel = $lang === 'id' ? 'Diskon: -' : 'Discount: -';
            $lines[] = $discLabel . Currency::format($discount, $currency);
        }

        $afterDiscount = max(0.0, $subtotal - $discount);
        $ppnAmount     = $ppnRate > 0 ? round($afterDiscount * $ppnRate / 100, 2) : 0.0;

        if ($ppnAmount > 0) {
            if ($discount === 0.0) {
                $lines[] = 'Subtotal: ' . Currency::format($subtotal, $currency);
            }
            $ppnLabel = $lang === 'id' ? "PPN ({$ppnRate}%): " : "VAT ({$ppnRate}%): ";
            $lines[]  = $ppnLabel . Currency::format($ppnAmount, $currency);
        }

        $total   = $afterDiscount + $ppnAmount;
        $lines[] = '*Total: ' . Currency::format($total, $currency) . '*';
        $lines[] = $lang === 'id'
            ? "\nKetik *checkout* untuk melanjutkan pembayaran."
            : "\nType *checkout* to proceed to payment.";

        return implode("\n", $lines);
    }

    private function handleView(array $ctx): array
    {
        $cart     = $ctx['cart'];
        $lang     = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';
        $items    = $this->cartModel->getItems($cart['id']);

        if (empty($items)) {
            $reply = $lang === 'id'
                ? 'Keranjangmu masih kosong. Ketik *menu* untuk lihat menu.'
                : 'Your cart is empty. Type *menu* to browse our menu.';
            return ['reply' => $reply, 'state' => 'idle', 'action_result' => [], 'conv_context' => ['last_topic' => 'cart', 'last_cart_items' => [], 'last_promo_code' => '']];
        }

        return [
            'reply' => $this->buildCartSummary($cart, $lang, $currency, (float)($ctx['ppn_rate'] ?? 0)),
            'state' => 'idle',
            'action_result' => $items,
            'conv_context' => $this->buildCartContextFromCurrentCart($cart['id'], $ctx['conv_context'] ?? []),
        ];
    }

    private function buildCartContextFromAdded(array $added, array $existing): array
    {
        $existing['last_topic'] = 'cart';
        $existing['last_cart_items'] = array_map(static fn(array $row): array => [
            'id' => (int)($row['item']['id'] ?? 0),
            'name' => (string)($row['item']['name'] ?? ''),
            'variant_label' => (string)($row['variant']['label'] ?? ''),
            'qty' => (int)($row['qty'] ?? 0),
            'notes' => (string)($row['notes'] ?? ''),
        ], $added);
        return $existing;
    }

    private function buildCartContextFromCurrentCart(int $cartId, array $existing): array
    {
        $items = $this->cartModel->getItems($cartId);
        $existing['last_topic'] = 'cart';
        $existing['last_cart_items'] = array_map(static fn(array $item): array => [
            'id' => (int)($item['menu_item_id'] ?? 0),
            'name' => (string)($item['name'] ?? ''),
            'variant_label' => (string)($item['variant_label'] ?? ''),
            'qty' => (int)($item['quantity'] ?? 0),
            'notes' => (string)($item['notes'] ?? ''),
        ], $items);
        return $existing;
    }

    private function resolveReferencedItems(array $parsed, array $convCtx): array
    {
        if (empty($parsed) || empty($convCtx['last_menu_items']) || !is_array($convCtx['last_menu_items'])) {
            return $parsed;
        }

        $fallbackName = (string)($convCtx['last_menu_items'][0]['name'] ?? '');
        if ($fallbackName === '') {
            return $parsed;
        }

        foreach ($parsed as &$item) {
            $query = mb_strtolower(trim((string)($item['item_query'] ?? '')), 'UTF-8');
            if (in_array($query, ['ini', 'itu', 'yang ini', 'yang itu', 'menu ini', 'menu itu'], true)) {
                $item['item_query'] = $fallbackName;
            }
        }
        unset($item);

        return $parsed;
    }

    private function resolveVariant(array $item, ?string $variantLabel): ?array
    {
        $variants = $item['variants'] ?? [];
        if (empty($variants)) {
            return null;
        }

        if ($variantLabel === null || $variantLabel === '') {
            return null;
        }

        $needle = mb_strtolower(trim($variantLabel), 'UTF-8');
        foreach ($variants as $variant) {
            $label = mb_strtolower((string)($variant['label'] ?? ''), 'UTF-8');
            $slug = mb_strtolower((string)($variant['slug'] ?? ''), 'UTF-8');
            if ($needle === $label || $needle === $slug) {
                return $variant;
            }
        }

        return null;
    }

    private function askForVariant(array $item, int $qty, array $ctx, array $selectedToppings = []): array
    {
        $lang = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';
        $variants = $item['variants'] ?? [];
        $options = array_map(
            static fn(array $variant): string => "{$variant['label']} (" . Currency::format((float)($variant['effective_price'] ?? 0), $currency) . ")",
            $variants
        );

        $reply = $lang === 'id'
            ? "Ukuran untuk *{$item['name']}* yang kamu mau apa?\n" . implode("\n", array_map(static fn(string $opt): string => "• {$opt}", $options))
            : "Which size do you want for *{$item['name']}*?\n" . implode("\n", array_map(static fn(string $opt): string => "• {$opt}", $options));

        $convCtx = $ctx['conv_context'] ?? [];
        $convCtx['pending_variant_selection'] = [
            'menu_item_id' => (int)($item['id'] ?? 0),
            'menu_name' => (string)($item['name'] ?? ''),
            'qty' => $qty,
            'selected_toppings' => $selectedToppings,
            'variants' => array_map(static fn(array $variant): array => [
                'id' => (int)($variant['id'] ?? 0),
                'label' => (string)($variant['label'] ?? ''),
                'slug' => (string)($variant['slug'] ?? ''),
                'effective_price' => (float)($variant['effective_price'] ?? 0),
            ], $variants),
        ];
        $convCtx['last_topic'] = 'menu';

        return [
            'reply' => $reply,
            'state' => 'awaiting_variant',
            'action_result' => null,
            'conv_context' => $convCtx,
        ];
    }

    private function handleVariantSelection(array $ctx): array
    {
        $convCtx = $ctx['conv_context'] ?? [];
        $pending = $convCtx['pending_variant_selection'] ?? null;
        $lang = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';

        if (!is_array($pending) || empty($pending['menu_item_id']) || empty($pending['variants'])) {
            return ['reply' => $this->t($lang, 'specify_item'), 'state' => 'idle', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $lower = mb_strtolower(trim((string)$ctx['message']), 'UTF-8');
        if (preg_match('/\b(batal|cancel|tidak jadi)\b/u', $lower)) {
            unset($convCtx['pending_variant_selection']);
            return [
                'reply' => $lang === 'id' ? 'Pemilihan ukuran dibatalkan.' : 'Size selection cancelled.',
                'state' => 'idle',
                'action_result' => null,
                'conv_context' => $convCtx,
            ];
        }

        // Jika customer menyebut jumlah topping yang berbeda dari item saat ini (khusus yoghurt),
        // redirect ke item yoghurt yang sesuai sebelum lanjut memilih ukuran.
        if (preg_match('/\b([234])\s*topping/iu', $ctx['message'], $toppingMatch)) {
            $earlyItem = $this->menuModel->getItemForBranch((int)$pending['menu_item_id'], (int)$ctx['branch_id']);
            if ($earlyItem && stripos((string)($earlyItem['name'] ?? ''), 'yoghurt') !== false) {
                $requestedCount = (int)$toppingMatch[1];
                $requiredCount  = (int)($earlyItem['max_toppings'] ?? 0);
                if ($requiredCount > 0 && $requestedCount !== $requiredCount) {
                    $redirectItem = $this->findAvailableItem("frozen yoghurt {$requestedCount} toppings", (int)$ctx['branch_id']);
                    if ($redirectItem && (int)$redirectItem['id'] !== (int)$pending['menu_item_id']) {
                        unset($convCtx['pending_variant_selection']);
                        return $this->askForVariant($redirectItem, (int)($pending['qty'] ?? 1), array_merge($ctx, ['conv_context' => $convCtx]), []);
                    }
                }
            }
        }

        $variant = null;
        foreach ($pending['variants'] as $option) {
            $label = mb_strtolower((string)($option['label'] ?? ''), 'UTF-8');
            $slug = mb_strtolower((string)($option['slug'] ?? ''), 'UTF-8');
            if (preg_match('/\b' . preg_quote($label, '/') . '\b/u', $lower) || preg_match('/\b' . preg_quote($slug, '/') . '\b/u', $lower)) {
                $variant = $option;
                break;
            }
        }

        if ($variant === null) {
            $reply = $lang === 'id'
                ? 'Pilih salah satu ukuran yang tersedia ya.'
                : 'Please choose one of the available sizes.';
            return ['reply' => $reply, 'state' => 'awaiting_variant', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $item = $this->menuModel->getItemForBranch((int)$pending['menu_item_id'], (int)$ctx['branch_id']);
        if (!$item) {
            unset($convCtx['pending_variant_selection']);
            return ['reply' => $this->t($lang, 'item_not_found'), 'state' => 'idle', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $qty = (int)($pending['qty'] ?? 1);
        $selectedToppings = is_array($pending['selected_toppings'] ?? null) ? $pending['selected_toppings'] : [];
        $selectedToppings = $this->mergeToppingSelections($selectedToppings, $this->extractSelectedToppings($item, (string)$ctx['message']));
        if (!empty($item['has_toppings']) && !$this->hasValidToppingCount($item, $selectedToppings)) {
            unset($convCtx['pending_variant_selection']);
            return $this->askForToppings($item, $qty, $variant, $selectedToppings, array_merge($ctx, ['conv_context' => $convCtx]));
        }

        $notes      = $this->buildToppingNote($selectedToppings);
        $cartItemId = $this->cartModel->addItem(
            (int)$ctx['cart']['id'],
            (int)$item['id'],
            $qty,
            (float)($variant['effective_price'] ?? $item['effective_price']),
            $notes,
            (int)($variant['id'] ?? 0),
            (string)($variant['label'] ?? '')
        );
        unset($convCtx['pending_variant_selection']);
        $convCtx = $this->buildCartContextFromCurrentCart((int)$ctx['cart']['id'], $convCtx);
        $convCtx['pending_note_cart_item_ids'] = [$cartItemId];

        $displayName = "{$item['name']} - {$variant['label']}";
        $notesQ = $lang === 'id'
            ? "📝 Ada catatan khusus untuk *{$displayName}*?\n_(contoh: sedikit gula, tanpa es, extra shot)_\nKetik *-* jika tidak ada."
            : "📝 Any special notes for *{$displayName}*?\n_(e.g. less sugar, no ice, extra shot)_\nType *-* to skip.";

        $reply = ($lang === 'id'
            ? "✅ *{$qty}x {$displayName}* ditambahkan.\n\n"
            : "✅ *{$qty}x {$displayName}* added.\n\n")
            . $this->buildCartSummary($ctx['cart'], $lang, $currency, (float)($ctx['ppn_rate'] ?? 0))
            . "\n\n" . $notesQ;

        return [
            'reply'         => $reply,
            'state'         => 'awaiting_item_notes',
            'action_result' => ['item' => $item, 'variant' => $variant, 'qty' => $qty],
            'conv_context'  => $convCtx,
        ];
    }

    private function normalizeYoghurtQuery(string $query, string $message): string
    {
        $lowerQuery = mb_strtolower($query, 'UTF-8');
        $lowerMessage = mb_strtolower($message, 'UTF-8');
        if (!str_contains($lowerQuery, 'yoghurt') && !str_contains($lowerMessage, 'yoghurt')) {
            return $query;
        }

        if (preg_match('/\b([234])\s*toppings?\b/u', $lowerMessage, $m)) {
            return "frozen yoghurt {$m[1]} toppings";
        }

        return $query;
    }

    private function extractSelectedToppings(array $item, string $message): array
    {
        $toppings = $item['toppings'] ?? [];
        if (empty($toppings)) {
            return [];
        }

        $lower = mb_strtolower($message, 'UTF-8');
        $selected = [];
        foreach ($toppings as $topping) {
            $name = mb_strtolower((string)($topping['name'] ?? ''), 'UTF-8');
            $slug = mb_strtolower((string)($topping['slug'] ?? ''), 'UTF-8');
            $slugWords = str_replace('-', ' ', $slug);
            if (($name !== '' && preg_match('/\b' . preg_quote($name, '/') . '\b/u', $lower))
                || ($slug !== '' && preg_match('/\b' . preg_quote($slug, '/') . '\b/u', $lower))
                || ($slugWords !== '' && preg_match('/\b' . preg_quote($slugWords, '/') . '\b/u', $lower))) {
                $selected[] = [
                    'id' => (int)($topping['id'] ?? 0),
                    'name' => (string)($topping['name'] ?? ''),
                    'slug' => (string)($topping['slug'] ?? ''),
                ];
            }
        }

        return $selected;
    }

    private function hasValidToppingCount(array $item, array $selectedToppings): bool
    {
        $min = (int)($item['min_toppings'] ?? 0);
        $max = (int)($item['max_toppings'] ?? 0);
        if ($max <= 0) {
            return true;
        }

        $count = count($selectedToppings);
        return $count >= $min && $count <= $max;
    }

    private function mergeToppingSelections(array $left, array $right): array
    {
        $merged = [];
        foreach (array_merge($left, $right) as $topping) {
            $id = (int)($topping['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $merged[$id] = $topping;
        }

        return array_values($merged);
    }

    private function buildToppingNote(array $selectedToppings): string
    {
        if (empty($selectedToppings)) {
            return '';
        }

        $names = array_values(array_filter(array_map(
            static fn(array $topping): string => trim((string)($topping['name'] ?? '')),
            $selectedToppings
        )));

        return empty($names) ? '' : 'Toppings: ' . implode(', ', $names);
    }

    private function askForToppings(array $item, int $qty, ?array $variant, array $selectedToppings, array $ctx): array
    {
        $lang = $ctx['language'] ?? 'id';
        $options = array_map(
            static fn(array $topping): string => (string)($topping['name'] ?? ''),
            $item['toppings'] ?? []
        );
        $min = (int)($item['min_toppings'] ?? 0);
        $max = (int)($item['max_toppings'] ?? 0);
        $countText = $min === $max
            ? (string)$max
            : "{$min}-{$max}";

        $reply = $lang === 'id'
            ? "Pilih {$countText} topping untuk *{$item['name']}* ya.\n" . implode("\n", array_map(static fn(string $opt): string => "• {$opt}", $options))
            : "Choose {$countText} toppings for *{$item['name']}*.\n" . implode("\n", array_map(static fn(string $opt): string => "• {$opt}", $options));

        $convCtx = $ctx['conv_context'] ?? [];
        $convCtx['pending_topping_selection'] = [
            'menu_item_id' => (int)($item['id'] ?? 0),
            'menu_name' => (string)($item['name'] ?? ''),
            'qty' => $qty,
            'variant' => $variant,
            'min_toppings' => $min,
            'max_toppings' => $max,
            'selected_toppings' => $selectedToppings,
            'toppings' => array_map(static fn(array $topping): array => [
                'id' => (int)($topping['id'] ?? 0),
                'name' => (string)($topping['name'] ?? ''),
                'slug' => (string)($topping['slug'] ?? ''),
            ], $item['toppings'] ?? []),
        ];

        return [
            'reply' => $reply,
            'state' => 'awaiting_toppings',
            'action_result' => null,
            'conv_context' => $convCtx,
        ];
    }

    private function handleToppingSelection(array $ctx): array
    {
        $convCtx = $ctx['conv_context'] ?? [];
        $pending = $convCtx['pending_topping_selection'] ?? null;
        $lang = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';

        if (!is_array($pending) || empty($pending['menu_item_id']) || empty($pending['toppings'])) {
            return ['reply' => $this->t($lang, 'specify_item'), 'state' => 'idle', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $message = mb_strtolower(trim((string)$ctx['message']), 'UTF-8');
        if (preg_match('/\b(batal|cancel|tidak jadi)\b/u', $message)) {
            unset($convCtx['pending_topping_selection']);
            return [
                'reply' => $lang === 'id' ? 'Pemilihan topping dibatalkan.' : 'Topping selection cancelled.',
                'state' => 'idle',
                'action_result' => null,
                'conv_context' => $convCtx,
            ];
        }

        $selected = is_array($pending['selected_toppings'] ?? null) ? $pending['selected_toppings'] : [];
        foreach ($pending['toppings'] as $option) {
            $name = mb_strtolower((string)($option['name'] ?? ''), 'UTF-8');
            $slug = mb_strtolower((string)($option['slug'] ?? ''), 'UTF-8');
            $slugWords = str_replace('-', ' ', $slug);
            if (($name !== '' && preg_match('/\b' . preg_quote($name, '/') . '\b/u', $message))
                || ($slug !== '' && preg_match('/\b' . preg_quote($slug, '/') . '\b/u', $message))
                || ($slugWords !== '' && preg_match('/\b' . preg_quote($slugWords, '/') . '\b/u', $message))) {
                $selected[] = $option;
            }
        }
        $selected = $this->mergeToppingSelections([], $selected);

        $min = (int)($pending['min_toppings'] ?? 0);
        $max = (int)($pending['max_toppings'] ?? 0);
        if (count($selected) < $min || count($selected) > $max) {
            return [
                'reply' => $lang === 'id' ? 'Jumlah topping belum sesuai. Coba sebutkan topping yang kamu pilih.' : 'The topping count is not valid yet. Please list the toppings you want.',
                'state' => 'awaiting_toppings',
                'action_result' => null,
                'conv_context' => $convCtx,
            ];
        }

        $item = $this->menuModel->getItemForBranch((int)$pending['menu_item_id'], (int)$ctx['branch_id']);
        if (!$item) {
            unset($convCtx['pending_topping_selection']);
            return ['reply' => $this->t($lang, 'item_not_found'), 'state' => 'idle', 'action_result' => null, 'conv_context' => $convCtx];
        }

        $variant    = is_array($pending['variant'] ?? null) ? $pending['variant'] : null;
        $qty        = (int)($pending['qty'] ?? 1);
        $notes      = $this->buildToppingNote($selected);
        $cartItemId = $this->cartModel->addItem(
            (int)$ctx['cart']['id'],
            (int)$item['id'],
            $qty,
            (float)($variant['effective_price'] ?? $item['effective_price']),
            $notes,
            $variant['id'] ?? null,
            $variant['label'] ?? null
        );
        unset($convCtx['pending_topping_selection']);
        $convCtx = $this->buildCartContextFromCurrentCart((int)$ctx['cart']['id'], $convCtx);
        $convCtx['pending_note_cart_item_ids'] = [$cartItemId];

        $displayName = $variant ? "{$item['name']} - {$variant['label']}" : $item['name'];
        $notesQ = $lang === 'id'
            ? "📝 Ada catatan khusus untuk *{$displayName}*?\n_(contoh: sedikit gula, tanpa es, extra shot)_\nKetik *-* jika tidak ada."
            : "📝 Any special notes for *{$displayName}*?\n_(e.g. less sugar, no ice, extra shot)_\nType *-* to skip.";

        $toppingLine = $notes !== '' ? "\n{$notes}" : '';
        $reply = ($lang === 'id'
            ? "✅ *{$qty}x {$displayName}* ditambahkan.{$toppingLine}\n\n"
            : "✅ *{$qty}x {$displayName}* added.{$toppingLine}\n\n")
            . $this->buildCartSummary($ctx['cart'], $lang, $currency, (float)($ctx['ppn_rate'] ?? 0))
            . "\n\n" . $notesQ;

        return [
            'reply'         => $reply,
            'state'         => 'awaiting_item_notes',
            'action_result' => ['item' => $item, 'variant' => $variant, 'qty' => $qty, 'notes' => $notes],
            'conv_context'  => $convCtx,
        ];
    }

    private function handleItemNotes(array $ctx): array
    {
        $lang       = $ctx['language'] ?? 'id';
        $currency   = $ctx['currency'] ?? 'IDR';
        $message    = trim($ctx['message']);
        $convCtx    = $ctx['conv_context'] ?? [];
        $pendingIds = array_map('intval', (array)($convCtx['pending_note_cart_item_ids'] ?? []));
        unset($convCtx['pending_note_cart_item_ids']);

        $isSkip = (bool) preg_match('/^[-–]\s*$|^(tidak|tidak ada|no|none|skip|lewati?)\s*$/iu', $message);

        if (!$isSkip && $message !== '' && !empty($pendingIds)) {
            foreach ($pendingIds as $cartItemId) {
                $this->cartModel->updateItemNotes($cartItemId, $message);
            }
        }

        $reply = $isSkip
            ? ($lang === 'id' ? "OK, tidak ada catatan. 👍" : "OK, no special notes. 👍")
            : ($lang === 'id' ? "✅ Catatan disimpan: _{$message}_" : "✅ Note saved: _{$message}_");

        $reply .= "\n\n" . $this->buildCartSummary($ctx['cart'], $lang, $currency, (float)($ctx['ppn_rate'] ?? 0));

        return [
            'reply'         => $reply,
            'state'         => 'idle',
            'action_result' => ['notes_saved' => !$isSkip, 'notes' => $isSkip ? '' : $message],
            'conv_context'  => $convCtx,
        ];
    }

    private function t(string $lang, string $key): string
    {
        $t = [
            'id' => ['specify_item' => 'Ketik nama menu yang ingin kamu pesan.', 'item_not_found' => 'Menu tidak ditemukan.'],
            'en' => ['specify_item' => 'Please type the menu item name.', 'item_not_found' => 'Item not found.'],
        ];
        return $t[$lang][$key] ?? $t['id'][$key];
    }
}
