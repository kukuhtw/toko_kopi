<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;

class AdminStructuredLlmService
{
    private string $provider = 'none';
    private string $apiKey = '';
    private string $model = '';

    public function __construct()
    {
        $this->loadConfig();
    }

    public function isEnabled(): bool
    {
        return $this->provider !== 'none' && $this->apiKey !== '';
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function completeText(string $systemPrompt, string $userPrompt, int $maxTokens = 800): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $prompt = "System:\n{$systemPrompt}\n\nUser:\n{$userPrompt}";

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
            error_log('[AdminStructuredLlmService] ' . $e->getMessage());
        }

        return null;
    }

    public function completeJson(string $systemPrompt, string $userPrompt, array $fallback, int $maxTokens = 1200): array
    {
        $response = $this->completeText(
            $systemPrompt . "\nReturn valid JSON only. No markdown fences. No explanation.",
            $userPrompt,
            $maxTokens
        );

        if ($response === null) {
            return $fallback;
        }

        $decoded = json_decode(trim($response), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/(\{.*\}|\[.*\])/s', $response, $matches) === 1) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $fallback;
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

    private function callOpenAi(string $prompt, int $maxTokens): ?string
    {
        $payload = json_encode([
            'model' => $this->model ?: 'gpt-4.1-mini',
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
                'X-Title: Toko Kopi Admin Import Export',
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
            CURLOPT_TIMEOUT_MS => 20000,
        ]);
        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $result === false) {
            throw new \UnexpectedValueException('cURL error: ' . $err);
        }

        return $result ?: null;
    }
}
