<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Helpers\Currency;
use App\Models\MenuModel;

class MenuRagResponder
{
    private MenuModel $menuModel;
    private string $provider = 'none';
    private string $apiKey = '';
    private string $model = '';

    public function __construct()
    {
        $this->menuModel = new MenuModel();
        $this->loadConfig();
    }

    public function isEnabled(): bool
    {
        return $this->provider !== 'none' && $this->apiKey !== '';
    }

    public function resolveItems(string $message, int $branchId, int $maxItems = 4): array
    {
        $maxItems = max(1, min($maxItems, 6));
        $seen = [];
        $items = [];

        foreach ($this->buildSearchCandidates($message) as $candidate) {
            foreach ($this->menuModel->searchRelevantByName($candidate, $branchId, 3) as $item) {
                $id = (int)($item['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $items[] = $item;
                break;
            }

            if (count($items) >= $maxItems) {
                return $items;
            }
        }

        return $items;
    }

    public function composeDescriptionReply(array $ctx, array $items): ?string
    {
        if (!$this->isEnabled() || empty($items)) {
            return null;
        }

        $lang = $ctx['language'] ?? 'id';
        $currency = $ctx['currency'] ?? 'IDR';
        $message = trim((string)($ctx['message'] ?? ''));

        $menuContext = [];
        foreach ($items as $index => $item) {
            $desc = trim((string)($item['description'] ?? ''));
            $variantSummary = $this->buildVariantSummary($item, $currency);
            $priceLine = $variantSummary !== ''
                ? "Price options: {$variantSummary}"
                : 'Price: ' . Currency::format((float)($item['effective_price'] ?? $item['price'] ?? 0), $currency);
            $menuContext[] = ($index + 1) . '. '
                . 'Name: ' . (string)$item['name'] . "\n"
                . 'Category: ' . (string)($item['category_name'] ?? '-') . "\n"
                . $priceLine . "\n"
                . 'Description: ' . ($desc !== '' ? $desc : '[no description]');
        }

        $prompt = $this->buildPrompt($lang, $message, implode("\n\n", $menuContext), count($items) > 1);
        $maxTokens = count($items) > 1 ? 320 : 220;
        $reply = $this->callLlm($prompt, $maxTokens);
        if ($reply === null) {
            return null;
        }

        $reply = trim($reply);
        return $reply !== '' ? $reply : null;
    }

    private function buildSearchCandidates(string $message): array
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        $clean = preg_replace('/\b(itu|apa|apakah|tolong|jelaskan|ceritakan|seperti|minuman|makanan|nih|ya|dong|menu|paketnya|detail|info|deskripsi|tentang|minta|please|plis|describe|description|tell me more|more info)\b/u', ' ', $lower);
        $clean = preg_replace('/rp\s*[0-9\.,]+/iu', ' ', (string)$clean);
        $clean = preg_replace('/sgd\s*[0-9\.,]+|aud\s*[0-9\.,]+|\$\s*[0-9\.,]+/iu', ' ', (string)$clean);
        $clean = preg_replace('/[^\p{L}\p{N}\s,&+\-]/u', ' ', (string)$clean);
        $clean = trim((string)preg_replace('/\s+/u', ' ', (string)$clean));

        if ($clean === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*|\s+dan\s+|\s+and\s+|\s+&\s+|\s+\+\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = array_values(array_filter(array_map(static fn(string $part): string => trim($part), $parts)));

        $candidates = [];
        foreach ($parts as $part) {
            $candidates[] = $part;
            $tokens = array_values(array_filter(explode(' ', $part), static fn(string $token): bool => mb_strlen($token, 'UTF-8') >= 4));
            if (count($tokens) >= 2) {
                $candidates[] = implode(' ', $tokens);
            }
        }

        $candidates[] = $clean;

        return array_values(array_unique(array_filter($candidates)));
    }

    private function buildPrompt(string $lang, string $message, string $menuContext, bool $multiple): string
    {
        if ($lang === 'en') {
            $style = $multiple
                ? 'Explain each requested menu item briefly in English. Use only the retrieved menu context.'
                : 'Explain the requested menu item briefly in English. Use only the retrieved menu context.';
            $closing = 'End with a short ordering hint like "Want to order? Type order 1 <item name>."';
        } else {
            $style = $multiple
                ? 'Jelaskan setiap menu yang ditanyakan dengan singkat dalam bahasa Indonesia. Gunakan hanya konteks menu yang diberikan.'
                : 'Jelaskan menu yang ditanyakan dengan singkat dalam bahasa Indonesia. Gunakan hanya konteks menu yang diberikan.';
            $closing = 'Akhiri dengan hint singkat untuk pesan, misalnya "Mau pesan? Ketik pesan 1 <nama menu>."';
        }

        return <<<PROMPT
You are a coffee shop menu assistant.

Rules:
- {$style}
- Do not invent ingredients, variants, or promo details not present in the retrieved context.
- Mention the item name and price for each item.
- If a description is missing, say the item is available but its short description is not yet provided.
- Keep the answer concise and easy to scan.
- {$closing}

Customer message:
{$message}

Retrieved menu context:
{$menuContext}
PROMPT;
    }

    private function buildVariantSummary(array $item, string $currency): string
    {
        $variants = $item['variants'] ?? [];
        if (empty($variants)) {
            return '';
        }

        $parts = [];
        foreach ($variants as $variant) {
            $label = trim((string)($variant['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $parts[] = $label . ' ' . Currency::format((float)($variant['effective_price'] ?? 0), $currency);
        }

        return implode(', ', $parts);
    }

    private function loadConfig(): void
    {
        $rows = Database::getInstance()->query(
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
            error_log('[MenuRagResponder] ' . $e->getMessage());
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
