<?php

declare(strict_types=1);

use App\Helpers\Csrf;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class MokaConnectPrivateSolutionPlugin implements PluginInterface
{
    private MokaConnectRepository $repo;
    private MokaConnectService $service;

    public function __construct()
    {
        $this->repo = new MokaConnectRepository();
        $this->service = new MokaConnectService($this->repo);
    }

    public function getName(): string
    {
        return 'Moka Connect / Private Solution';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'Toko Kopi';
    }

    public function register(): void
    {
        $this->repo->ensureSchema();

        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItems'], 18);
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 18);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 18);
        HookManager::addAction('order.created', [$this, 'handleOrderCreated'], 18);
        HookManager::addAction('order.status_changed', [$this, 'handleOrderStatusChanged'], 18);
        HookManager::addAction('order.payment_updated', [$this, 'handlePaymentUpdated'], 18);
    }

    public function addNavItems(array $items, string $role): array
    {
        if ($role === 'super_admin') {
            $items['Integrations'][] = [
                'url' => '/dashboard/super/moka.php',
                'icon' => 'MK',
                'label' => 'Moka Connect',
            ];
        }

        if ($role === 'branch_admin') {
            $items['Integrations'][] = [
                'url' => '/dashboard/branch/moka.php',
                'icon' => 'MK',
                'label' => 'Moka Connect',
            ];
        }

        return $items;
    }

    public function addBranchSettingsSection(array $sections, int $branchId): array
    {
        $sections[self::slug()] = $this->renderBranchSettingsCard($branchId, false);
        return $sections;
    }

    public function addSuperSettingsSection(array $sections, int $branchId): array
    {
        $sections[self::slug()] = $this->renderBranchSettingsCard($branchId, true) . $this->renderGlobalSettingsCard();
        return $sections;
    }

    public function handleOrderCreated(array $order): void
    {
        if ($this->service->shouldSuppressOutboundForOrder((int)($order['id'] ?? 0))) {
            return;
        }
        if ($this->repo->getBranchSetting((int)($order['branch_id'] ?? 0), 'sync_orders', '1') !== '1') {
            return;
        }

        $this->service->queueOrderSync($order, 'order.created');
    }

    public function handleOrderStatusChanged(array $order, string $oldStatus, string $newStatus): void
    {
        if ($this->service->shouldSuppressOutboundForOrder((int)($order['id'] ?? 0))) {
            return;
        }
        if ($this->repo->getBranchSetting((int)($order['branch_id'] ?? 0), 'sync_orders', '1') !== '1') {
            return;
        }

        $order['status_transition'] = ['from' => $oldStatus, 'to' => $newStatus];
        $this->service->queueOrderSync($order, 'order.status_changed');
    }

    public function handlePaymentUpdated(array $order, string $paymentStatus): void
    {
        if ($this->service->shouldSuppressOutboundForOrder((int)($order['id'] ?? 0))) {
            return;
        }
        if ($this->repo->getBranchSetting((int)($order['branch_id'] ?? 0), 'sync_orders', '1') !== '1') {
            return;
        }

        $order['payment_status'] = $paymentStatus;
        $this->service->queueOrderSync($order, 'order.payment_updated');
    }

    private function renderBranchSettingsCard(int $branchId, bool $includeBranchField): string
    {
        $baseUrl = $this->repo->getBranchSetting($branchId, 'base_url', 'https://api.mokapos.com');
        $authMode = $this->repo->getBranchSetting($branchId, 'auth_mode', 'basic_api_key');
        $apiKey = $this->repo->getBranchSetting($branchId, 'api_key');
        $clientId = $this->repo->getBranchSetting($branchId, 'client_id');
        $clientSecret = $this->repo->getBranchSetting($branchId, 'client_secret');
        $accessToken = $this->repo->getBranchSetting($branchId, 'access_token');
        $merchantId = $this->repo->getBranchSetting($branchId, 'merchant_id');
        $outletId = $this->repo->getBranchSetting($branchId, 'outlet_id');
        $webhookSecret = $this->repo->getBranchSetting($branchId, 'webhook_secret');
        $ordersPath = $this->repo->getBranchSetting($branchId, 'orders_path', '/v1/orders');
        $productsPath = $this->repo->getBranchSetting($branchId, 'products_path', '/v1/products');
        $customersPath = $this->repo->getBranchSetting($branchId, 'customers_path', '/v1/customers');
        $outletsPath = $this->repo->getBranchSetting($branchId, 'outlets_path', '/v1/outlets');
        $tokenPath = $this->repo->getBranchSetting($branchId, 'token_path', '/oauth/token');
        $syncOrders = $this->repo->getBranchSetting($branchId, 'sync_orders', '1') === '1';
        $syncProducts = $this->repo->getBranchSetting($branchId, 'sync_products', '1') === '1';
        $syncCustomers = $this->repo->getBranchSetting($branchId, 'sync_customers', '1') === '1';
        $syncOutlets = $this->repo->getBranchSetting($branchId, 'sync_outlets', '1') === '1';
        $liveOrderPush = $this->repo->getBranchSetting($branchId, 'live_order_push', '1') === '1';
        $liveCatalogPull = $this->repo->getBranchSetting($branchId, 'live_catalog_pull', '1') === '1';
        $maxRetries = $this->repo->getBranchSetting($branchId, 'max_retries', '3');
        $retryDelaySeconds = $this->repo->getBranchSetting($branchId, 'retry_delay_seconds', '300');
        $outOrderKey = $this->repo->getBranchSetting($branchId, 'map_out_order_key', 'order');
        $outOrderIdKey = $this->repo->getBranchSetting($branchId, 'map_out_order_id_key', 'external_order_id');
        $outReceiptKey = $this->repo->getBranchSetting($branchId, 'map_out_receipt_key', 'receipt_number');
        $outStatusKey = $this->repo->getBranchSetting($branchId, 'map_out_status_key', 'status');
        $outCustomerKey = $this->repo->getBranchSetting($branchId, 'map_out_customer_key', 'customer');
        $outPaymentKey = $this->repo->getBranchSetting($branchId, 'map_out_payment_key', 'payment');
        $outTotalsKey = $this->repo->getBranchSetting($branchId, 'map_out_totals_key', 'totals');
        $outLineItemsKey = $this->repo->getBranchSetting($branchId, 'map_out_line_items_key', 'line_items');
        $outMetadataKey = $this->repo->getBranchSetting($branchId, 'map_out_metadata_key', 'metadata');
        $outFulfillmentKey = $this->repo->getBranchSetting($branchId, 'map_out_fulfillment_key', 'fulfillment');
        $inOrderNumberPath = $this->repo->getBranchSetting($branchId, 'map_in_order_number_path', 'order.external_order_id|order.receipt_number|external_order_id|receipt_number|order_number');
        $inExternalRefPath = $this->repo->getBranchSetting($branchId, 'map_in_external_ref_path', 'id|order.id|external_id');
        $inOrderStatusPath = $this->repo->getBranchSetting($branchId, 'map_in_order_status_path', 'order.status|status|order_status');
        $inPaymentStatusPath = $this->repo->getBranchSetting($branchId, 'map_in_payment_status_path', 'order.payment.status|payment.status|payment_status');
        $orderStatusPairs = $this->repo->getBranchSetting($branchId, 'map_order_status_pairs', "pending=pending\nopen=confirmed\nprocessing=preparing\nclosed=completed\ncancelled=cancelled");
        $paymentStatusPairs = $this->repo->getBranchSetting($branchId, 'map_payment_status_pairs', "paid=paid\nunpaid=unpaid\npending=unpaid\nfailed=failed\nexpired=failed");
        $isActive = $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
        $webhookUrl = BASE_URL . '/api/plugins/moka/webhook.php?branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Moka Connect / Private Solution</div>
          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.84rem;line-height:1.7">
            Konektor ini menyiapkan autentikasi, queue sinkronisasi, push order ke Moka, pull katalog produk, retry policy, dan monitoring status per order dari dashboard.
            <br><br>
            <strong>Catatan integrasi</strong><br>
            Endpoint final dan skema payload Moka bisa berbeda sesuai approval Private Solution. Karena itu field mapping di plugin ini dibuat eksplisit dan masih bisa disesuaikan lewat path endpoint serta mode auth.
            <br><br>
            <strong>Webhook inbound</strong><br>
            <code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code>
          </div>

          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::slug() ?>">
            <?php if ($includeBranchField): ?>
            <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
            <?php endif; ?>
            <input type="hidden" name="is_active" value="0">
            <input type="hidden" name="sync_orders" value="0">
            <input type="hidden" name="sync_products" value="0">
            <input type="hidden" name="sync_customers" value="0">
            <input type="hidden" name="sync_outlets" value="0">
            <input type="hidden" name="live_order_push" value="0">
            <input type="hidden" name="live_catalog_pull" value="0">

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan konektor Moka untuk cabang ini</span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_base_url_<?= (int)$branchId ?>">API Base URL</label>
                <input type="url" id="moka_base_url_<?= (int)$branchId ?>" name="base_url" class="form-control"
                       value="<?= htmlspecialchars($baseUrl) ?>"
                       placeholder="https://api.mokapos.com">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_auth_mode_<?= (int)$branchId ?>">Authentication Mode</label>
                <select id="moka_auth_mode_<?= (int)$branchId ?>" name="auth_mode" class="form-control">
                  <option value="basic_api_key" <?= $authMode === 'basic_api_key' ? 'selected' : '' ?>>Basic API Key</option>
                  <option value="oauth2_client" <?= $authMode === 'oauth2_client' ? 'selected' : '' ?>>OAuth2 Client Credentials</option>
                  <option value="bearer_token" <?= $authMode === 'bearer_token' ? 'selected' : '' ?>>Bearer Token Manual</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_api_key_<?= (int)$branchId ?>">API Key</label>
                <input type="password" id="moka_api_key_<?= (int)$branchId ?>" name="api_key" class="form-control"
                       value="<?= htmlspecialchars($apiKey) ?>"
                       placeholder="private-api-key">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_access_token_<?= (int)$branchId ?>">Access Token</label>
                <input type="password" id="moka_access_token_<?= (int)$branchId ?>" name="access_token" class="form-control"
                       value="<?= htmlspecialchars($accessToken) ?>"
                       placeholder="optional bearer token">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_client_id_<?= (int)$branchId ?>">Client ID</label>
                <input type="text" id="moka_client_id_<?= (int)$branchId ?>" name="client_id" class="form-control"
                       value="<?= htmlspecialchars($clientId) ?>"
                       placeholder="moka_client_id">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_client_secret_<?= (int)$branchId ?>">Client Secret</label>
                <input type="password" id="moka_client_secret_<?= (int)$branchId ?>" name="client_secret" class="form-control"
                       value="<?= htmlspecialchars($clientSecret) ?>"
                       placeholder="moka_client_secret">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_merchant_id_<?= (int)$branchId ?>">Merchant ID</label>
                <input type="text" id="moka_merchant_id_<?= (int)$branchId ?>" name="merchant_id" class="form-control"
                       value="<?= htmlspecialchars($merchantId) ?>"
                       placeholder="merchant-id">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_outlet_id_<?= (int)$branchId ?>">Outlet ID</label>
                <input type="text" id="moka_outlet_id_<?= (int)$branchId ?>" name="outlet_id" class="form-control"
                       value="<?= htmlspecialchars($outletId) ?>"
                       placeholder="outlet-id">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="moka_webhook_secret_<?= (int)$branchId ?>">Webhook Secret</label>
              <input type="text" id="moka_webhook_secret_<?= (int)$branchId ?>" name="webhook_secret" class="form-control"
                     value="<?= htmlspecialchars($webhookSecret) ?>"
                     placeholder="shared secret opsional">
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_orders_path_<?= (int)$branchId ?>">Orders Path</label>
                <input type="text" id="moka_orders_path_<?= (int)$branchId ?>" name="orders_path" class="form-control"
                       value="<?= htmlspecialchars($ordersPath) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_products_path_<?= (int)$branchId ?>">Products Path</label>
                <input type="text" id="moka_products_path_<?= (int)$branchId ?>" name="products_path" class="form-control"
                       value="<?= htmlspecialchars($productsPath) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_customers_path_<?= (int)$branchId ?>">Customers Path</label>
                <input type="text" id="moka_customers_path_<?= (int)$branchId ?>" name="customers_path" class="form-control"
                       value="<?= htmlspecialchars($customersPath) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_outlets_path_<?= (int)$branchId ?>">Outlets Path</label>
                <input type="text" id="moka_outlets_path_<?= (int)$branchId ?>" name="outlets_path" class="form-control"
                       value="<?= htmlspecialchars($outletsPath) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_token_path_<?= (int)$branchId ?>">OAuth Token Path</label>
                <input type="text" id="moka_token_path_<?= (int)$branchId ?>" name="token_path" class="form-control"
                       value="<?= htmlspecialchars($tokenPath) ?>"
                       placeholder="/oauth/token">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_max_retries_<?= (int)$branchId ?>">Max Retry Order</label>
                <input type="number" id="moka_max_retries_<?= (int)$branchId ?>" name="max_retries" class="form-control"
                       min="0" max="20" value="<?= htmlspecialchars($maxRetries) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_retry_delay_<?= (int)$branchId ?>">Retry Delay (detik)</label>
                <input type="number" id="moka_retry_delay_<?= (int)$branchId ?>" name="retry_delay_seconds" class="form-control"
                       min="30" max="86400" value="<?= htmlspecialchars($retryDelaySeconds) ?>">
              </div>
              <div class="form-group">
                <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;font-size:.82rem;line-height:1.6;margin-top:24px">
                  <strong>Default mapping order</strong><br>
                  <code>order_number -> external_order_id</code><br>
                  <code>items[] -> line_items[]</code><br>
                  <code>subtotal/discount/tax/total -> totals.*</code><br>
                  <code>customer_* -> customer.*</code>
                </div>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="sync_orders" value="1" <?= $syncOrders ? 'checked' : '' ?>>
                  <span>Sinkronkan event order</span>
                </label>
              </div>
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="sync_products" value="1" <?= $syncProducts ? 'checked' : '' ?>>
                  <span>Sinkronkan katalog produk</span>
                </label>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="sync_customers" value="1" <?= $syncCustomers ? 'checked' : '' ?>>
                  <span>Sinkronkan customer</span>
                </label>
              </div>
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="sync_outlets" value="1" <?= $syncOutlets ? 'checked' : '' ?>>
                  <span>Sinkronkan outlet</span>
                </label>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="live_order_push" value="1" <?= $liveOrderPush ? 'checked' : '' ?>>
                  <span>Push order live saat queue dibuat</span>
                </label>
              </div>
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="live_catalog_pull" value="1" <?= $liveCatalogPull ? 'checked' : '' ?>>
                  <span>Izinkan pull katalog live dari dashboard</span>
                </label>
              </div>
            </div>

            <div style="margin:18px 0 10px;font-weight:700;color:var(--coffee-dark)">Mapping UI</div>
            <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
              Ubah key outbound dan path inbound di sini tanpa perlu edit code. Gunakan format path seperti <code>order.status</code> atau beberapa fallback dipisahkan <code>|</code>.
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_map_out_order_key_<?= (int)$branchId ?>">Outbound Order Container</label>
                <input type="text" id="moka_map_out_order_key_<?= (int)$branchId ?>" name="map_out_order_key" class="form-control" value="<?= htmlspecialchars($outOrderKey) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_map_out_order_id_key_<?= (int)$branchId ?>">Outbound Order ID Key</label>
                <input type="text" id="moka_map_out_order_id_key_<?= (int)$branchId ?>" name="map_out_order_id_key" class="form-control" value="<?= htmlspecialchars($outOrderIdKey) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_map_out_receipt_key_<?= (int)$branchId ?>">Outbound Receipt Key</label>
                <input type="text" id="moka_map_out_receipt_key_<?= (int)$branchId ?>" name="map_out_receipt_key" class="form-control" value="<?= htmlspecialchars($outReceiptKey) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_map_out_status_key_<?= (int)$branchId ?>">Outbound Status Key</label>
                <input type="text" id="moka_map_out_status_key_<?= (int)$branchId ?>" name="map_out_status_key" class="form-control" value="<?= htmlspecialchars($outStatusKey) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_map_out_customer_key_<?= (int)$branchId ?>">Outbound Customer Key</label>
                <input type="text" id="moka_map_out_customer_key_<?= (int)$branchId ?>" name="map_out_customer_key" class="form-control" value="<?= htmlspecialchars($outCustomerKey) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_map_out_payment_key_<?= (int)$branchId ?>">Outbound Payment Key</label>
                <input type="text" id="moka_map_out_payment_key_<?= (int)$branchId ?>" name="map_out_payment_key" class="form-control" value="<?= htmlspecialchars($outPaymentKey) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_map_out_totals_key_<?= (int)$branchId ?>">Outbound Totals Key</label>
                <input type="text" id="moka_map_out_totals_key_<?= (int)$branchId ?>" name="map_out_totals_key" class="form-control" value="<?= htmlspecialchars($outTotalsKey) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_map_out_line_items_key_<?= (int)$branchId ?>">Outbound Line Items Key</label>
                <input type="text" id="moka_map_out_line_items_key_<?= (int)$branchId ?>" name="map_out_line_items_key" class="form-control" value="<?= htmlspecialchars($outLineItemsKey) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_map_out_metadata_key_<?= (int)$branchId ?>">Outbound Metadata Key</label>
                <input type="text" id="moka_map_out_metadata_key_<?= (int)$branchId ?>" name="map_out_metadata_key" class="form-control" value="<?= htmlspecialchars($outMetadataKey) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_map_out_fulfillment_key_<?= (int)$branchId ?>">Outbound Fulfillment Key</label>
                <input type="text" id="moka_map_out_fulfillment_key_<?= (int)$branchId ?>" name="map_out_fulfillment_key" class="form-control" value="<?= htmlspecialchars($outFulfillmentKey) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_map_in_order_number_path_<?= (int)$branchId ?>">Inbound Order Number Path</label>
                <input type="text" id="moka_map_in_order_number_path_<?= (int)$branchId ?>" name="map_in_order_number_path" class="form-control" value="<?= htmlspecialchars($inOrderNumberPath) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_map_in_external_ref_path_<?= (int)$branchId ?>">Inbound External Ref Path</label>
                <input type="text" id="moka_map_in_external_ref_path_<?= (int)$branchId ?>" name="map_in_external_ref_path" class="form-control" value="<?= htmlspecialchars($inExternalRefPath) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_map_in_order_status_path_<?= (int)$branchId ?>">Inbound Order Status Path</label>
                <input type="text" id="moka_map_in_order_status_path_<?= (int)$branchId ?>" name="map_in_order_status_path" class="form-control" value="<?= htmlspecialchars($inOrderStatusPath) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_map_in_payment_status_path_<?= (int)$branchId ?>">Inbound Payment Status Path</label>
                <input type="text" id="moka_map_in_payment_status_path_<?= (int)$branchId ?>" name="map_in_payment_status_path" class="form-control" value="<?= htmlspecialchars($inPaymentStatusPath) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_map_order_status_pairs_<?= (int)$branchId ?>">Order Status Map</label>
                <textarea id="moka_map_order_status_pairs_<?= (int)$branchId ?>" name="map_order_status_pairs" class="form-control" rows="5" placeholder="open=confirmed&#10;closed=completed"><?= htmlspecialchars($orderStatusPairs) ?></textarea>
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_map_payment_status_pairs_<?= (int)$branchId ?>">Payment Status Map</label>
                <textarea id="moka_map_payment_status_pairs_<?= (int)$branchId ?>" name="map_payment_status_pairs" class="form-control" rows="5" placeholder="paid=paid&#10;pending=unpaid"><?= htmlspecialchars($paymentStatusPairs) ?></textarea>
              </div>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan Moka</button>
          </form>
        </div>
        <?php

        return ob_get_clean();
    }

    private function renderGlobalSettingsCard(): string
    {
        $mode = $this->repo->getGlobalSetting('connection_mode', 'sandbox');
        $timeout = $this->repo->getGlobalSetting('timeout_seconds', '15');
        $verifySsl = $this->repo->getGlobalSetting('verify_ssl', '1') === '1';
        $runnerToken = $this->repo->getGlobalSetting('runner_token');
        $runnerUrl = BASE_URL . '/api/plugins/moka/process-queue.php?token=' . rawurlencode($runnerToken !== '' ? $runnerToken : 'isi-token-runner');

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Moka Global Defaults</div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_global_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::slug() ?>">
            <input type="hidden" name="verify_ssl" value="0">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="moka_connection_mode">Connection Mode</label>
                <select id="moka_connection_mode" name="connection_mode" class="form-control">
                  <option value="sandbox" <?= $mode === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                  <option value="production" <?= $mode === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="moka_timeout_seconds">Timeout (detik)</label>
                <input type="number" id="moka_timeout_seconds" name="timeout_seconds" class="form-control"
                       min="5" max="120" value="<?= htmlspecialchars($timeout) ?>">
              </div>
            </div>

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="verify_ssl" value="1" <?= $verifySsl ? 'checked' : '' ?>>
                <span>Verifikasi SSL saat request HTTP</span>
              </label>
            </div>

            <div class="form-group">
              <label class="form-label" for="moka_runner_token">Runner Token</label>
              <input type="text" id="moka_runner_token" name="runner_token" class="form-control"
                     value="<?= htmlspecialchars($runnerToken) ?>"
                     placeholder="token-rahasia-untuk-cron">
              <small style="color:var(--text-light)">Gunakan token ini untuk job runner otomatis.</small>
            </div>

            <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
              <strong>URL job runner</strong><br>
              <code style="word-break:break-all"><?= htmlspecialchars($runnerUrl) ?></code><br>
              Contoh cron:
              <code>curl "<?= htmlspecialchars($runnerUrl) ?>"</code>
            </div>

            <button type="submit" class="btn btn-outline">Simpan Default Global</button>
          </form>
        </div>
        <?php

        return ob_get_clean();
    }

    private static function slug(): string
    {
        return MokaConnectRepository::PLUGIN_SLUG;
    }
}
