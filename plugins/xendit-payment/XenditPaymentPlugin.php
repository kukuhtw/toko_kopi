<?php

use App\Config\Database;
use App\Helpers\Csrf;
use App\Models\BranchModel;
use App\Models\OrderModel;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

/**
 * Xendit Payment Plugin
 *
 * Flow:
 * 1. order.created -> create Xendit invoice/payment link
 * 2. chat.after_ai -> append payment URL for chatbot checkout flow
 * 3. order.checkout_response -> expose payment URL to web checkout
 * 4. payment.notification -> verify webhook token and update order payment status
 * 5. settings sections -> configure branch-level Xendit credentials
 */
class XenditPaymentPlugin implements PluginInterface
{
    private const PLUGIN_SLUG = 'xendit-payment';

    private static string $pendingInvoiceUrl = '';

    public function getName(): string
    {
        return 'Xendit Payment';
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
        HookManager::addAction('order.created', [$this, 'onOrderCreated']);
        HookManager::addFilter('chat.after_ai', [$this, 'appendPaymentLink'], 20);
        HookManager::addAction('payment.notification', [$this, 'handleNotification']);
        HookManager::addFilter('order.checkout_response', [$this, 'appendCheckoutPaymentData'], 20);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 12);
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 12);
    }

    public function onOrderCreated(array $order): void
    {
        $branchId = (int)($order['branch_id'] ?? 0);
        if ($branchId <= 0 || !$this->isEnabled($branchId)) {
            return;
        }

        $secretKey = (string)$this->getSetting($branchId, 'secret_api_key');
        if ($secretKey === '') {
            return;
        }

        $invoice = $this->createInvoiceForOrder($order, $branchId, $secretKey);
        if (!$invoice) {
            error_log('[xendit-payment] Failed to create invoice for order ' . (string)($order['order_number'] ?? '-'));
            return;
        }

        $orderId = (int)($order['id'] ?? 0);
        $invoiceUrl = (string)($invoice['invoice_url'] ?? '');
        $invoiceId = (string)($invoice['id'] ?? '');

        if ($orderId > 0 && $invoiceUrl !== '') {
            $this->saveSetting($branchId, 'invoice_url_' . $orderId, $invoiceUrl);
            $this->saveSetting($branchId, 'invoice_id_' . $orderId, $invoiceId);
            self::$pendingInvoiceUrl = $invoiceUrl;
            error_log('[xendit-payment] Invoice URL order ' . (string)($order['order_number'] ?? '-') . ': ' . $invoiceUrl);
        }
    }

    public function appendPaymentLink(string $reply, int $branchId, string $intent, array $context = []): string
    {
        if (self::$pendingInvoiceUrl === '' || !$this->isEnabled($branchId)) {
            return $reply;
        }

        $url = self::$pendingInvoiceUrl;
        self::$pendingInvoiceUrl = '';

        $lang = (string)($context['language'] ?? 'id');
        $label = $lang === 'en' ? 'Pay now' : 'Bayar sekarang';

        return rtrim($reply) . "\n\n💳 *{$label}:*\n" . $url;
    }

    public function handleNotification(string $provider, array $payload, int $branchId): void
    {
        if ($provider !== 'xendit' || !$this->isEnabled($branchId)) {
            return;
        }

        $secretKey = (string)$this->getSetting($branchId, 'secret_api_key');
        $webhookToken = (string)$this->getSetting($branchId, 'webhook_token');
        if ($secretKey === '' || $webhookToken === '') {
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

        $client = new XenditClient($secretKey);
        if (!$client->verifyWebhookToken($receivedToken, $webhookToken)) {
            error_log('[xendit-payment] Invalid webhook token');
            return;
        }

        $orderNumber = (string)($payload['external_id'] ?? '');
        $invoiceStatus = strtoupper((string)($payload['status'] ?? ''));

        if ($orderNumber === '' || $invoiceStatus === '') {
            return;
        }

        $orderModel = new OrderModel();
        $order = $orderModel->findByOrderNumber($orderNumber);
        if (!$order) {
            return;
        }

        if ($invoiceStatus === 'PAID') {
            $orderModel->updatePayment((int)$order['id'], 'paid');
            error_log('[xendit-payment] Order ' . $orderNumber . ' payment -> paid');
            return;
        }

        if (in_array($invoiceStatus, ['EXPIRED', 'FAILED'], true)) {
            $orderModel->updatePayment((int)$order['id'], 'unpaid');
            error_log('[xendit-payment] Order ' . $orderNumber . ' payment -> unpaid (' . $invoiceStatus . ')');
        }
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

        $invoiceUrl = (string)$this->getSetting($branchId, 'invoice_url_' . $orderId);
        if ($invoiceUrl === '') {
            return $responseData;
        }

        $responseData['payment'] = [
            'provider' => 'xendit',
            'status' => (string)($order['payment_status'] ?? 'unpaid'),
            'url' => $invoiceUrl,
            'label' => 'Bayar via Xendit',
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
        $secretKey = (string)$this->getSetting($branchId, 'secret_api_key');
        $webhookToken = (string)$this->getSetting($branchId, 'webhook_token');
        $invoiceDuration = (string)($this->getSetting($branchId, 'invoice_duration') ?: '86400');
        $successRedirectUrl = (string)$this->getSetting($branchId, 'success_redirect_url');
        $failureRedirectUrl = (string)$this->getSetting($branchId, 'failure_redirect_url');
        $enabled = $this->isEnabled($branchId);
        $notifUrl = BASE_URL . '/api/payment/notify.php?provider=xendit&branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">💳 Xendit Payment Gateway</div>

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
                <span>Aktifkan Xendit untuk cabang ini</span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="xnd_secret_api_key_<?= (int)$branchId ?>">Secret API Key</label>
                <input type="password" id="xnd_secret_api_key_<?= (int)$branchId ?>" name="secret_api_key" class="form-control"
                       value="<?= htmlspecialchars($secretKey) ?>"
                       placeholder="xnd_development_... / xnd_production_...">
              </div>
              <div class="form-group">
                <label class="form-label" for="xnd_webhook_token_<?= (int)$branchId ?>">Webhook Token</label>
                <input type="text" id="xnd_webhook_token_<?= (int)$branchId ?>" name="webhook_token" class="form-control"
                       value="<?= htmlspecialchars($webhookToken) ?>"
                       placeholder="Token dari Xendit Dashboard > Webhooks">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="xnd_invoice_duration_<?= (int)$branchId ?>">Invoice Duration (detik)</label>
                <input type="number" min="300" step="1" id="xnd_invoice_duration_<?= (int)$branchId ?>" name="invoice_duration" class="form-control"
                       value="<?= htmlspecialchars($invoiceDuration) ?>"
                       placeholder="86400">
              </div>
              <div class="form-group">
                <label class="form-label" for="xnd_success_redirect_url_<?= (int)$branchId ?>">Success Redirect URL</label>
                <input type="url" id="xnd_success_redirect_url_<?= (int)$branchId ?>" name="success_redirect_url" class="form-control"
                       value="<?= htmlspecialchars($successRedirectUrl) ?>"
                       placeholder="<?= htmlspecialchars(BASE_URL . '/order.php') ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="xnd_failure_redirect_url_<?= (int)$branchId ?>">Failure Redirect URL</label>
              <input type="url" id="xnd_failure_redirect_url_<?= (int)$branchId ?>" name="failure_redirect_url" class="form-control"
                     value="<?= htmlspecialchars($failureRedirectUrl) ?>"
                     placeholder="<?= htmlspecialchars(BASE_URL . '/order.php') ?>">
            </div>

            <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.65">
              <strong>Webhook URL</strong> - daftarkan di Xendit Dashboard > Webhooks untuk event <code>invoice</code>:<br>
              <code style="word-break:break-all"><?= htmlspecialchars($notifUrl) ?></code><br><br>
              <strong>Catatan:</strong> plugin ini menggunakan Xendit Payment Link / Invoice API dan akan membuat <code>invoice_url</code> setelah order dibuat.
            </div>

            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan Xendit</button>
          </form>
        </div>
        <?php

        return ob_get_clean();
    }

    private function isEnabled(int $branchId): bool
    {
        return $this->getSetting($branchId, 'is_enabled') === '1';
    }

    private function createInvoiceForOrder(array $order, int $branchId, string $secretKey): ?array
    {
        $client = new XenditClient($secretKey);
        $branchModel = new BranchModel();
        $currency = $branchModel->getCurrency($branchId);
        $invoiceDuration = max(300, (int)($this->getSetting($branchId, 'invoice_duration') ?: 86400));
        $successRedirectUrl = trim((string)$this->getSetting($branchId, 'success_redirect_url'));
        $failureRedirectUrl = trim((string)$this->getSetting($branchId, 'failure_redirect_url'));

        $items = [];
        foreach ((array)($order['items'] ?? []) as $item) {
            $items[] = [
                'name' => (string)($item['menu_name'] ?? $item['base_name'] ?? $item['name'] ?? 'Item'),
                'quantity' => (int)($item['quantity'] ?? 1),
                'price' => (float)($item['unit_price'] ?? 0),
                'category' => (string)($item['category_name'] ?? 'Menu'),
            ];
        }

        $payload = [
            'external_id' => (string)($order['order_number'] ?? ''),
            'amount' => (float)($order['total_amount'] ?? 0),
            'description' => 'Payment for order ' . (string)($order['order_number'] ?? ''),
            'invoice_duration' => $invoiceDuration,
            'currency' => $currency ?: 'IDR',
            'customer' => [
                'given_names' => (string)($order['customer_name'] ?? ''),
                'email' => (string)($order['customer_email'] ?? ''),
                'mobile_number' => (string)($order['customer_wa'] ?? ''),
            ],
            'items' => $items,
            'metadata' => [
                'order_id' => (int)($order['id'] ?? 0),
                'branch_id' => $branchId,
                'provider' => 'xendit',
            ],
        ];

        if ($successRedirectUrl !== '') {
            $payload['success_redirect_url'] = $successRedirectUrl;
        }
        if ($failureRedirectUrl !== '') {
            $payload['failure_redirect_url'] = $failureRedirectUrl;
        }

        return $client->createInvoice($payload);
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
