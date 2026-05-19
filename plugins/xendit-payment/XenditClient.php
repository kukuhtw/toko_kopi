<?php

/**
 * Minimal client for Xendit Payment Link / Invoice API.
 * Uses plain cURL instead of the official SDK.
 */
final class XenditClient
{
    private const BASE_URL = 'https://api.xendit.co';

    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * Create a Xendit invoice and return its core response payload.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function createInvoice(array $params): ?array
    {
        $response = $this->request('POST', '/v2/invoices', $params);
        if (!$response['ok']) {
            error_log('[XenditClient] createInvoice failed: HTTP ' . $response['status'] . ' ' . $response['body']);
            return null;
        }

        $data = json_decode((string)$response['body'], true);
        return is_array($data) ? $data : null;
    }

    public function verifyWebhookToken(?string $receivedToken, string $expectedToken): bool
    {
        $received = trim((string)$receivedToken);
        $expected = trim($expectedToken);

        if ($received === '' || $expected === '') {
            return false;
        }

        return hash_equals($expected, $received);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{ok:bool,status:int,body:string}
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $ch = curl_init(self::BASE_URL . $path);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->secretKey . ':'),
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            error_log('[XenditClient] cURL error: ' . $error);
            return ['ok' => false, 'status' => 0, 'body' => $error];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => (string)$body,
        ];
    }
}
