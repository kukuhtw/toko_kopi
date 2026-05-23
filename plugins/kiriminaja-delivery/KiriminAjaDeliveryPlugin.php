<?php

declare(strict_types=1);

use App\Helpers\Csrf;
use App\Models\BranchModel;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class KiriminAjaDeliveryPlugin implements PluginInterface
{
    private KiriminAjaDeliveryRepository $repo;
    private KiriminAjaDeliveryService $service;

    public function __construct()
    {
        $this->repo = new KiriminAjaDeliveryRepository();
        $this->service = new KiriminAjaDeliveryService($this->repo);
    }

    public function getName(): string { return 'KiriminAja Delivery'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'Codex'; }

    public function register(): void
    {
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 19);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 19);
        HookManager::addFilter('cart.before_checkout', [$this, 'enrichCheckoutData'], 19);
        HookManager::addFilter('order.before_create', [$this, 'appendOrderData'], 19);
        HookManager::addFilter('order.checkout_response', [$this, 'appendCheckoutResponse'], 19);
    }

    public function addBranchSettingsSection(array $sections, int $branchId): array
    {
        $sections[KiriminAjaDeliveryRepository::PLUGIN_SLUG] = $this->renderBranchSettingsCard($branchId);
        return $sections;
    }

    public function addSuperSettingsSection(array $sections, int $branchId): array
    {
        $sections[KiriminAjaDeliveryRepository::PLUGIN_SLUG] = $this->renderSuperSettingsCard($branchId);
        return $sections;
    }

    public function enrichCheckoutData(array $customerData, array $cart, array $items, int $branchId): array
    {
        return $this->service->calculateForCheckout($branchId, $cart, $items, $customerData);
    }

    public function appendOrderData(array $orderData, array $cart, array $items, array $customerData, int $customerId, float $ppnRate): array
    {
        return $this->service->appendOrderData($orderData, $cart, $items, $customerData, $customerId, $ppnRate);
    }

    public function appendCheckoutResponse(array $responseData, array $order, int $branchId): array
    {
        return $this->service->appendCheckoutResponse($responseData, $order, $branchId);
    }

    private function renderSuperSettingsCard(int $branchId): string
    {
        $branchModel = new BranchModel();
        $selectedBranch = $branchModel->find($branchId);
        $branchName = trim((string)($selectedBranch['name'] ?? 'Cabang #' . $branchId));
        $isActive = $this->service->isImplementedForBranch($branchId);
        $mode = $this->repo->getBranchSetting($branchId, 'mode', 'sandbox');
        $baseUrl = $this->repo->getBranchSetting($branchId, 'base_url', 'https://api.kiriminaja.com');
        $apiKey = $this->repo->getBranchSetting($branchId, 'api_key');
        $courierLabel = $this->repo->getBranchSetting($branchId, 'courier_label', 'KiriminAja');
        $serviceLabel = $this->repo->getBranchSetting($branchId, 'service_label', 'Delivery Fee');
        $client = $this->service->getClient($branchId)->getOverview();

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">KiriminAja Delivery</div>
          <div style="margin-bottom:14px">
            <div style="font-size:.78rem;color:var(--text-light,#6b7280);margin-bottom:6px">Cabang yang sedang diatur</div>
            <div style="font-weight:700;font-size:1rem"><?= htmlspecialchars($branchName) ?></div>
          </div>
          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
            Super admin menentukan cabang mana yang menggunakan plugin ini. Saat aktif di <strong><?= htmlspecialchars($branchName) ?></strong>,
            flow order <strong>delivery ke alamat</strong> akan menambahkan komponen <code>delivery_fee</code>.
            Admin cabang tetap bisa mengatur <strong>default fee delivery</strong> masing-masing dari dashboard cabang.
          </div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= KiriminAjaDeliveryRepository::PLUGIN_SLUG ?>">
            <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
            <input type="hidden" name="is_active" value="0">

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan KiriminAja untuk <?= htmlspecialchars($branchName) ?></span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Mode</label>
                <select name="mode" class="form-control">
                  <option value="sandbox" <?= $mode === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                  <option value="production" <?= $mode === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Base URL API</label>
                <input type="text" name="base_url" class="form-control" value="<?= htmlspecialchars($baseUrl) ?>" placeholder="https://api.kiriminaja.com">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">API Key</label>
                <input type="password" name="api_key" class="form-control" value="<?= htmlspecialchars($apiKey) ?>" placeholder="API key dari KiriminAja">
              </div>
              <div class="form-group">
                <label class="form-label">Courier Label</label>
                <input type="text" name="courier_label" class="form-control" value="<?= htmlspecialchars($courierLabel) ?>" placeholder="KiriminAja">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Service Label</label>
              <input type="text" name="service_label" class="form-control" value="<?= htmlspecialchars($serviceLabel) ?>" placeholder="Delivery Fee">
            </div>

            <div style="background:#eef7fb;border:1px solid #cfe8f3;border-radius:10px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.65">
              <strong>Status integrasi</strong><br>
              Base URL: <code><?= htmlspecialchars($client['base_url'] ?? '') ?></code><br>
              API Key: <?= !empty($client['has_api_key']) ? 'tersedia' : 'belum diisi' ?><br>
              Dokumentasi resmi: <a href="https://developer.kiriminaja.com/docs/introduction" target="_blank" rel="noopener">developer.kiriminaja.com/docs/introduction</a>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan KiriminAja</button>
          </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderBranchSettingsCard(int $branchId): string
    {
        $isActive = $this->service->isImplementedForBranch($branchId);
        $defaultFee = $this->repo->getBranchSetting($branchId, 'default_delivery_fee', '0');
        $courierLabel = $this->repo->getBranchSetting($branchId, 'courier_label', 'KiriminAja');
        $serviceLabel = $this->repo->getBranchSetting($branchId, 'service_label', 'Delivery Fee');
        $branchModel = new BranchModel();
        $selectedBranch = $branchModel->find($branchId);
        $branchName = trim((string)($selectedBranch['name'] ?? 'Cabang #' . $branchId));

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">KiriminAja Delivery</div>
          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
            <?php if ($isActive): ?>
              Plugin ini aktif untuk <strong><?= htmlspecialchars($branchName) ?></strong>. Isi <strong>default fee delivery</strong> di bawah agar biaya delivery otomatis masuk saat checkout alamat.
            <?php else: ?>
              Plugin KiriminAja belum aktif untuk <strong><?= htmlspecialchars($branchName) ?></strong>. Anda tetap bisa menyimpan <strong>default fee delivery</strong> sebagai draft,
              dan nilainya akan langsung dipakai saat super admin mengaktifkan <strong><?= htmlspecialchars($branchName) ?></strong>.
            <?php endif; ?>
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:.82rem">
            <span style="display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;<?= $isActive ? 'background:#e8f7ed;color:#1f7a3d' : 'background:#fff4e5;color:#9a5b00' ?>">
              <?= $isActive ? ('Aktif di ' . htmlspecialchars($branchName)) : ('Belum aktif di ' . htmlspecialchars($branchName)) ?>
            </span>
          </div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= KiriminAjaDeliveryRepository::PLUGIN_SLUG ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Default Fee Delivery</label>
                <input type="number" min="0" step="0.01" name="default_delivery_fee" class="form-control" value="<?= htmlspecialchars($defaultFee) ?>" placeholder="0">
              </div>
              <div class="form-group">
                <label class="form-label">Courier Label</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($courierLabel) ?>" readonly>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Service Label</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($serviceLabel) ?>" readonly>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Default Fee Cabang</button>
          </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
