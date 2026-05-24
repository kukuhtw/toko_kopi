<?php

declare(strict_types=1);

use App\Helpers\Csrf;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class GoSendDeliveryPlugin implements PluginInterface
{
    private GoSendDeliveryRepository $repo;
    private GoSendDeliveryService $service;

    public function __construct()
    {
        $this->repo = new GoSendDeliveryRepository();
        $this->service = new GoSendDeliveryService($this->repo);
    }

    public function getName(): string { return 'GoSend Delivery'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'Codex'; }

    public function register(): void
    {
        $this->repo->ensureSchema();

        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItems'], 18);
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 18);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 18);
        HookManager::addAction('order.created', [$this, 'handleOrderCreated'], 18);
    }

    public function addNavItems(array $items, string $role): array
    {
        if ($role === 'super_admin') {
            $items['Integrations'][] = ['url' => '/dashboard/super/gosend.php', 'icon' => 'GS', 'label' => 'GoSend'];
        }
        if ($role === 'branch_admin') {
            $items['Integrations'][] = ['url' => '/dashboard/branch/gosend.php', 'icon' => 'GS', 'label' => 'GoSend'];
        }
        return $items;
    }

    public function addBranchSettingsSection(array $sections, int $branchId): array
    {
        $sections[GoSendDeliveryRepository::PLUGIN_SLUG] = $this->renderBranchSettingsCard($branchId, false);
        return $sections;
    }

    public function addSuperSettingsSection(array $sections, int $branchId): array
    {
        $sections[GoSendDeliveryRepository::PLUGIN_SLUG] = $this->renderBranchSettingsCard($branchId, true) . $this->renderGlobalSettingsCard();
        return $sections;
    }

    public function handleOrderCreated(array $order): void
    {
        $this->service->queueOrderDelivery($order, 'order.created');
    }

    private function renderGlobalSettingsCard(): string
    {
        $mode = $this->repo->getGlobalSetting('connection_mode', 'mock');
        $timeout = $this->repo->getGlobalSetting('timeout_seconds', '15');
        $verifySsl = $this->repo->getGlobalSetting('verify_ssl', '1') === '1';
        $runnerToken = $this->repo->getGlobalSetting('runner_token');

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">GoSend Global Runtime</div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_app_settings">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Connection Mode</label>
                <select name="<?= GoSendDeliveryRepository::PLUGIN_SLUG ?>_connection_mode" class="form-control">
                  <option value="mock" <?= $mode === 'mock' ? 'selected' : '' ?>>Mock</option>
                  <option value="staging" <?= $mode === 'staging' ? 'selected' : '' ?>>Staging</option>
                  <option value="production" <?= $mode === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Timeout (detik)</label>
                <input type="number" name="<?= GoSendDeliveryRepository::PLUGIN_SLUG ?>_timeout_seconds" class="form-control" min="5" max="120" value="<?= htmlspecialchars($timeout) ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:32px">
                  <input type="hidden" name="<?= GoSendDeliveryRepository::PLUGIN_SLUG ?>_verify_ssl" value="0">
                  <input type="checkbox" name="<?= GoSendDeliveryRepository::PLUGIN_SLUG ?>_verify_ssl" value="1" <?= $verifySsl ? 'checked' : '' ?>>
                  <span>Verify SSL</span>
                </label>
              </div>
              <div class="form-group">
                <label class="form-label">Runner Token</label>
                <input type="text" name="<?= GoSendDeliveryRepository::PLUGIN_SLUG ?>_runner_token" class="form-control" value="<?= htmlspecialchars($runnerToken) ?>" placeholder="token untuk process-queue.php">
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Runtime GoSend</button>
          </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderBranchSettingsCard(int $branchId, bool $includeBranchField): string
    {
        $isActive = $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
        $baseUrl = $this->repo->getBranchSetting($branchId, 'base_url', 'https://ecommercetools-integration.gojek.com');
        $clientId = $this->repo->getBranchSetting($branchId, 'client_id');
        $passKey = $this->repo->getBranchSetting($branchId, 'pass_key');
        $serviceType = $this->repo->getBranchSetting($branchId, 'service_type', 'instant');
        $authMode = $this->repo->getBranchSetting($branchId, 'auth_mode', 'header_pair');
        $originName = $this->repo->getBranchSetting($branchId, 'origin_contact_name', 'Store');
        $originPhone = $this->repo->getBranchSetting($branchId, 'origin_contact_phone');
        $originAddress = $this->repo->getBranchSetting($branchId, 'origin_address');
        $originLat = $this->repo->getBranchSetting($branchId, 'origin_latitude');
        $originLng = $this->repo->getBranchSetting($branchId, 'origin_longitude');
        $defaultWeightGrams = $this->repo->getBranchSetting($branchId, 'default_weight_grams', '1000');
        $merchantKey = $this->repo->getBranchSetting($branchId, 'merchant_key');
        $shopId = $this->repo->getBranchSetting($branchId, 'shop_id');
        $clientIdHeader = $this->repo->getBranchSetting($branchId, 'client_id_header', 'Client-ID');
        $passKeyHeader = $this->repo->getBranchSetting($branchId, 'pass_key_header', 'Pass-Key');
        $bookingPath = $this->repo->getBranchSetting($branchId, 'booking_path', '/api/v1/bookings');
        $bookingMethod = strtoupper($this->repo->getBranchSetting($branchId, 'booking_method', 'POST'));
        $estimatePath = $this->repo->getBranchSetting($branchId, 'estimate_path', '/api/v1/bookings/estimate');
        $estimateMethod = strtoupper($this->repo->getBranchSetting($branchId, 'estimate_method', 'POST'));
        $pickupPath = $this->repo->getBranchSetting($branchId, 'pickup_path', '/api/v1/bookings/{external_ref}/pickup');
        $pickupMethod = strtoupper($this->repo->getBranchSetting($branchId, 'pickup_method', 'POST'));
        $statusPath = $this->repo->getBranchSetting($branchId, 'status_path', '/api/v1/bookings/{external_ref}');
        $statusMethod = strtoupper($this->repo->getBranchSetting($branchId, 'status_method', 'GET'));
        $cancelPath = $this->repo->getBranchSetting($branchId, 'cancel_path', '/api/v1/bookings/{external_ref}/cancel');
        $cancelMethod = strtoupper($this->repo->getBranchSetting($branchId, 'cancel_method', 'POST'));
        $useJsonBody = $this->repo->getBranchSetting($branchId, 'use_json_body', '1') === '1';
        $extraHeaders = $this->repo->getBranchSetting($branchId, 'extra_headers');
        $trackingBaseUrl = $this->repo->getBranchSetting($branchId, 'tracking_base_url', 'https://gojek.com/gosend');
        $webhookSecret = $this->repo->getBranchSetting($branchId, 'webhook_secret');
        $maxRetries = $this->repo->getBranchSetting($branchId, 'max_retries', '3');
        $retryDelay = $this->repo->getBranchSetting($branchId, 'retry_delay_seconds', '300');
        $overview = $this->service->getClientOverview($branchId);
        $webhookUrl = BASE_URL . '/api/plugins/gosend/webhook.php?branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">GoSend Delivery</div>
          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.84rem;line-height:1.7">
            Plugin ini adalah fondasi integrasi GoSend API untuk booking delivery dari order chatbot, monitoring tracking, queue retry, dan webhook status.
            <br><br>
            <strong>Webhook endpoint</strong><br>
            <code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code>
          </div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= GoSendDeliveryRepository::PLUGIN_SLUG ?>">
            <?php if ($includeBranchField): ?>
            <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
            <?php endif; ?>
            <input type="hidden" name="is_active" value="0">

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan GoSend untuk cabang ini</span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Base URL</label>
                <input type="url" name="base_url" class="form-control" value="<?= htmlspecialchars($baseUrl) ?>" placeholder="https://ecommercetools-integration.gojek.com">
              </div>
              <div class="form-group">
                <label class="form-label">Service Type</label>
                <select name="service_type" class="form-control">
                  <option value="instant" <?= $serviceType === 'instant' ? 'selected' : '' ?>>Instant</option>
                  <option value="sameday" <?= $serviceType === 'sameday' ? 'selected' : '' ?>>SameDay</option>
                  <option value="car" <?= $serviceType === 'car' ? 'selected' : '' ?>>Car</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Auth Mode</label>
                <select name="auth_mode" class="form-control">
                  <option value="header_pair" <?= $authMode === 'header_pair' ? 'selected' : '' ?>>Header Pair</option>
                  <option value="basic" <?= $authMode === 'basic' ? 'selected' : '' ?>>Basic Auth</option>
                  <option value="bearer" <?= $authMode === 'bearer' ? 'selected' : '' ?>>Bearer Token</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Client ID</label>
                <input type="text" name="client_id" class="form-control" value="<?= htmlspecialchars($clientId) ?>" placeholder="credential dari GoSend">
              </div>
              <div class="form-group">
                <label class="form-label">Pass Key</label>
                <input type="password" name="pass_key" class="form-control" value="<?= htmlspecialchars($passKey) ?>" placeholder="credential dari GoSend">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Client ID Header</label>
                <input type="text" name="client_id_header" class="form-control" value="<?= htmlspecialchars($clientIdHeader) ?>" placeholder="Client-ID">
              </div>
              <div class="form-group">
                <label class="form-label">Pass Key Header</label>
                <input type="text" name="pass_key_header" class="form-control" value="<?= htmlspecialchars($passKeyHeader) ?>" placeholder="Pass-Key">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Merchant Key</label>
                <input type="text" name="merchant_key" class="form-control" value="<?= htmlspecialchars($merchantKey) ?>" placeholder="opsional">
              </div>
              <div class="form-group">
                <label class="form-label">Shop ID</label>
                <input type="text" name="shop_id" class="form-control" value="<?= htmlspecialchars($shopId) ?>" placeholder="opsional">
              </div>
              <div class="form-group">
                <label class="form-label">Default Weight (gram)</label>
                <input type="number" name="default_weight_grams" class="form-control" min="100" max="50000" value="<?= htmlspecialchars($defaultWeightGrams) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Origin Contact Name</label>
                <input type="text" name="origin_contact_name" class="form-control" value="<?= htmlspecialchars($originName) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Origin Contact Phone</label>
                <input type="text" name="origin_contact_phone" class="form-control" value="<?= htmlspecialchars($originPhone) ?>" placeholder="08xxxxxxxxxx">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Origin Address</label>
              <textarea name="origin_address" class="form-control" rows="2"><?= htmlspecialchars($originAddress) ?></textarea>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Origin Latitude</label>
                <input type="text" name="origin_latitude" class="form-control" value="<?= htmlspecialchars($originLat) ?>" placeholder="-6.2000">
              </div>
              <div class="form-group">
                <label class="form-label">Origin Longitude</label>
                <input type="text" name="origin_longitude" class="form-control" value="<?= htmlspecialchars($originLng) ?>" placeholder="106.8166">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Tracking Base URL</label>
                <input type="url" name="tracking_base_url" class="form-control" value="<?= htmlspecialchars($trackingBaseUrl) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Webhook Secret</label>
                <input type="text" name="webhook_secret" class="form-control" value="<?= htmlspecialchars($webhookSecret) ?>" placeholder="opsional untuk verifikasi HMAC">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Booking Path</label>
                <input type="text" name="booking_path" class="form-control" value="<?= htmlspecialchars($bookingPath) ?>" placeholder="/api/v1/bookings">
              </div>
              <div class="form-group">
                <label class="form-label">Booking Method</label>
                <select name="booking_method" class="form-control">
                  <option value="POST" <?= $bookingMethod === 'POST' ? 'selected' : '' ?>>POST</option>
                  <option value="PUT" <?= $bookingMethod === 'PUT' ? 'selected' : '' ?>>PUT</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Estimate Path</label>
                <input type="text" name="estimate_path" class="form-control" value="<?= htmlspecialchars($estimatePath) ?>" placeholder="/api/v1/bookings/estimate">
              </div>
              <div class="form-group">
                <label class="form-label">Estimate Method</label>
                <select name="estimate_method" class="form-control">
                  <option value="POST" <?= $estimateMethod === 'POST' ? 'selected' : '' ?>>POST</option>
                  <option value="GET" <?= $estimateMethod === 'GET' ? 'selected' : '' ?>>GET</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Pickup Path</label>
                <input type="text" name="pickup_path" class="form-control" value="<?= htmlspecialchars($pickupPath) ?>" placeholder="/api/v1/bookings/{external_ref}/pickup">
              </div>
              <div class="form-group">
                <label class="form-label">Pickup Method</label>
                <select name="pickup_method" class="form-control">
                  <option value="POST" <?= $pickupMethod === 'POST' ? 'selected' : '' ?>>POST</option>
                  <option value="PUT" <?= $pickupMethod === 'PUT' ? 'selected' : '' ?>>PUT</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Status Path</label>
                <input type="text" name="status_path" class="form-control" value="<?= htmlspecialchars($statusPath) ?>" placeholder="/api/v1/bookings/{external_ref}">
              </div>
              <div class="form-group">
                <label class="form-label">Status Method</label>
                <select name="status_method" class="form-control">
                  <option value="GET" <?= $statusMethod === 'GET' ? 'selected' : '' ?>>GET</option>
                  <option value="POST" <?= $statusMethod === 'POST' ? 'selected' : '' ?>>POST</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Cancel Path</label>
                <input type="text" name="cancel_path" class="form-control" value="<?= htmlspecialchars($cancelPath) ?>" placeholder="/api/v1/bookings/{external_ref}/cancel">
              </div>
              <div class="form-group">
                <label class="form-label">Cancel Method</label>
                <select name="cancel_method" class="form-control">
                  <option value="POST" <?= $cancelMethod === 'POST' ? 'selected' : '' ?>>POST</option>
                  <option value="DELETE" <?= $cancelMethod === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="hidden" name="use_json_body" value="0">
                <input type="checkbox" name="use_json_body" value="1" <?= $useJsonBody ? 'checked' : '' ?>>
                <span>Kirim payload sebagai JSON body</span>
              </label>
            </div>

            <div class="form-group">
              <label class="form-label">Extra Headers</label>
              <textarea name="extra_headers" class="form-control" rows="3" placeholder="X-Partner-Key: abc123&#10;X-Environment: staging"><?= htmlspecialchars($extraHeaders) ?></textarea>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Max Retries</label>
                <input type="number" name="max_retries" class="form-control" min="0" max="20" value="<?= htmlspecialchars($maxRetries) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Retry Delay (detik)</label>
                <input type="number" name="retry_delay_seconds" class="form-control" min="30" max="86400" value="<?= htmlspecialchars($retryDelay) ?>">
              </div>
            </div>

            <div style="background:#eef7fb;border:1px solid #cfe8f3;border-radius:10px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.65">
              <strong>Status runtime</strong><br>
              Mode: <code><?= htmlspecialchars((string)($overview['mode'] ?? 'mock')) ?></code><br>
              Base URL: <code><?= htmlspecialchars((string)($overview['base_url'] ?? '')) ?></code><br>
              Client ID: <?= !empty($overview['has_client_id']) ? 'tersedia' : 'belum diisi' ?><br>
              Pass Key: <?= !empty($overview['has_pass_key']) ? 'tersedia' : 'belum diisi' ?><br>
              Service: <?= htmlspecialchars((string)($overview['service_type'] ?? 'instant')) ?><br>
              Auth: <code><?= htmlspecialchars((string)($overview['auth_mode'] ?? 'header_pair')) ?></code><br>
              Booking Path: <code><?= htmlspecialchars((string)($overview['booking_path'] ?? '')) ?></code><br>
              Pickup Path: <code><?= htmlspecialchars((string)($overview['pickup_path'] ?? '')) ?></code><br>
              Status Path: <code><?= htmlspecialchars((string)($overview['status_path'] ?? '')) ?></code>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan GoSend</button>
          </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
