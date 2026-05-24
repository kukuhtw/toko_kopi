<?php

declare(strict_types=1);

final class MokaConnectClient
{
    private MokaConnectRepository $repo;

    public function __construct(?MokaConnectRepository $repo = null)
    {
        $this->repo = $repo ?? new MokaConnectRepository();
    }

    public function probe(int $branchId): array
    {
        $config = $this->getBranchConfig($branchId);
        if (!$config['has_credentials']) {
            return [
                'success' => false,
                'message' => 'Credential Moka belum lengkap.',
                'request' => ['config' => $this->maskConfig($config)],
            ];
        }

        $result = $this->sendRequest($branchId, $this->buildOutletsPullRequest($branchId));
        $message = $result['success']
            ? 'Koneksi Moka berhasil diuji ke endpoint outlet.'
            : ('Tes koneksi gagal: ' . ($result['error'] ?: ('HTTP ' . (string)$result['status'])));

        return [
            'success' => $result['success'],
            'message' => $message,
            'status' => $result['status'],
            'request' => $result['request_preview'],
            'response' => $result['response_preview'],
        ];
    }

    public function sendOrderUpsert(int $branchId, array $payload): array
    {
        return $this->sendRequest($branchId, $this->buildOrderUpsertRequest($branchId, $payload));
    }

    public function pullProducts(int $branchId): array
    {
        return $this->sendRequest($branchId, $this->buildProductsPullRequest($branchId));
    }

    public function buildOrderUpsertRequest(int $branchId, array $order): array
    {
        $config = $this->getBranchConfig($branchId);
        return [
            'method' => 'POST',
            'url' => $this->buildUrl($config['base_url'], $config['orders_path']),
            'headers' => [],
            'body' => array_merge([
                'merchant_id' => $config['merchant_id'],
                'outlet_id' => $config['outlet_id'],
                'source' => 'kopibot',
            ], $order),
        ];
    }

    public function buildProductsPullRequest(int $branchId): array
    {
        $config = $this->getBranchConfig($branchId);
        return [
            'method' => 'GET',
            'url' => $this->buildUrl($config['base_url'], $config['products_path']),
            'headers' => [],
            'query' => [
                'merchant_id' => $config['merchant_id'],
                'outlet_id' => $config['outlet_id'],
            ],
        ];
    }

    public function buildCustomersPullRequest(int $branchId): array
    {
        $config = $this->getBranchConfig($branchId);
        return [
            'method' => 'GET',
            'url' => $this->buildUrl($config['base_url'], $config['customers_path']),
            'headers' => [],
            'query' => [
                'merchant_id' => $config['merchant_id'],
                'outlet_id' => $config['outlet_id'],
            ],
        ];
    }

    public function buildOutletsPullRequest(int $branchId): array
    {
        $config = $this->getBranchConfig($branchId);
        return [
            'method' => 'GET',
            'url' => $this->buildUrl($config['base_url'], $config['outlets_path']),
            'headers' => [],
            'query' => [
                'merchant_id' => $config['merchant_id'],
            ],
        ];
    }

    public function getBranchConfig(int $branchId): array
    {
        $mode = $this->repo->getGlobalSetting('connection_mode', 'sandbox');
        $defaultBaseUrl = $mode === 'production'
            ? 'https://api.mokapos.com'
            : 'https://api-staging.mokapos.com';
        $authMode = $this->repo->getBranchSetting($branchId, 'auth_mode', 'basic_api_key');
        $apiKey = $this->repo->getBranchSetting($branchId, 'api_key');
        $clientId = $this->repo->getBranchSetting($branchId, 'client_id');
        $clientSecret = $this->repo->getBranchSetting($branchId, 'client_secret');
        $accessToken = $this->repo->getBranchSetting($branchId, 'access_token');

        $hasCredentials = $authMode === 'basic_api_key'
            ? $apiKey !== ''
            : (($clientId !== '' && $clientSecret !== '') || $accessToken !== '');

        return [
            'base_url' => $this->repo->getBranchSetting($branchId, 'base_url', $defaultBaseUrl),
            'auth_mode' => $authMode,
            'api_key' => $apiKey,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'access_token' => $accessToken,
            'merchant_id' => $this->repo->getBranchSetting($branchId, 'merchant_id'),
            'outlet_id' => $this->repo->getBranchSetting($branchId, 'outlet_id'),
            'webhook_secret' => $this->repo->getBranchSetting($branchId, 'webhook_secret'),
            'orders_path' => $this->repo->getBranchSetting($branchId, 'orders_path', '/v1/orders'),
            'products_path' => $this->repo->getBranchSetting($branchId, 'products_path', '/v1/products'),
            'customers_path' => $this->repo->getBranchSetting($branchId, 'customers_path', '/v1/customers'),
            'outlets_path' => $this->repo->getBranchSetting($branchId, 'outlets_path', '/v1/outlets'),
            'token_path' => $this->repo->getBranchSetting($branchId, 'token_path', '/oauth/token'),
            'timeout_seconds' => (int)$this->repo->getGlobalSetting('timeout_seconds', '15'),
            'verify_ssl' => $this->repo->getGlobalSetting('verify_ssl', '1') === '1',
            'live_order_push' => $this->repo->getBranchSetting($branchId, 'live_order_push', '1') === '1',
            'live_catalog_pull' => $this->repo->getBranchSetting($branchId, 'live_catalog_pull', '1') === '1',
            'max_retries' => max(0, (int)$this->repo->getBranchSetting($branchId, 'max_retries', '3')),
            'retry_delay_seconds' => max(30, (int)$this->repo->getBranchSetting($branchId, 'retry_delay_seconds', '300')),
            'has_credentials' => $hasCredentials,
        ];
    }

    public function sendRequest(int $branchId, array $request): array
    {
        $config = $this->getBranchConfig($branchId);
        if (!function_exists('curl_init')) {
            return $this->failedResult(0, 'cURL extension tidak tersedia.', $request);
        }

        $accessToken = null;
        if ($config['auth_mode'] === 'oauth2_client' && $config['access_token'] === '') {
            $tokenResult = $this->fetchAccessToken($config);
            if (!$tokenResult['success']) {
                return $this->failedResult(
                    (int)($tokenResult['status'] ?? 0),
                    (string)($tokenResult['error'] ?? 'Gagal mengambil access token.'),
                    $request,
                    $tokenResult['response_preview'] ?? []
                );
            }
            $accessToken = (string)($tokenResult['access_token'] ?? '');
        }

        $method = strtoupper((string)($request['method'] ?? 'GET'));
        $url = (string)($request['url'] ?? '');
        $query = is_array($request['query'] ?? null) ? $request['query'] : [];
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = $this->buildHeaders($config, $accessToken);
        foreach ((array)($request['headers'] ?? []) as $header) {
            $headers[] = (string)$header;
        }

        $ch = curl_init($url);
        $body = null;
        if (array_key_exists('body', $request)) {
            $body = json_encode($request['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

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
            'body' => $request['body'] ?? null,
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

    private function fetchAccessToken(array $config): array
    {
        if ($config['client_id'] === '' || $config['client_secret'] === '') {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'Client ID / Client Secret OAuth2 belum lengkap.',
                'response_preview' => [],
            ];
        }

        $url = $this->buildUrl($config['base_url'], $config['token_path']);
        $ch = curl_init($url);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret']),
            'User-Agent: KopiBot-Moka-Connector/1.0',
        ];
        $body = http_build_query(['grant_type' => 'client_credentials']);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $config['timeout_seconds'],
            CURLOPT_SSL_VERIFYPEER => $config['verify_ssl'],
        ]);

        $rawBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $decoded = $this->decodeBody((string)$rawBody);
        if ($error !== '') {
            return [
                'success' => false,
                'status' => 0,
                'error' => $error,
                'response_preview' => $this->buildResponsePreview(0, $decoded, (string)$rawBody, $error),
            ];
        }

        $token = is_array($decoded) ? (string)($decoded['access_token'] ?? '') : '';
        if ($status < 200 || $status >= 300 || $token === '') {
            return [
                'success' => false,
                'status' => $status,
                'error' => $this->extractErrorMessage($decoded, (string)$rawBody),
                'response_preview' => $this->buildResponsePreview($status, $decoded, (string)$rawBody, ''),
            ];
        }

        return [
            'success' => true,
            'status' => $status,
            'access_token' => $token,
            'response_preview' => $this->buildResponsePreview($status, $decoded, (string)$rawBody, ''),
        ];
    }

    private function buildHeaders(array $config, ?string $accessToken = null): array
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: KopiBot-Moka-Connector/1.0',
        ];

        if ($config['auth_mode'] === 'basic_api_key' && $config['api_key'] !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($config['api_key'] . ':');
        } elseif (($accessToken ?? '') !== '') {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        } elseif ($config['access_token'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $config['access_token'];
        }

        return $headers;
    }

    private function buildUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim(trim($path) !== '' ? trim($path) : '/v1/orders', '/');
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
        return $rawBody !== '' ? mb_substr($rawBody, 0, 300) : 'Unknown Moka API error.';
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
            if (stripos($header, 'Authorization:') === 0) {
                return 'Authorization: [masked]';
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

    private function maskConfig(array $config): array
    {
        foreach (['api_key', 'client_secret', 'access_token', 'webhook_secret'] as $secretKey) {
            if (($config[$secretKey] ?? '') !== '') {
                $config[$secretKey] = '[masked]';
            }
        }

        return $config;
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
}
