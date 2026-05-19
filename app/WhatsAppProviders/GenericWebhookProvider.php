<?php

declare(strict_types=1);

namespace App\WhatsAppProviders;

/**
 * Generic/fallback provider for custom webhooks.
 * Expects a simple JSON body: {"from":"phone","message":"text"}
 */
class GenericWebhookProvider implements WhatsAppProviderInterface
{
    private string $webhookUrl;
    private string $apiKey;

    public function __construct(array $config)
    {
        $this->webhookUrl = $config['webhook_url'] ?? '';
        $this->apiKey     = $config['api_key']     ?? '';
    }

    public function getName(): string
    {
        return 'Generic Webhook';
    }

    public function sendMessage(string $to, string $message): bool
    {
        if (empty($this->webhookUrl)) return false;

        $ch = curl_init($this->webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "X-API-Key: {$this->apiKey}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['to' => $to, 'message' => $message]),
            CURLOPT_TIMEOUT    => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    public function parseWebhook(array $payload): ?array
    {
        if (empty($payload['from']) || empty($payload['message'])) return null;

        return [
            'from'    => $payload['from'],
            'message' => $payload['message'],
            'raw'     => $payload,
        ];
    }

    public function verifyWebhook(array $headers, string $rawBody, array $payload = [], array $server = []): bool
    {
        if (empty($this->apiKey)) return true;
        $key = $headers['HTTP_X_API_KEY'] ?? '';
        return hash_equals($this->apiKey, $key);
    }
}
