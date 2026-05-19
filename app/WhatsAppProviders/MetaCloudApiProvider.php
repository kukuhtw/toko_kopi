<?php

declare(strict_types=1);

namespace App\WhatsAppProviders;

class MetaCloudApiProvider implements WhatsAppProviderInterface
{
    private string $accessToken;
    private string $phoneNumberId;
    private string $verifyToken;
    private string $appSecret;

    public function __construct(array $config)
    {
        $this->accessToken   = $config['api_key']       ?? '';
        $this->phoneNumberId = $config['phone_number_id'] ?? '';
        $this->verifyToken   = $config['webhook_token'] ?? '';
        $this->appSecret     = $config['api_secret']    ?? '';
    }

    public function getName(): string
    {
        return 'Meta Cloud API';
    }

    public function sendMessage(string $to, string $message): bool
    {
        // Normalize number
        $to = preg_replace('/[^0-9]/', '', $to);

        $url  = "https://graph.facebook.com/v19.0/{$this->phoneNumberId}/messages";
        $body = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$this->accessToken}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT    => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("[Meta] Send failed. HTTP {$httpCode}: {$response}");
            return false;
        }

        $data = json_decode($response, true);
        return !empty($data['messages'][0]['id']);
    }

    public function parseWebhook(array $payload): ?array
    {
        // Handle Meta verification challenge
        if (isset($payload['hub_mode'])) {
            return null; // handled separately in webhook endpoint
        }

        $entry = $payload['entry'][0] ?? null;
        if (!$entry) return null;

        $changes = $entry['changes'][0] ?? null;
        if (!$changes || ($changes['field'] ?? '') !== 'messages') return null;

        $value   = $changes['value'] ?? [];
        $msgData = $value['messages'][0] ?? null;
        if (!$msgData || ($msgData['type'] ?? '') !== 'text') return null;

        return [
            'from'    => $msgData['from'],
            'message' => $msgData['text']['body'] ?? '',
            'raw'     => $payload,
        ];
    }

    public function verifyWebhook(array $headers, string $rawBody, array $payload = [], array $server = []): bool
    {
        // Verify via X-Hub-Signature-256
        $signature = $headers['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if (empty($signature) || !$this->appSecret) return true; // skip if no secret

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->appSecret);
        return hash_equals($expected, $signature);
    }

    /** Handle Meta webhook verification challenge */
    public function handleVerification(): void
    {
        $mode  = $_GET['hub_mode']       ?? '';
        $token = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? '';

        if ($mode === 'subscribe' && hash_equals($this->verifyToken, $token)) {
            echo $challenge;
            exit;
        }
        http_response_code(403);
        exit('Forbidden');
    }
}
