<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PromoModel;

class PromoRagResponder
{
    private PromoModel $promoModel;
    private string $provider = 'none';
    private string $apiKey = '';
    private string $model = '';

    public function __construct()
    {
        $this->promoModel = new PromoModel();
        $this->loadConfig();
    }

    public function isEnabled(): bool
    {
        return $this->provider !== 'none' && $this->apiKey !== '';
    }

    public function resolvePromos(string $message, int $branchId, string $nowLocal = '', int $limit = 4): array
    {
        $all = $this->promoModel->getActiveForBranch($branchId, $nowLocal);
        if (empty($all)) {
            return [];
        }

        $query = $this->normalizeQuery($message);
        if ($query === '') {
            return array_slice($all, 0, $limit);
        }

        $ranked = [];
        foreach ($all as $promo) {
            $score = $this->scorePromoMatch($promo, $query);
            if ($score <= 0) {
                continue;
            }
            $promo['_score'] = $score;
            $ranked[] = $promo;
        }

        usort($ranked, static function (array $a, array $b): int {
            $score = ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
            if ($score !== 0) {
                return $score;
            }
            return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });

        return array_slice(array_map(static function (array $promo): array {
            unset($promo['_score']);
            return $promo;
        }, $ranked), 0, $limit);
    }

    public function composeReply(array $ctx, array $promos): ?string
    {
        if (!$this->isEnabled() || empty($promos)) {
            return null;
        }

        $lang = $ctx['language'] ?? 'id';
        $message = trim((string)($ctx['message'] ?? ''));

        $promoContext = [];
        foreach ($promos as $index => $promo) {
            $discount = (string)$promo['discount_value'];
            if (($promo['discount_type'] ?? '') === 'percent') {
                $discount .= '%';
            }
            $promoContext[] = ($index + 1) . '. '
                . 'Title: ' . (string)$promo['title'] . "\n"
                . 'Description: ' . (string)($promo['description'] ?? '-') . "\n"
                . 'Discount: ' . $discount . "\n"
                . 'Promo code: ' . ((string)($promo['promo_code'] ?? '') !== '' ? (string)$promo['promo_code'] : '[none]') . "\n"
                . 'Min order: ' . (string)($promo['min_order'] ?? '0');
        }

        $prompt = $this->buildPrompt($lang, $message, implode("\n\n", $promoContext));
        return $this->callLlm($prompt, 260);
    }

    private function normalizeQuery(string $message): string
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        $lower = preg_replace('/\b(promo|diskon|discount|voucher|kode|code|jelaskan|detail|info|deskripsi|tentang|yang|mana|apa|ada|pakai|gunakan|terbaik|best|termurah|murah)\b/u', ' ', $lower);
        $lower = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', (string)$lower);
        $lower = preg_replace('/\s+/u', ' ', (string)$lower);
        return trim((string)$lower);
    }

    private function scorePromoMatch(array $promo, string $query): int
    {
        $title = mb_strtolower((string)($promo['title'] ?? ''), 'UTF-8');
        $desc = mb_strtolower((string)($promo['description'] ?? ''), 'UTF-8');
        $code = mb_strtolower((string)($promo['promo_code'] ?? ''), 'UTF-8');

        $score = 0;
        if ($query !== '' && str_contains($title, $query)) {
            $score += 100;
        }
        if ($query !== '' && str_contains($desc, $query)) {
            $score += 40;
        }
        if ($query !== '' && $code !== '' && str_contains($code, $query)) {
            $score += 120;
        }

        foreach (array_filter(explode(' ', $query)) as $token) {
            if (mb_strlen($token, 'UTF-8') < 3) {
                continue;
            }
            if (str_contains($title, $token)) {
                $score += 20;
            }
            if (str_contains($desc, $token)) {
                $score += 8;
            }
            if ($code !== '' && str_contains($code, $token)) {
                $score += 25;
            }
        }

        return $score;
    }

    private function buildPrompt(string $lang, string $message, string $promoContext): string
    {
        if ($lang === 'en') {
            $instruction = 'Answer briefly in English using only the retrieved promo context. Mention title, discount, and code if any. If the user asks for recommendation, choose the most relevant promo from the retrieved list and explain why in one short sentence.';
            $closing = 'End with a short checkout hint.';
        } else {
            $instruction = 'Jawab singkat dalam bahasa Indonesia hanya berdasarkan konteks promo yang diberikan. Sebutkan judul, diskon, dan kode jika ada. Jika user minta rekomendasi, pilih promo paling relevan dari daftar yang diambil lalu jelaskan alasannya dalam satu kalimat singkat.';
            $closing = 'Akhiri dengan hint singkat untuk checkout.';
        }

        return <<<PROMPT
You are a coffee shop promo assistant.

Rules:
- {$instruction}
- Do not invent promo conditions beyond the retrieved context.
- {$closing}

Customer message:
{$message}

Retrieved promo context:
{$promoContext}
PROMPT;
    }

    private function loadConfig(): void
    {
        $rows = \App\Config\Database::getInstance()->query(
            'SELECT setting_key, setting_val FROM app_settings
             WHERE setting_key IN ("llm_provider","llm_api_key","llm_model")'
        )->fetchAll();
        $cfg = array_column($rows, 'setting_val', 'setting_key');

        $this->provider = (string)($cfg['llm_provider'] ?? 'none');
        $this->apiKey = (string)($cfg['llm_api_key'] ?? '');
        $this->model = (string)($cfg['llm_model'] ?? '');
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
            if ($this->provider === 'openrouter') {
                return $this->callOpenRouter($prompt, $maxTokens);
            }
        } catch (\Throwable $e) {
            error_log('[PromoRagResponder] ' . $e->getMessage());
        }

        return null;
    }

    private function callOpenAi(string $prompt, int $maxTokens): ?string
    {
        $payload = json_encode([
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $response = $this->httpPost(
            'https://api.openai.com/v1/chat/completions',
            ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json'],
            (string)$payload
        );

        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function callAnthropic(string $prompt, int $maxTokens): ?string
    {
        $payload = json_encode([
            'model' => $this->model ?: 'claude-haiku-4-5-20251001',
            'max_tokens' => $maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $response = $this->httpPost(
            'https://api.anthropic.com/v1/messages',
            [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ],
            (string)$payload
        );

        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? null;
    }

    private function callOpenRouter(string $prompt, int $maxTokens): ?string
    {
        $payload = json_encode([
            'model' => $this->model ?: 'openai/gpt-4o-mini',
            'max_tokens' => $maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $response = $this->httpPost(
            'https://openrouter.ai/api/v1/chat/completions',
            [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . (defined('BASE_URL') ? BASE_URL : 'http://localhost'),
                'X-Title: Toko Kopi Chatbot',
            ],
            (string)$payload
        );

        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function httpPost(string $url, array $headers, string $body): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_CONNECTTIMEOUT_MS => 5000,
            CURLOPT_TIMEOUT_MS => 12000,
        ]);
        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $result === false) {
            throw new \UnexpectedValueException("cURL error: {$err}");
        }

        return $result ?: null;
    }
}
