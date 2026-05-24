<?php

declare(strict_types=1);

final class GoSendDeliveryClient
{
    public function __construct(private GoSendDeliveryRepository $repo)
    {
    }

    public function getConfig(int $branchId): array
    {
        $mode = $this->repo->getGlobalSetting('connection_mode', 'mock');
        $baseUrl = $this->repo->getBranchSetting(
            $branchId,
            'base_url',
            $mode === 'mock' ? '' : 'https://ecommercetools-integration.gojek.com'
        );

        return [
            'mode' => $mode,
            'base_url' => $baseUrl,
            'client_id' => $this->repo->getBranchSetting($branchId, 'client_id'),
            'pass_key' => $this->repo->getBranchSetting($branchId, 'pass_key'),
            'service_type' => $this->repo->getBranchSetting($branchId, 'service_type', 'instant'),
            'timeout_seconds' => (int)$this->repo->getGlobalSetting('timeout_seconds', '15'),
            'verify_ssl' => $this->repo->getGlobalSetting('verify_ssl', '1') === '1',
            'tracking_base_url' => $this->repo->getBranchSetting($branchId, 'tracking_base_url', 'https://gojek.com/gosend'),
            'webhook_secret' => $this->repo->getBranchSetting($branchId, 'webhook_secret'),
            'auth_mode' => $this->repo->getBranchSetting($branchId, 'auth_mode', 'header_pair'),
            'client_id_header' => $this->repo->getBranchSetting($branchId, 'client_id_header', 'Client-ID'),
            'pass_key_header' => $this->repo->getBranchSetting($branchId, 'pass_key_header', 'Pass-Key'),
            'booking_path' => $this->repo->getBranchSetting($branchId, 'booking_path', '/api/v1/bookings'),
            'booking_method' => strtoupper($this->repo->getBranchSetting($branchId, 'booking_method', 'POST')),
            'estimate_path' => $this->repo->getBranchSetting($branchId, 'estimate_path', '/api/v1/bookings/estimate'),
            'estimate_method' => strtoupper($this->repo->getBranchSetting($branchId, 'estimate_method', 'POST')),
            'pickup_path' => $this->repo->getBranchSetting($branchId, 'pickup_path', '/api/v1/bookings/{external_ref}/pickup'),
            'pickup_method' => strtoupper($this->repo->getBranchSetting($branchId, 'pickup_method', 'POST')),
            'status_path' => $this->repo->getBranchSetting($branchId, 'status_path', '/api/v1/bookings/{external_ref}'),
            'status_method' => strtoupper($this->repo->getBranchSetting($branchId, 'status_method', 'GET')),
            'cancel_path' => $this->repo->getBranchSetting($branchId, 'cancel_path', '/api/v1/bookings/{external_ref}/cancel'),
            'cancel_method' => strtoupper($this->repo->getBranchSetting($branchId, 'cancel_method', 'POST')),
            'merchant_key' => $this->repo->getBranchSetting($branchId, 'merchant_key', ''),
            'shop_id' => $this->repo->getBranchSetting($branchId, 'shop_id', ''),
            'use_json_body' => $this->repo->getBranchSetting($branchId, 'use_json_body', '1') === '1',
            'extra_headers' => $this->parseExtraHeaders($this->repo->getBranchSetting($branchId, 'extra_headers', '')),
        ];
    }

    public function createDeliveryOrder(int $branchId, array $payload): array
    {
        $config = $this->getConfig($branchId);
        if ($config['mode'] === 'mock') {
            return $this->mockCreateOrder($config, $payload);
        }

        $result = $this->sendRequest($branchId, [
            'method' => $config['booking_method'],
            'path' => $config['booking_path'],
            'body' => $payload,
        ]);

        if (!$result['success']) {
            throw new RuntimeException($result['error'] ?: 'Create booking GoSend gagal.');
        }

        return $this->normalizeBookingResponse($config, $payload, $result);
    }

    public function estimate(int $branchId, array $payload): array
    {
        $config = $this->getConfig($branchId);
        if ($config['mode'] === 'mock') {
            return $this->mockEstimate($config, $payload);
        }

        $result = $this->sendRequest($branchId, [
            'method' => $config['estimate_method'],
            'path' => $config['estimate_path'],
            'body' => $payload,
        ]);

        if (!$result['success']) {
            throw new RuntimeException($result['error'] ?: 'Estimate GoSend gagal.');
        }

        $data = is_array($result['data']) ? $result['data'] : [];
        return [
            'success' => true,
            'mode' => $config['mode'],
            'service_type' => (string)($data['service_type'] ?? $payload['service_type'] ?? $config['service_type']),
            'estimated_price' => (float)($data['estimated_price'] ?? $data['price'] ?? $data['amount'] ?? 0),
            'distance_km' => (float)($data['distance_km'] ?? $data['distance'] ?? $this->estimateDistanceKm($payload)),
            'request_preview' => $result['request_preview'],
            'response_preview' => $result['response_preview'],
        ];
    }

    public function requestPickup(int $branchId, array $payload, string $externalRef): array
    {
        $config = $this->getConfig($branchId);
        if ($config['mode'] === 'mock') {
            return [
                'success' => true,
                'mode' => 'mock',
                'external_ref' => $externalRef,
                'delivery_status' => 'confirmed',
                'note' => 'Mock pickup trigger GoSend berhasil dijalankan.',
                'tracking_url' => rtrim((string)$config['tracking_base_url'], '/') . '/track/' . rawurlencode($externalRef),
            ];
        }

        $result = $this->sendRequest($branchId, [
            'method' => $config['pickup_method'],
            'path' => $this->replacePathTokens($config['pickup_path'], $payload, $externalRef),
            'body' => $payload,
        ]);

        if (!$result['success']) {
            throw new RuntimeException($result['error'] ?: 'Pickup trigger GoSend gagal.');
        }

        $data = is_array($result['data']) ? $result['data'] : [];
        return [
            'success' => true,
            'mode' => $config['mode'],
            'external_ref' => (string)($data['external_ref'] ?? $data['booking_id'] ?? $externalRef),
            'delivery_status' => (string)($data['delivery_status'] ?? $data['status'] ?? 'confirmed'),
            'tracking_url' => (string)($data['tracking_url'] ?? (rtrim((string)$config['tracking_base_url'], '/') . '/track/' . rawurlencode($externalRef))),
            'note' => (string)($data['message'] ?? 'Pickup GoSend berhasil ditrigger.'),
            'request_preview' => $result['request_preview'],
            'response_preview' => $result['response_preview'],
        ];
    }

    public function fetchBookingStatus(int $branchId, array $payload, string $externalRef): array
    {
        $config = $this->getConfig($branchId);
        if ($config['mode'] === 'mock') {
            return [
                'success' => true,
                'mode' => 'mock',
                'external_ref' => $externalRef,
                'delivery_status' => 'searching_driver',
                'tracking_url' => rtrim((string)$config['tracking_base_url'], '/') . '/track/' . rawurlencode($externalRef),
                'note' => 'Mock status GoSend.',
            ];
        }

        $result = $this->sendRequest($branchId, [
            'method' => $config['status_method'],
            'path' => $this->replacePathTokens($config['status_path'], $payload, $externalRef),
        ]);

        if (!$result['success']) {
            throw new RuntimeException($result['error'] ?: 'Lookup status GoSend gagal.');
        }

        $data = is_array($result['data']) ? $result['data'] : [];
        return [
            'success' => true,
            'mode' => $config['mode'],
            'external_ref' => (string)($data['external_ref'] ?? $data['booking_id'] ?? $externalRef),
            'delivery_status' => (string)($data['delivery_status'] ?? $data['status'] ?? 'unknown'),
            'tracking_url' => (string)($data['tracking_url'] ?? ''),
            'note' => (string)($data['message'] ?? 'Status booking GoSend diambil dari API live.'),
            'request_preview' => $result['request_preview'],
            'response_preview' => $result['response_preview'],
        ];
    }

    public function verifyWebhookSignature(int $branchId, string $rawBody, ?string $signature): bool
    {
        $secret = $this->repo->getBranchSetting($branchId, 'webhook_secret');
        if ($secret === '') {
            return true;
        }
        if (!$signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals(strtolower($expected), strtolower(trim($signature)));
    }

    public function sendRequest(int $branchId, array $request): array
    {
        $config = $this->getConfig($branchId);
        if (!function_exists('curl_init')) {
            return $this->failedResult(0, 'cURL extension tidak tersedia.', $request);
        }
        if ($config['base_url'] === '') {
            return $this->failedResult(0, 'Base URL GoSend belum diisi.', $request);
        }
        if ($config['client_id'] === '' || $config['pass_key'] === '') {
            return $this->failedResult(0, 'Konfigurasi GoSend belum lengkap. Isi client ID dan pass key.', $request);
        }

        $method = strtoupper((string)($request['method'] ?? 'GET'));
        $path = (string)($request['path'] ?? '/');
        $url = $this->buildUrl($config['base_url'], $path);
        $query = is_array($request['query'] ?? null) ? $request['query'] : [];
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $bodyPayload = $request['body'] ?? null;
        $headers = $this->buildHeaders($config);
        foreach ((array)($request['headers'] ?? []) as $header) {
            $headers[] = (string)$header;
        }

        $body = null;
        if ($bodyPayload !== null) {
            $body = $config['use_json_body']
                ? json_encode($bodyPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : http_build_query(is_array($bodyPayload) ? $bodyPayload : []);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $config['timeout_seconds'],
            CURLOPT_SSL_VERIFYPEER => $config['verify_ssl'],
            CURLOPT_HEADER => false,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $decoded = $this->decodeBody((string)$rawBody);
        $requestPreview = $this->sanitizeRequestPreview([
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $bodyPayload,
        ]);
        $responsePreview = $this->buildResponsePreview($status, $decoded, (string)$rawBody, $error);

        if ($error !== '') {
            return [
                'success' => false,
                'status' => 0,
                'data' => null,
                'error' => $error,
                'request_preview' => $requestPreview,
                'response_preview' => $responsePreview,
            ];
        }

        return [
            'success' => $status >= 200 && $status < 300,
            'status' => $status,
            'data' => $decoded,
            'error' => $status >= 200 && $status < 300 ? '' : $this->extractErrorMessage($decoded, (string)$rawBody),
            'request_preview' => $requestPreview,
            'response_preview' => $responsePreview,
        ];
    }

    private function buildHeaders(array $config): array
    {
        $headers = [
            'Accept: application/json',
            ($config['use_json_body'] ? 'Content-Type: application/json' : 'Content-Type: application/x-www-form-urlencoded'),
            'User-Agent: KopiBot-GoSend-Connector/1.0',
        ];

        $authMode = strtolower((string)($config['auth_mode'] ?? 'header_pair'));
        if ($authMode === 'basic') {
            $headers[] = 'Authorization: Basic ' . base64_encode($config['client_id'] . ':' . $config['pass_key']);
        } elseif ($authMode === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $config['pass_key'];
        } else {
            $headers[] = trim((string)$config['client_id_header']) . ': ' . $config['client_id'];
            $headers[] = trim((string)$config['pass_key_header']) . ': ' . $config['pass_key'];
        }

        if ($config['merchant_key'] !== '') {
            $headers[] = 'X-Merchant-Key: ' . $config['merchant_key'];
        }
        if ($config['shop_id'] !== '') {
            $headers[] = 'X-Shop-Id: ' . $config['shop_id'];
        }
        foreach ((array)($config['extra_headers'] ?? []) as $header) {
            $headers[] = $header;
        }

        return $headers;
    }

    private function normalizeBookingResponse(array $config, array $payload, array $result): array
    {
        $data = is_array($result['data']) ? $result['data'] : [];
        $serviceType = (string)($data['service_type'] ?? $payload['service_type'] ?? $config['service_type']);
        $externalRef = (string)($data['external_ref'] ?? $data['booking_id'] ?? $data['order_no'] ?? '');
        if ($externalRef === '') {
            $externalRef = 'GK-' . strtoupper(substr($serviceType, 0, 2)) . '-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        return [
            'success' => true,
            'mode' => $config['mode'],
            'external_ref' => $externalRef,
            'tracking_url' => (string)($data['tracking_url'] ?? (rtrim((string)$config['tracking_base_url'], '/') . '/track/' . rawurlencode($externalRef))),
            'service_type' => $serviceType,
            'delivery_status' => (string)($data['delivery_status'] ?? $data['status'] ?? 'searching_driver'),
            'note' => (string)($data['message'] ?? 'Booking GoSend berhasil dibuat dari endpoint live.'),
            'request_preview' => $result['request_preview'],
            'response_preview' => $result['response_preview'],
        ];
    }

    private function mockCreateOrder(array $config, array $payload): array
    {
        $orderNumber = (string)($payload['order_number'] ?? ('ORD-' . date('YmdHis')));
        $serviceType = (string)($payload['service_type'] ?? $config['service_type'] ?? 'instant');
        $externalRef = 'GK-' . strtoupper(substr($serviceType, 0, 2)) . '-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $trackingBase = rtrim((string)($config['tracking_base_url'] ?? 'https://gojek.com/gosend'), '/');

        return [
            'success' => true,
            'mode' => $config['mode'],
            'external_ref' => $externalRef,
            'tracking_url' => $trackingBase . '/track/' . rawurlencode($externalRef),
            'service_type' => $serviceType,
            'delivery_status' => 'searching_driver',
            'note' => $config['mode'] === 'staging'
                ? 'Simulasi staging GoSend siap dilanjutkan ke endpoint resmi partner.'
                : 'Mock booking GoSend berhasil dibuat untuk pengujian internal.',
            'echo_order_number' => $orderNumber,
        ];
    }

    private function mockEstimate(array $config, array $payload): array
    {
        $distanceKm = $this->estimateDistanceKm($payload);
        $base = match (strtolower((string)($payload['service_type'] ?? $config['service_type'] ?? 'instant'))) {
            'sameday' => 18000,
            'car' => 35000,
            default => 12000,
        };
        $price = $base + (int)max(0, ceil(max(0, $distanceKm - 3)) * 2500);

        return [
            'success' => true,
            'service_type' => (string)($payload['service_type'] ?? $config['service_type']),
            'estimated_price' => $price,
            'distance_km' => $distanceKm,
            'mode' => $config['mode'],
        ];
    }

    private function replacePathTokens(string $path, array $payload, string $externalRef): string
    {
        $orderNumber = (string)($payload['order_number'] ?? '');
        return strtr($path, [
            '{external_ref}' => rawurlencode($externalRef),
            '{booking_id}' => rawurlencode($externalRef),
            '{order_number}' => rawurlencode($orderNumber),
        ]);
    }

    private function buildUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim(trim($path) !== '' ? trim($path) : '/', '/');
    }

    private function parseExtraHeaders(string $raw): array
    {
        $headers = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && str_contains($line, ':')) {
                $headers[] = $line;
            }
        }
        return $headers;
    }

    private function decodeBody(string $body): mixed
    {
        $decoded = json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }

    private function extractErrorMessage(mixed $decoded, string $rawBody): string
    {
        if (is_array($decoded)) {
            foreach (['message', 'error', 'error_description', 'detail'] as $key) {
                if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }
        }

        $rawBody = trim($rawBody);
        return $rawBody !== '' ? mb_substr($rawBody, 0, 300) : 'Unknown GoSend API error.';
    }

    private function buildResponsePreview(int $status, mixed $decoded, string $rawBody, string $error): array
    {
        return [
            'http_status' => $status,
            'error' => $error !== '' ? $error : null,
            'body' => is_array($decoded) ? $decoded : mb_substr(trim($rawBody), 0, 1000),
        ];
    }

    private function sanitizeRequestPreview(array $request): array
    {
        $headers = array_map(static function ($header): string {
            $header = (string)$header;
            $lower = strtolower($header);
            if (str_starts_with($lower, 'authorization:') || str_contains($lower, 'pass-key') || str_contains($lower, 'client-id')) {
                [$name] = explode(':', $header, 2);
                return trim($name) . ': [masked]';
            }
            return $header;
        }, (array)($request['headers'] ?? []));

        return [
            'method' => (string)($request['method'] ?? 'GET'),
            'url' => (string)($request['url'] ?? ''),
            'headers' => $headers,
            'body' => $request['body'] ?? null,
        ];
    }

    private function failedResult(int $status, string $error, array $request, array $responsePreview = []): array
    {
        return [
            'success' => false,
            'status' => $status,
            'data' => null,
            'error' => $error,
            'request_preview' => $this->sanitizeRequestPreview($request),
            'response_preview' => $responsePreview === [] ? ['http_status' => $status, 'error' => $error] : $responsePreview,
        ];
    }

    private function estimateDistanceKm(array $payload): float
    {
        $originLat = (float)($payload['origin_latitude'] ?? 0);
        $originLng = (float)($payload['origin_longitude'] ?? 0);
        $destLat = (float)($payload['destination_latitude'] ?? 0);
        $destLng = (float)($payload['destination_longitude'] ?? 0);
        if (!$originLat || !$originLng || !$destLat || !$destLng) {
            return 3.0;
        }

        $theta = deg2rad($originLng - $destLng);
        $dist = sin(deg2rad($originLat)) * sin(deg2rad($destLat))
            + cos(deg2rad($originLat)) * cos(deg2rad($destLat)) * cos($theta);
        $dist = acos(min(1, max(-1, $dist)));
        return round(rad2deg($dist) * 111.13384, 2);
    }
}
