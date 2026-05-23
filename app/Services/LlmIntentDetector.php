<?php

declare(strict_types=1);

namespace App\Services;

/**
 * LLM-based intent detector — uses OpenAI or Anthropic to classify intent
 * and parse multi-item orders. Falls back to rule-based on API error.
 */
class LlmIntentDetector implements IntentDetectorInterface
{
    private string   $provider;
    private string   $apiKey;
    private string   $model;
    private IntentDetector $fallback;
    private ?int     $branchId = null;
    private ?int     $convId   = null;

    // $ per 1 million tokens [input, output]
    private const MODEL_PRICING = [
        'gpt-4.1-nano'             => [0.10,  0.40],
        'gpt-4.1-mini'             => [0.40,  1.60],
        'gpt-4.1'                  => [2.00,  8.00],
        'gpt-4o'                   => [2.50, 10.00],
        'gpt-4o-mini'              => [0.15,  0.60],
        'gpt-4-turbo'              => [10.00, 30.00],
        'o4-mini'                  => [1.10,  4.40],
        'o3-mini'                  => [1.10,  4.40],
        'gpt-3.5-turbo'            => [0.50,  1.50],
        'claude-haiku-4-5-20251001'  => [0.80,  4.00],
        'claude-sonnet-4-6'          => [3.00, 15.00],
        'claude-opus-4-7'            => [15.00,75.00],
        'claude-3-5-sonnet-20241022' => [3.00, 15.00],
        'claude-3-5-haiku-20241022'  => [0.80,  4.00],
    ];

    private const INTENTS = [
        'tanya_menu', 'tanya_harga', 'tanya_promo',
        'tambah_item', 'ubah_item', 'hapus_item', 'clear_cart', 'lihat_cart',
        'checkout', 'isi_nama', 'isi_email', 'isi_wa', 'isi_fulfillment', 'isi_nomor_meja', 'isi_alamat', 'isi_kode_pos',
        'konfirmasi_order', 'tanya_status_order', 'batal_order',
        'small_talk', 'out_of_scope',
    ];

    public function __construct(string $provider, string $apiKey, string $model)
    {
        $this->provider = $provider;
        $this->apiKey   = $apiKey;
        $this->model    = $model;
        $this->fallback = new IntentDetector();
    }

    public function detect(string $message, array $context = []): string
    {
        // Still use rule-based for checkout data-collection states
        $state = $context['state'] ?? 'idle';
        if (in_array($state, ['awaiting_name','awaiting_email','awaiting_wa','awaiting_fulfillment','awaiting_table','awaiting_address','awaiting_postal','awaiting_confirmation'])) {
            return $this->fallback->detect($message, $context);
        }

        // Pre-check: history questions — LLMs often confuse "tadi saya pesan apa?" with tambah_item
        $lower = mb_strtolower(trim($message), 'UTF-8');
        if ($this->looksLikeOrderHistory($lower)) {
            return 'tanya_status_order';
        }

        // Pre-check: item description / explain requests — LLMs often misclassify these
        if ($this->looksLikeMenuExplanation($lower)) {
            return 'tanya_menu';
        }

        if ($this->looksLikeMenuRecommendation($lower)) {
            return 'tanya_menu';
        }

        // Pre-check: "bikin jadi" / "jadiin" — LLMs may misclassify as out_of_scope
        if (preg_match('/\bbikin\s+jadi\b|\bjadiin\b/u', $lower)) {
            return 'ubah_item';
        }

        $intentList = implode(', ', self::INTENTS);
        $prompt = <<<PROMPT
You are an intent classifier for an Indonesian coffee shop chatbot.
Classify the user message into exactly one of these intents:
{$intentList}

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
- tanya_status_order: asking about PAST orders, order history, or whether they've ordered before (e.g. "tadi saya pesan apa?", "pernah order disini?", "riwayat pesanan saya", "cek order saya")
- batal_order: cancelling order
- small_talk: greeting, thank you, casual chat
- out_of_scope: anything unrelated to the coffee shop

IMPORTANT:
- "tadi saya pesan apa?", "riwayat order", "history pesanan", "pernah order disini?", "saya pernah pesan?" = tanya_status_order (past orders)
- "saya pesan apa?", "pesan apa saya?", "keranjang saya" = lihat_cart (current cart, NOT tanya_status_order)
- "[item] jadi [number]" e.g. "lemon tea jadi 4" = ubah_item (change quantity), NOT tambah_item
- "tadi saya pesan apa?" and similar with "tadi/riwayat/history" = tanya_status_order, NOT tambah_item
- Requests such as "jelaskan", "detail", "info", "deskripsi", "ceritakan", "apa itu" about menu items = tanya_menu
- If the message contains menu names, bullet points, prices like "Rp56.000", "A$5.50", or "$4.00", and asks for explanation/detail, classify as tanya_menu
- Requests such as "recommend me something hot", "something cold", "minuman panas", "rekomendasi kopi dingin" = tanya_menu
- Do NOT classify a menu explanation request as out_of_scope just because the message is long, pasted, or contains multiple item names

Examples:
- "apa itu paket pagi spesial" -> tanya_menu
- "jelaskan paket siang produktif" -> tanya_menu
- "• Paket Siang Produktif — Rp56.000 • Paket Sore Santai — Rp62.000 jelaskan detail" -> tanya_menu
- "Paket Malam Relax, Paket Berdua Spesial, minta info detail" -> tanya_menu
- "pesan 2 latte dan 1 croissant" -> tambah_item
- "lemon tea jadi 4" -> ubah_item

Respond with ONLY the intent name, nothing else.

User message: "{$message}"
PROMPT;

        $intent = $this->callLlm($prompt, 20);

        return ($intent !== null && in_array(trim($intent), self::INTENTS))
            ? trim($intent)
            : $this->fallback->detect($message, $context);
    }

    private function looksLikeOrderHistory(string $lower): bool
    {
        $markers = [
            // past-order questions with "tadi"
            'tadi saya pesan', 'pesan apa tadi', 'saya beli apa tadi',
            'order apa tadi', 'tadi pesan apa', 'pesanan saya tadi',
            'saya udah pesan apa',
            // "have I ever ordered" phrasing
            'pernah order', 'pernah pesan', 'pernah beli',
            'sudah pernah order', 'saya order sebelumnya',
            // history / tracking keywords
            'riwayat order', 'riwayat pesanan', 'history order', 'history pesanan',
            'cek order saya', 'status order saya', 'lacak order',
            'detail ord-', 'detail order',
        ];
        foreach ($markers as $m) {
            if (str_contains($lower, $m)) { return true; }
        }
        if (preg_match('/\bord-\d{8}-[a-z0-9]+\b/i', $lower) === 1) {
            return true;
        }
        return false;
    }

    private function looksLikeMenuExplanation(string $lower): bool
    {
        if (preg_match('/\bitu\s+apa\b|\bapa\s+itu\b|\bitu\s+minuman\b|\bseperti\s+apa\b|\b(jelaskan|deskripsi|detail|info|ceritakan)\b/u', $lower)) {
            return true;
        }

        $hasMenuFormatting = str_contains($lower, 'rp')
            || str_contains($lower, '•')
            || str_contains($lower, 'paket ')
            || str_contains($lower, '—');

        $hasExplainVerb = preg_match('/\b(jelaskan|deskripsi|detail|info|ceritakan)\b/u', $lower) === 1;

        return $hasMenuFormatting && $hasExplainVerb;
    }

    private function looksLikeMenuRecommendation(string $lower): bool
    {
        return preg_match('/\b(recommend|recommend me|suggest|rekomendasi|sarankan|something hot|something cold|something cool|minuman panas|minuman dingin|kopi panas|kopi dingin)\b/u', $lower) === 1;
    }

    public function setLoggingContext(int $branchId, int $convId): void
    {
        $this->branchId = $branchId;
        $this->convId   = $convId;
    }

    public function extractOrderIntent(string $message): array
    {
        $items = $this->extractMultipleItems($message);
        return $items[0] ?? ['item_query' => '', 'qty' => 1];
    }

    public function extractMultipleItems(string $message): array
    {
        $prompt = <<<PROMPT
Extract all menu items the customer wants to order from the message below.
Return a JSON array of objects with "item_query" (item name, lowercase) and "qty" (integer, default 1).
Only return valid JSON, no explanation.

Examples:
"pesan 2 croissant dan 1 lemon tea" → [{"item_query":"croissant","qty":2},{"item_query":"lemon tea","qty":1}]
"mau latte" → [{"item_query":"latte","qty":1}]
"3 americano dan cappuccino" → [{"item_query":"americano","qty":3},{"item_query":"cappuccino","qty":1}]

Message: "{$message}"
PROMPT;

        $raw = $this->callLlm($prompt, 200);

        if ($raw !== null && preg_match('/\[.*\]/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded) && !empty($decoded)) {
                return array_map(fn($i) => [
                    'item_query' => (string)($i['item_query'] ?? ''),
                    'qty'        => max(1, (int)($i['qty'] ?? 1)),
                ], $decoded);
            }
        }

        // Fallback to rule-based parser
        return $this->fallback->extractMultipleItems($message);
    }

    private function callLlm(string $prompt, int $maxTokens): ?string
    {
        try {
            if ($this->provider === 'openai') {
                return $this->callOpenAi($prompt, $maxTokens);
            }
            if ($this->provider === 'anthropic') {
                return $this->callAnthropic($prompt, $maxTokens);
            }
        } catch (\Throwable $e) {
            error_log('[LlmIntentDetector] ' . $e->getMessage());
        }
        return null;
    }

    private function callOpenAi(string $prompt, int $maxTokens): ?string
    {
        $payload = json_encode([
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        $response = $this->httpPost(
            'https://api.openai.com/v1/chat/completions',
            ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json'],
            $payload
        );

        if ($response === null) { return null; }
        $data = json_decode($response, true);
        $this->logTokenUsage(
            (int)($data['usage']['prompt_tokens']     ?? 0),
            (int)($data['usage']['completion_tokens'] ?? 0)
        );
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function callAnthropic(string $prompt, int $maxTokens): ?string
    {
        $payload = json_encode([
            'model'      => $this->model ?: 'claude-haiku-4-5-20251001',
            'max_tokens' => $maxTokens,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        $response = $this->httpPost(
            'https://api.anthropic.com/v1/messages',
            [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ],
            $payload
        );

        if ($response === null) { return null; }
        $data = json_decode($response, true);
        $this->logTokenUsage(
            (int)($data['usage']['input_tokens']  ?? 0),
            (int)($data['usage']['output_tokens'] ?? 0)
        );
        return $data['content'][0]['text'] ?? null;
    }

    private function logTokenUsage(int $promptTokens, int $completionTokens): void
    {
        if ($this->branchId === null || ($promptTokens === 0 && $completionTokens === 0)) {
            return;
        }
        $pricing = self::MODEL_PRICING[$this->model] ?? null;
        $cost    = $pricing
            ? ($promptTokens * $pricing[0] + $completionTokens * $pricing[1]) / 1_000_000
            : 0.0;
        try {
            \App\Config\Database::getInstance()->prepare(
                'INSERT INTO token_usage_logs
                 (branch_id, conversation_id, provider, model, prompt_tokens, completion_tokens, total_tokens, cost_estimate)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $this->branchId, $this->convId, $this->provider, $this->model,
                $promptTokens, $completionTokens,
                $promptTokens + $completionTokens, round($cost, 6),
            ]);
        } catch (\Throwable) {
            // Non-critical — don't break chatbot if logging fails
        }
    }

    private function httpPost(string $url, array $headers, string $body): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST                => true,
            CURLOPT_POSTFIELDS          => $body,
            CURLOPT_HTTPHEADER          => $headers,
            CURLOPT_RETURNTRANSFER      => true,
            CURLOPT_NOSIGNAL            => 1,      // required for timeout to work on Windows
            CURLOPT_CONNECTTIMEOUT_MS   => 5000,   // 5 s connect
            CURLOPT_TIMEOUT_MS          => 12000,  // 12 s total
        ]);
        $result = curl_exec($ch);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err || $result === false) {
            throw new \UnexpectedValueException("cURL error: {$err}");
        }
        return $result ?: null;
    }
}

