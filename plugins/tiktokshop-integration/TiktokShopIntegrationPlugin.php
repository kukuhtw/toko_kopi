<?php

declare(strict_types=1);

use KopiBot\Contracts\PluginInterface;
use KopiBot\Core\HookManager;
use KopiBot\Security\Csrf;

final class TiktokShopIntegrationPlugin implements PluginInterface
{
    private TiktokShopIntegrationRepository $repo;
    private TiktokShopIntegrationService $service;

    public function __construct()
    {
        $this->repo = new TiktokShopIntegrationRepository();
        $this->service = new TiktokShopIntegrationService($this->repo);
    }

    public function getName(): string
    {
        return 'TikTok Shop Integration';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'Codex';
    }

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
            $items['Integrations'][] = ['url' => '/dashboard/super/tiktokshop.php', 'icon' => 'TT', 'label' => 'TikTok Shop'];
        }
        if ($role === 'branch_admin') {
            $items['Integrations'][] = ['url' => '/dashboard/branch/tiktokshop.php', 'icon' => 'TT', 'label' => 'TikTok Shop'];
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
        $this->service->queueOrderSync($order);
    }

    public static function slug(): string
    {
        return TiktokShopIntegrationRepository::PLUGIN_SLUG;
    }

    private function renderGlobalSettingsCard(): string
    {
        $mode = $this->repo->getGlobalSetting('connection_mode', 'sandbox');
        $timeout = $this->repo->getGlobalSetting('timeout_seconds', '20');
        $verifyWebhook = $this->repo->getGlobalSetting('verify_webhook_signature', '1') === '1';

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">TikTok Shop Global Runtime</div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_global_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::slug() ?>">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Connection Mode</label>
                <select name="<?= self::slug() ?>_connection_mode" class="form-control">
                  <option value="sandbox" <?= $mode === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                  <option value="staging" <?= $mode === 'staging' ? 'selected' : '' ?>>Staging</option>
                  <option value="production" <?= $mode === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Timeout (detik)</label>
                <input type="number" name="<?= self::slug() ?>_timeout_seconds" class="form-control" min="5" max="120" value="<?= htmlspecialchars($timeout) ?>">
              </div>
            </div>
            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="hidden" name="<?= self::slug() ?>_verify_webhook_signature" value="0">
                <input type="checkbox" name="<?= self::slug() ?>_verify_webhook_signature" value="1" <?= $verifyWebhook ? 'checked' : '' ?>>
                <span>Verifikasi signature webhook</span>
              </label>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Runtime TikTok Shop</button>
          </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderBranchSettingsCard(int $branchId, bool $includeBranchField): string
    {
        $isActive = $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
        $shopCipher = $this->repo->getBranchSetting($branchId, 'shop_cipher');
        $appKey = $this->repo->getBranchSetting($branchId, 'app_key');
        $appSecret = $this->repo->getBranchSetting($branchId, 'app_secret');
        $accessToken = $this->repo->getBranchSetting($branchId, 'access_token');
        $warehouseId = $this->repo->getBranchSetting($branchId, 'warehouse_id');
        $webhookSecret = $this->repo->getBranchSetting($branchId, 'webhook_secret');
        $syncProducts = $this->repo->getBranchSetting($branchId, 'sync_products', '1') === '1';
        $syncStock = $this->repo->getBranchSetting($branchId, 'sync_stock', '1') === '1';
        $syncOrders = $this->repo->getBranchSetting($branchId, 'sync_orders', '1') === '1';
        $overview = $this->repo->getBranchOverview($branchId);
        $recentLogs = $this->repo->getRecentLogs($branchId, 6);
        $webhookUrl = BASE_URL . '/api/plugins/tiktokshop/webhook.php?branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">TikTok Shop Integration</div>
          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.84rem;line-height:1.7">
            Scaffold awal untuk integrasi TikTok Shop: OAuth, sinkron produk, stok, order, webhook, dan audit log.
            <br><br>
            <strong>Webhook endpoint</strong><br>
            <code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code>
          </div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::slug() ?>">
            <?php if ($includeBranchField): ?>
            <input type="hidden" name="branch_id" value="<?= (int) $branchId ?>">
            <?php endif; ?>
            <input type="hidden" name="is_active" value="0">
            <input type="hidden" name="sync_products" value="0">
            <input type="hidden" name="sync_stock" value="0">
            <input type="hidden" name="sync_orders" value="0">

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan TikTok Shop untuk cabang ini</span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Shop Cipher</label>
                <input type="text" name="shop_cipher" class="form-control" value="<?= htmlspecialchars($shopCipher) ?>" placeholder="shop cipher dari TikTok Shop">
              </div>
              <div class="form-group">
                <label class="form-label">Warehouse ID</label>
                <input type="text" name="warehouse_id" class="form-control" value="<?= htmlspecialchars($warehouseId) ?>" placeholder="warehouse mapping">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">App Key</label>
                <input type="text" name="app_key" class="form-control" value="<?= htmlspecialchars($appKey) ?>" placeholder="TikTok app key">
              </div>
              <div class="form-group">
                <label class="form-label">App Secret</label>
                <input type="password" name="app_secret" class="form-control" value="<?= htmlspecialchars($appSecret) ?>" placeholder="TikTok app secret">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Access Token</label>
                <input type="text" name="access_token" class="form-control" value="<?= htmlspecialchars($accessToken) ?>" placeholder="token OAuth / app access token">
              </div>
              <div class="form-group">
                <label class="form-label">Webhook Secret</label>
                <input type="text" name="webhook_secret" class="form-control" value="<?= htmlspecialchars($webhookSecret) ?>" placeholder="opsional untuk verifikasi signature">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="sync_products" value="1" <?= $syncProducts ? 'checked' : '' ?>>
                  <span>Sinkron produk</span>
                </label>
              </div>
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="sync_stock" value="1" <?= $syncStock ? 'checked' : '' ?>>
                  <span>Sinkron stok</span>
                </label>
              </div>
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="sync_orders" value="1" <?= $syncOrders ? 'checked' : '' ?>>
                  <span>Sinkron order</span>
                </label>
              </div>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Konfigurasi TikTok Shop</button>
          </form>

          <div style="margin-top:18px;background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px">
            <strong>Ringkasan Sinkronisasi</strong><br>
            Total log: <?= (int) ($overview['total_logs'] ?? 0) ?> |
            Success: <?= (int) ($overview['success_logs'] ?? 0) ?> |
            Pending: <?= (int) ($overview['pending_logs'] ?? 0) ?> |
            Failed: <?= (int) ($overview['failed_logs'] ?? 0) ?>
          </div>

          <div style="margin-top:14px">
            <strong>Log Terbaru</strong>
            <div style="overflow:auto;margin-top:8px">
              <table class="table" style="width:100%;font-size:.9rem">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Order</th>
                    <th>Dibuat</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentLogs as $log): ?>
                  <tr>
                    <td><?= (int) ($log['id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($log['event_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($log['status'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($log['order_number'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($log['created_at'] ?? '')) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if ($recentLogs === []): ?>
                  <tr><td colspan="5">Belum ada log sinkronisasi.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
