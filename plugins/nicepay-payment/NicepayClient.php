<?php

declare(strict_types=1);

final class NicepayClient
{
    public function __construct(
        private string $registrationBaseUrl,
        private string $checkoutBaseUrl,
        private string $merchantId,
        private string $merchantKey,
    ) {
    }

    public function createPaymentLink(array $payload): ?array
    {
        $timestamp = (string)($payload['timestamp'] ?? '');
        $referenceNo = (string)($payload['referenceno'] ?? '');
        $amount = (string)($payload['amt'] ?? '');
        if ($timestamp === '' || $referenceNo === '' || $amount === '') {
            return null;
        }

        $payload['imid'] = $this->merchantId;
        $payload['merchanttoken'] = $this->generateMerchantToken($timestamp, $referenceNo, $amount);

        $response = $this->postForm(
            rtrim($this->registrationBaseUrl, '/') . '/nicepay/direct/v2/registration',
            $payload
        );

        if (!is_array($response)) {
            return null;
        }

        $resultCode = (string)($response['resultCd'] ?? $response['resultcd'] ?? '');
        if ($resultCode !== '' && $resultCode !== '0000') {
            return [
                'payment_url' => '',
                'reference_id' => '',
                'raw' => $response,
                'error' => (string)($response['resultMsg'] ?? $response['resultmsg'] ?? 'Unknown Nicepay error'),
            ];
        }

        $txId = (string)($response['txId'] ?? $response['txid'] ?? '');
        if ($txId === '') {
            return null;
        }

        return [
            'payment_url' => $this->buildCheckoutUrl($txId),
            'reference_id' => $txId,
            'raw' => $response,
        ];
    }

    public function verifyCallbackToken(?string $received, string $expected): bool
    {
        if ($expected === '') {
            return true;
        }

        return hash_equals($expected, (string)$received);
    }

    public function generateMerchantToken(string $timestamp, string $referenceNo, string $amount): string
    {
        return hash('sha256', $timestamp . $this->merchantId . $referenceNo . $amount . $this->merchantKey);
    }

    private function buildCheckoutUrl(string $txId): string
    {
        $separator = str_contains($this->checkoutBaseUrl, '?') ? '&' : '?';
        return rtrim($this->checkoutBaseUrl, '&?') . $separator . 'txid=' . rawurlencode($txId);
    }

    private function postForm(string $url, array $payload): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        if (!is_string($body) || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        parse_str($body, $formDecoded);
        return is_array($formDecoded) && $formDecoded !== [] ? $formDecoded : null;
    }
}
