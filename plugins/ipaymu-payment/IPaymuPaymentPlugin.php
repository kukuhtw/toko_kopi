<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Csrf;
use App\Models\BranchModel;
use App\Models\OrderModel;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class IPaymuPaymentPlugin implements PluginInterface
{
    private const PLUGIN_SLUG = 'ipaymu-payment';
    private static string $pendingPaymentUrl = '';

    public function getName(): string
    {
        return 'iPaymu Payment';
    }

    public function getVersion(): string
    {
        return '1.1.0';
    }

    public function getAuthor(): string
    {
        return 'Codex';
    }

    public function register(): void
    {
        HookManager::addAction('order.created', [$this, 'onOrderCreated']);
        HookManager::addFilter('chat.after_ai', [$this, 'appendPaymentLink'], 20);
        HookManager::addAction('payment.notification', [$this, 'handleNotification']);
        HookManager::addFilter('order.checkout_response', [$this, 'appendCheckoutPaymentData'], 20);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 13);
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 13);
    }

    public function onOrderCreated(array $order): void
    {
        $branchId = (int)($order['branch_id'] ?? 0);
        if ($branchId <= 0 || !$this->isEnabled($branchId)) {
            return;
        }

        $apiKey = trim((string)$this->getSetting($branchId, 'api_key'));
        $va = trim((string)$this->getSetting($branchId, 'va'));
        $baseUrl = trim((string)$this->getSetting($branchId, 'base_url'));
        $endpointPath = trim((string)($this->getSetting($branchId, 'endpoint_path') ?: '/payment'));
        if ($apiKey === '' || $va === '' || $baseUrl === '') {
            error_log('[ipaymu-payment] Missing required sandbox configuration for branch ' . $branchId);
            return;
        }

        $client = new IPaymuClient($baseUrl, $apiKey, $va, $endpointPath);
        $payment = $client->createPaymentLink($this->buildPayload($order, $branchId));
        if (!$payment) {
            error_log('[ipaymu-payment] Failed to create payment link for order ' . (string)($order['order_number'] ?? '-'));
            return;
        }

        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $paymentUrl = (string)($payment['payment_url'] ?? '');
        $referenceId = (string)($payment['reference_id'] ?? '');
        $this->saveSetting($branchId, 'payment_url_' . $orderId, $paymentUrl);
        $this->saveSetting($branchId, 'reference_id_' . $orderId, $referenceId);
        self::$pendingPaymentUrl = $paymentUrl;
    }

    public function appendPaymentLink(string $reply, int $branchId, string $intent, array $context = []): string
    {
        if (self::$pendingPaymentUrl === '' || !$this->isEnabled($branchId)) {
            return $reply;
        }

        $url = self::$pendingPaymentUrl;
        self::$pendingPaymentUrl = '';
        $label = (($context['language'] ?? 'id') === 'en') ? 'Pay with iPaymu' : 'Bayar via iPaymu';

        return rtrim($reply) . "\n\n💳 *{$label}:*\n" . $url;
    }

    public function handleNotification(string $provider, array $payload, int $branchId): void
    {
        if ($provider !== 'ipaymu' || !$this->isEnabled($branchId)) {
            return;
        }

        $apiKey = trim((string)$this->getSetting($branchId, 'api_key'));
        $va = trim((string)$this->getSetting($branchId, 'va'));
        if ($apiKey === '' || $va === '') {
            return;
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $receivedToken = null;
        foreach ((array)$headers as $name => $value) {
            if (strtolower((string)$name) === 'x-callback-token') {
                $receivedToken = (string)$value;
                break;
            }
        }

        $client = new IPaymuClient(
            (string)($this->getSetting($branchId, 'base_url') ?: 'https://sandbox.ipaymu.com/api/v2'),
            $apiKey,
            $va,
            (string)($this->getSetting($branchId, 'endpoint_path') ?: '/payment')
        );
        if (!$client->verifyCallbackToken($receivedToken, (string)$this->getSetting($branchId, 'callback_token'))) {
            error_log('[ipaymu-payment] Invalid callback token');
            return;
        }

        $orderNumber = (string)($payload['referenceId'] ?? $payload['reference_id'] ?? $payload['order_id'] ?? '');
        $status = strtolower((string)($payload['status'] ?? $payload['transactionStatus'] ?? $payload['paymentStatus'] ?? ''));

        if ($orderNumber === '') {
            $orderNumber = $this->resolveOrderNumberFromGatewayReference(
                $branchId,
                (string)($payload['sessionId'] ?? $payload['SessionID'] ?? $payload['transaction_id'] ?? '')
            );
        }
        if ($orderNumber === '') {
            return;
        }

        $paymentState = $this->mapIncomingStatus($status);
        if ($paymentState === null) {
            return;
        }

        $orderModel = new OrderModel();
        $order = $orderModel->findByOrderNumber($orderNumber);
        if (!$order) {
            return;
        }

        $orderModel->updatePayment((int)$order['id'], $paymentState);
    }

    public function appendCheckoutPaymentData(array $responseData, array $order, int $branchId): array
    {
        if (!$this->isEnabled($branchId)) {
            return $responseData;
        }

        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0) {
            return $responseData;
        }

        $url = (string)$this->getSetting($branchId, 'payment_url_' . $orderId);
        if ($url === '') {
            return $responseData;
        }

        $responseData['payment'] = [
            'provider' => 'ipaymu',
            'status' => (string)($order['payment_status'] ?? 'unpaid'),
            'url' => $url,
            'label' => 'Bayar via iPaymu',
        ];

        return $responseData;
    }

    public function addSuperSettingsSection(array $sections, int $branchId): array
    {
        $sections[self::PLUGIN_SLUG] = $this->renderSettingsSection($branchId, true);
        return $sections;
    }

    public function addBranchSettingsSection(array $sections, int $branchId): array
    {
        $sections[self::PLUGIN_SLUG] = $this->renderSettingsSection($branchId, false);
        return $sections;
    }

    private function renderSettingsSection(int $branchId, bool $isSuperAdmin): string
    {
        $enabled = $this->isEnabled($branchId);
        $mode = (string)($this->getSetting($branchId, 'mode') ?: 'sandbox');
        $baseUrl = (string)($this->getSetting($branchId, 'base_url') ?: 'https://sandbox.ipaymu.com/api/v2');
        $endpointPath = (string)($this->getSetting($branchId, 'endpoint_path') ?: '/payment');
        $apiKey = (string)$this->getSetting($branchId, 'api_key');
        $va = (string)$this->getSetting($branchId, 'va');
        $callbackToken = (string)$this->getSetting($branchId, 'callback_token');
        $successUrl = (string)$this->getSetting($branchId, 'success_redirect_url');
        $paymentMethod = (string)$this->getSetting($branchId, 'payment_method');
        $notifyUrl = BASE_URL . '/api/payment/notify.php?provider=ipaymu&branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">💳 iPaymu Payment Gateway</div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <?php if ($isSuperAdmin): ?>
              <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
            <?php endif; ?>
            <input type="hidden" name="plugin_slug" value="<?= self::PLUGIN_SLUG ?>">

            <div class="form-group">
              <input type="hidden" name="is_enabled" value="0">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                <span>Aktifkan iPaymu untuk cabang ini</span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="ipaymu_mode_<?= (int)$branchId ?>">Mode</label>
                <select id="ipaymu_mode_<?= (int)$branchId ?>" name="mode" class="form-control">
                  <option value="sandbox" <?= $mode === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                  <option value="production" <?= $mode === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="ipaymu_payment_method_<?= (int)$branchId ?>">Payment Method</label>
                <input type="text" id="ipaymu_payment_method_<?= (int)$branchId ?>" name="payment_method" class="form-control"
                       value="<?= htmlspecialchars($paymentMethod) ?>"
                       placeholder="Opsional, sesuai channel yang diaktifkan di akun iPaymu">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="ipaymu_base_url_<?= (int)$branchId ?>">Base URL API</label>
                <input type="text" id="ipaymu_base_url_<?= (int)$branchId ?>" name="base_url" class="form-control"
                       value="<?= htmlspecialchars($baseUrl) ?>"
                       placeholder="https://sandbox.ipaymu.com/api/v2">
              </div>
              <div class="form-group">
                <label class="form-label" for="ipaymu_endpoint_path_<?= (int)$branchId ?>">Endpoint Path</label>
                <input type="text" id="ipaymu_endpoint_path_<?= (int)$branchId ?>" name="endpoint_path" class="form-control"
                       value="<?= htmlspecialchars($endpointPath) ?>"
                       placeholder="/payment">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="ipaymu_api_key_<?= (int)$branchId ?>">API Key</label>
                <input type="password" id="ipaymu_api_key_<?= (int)$branchId ?>" name="api_key" class="form-control"
                       value="<?= htmlspecialchars($apiKey) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="ipaymu_va_<?= (int)$branchId ?>">VA Number / Merchant VA</label>
                <input type="text" id="ipaymu_va_<?= (int)$branchId ?>" name="va" class="form-control"
                       value="<?= htmlspecialchars($va) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="ipaymu_callback_token_<?= (int)$branchId ?>">Callback Token</label>
                <input type="text" id="ipaymu_callback_token_<?= (int)$branchId ?>" name="callback_token" class="form-control"
                       value="<?= htmlspecialchars($callbackToken) ?>"
                       placeholder="Opsional, untuk proteksi endpoint webhook internal">
              </div>
              <div class="form-group">
                <label class="form-label" for="ipaymu_success_redirect_url_<?= (int)$branchId ?>">Success Redirect URL</label>
                <input type="url" id="ipaymu_success_redirect_url_<?= (int)$branchId ?>" name="success_redirect_url" class="form-control"
                       value="<?= htmlspecialchars($successUrl) ?>"
                       placeholder="<?= htmlspecialchars(BASE_URL . '/order.php') ?>">
              </div>
            </div>

            <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
              <strong>Sandbox flow yang dipakai plugin ini</strong><br>
              1. POST data order ke endpoint redirect iPaymu<br>
              2. Terima <code>Url</code> / <code>SessionID</code><br>
              3. Kirim customer ke halaman bayar iPaymu<br>
              4. Terima notifikasi server-to-server di <code>unotify</code><br><br>
              <strong>Webhook URL</strong><br>
              <code style="word-break:break-all"><?= htmlspecialchars($notifyUrl) ?></code><br><br>
              <strong>Catatan:</strong> implementasi ini ditujukan untuk sandbox flow redirect payment iPaymu. Karena dokumentasi resmi iPaymu menggunakan contoh merchant-specific dan Postman, <code>base_url</code> serta <code>endpoint_path</code> sengaja dibuat fleksibel supaya mudah disesuaikan dengan akun sandbox Anda.
            </div>

            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan iPaymu</button>
          </form>
        </div>
        <?php

        return ob_get_clean();
    }

    private function buildPayload(array $order, int $branchId): array
    {
        $branchModel = new BranchModel();
        $currency = strtoupper((string)($branchModel->getCurrency($branchId) ?: 'IDR'));

        $products = [];
        $qty = [];
        $prices = [];
        foreach ((array)($order['items'] ?? []) as $item) {
            $products[] = (string)($item['menu_name'] ?? $item['base_name'] ?? 'Item');
            $qty[] = (int)($item['quantity'] ?? 1);
            $prices[] = max(0, (int)round((float)($item['unit_price'] ?? 0)));
        }

        if ($products === []) {
            $products[] = 'Order ' . (string)($order['order_number'] ?? '');
            $qty[] = 1;
            $prices[] = max(0, (int)round((float)($order['total_amount'] ?? 0)));
        }

        $payload = [
            'referenceId' => (string)($order['order_number'] ?? ''),
            'product' => $products,
            'qty' => $qty,
            'price' => $prices,
            'buyerName' => (string)($order['customer_name'] ?? ''),
            'buyerPhone' => $this->sanitizePhone((string)($order['customer_wa'] ?? '')),
            'buyerEmail' => (string)($order['customer_email'] ?? 'customer@example.com'),
            'returnUrl' => (string)($this->getSetting($branchId, 'success_redirect_url') ?: (BASE_URL . '/order.php')),
            'notifyUrl' => BASE_URL . '/api/payment/notify.php?provider=ipaymu&branch=' . $branchId,
            'comments' => 'Payment for order ' . (string)($order['order_number'] ?? ''),
            'currency' => $currency,
        ];

        $paymentMethod = trim((string)$this->getSetting($branchId, 'payment_method'));
        if ($paymentMethod !== '') {
            $payload['paymentMethod'] = $paymentMethod;
        }

        return $payload;
    }

    private function sanitizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return '';
        }
        return substr($digits, 0, 20);
    }

    private function mapIncomingStatus(string $status): ?string
    {
        if ($status === '') {
            return null;
        }

        if (in_array($status, ['paid', 'success', 'settlement', 'berhasil', 'sukses'], true)) {
            return 'paid';
        }
        if (in_array($status, ['pending', 'waiting', 'unsettled'], true)) {
            return 'unpaid';
        }
        if (in_array($status, ['failed', 'cancelled', 'expired', 'deny', 'gagal'], true)) {
            return 'failed';
        }

        return null;
    }

    private function resolveOrderNumberFromGatewayReference(int $branchId, string $gatewayReference): string
    {
        if ($gatewayReference === '') {
            return '';
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT setting_key FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_val = ? AND setting_key LIKE ? LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $gatewayReference, 'reference_id_%']);
        $row = $stmt->fetch();
        if (!$row) {
            return '';
        }

        $settingKey = (string)($row['setting_key'] ?? '');
        $orderId = (int)substr($settingKey, strlen('reference_id_'));
        if ($orderId <= 0) {
            return '';
        }

        $order = (new OrderModel())->find($orderId);
        return $order ? (string)($order['order_number'] ?? '') : '';
    }

    private function isEnabled(int $branchId): bool
    {
        return $this->getSetting($branchId, 'is_enabled') === '1';
    }

    private function getSetting(int $branchId, string $key): ?string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['setting_val'] : null;
    }

    private function saveSetting(int $branchId, string $key, string $value): void
    {
        Database::getInstance()->prepare(
            'INSERT INTO plugin_branch_settings (plugin_slug, branch_id, setting_key, setting_val)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
        )->execute([self::PLUGIN_SLUG, $branchId, $key, $value]);
    }
}
