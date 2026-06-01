<?php

declare(strict_types=1);

use App\Helpers\Csrf;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class WooCommerceConnectorPlugin implements PluginInterface
{
    private WooCommerceConnectorRepository $repo;
    private WooCommerceConnectorService $service;

    public function __construct()
    {
        $this->repo = new WooCommerceConnectorRepository();
        $this->service = new WooCommerceConnectorService($this->repo);
    }

    public function getName(): string
    {
        return 'WooCommerce Connector';
    }

    public function getVersion(): string
    {
        return '0.2.0';
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
                'url' => '/dashboard/super/woocommerce.php',
                'icon' => 'WC',
                'label' => 'WooCommerce Connector',
            ];
        }

        if ($role === 'branch_admin') {
            $items['Integrations'][] = [
                'url' => '/dashboard/branch/woocommerce.php',
                'icon' => 'WC',
                'label' => 'WooCommerce Connector',
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
        if ($this->repo->getBranchSetting((int)($order['branch_id'] ?? 0), 'sync_orders', '1') !== '1') {
            return;
        }

        $this->service->queueOrderSync($order, 'order.created');
    }

    public function handleOrderStatusChanged(array $order, string $oldStatus, string $newStatus): void
    {
        if ($this->repo->getBranchSetting((int)($order['branch_id'] ?? 0), 'sync_orders', '1') !== '1') {
            return;
        }

        $order['status_transition'] = ['from' => $oldStatus, 'to' => $newStatus];
        $this->service->queueOrderSync($order, 'order.status_changed');
    }

    public function handlePaymentUpdated(array $order, string $paymentStatus): void
    {
        if ($this->repo->getBranchSetting((int)($order['branch_id'] ?? 0), 'sync_orders', '1') !== '1') {
            return;
        }

        $order['payment_status'] = $paymentStatus;
        $this->service->queueOrderSync($order, 'order.payment_updated');
    }

    private function renderBranchSettingsCard(int $branchId, bool $includeBranchField): string
    {
        $baseUrl = $this->repo->getBranchSetting($branchId, 'base_url');
        $storeUrl = $this->repo->getBranchSetting($branchId, 'store_url');
        $consumerKey = $this->repo->getBranchSetting($branchId, 'consumer_key');
        $consumerSecret = $this->repo->getBranchSetting($branchId, 'consumer_secret');
        $webhookSecret = $this->repo->getBranchSetting($branchId, 'webhook_secret');
        $syncOrders = $this->repo->getBranchSetting($branchId, 'sync_orders', '1') === '1';
        $syncProducts = $this->repo->getBranchSetting($branchId, 'sync_products', '1') === '1';
        $liveOrderPush = $this->repo->getBranchSetting($branchId, 'live_order_push', '1') === '1';
        $liveCatalogPull = $this->repo->getBranchSetting($branchId, 'live_catalog_pull', '1') === '1';
        $isActive = $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
        $statusMap = $this->repo->getBranchSetting($branchId, 'order_status_map', 'processing:processing,completed:completed,cancelled:cancelled,on-hold:pending,pending:pending');
        $paymentMap = $this->repo->getBranchSetting($branchId, 'payment_status_map', 'paid:paid,pending:pending,failed:failed,refunded:refunded');
        $webhookUrl = BASE_URL . '/api/plugins/woocommerce/webhook.php?branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">WooCommerce Connector</div>
          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.84rem;line-height:1.7">
            Hubungkan cabang ini ke WooCommerce untuk sinkron katalog, push order, dan inbound webhook status.
            Tahap saat ini sudah mendukung koneksi API, live catalog pull, order push berbasis mapping, dan webhook status order/payment.
            <br><br>
            <strong>Webhook endpoint</strong><br>
            <code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code>
            <br><br>
            Jika webhook WooCommerce memakai <strong>Secret</strong>, sistem akan memverifikasi header
            <code>X-WC-Webhook-Signature</code> menggunakan HMAC-SHA256 berbasis raw payload.
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
            <input type="hidden" name="live_order_push" value="0">
            <input type="hidden" name="live_catalog_pull" value="0">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="woo_base_url_<?= (int)$branchId ?>">REST API Base URL</label>
                <input type="url" id="woo_base_url_<?= (int)$branchId ?>" name="base_url" class="form-control"
                       value="<?= htmlspecialchars($baseUrl) ?>"
                       placeholder="https://example.com/wp-json/wc/v3">
              </div>
              <div class="form-group">
                <label class="form-label" for="woo_store_url_<?= (int)$branchId ?>">Store URL</label>
                <input type="url" id="woo_store_url_<?= (int)$branchId ?>" name="store_url" class="form-control"
                       value="<?= htmlspecialchars($storeUrl) ?>"
                       placeholder="https://example.com">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="woo_consumer_key_<?= (int)$branchId ?>">Consumer Key</label>
                <input type="password" id="woo_consumer_key_<?= (int)$branchId ?>" name="consumer_key" class="form-control"
                       value="<?= htmlspecialchars($consumerKey) ?>"
                       placeholder="ck_xxxxxxxxx">
              </div>
              <div class="form-group">
                <label class="form-label" for="woo_consumer_secret_<?= (int)$branchId ?>">Consumer Secret</label>
                <input type="password" id="woo_consumer_secret_<?= (int)$branchId ?>" name="consumer_secret" class="form-control"
                       value="<?= htmlspecialchars($consumerSecret) ?>"
                       placeholder="cs_xxxxxxxxx">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="woo_webhook_secret_<?= (int)$branchId ?>">Webhook Secret</label>
              <input type="text" id="woo_webhook_secret_<?= (int)$branchId ?>" name="webhook_secret" class="form-control"
                     value="<?= htmlspecialchars($webhookSecret) ?>"
                     placeholder="opsional untuk proteksi endpoint">
            </div>

            <div class="form-group">
              <label class="form-label" for="woo_status_map_<?= (int)$branchId ?>">Order Status Mapping</label>
              <textarea id="woo_status_map_<?= (int)$branchId ?>" name="order_status_map" class="form-control" rows="3"
                        placeholder="processing:processing,completed:completed,cancelled:cancelled,on-hold:pending,pending:pending"><?= htmlspecialchars($statusMap) ?></textarea>
              <small style="color:var(--text-mid);font-size:.8rem">Format: <code>status_woocommerce:status_internal</code>, pisahkan dengan koma.</small>
            </div>

            <div class="form-group">
              <label class="form-label" for="woo_payment_map_<?= (int)$branchId ?>">Payment Status Mapping</label>
              <textarea id="woo_payment_map_<?= (int)$branchId ?>" name="payment_status_map" class="form-control" rows="3"
                        placeholder="paid:paid,pending:pending,failed:failed,refunded:refunded"><?= htmlspecialchars($paymentMap) ?></textarea>
              <small style="color:var(--text-mid);font-size:.8rem">Format: <code>status_remote:status_payment_internal</code>.</small>
            </div>

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan konektor WooCommerce untuk cabang ini</span>
              </label>
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
                  <input type="checkbox" name="live_order_push" value="1" <?= $liveOrderPush ? 'checked' : '' ?>>
                  <span>Aktifkan push order live ke WooCommerce</span>
                </label>
              </div>
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="live_catalog_pull" value="1" <?= $liveCatalogPull ? 'checked' : '' ?>>
                  <span>Aktifkan pull katalog live dari WooCommerce</span>
                </label>
              </div>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan WooCommerce</button>
          </form>
        </div>
        <?php

        return ob_get_clean();
    }

    private function renderGlobalSettingsCard(): string
    {
        $mode = $this->repo->getGlobalSetting('connection_mode', 'sandbox');
        $timeout = $this->repo->getGlobalSetting('timeout_seconds', '15');
        $batch = $this->repo->getGlobalSetting('batch_limit', '50');

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">WooCommerce Global Defaults</div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_global_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::slug() ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="woo_connection_mode">Connection Mode</label>
                <select id="woo_connection_mode" name="connection_mode" class="form-control">
                  <option value="sandbox" <?= $mode === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                  <option value="production" <?= $mode === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="woo_timeout_seconds">Timeout (detik)</label>
                <input type="number" id="woo_timeout_seconds" name="timeout_seconds" class="form-control"
                       min="5" max="120" value="<?= htmlspecialchars($timeout) ?>">
              </div>
            </div>

            <div class="form-group" style="max-width:220px">
              <label class="form-label" for="woo_batch_limit">Batch Limit</label>
              <input type="number" id="woo_batch_limit" name="batch_limit" class="form-control"
                     min="1" max="500" value="<?= htmlspecialchars($batch) ?>">
            </div>

            <button type="submit" class="btn btn-outline">Simpan Global Defaults</button>
          </form>
        </div>
        <?php

        return ob_get_clean();
    }

    public static function slug(): string
    {
        return WooCommerceConnectorRepository::PLUGIN_SLUG;
    }
}
