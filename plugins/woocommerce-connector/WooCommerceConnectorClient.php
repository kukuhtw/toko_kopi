<?php

declare(strict_types=1);

final class WooCommerceConnectorClient
{
    private WooCommerceConnectorRepository $repo;

    public function __construct(?WooCommerceConnectorRepository $repo = null)
    {
        $this->repo = $repo ?? new WooCommerceConnectorRepository();
    }

    public function probe(int $branchId): array
    {
        $config = $this->getBranchConfig($branchId);
        if (!$config['has_credentials']) {
            return [
                'success' => false,
                'message' => 'Credential WooCommerce belum lengkap.',
                'request' => ['config' => $this->maskConfig($config)],
            ];
        }

        $result = $this->sendRequest($branchId, [
            'method' => 'GET',
            'url' => $this->buildUrl($config['base_url'], $config['products_path']),
            'query' => ['per_page' => 1, 'page' => 1],
        ]);

        return [
            'success' => $result['success'],
            'message' => $result['success']
                ? 'Koneksi WooCommerce berhasil diuji ke endpoint products.'
                : ('Tes koneksi gagal: ' . ($result['error'] ?: ('HTTP ' . (string)$result['status']))),
            'status' => $result['status'],
            'request' => $result['request_preview'],
            'response' => $result['response_preview'],
        ];
    }

    public function pullCategories(int $branchId, int $page = 1, int $perPage = 100): array
    {
        $config = $this->getBranchConfig($branchId);
        return $this->sendRequest($branchId, [
            'method' => 'GET',
            'url' => $this->buildUrl($config['base_url'], $config['categories_path']),
            'query' => ['per_page' => $perPage, 'page' => $page],
        ]);
    }

    public function pullProducts(int $branchId, int $page = 1, int $perPage = 50): array
    {
        $config = $this->getBranchConfig($branchId);
        return $this->sendRequest($branchId, [
            'method' => 'GET',
            'url' => $this->buildUrl($config['base_url'], $config['products_path']),
            'query' => ['per_page' => $perPage, 'page' => $page, 'status' => 'publish'],
        ]);
    }

    public function pullVariations(int $branchId, int $productId, int $page = 1, int $perPage = 100): array
    {
        $config = $this->getBranchConfig($branchId);
        return $this->sendRequest($branchId, [
            'method' => 'GET',
            'url' => $this->buildUrl($config['base_url'], $config['products_path'] . '/' . $productId . '/variations'),
            'query' => ['per_page' => $perPage, 'page' => $page],
        ]);
    }

    public function createOrder(int $branchId, array $payload): array
    {
        $config = $this->getBranchConfig($branchId);
        return $this->sendRequest($branchId, [
            'method' => 'POST',
            'url' => $this->buildUrl($config['base_url'], $config['orders_path']),
            'body' => $payload,
        ]);
    }

    public function getBranchConfig(int $branchId): array
    {
        $mode = $this->repo->getGlobalSetting('connection_mode', 'sandbox');
        $storeUrl = rtrim($this->repo->getBranchSetting($branchId, 'store_url'), '/');
        $baseUrl = rtrim($this->repo->getBranchSetting($branchId, 'base_url'), '/');
        if ($baseUrl === '' && $storeUrl !== '') {
            $baseUrl = $storeUrl . '/wp-json/wc/v3';
        }

        $consumerKey = $this->repo->getBranchSetting($branchId, 'consumer_key');
        $consumerSecret = $this->repo->getBranchSetting($branchId, 'consumer_secret');

        return [
            'base_url' => $baseUrl,
            'store_url' => $storeUrl,
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
            'webhook_secret' => $this->repo->getBranchSetting($branchId, 'webhook_secret'),
            'products_path' => $this->repo->getBranchSetting($branchId, 'products_path', '/products'),
            'categories_path' => $this->repo->getBranchSetting($branchId, 'categories_path', '/products/categories'),
            'orders_path' => $this->repo->getBranchSetting($branchId, 'orders_path', '/orders'),
            'timeout_seconds' => (int)$this->repo->getGlobalSetting('timeout_seconds', '15'),
            'verify_ssl' => $this->repo->getGlobalSetting('verify_ssl', '1') === '1',
            'live_order_push' => $this->repo->getBranchSetting($branchId, 'live_order_push', '1') === '1',
            'live_catalog_pull' => $this->repo->getBranchSetting($branchId, 'live_catalog_pull', '1') === '1',
            'per_page' => max(1, min(100, (int)$this->repo->getGlobalSetting('batch_limit', '50'))),
            'has_credentials' => $baseUrl !== '' && $storeUrl !== '' && $consumerKey !== '' && $consumerSecret !== '',
            'mode' => $mode,
        ];
    }

    public function sendRequest(int $branchId, array $request): array
    {
        $config = $this->getBranchConfig($branchId);
        if (!function_exists('curl_init')) {
            return $this->failedResult(0, 'cURL extension tidak tersedia.', $request);
        }

        $method = strtoupper((string)($request['method'] ?? 'GET'));
        $url = (string)($request['url'] ?? '');
        $query = is_array($request['query'] ?? null) ? $request['query'] : [];
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($config['consumer_key'] . ':' . $config['consumer_secret']),
            'User-Agent: KopiBot-WooCommerce-Connector/0.2',
        ];

        $body = null;
        if (array_key_exists('body', $request)) {
            $body = json_encode($request['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
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

    private function buildUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function decodeBody(string $body): array|string|null
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }

    private function extractErrorMessage(array|string|null $decoded, string $rawBody): string
    {
        if (is_array($decoded)) {
            foreach (['message', 'error', 'code'] as $key) {
                if (!empty($decoded[$key]) && is_scalar($decoded[$key])) {
                    return (string)$decoded[$key];
                }
            }
        }

        return trim($rawBody) !== '' ? trim($rawBody) : 'Unknown WooCommerce API error.';
    }

    private function failedResult(int $status, string $error, array $request): array
    {
        return [
            'success' => false,
            'status' => $status,
            'data' => null,
            'error' => $error,
            'request_preview' => $this->sanitizeRequestPreview($request),
            'response_preview' => ['error' => $error],
        ];
    }

    private function sanitizeRequestPreview(array $request): array
    {
        $headers = [];
        foreach ((array)($request['headers'] ?? []) as $header) {
            $header = (string)$header;
            if (stripos($header, 'Authorization:') === 0) {
                $headers[] = 'Authorization: [masked]';
            } else {
                $headers[] = $header;
            }
        }

        return [
            'method' => (string)($request['method'] ?? 'GET'),
            'url' => (string)($request['url'] ?? ''),
            'headers' => $headers,
            'body' => $request['body'] ?? null,
        ];
    }

    private function buildResponsePreview(int $status, array|string|null $decoded, string $rawBody, string $error): array
    {
        return [
            'status' => $status,
            'error' => $error !== '' ? $error : null,
            'body' => is_array($decoded) ? $decoded : (trim($rawBody) !== '' ? $rawBody : null),
        ];
    }

    private function maskConfig(array $config): array
    {
        foreach (['consumer_key', 'consumer_secret', 'webhook_secret'] as $secretKey) {
            if (!empty($config[$secretKey])) {
                $config[$secretKey] = '[masked]';
            }
        }

        return $config;
    }
}
