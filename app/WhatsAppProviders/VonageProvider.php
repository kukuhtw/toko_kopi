<?php

declare(strict_types=1);

namespace App\WhatsAppProviders;

class VonageProvider implements WhatsAppProviderInterface
{
    private string $apiKey;
    private string $apiSecret;
    private string $fromNumber;
    private string $sendUrl = 'https://api.nexmo.com/v1/messages';

    public function __construct(array $config)
    {
        $this->apiKey     = (string)($config['api_key']    ?? '');
        $this->apiSecret  = (string)($config['api_secret'] ?? '');
        $this->fromNumber = $this->normalizeE164((string)($config['wa_number'] ?? ''));
    }

    public function getName(): string
    {
        return 'Vonage';
    }

    public function sendMessage(string $to, string $message): bool
    {
        if ($this->apiKey === '' || $this->apiSecret === '' || $this->fromNumber === '') {
            error_log('[Vonage] Missing apiKey, apiSecret, or fromNumber');
            return false;
        }

        $body = json_encode([
            'message_type' => 'text',
            'text'         => $message,
            'to'           => $this->normalizeE164($to),
            'from'         => $this->fromNumber,
            'channel'      => 'whatsapp',
        ]);

        $credentials = base64_encode("{$this->apiKey}:{$this->apiSecret}");

        $ch = curl_init($this->sendUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Basic {$credentials}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT    => 12,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Vonage returns 202 Accepted on success
        if ($response === false || $httpCode !== 202) {
            error_log("[Vonage] Send failed. HTTP {$httpCode}: {$response}");
            return false;
        }

        $data = json_decode((string)$response, true);
        return isset($data['message_uuid']) && $data['message_uuid'] !== '';
    }

    public function parseWebhook(array $payload): ?array
    {
        // Vonage inbound WhatsApp message
        $channel     = (string)($payload['channel']      ?? '');
        $msgType     = (string)($payload['message_type'] ?? '');
        $from        = (string)($payload['from']         ?? '');

        if ($channel !== 'whatsapp' || $from === '') {
            return null;
        }

        $message = '';
        if ($msgType === 'text') {
            $message = (string)($payload['text'] ?? '');
        }

        if ($message === '') {
            return null;
        }

        return [
            'from'    => $from,
            'message' => $message,
            'raw'     => $payload,
        ];
    }

    public function verifyWebhook(array $headers, string $rawBody, array $payload = [], array $server = []): bool
    {
        if ($this->apiSecret === '') {
            return true;
        }

        // Vonage signs the body with HMAC-SHA256 using the api_secret
        // Header: X-Nexmo-Signature (format: sha256=<hex>)
        $header = $this->headerValue($headers, 'X-Nexmo-Signature')
               ?? $this->headerValue($headers, 'X-Vonage-Signature');

        if ($header === null) {
            // Vonage sandbox/inbound messages may omit the signature header
            return true;
        }

        // Strip "sha256=" prefix if present
        $received = preg_replace('/^sha256=/i', '', $header) ?? $header;
        $expected = hash_hmac('sha256', $rawBody, $this->apiSecret);

        return hash_equals($expected, $received);
    }

    private function normalizeE164(string $number): string
    {
        $number = preg_replace('/\D/', '', $number) ?? $number;
        return ltrim($number, '+');
    }

    private function headerValue(array $headers, string $target): ?string
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string)$name, $target) === 0) {
                return is_array($value) ? (string)($value[0] ?? '') : (string)$value;
            }
        }
        return null;
    }
}
