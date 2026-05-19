<?php

declare(strict_types=1);

use App\Plugin\LlmProviderInterface;

/**
 * Anthropic Claude API client.
 * Supports prompt caching (ephemeral system prompt) and native tool use.
 */
class AnthropicProvider implements LlmProviderInterface
{
    private const API_URL    = 'https://api.anthropic.com/v1/messages';
    private const API_VER    = '2023-06-01';
    private const CACHE_BETA = 'prompt-caching-2024-07-31';

    private const MODEL_PRICING = [
        'claude-opus-4-7'            => [15.00, 75.00],
        'claude-sonnet-4-6'          => [3.00,  15.00],
        'claude-haiku-4-5-20251001'  => [0.80,   4.00],
        'claude-3-5-sonnet-20241022' => [3.00,  15.00],
        'claude-3-5-haiku-20241022'  => [0.80,   4.00],
    ];

    private array $lastUsage = [];
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'claude-haiku-4-5-20251001')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function getName(): string   { return 'anthropic'; }
    public function getModel(): string  { return $this->model; }
    public function isAvailable(): bool { return $this->apiKey !== ''; }

    /**
     * Basic multi-turn chat (no caching, no tools).
     * Satisfies LlmProviderInterface for generic use.
     */
    public function chat(array $messages, array $options = []): string
    {
        $payload = array_merge([
            'model'      => $this->model,
            'max_tokens' => 1024,
            'messages'   => $messages,
        ], $options);

        $response       = $this->post($payload, false);
        $data           = json_decode($response, true);
        $this->lastUsage = $this->parseUsage($data);

        return $data['content'][0]['text'] ?? '';
    }

    /**
     * Single-turn completion with an ephemeral-cached system prompt.
     * The system prompt tokens are served from cache on repeat calls, saving ~90% cost.
     */
    public function completeWithCache(
        string $userMessage,
        string $systemPrompt,
        int    $maxTokens = 20
    ): ?string {
        $payload = [
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'system'     => [[
                'type'          => 'text',
                'text'          => $systemPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages'   => [['role' => 'user', 'content' => $userMessage]],
        ];

        $response       = $this->post($payload, true);
        $data           = json_decode($response, true);
        $this->lastUsage = $this->parseUsage($data);

        return $data['content'][0]['text'] ?? null;
    }

    /**
     * Structured extraction using Claude's native tool use.
     * Returns the tool's input object, or null on failure.
     */
    public function completeWithTool(
        string $userMessage,
        string $systemPrompt,
        array  $toolSchema,
        int    $maxTokens = 500
    ): ?array {
        $payload = [
            'model'       => $this->model,
            'max_tokens'  => $maxTokens,
            'system'      => [[
                'type'          => 'text',
                'text'          => $systemPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'tools'       => [$toolSchema],
            'tool_choice' => ['type' => 'any'],
            'messages'    => [['role' => 'user', 'content' => $userMessage]],
        ];

        $response       = $this->post($payload, true);
        $data           = json_decode($response, true);
        $this->lastUsage = $this->parseUsage($data);

        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                return $block['input'] ?? null;
            }
        }

        return null;
    }

    /** Usage data from the most recent API call */
    public function getLastUsage(): array { return $this->lastUsage; }

    public function estimateCost(int $promptTokens, int $completionTokens): float
    {
        $pricing = self::MODEL_PRICING[$this->model] ?? [3.00, 15.00];
        return ($promptTokens * $pricing[0] + $completionTokens * $pricing[1]) / 1_000_000;
    }

    private function parseUsage(array $data): array
    {
        return [
            'input_tokens'                => $data['usage']['input_tokens']                   ?? 0,
            'output_tokens'               => $data['usage']['output_tokens']                  ?? 0,
            'cache_creation_input_tokens' => $data['usage']['cache_creation_input_tokens']    ?? 0,
            'cache_read_input_tokens'     => $data['usage']['cache_read_input_tokens']        ?? 0,
        ];
    }

    private function post(array $payload, bool $withCacheBeta): string
    {
        $headers = [
            'x-api-key: '         . $this->apiKey,
            'anthropic-version: ' . self::API_VER,
            'Content-Type: application/json',
        ];

        if ($withCacheBeta) {
            $headers[] = 'anthropic-beta: ' . self::CACHE_BETA;
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST              => true,
            CURLOPT_POSTFIELDS        => json_encode($payload),
            CURLOPT_HTTPHEADER        => $headers,
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_NOSIGNAL          => 1,
            CURLOPT_CONNECTTIMEOUT_MS => 5_000,
            CURLOPT_TIMEOUT_MS        => 15_000,
        ]);

        $result = curl_exec($ch);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err || $result === false) {
            throw new \UnexpectedValueException("Anthropic cURL error: {$err}");
        }

        return $result ?: '{}';
    }
}
