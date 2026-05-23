<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Csrf;
use App\Models\BranchModel;
use App\Models\OrderModel;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class NicepayPaymentPlugin implements PluginInterface
{
    private const PLUGIN_SLUG = 'nicepay-payment';
    private static string $pendingPaymentUrl = '';

    public function getName(): string
    {
        return 'Nicepay Payment';
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
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 14);
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 14);
    }

    public function onOrderCreated(array $order): void
    {
        $branchId = (int)($order['branch_id'] ?? 0);
        if ($branchId <= 0 || !$this->isEnabled($branchId)) {
            return;
        }

        $merchantId = trim((string)$this->getSetting($branchId, 'merchant_id'));
        $merchantKey = trim((string)($this->getSetting($branchId, 'merchant_key') ?: $this->getSetting($branchId, 'secret_key')));
        $registrationBaseUrl = trim((string)($this->getSetting($branchId, 'registration_base_url') ?: $this->getSetting($branchId, 'base_url') ?: 'https://dev.nicepay.co.id'));
        $checkoutBaseUrl = trim((string)($this->getSetting($branchId, 'checkout_base_url') ?: 'https://dev.nicepay.co.id/nicepay/redirect/v2/payment'));
        if ($merchantId === '' || $merchantKey === '' || $registrationBaseUrl === '' || $checkoutBaseUrl === '') {
            error_log('[nicepay-payment] Missing required sandbox configuration for branch ' . $branchId);
            return;
        }

        $client = new NicepayClient($registrationBaseUrl, $checkoutBaseUrl, $merchantId, $merchantKey);
        $payment = $client->createPaymentLink($this->buildPayload($order, $branchId));
        if (!$payment || (string)($payment['payment_url'] ?? '') === '') {
            error_log('[nicepay-payment] Failed to create payment link for order ' . (string)($order['order_number'] ?? '-'));
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
        $label = (($context['language'] ?? 'id') === 'en') ? 'Pay with Nicepay' : 'Bayar via Nicepay';

        return rtrim($reply) . "\n\n💳 *{$label}:*\n" . $url;
    }

    public function handleNotification(string $provider, array $payload, int $branchId): void
    {
        if ($provider !== 'nicepay' || !$this->isEnabled($branchId)) {
            return;
        }

        $merchantId = trim((string)$this->getSetting($branchId, 'merchant_id'));
        $merchantKey = trim((string)($this->getSetting($branchId, 'merchant_key') ?: $this->getSetting($branchId, 'secret_key')));
        if ($merchantId === '' || $merchantKey === '') {
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

        $client = new NicepayClient(
            (string)($this->getSetting($branchId, 'registration_base_url') ?: $this->getSetting($branchId, 'base_url') ?: 'https://dev.nicepay.co.id'),
            (string)($this->getSetting($branchId, 'checkout_base_url') ?: 'https://dev.nicepay.co.id/nicepay/redirect/v2/payment'),
            $merchantId,
            $merchantKey
        );
        if (!$client->verifyCallbackToken($receivedToken, (string)$this->getSetting($branchId, 'callback_token'))) {
            error_log('[nicepay-payment] Invalid callback token');
            return;
        }

        $orderNumber = (string)($payload['referenceNo'] ?? $payload['referenceno'] ?? $payload['orderNo'] ?? $payload['order_id'] ?? '');
        $resultCode = (string)($payload['resultCd'] ?? $payload['resultcd'] ?? '');
        $status = strtolower((string)($payload['status'] ?? $payload['paymentStatus'] ?? $payload['txStatus'] ?? ''));

        if ($orderNumber === '') {
            $orderNumber = $this->resolveOrderNumberFromGatewayReference(
                $branchId,
                (string)($payload['txId'] ?? $payload['txid'] ?? '')
            );
        }
        if ($orderNumber === '') {
            return;
        }

        $paymentState = $this->mapIncomingStatus($resultCode, $status);
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
            'provider' => 'nicepay',
            'status' => (string)($order['payment_status'] ?? 'unpaid'),
            'url' => $url,
            'label' => 'Bayar via Nicepay',
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
        $registrationBaseUrl = (string)($this->getSetting($branchId, 'registration_base_url') ?: $this->getSetting($branchId, 'base_url') ?: 'https://dev.nicepay.co.id');
        $checkoutBaseUrl = (string)($this->getSetting($branchId, 'checkout_base_url') ?: 'https://dev.nicepay.co.id/nicepay/redirect/v2/payment');
        $merchantId = (string)$this->getSetting($branchId, 'merchant_id');
        $merchantKey = (string)($this->getSetting($branchId, 'merchant_key') ?: $this->getSetting($branchId, 'secret_key'));
        $payMethod = (string)$this->getSetting($branchId, 'pay_method');
        $callbackToken = (string)$this->getSetting($branchId, 'callback_token');
        $successUrl = (string)$this->getSetting($branchId, 'success_redirect_url');
        $expiryMinutes = (string)($this->getSetting($branchId, 'expiry_minutes') ?: '20');
        $notifyUrl = BASE_URL . '/api/payment/notify.php?provider=nicepay&branch=' . $branchId;
        $callbackUrl = $successUrl !== '' ? $successUrl : (BASE_URL . '/order.php');

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">💳 Nicepay Payment Gateway</div>
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
                <span>Aktifkan Nicepay untuk cabang ini</span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="nicepay_mode_<?= (int)$branchId ?>">Mode</label>
                <select id="nicepay_mode_<?= (int)$branchId ?>" name="mode" class="form-control">
                  <option value="sandbox" <?= $mode === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                  <option value="production" <?= $mode === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="nicepay_pay_method_<?= (int)$branchId ?>">Pay Method</label>
                <input type="text" id="nicepay_pay_method_<?= (int)$branchId ?>" name="pay_method" class="form-control"
                       value="<?= htmlspecialchars($payMethod) ?>"
                       placeholder="Kosongkan untuk all payment method / contoh: 02, 08">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="nicepay_registration_base_url_<?= (int)$branchId ?>">Registration Base URL</label>
                <input type="text" id="nicepay_registration_base_url_<?= (int)$branchId ?>" name="registration_base_url" class="form-control"
                       value="<?= htmlspecialchars($registrationBaseUrl) ?>"
                       placeholder="https://dev.nicepay.co.id">
              </div>
              <div class="form-group">
                <label class="form-label" for="nicepay_checkout_base_url_<?= (int)$branchId ?>">Checkout URL</label>
                <input type="text" id="nicepay_checkout_base_url_<?= (int)$branchId ?>" name="checkout_base_url" class="form-control"
                       value="<?= htmlspecialchars($checkoutBaseUrl) ?>"
                       placeholder="https://dev.nicepay.co.id/nicepay/redirect/v2/payment">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="nicepay_merchant_id_<?= (int)$branchId ?>">IMID / Merchant ID</label>
                <input type="text" id="nicepay_merchant_id_<?= (int)$branchId ?>" name="merchant_id" class="form-control"
                       value="<?= htmlspecialchars($merchantId) ?>"
                       placeholder="IONPAYTEST / IMID sandbox Anda">
              </div>
              <div class="form-group">
                <label class="form-label" for="nicepay_merchant_key_<?= (int)$branchId ?>">Merchant Key</label>
                <input type="password" id="nicepay_merchant_key_<?= (int)$branchId ?>" name="merchant_key" class="form-control"
                       value="<?= htmlspecialchars($merchantKey) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="nicepay_expiry_minutes_<?= (int)$branchId ?>">Expiry Minutes</label>
                <input type="number" min="5" max="20" id="nicepay_expiry_minutes_<?= (int)$branchId ?>" name="expiry_minutes" class="form-control"
                       value="<?= htmlspecialchars($expiryMinutes) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="nicepay_success_redirect_url_<?= (int)$branchId ?>">Success Redirect URL</label>
                <input type="url" id="nicepay_success_redirect_url_<?= (int)$branchId ?>" name="success_redirect_url" class="form-control"
                       value="<?= htmlspecialchars($successUrl) ?>"
                       placeholder="<?= htmlspecialchars(BASE_URL . '/order.php') ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="nicepay_callback_token_<?= (int)$branchId ?>">Callback Token</label>
              <input type="text" id="nicepay_callback_token_<?= (int)$branchId ?>" name="callback_token" class="form-control"
                     value="<?= htmlspecialchars($callbackToken) ?>"
                     placeholder="Opsional, untuk proteksi endpoint webhook internal">
            </div>

            <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
              <strong>Sandbox flow yang dipakai plugin ini</strong><br>
              1. Registrasi ke <code>/nicepay/direct/v2/registration</code><br>
              2. Simpan <code>txid</code><br>
              3. Redirect customer ke <code>/nicepay/redirect/v2/payment?txid=...</code><br><br>
              <strong>Server-to-server DB Process URL</strong><br>
              <code style="word-break:break-all"><?= htmlspecialchars($notifyUrl) ?></code><br><br>
              <strong>Customer Callback URL</strong><br>
              <code style="word-break:break-all"><?= htmlspecialchars($callbackUrl) ?></code><br><br>
              <strong>Catatan:</strong> implementasi ini mengikuti pola resmi Checkout API sandbox Nicepay. Untuk produksi, sesuaikan domain, IMID, Merchant Key, dan metode pembayaran yang diaktifkan merchant.
            </div>

            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan Nicepay</button>
          </form>
        </div>
        <?php

        return ob_get_clean();
    }

    private function buildPayload(array $order, int $branchId): array
    {
        $branchModel = new BranchModel();
        $branch = $branchModel->find($branchId) ?: [];

        $timestamp = date('YmdHis');
        $amount = (string)max(1, (int)round((float)($order['total_amount'] ?? 0)));
        $expiryMinutes = max(5, min(20, (int)($this->getSetting($branchId, 'expiry_minutes') ?: 20)));
        $expiryDateTime = time() + ($expiryMinutes * 60);
        $currency = strtoupper((string)($branchModel->getCurrency($branchId) ?: 'IDR'));
        if ($currency === '') {
            $currency = 'IDR';
        }

        $billingAddress = (string)($order['delivery_address'] ?? $branch['address'] ?? '-');
        $billingCity = (string)($order['city'] ?? $branch['city'] ?? 'Jakarta');
        $billingState = (string)($branch['city'] ?? 'Jakarta');
        $billingPostCode = (string)($order['postal_code'] ?? $branch['postal_code'] ?? '10110');
        $billingCountry = strtoupper((string)($this->getSetting($branchId, 'billing_country') ?: 'ID'));
        $userIp = preg_replace('/[^0-9a-fA-F:\.]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')) ?: '127.0.0.1';

        $payload = [
            'timestamp' => $timestamp,
            'amt' => $amount,
            'referenceno' => (string)($order['order_number'] ?? ''),
            'goodsnm' => 'Order ' . (string)($order['order_number'] ?? ''),
            'currency' => $currency,
            'billingnm' => (string)($order['customer_name'] ?? 'Customer'),
            'billingphone' => $this->sanitizePhone((string)($order['customer_wa'] ?? '')),
            'billingemail' => (string)($order['customer_email'] ?? 'customer@example.com'),
            'billingaddr' => $billingAddress,
            'billingcity' => $billingCity,
            'billingstate' => $billingState,
            'billingpostcd' => preg_replace('/\D+/', '', $billingPostCode) ?: '10110',
            'billingcountry' => $billingCountry,
            'dbprocessurl' => BASE_URL . '/api/payment/notify.php?provider=nicepay&branch=' . $branchId,
            'callbackurl' => (string)($this->getSetting($branchId, 'success_redirect_url') ?: (BASE_URL . '/order.php')),
            'description' => 'Payment for order ' . (string)($order['order_number'] ?? ''),
            'userip' => $userIp,
            'cartdata' => json_encode($this->buildCartData($order), JSON_UNESCAPED_SLASHES),
            'paymentexpdt' => date('Ymd', $expiryDateTime),
            'paymentexptm' => date('His', $expiryDateTime),
        ];

        $payMethod = trim((string)$this->getSetting($branchId, 'pay_method'));
        if ($payMethod !== '') {
            $payload['paymethod'] = $payMethod;
        }

        return $payload;
    }

    private function buildCartData(array $order): array
    {
        $items = [];
        foreach ((array)($order['items'] ?? []) as $item) {
            $items[] = [
                'img_url' => '',
                'goods_name' => (string)($item['menu_name'] ?? $item['base_name'] ?? 'Item'),
                'goods_detail' => (string)($item['variant_label'] ?? $item['notes'] ?? ''),
                'goods_amt' => (string)max(0, (int)round((float)($item['subtotal'] ?? (($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0))))),
            ];
        }

        return [
            'count' => (string)count($items),
            'item' => $items,
        ];
    }

    private function sanitizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return '081234567890';
        }
        return substr($digits, 0, 15);
    }

    private function mapIncomingStatus(string $resultCode, string $status): ?string
    {
        if ($status !== '') {
            if (in_array($status, ['paid', 'success', 'settlement'], true)) {
                return 'paid';
            }
            if (in_array($status, ['pending', 'waiting', 'open'], true)) {
                return 'unpaid';
            }
            if (in_array($status, ['failed', 'cancelled', 'expired', 'deny'], true)) {
                return 'failed';
            }
        }

        if ($resultCode === '0000') {
            return 'paid';
        }

        if ($resultCode !== '') {
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
