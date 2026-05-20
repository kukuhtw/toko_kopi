<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Csrf;
use App\Models\CustomerModel;
use App\Plugin\PluginInterface;
use App\Plugin\HookManager;
use App\WhatsAppProviders\ProviderFactory;

class CustomerCrmPlugin implements PluginInterface
{
    private const SLUG = 'customer-crm';
    private static bool $schemaReady = false;

    public function getName(): string
    {
        return 'Customer CRM';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'KopiBot Team';
    }

    public function register(): void
    {
        $this->ensureSchema();
        $this->backfillLoyaltyHistory();

        HookManager::addFilter('super.settings.sections', [$this, 'addSettingsSection'], 18);
        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItems'], 18);
        HookManager::addAction('loyalty.points_changed', [$this, 'handleLoyaltyPointsChanged'], 18);
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
            'url' => '/dashboard/branch/crm.php',
            'icon' => 'CRM',
            'label' => 'Customer CRM',
        ];

        return $navItems;
    }

    public function addSettingsSection(array $sections, int $branchId): array
    {
        $defaultCountryCode = htmlspecialchars($this->getSetting('default_country_code', '+62'));
        $notifyWhatsapp = $this->getSetting('notify_loyalty_whatsapp', '1') === '1';
        $notifyEmail = $this->getSetting('notify_loyalty_email', '0') === '1';
        $notifyEarn = $this->getSetting('notify_on_earn', '1') === '1';
        $notifyRedeem = $this->getSetting('notify_on_redeem', '1') === '1';
        $notifyRefund = $this->getSetting('notify_on_refund', '1') === '1';
        $subjectTemplate = htmlspecialchars($this->getSetting('email_subject_template', 'Update poin loyalty untuk {customer_name}'));
        $earnTemplate = htmlspecialchars($this->getSetting('message_template_earn', "Halo {customer_name}, kamu mendapat {points_delta} poin dari order {order_number}. Saldo poin kamu sekarang {balance_points}."));
        $redeemTemplate = htmlspecialchars($this->getSetting('message_template_redeem', "Halo {customer_name}, {points_abs} poin berhasil dipakai untuk order {order_number}. Sisa saldo poin kamu sekarang {balance_points}."));
        $refundTemplate = htmlspecialchars($this->getSetting('message_template_refund', "Halo {customer_name}, {points_delta} poin sudah dikembalikan untuk order {order_number}. Saldo poin kamu sekarang {balance_points}."));

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Customer CRM</div>
          <p style="font-size:.875rem;color:var(--text-light);margin-bottom:14px">
            Normalisasi identitas customer berbasis email dan WhatsApp dengan country code, plus notifikasi loyalty otomatis ke customer.
          </p>
          <p style="font-size:.8rem;color:var(--text-light);margin-bottom:14px">
            Pengaturan CRM ini berlaku global untuk semua cabang. Branch admin hanya memakai konfigurasi yang ditetapkan dari halaman ini.
          </p>

          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_global_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::SLUG ?>">

            <div class="form-row">
              <div class="form-group" style="max-width:220px">
                <label class="form-label" for="crm_default_cc">Default Country Code</label>
                <input type="text" id="crm_default_cc" name="default_country_code" class="form-control"
                       value="<?= $defaultCountryCode ?>" placeholder="+62">
                <small style="color:var(--text-light)">Dipakai saat nomor customer belum memakai format internasional.</small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <input type="hidden" name="notify_loyalty_whatsapp" value="0">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="notify_loyalty_whatsapp" value="1" <?= $notifyWhatsapp ? 'checked' : '' ?>>
                  <span>Kirim notifikasi loyalty via WhatsApp</span>
                </label>
              </div>
              <div class="form-group">
                <input type="hidden" name="notify_loyalty_email" value="0">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="notify_loyalty_email" value="1" <?= $notifyEmail ? 'checked' : '' ?>>
                  <span>Kirim notifikasi loyalty via email</span>
                </label>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <input type="hidden" name="notify_on_earn" value="0">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="notify_on_earn" value="1" <?= $notifyEarn ? 'checked' : '' ?>>
                  <span>Notifikasi saat customer mendapat poin</span>
                </label>
              </div>
              <div class="form-group">
                <input type="hidden" name="notify_on_redeem" value="0">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="notify_on_redeem" value="1" <?= $notifyRedeem ? 'checked' : '' ?>>
                  <span>Notifikasi saat customer memakai poin</span>
                </label>
              </div>
              <div class="form-group">
                <input type="hidden" name="notify_on_refund" value="0">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                  <input type="checkbox" name="notify_on_refund" value="1" <?= $notifyRefund ? 'checked' : '' ?>>
                  <span>Notifikasi saat poin dikembalikan</span>
                </label>
              </div>
            </div>

            <div class="form-group" style="max-width:720px">
              <label class="form-label" for="crm_email_subject">Template Subject Email</label>
              <input type="text" id="crm_email_subject" name="email_subject_template" class="form-control"
                     value="<?= $subjectTemplate ?>">
            </div>

            <div class="form-group" style="max-width:720px">
              <label class="form-label" for="crm_tpl_earn">Template Pesan Earn</label>
              <textarea id="crm_tpl_earn" name="message_template_earn" class="form-control" rows="3"><?= $earnTemplate ?></textarea>
            </div>

            <div class="form-group" style="max-width:720px">
              <label class="form-label" for="crm_tpl_redeem">Template Pesan Redeem</label>
              <textarea id="crm_tpl_redeem" name="message_template_redeem" class="form-control" rows="3"><?= $redeemTemplate ?></textarea>
            </div>

            <div class="form-group" style="max-width:720px">
              <label class="form-label" for="crm_tpl_refund">Template Pesan Refund</label>
              <textarea id="crm_tpl_refund" name="message_template_refund" class="form-control" rows="3"><?= $refundTemplate ?></textarea>
            </div>

            <small style="display:block;color:var(--text-light);margin-bottom:14px">
              Placeholder tersedia: <code>{customer_name}</code>, <code>{order_number}</code>, <code>{points_delta}</code>,
              <code>{points_abs}</code>, <code>{balance_points}</code>, <code>{lifetime_points}</code>, <code>{description}</code>.
            </small>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan CRM</button>
          </form>
        </div>
        <?php

        $sections[self::SLUG] = ob_get_clean();
        return $sections;
    }

    public function handleLoyaltyPointsChanged(array $event): void
    {
        $type = (string)($event['transaction_type'] ?? '');
        if (!$this->shouldNotifyForType($type)) {
            return;
        }

        $branchId = (int)($event['branch_id'] ?? 0);
        $customerId = (int)($event['customer_id'] ?? 0);
        if ($branchId <= 0 || $customerId <= 0) {
            return;
        }

        $customer = (new CustomerModel())->find($customerId);
        if (!$customer) {
            return;
        }

        $orderNumber = $this->getOrderNumber((int)($event['order_id'] ?? 0));
        $message = $this->renderMessageTemplate($type, $customer, $event, $orderNumber);
        if ($message === '') {
            return;
        }

        $subject = $this->renderSubject($customer, $event, $orderNumber);

        if ($this->getSetting('notify_loyalty_whatsapp', '1') === '1') {
            $whatsapp = (string)($customer['whatsapp'] ?? '');
            $sent = $this->sendWhatsApp($branchId, $whatsapp, $message);
            $this->logNotification($branchId, $customerId, (int)($event['order_id'] ?? 0), 'whatsapp', $type, $whatsapp, $message, $sent);
        }

        if ($this->getSetting('notify_loyalty_email', '0') === '1') {
            $email = CustomerModel::normalizeEmail((string)($customer['email'] ?? ''));
            $sent = $this->sendEmail($email, $subject, $message);
            $this->logNotification($branchId, $customerId, (int)($event['order_id'] ?? 0), 'email', $type, $email, $message, $sent);
        }
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $sqlFile = __DIR__ . '/schema.sql';
        if (!is_file($sqlFile)) {
            throw new \RuntimeException('Schema file not found for plugin customer-crm.');
        }

        $sql = trim((string) file_get_contents($sqlFile));
        if ($sql === '') {
            self::$schemaReady = true;
            return;
        }

        foreach ($this->splitSqlStatements($sql) as $statement) {
            Database::getInstance()->exec($statement);
        }

        self::$schemaReady = true;
    }

    /**
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $prev = $i > 0 ? $sql[$i - 1] : '';

            if ($char === "'" && !$inDoubleQuote && $prev !== '\\') {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote && $prev !== '\\') {
                $inDoubleQuote = !$inDoubleQuote;
            }

            if (!$inSingleQuote && !$inDoubleQuote) {
                if ($char === '#' || ($char === '-' && substr($sql, $i, 2) === '--')) {
                    while ($i < $length && $sql[$i] !== "\n") {
                        $i++;
                    }
                    continue;
                }

                if ($char === ';') {
                    $statement = trim($buffer);
                    if ($statement !== '') {
                        $statements[] = $statement;
                    }
                    $buffer = '';
                    continue;
                }
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    private function shouldNotifyForType(string $type): bool
    {
        return match ($type) {
            'earn' => $this->getSetting('notify_on_earn', '1') === '1',
            'redeem' => $this->getSetting('notify_on_redeem', '1') === '1',
            'refund' => $this->getSetting('notify_on_refund', '1') === '1',
            default => false,
        };
    }

    private function renderSubject(array $customer, array $event, string $orderNumber): string
    {
        $template = $this->getSetting('email_subject_template', 'Update poin loyalty untuk {customer_name}');
        return $this->replacePlaceholders($template, $customer, $event, $orderNumber);
    }

    private function renderMessageTemplate(string $type, array $customer, array $event, string $orderNumber): string
    {
        $template = match ($type) {
            'earn' => $this->getSetting('message_template_earn', ''),
            'redeem' => $this->getSetting('message_template_redeem', ''),
            'refund' => $this->getSetting('message_template_refund', ''),
            default => '',
        };

        return trim($this->replacePlaceholders($template, $customer, $event, $orderNumber));
    }

    private function backfillLoyaltyHistory(): void
    {
        if ($this->getAppSetting($this->buildSettingKey('loyalty_backfill_done'), '0') === '1') {
            return;
        }

        try {
            if (!$this->tableExists('loyalty_point_transactions')) {
                $this->setAppSetting($this->buildSettingKey('loyalty_backfill_done'), '1');
                return;
            }

            $stmt = Database::getInstance()->query(
                'SELECT
                    lpt.id,
                    lpt.branch_id,
                    lpt.customer_id,
                    lpt.order_id,
                    lpt.transaction_type,
                    lpt.description,
                    c.email,
                    c.whatsapp
                 FROM loyalty_point_transactions lpt
                 JOIN customers c ON c.id = lpt.customer_id
                 ORDER BY lpt.id ASC'
            );

            foreach ($stmt->fetchAll() as $row) {
                $channel = 'history';
                $eventHash = sha1('crm-backfill-loyalty-tx|' . (string)($row['id'] ?? '0'));
                $recipient = CustomerModel::normalizeEmail((string)($row['email'] ?? ''));
                if ($recipient === '') {
                    $recipient = (new CustomerModel())->normalizeWhatsApp((string)($row['whatsapp'] ?? ''), $this->getSetting('default_country_code', '+62'));
                }

                Database::getInstance()->prepare(
                    'INSERT INTO crm_notification_logs
                        (branch_id, customer_id, order_id, event_hash, channel, event_type, recipient, message_preview, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE status = status'
                )->execute([
                    (int)($row['branch_id'] ?? 0),
                    (int)($row['customer_id'] ?? 0),
                    !empty($row['order_id']) ? (int)$row['order_id'] : null,
                    $eventHash,
                    $channel,
                    (string)($row['transaction_type'] ?? 'unknown'),
                    $recipient,
                    $this->buildBackfillPreview((string)($row['transaction_type'] ?? ''), (string)($row['description'] ?? '')),
                    'backfilled',
                ]);
            }

            $this->setAppSetting($this->buildSettingKey('loyalty_backfill_done'), '1');
        } catch (\Throwable) {
            // Keep bootstrap resilient if loyalty tables are not ready yet.
        }
    }

    private function buildBackfillPreview(string $type, string $description): string
    {
        $prefix = match ($type) {
            'earn' => 'Backfill loyalty earn',
            'redeem' => 'Backfill loyalty redeem',
            'refund' => 'Backfill loyalty refund',
            default => 'Backfill loyalty event',
        };

        $description = trim($description);
        return $description !== '' ? $prefix . ': ' . $description : $prefix;
    }

    private function replacePlaceholders(string $template, array $customer, array $event, string $orderNumber): string
    {
        $customerName = trim((string)($customer['name'] ?? ''));
        $pointsDelta = (int)($event['points_delta'] ?? 0);
        $pointsAbs = abs($pointsDelta);

        $replacements = [
            '{customer_name}' => $customerName !== '' ? $customerName : 'Customer',
            '{order_number}' => $orderNumber !== '' ? $orderNumber : '-',
            '{points_delta}' => (string)$pointsDelta,
            '{points_abs}' => (string)$pointsAbs,
            '{balance_points}' => (string)(int)($event['balance_points'] ?? 0),
            '{lifetime_points}' => (string)(int)($event['lifetime_points'] ?? 0),
            '{description}' => (string)($event['description'] ?? ''),
        ];

        return strtr($template, $replacements);
    }

    private function sendWhatsApp(int $branchId, string $recipient, string $message): bool
    {
        $normalized = (new CustomerModel())->normalizeWhatsApp($recipient, $this->getSetting('default_country_code', '+62'));
        if ($normalized === '') {
            return false;
        }

        $provider = ProviderFactory::forBranchAny($branchId);
        if ($provider === null) {
            return false;
        }

        return $provider->sendMessage($normalized, $message);
    }

    private function sendEmail(string $recipient, string $subject, string $message): bool
    {
        if ($recipient === '') {
            return false;
        }

        $mailDriver = $this->getAppSetting('plugin_notifikasi_admin_mail_driver', 'smtp');
        if ($mailDriver === 'kirim_email') {
            require_once dirname(__DIR__) . '/notifikasi-admin/KirimEmailMailer.php';
            return KirimEmailMailer::send([
                'base_url' => $this->getAppSetting('plugin_notifikasi_admin_ke_base_url', 'https://smtp-app.kirim.email'),
                'domain' => $this->getAppSetting('plugin_notifikasi_admin_ke_domain', ''),
                'username' => $this->getAppSetting('plugin_notifikasi_admin_ke_username', ''),
                'token' => $this->getAppSetting('plugin_notifikasi_admin_ke_token', ''),
                'from_email' => $this->getAppSetting('plugin_notifikasi_admin_ke_from_email', ''),
                'from_name' => $this->getAppSetting('plugin_notifikasi_admin_ke_from_name', 'KopiBot'),
            ], $recipient, $subject, $message);
        }

        if ($mailDriver === 'smtp') {
            require_once dirname(__DIR__) . '/notifikasi-admin/SmtpMailer.php';
            return SmtpMailer::send([
                'smtp_host' => $this->getAppSetting('plugin_notifikasi_admin_smtp_host', ''),
                'smtp_port' => $this->getAppSetting('plugin_notifikasi_admin_smtp_port', '587'),
                'smtp_encryption' => $this->getAppSetting('plugin_notifikasi_admin_smtp_encryption', 'tls'),
                'smtp_user' => $this->getAppSetting('plugin_notifikasi_admin_smtp_user', ''),
                'smtp_pass' => $this->getAppSetting('plugin_notifikasi_admin_smtp_pass', ''),
                'smtp_from_email' => $this->getAppSetting('plugin_notifikasi_admin_smtp_from_email', ''),
                'smtp_from_name' => $this->getAppSetting('plugin_notifikasi_admin_smtp_from_name', 'KopiBot'),
            ], $recipient, $subject, $message);
        }

        return (bool) @mail($recipient, $subject, $message, 'From: noreply@tokokopi.com');
    }

    private function logNotification(
        int $branchId,
        int $customerId,
        int $orderId,
        string $channel,
        string $eventType,
        string $recipient,
        string $message,
        bool $sent
    ): void {
        if ($recipient === '') {
            return;
        }

        $hash = sha1(implode('|', [$branchId, $customerId, $orderId, $eventType, $recipient, trim($message)]));

        Database::getInstance()->prepare(
            'INSERT INTO crm_notification_logs
                (branch_id, customer_id, order_id, event_hash, channel, event_type, recipient, message_preview, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = status'
        )->execute([
            $branchId,
            $customerId,
            $orderId ?: null,
            $hash,
            $channel,
            $eventType,
            $recipient,
            mb_substr($message, 0, 1000, 'UTF-8'),
            $sent ? 'sent' : 'failed',
        ]);
    }

    private function getOrderNumber(int $orderId): string
    {
        if ($orderId <= 0) {
            return '';
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT order_number FROM orders WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$orderId]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (string)$value : '';
    }

    private function getSetting(string $key, string $default = ''): string
    {
        return $this->getAppSetting($this->buildSettingKey($key), $default);
    }

    private function getAppSetting(string $key, string $default = ''): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM app_settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null ? (string)$value : $default;
    }

    private function setAppSetting(string $key, string $value): void
    {
        Database::getInstance()->prepare(
            'INSERT INTO app_settings (setting_key, setting_val)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
        )->execute([$key, $value]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = Database::getInstance()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function buildSettingKey(string $key): string
    {
        return 'plugin_' . str_replace('-', '_', self::SLUG) . '_' . $key;
    }
}
