<?php

declare(strict_types=1);

use App\Services\IntentDetectorInterface;
use App\Config\Database;

/**
 * Intent detector powered by Anthropic Claude.
 *
 * Improvements over the built-in LlmIntentDetector:
 * - System prompt is ephemeral-cached → ~90% cheaper on repeated calls
 * - Order extraction uses native tool use → no brittle JSON-from-text parsing
 * - Cache-read token counts are logged separately for cost analysis
 */
class AnthropicIntentDetector implements IntentDetectorInterface
{
    private \App\Services\IntentDetector $fallback;
    private AnthropicProvider $provider;
    private ?int $branchId = null;
    private ?int $convId   = null;

    private const INTENTS = [
        'tanya_menu', 'tanya_harga', 'tanya_promo',
        'tambah_item', 'ubah_item', 'hapus_item', 'clear_cart', 'lihat_cart',
        'checkout', 'isi_nama', 'isi_email', 'isi_wa', 'isi_alamat', 'isi_kode_pos',
        'konfirmasi_order', 'tanya_status_order', 'batal_order',
        'small_talk', 'out_of_scope',
    ];

    // Cached by Anthropic after first call — identical across all messages
    private const INTENT_SYSTEM_PROMPT = <<<'SYS'
You are an intent classifier for an Indonesian coffee shop chatbot.
Classify the user message into exactly one of these intents:
tanya_menu, tanya_harga, tanya_promo, tambah_item, ubah_item, hapus_item, clear_cart, lihat_cart, checkout, isi_nama, isi_email, isi_wa, isi_alamat, isi_kode_pos, konfirmasi_order, tanya_status_order, batal_order, small_talk, out_of_scope

Intent descriptions:
- tanya_menu: asking what's on the menu, asking to explain menu items, asking what a named drink/food/package is
- tanya_harga: asking about price
- tanya_promo: asking about promotions or discount codes
- tambah_item: ordering or ADDING NEW items to cart (e.g. "pesan 2 latte", "mau kopi", "1 croissant")
- ubah_item: changing quantity of a cart item (e.g. "lemon tea jadi 4", "ganti latte jadi 2", "ubah sandwich jadi 3")
- hapus_item: removing a specific item from cart
- clear_cart: clearing the entire cart
- lihat_cart: viewing current cart contents ("saya pesan apa?", "keranjang saya", "lihat pesanan")
- checkout: proceeding to payment/checkout
- isi_nama: providing their name
- isi_email: providing their email
- isi_wa: providing their WhatsApp number
- isi_alamat: providing delivery address
- isi_kode_pos: providing postal code
- konfirmasi_order: confirming the order (yes/ok/ya/confirm)
- tanya_status_order: asking about PAST orders, order history, or whether they've ordered before
- batal_order: cancelling order
- small_talk: greeting, thank you, casual chat
- out_of_scope: anything unrelated to the coffee shop

IMPORTANT:
- "tadi saya pesan apa?", "riwayat order", "history pesanan", "pernah order disini?", "saya pernah pesan?" = tanya_status_order (past orders)
- "saya pesan apa?", "pesan apa saya?", "keranjang saya" = lihat_cart (current cart, NOT tanya_status_order)
- "[item] jadi [number]" e.g. "lemon tea jadi 4" = ubah_item (change quantity), NOT tambah_item
- "tadi saya pesan apa?" and similar with "tadi/riwayat/history" = tanya_status_order, NOT tambah_item
- Requests such as "jelaskan", "detail", "info", "deskripsi", "ceritakan", "apa itu" about menu items = tanya_menu
- If the message contains menu names, bullet points, prices like "Rp56.000", and asks for explanation/detail, classify as tanya_menu
- Requests such as "recommend me something hot", "something cold", "minuman panas", "rekomendasi kopi dingin" = tanya_menu
- Do NOT classify a menu explanation request as out_of_scope just because the message is long

Examples:
- "apa itu paket pagi spesial" -> tanya_menu
- "jelaskan paket siang produktif" -> tanya_menu
- "pesan 2 latte dan 1 croissant" -> tambah_item
- "lemon tea jadi 4" -> ubah_item
- "tadi saya pesan apa?" -> tanya_status_order
- "saya pesan apa?" -> lihat_cart

Respond with ONLY the intent name, nothing else.
SYS;

    private const EXTRACT_SYSTEM_PROMPT = <<<'SYS'
You extract menu items from Indonesian coffee shop customer messages.
Use the extract_order_items tool to return structured data with item names (lowercase) and quantities.
SYS;

    private const EXTRACT_TOOL = [
        'name'         => 'extract_order_items',
        'description'  => 'Extract all menu items the customer wants to order, with quantities',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'items' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'item_query' => [
                                'type'        => 'string',
                                'description' => 'Menu item name in lowercase',
                            ],
                            'qty' => [
                                'type'        => 'integer',
                                'minimum'     => 1,
                                'description' => 'Quantity ordered',
                            ],
                        ],
                        'required' => ['item_query', 'qty'],
                    ],
                ],
            ],
            'required' => ['items'],
        ],
    ];

    public function __construct(AnthropicProvider $provider)
    {
        $this->provider = $provider;
        $this->fallback = new \App\Services\IntentDetector();
    }

    public function detect(string $message, array $context = []): string
    {
        // Checkout data-collection states are handled by rule-based for reliability
        $state = $context['state'] ?? 'idle';
        if (in_array($state, ['awaiting_name','awaiting_email','awaiting_wa','awaiting_address','awaiting_postal','awaiting_confirmation'])) {
            return $this->fallback->detect($message, $context);
        }

        $lower = mb_strtolower(trim($message), 'UTF-8');

        if ($this->looksLikeOrderHistory($lower))       return 'tanya_status_order';
        if ($this->looksLikeMenuExplanation($lower))    return 'tanya_menu';
        if ($this->looksLikeMenuRecommendation($lower)) return 'tanya_menu';
        if (preg_match('/\bbikin\s+jadi\b|\bjadiin\b/u', $lower)) return 'ubah_item';

        try {
            $raw = $this->provider->completeWithCache(
                'User message: "' . $message . '"',
                self::INTENT_SYSTEM_PROMPT,
                maxTokens: 20
            );
            $this->logUsage();

            if ($raw !== null && in_array(trim($raw), self::INTENTS)) {
                return trim($raw);
            }
        } catch (\Throwable $e) {
            error_log('[AnthropicIntentDetector] detect: ' . $e->getMessage());
        }

        return $this->fallback->detect($message, $context);
    }

    public function extractOrderIntent(string $message): array
    {
        $items = $this->extractMultipleItems($message);
        return $items[0] ?? ['item_query' => '', 'qty' => 1];
    }

    public function extractMultipleItems(string $message): array
    {
        try {
            $result = $this->provider->completeWithTool(
                'Extract items from: "' . $message . '"',
                self::EXTRACT_SYSTEM_PROMPT,
                self::EXTRACT_TOOL,
                maxTokens: 300
            );
            $this->logUsage();

            if (is_array($result) && !empty($result['items'])) {
                return array_map(fn($i) => [
                    'item_query' => (string) ($i['item_query'] ?? ''),
                    'qty'        => max(1, (int) ($i['qty'] ?? 1)),
                ], $result['items']);
            }
        } catch (\Throwable $e) {
            error_log('[AnthropicIntentDetector] extractMultipleItems: ' . $e->getMessage());
        }

        return $this->fallback->extractMultipleItems($message);
    }

    public function setLoggingContext(int $branchId, int $convId): void
    {
        $this->branchId = $branchId;
        $this->convId   = $convId;
    }

    private function logUsage(): void
    {
        if ($this->branchId === null) return;

        $usage  = $this->provider->getLastUsage();
        $input  = ($usage['input_tokens'] ?? 0) + ($usage['cache_creation_input_tokens'] ?? 0);
        $output = $usage['output_tokens'] ?? 0;

        if ($input === 0 && $output === 0) return;

        $cost = $this->provider->estimateCost($input, $output);

        try {
            Database::getInstance()->prepare(
                'INSERT INTO token_usage_logs
                 (branch_id, conversation_id, provider, model, prompt_tokens, completion_tokens, total_tokens, cost_estimate)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $this->branchId, $this->convId,
                'anthropic', $this->provider->getModel(),
                $input, $output, $input + $output,
                round($cost, 6),
            ]);
        } catch (\Throwable) {}
    }

    private function looksLikeOrderHistory(string $lower): bool
    {
        foreach ([
            'tadi saya pesan', 'pesan apa tadi', 'saya beli apa tadi',
            'order apa tadi', 'tadi pesan apa', 'pesanan saya tadi', 'saya udah pesan apa',
            'pernah order', 'pernah pesan', 'pernah beli',
            'sudah pernah order', 'saya order sebelumnya',
            'riwayat order', 'riwayat pesanan', 'history order', 'history pesanan',
            'cek order saya', 'status order saya', 'lacak order',
        ] as $marker) {
            if (str_contains($lower, $marker)) return true;
        }
        return false;
    }

    private function looksLikeMenuExplanation(string $lower): bool
    {
        if (preg_match('/\bitu\s+apa\b|\bapa\s+itu\b|\bitu\s+minuman\b|\bseperti\s+apa\b|\b(jelaskan|deskripsi|detail|info|ceritakan)\b/u', $lower)) {
            return true;
        }
        $hasMenuFmt  = str_contains($lower, 'rp') || str_contains($lower, '•')
                    || str_contains($lower, 'paket ') || str_contains($lower, '—');
        $hasExplainV = preg_match('/\b(jelaskan|deskripsi|detail|info|ceritakan)\b/u', $lower) === 1;
        return $hasMenuFmt && $hasExplainV;
    }

    private function looksLikeMenuRecommendation(string $lower): bool
    {
        return preg_match(
            '/\b(recommend|recommend me|suggest|rekomendasi|sarankan|something hot|something cold|something cool|minuman panas|minuman dingin|kopi panas|kopi dingin)\b/u',
            $lower
        ) === 1;
    }
}
