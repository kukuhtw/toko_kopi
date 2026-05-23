<?php

declare(strict_types=1);

final class IPaymuClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $va,
        private string $endpointPath = '/payment',
    ) {
    }

    public function createPaymentLink(array $payload): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->endpointPath, '/');
        $headers = [
            'Accept: application/json',
            'apikey: ' . $this->apiKey,
            'va: ' . $this->va,
        ];

        $response = $this->postJson($url, $payload, array_merge($headers, ['Content-Type: application/json']));
        $normalized = $this->normalizeResponse($response);
        if ($normalized !== null) {
            return $normalized;
        }

        $response = $this->postForm($url, $payload, array_merge($headers, ['Content-Type: application/x-www-form-urlencoded']));
        return $this->normalizeResponse($response);
    }

    public function verifyCallbackToken(?string $received, string $expected): bool
    {
        if ($expected === '') {
            return true;
        }

        return hash_equals($expected, (string)$received);
    }

    private function normalizeResponse(?array $response): ?array
    {
        if (!is_array($response)) {
            return null;
        }

        $data = is_array($response['Data'] ?? null)
            ? $response['Data']
            : (is_array($response['data'] ?? null) ? $response['data'] : []);

        $paymentUrl = (string)($data['Url'] ?? $data['url'] ?? $data['SessionUrl'] ?? $response['Url'] ?? $response['url'] ?? '');
        $reference = (string)($data['ReferenceId'] ?? $data['referenceId'] ?? $data['TransactionId'] ?? $data['SessionID'] ?? $response['TransactionId'] ?? $response['SessionID'] ?? '');

        if ($paymentUrl === '') {
            return null;
        }

        return [
            'payment_url' => $paymentUrl,
            'reference_id' => $reference,
            'raw' => $response,
        ];
    }

    private function postJson(string $url, array $payload, array $headers): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        return $this->decodeResponseBody($body);
    }

    private function postForm(string $url, array $payload, array $headers): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        return $this->decodeResponseBody($body);
    }

    private function decodeResponseBody(mixed $body): ?array
    {
        if (!is_string($body) || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        parse_str($body, $parsed);
        return is_array($parsed) && $parsed !== [] ? $parsed : null;
    }
}
