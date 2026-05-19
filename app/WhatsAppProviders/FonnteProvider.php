<?php

declare(strict_types=1);

namespace App\WhatsAppProviders;

class FonnteProvider implements WhatsAppProviderInterface
{
    private string $apiKey;
    private string $apiUrl = 'https://api.fonnte.com/send';

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function getName(): string
    {
        return 'Fonnte';
    }

    public function sendMessage(string $to, string $message): bool
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ["Authorization: {$this->apiKey}"],
            CURLOPT_POSTFIELDS     => http_build_query([
                'target'  => $to,
                'message' => $message,
            ]),
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("[Fonnte] Send failed. HTTP {$httpCode}: {$response}");
            return false;
        }

        $data = json_decode($response, true);
        return ($data['status'] ?? false) === true;
    }

    public function parseWebhook(array $payload): ?array
    {
        // Fonnte webhook format
        if (empty($payload['sender']) || empty($payload['message'])) {
            return null;
        }

        return [
            'from'    => $payload['sender'],
            'message' => $payload['message'],
            'raw'     => $payload,
        ];
    }

    public function verifyWebhook(array $headers, string $rawBody, array $payload = [], array $server = []): bool
    {
        // Fonnte does not have strict signature verification by default
        return !empty($this->apiKey);
    }
}
