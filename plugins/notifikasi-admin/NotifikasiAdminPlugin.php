<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Helpers\Currency;
use App\Helpers\Csrf;
use App\Config\Database;
use App\Models\BranchModel;

class NotifikasiAdminPlugin implements PluginInterface
{
    private const SLUG = 'notifikasi-admin';

    public function getName(): string    { return 'Notifikasi Admin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'KopiBot Team'; }

    public function register(): void
    {
        HookManager::addAction('order.created',            [$this, 'onOrderCreated']);
        HookManager::addFilter('dashboard.topbar_actions', [$this, 'renderBell'],   10);
        HookManager::addFilter('dashboard.nav_items',      [$this, 'addNavItem'],   10);
        HookManager::addFilter('super.settings.sections',  [$this, 'tambahForm'],   10);
    }

    // ── Action: order.created ───────────────────────────────────

    public function onOrderCreated(array $order): void
    {
        $branchId = (int)($order['branch_id']     ?? 0);
        $orderId  = (int)($order['id']             ?? 0);
        $orderNum = (string)($order['order_number'] ?? '-');
        $customer = (string)($order['customer_name'] ?? '-');
        $currency = (new BranchModel())->getCurrency($branchId);
        $total    = Currency::format((float)($order['total_amount'] ?? 0), $currency);
        $channel  = strtoupper((string)($order['channel'] ?? ''));

        $emailTujuan = $this->getSetting($branchId, 'email_admin');
        if ($emailTujuan) {
            $subject = "[KopiBot] Order Baru: {$orderNum}";
            $body    = "Order baru masuk!\n\n"
                     . "No. Order : {$orderNum}\n"
                     . "Customer  : {$customer}\n"
                     . "Total     : {$total}\n"
                     . "Channel   : {$channel}\n"
                     . "Waktu     : " . date('d/m/Y H:i') . "\n";

            $sent   = $this->sendEmail($branchId, $emailTujuan, $subject, $body);
            $status = $sent ? 'sent' : 'failed';
            $this->logNotification($branchId, $orderId, 'order_new', 'email', $emailTujuan, $status);
        }

        // Notifikasi in-app
        $payload = (string)json_encode([
            'order_number' => $orderNum,
            'customer'     => $customer,
            'total'        => $total,
            'channel'      => $channel,
        ]);
        $this->logNotification($branchId, $orderId, 'order_new', 'dashboard', 'admin', 'pending', $payload);
    }

    // ── Filter: dashboard.topbar_actions ───────────────────────

    public function renderBell(string $html, int $branchId, string $role): string
    {
        if ($branchId <= 0 || $role !== 'branch_admin') {
            return $html;
        }

        $unread  = $this->countUnread($branchId);
        $baseUrl = BASE_URL;
        $label   = $unread > 99 ? '99+' : (string)$unread;
        $badge   = $unread > 0 ? '<span class="notif-badge">' . $label . '</span>' : '';

        return $html
            . '<a href="' . $baseUrl . '/dashboard/branch/notifications.php"'
            . ' class="topbar-icon-btn" title="Notifikasi order baru">'
            . '🔔' . $badge
            . '</a>';
    }

    // ── Filter: dashboard.nav_items ─────────────────────────────

    public function addNavItem(array $items, string $role): array
    {
        if ($role !== 'branch_admin') {
            return $items;
        }

        if (isset($items['Order'])) {
            $items['Order'][] = [
                'url'   => '/dashboard/branch/notifications.php',
                'icon'  => '🔔',
                'label' => 'Notifikasi',
            ];
        }

        return $items;
    }

    // ── Filter: settings.sections ───────────────────────────────

    public function tambahForm(array $sections, int $branchId): array
    {
        $emailSaved  = htmlspecialchars((string)($this->getSetting($branchId, 'email_admin')      ?? ''));
        $smtpHost    = htmlspecialchars((string)($this->getSetting($branchId, 'smtp_host')        ?? ''));
        $smtpPort    = htmlspecialchars((string)($this->getSetting($branchId, 'smtp_port')        ?? '587'));
        $smtpEnc     = $this->getSetting($branchId, 'smtp_encryption') ?? 'tls';
        $smtpUser    = htmlspecialchars((string)($this->getSetting($branchId, 'smtp_user')        ?? ''));
        $smtpPass    = htmlspecialchars((string)($this->getSetting($branchId, 'smtp_pass')        ?? ''));
        $smtpFrom    = htmlspecialchars((string)($this->getSetting($branchId, 'smtp_from_email')  ?? ''));
        $smtpName    = htmlspecialchars((string)($this->getSetting($branchId, 'smtp_from_name')   ?? 'KopiBot'));

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">🔔 Notifikasi Admin</div>
          <p style="font-size:.875rem;color:var(--text-light);margin-bottom:14px">
            Notifikasi in-app selalu aktif. Konfigurasi SMTP untuk mengaktifkan notifikasi email setiap ada order baru.
          </p>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action"      value="save_plugin_settings">
            <input type="hidden" name="branch_id"   value="<?= (int)$branchId ?>">
            <input type="hidden" name="plugin_slug" value="<?= self::SLUG ?>">

            <div class="form-group" style="max-width:420px">
              <label class="form-label" for="na_email">Email Tujuan Notifikasi</label>
              <input type="email" id="na_email" name="email_admin" class="form-control"
                     value="<?= $emailSaved ?>" placeholder="admin@tokokamu.com">
              <small style="color:var(--text-light)">Kosongkan untuk menonaktifkan notifikasi email.</small>
            </div>

            <hr style="margin:20px 0;border-color:var(--border)">
            <p style="font-weight:600;margin-bottom:14px">Konfigurasi SMTP</p>

            <div style="display:grid;grid-template-columns:1fr 140px;gap:12px;max-width:560px">
              <div class="form-group">
                <label class="form-label" for="na_smtp_host">SMTP Host</label>
                <input type="text" id="na_smtp_host" name="smtp_host" class="form-control"
                       value="<?= $smtpHost ?>" placeholder="smtp.gmail.com">
              </div>
              <div class="form-group">
                <label class="form-label" for="na_smtp_port">Port</label>
                <input type="number" id="na_smtp_port" name="smtp_port" class="form-control"
                       value="<?= $smtpPort ?>" placeholder="587">
              </div>
            </div>

            <div class="form-group" style="max-width:200px">
              <label class="form-label" for="na_smtp_enc">Enkripsi</label>
              <select id="na_smtp_enc" name="smtp_encryption" class="form-control">
                <option value="tls"  <?= $smtpEnc === 'tls'  ? 'selected' : '' ?>>TLS (port 587)</option>
                <option value="ssl"  <?= $smtpEnc === 'ssl'  ? 'selected' : '' ?>>SSL (port 465)</option>
                <option value="none" <?= $smtpEnc === 'none' ? 'selected' : '' ?>>None (port 25)</option>
              </select>
            </div>

            <div class="form-group" style="max-width:420px">
              <label class="form-label" for="na_smtp_user">Username SMTP</label>
              <input type="text" id="na_smtp_user" name="smtp_user" class="form-control"
                     value="<?= $smtpUser ?>" placeholder="emailkamu@gmail.com">
            </div>

            <div class="form-group" style="max-width:420px">
              <label class="form-label" for="na_smtp_pass">Password SMTP</label>
              <input type="password" id="na_smtp_pass" name="smtp_pass" class="form-control"
                     value="<?= $smtpPass ?>" placeholder="App password / password email">
              <small style="color:var(--text-light)">
                Gmail: gunakan <strong>App Password</strong> (bukan password biasa).
                Aktifkan 2FA dulu di akun Google, lalu buat App Password di
                <em>myaccount.google.com → Security → App passwords</em>.
              </small>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:560px">
              <div class="form-group">
                <label class="form-label" for="na_smtp_from">Email Pengirim</label>
                <input type="email" id="na_smtp_from" name="smtp_from_email" class="form-control"
                       value="<?= $smtpFrom ?>" placeholder="noreply@tokokamu.com">
              </div>
              <div class="form-group">
                <label class="form-label" for="na_smtp_name">Nama Pengirim</label>
                <input type="text" id="na_smtp_name" name="smtp_from_name" class="form-control"
                       value="<?= $smtpName ?>" placeholder="KopiBot">
              </div>
            </div>

            <button type="submit" class="btn btn-primary">💾 Simpan</button>
          </form>
        </div>
        <?php
        $sections[self::SLUG] = ob_get_clean();
        return $sections;
    }

    // ── Email ────────────────────────────────────────────────────

    private function sendEmail(int $branchId, string $to, string $subject, string $body): bool
    {
        $smtpHost = $this->getSetting($branchId, 'smtp_host');

        if ($smtpHost) {
            require_once __DIR__ . '/SmtpMailer.php';
            return SmtpMailer::send([
                'smtp_host'       => $smtpHost,
                'smtp_port'       => $this->getSetting($branchId, 'smtp_port')        ?? '587',
                'smtp_encryption' => $this->getSetting($branchId, 'smtp_encryption')  ?? 'tls',
                'smtp_user'       => $this->getSetting($branchId, 'smtp_user')        ?? '',
                'smtp_pass'       => $this->getSetting($branchId, 'smtp_pass')        ?? '',
                'smtp_from_email' => $this->getSetting($branchId, 'smtp_from_email')  ?? '',
                'smtp_from_name'  => $this->getSetting($branchId, 'smtp_from_name')   ?? 'KopiBot',
            ], $to, $subject, $body);
        }

        // Fallback ke mail() jika SMTP belum dikonfigurasi
        return (bool)@mail($to, $subject, $body, 'From: noreply@tokokopi.com');
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function getSetting(int $branchId, string $key): ?string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute([self::SLUG, $branchId, $key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['setting_val'] : null;
    }

    private function countUnread(int $branchId): int
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*) FROM notification_logs
             WHERE branch_id = ? AND channel = ? AND status = ?'
        );
        $stmt->execute([$branchId, 'dashboard', 'pending']);
        return (int)$stmt->fetchColumn();
    }

    private function logNotification(
        int    $branchId,
        int    $orderId,
        string $type,
        string $channel,
        string $recipient,
        string $status,
        string $payload = ''
    ): void {
        Database::getInstance()->prepare(
            'INSERT INTO notification_logs
                (branch_id, order_id, type, channel, recipient, status, payload, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $branchId ?: null,
            $orderId  ?: null,
            $type,
            $channel,
            $recipient,
            $status,
            $payload ?: null,
            in_array($status, ['sent', 'failed'], true) ? date('Y-m-d H:i:s') : null,
        ]);
    }
}
