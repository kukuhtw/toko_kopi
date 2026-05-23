<?php

declare(strict_types=1);

final class KiriminAjaDeliveryService
{
    private KiriminAjaDeliveryRepository $repo;

    public function __construct(?KiriminAjaDeliveryRepository $repo = null)
    {
        $this->repo = $repo ?? new KiriminAjaDeliveryRepository();
        $this->repo->ensureSchema();
    }

    public function isImplementedForBranch(int $branchId): bool
    {
        return $branchId > 0 && $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
    }

    public function getDefaultFee(int $branchId): float
    {
        return max(0.0, (float)$this->repo->getBranchSetting($branchId, 'default_delivery_fee', '0'));
    }

    public function getClient(int $branchId): KiriminAjaDeliveryClient
    {
        return new KiriminAjaDeliveryClient(
            $this->repo->getBranchSetting($branchId, 'base_url', 'https://api.kiriminaja.com'),
            $this->repo->getBranchSetting($branchId, 'api_key')
        );
    }

    public function calculateForCheckout(int $branchId, array $cart, array $items, array $customerData): array
    {
        if (!$this->isImplementedForBranch($branchId)) {
            return $customerData;
        }

        $fulfillmentType = strtolower(trim((string)($customerData['fulfillment_type'] ?? 'delivery')));
        if ($fulfillmentType !== 'delivery') {
            $customerData['delivery_fee'] = 0.0;
            $customerData['delivery_breakdown'] = null;
            return $customerData;
        }

        if ((float)($customerData['delivery_fee'] ?? 0) > 0) {
            return $customerData;
        }

        $defaultFee = $this->getDefaultFee($branchId);
        $courierLabel = trim($this->repo->getBranchSetting($branchId, 'courier_label', 'KiriminAja'));
        $serviceLabel = trim($this->repo->getBranchSetting($branchId, 'service_label', 'Delivery Fee'));

        $customerData['delivery_fee'] = $defaultFee;
        $customerData['delivery_breakdown'] = [
            'provider' => 'kiriminaja',
            'courier' => $courierLabel !== '' ? $courierLabel : 'KiriminAja',
            'service' => $serviceLabel !== '' ? $serviceLabel : 'Delivery Fee',
            'cost' => $defaultFee,
            'mode' => $this->repo->getBranchSetting($branchId, 'mode', 'sandbox'),
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
        $orderData['delivery_courier'] = (string)($breakdown['courier'] ?? 'KiriminAja');
        $orderData['delivery_service'] = (string)($breakdown['service'] ?? 'Delivery Fee');
        $orderData['delivery_provider'] = 'kiriminaja';
        $orderData['delivery_reference'] = (string)($breakdown['reference'] ?? '');
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
            'provider' => 'kiriminaja',
            'fee' => $deliveryFee,
            'courier' => (string)($order['delivery_courier'] ?? 'KiriminAja'),
            'service' => (string)($order['delivery_service'] ?? 'Delivery Fee'),
        ];

        return $responseData;
    }
}
