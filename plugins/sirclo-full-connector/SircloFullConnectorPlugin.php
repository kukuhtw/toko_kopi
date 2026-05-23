<?php

declare(strict_types=1);

use App\Helpers\Csrf;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class SircloFullConnectorPlugin implements PluginInterface
{
    private SircloConnectorRepository $repo;
    private SircloConnectorService $service;

    public function __construct()
    {
        $this->repo = new SircloConnectorRepository();
        $this->service = new SircloConnectorService($this->repo);
    }

    public function getName(): string
    {
        return 'Sirclo Full Connector';
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
                'url' => '/dashboard/super/sirclo.php',
                'icon' => 'SC',
                'label' => 'Sirclo Connector',
            ];
        }

        if ($role === 'branch_admin') {
            $items['Integrations'][] = [
                'url' => '/dashboard/branch/sirclo.php',
                'icon' => 'SC',
                'label' => 'Sirclo Connector',
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
        $storeId = $this->repo->getBranchSetting($branchId, 'store_id');
        $apiKey = $this->repo->getBranchSetting($branchId, 'api_key');
        $apiSecret = $this->repo->getBranchSetting($branchId, 'api_secret');
        $webhookSecret = $this->repo->getBranchSetting($branchId, 'webhook_secret');
        $syncOrders = $this->repo->getBranchSetting($branchId, 'sync_orders', '1') === '1';
        $syncProducts = $this->repo->getBranchSetting($branchId, 'sync_products', '1') === '1';
        $syncCustomers = $this->repo->getBranchSetting($branchId, 'sync_customers', '1') === '1';
        $isActive = $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
        $statusMap = $this->repo->getBranchSetting($branchId, 'order_status_map', 'pending:new,processing:processing,completed:delivered,cancelled:cancelled');
        $webhookUrl = BASE_URL . '/api/plugins/sirclo/webhook.php?branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Sirclo Full Connector</div>
          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.84rem;line-height:1.7">
            Hubungkan cabang ini ke SIRCLO untuk sinkronisasi order, katalog produk, dan customer.
            Scaffold ini sudah siap menyimpan konfigurasi, mencatat activity log, dan mengantre event order dari sistem inti.
            <br><br>
            <strong>Webhook placeholder</strong><br>
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

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="sirclo_base_url_<?= (int)$branchId ?>">API Base URL</label>
                <input type="url" id="sirclo_base_url_<?= (int)$branchId ?>" name="base_url" class="form-control"
                       value="<?= htmlspecialchars($baseUrl) ?>"
                       placeholder="https://api.sirclo.example">
              </div>
              <div class="form-group">
                <label class="form-label" for="sirclo_store_id_<?= (int)$branchId ?>">Store ID</label>
                <input type="text" id="sirclo_store_id_<?= (int)$branchId ?>" name="store_id" class="form-control"
                       value="<?= htmlspecialchars($storeId) ?>"
                       placeholder="store-123">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="sirclo_api_key_<?= (int)$branchId ?>">API Key</label>
                <input type="password" id="sirclo_api_key_<?= (int)$branchId ?>" name="api_key" class="form-control"
                       value="<?= htmlspecialchars($apiKey) ?>"
                       placeholder="sirclo-api-key">
              </div>
              <div class="form-group">
                <label class="form-label" for="sirclo_api_secret_<?= (int)$branchId ?>">API Secret</label>
                <input type="password" id="sirclo_api_secret_<?= (int)$branchId ?>" name="api_secret" class="form-control"
                       value="<?= htmlspecialchars($apiSecret) ?>"
                       placeholder="sirclo-api-secret">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="sirclo_webhook_secret_<?= (int)$branchId ?>">Webhook Secret</label>
              <input type="text" id="sirclo_webhook_secret_<?= (int)$branchId ?>" name="webhook_secret" class="form-control"
                     value="<?= htmlspecialchars($webhookSecret) ?>"
                     placeholder="optional-shared-secret">
            </div>

            <div class="form-group">
              <label class="form-label" for="sirclo_status_map_<?= (int)$branchId ?>">Order Status Mapping</label>
              <textarea id="sirclo_status_map_<?= (int)$branchId ?>" name="order_status_map" class="form-control" rows="3"
                        placeholder="pending:new,processing:processing,completed:delivered,cancelled:cancelled"><?= htmlspecialchars($statusMap) ?></textarea>
              <small style="color:var(--text-mid);font-size:.8rem">Format per pasangan: <code>status_lokal:status_sirclo</code>, pisahkan dengan koma.</small>
            </div>

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan konektor Sirclo untuk cabang ini</span>
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

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="sync_customers" value="1" <?= $syncCustomers ? 'checked' : '' ?>>
                <span>Sinkronkan data customer</span>
              </label>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan Sirclo</button>
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
          <div class="card-title">Sirclo Global Defaults</div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_global_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::slug() ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="sirclo_connection_mode">Connection Mode</label>
                <select id="sirclo_connection_mode" name="connection_mode" class="form-control">
                  <option value="sandbox" <?= $mode === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                  <option value="production" <?= $mode === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="sirclo_timeout_seconds">Timeout (detik)</label>
                <input type="number" id="sirclo_timeout_seconds" name="timeout_seconds" class="form-control"
                       min="5" max="120" value="<?= htmlspecialchars($timeout) ?>">
              </div>
            </div>

            <div class="form-group" style="max-width:220px">
              <label class="form-label" for="sirclo_batch_limit">Batch Limit</label>
              <input type="number" id="sirclo_batch_limit" name="batch_limit" class="form-control"
                     min="1" max="500" value="<?= htmlspecialchars($batch) ?>">
            </div>

            <button type="submit" class="btn btn-outline">Simpan Default Global</button>
          </form>
        </div>
        <?php

        return ob_get_clean();
    }

    private static function slug(): string
    {
        return SircloConnectorRepository::PLUGIN_SLUG;
    }
}
