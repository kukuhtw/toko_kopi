<?php

declare(strict_types=1);

/**
 * Client minimal untuk transactional email KIRIM.EMAIL.
 * Menggunakan Basic Auth sesuai dokumentasi openapi/landing page.
 */
class KirimEmailMailer
{
    public static function send(array $cfg, string $to, string $subject, string $body): bool
    {
        $baseUrl   = rtrim((string)($cfg['base_url'] ?? 'https://smtp-app.kirim.email'), '/');
        $domain    = trim((string)($cfg['domain'] ?? ''));
        $username  = trim((string)($cfg['username'] ?? ''));
        $token     = (string)($cfg['token'] ?? '');
        $fromEmail = trim((string)($cfg['from_email'] ?? ''));
        $fromName  = trim((string)($cfg['from_name'] ?? 'KopiBot'));

        if ($domain === '' || $username === '' || $token === '' || $fromEmail === '' || $to === '') {
            error_log('[KirimEmailMailer] Konfigurasi belum lengkap.');
            return false;
        }

        if (!function_exists('curl_init')) {
            error_log('[KirimEmailMailer] cURL extension tidak tersedia.');
            return false;
        }

        $payload = [
            'from'      => $fromEmail,
            'from_name' => $fromName,
            'to'        => $to,
            'subject'   => $subject,
            'text'      => $body,
        ];

        $endpoints = [
            $baseUrl . '/api/domains/' . rawurlencode($domain) . '/message',
            $baseUrl . '/api/domains/' . rawurlencode($domain) . '/messages',
        ];

        foreach ($endpoints as $endpoint) {
            $result = self::dispatch($endpoint, $username, $token, $payload);
            if ($result['ok']) {
                return true;
            }

            if ($result['http_code'] !== 404) {
                error_log('[KirimEmailMailer] ' . $result['error']);
                return false;
            }
        }

        error_log('[KirimEmailMailer] Endpoint KIRIM.EMAIL tidak ditemukan.');
        return false;
    }

    private static function dispatch(string $url, string $username, string $token, array $payload): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'http_code' => 0, 'error' => 'Gagal inisialisasi cURL.'];
        }

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERPWD => $username . ':' . $token,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $parts  = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            },
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'http_code' => $httpCode, 'error' => 'Request gagal: ' . $curlErr];
        }

        $decoded = json_decode($response, true);
        $success = is_array($decoded) && (
            ($decoded['success'] ?? false) === true
            || !empty($decoded['message_id'])
        );

        if ($httpCode >= 200 && $httpCode < 300 && $success) {
            return ['ok' => true, 'http_code' => $httpCode, 'error' => ''];
        }

        $error = 'HTTP ' . $httpCode;
        if (isset($decoded['error']) && is_string($decoded['error']) && $decoded['error'] !== '') {
            $error .= ' - ' . $decoded['error'];
        } elseif (isset($decoded['message']) && is_string($decoded['message']) && $decoded['message'] !== '') {
            $error .= ' - ' . $decoded['message'];
        } elseif (is_string($response) && trim($response) !== '') {
            $error .= ' - ' . trim($response);
        }

        if (isset($responseHeaders['retry-after'])) {
            $error .= ' (retry after ' . $responseHeaders['retry-after'] . 's)';
        }

        return ['ok' => false, 'http_code' => $httpCode, 'error' => $error];
    }
}
