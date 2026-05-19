<?php

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;
use App\Models\OrderModel;

/**
 * Midtrans Payment Plugin
 *
 * Cara kerja:
 *  1. order.created  → buat Snap transaction → simpan URL ke plugin_branch_settings
 *  2. chat.after_ai  → tempelkan link bayar ke balasan chatbot (satu kali, request yang sama)
 *  3. payment.notification → verifikasi & update payment_status order ke 'paid'
 *  4. settings.sections → tampilkan form konfigurasi di halaman Settings cabang
 */
class MidtransPaymentPlugin implements PluginInterface
{
    /** Snap URL sementara — diisi di order.created, dikonsumsi di chat.after_ai */
    private static string $pendingSnapUrl = '';

    public function getName(): string    { return 'Midtrans Payment'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'KopiBot Team'; }

    public function register(): void
    {
        // Setelah order dibuat → generate link bayar
        HookManager::addAction('order.created', [$this, 'onOrderCreated']);

        // Tempelkan link bayar ke balasan chatbot pada request yang sama
        HookManager::addFilter('chat.after_ai', [$this, 'appendPaymentLink'], 20);

        // Notifikasi pembayaran dari Midtrans
        HookManager::addAction('payment.notification', [$this, 'handleNotification']);

        // Tambahkan data pembayaran ke respons checkout web
        HookManager::addFilter('order.checkout_response', [$this, 'appendCheckoutPaymentData'], 20);

        // Tambah section pengaturan di dashboard super admin
        HookManager::addFilter('super.settings.sections', [$this, 'addSettingsSection'], 10);
    }

    // ── Hook: order.created ─────────────────────────────────────

    public function onOrderCreated(array $order): void
    {
        $branchId  = (int)($order['branch_id'] ?? 0);
        $serverKey = $this->getSetting($branchId, 'server_key');

        if (!$serverKey) {
            return; // plugin belum dikonfigurasi untuk cabang ini
        }

        $isProduction = $this->getSetting($branchId, 'is_production') === '1';
        $client       = new MidtransClient($serverKey, $isProduction);

        $snapUrl = $client->createSnap([
            'transaction_details' => [
                'order_id'     => $order['order_number'],
                'gross_amount' => (int)round((float)($order['total_amount'] ?? 0)),
            ],
            'customer_details' => [
                'first_name' => (string)($order['customer_name']  ?? ''),
                'phone'      => (string)($order['customer_wa']    ?? ''),
                'email'      => (string)($order['customer_email'] ?? ''),
            ],
            'callbacks' => [
                'finish' => BASE_URL . '/order.php',
            ],
        ]);

        if (!$snapUrl) {
            error_log("[midtrans-payment] Gagal membuat Snap untuk order {$order['order_number']}");
            return;
        }

        // Simpan untuk ditampilkan di order detail & dikonsumsi chat.after_ai
        $this->saveSetting($branchId, 'snap_url_' . $order['id'], $snapUrl);
        self::$pendingSnapUrl = $snapUrl;

        error_log("[midtrans-payment] Snap URL order {$order['order_number']}: {$snapUrl}");
    }

    // ── Filter: chat.after_ai ────────────────────────────────────

    /**
     * Tempelkan link bayar Midtrans ke akhir balasan chatbot.
     * Hanya dieksekusi jika $pendingSnapUrl terisi (checkout berhasil di request ini).
     */
    public function appendPaymentLink(string $reply, int $branchId, string $intent): string
    {
        if (self::$pendingSnapUrl === '') {
            return $reply;
        }

        $url              = self::$pendingSnapUrl;
        self::$pendingSnapUrl = '';

        return $reply . "\n\n💳 *Bayar sekarang:*\n" . $url;
    }

    // ── Action: payment.notification ────────────────────────────

    /**
     * Dipanggil dari /api/payment/notify.php?provider=midtrans&branch={id}
     */
    public function handleNotification(string $provider, array $payload, int $branchId): void
    {
        if ($provider !== 'midtrans') {
            return;
        }

        $serverKey = $this->getSetting($branchId, 'server_key');
        if (!$serverKey) {
            return;
        }

        $isProduction = $this->getSetting($branchId, 'is_production') === '1';
        $client       = new MidtransClient($serverKey, $isProduction);

        if (!$client->verifyNotification($payload)) {
            error_log('[midtrans-payment] Signature notifikasi tidak valid');
            return;
        }

        $orderNumber = (string)($payload['order_id']           ?? '');
        $txStatus    = (string)($payload['transaction_status'] ?? '');
        $fraudStatus = (string)($payload['fraud_status']       ?? 'accept');

        $paid = ($txStatus === 'capture'    && $fraudStatus === 'accept')
             || ($txStatus === 'settlement');

        $failed = in_array($txStatus, ['cancel', 'deny', 'expire', 'failure'], true);

        if ($orderNumber && ($paid || $failed)) {
            $orderModel = new OrderModel();
            $order      = $orderModel->findByOrderNumber($orderNumber);

            if ($order) {
                $orderModel->updatePayment(
                    (int)$order['id'],
                    $paid ? 'paid' : 'failed',
                );
                error_log("[midtrans-payment] Order {$orderNumber} payment → " . ($paid ? 'paid' : 'failed'));
            }
        }
    }

    // ── Filter: order.checkout_response ─────────────────────────

    public function appendCheckoutPaymentData(array $responseData, array $order, int $branchId): array
    {
        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0) {
            return $responseData;
        }

        $snapUrl = (string)($this->getSetting($branchId, 'snap_url_' . $orderId) ?? '');
        if ($snapUrl === '') {
            return $responseData;
        }

        $responseData['payment'] = [
            'provider' => 'midtrans',
            'status'   => (string)($order['payment_status'] ?? 'unpaid'),
            'url'      => $snapUrl,
            'label'    => 'Bayar Sekarang',
        ];

        return $responseData;
    }

    // ── Filter: settings.sections ───────────────────────────────

    public function addSettingsSection(array $sections, int $branchId): array
    {
        $serverKey    = (string)($this->getSetting($branchId, 'server_key')    ?? '');
        $clientKey    = (string)($this->getSetting($branchId, 'client_key')    ?? '');
        $isProduction = $this->getSetting($branchId, 'is_production') === '1';
        $notifUrl     = BASE_URL . '/api/payment/notify.php?provider=midtrans&branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">💳 Midtrans Payment Gateway</div>

          <form method="POST">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="action"      value="save_plugin_settings">
            <input type="hidden" name="branch_id"   value="<?= (int)$branchId ?>">
            <input type="hidden" name="plugin_slug" value="midtrans-payment">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="mdt_server_key">Server Key</label>
                <input type="password" id="mdt_server_key" name="server_key" class="form-control"
                       value="<?= htmlspecialchars($serverKey) ?>"
                       placeholder="SB-Mid-server-xxxx (sandbox) / Mid-server-xxxx (prod)">
              </div>
              <div class="form-group">
                <label class="form-label" for="mdt_client_key">Client Key <small style="color:var(--text-light)">(untuk Snap.js)</small></label>
                <input type="text" id="mdt_client_key" name="client_key" class="form-control"
                       value="<?= htmlspecialchars($clientKey) ?>"
                       placeholder="SB-Mid-client-xxxx">
              </div>
            </div>

            <div class="form-group">
              <!-- hidden field agar 'is_production=0' terkirim saat checkbox tidak dicentang -->
              <input type="hidden" name="is_production" value="0">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_production" value="1"
                       <?= $isProduction ? 'checked' : '' ?>>
                <span>Production mode <small style="color:var(--text-light)">(hilangkan centang untuk Sandbox)</small></span>
              </label>
            </div>

            <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem">
              <strong>Notification URL</strong> — daftarkan di
              <a href="https://dashboard.midtrans.com" target="_blank" rel="noopener">Midtrans Dashboard</a>
              → Settings → Payment → Notification URL:<br>
              <code style="word-break:break-all"><?= htmlspecialchars($notifUrl) ?></code>
            </div>

            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan Midtrans</button>
          </form>
        </div>
        <?php
        $sections['midtrans-payment'] = ob_get_clean();
        return $sections;
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function getSetting(int $branchId, string $key): ?string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute(['midtrans-payment', $branchId, $key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['setting_val'] : null;
    }

    private function saveSetting(int $branchId, string $key, string $value): void
    {
        Database::getInstance()->prepare(
            'INSERT INTO plugin_branch_settings (plugin_slug, branch_id, setting_key, setting_val)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
        )->execute(['midtrans-payment', $branchId, $key, $value]);
    }
}
