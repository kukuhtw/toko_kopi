<?php

/**
 * Klien minimalis untuk Midtrans Snap API.
 * Tidak butuh SDK resmi — hanya cURL.
 */
class MidtransClient
{
    private const SNAP_SANDBOX    = 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    private const SNAP_PRODUCTION = 'https://app.midtrans.com/snap/v1/transactions';
    private string $serverKey;
    private bool $isProduction;

    public function __construct(string $serverKey, bool $isProduction = false)
    {
        $this->serverKey = $serverKey;
        $this->isProduction = $isProduction;
    }

    /**
     * Buat transaksi Snap dan kembalikan redirect_url pembayaran.
     * Kembalikan null jika gagal.
     */
    public function createSnap(array $params): ?string
    {
        $url  = $this->isProduction ? self::SNAP_PRODUCTION : self::SNAP_SANDBOX;
        $auth = base64_encode($this->serverKey . ':');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . $auth,
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("[MidtransClient] cURL error: {$curlErr}");
            return null;
        }

        if ($httpCode !== 201) {
            error_log("[MidtransClient] HTTP {$httpCode}: {$response}");
            return null;
        }

        $data = json_decode((string)$response, true);
        return is_string($data['redirect_url'] ?? null) ? $data['redirect_url'] : null;
    }

    /**
     * Verifikasi signature_key dari payload notifikasi Midtrans.
     * Formula: SHA512(order_id + status_code + gross_amount + server_key)
     */
    public function verifyNotification(array $payload): bool
    {
        $orderId    = (string)($payload['order_id']        ?? '');
        $statusCode = (string)($payload['status_code']     ?? '');
        $gross      = (string)($payload['gross_amount']    ?? '');
        $received   = (string)($payload['signature_key']   ?? '');

        $expected = hash('sha512', $orderId . $statusCode . $gross . $this->serverKey);

        return hash_equals($expected, $received);
    }
}
