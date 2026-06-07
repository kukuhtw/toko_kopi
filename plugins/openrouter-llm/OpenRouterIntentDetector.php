<?php

declare(strict_types=1);

use App\Services\IntentDetectorInterface;
use KopiBot\Core\DatabaseConnection;

/**
 * Intent detector powered by OpenRouter.
 * Works with any model available on openrouter.ai — GPT, Claude, Gemini, Llama, DeepSeek, etc.
 */
class OpenRouterIntentDetector implements IntentDetectorInterface
{
    private \App\Services\IntentDetector $fallback;
    private OpenRouterProvider $provider;
    private ?int $branchId = null;
    private ?int $convId   = null;

    private const INTENTS = [
        'tanya_menu', 'tanya_harga', 'tanya_promo',
        'tambah_item', 'ubah_item', 'hapus_item', 'clear_cart', 'lihat_cart',
        'checkout', 'isi_nama', 'isi_email', 'isi_wa', 'isi_alamat', 'isi_kode_pos',
        'konfirmasi_order', 'tanya_status_order', 'batal_order',
        'small_talk', 'out_of_scope',
    ];

    private function buildIntentSystemPrompt(string $businessType): string
    {
        return <<<SYS
You are an intent classifier for an Indonesian {$businessType} chatbot.
Classify the user message into exactly one of these intents:
tanya_menu, tanya_harga, tanya_promo, tambah_item, ubah_item, hapus_item, clear_cart, lihat_cart, checkout, isi_nama, isi_email, isi_wa, isi_alamat, isi_kode_pos, konfirmasi_order, tanya_status_order, batal_order, small_talk, out_of_scope

Intent descriptions:
- tanya_menu: asking what's on the menu, asking to explain catalog items, asking what a named product/package is
- tanya_harga: asking about price
- tanya_promo: asking about promotions or discount codes
- tambah_item: ordering or ADDING NEW items to cart (e.g. "pesan 2 latte", "mau kopi", "1 croissant")
- ubah_item: changing quantity of a cart item (e.g. "lemon tea jadi 4", "ganti latte jadi 2")
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
- out_of_scope: anything unrelated to the {$businessType}

IMPORTANT:
- "tadi saya pesan apa?", "riwayat order", "history pesanan", "pernah order disini?" = tanya_status_order (past orders)
- "saya pesan apa?", "pesan apa saya?", "keranjang saya" = lihat_cart
- "[item] jadi [number]" e.g. "lemon tea jadi 4" = ubah_item, NOT tambah_item
- "jelaskan", "detail", "info", "deskripsi", "apa itu" about catalog items = tanya_menu
- "recommend", "rekomendasi", "sarankan" = tanya_menu
- Do NOT classify catalog-explanation messages as out_of_scope

Examples:
- "apa itu paket pagi spesial" -> tanya_menu
- "pesan 2 latte dan 1 croissant" -> tambah_item
- "lemon tea jadi 4" -> ubah_item
- "tadi saya pesan apa?" -> tanya_status_order
- "saya pesan apa?" -> lihat_cart

Respond with ONLY the intent name, nothing else.
SYS;
    }

    private function buildDetectAllSystemPrompt(string $businessType): string
    {
        return <<<SYS
You are an intent classifier for an Indonesian {$businessType} chatbot.
Identify ALL intents present in the user message, from this list:
tanya_menu, tanya_harga, tanya_promo, tambah_item, ubah_item, hapus_item, clear_cart, lihat_cart, checkout, isi_nama, isi_email, isi_wa, isi_alamat, isi_kode_pos, konfirmasi_order, tanya_status_order, batal_order, small_talk, out_of_scope

Rules:
- Return a JSON array ordered by relevance, e.g. ["tambah_item","tanya_promo"]
- Most messages have exactly 1 intent; only return multiple if the message clearly contains multiple distinct requests
- Do NOT include out_of_scope or small_talk if other actionable intents exist
- "tadi saya pesan apa?" = tanya_status_order; "saya pesan apa?" = lihat_cart
- "[item] jadi [number]" = ubah_item; order requests = tambah_item
- Catalog explanation/detail/describe/recommend requests = tanya_menu
- out_of_scope: anything unrelated to the {$businessType}
- Return ONLY the JSON array, nothing else. Example: ["tambah_item","tanya_promo"]
SYS;
    }

    private const EXTRACT_SYSTEM_PROMPT = <<<'SYS'
You extract menu items from Indonesian coffee shop customer messages.
Return a JSON array of objects with "item_query" (item name, lowercase) and "qty" (integer, min 1).
Return ONLY the JSON array, no explanation, no markdown.

Examples:
"pesan 2 croissant dan 1 lemon tea" -> [{"item_query":"croissant","qty":2},{"item_query":"lemon tea","qty":1}]
"mau latte" -> [{"item_query":"latte","qty":1}]
"3 americano dan cappuccino" -> [{"item_query":"americano","qty":3},{"item_query":"cappuccino","qty":1}]
SYS;

    public function __construct(OpenRouterProvider $provider)
    {
        $this->provider = $provider;
        $this->fallback = new \App\Services\IntentDetector();
    }

    public function detectAll(string $message, array $context = []): array
    {
        $state = $context['state'] ?? 'idle';
        if (in_array($state, ['awaiting_name','awaiting_email','awaiting_wa','awaiting_address',
            'awaiting_postal','awaiting_confirmation'])) {
            return [$this->detect($message, $context)];
        }

        $lower = mb_strtolower(trim($message), 'UTF-8');
        if ($this->looksLikeOrderHistory($lower))       { return ['tanya_status_order']; }
        if ($this->looksLikeMenuExplanation($lower))    { return ['tanya_menu']; }
        if ($this->looksLikeMenuRecommendation($lower)) { return ['tanya_menu']; }
        if (preg_match('/\bbikin\s+jadi\b|\bjadiin\b/u', $lower)) { return ['ubah_item']; }

        try {
            $businessType = $context['business_type'] ?? 'toko';
            $raw = $this->provider->completeJson(
                'User message: "' . $message . '"',
                $this->buildDetectAllSystemPrompt($businessType),
                maxTokens: 60
            );
            $this->logUsage();

            $intents = $this->parseIntentArray($raw ?? '');
            if (!empty($intents)) {
                return $intents;
            }
        } catch (\Throwable $e) {
            error_log('[OpenRouterIntentDetector] detectAll: ' . $e->getMessage());
        }

        return $this->fallback->detectAll($message, $context);
    }

    private function parseIntentArray(string $raw): array
    {
        if (preg_match('/\[[^\]]*\]/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                $valid = array_values(array_filter(
                    array_map('trim', $decoded),
                    fn($i) => is_string($i) && in_array($i, self::INTENTS, true)
                ));
                if (!empty($valid)) {
                    return $valid;
                }
            }
        }
        return [];
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
            $businessType = $context['business_type'] ?? 'toko';
            $raw = $this->provider->completeWithSystemPrompt(
                'User message: "' . $message . '"',
                $this->buildIntentSystemPrompt($businessType),
                maxTokens: 20
            );
            $this->logUsage();

            if ($raw !== null && in_array(trim($raw), self::INTENTS)) {
                return trim($raw);
            }
        } catch (\Throwable $e) {
            error_log('[OpenRouterIntentDetector] detect: ' . $e->getMessage());
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
            $raw = $this->provider->completeJson(
                'Extract items from: "' . $message . '"',
                self::EXTRACT_SYSTEM_PROMPT,
                maxTokens: 300
            );
            $this->logUsage();

            if ($raw !== null && preg_match('/\[.*\]/s', $raw, $m)) {
                $decoded = json_decode($m[0], true);
                if (is_array($decoded) && !empty($decoded)) {
                    return array_map(fn($i) => [
                        'item_query' => (string) ($i['item_query'] ?? ''),
                        'qty'        => max(1, (int) ($i['qty'] ?? 1)),
                    ], $decoded);
                }
            }
        } catch (\Throwable $e) {
            error_log('[OpenRouterIntentDetector] extractMultipleItems: ' . $e->getMessage());
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
        $input  = $usage['prompt_tokens']     ?? 0;
        $output = $usage['completion_tokens'] ?? 0;

        if ($input === 0 && $output === 0) return;

        $cost = $this->provider->estimateCost($input, $output);

        try {
            DatabaseConnection::getInstance()->prepare(
                'INSERT INTO token_usage_logs
                 (branch_id, conversation_id, provider, model, prompt_tokens, completion_tokens, total_tokens, cost_estimate)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $this->branchId, $this->convId,
                'openrouter', $this->provider->getModel(),
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

