<?php

declare(strict_types=1);

use KopiBot\Contracts\EmbeddingProviderInterface;

class OpenAIEmbeddingProvider implements EmbeddingProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/embeddings';

    public function __construct(
        private string $apiKey,
        private string $model = 'text-embedding-3-small',
        private int $dimensions = 1536
    ) {}

    public function getName(): string
    {
        return 'openai';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '' && function_exists('curl_init');
    }

    public function embed(string $text): array
    {
        $vectors = $this->embedBatch([$text]);
        return $vectors[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        $texts = array_values(array_filter(array_map(
            static fn($text): string => trim((string)$text),
            $texts
        ), static fn(string $text): bool => $text !== ''));

        if ($texts === []) {
            return [];
        }

        $payload = [
            'model' => $this->model,
            'input' => $texts,
        ];

        if ($this->dimensions > 0 && str_starts_with($this->model, 'text-embedding-3')) {
            $payload['dimensions'] = $this->dimensions;
        }

        $data = $this->post($payload);
        $rows = (array)($data['data'] ?? []);
        usort($rows, static fn(array $a, array $b): int => ((int)($a['index'] ?? 0)) <=> ((int)($b['index'] ?? 0)));

        $vectors = [];
        foreach ($rows as $row) {
            $embedding = $row['embedding'] ?? [];
            $vectors[] = is_array($embedding) ? array_map('floatval', $embedding) : [];
        }

        return $vectors;
    }

    private function post(array $payload): array
    {
        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 5000,
            CURLOPT_TIMEOUT_MS => 20000,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new \RuntimeException('OpenAI embedding request failed: ' . $error);
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenAI embedding response is not valid JSON.');
        }

        if ($status >= 400) {
            $message = (string)($decoded['error']['message'] ?? 'OpenAI embedding request failed.');
            throw new \RuntimeException($message);
        }

        return $decoded;
    }
}
