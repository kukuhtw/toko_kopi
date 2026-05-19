<?php

declare(strict_types=1);

namespace App\WhatsAppProviders;

class WablasProvider implements WhatsAppProviderInterface
{
    private string $apiKey;
    private string $secretKey;
    private string $serverUrl;

    public function __construct(array $config)
    {
        $this->apiKey    = $config['api_key']    ?? '';
        $this->secretKey = $config['api_secret'] ?? '';
        $this->serverUrl = $config['server_url'] ?? 'https://console.wablas.com';
    }

    public function getName(): string
    {
        return 'Wablas';
    }

    public function sendMessage(string $to, string $message): bool
    {
        $url = rtrim($this->serverUrl, '/') . '/api/send-message';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: {$this->apiKey}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'phone'   => $to,
                'message' => $message,
            ]),
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("[Wablas] Send failed. HTTP {$httpCode}: {$response}");
            return false;
        }

        $data = json_decode($response, true);
        return ($data['status'] ?? false) === true;
    }

    public function parseWebhook(array $payload): ?array
    {
        // Wablas webhook
        if (empty($payload['data']['phone']) || empty($payload['data']['message'])) {
            return null;
        }

        return [
            'from'    => $payload['data']['phone'],
            'message' => $payload['data']['message'],
            'raw'     => $payload,
        ];
    }

    public function verifyWebhook(array $headers, string $rawBody, array $payload = [], array $server = []): bool
    {
        // Basic token check
        $token = $headers['HTTP_WABLAS_TOKEN'] ?? $headers['wablas-token'] ?? '';
        return !$this->secretKey || hash_equals($this->secretKey, $token);
    }
}
