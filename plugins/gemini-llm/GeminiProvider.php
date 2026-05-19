<?php

declare(strict_types=1);

use App\Plugin\LlmProviderInterface;

/**
 * Google Gemini API client via Generative Language REST API.
 */
class GeminiProvider implements LlmProviderInterface
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    private const MODEL_PRICING = [
        'gemini-2.5-flash'       => [0.30, 2.50],
        'gemini-2.5-flash-lite'  => [0.10, 0.40],
        'gemini-2.0-flash'       => [0.10, 0.40],
        'gemini-1.5-flash'       => [0.075, 0.30],
        'gemini-1.5-pro'         => [1.25, 5.00],
    ];

    private string $apiKey;
    private string $model;
    private array $lastUsage = [];

    public function __construct(string $apiKey, string $model = 'gemini-2.0-flash')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function getName(): string   { return 'gemini'; }
    public function getModel(): string  { return $this->model; }
    public function isAvailable(): bool { return $this->apiKey !== ''; }

    public function chat(array $messages, array $options = []): string
    {
        $payload = [
            'contents' => $this->buildContents($messages),
        ];

        if (!empty($options)) {
            $payload['generationConfig'] = $options;
        }

        $data = $this->post($payload);
        return $this->extractText($data);
    }

    public function completeWithSystemPrompt(string $userMessage, string $systemPrompt, int $maxTokens = 20): ?string
    {
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => [[
                'role'  => 'user',
                'parts' => [
                    ['text' => $userMessage],
                ],
            ]],
            'generationConfig' => [
                'temperature'     => 0,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        $data = $this->post($payload);
        $text = trim($this->extractText($data));
        return $text !== '' ? $text : null;
    }

    public function completeJson(string $userMessage, string $systemPrompt, int $maxTokens = 300): ?string
    {
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => [[
                'role'  => 'user',
                'parts' => [
                    ['text' => $userMessage],
                ],
            ]],
            'generationConfig' => [
                'temperature'      => 0,
                'maxOutputTokens'  => $maxTokens,
                'responseMimeType' => 'application/json',
            ],
        ];

        $data = $this->post($payload);
        $text = trim($this->extractText($data));
        return $text !== '' ? $text : null;
    }

    public function getLastUsage(): array
    {
        return $this->lastUsage;
    }

    public function estimateCost(int $promptTokens, int $completionTokens): float
    {
        $pricing = self::MODEL_PRICING[$this->model] ?? [0.10, 0.40];
        return ($promptTokens * $pricing[0] + $completionTokens * $pricing[1]) / 1_000_000;
    }

    private function buildContents(array $messages): array
    {
        $contents = [];
        foreach ($messages as $message) {
            $role = (($message['role'] ?? 'user') === 'assistant') ? 'model' : 'user';
            $contents[] = [
                'role'  => $role,
                'parts' => [
                    ['text' => (string)($message['content'] ?? '')],
                ],
            ];
        }
        return $contents;
    }

    private function extractText(array $data): string
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $texts = [];
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $texts[] = $part['text'];
            }
        }
        return trim(implode("\n", $texts));
    }

    private function post(array $payload): array
    {
        $url = self::API_BASE . rawurlencode($this->model) . ':generateContent?key=' . rawurlencode($this->apiKey);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL       => 1,
            CURLOPT_CONNECTTIMEOUT_MS => 5000,
            CURLOPT_TIMEOUT_MS        => 15000,
        ]);

        $result = curl_exec($ch);
        $err    = curl_error($ch);
        $code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $result === false) {
            throw new \UnexpectedValueException("Gemini cURL error: {$err}");
        }

        $data = json_decode($result ?: '{}', true);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Gemini response is not valid JSON.');
        }

        if ($code >= 400 || isset($data['error'])) {
            $message = (string)($data['error']['message'] ?? ('Gemini API error HTTP ' . $code));
            throw new \UnexpectedValueException($message);
        }

        $this->lastUsage = [
            'prompt_tokens'     => (int)($data['usageMetadata']['promptTokenCount'] ?? 0),
            'completion_tokens' => (int)($data['usageMetadata']['candidatesTokenCount'] ?? 0),
        ];

        return $data;
    }
}
