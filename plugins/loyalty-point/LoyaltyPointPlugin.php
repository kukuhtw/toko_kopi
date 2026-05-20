<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Csrf;
use App\Helpers\Currency;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;
use App\Services\IntentPatternRegistry;
use App\Skills\SkillRegistry;

class LoyaltyPointPlugin implements PluginInterface
{
    private const SLUG = 'loyalty-point';

    public function getName(): string
    {
        return 'Loyalty Point';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'KopiBot';
    }

    public function register(): void
    {
        (new LoyaltyPointRepository())->ensureSchema();

        HookManager::addAction('order.created', [$this, 'consumeRedeemedPoints'], 10);
        HookManager::addAction('order.completed', [$this, 'handleOrderCompleted'], 10);
        HookManager::addAction('order.payment_updated', [$this, 'handlePaymentUpdated'], 10);
        HookManager::addAction('order.status_changed', [$this, 'refundRedeemedPointsOnCancel'], 10);
        HookManager::addFilter('cart.before_checkout', [$this, 'validateCheckoutRedemption'], 10);
        HookManager::addFilter('order.before_create', [$this, 'attachOrderRedemptionData'], 10);
        HookManager::addFilter('chat.after_ai', [$this, 'appendPointsHint'], 18);
        HookManager::addFilter('settings.sections', [$this, 'addSettingsSection'], 18);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 18);
        HookManager::addFilter('skills.registered', [$this, 'registerSkill'], 18);
        HookManager::addFilter('intent.patterns', [$this, 'registerIntentPatterns'], 18);
        HookManager::addFilter('dashboard.branch_widgets', [$this, 'addBranchWidget'], 18);
        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItems'], 18);
    }

    public function handleOrderCompleted(array $order): void
    {
        $this->tryAwardPoints($order);
    }

    public function handlePaymentUpdated(array $order, string $paymentStatus): void
    {
        if ($paymentStatus !== 'paid') {
            return;
        }

        $this->tryAwardPoints($order);
    }

    public function appendPointsHint(string $reply, int $branchId, string $intent, array $ctx = []): string
    {
        if (!$this->isEnabled($branchId)) {
            return $reply;
        }

        $eligibleIntents = ['lihat_cart', 'checkout', 'tanya_promo'];
        if (!in_array($intent, $eligibleIntents, true)) {
            return $reply;
        }

        $pointsPerUnit = (int)$this->getSetting($branchId, 'points_per_unit', '1');
        $spendAmount   = (float)$this->getSetting($branchId, 'spend_amount', '10000');
        if ($pointsPerUnit <= 0 || $spendAmount <= 0) {
            return $reply;
        }

        $currency = (string)($ctx['currency'] ?? 'IDR');
        $lang     = (string)($ctx['language'] ?? 'id');
        $balance  = 0;
        if (!empty($ctx['customer']['id'])) {
            $row = (new LoyaltyPointRepository())->getBalance($branchId, (int)$ctx['customer']['id']);
            $balance = (int)($row['balance_points'] ?? 0);
        }

        $redeemedPoints = (int)($ctx['cart']['loyalty_points_redeemed'] ?? 0);
        $redeemedAmount = (float)($ctx['cart']['loyalty_discount_amount'] ?? 0);
        $hint = $lang === 'en'
            ? "Loyalty active: every " . Currency::format($spendAmount, $currency) . " earns {$pointsPerUnit} point(s). Your current balance: {$balance}. Type *my points* anytime."
            : "Loyalty aktif: setiap belanja " . Currency::format($spendAmount, $currency) . " dapat {$pointsPerUnit} poin. Saldo kamu saat ini: {$balance} poin. Ketik *poin saya* kapan saja.";
        if ($redeemedPoints > 0 && $redeemedAmount > 0) {
            $hint .= $lang === 'en'
                ? " Redeemed on this cart: {$redeemedPoints} points (" . Currency::format($redeemedAmount, $currency) . ")."
                : " Poin yang sedang dipakai di keranjang ini: {$redeemedPoints} poin (" . Currency::format($redeemedAmount, $currency) . ").";
        }

        return $reply . "\n\n" . $hint;
    }

    public function addSettingsSection(array $sections, int $branchId): array
    {
        $scope = $this->getSettingsScope();
        $enabled       = $this->getSetting($branchId, 'enabled', '1') !== '0';
        $pointsPerUnit = $this->getSetting($branchId, 'points_per_unit', '1');
        $spendAmount   = $this->getSetting($branchId, 'spend_amount', '10000');
        $requirePaid   = $this->getSetting($branchId, 'require_paid', '0') === '1';
        $redeemPointsUnit = $this->getSetting($branchId, 'redeem_points_unit', '10');
        $redeemValueAmount = $this->getSetting($branchId, 'redeem_value_amount', '1000');
        $minRedeemPoints = $this->getSetting($branchId, 'min_redeem_points', '10');
        $currency      = $this->getBranchCurrency($branchId);
        $summary       = (new LoyaltyPointRepository())->getBranchSummary($branchId);

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">⭐ Loyalty Point</div>
          <p style="font-size:.875rem;color:var(--text-light);margin-bottom:14px">
            Beri poin otomatis ke customer saat order selesai. Customer juga bisa cek saldo poin langsung dari chatbot.
          </p>

          <?php if ($scope === 'global'): ?>
            <div style="margin-bottom:14px;padding:12px 14px;background:var(--bg-light,#faf9f7);border-radius:8px;color:var(--text-light)">
              Pengaturan loyalty saat ini bersifat global untuk semua cabang. Ubah dari halaman Super Admin Settings jika ingin mengganti program loyalty.
            </div>
          <?php else: ?>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::SLUG ?>">

            <div class="form-group">
              <input type="hidden" name="enabled" value="0">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                <span>Aktifkan loyalty point untuk cabang ini</span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="lp_spend_amount">Belanja per Kelipatan (<?= htmlspecialchars($currency) ?>)</label>
                <input type="number" id="lp_spend_amount" name="spend_amount" class="form-control"
                       min="1" step="1000" value="<?= htmlspecialchars($spendAmount) ?>">
                <small style="color:var(--text-light)">Contoh 10000 = setiap belanja 10.000 dapat poin.</small>
              </div>
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="lp_points_per_unit">Poin Diberikan</label>
                <input type="number" id="lp_points_per_unit" name="points_per_unit" class="form-control"
                       min="1" step="1" value="<?= htmlspecialchars($pointsPerUnit) ?>">
                <small style="color:var(--text-light)">Contoh 1 = dapat 1 poin per kelipatan.</small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="lp_redeem_points_unit">Poin per Redeem</label>
                <input type="number" id="lp_redeem_points_unit" name="redeem_points_unit" class="form-control"
                       min="1" step="1" value="<?= htmlspecialchars($redeemPointsUnit) ?>">
              </div>
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="lp_redeem_value_amount">Nilai Diskon (<?= htmlspecialchars($currency) ?>)</label>
                <input type="number" id="lp_redeem_value_amount" name="redeem_value_amount" class="form-control"
                       min="1" step="500" value="<?= htmlspecialchars($redeemValueAmount) ?>">
              </div>
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="lp_min_redeem_points">Minimal Redeem Poin</label>
                <input type="number" id="lp_min_redeem_points" name="min_redeem_points" class="form-control"
                       min="1" step="1" value="<?= htmlspecialchars($minRedeemPoints) ?>">
              </div>
            </div>

            <div class="form-group">
              <input type="hidden" name="require_paid" value="0">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="require_paid" value="1" <?= $requirePaid ? 'checked' : '' ?>>
                <span>Hanya berikan poin jika order sudah berstatus paid</span>
              </label>
            </div>

            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan Loyalty</button>
          </form>
          <?php endif; ?>

          <div style="margin-top:16px;background:var(--bg-light,#faf9f7);border-radius:8px;padding:14px">
            <div style="font-weight:600;margin-bottom:10px">Ringkasan Program Saat Ini</div>
            <div style="font-size:.8rem;color:var(--text-light);margin-bottom:12px">
              Scope aktif: <strong><?= $scope === 'global' ? 'Global untuk semua cabang' : 'Per cabang' ?></strong>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:12px">
              <div>
                <div style="font-size:.78rem;color:var(--text-light)">Member Aktif</div>
                <div style="font-size:1.2rem;font-weight:700"><?= number_format((int)($summary['member_count'] ?? 0)) ?></div>
              </div>
              <div>
                <div style="font-size:.78rem;color:var(--text-light)">Saldo Poin Beredar</div>
                <div style="font-size:1.2rem;font-weight:700"><?= number_format((int)($summary['total_balance_points'] ?? 0)) ?></div>
              </div>
              <div>
                <div style="font-size:.78rem;color:var(--text-light)">Total Poin Diberikan</div>
                <div style="font-size:1.2rem;font-weight:700"><?= number_format((int)($summary['total_lifetime_points'] ?? 0)) ?></div>
              </div>
            </div>

            <div style="font-weight:600;font-size:.85rem;margin-bottom:8px">Top Member</div>
            <?php foreach (($summary['top_members'] ?? []) as $member): ?>
              <div style="display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid rgba(0,0,0,.06)">
                <div>
                  <div style="font-weight:600"><?= htmlspecialchars((string)($member['name'] ?: $member['identifier'])) ?></div>
                  <div style="font-size:.75rem;color:var(--text-light)">Lifetime <?= number_format((int)($member['lifetime_points'] ?? 0)) ?> poin</div>
                </div>
                <div style="font-weight:700"><?= number_format((int)($member['balance_points'] ?? 0)) ?> poin</div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($summary['top_members'])): ?>
              <div style="font-size:.85rem;color:var(--text-light)">Belum ada customer yang mengumpulkan poin.</div>
            <?php endif; ?>
          </div>
        </div>
        <?php

        $sections[self::SLUG] = ob_get_clean();
        return $sections;
    }

    public function addSuperSettingsSection(array $sections, int $branchId): array
    {
        $scope = $this->getSettingsScope();
        $enabled = $this->getGlobalSetting('enabled', '1') !== '0';
        $pointsPerUnit = $this->getGlobalSetting('points_per_unit', '1');
        $spendAmount = $this->getGlobalSetting('spend_amount', '10000');
        $requirePaid = $this->getGlobalSetting('require_paid', '0') === '1';
        $redeemPointsUnit = $this->getGlobalSetting('redeem_points_unit', '10');
        $redeemValueAmount = $this->getGlobalSetting('redeem_value_amount', '1000');
        $minRedeemPoints = $this->getGlobalSetting('min_redeem_points', '10');

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Loyalty Point</div>
          <p style="font-size:.875rem;color:var(--text-light);margin-bottom:14px">
            Atur apakah program loyalty dipakai global untuk semua cabang atau dikelola masing-masing cabang.
          </p>

          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_global_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::SLUG ?>">

            <div class="form-group" style="max-width:280px">
              <label class="form-label" for="lp_settings_scope">Mode Pengaturan</label>
              <select id="lp_settings_scope" name="settings_scope" class="form-control">
                <option value="global" <?= $scope === 'global' ? 'selected' : '' ?>>Global untuk semua cabang</option>
                <option value="branch" <?= $scope === 'branch' ? 'selected' : '' ?>>Diatur per cabang</option>
              </select>
              <small style="color:var(--text-light)">Jika pilih global, halaman cabang hanya menampilkan ringkasan dan memakai nilai dari sini.</small>
            </div>

            <div class="form-group">
              <input type="hidden" name="enabled" value="0">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                <span>Aktifkan loyalty point global</span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="lp_global_spend_amount">Belanja per Kelipatan</label>
                <input type="number" id="lp_global_spend_amount" name="spend_amount" class="form-control"
                       min="1" step="1000" value="<?= htmlspecialchars($spendAmount) ?>">
              </div>
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="lp_global_points_per_unit">Poin Diberikan</label>
                <input type="number" id="lp_global_points_per_unit" name="points_per_unit" class="form-control"
                       min="1" step="1" value="<?= htmlspecialchars($pointsPerUnit) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="lp_global_redeem_points_unit">Poin per Redeem</label>
                <input type="number" id="lp_global_redeem_points_unit" name="redeem_points_unit" class="form-control"
                       min="1" step="1" value="<?= htmlspecialchars($redeemPointsUnit) ?>">
              </div>
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="lp_global_redeem_value_amount">Nilai Diskon</label>
                <input type="number" id="lp_global_redeem_value_amount" name="redeem_value_amount" class="form-control"
                       min="1" step="500" value="<?= htmlspecialchars($redeemValueAmount) ?>">
              </div>
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="lp_global_min_redeem_points">Minimal Redeem Poin</label>
                <input type="number" id="lp_global_min_redeem_points" name="min_redeem_points" class="form-control"
                       min="1" step="1" value="<?= htmlspecialchars($minRedeemPoints) ?>">
              </div>
            </div>

            <div class="form-group">
              <input type="hidden" name="require_paid" value="0">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="require_paid" value="1" <?= $requirePaid ? 'checked' : '' ?>>
                <span>Hanya berikan poin jika order sudah berstatus paid</span>
              </label>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Scope & Pengaturan Global Loyalty</button>
          </form>
        </div>
        <?php

        $sections[self::SLUG] = ob_get_clean();
        return $sections;
    }

    public function registerSkill(array $skills): array
    {
        return SkillRegistry::register($skills, new LoyaltyPointSkill(), 55);
    }

    public function registerIntentPatterns(array $patterns): array
    {
        $patterns = IntentPatternRegistry::extend($patterns, 'cek_poin_loyalty', [
            'poin saya',
            'cek poin',
            'saldo poin',
            'loyalty point',
            'point saya',
            'my points',
            'reward saya',
            'cek reward',
        ]);

        $patterns = IntentPatternRegistry::extend($patterns, 'pakai_poin_loyalty', [
            'pakai poin',
            'gunakan poin',
            'redeem point',
            'redeem points',
            'use points',
            'tukar poin',
        ]);

        return IntentPatternRegistry::extend($patterns, 'hapus_poin_loyalty', [
            'hapus poin',
            'batal pakai poin',
            'jangan pakai poin',
            'remove points',
            'clear points',
            'cancel points',
        ]);
    }

    public function addBranchWidget(array $widgets, int $branchId): array
    {
        if (!$this->isEnabled($branchId)) {
            return $widgets;
        }

        $summary = (new LoyaltyPointRepository())->getBranchSummary($branchId);

        ob_start();
        ?>
        <div class="card" style="margin-bottom:20px">
          <div class="card-title">⭐ Loyalty Point</div>
          <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px">
            <div>
              <div style="font-size:.78rem;color:var(--text-light)">Member</div>
              <div style="font-size:1.3rem;font-weight:700"><?= number_format((int)($summary['member_count'] ?? 0)) ?></div>
            </div>
            <div>
              <div style="font-size:.78rem;color:var(--text-light)">Saldo Beredar</div>
              <div style="font-size:1.3rem;font-weight:700"><?= number_format((int)($summary['total_balance_points'] ?? 0)) ?></div>
            </div>
            <div>
              <div style="font-size:.78rem;color:var(--text-light)">Total Earned</div>
              <div style="font-size:1.3rem;font-weight:700"><?= number_format((int)($summary['total_lifetime_points'] ?? 0)) ?></div>
            </div>
          </div>
        </div>
        <?php

        $widgets[] = ob_get_clean();
        return $widgets;
    }

    public function addNavItems(array $navItems, string $role): array
    {
        if ($role !== 'branch_admin') {
            return $navItems;
        }

        if (!isset($navItems['Order']) || !is_array($navItems['Order'])) {
            $navItems['Order'] = [];
        }

        $navItems['Order'][] = [
            'url' => '/dashboard/branch/loyalty.php',
            'icon' => 'LP',
            'label' => 'Loyalty Member',
        ];

        return $navItems;
    }

    public function validateCheckoutRedemption(array $customerData, array $cart, array $items, int $branchId): array
    {
        $redeemedPoints = (int)($cart['loyalty_points_redeemed'] ?? 0);
        $redeemedAmount = (float)($cart['loyalty_discount_amount'] ?? 0);
        if ($redeemedPoints <= 0 || $redeemedAmount <= 0) {
            return $customerData;
        }

        $balance = (new LoyaltyPointRepository())->getBalance($branchId, (int)($cart['customer_id'] ?? 0));
        if ((int)($balance['balance_points'] ?? 0) < $redeemedPoints) {
            throw new \RuntimeException('Saldo poin tidak mencukupi untuk checkout.');
        }

        return $customerData;
    }

    public function attachOrderRedemptionData(
        array $orderData,
        array $cart,
        array $cartItems = [],
        array $customerData = [],
        int $customerId = 0,
        float $ppnRate = 0.0
    ): array
    {
        $orderData['loyalty_points_redeemed'] = (int)($cart['loyalty_points_redeemed'] ?? 0);
        $orderData['loyalty_discount_amount'] = (float)($cart['loyalty_discount_amount'] ?? 0);
        return $orderData;
    }

    public function consumeRedeemedPoints(array $order): void
    {
        $points = (int)($order['loyalty_points_redeemed'] ?? 0);
        if ($points <= 0) {
            return;
        }

        $repo = new LoyaltyPointRepository();
        $orderId = (int)($order['id'] ?? 0);
        if ($repo->hasTransactionForOrder($orderId, 'redeem')) {
            return;
        }

        $repo->redeemPoints(
            (int)($order['branch_id'] ?? 0),
            (int)($order['customer_id'] ?? 0),
            $orderId,
            $points,
            'Redeem poin untuk order ' . (string)($order['order_number'] ?? ('#' . $orderId))
        );
    }

    public function refundRedeemedPointsOnCancel(array $order, string $oldStatus, string $newStatus): void
    {
        if ($newStatus !== 'cancelled') {
            return;
        }

        $points = (int)($order['loyalty_points_redeemed'] ?? 0);
        if ($points <= 0) {
            return;
        }

        $repo = new LoyaltyPointRepository();
        $orderId = (int)($order['id'] ?? 0);
        if (!$repo->hasTransactionForOrder($orderId, 'redeem') || $repo->hasTransactionForOrder($orderId, 'refund')) {
            return;
        }

        $repo->refundRedeemedPoints(
            (int)($order['branch_id'] ?? 0),
            (int)($order['customer_id'] ?? 0),
            $orderId,
            $points,
            'Refund poin untuk order batal ' . (string)($order['order_number'] ?? ('#' . $orderId))
        );
    }

    private function tryAwardPoints(array $order): void
    {
        $branchId   = (int)($order['branch_id'] ?? 0);
        $customerId = (int)($order['customer_id'] ?? 0);
        $orderId    = (int)($order['id'] ?? 0);

        if (!$this->isEnabled($branchId) || $branchId <= 0 || $customerId <= 0 || $orderId <= 0) {
            return;
        }

        if ($this->requiresPaid($branchId) && ($order['payment_status'] ?? 'unpaid') !== 'paid') {
            return;
        }

        $repo = new LoyaltyPointRepository();
        if ($repo->hasEarnTransactionForOrder($orderId)) {
            return;
        }

        $points = $this->calculateEarnedPoints($branchId, (float)($order['total_amount'] ?? 0));
        if ($points <= 0) {
            return;
        }

        $description = 'Poin dari order ' . (string)($order['order_number'] ?? ('#' . $orderId));
        $repo->awardPoints($branchId, $customerId, $orderId, $points, $description);
    }

    private function calculateEarnedPoints(int $branchId, float $totalAmount): int
    {
        $spendAmount   = (float)$this->getSetting($branchId, 'spend_amount', '10000');
        $pointsPerUnit = (int)$this->getSetting($branchId, 'points_per_unit', '1');

        if ($spendAmount <= 0 || $pointsPerUnit <= 0 || $totalAmount <= 0) {
            return 0;
        }

        return (int) floor($totalAmount / $spendAmount) * $pointsPerUnit;
    }

    public static function getRedeemSettings(int $branchId): array
    {
        $plugin = new self();
        return [
            'points_unit' => max(1, (int)$plugin->getSetting($branchId, 'redeem_points_unit', '10')),
            'value_amount' => max(1.0, (float)$plugin->getSetting($branchId, 'redeem_value_amount', '1000')),
            'min_points' => max(1, (int)$plugin->getSetting($branchId, 'min_redeem_points', '10')),
        ];
    }

    public static function calculateRedeemDiscount(int $points, int $pointsUnit, float $valueAmount): float
    {
        if ($points <= 0 || $pointsUnit <= 0 || $valueAmount <= 0) {
            return 0.0;
        }

        return floor($points / $pointsUnit) * $valueAmount;
    }

    public static function calculateRedeemPointsForDiscount(float $discount, int $pointsUnit, float $valueAmount): int
    {
        if ($discount <= 0 || $pointsUnit <= 0 || $valueAmount <= 0) {
            return 0;
        }

        return (int) floor($discount / $valueAmount) * $pointsUnit;
    }

    private function isEnabled(int $branchId): bool
    {
        return $this->getSetting($branchId, 'enabled', '1') !== '0';
    }

    private function requiresPaid(int $branchId): bool
    {
        return $this->getSetting($branchId, 'require_paid', '0') === '1';
    }

    private function getSetting(int $branchId, string $key, string $default = ''): string
    {
        if ($this->getSettingsScope() === 'global') {
            return $this->getGlobalSetting($key, $default);
        }

        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT setting_val
                 FROM plugin_branch_settings
                 WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ?
                 LIMIT 1'
            );
            $stmt->execute([self::SLUG, $branchId, $key]);
            $value = $stmt->fetchColumn();

            return $value !== false ? (string)$value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function getSettingsScope(): string
    {
        $scope = $this->getGlobalSetting('settings_scope', 'branch');
        return $scope === 'global' ? 'global' : 'branch';
    }

    private function getGlobalSetting(string $key, string $default = ''): string
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT setting_val
                 FROM app_settings
                 WHERE setting_key = ?
                 LIMIT 1'
            );
            $stmt->execute([$this->buildGlobalSettingKey($key)]);
            $value = $stmt->fetchColumn();

            return $value !== false && $value !== null ? (string)$value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function buildGlobalSettingKey(string $key): string
    {
        return 'plugin_' . str_replace('-', '_', self::SLUG) . '_' . $key;
    }

    private function getBranchCurrency(int $branchId): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val
             FROM branch_settings
             WHERE branch_id = ? AND setting_key = "currency"
             LIMIT 1'
        );
        $stmt->execute([$branchId]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (string)$value : 'IDR';
    }
}
