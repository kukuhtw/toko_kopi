<?php

declare(strict_types=1);

use App\Models\OrderModel;

final class GoSendDeliveryService
{
    public function __construct(
        private GoSendDeliveryRepository $repo,
        private ?GoSendDeliveryClient $client = null
    ) {
        $this->client ??= new GoSendDeliveryClient($repo);
    }

    public function isActive(int $branchId): bool
    {
        return $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
    }

    public function queueOrderDelivery(array $order, string $eventName = 'order.created'): void
    {
        $branchId = (int)($order['branch_id'] ?? 0);
        if ($branchId <= 0 || !$this->isActive($branchId)) {
            return;
        }
        if (strtolower((string)($order['fulfillment_type'] ?? 'delivery')) !== 'delivery') {
            return;
        }

        $orderId = (int)($order['id'] ?? 0);
        $syncKey = 'gosend:' . $branchId . ':' . ($order['order_number'] ?? ('order-' . $orderId)) . ':' . $eventName;
        $payload = $this->buildPayload($branchId, $order);
        $logId = $this->repo->queueLog($branchId, $orderId, $eventName, 'pending', $payload, $syncKey);

        $this->repo->upsertDeliveryOrderStatus($branchId, $orderId, (string)($order['order_number'] ?? ''), [
            'delivery_status' => $eventName === 'pickup.requested' ? 'pickup_queued' : 'queued',
            'service_type' => $payload['service_type'] ?? null,
            'latest_note' => $eventName === 'pickup.requested'
                ? 'Pickup GoSend masuk queue.'
                : 'Booking GoSend masuk queue.',
            'last_log_id' => $logId,
        ]);
    }

    public function processPendingQueue(?int $branchId = null, int $limit = 20): array
    {
        $logs = $this->repo->getPendingLogs($branchId, $limit);
        $processed = 0;
        $success = 0;
        $failed = 0;

        foreach ($logs as $log) {
            $processed++;
            $result = $this->processQueuedLog($log);
            if (!empty($result['success'])) {
                $success++;
            } else {
                $failed++;
            }
        }

        return compact('processed', 'success', 'failed');
    }

    public function processQueuedLog(array $log): array
    {
        $branchId = (int)($log['branch_id'] ?? 0);
        $orderId = (int)($log['order_id'] ?? 0);
        $eventName = (string)($log['event_name'] ?? 'order.created');
        $order = $orderId > 0 ? $this->repo->getOrderById($orderId) : false;
        if (!$order) {
            $this->repo->markLogProcessed((int)$log['id'], 'failed', [], null, null, 404, 'Order tidak ditemukan.');
            return ['success' => false, 'message' => 'Order tidak ditemukan'];
        }

        try {
            $payload = json_decode((string)($log['payload_preview'] ?? '{}'), true);
            if (!is_array($payload) || $payload === []) {
                $payload = $this->buildPayload($branchId, $order);
            }

            $deliveryOrder = $this->repo->getDeliveryOrderByOrderId($orderId);
            if ($eventName === 'pickup.requested') {
                $externalRef = (string)($deliveryOrder['external_ref'] ?? '');
                if ($externalRef === '') {
                    $bookingResponse = $this->client->createDeliveryOrder($branchId, $payload);
                    $externalRef = (string)($bookingResponse['external_ref'] ?? '');
                    $trackingUrl = (string)($bookingResponse['tracking_url'] ?? '');
                    $this->repo->upsertDeliveryOrderStatus($branchId, $orderId, (string)($order['order_number'] ?? ''), [
                        'external_ref' => $externalRef,
                        'tracking_url' => $trackingUrl,
                        'service_type' => $bookingResponse['service_type'] ?? ($payload['service_type'] ?? null),
                        'delivery_status' => (string)($bookingResponse['delivery_status'] ?? 'searching_driver'),
                        'latest_note' => (string)($bookingResponse['note'] ?? 'Booking GoSend berhasil dibuat sebelum pickup trigger.'),
                        'last_log_id' => (int)$log['id'],
                    ]);
                }

                $response = $this->client->requestPickup($branchId, $payload, $externalRef);
            } else {
                $response = $this->client->createDeliveryOrder($branchId, $payload);
            }

            $externalRef = (string)($response['external_ref'] ?? ($deliveryOrder['external_ref'] ?? ''));
            $trackingUrl = (string)($response['tracking_url'] ?? ($deliveryOrder['tracking_url'] ?? ''));
            $deliveryStatus = (string)($response['delivery_status'] ?? 'searching_driver');

            $this->repo->markLogProcessed((int)$log['id'], 'success', $response, $externalRef, $trackingUrl, 200, null, null);
            $this->repo->upsertDeliveryOrderStatus($branchId, $orderId, (string)($order['order_number'] ?? ''), [
                'external_ref' => $externalRef !== '' ? $externalRef : null,
                'tracking_url' => $trackingUrl !== '' ? $trackingUrl : null,
                'service_type' => $response['service_type'] ?? ($payload['service_type'] ?? null),
                'delivery_status' => $deliveryStatus,
                'latest_note' => (string)($response['note'] ?? 'Booking berhasil dibuat.'),
                'last_log_id' => (int)$log['id'],
            ]);

            return ['success' => true, 'message' => $eventName === 'pickup.requested'
                ? 'Pickup GoSend berhasil diproses.'
                : 'Booking GoSend berhasil diproses.'];
        } catch (Throwable $e) {
            $maxRetries = max(0, (int)$this->repo->getBranchSetting($branchId, 'max_retries', '3'));
            $attemptCount = (int)($log['attempt_count'] ?? 0) + 1;
            $retryDelay = max(30, (int)$this->repo->getBranchSetting($branchId, 'retry_delay_seconds', '300'));
            $status = $attemptCount <= $maxRetries ? 'retry_scheduled' : 'failed';
            $nextRetryAt = $status === 'retry_scheduled' ? date('Y-m-d H:i:s', time() + $retryDelay) : null;

            $this->repo->markLogProcessed((int)$log['id'], $status, [], null, null, 500, $e->getMessage(), $nextRetryAt);
            $this->repo->upsertDeliveryOrderStatus($branchId, $orderId, (string)($order['order_number'] ?? ''), [
                'delivery_status' => $status,
                'latest_note' => $e->getMessage(),
                'last_log_id' => (int)$log['id'],
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function requestPickupForOrder(int $orderId): array
    {
        $order = $this->repo->getOrderById($orderId);
        if (!$order) {
            throw new RuntimeException('Order tidak ditemukan.');
        }

        $branchId = (int)($order['branch_id'] ?? 0);
        if (!$this->isActive($branchId)) {
            throw new RuntimeException('Plugin GoSend belum aktif untuk cabang ini.');
        }
        if (strtolower((string)($order['fulfillment_type'] ?? 'delivery')) !== 'delivery') {
            throw new RuntimeException('Pickup GoSend hanya untuk order delivery.');
        }

        $this->queueOrderDelivery($order, 'pickup.requested');
        $logs = $this->repo->getPendingLogs($branchId, 20);
        foreach ($logs as $log) {
            if ((int)($log['order_id'] ?? 0) === $orderId && (string)($log['event_name'] ?? '') === 'pickup.requested') {
                return $this->processQueuedLog($log);
            }
        }

        return ['success' => true, 'message' => 'Pickup GoSend sudah di-queue.'];
    }

    public function refreshStatusForOrder(int $orderId): array
    {
        $order = $this->repo->getOrderById($orderId);
        if (!$order) {
            throw new RuntimeException('Order tidak ditemukan.');
        }
        $branchId = (int)($order['branch_id'] ?? 0);
        $deliveryOrder = $this->repo->getDeliveryOrderByOrderId($orderId);
        if (!$deliveryOrder) {
            throw new RuntimeException('Order belum memiliki booking GoSend.');
        }

        $payload = $this->buildPayload($branchId, $order);
        $externalRef = (string)($deliveryOrder['external_ref'] ?? '');
        if ($externalRef === '') {
            throw new RuntimeException('External reference GoSend belum tersedia.');
        }

        $response = $this->client->fetchBookingStatus($branchId, $payload, $externalRef);
        $remoteStatus = strtolower(trim((string)($response['delivery_status'] ?? 'unknown')));
        $oldStatus = (string)($order['order_status'] ?? '');
        $newStatus = $this->mapGoSendStatusToOrderStatus($remoteStatus);

        $this->repo->upsertDeliveryOrderStatus($branchId, $orderId, (string)($order['order_number'] ?? ''), [
            'external_ref' => $externalRef,
            'tracking_url' => (string)($response['tracking_url'] ?? ($deliveryOrder['tracking_url'] ?? '')),
            'service_type' => $deliveryOrder['service_type'] ?? ($payload['service_type'] ?? null),
            'delivery_status' => $remoteStatus,
            'latest_note' => (string)($response['note'] ?? 'Status GoSend di-refresh dari API live.'),
            'last_log_id' => $deliveryOrder['last_log_id'] ?? null,
        ]);

        if ($newStatus !== '' && $newStatus !== $oldStatus) {
            (new OrderModel())->updateStatus($orderId, $newStatus);
        }

        return [
            'success' => true,
            'message' => 'Status GoSend berhasil di-refresh.',
            'delivery_status' => $remoteStatus,
            'order_status' => $newStatus !== '' ? $newStatus : $oldStatus,
        ];
    }

    public function handleInboundWebhook(int $branchId, array|string|null $payload): array
    {
        $data = is_array($payload) ? $payload : (json_decode((string)$payload, true) ?: []);
        $orderNumber = trim((string)($data['order_number'] ?? $data['partner_order_id'] ?? $data['booking']['order_number'] ?? ''));
        $externalRef = trim((string)($data['external_ref'] ?? $data['booking_id'] ?? $data['booking']['id'] ?? ''));
        $remoteStatus = strtolower(trim((string)($data['status'] ?? $data['booking']['status'] ?? '')));
        $trackingUrl = trim((string)($data['tracking_url'] ?? $data['booking']['tracking_url'] ?? ''));

        $deliveryOrder = $this->repo->findDeliveryOrder($branchId, $orderNumber, $externalRef);
        if (!$deliveryOrder) {
            $this->repo->addWebhookAudit($branchId, null, $orderNumber, $externalRef, $remoteStatus, null, null, $data, 'Order internal belum ditemukan dari webhook GoSend.');
            return ['success' => false, 'message' => 'Order internal belum ditemukan dari webhook GoSend.'];
        }

        $orderId = (int)($deliveryOrder['order_id'] ?? 0);
        $oldOrder = $this->repo->getOrderById($orderId);
        $oldStatus = (string)($oldOrder['order_status'] ?? '');
        $newStatus = $this->mapGoSendStatusToOrderStatus($remoteStatus);

        $this->repo->upsertDeliveryOrderStatus($branchId, $orderId, (string)($deliveryOrder['order_number'] ?? $orderNumber), [
            'external_ref' => $externalRef !== '' ? $externalRef : ($deliveryOrder['external_ref'] ?? null),
            'tracking_url' => $trackingUrl !== '' ? $trackingUrl : ($deliveryOrder['tracking_url'] ?? null),
            'service_type' => $deliveryOrder['service_type'] ?? null,
            'delivery_status' => $remoteStatus !== '' ? $remoteStatus : ($deliveryOrder['delivery_status'] ?? 'unknown'),
            'latest_note' => 'Webhook GoSend diterima.',
        ]);

        if ($newStatus !== '' && $newStatus !== $oldStatus) {
            (new OrderModel())->updateStatus($orderId, $newStatus);
        }

        $this->repo->addWebhookAudit(
            $branchId,
            $orderId,
            (string)($deliveryOrder['order_number'] ?? $orderNumber),
            $externalRef !== '' ? $externalRef : (string)($deliveryOrder['external_ref'] ?? ''),
            $remoteStatus,
            $oldStatus !== '' ? $oldStatus : null,
            $newStatus !== '' ? $newStatus : $oldStatus,
            $data,
            'Webhook GoSend berhasil diproses.'
        );

        return ['success' => true, 'message' => 'Webhook GoSend berhasil diproses.'];
    }

    public function getClientOverview(int $branchId): array
    {
        $config = $this->client->getConfig($branchId);
        return [
            'mode' => $config['mode'],
            'base_url' => $config['base_url'],
            'has_client_id' => $config['client_id'] !== '',
            'has_pass_key' => $config['pass_key'] !== '',
            'service_type' => $config['service_type'],
            'booking_path' => $config['booking_path'],
            'pickup_path' => $config['pickup_path'],
            'status_path' => $config['status_path'],
            'auth_mode' => $config['auth_mode'],
        ];
    }

    public function estimate(int $branchId, array $payload): array
    {
        return $this->client->estimate($branchId, $payload);
    }

    public function getDeliveryOrderStatus(int $orderId): array|false
    {
        return $this->repo->getDeliveryOrderByOrderId($orderId);
    }

    private function buildPayload(int $branchId, array $order): array
    {
        $serviceType = $this->repo->getBranchSetting($branchId, 'service_type', 'instant');
        $items = [];
        foreach ((array)($order['items'] ?? []) as $item) {
            $items[] = [
                'name' => (string)($item['menu_name'] ?? ''),
                'quantity' => (int)($item['quantity'] ?? 0),
                'price' => (float)($item['unit_price'] ?? 0),
                'notes' => (string)($item['notes'] ?? ''),
            ];
        }

        return [
            'order_number' => (string)($order['order_number'] ?? ''),
            'service_type' => $serviceType,
            'merchant_key' => $this->repo->getBranchSetting($branchId, 'merchant_key', ''),
            'shop_id' => $this->repo->getBranchSetting($branchId, 'shop_id', ''),
            'origin' => [
                'contact_name' => $this->repo->getBranchSetting($branchId, 'origin_contact_name', 'Store'),
                'contact_phone' => $this->repo->getBranchSetting($branchId, 'origin_contact_phone', (string)($order['branch_phone'] ?? '')),
                'address' => $this->repo->getBranchSetting($branchId, 'origin_address', ''),
                'latitude' => $this->repo->getBranchSetting($branchId, 'origin_latitude', ''),
                'longitude' => $this->repo->getBranchSetting($branchId, 'origin_longitude', ''),
            ],
            'destination' => [
                'contact_name' => (string)($order['customer_name'] ?? ''),
                'contact_phone' => (string)($order['customer_wa'] ?? ''),
                'address' => (string)($order['delivery_address'] ?? ''),
                'postal_code' => (string)($order['postal_code'] ?? ''),
                'latitude' => $this->repo->getBranchSetting($branchId, 'default_destination_latitude', ''),
                'longitude' => $this->repo->getBranchSetting($branchId, 'default_destination_longitude', ''),
            ],
            'item' => [
                'description' => 'Order ' . (string)($order['order_number'] ?? ''),
                'value' => (float)($order['total_amount'] ?? 0),
                'quantity' => max(1, count($items)),
                'weight_grams' => (int)$this->repo->getBranchSetting($branchId, 'default_weight_grams', '1000'),
            ],
            'items' => $items,
            'notes' => (string)($order['notes'] ?? ''),
        ];
    }

    private function mapGoSendStatusToOrderStatus(string $status): string
    {
        return match ($status) {
            'confirmed', 'allocated', 'searching_driver', 'driver_assigned', 'picked_up', 'in_delivery', 'on_delivery' => 'on_delivery',
            'delivered', 'completed' => 'completed',
            'cancelled', 'canceled', 'failed' => 'cancelled',
            default => '',
        };
    }
}
