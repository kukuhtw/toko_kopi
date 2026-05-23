<?php

declare(strict_types=1);

use App\Helpers\Csrf;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class RajaOngkirDeliveryPlugin implements PluginInterface
{
    private RajaOngkirDeliveryRepository $repo;
    private RajaOngkirDeliveryService $service;

    public function __construct()
    {
        $this->repo = new RajaOngkirDeliveryRepository();
        $this->service = new RajaOngkirDeliveryService($this->repo);
    }

    public function getName(): string { return 'RajaOngkir Delivery'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'Toko Kopi'; }

    public function register(): void
    {
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 17);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 17);
        HookManager::addFilter('cart.before_checkout', [$this, 'enrichCheckoutData'], 17);
        HookManager::addFilter('order.before_create', [$this, 'appendOrderData'], 17);
        HookManager::addFilter('order.checkout_response', [$this, 'appendCheckoutResponse'], 17);
        HookManager::addAction('settings.saved', [$this, 'syncOriginAfterSettingsSaved'], 17);
    }

    public function addBranchSettingsSection(array $sections, int $branchId): array
    {
        $sections[RajaOngkirDeliveryRepository::PLUGIN_SLUG] = $this->renderSettingsCard($branchId, false);
        return $sections;
    }

    public function addSuperSettingsSection(array $sections, int $branchId): array
    {
        $sections[RajaOngkirDeliveryRepository::PLUGIN_SLUG] = $this->renderSettingsCard($branchId, true);
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

    public function syncOriginAfterSettingsSaved(int $branchId, array $payload = []): void
    {
        if (!$this->service->isActive($branchId)) {
            return;
        }

        $slug = strtolower((string)($payload['plugin_slug'] ?? ''));
        if ($slug !== '' && $slug !== RajaOngkirDeliveryRepository::PLUGIN_SLUG) {
            return;
        }

        $this->service->syncOriginForBranch($branchId);
    }

    private function renderSettingsCard(int $branchId, bool $includeBranchField): string
    {
        $isActive = $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
        $apiKey = $this->repo->getBranchSetting($branchId, 'api_key');
        $originId = $this->repo->getBranchSetting($branchId, 'origin_id');
        $originLabel = $this->repo->getBranchSetting($branchId, 'origin_label');
        $originLastSyncAt = $this->repo->getBranchSetting($branchId, 'origin_last_sync_at');
        $originSyncStatus = $this->repo->getBranchSetting($branchId, 'origin_sync_status');
        $originSyncMessage = $this->repo->getBranchSetting($branchId, 'origin_sync_message');
        $courierCode = $this->repo->getBranchSetting($branchId, 'courier_code', 'jne');
        $pricePreference = $this->repo->getBranchSetting($branchId, 'price_preference', 'lowest');
        $baseWeight = $this->repo->getBranchSetting($branchId, 'base_weight_grams', '250');
        $perItemWeight = $this->repo->getBranchSetting($branchId, 'per_item_weight_grams', '200');
        $branch = (new \App\Models\BranchModel())->find($branchId) ?: [];
        $branchPostalCode = (string)($branch['postal_code'] ?? '');
        $statusTone = 'warning';
        $statusTitle = 'Menunggu sinkron origin';
        $statusMessage = 'Simpan pengaturan plugin untuk mulai sinkron origin RajaOngkir dari data cabang.';

        if (!$isActive) {
            $statusTitle = 'Plugin belum aktif';
            $statusMessage = 'Aktifkan plugin ini terlebih dulu jika ingin menghitung biaya delivery dengan RajaOngkir.';
        } elseif ($branchPostalCode === '' && trim((string)($branch['address'] ?? '')) === '') {
            $statusTitle = 'Data cabang belum lengkap';
            $statusMessage = 'Isi kode pos atau alamat cabang dulu agar origin RajaOngkir bisa disinkronkan.';
        } elseif ($apiKey === '') {
            $statusTitle = 'API key belum diisi';
            $statusMessage = 'Masukkan API key RajaOngkir untuk mengaktifkan sinkron origin otomatis.';
        } elseif ($originSyncStatus === 'success') {
            $statusTone = 'success';
            $statusTitle = 'Origin RajaOngkir berhasil disinkronkan';
            $statusMessage = $originSyncMessage !== '' ? $originSyncMessage : 'Origin cabang sudah siap dipakai untuk perhitungan ongkir.';
        } elseif ($originSyncStatus === 'error') {
            $statusTone = 'error';
            $statusTitle = 'Sinkron origin gagal';
            $statusMessage = $originSyncMessage !== '' ? $originSyncMessage : 'Periksa data cabang atau konfigurasi plugin RajaOngkir.';
        } elseif ($originId !== '') {
            $statusTone = 'success';
            $statusTitle = 'Origin RajaOngkir tersedia';
            $statusMessage = 'Origin ID sudah tersimpan dan siap dipakai untuk checkout delivery.';
        }

        $statusStyles = match ($statusTone) {
            'success' => 'background:#eaf8ef;border:1px solid #b7e3c4;color:#166534;',
            'error' => 'background:#fff1f2;border:1px solid #fecdd3;color:#b42318;',
            default => 'background:#fff7e6;border:1px solid #f3d19c;color:#9a6700;',
        };

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">RajaOngkir Delivery Fee</div>
          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
            Plugin ini menghitung ongkir RajaOngkir hanya untuk order dengan metode <strong>delivery ke alamat</strong>.
            Saat aktif, hasil ongkir akan dimasukkan sebagai komponen <code>delivery_fee</code> ke total order.
            Origin pengiriman otomatis mengikuti kode pos atau alamat cabang lalu disinkronkan ke <code>origin_id</code> RajaOngkir.
          </div>
          <div style="<?= $statusStyles ?>border-radius:10px;padding:12px 14px;margin-bottom:14px">
            <div style="font-weight:700;margin-bottom:4px"><?= htmlspecialchars($statusTitle) ?></div>
            <div style="font-size:.85rem;line-height:1.6"><?= htmlspecialchars($statusMessage) ?></div>
            <?php if ($originLastSyncAt !== ''): ?>
              <div style="font-size:.78rem;opacity:.85;margin-top:6px">
                Terakhir sinkron: <?= htmlspecialchars($originLastSyncAt) ?>
              </div>
            <?php endif; ?>
          </div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= RajaOngkirDeliveryRepository::PLUGIN_SLUG ?>">
            <?php if ($includeBranchField): ?>
            <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
            <?php endif; ?>
            <input type="hidden" name="is_active" value="0">

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan biaya delivery RajaOngkir untuk cabang ini</span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">API Key</label>
                <input type="password" name="api_key" class="form-control" value="<?= htmlspecialchars($apiKey) ?>" placeholder="RajaOngkir API key">
              </div>
              <div class="form-group">
                <label class="form-label">Kode Pos Cabang</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($branchPostalCode) ?>" readonly placeholder="Isi di pengaturan cabang">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Origin ID</label>
                <input type="text" name="origin_id" class="form-control" value="<?= htmlspecialchars($originId) ?>" placeholder="Akan otomatis terisi saat sinkron">
              </div>
              <div class="form-group">
                <label class="form-label">Origin Label</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($originLabel) ?>" readonly placeholder="Belum tersinkron">
              </div>
            </div>

            <div class="form-group">
              <small style="color:var(--text-light)">
                <?= $originLastSyncAt !== '' ? 'Terakhir sinkron: ' . htmlspecialchars($originLastSyncAt) . '.' : 'Belum pernah sinkron.' ?>
                <?= $originSyncStatus !== '' ? ' Status: ' . htmlspecialchars($originSyncStatus) . '.' : '' ?>
                <?= $originSyncMessage !== '' ? ' ' . htmlspecialchars($originSyncMessage) : '' ?>
              </small>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Courier Code</label>
                <input type="text" name="courier_code" class="form-control" value="<?= htmlspecialchars($courierCode) ?>" placeholder="jne / jnt / sicepat">
              </div>
              <div class="form-group">
                <label class="form-label">Price Preference</label>
                <select name="price_preference" class="form-control">
                  <option value="lowest" <?= $pricePreference === 'lowest' ? 'selected' : '' ?>>lowest</option>
                  <option value="highest" <?= $pricePreference === 'highest' ? 'selected' : '' ?>>highest</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Base Weight (gram)</label>
                <input type="number" min="0" name="base_weight_grams" class="form-control" value="<?= htmlspecialchars($baseWeight) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Per Item Weight (gram)</label>
                <input type="number" min="1" name="per_item_weight_grams" class="form-control" value="<?= htmlspecialchars($perItemWeight) ?>">
              </div>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan RajaOngkir</button>
          </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
