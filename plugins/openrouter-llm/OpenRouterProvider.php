<?php

declare(strict_types=1);

use App\Plugin\LlmProviderInterface;

/**
 * OpenRouter API client — OpenAI-compatible endpoint.
 * Supports 200+ models from OpenAI, Anthropic, Google, Meta, Mistral, DeepSeek, etc.
 * See: https://openrouter.ai/models
 */
class OpenRouterProvider implements LlmProviderInterface
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';

    // $ per 1 million tokens [input, output] — common OpenRouter models
    private const MODEL_PRICING = [
        // OpenAI via OpenRouter
        'openai/gpt-4.1'                        => [2.00,  8.00],
        'openai/gpt-4.1-mini'                   => [0.40,  1.60],
        'openai/gpt-4.1-nano'                   => [0.10,  0.40],
        'openai/gpt-4o'                         => [2.50, 10.00],
        'openai/gpt-4o-mini'                    => [0.15,  0.60],
        // Anthropic via OpenRouter
        'anthropic/claude-sonnet-4-5'           => [3.00, 15.00],
        'anthropic/claude-haiku-4-5'            => [0.80,  4.00],
        'anthropic/claude-3-5-sonnet'           => [3.00, 15.00],
        'anthropic/claude-3-5-haiku'            => [0.80,  4.00],
        // Google
        'google/gemini-2.0-flash-001'           => [0.10,  0.40],
        'google/gemini-flash-1.5'               => [0.075, 0.30],
        'google/gemini-pro-1.5'                 => [1.25,  5.00],
        // Meta Llama
        'meta-llama/llama-3.3-70b-instruct'     => [0.59,  0.79],
        'meta-llama/llama-3.1-70b-instruct'     => [0.59,  0.79],
        'meta-llama/llama-3.1-8b-instruct'      => [0.055, 0.055],
        // Mistral
        'mistralai/mistral-7b-instruct'         => [0.055, 0.055],
        'mistralai/mixtral-8x7b-instruct'       => [0.24,  0.24],
        'mistralai/mistral-small'               => [0.10,  0.30],
        // DeepSeek
        'deepseek/deepseek-chat'                => [0.14,  0.28],
        'deepseek/deepseek-r1'                  => [0.55,  2.19],
        // Qwen
        'qwen/qwen-2.5-72b-instruct'            => [0.35,  0.40],
        'qwen/qwen-2.5-7b-instruct'             => [0.055, 0.055],
    ];

    private array $lastUsage = [];
    private string $apiKey;
    private string $model;
    private string $siteUrl;
    private string $siteName;

    public function __construct(
        string $apiKey,
        string $model = 'openai/gpt-4o-mini',
        string $siteUrl = '',
        string $siteName = 'Toko Kopi Chatbot'
    ) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->siteUrl = $siteUrl;
        $this->siteName = $siteName;
    }

    public function getName(): string   { return 'openrouter'; }
    public function getModel(): string  { return $this->model; }
    public function isAvailable(): bool { return $this->apiKey !== ''; }

    /**
     * Multi-turn chat — satisfies LlmProviderInterface for generic use.
     */
    public function chat(array $messages, array $options = []): string
    {
        $payload = array_merge([
            'model'      => $this->model,
            'max_tokens' => 1024,
            'messages'   => $messages,
        ], $options);

        $response       = $this->post($payload);
        $data           = json_decode($response, true);
        $this->lastUsage = $this->parseUsage($data);

        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Single-turn completion with a separate system prompt.
     * Uses OpenAI message format: system + user messages.
     */
    public function completeWithSystemPrompt(
        string $userMessage,
        string $systemPrompt,
        int    $maxTokens = 20
    ): ?string {
        $payload = [
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'messages'   => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
        ];

        $response       = $this->post($payload);
        $data           = json_decode($response, true);
        $this->lastUsage = $this->parseUsage($data);

        return $data['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Structured JSON extraction via response_format.
     * Falls back to text parsing if model does not support JSON mode.
     */
    public function completeJson(
        string $userMessage,
        string $systemPrompt,
        int    $maxTokens = 400
    ): ?string {
        $payload = [
            'model'           => $this->model,
            'max_tokens'      => $maxTokens,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
        ];

        try {
            $response       = $this->post($payload);
            $data           = json_decode($response, true);
            $this->lastUsage = $this->parseUsage($data);

            // If the API returned an error (e.g. model doesn't support json_object), fall back
            if (isset($data['error'])) {
                return $this->completeWithSystemPrompt($userMessage, $systemPrompt, $maxTokens);
            }

            return $data['choices'][0]['message']['content'] ?? null;
        } catch (\Throwable) {
            return $this->completeWithSystemPrompt($userMessage, $systemPrompt, $maxTokens);
        }
    }

    /** Usage data from the most recent API call */
    public function getLastUsage(): array { return $this->lastUsage; }

    public function estimateCost(int $promptTokens, int $completionTokens): float
    {
        $pricing = self::MODEL_PRICING[$this->model] ?? [1.00, 5.00];
        return ($promptTokens * $pricing[0] + $completionTokens * $pricing[1]) / 1_000_000;
    }

    private function parseUsage(array $data): array
    {
        return [
            'prompt_tokens'     => $data['usage']['prompt_tokens']     ?? 0,
            'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
        ];
    }

    private function post(array $payload): string
    {
        $ref  = $this->siteUrl ?: (defined('BASE_URL') ? BASE_URL : 'http://localhost');
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: '          . $ref,
            'X-Title: '               . $this->siteName,
        ];

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
            throw new \UnexpectedValueException("OpenRouter cURL error: {$err}");
        }

        return $result ?: '{}';
    }
}
