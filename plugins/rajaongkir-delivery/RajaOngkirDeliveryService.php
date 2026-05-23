<?php

declare(strict_types=1);

final class RajaOngkirDeliveryService
{
    private RajaOngkirDeliveryRepository $repo;

    public function __construct(?RajaOngkirDeliveryRepository $repo = null)
    {
        $this->repo = $repo ?? new RajaOngkirDeliveryRepository();
        $this->repo->ensureSchema();
    }

    public function isActive(int $branchId): bool
    {
        return $branchId > 0 && $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
    }

    public function calculateForCheckout(int $branchId, array $cart, array $items, array $customerData): array
    {
        if (!$this->isActive($branchId)) {
            return $customerData;
        }

        $fulfillmentType = strtolower(trim((string)($customerData['fulfillment_type'] ?? 'delivery')));
        if ($fulfillmentType !== 'delivery') {
            $customerData['delivery_fee'] = 0.0;
            $customerData['delivery_breakdown'] = null;
            return $customerData;
        }

        $apiKey = $this->repo->getBranchSetting($branchId, 'api_key');
        $originId = $this->repo->getBranchSetting($branchId, 'origin_id');
        $courierCode = strtolower(trim($this->repo->getBranchSetting($branchId, 'courier_code', 'jne')));
        $pricePreference = strtolower(trim($this->repo->getBranchSetting($branchId, 'price_preference', 'lowest')));

        if ($originId === '' && $apiKey !== '') {
            $originId = $this->resolveAndPersistOriginId($branchId, $apiKey);
        }

        if ($apiKey === '' || $originId === '' || $courierCode === '') {
            throw new \RuntimeException('Plugin RajaOngkir aktif, tetapi konfigurasi origin/api key/courier belum lengkap.');
        }

        $addressQuery = trim((string)($customerData['address'] ?? ''));
        $postalCode = trim((string)($customerData['postal_code'] ?? ''));
        $search = $postalCode !== '' ? $postalCode : $addressQuery;
        if ($search === '') {
            throw new \RuntimeException('Alamat delivery diperlukan untuk menghitung ongkir.');
        }

        $destination = $this->searchDestination($apiKey, $search);
        if ($destination === null) {
            throw new \RuntimeException('Tujuan delivery tidak ditemukan di RajaOngkir. Periksa alamat atau kode pos.');
        }

        $weightGrams = $this->estimateWeight($branchId, $items);
        $shipping = $this->calculateDomesticCost(
            $apiKey,
            (string)$originId,
            (string)($destination['id'] ?? ''),
            $weightGrams,
            $courierCode,
            $pricePreference
        );

        if ($shipping === null) {
            throw new \RuntimeException('Gagal menghitung ongkir RajaOngkir untuk alamat ini.');
        }

        $customerData['delivery_fee'] = (float)($shipping['cost'] ?? 0);
        $customerData['delivery_breakdown'] = [
            'provider' => 'rajaongkir',
            'destination_id' => (string)($destination['id'] ?? ''),
            'destination_label' => (string)($destination['label'] ?? ''),
            'courier' => (string)($shipping['code'] ?? $courierCode),
            'service' => (string)($shipping['service'] ?? ''),
            'etd' => (string)($shipping['etd'] ?? ''),
            'weight_grams' => $weightGrams,
            'cost' => (float)($shipping['cost'] ?? 0),
        ];

        return $customerData;
    }

    public function appendOrderData(array $orderData, array $cart, array $items, array $customerData, int $customerId, float $ppnRate): array
    {
        $deliveryFee = (float)($customerData['delivery_fee'] ?? 0);
        $breakdown = $customerData['delivery_breakdown'] ?? null;
        if ($deliveryFee <= 0 || !is_array($breakdown)) {
            return $orderData;
        }

        $orderData['delivery_fee'] = $deliveryFee;
        $orderData['delivery_courier'] = (string)($breakdown['courier'] ?? '');
        $orderData['delivery_service'] = (string)($breakdown['service'] ?? '');
        $orderData['delivery_etd'] = (string)($breakdown['etd'] ?? '');
        $orderData['delivery_destination_id'] = (string)($breakdown['destination_id'] ?? '');
        $orderData['total_amount'] = (float)($orderData['total_amount'] ?? 0) + $deliveryFee;

        return $orderData;
    }

    public function appendCheckoutResponse(array $responseData, array $order, int $branchId): array
    {
        $deliveryFee = (float)($order['delivery_fee'] ?? 0);
        if ($deliveryFee <= 0) {
            return $responseData;
        }

        $responseData['delivery'] = [
            'provider' => 'rajaongkir',
            'fee' => $deliveryFee,
            'courier' => (string)($order['delivery_courier'] ?? ''),
            'service' => (string)($order['delivery_service'] ?? ''),
            'etd' => (string)($order['delivery_etd'] ?? ''),
        ];

        return $responseData;
    }

    public function previewDelivery(int $branchId, array $items, string $address, string $postalCode): array
    {
        $data = $this->calculateForCheckout($branchId, [], $items, [
            'fulfillment_type' => 'delivery',
            'address' => $address,
            'postal_code' => $postalCode,
        ]);

        return (array)($data['delivery_breakdown'] ?? []);
    }

    public function syncOriginForBranch(int $branchId): array
    {
        if ($branchId <= 0) {
            return ['ok' => false, 'message' => 'Cabang tidak valid.'];
        }

        $apiKey = $this->repo->getBranchSetting($branchId, 'api_key');
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'API key RajaOngkir belum diisi.'];
        }

        try {
            $originId = $this->resolveAndPersistOriginId($branchId, $apiKey);
            return [
                'ok' => true,
                'message' => 'Origin RajaOngkir berhasil disinkronkan.',
                'origin_id' => $originId,
                'origin_label' => $this->repo->getBranchSetting($branchId, 'origin_label'),
            ];
        } catch (\Throwable $e) {
            $this->repo->setBranchSetting($branchId, 'origin_sync_status', 'error');
            $this->repo->setBranchSetting($branchId, 'origin_sync_message', $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function estimateWeight(int $branchId, array $items): int
    {
        $baseWeight = max(0, (int)$this->repo->getBranchSetting($branchId, 'base_weight_grams', '250'));
        $perItemWeight = max(1, (int)$this->repo->getBranchSetting($branchId, 'per_item_weight_grams', '200'));
        $qty = 0;
        foreach ($items as $item) {
            $qty += (int)($item['quantity'] ?? 0);
        }

        return max(1, $baseWeight + ($qty * $perItemWeight));
    }

    private function resolveAndPersistOriginId(int $branchId, string $apiKey): string
    {
        $branchModel = new \App\Models\BranchModel();
        $branch = $branchModel->find($branchId);
        if (!$branch) {
            throw new \RuntimeException('Data cabang tidak ditemukan untuk sinkron origin RajaOngkir.');
        }

        $searchTerms = [];
        $postalCode = preg_replace('/\D/', '', (string)($branch['postal_code'] ?? ''));
        $address = trim((string)($branch['address'] ?? ''));
        $city = trim((string)($branch['city'] ?? ''));

        if ($postalCode !== '') {
            $searchTerms[] = $postalCode;
        }
        if ($address !== '') {
            $searchTerms[] = $address;
        }
        if ($city !== '') {
            $searchTerms[] = $city;
        }

        if ($searchTerms === []) {
            throw new \RuntimeException('Kode pos atau alamat cabang belum diisi, jadi origin RajaOngkir belum bisa disinkronkan.');
        }

        $destination = null;
        foreach ($searchTerms as $search) {
            $destination = $this->searchDestination($apiKey, $search);
            if ($destination !== null) {
                break;
            }
        }

        if ($destination === null) {
            throw new \RuntimeException('Lokasi origin cabang tidak ditemukan di RajaOngkir. Periksa kode pos atau alamat cabang.');
        }

        $originId = (string)($destination['id'] ?? '');
        if ($originId === '') {
            throw new \RuntimeException('RajaOngkir tidak mengembalikan origin_id yang valid.');
        }

        $this->repo->setBranchSetting($branchId, 'origin_id', $originId);
        $this->repo->setBranchSetting($branchId, 'origin_label', (string)($destination['label'] ?? ''));
        $this->repo->setBranchSetting($branchId, 'origin_last_sync_at', date('Y-m-d H:i:s'));
        $this->repo->setBranchSetting($branchId, 'origin_sync_status', 'success');
        $this->repo->setBranchSetting($branchId, 'origin_sync_message', 'Origin berhasil disinkronkan dari data cabang.');

        return $originId;
    }

    private function searchDestination(string $apiKey, string $search): ?array
    {
        $url = 'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination?search='
            . rawurlencode($search) . '&limit=1&offset=0';
        $response = $this->request('GET', $url, $apiKey);
        if (!is_array($response['data'] ?? null) || empty($response['data'][0])) {
            return null;
        }

        return $response['data'][0];
    }

    private function calculateDomesticCost(
        string $apiKey,
        string $originId,
        string $destinationId,
        int $weightGrams,
        string $courierCode,
        string $pricePreference
    ): ?array {
        $response = $this->request(
            'POST',
            'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost',
            $apiKey,
            [
                'origin' => $originId,
                'destination' => $destinationId,
                'weight' => (string)$weightGrams,
                'courier' => $courierCode,
                'price' => $pricePreference,
            ]
        );

        if (!is_array($response['data'] ?? null) || empty($response['data'][0])) {
            return null;
        }

        return $response['data'][0];
    }

    private function request(string $method, string $url, string $apiKey, array $form = []): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Ekstensi cURL diperlukan untuk plugin RajaOngkir.');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'key: ' . $apiKey,
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'key: ' . $apiKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
        }

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false || $curlError !== '') {
            throw new \RuntimeException('RajaOngkir request gagal: ' . $curlError);
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Response RajaOngkir tidak valid.');
        }

        $metaCode = (int)($decoded['meta']['code'] ?? $status);
        if ($status >= 400 || $metaCode >= 400) {
            $message = (string)($decoded['meta']['message'] ?? 'Request RajaOngkir gagal.');
            throw new \RuntimeException($message);
        }

        return $decoded;
    }
}
