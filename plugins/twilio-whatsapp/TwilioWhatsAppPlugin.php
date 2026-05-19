<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Csrf;
use App\Plugin\{HookManager, PluginInterface};

class TwilioWhatsAppPlugin implements PluginInterface
{
    private const PLUGIN_SLUG = 'twilio-whatsapp';

    public function getName(): string
    {
        return 'Twilio WhatsApp Gateway';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'Toko Kopi';
    }

    public function register(): void
    {
        HookManager::addFilter('channel.registered', [$this, 'registerChannel']);
        HookManager::addFilter('settings.sections', [$this, 'addSettingsSection'], 17);
        HookManager::addAction('settings.saved', [$this, 'cleanupLegacySetting'], 17);
    }

    public function registerChannel(array $channels): array
    {
        $channels['whatsapp_twilio'] = new TwilioWhatsAppChannel();
        return $channels;
    }

    public function addSettingsSection(array $sections, int $branchId): array
    {
        $waNumber   = $this->getSetting($branchId, 'wa_number');
        $accountSid = $this->getSetting($branchId, 'api_key');
        $authToken  = $this->getSetting($branchId, 'api_secret');
        $legacyUsed = $this->isUsingLegacyFallback($branchId);
        $webhookUrl = BASE_URL . '/api/channel/webhook.php?channel=whatsapp_twilio&branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Twilio WhatsApp Gateway</div>

          <?php if ($legacyUsed): ?>
          <div style="background:#fff7e6;border:1px solid #f2d19a;color:#8a5a12;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:.85rem;line-height:1.65">
            Konfigurasi ini masih membaca data Twilio lama dari menu WhatsApp legacy.
            Simpan ulang form ini sekali agar setting tersimpan penuh di sistem plugin.
          </div>
          <?php endif; ?>

          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
            <strong>Webhook URL Twilio</strong><br>
            <code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code><br><br>
            Tempel URL ini sebagai inbound webhook WhatsApp di console Twilio branch ini.
            Channel Twilio sekarang berjalan lewat sistem plugin, bukan core WhatsApp legacy.
          </div>

          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::PLUGIN_SLUG ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="twilio_wa_number">Nomor / Sender WhatsApp</label>
                <input type="text" id="twilio_wa_number" name="wa_number" class="form-control"
                       value="<?= htmlspecialchars($waNumber) ?>"
                       placeholder="whatsapp:+14155238886 atau +14155238886">
              </div>
              <div class="form-group">
                <label class="form-label" for="twilio_api_key">Account SID</label>
                <input type="text" id="twilio_api_key" name="api_key" class="form-control"
                       value="<?= htmlspecialchars($accountSid) ?>"
                       placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="twilio_api_secret">Auth Token</label>
              <input type="password" id="twilio_api_secret" name="api_secret" class="form-control"
                     value="<?= htmlspecialchars($authToken) ?>"
                     placeholder="Twilio Auth Token">
              <small style="color:var(--text-mid);font-size:.8rem">Dipakai untuk outbound API dan verifikasi header <code>X-Twilio-Signature</code>.</small>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan Twilio</button>
          </form>
        </div>
        <?php

        $sections['twilio-whatsapp'] = ob_get_clean();
        return $sections;
    }

    private function getSetting(int $branchId, string $key): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $pluginValue = $stmt->fetchColumn();
        if ($pluginValue !== false && $pluginValue !== null && $pluginValue !== '') {
            return (string) $pluginValue;
        }

        return $this->getLegacySetting($branchId, $key);
    }

    private function isUsingLegacyFallback(int $branchId): bool
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*) FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ?'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId]);
        $pluginCount = (int) $stmt->fetchColumn();

        if ($pluginCount > 0) {
            return false;
        }

        return $this->getLegacySetting($branchId, 'wa_number') !== ''
            || $this->getLegacySetting($branchId, 'api_key') !== ''
            || $this->getLegacySetting($branchId, 'api_secret') !== '';
    }

    private function getLegacySetting(int $branchId, string $key): string
    {
        $legacyFieldMap = [
            'wa_number'  => 'wa_number',
            'api_key'    => 'api_key',
            'api_secret' => 'api_secret',
        ];
        $legacyColumn = $legacyFieldMap[$key] ?? null;
        if ($legacyColumn === null) {
            return '';
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT bws.' . $legacyColumn . '
             FROM branch_whatsapp_settings bws
             JOIN whatsapp_providers wp ON bws.provider_id = wp.id
             WHERE bws.branch_id = ? AND wp.adapter_class = ? AND bws.is_active = 1
             ORDER BY bws.id DESC
             LIMIT 1'
        );
        $stmt->execute([$branchId, 'TwilioProvider']);

        return (string) ($stmt->fetchColumn() ?: '');
    }

    public function cleanupLegacySetting(int $branchId, array $payload = []): void
    {
        if (($payload['action'] ?? '') !== 'save_plugin_settings') {
            return;
        }

        if (($payload['plugin_slug'] ?? '') !== self::PLUGIN_SLUG) {
            return;
        }

        $stmt = Database::getInstance()->prepare(
            'DELETE bws FROM branch_whatsapp_settings bws
             JOIN whatsapp_providers wp ON bws.provider_id = wp.id
             WHERE bws.branch_id = ? AND wp.adapter_class = ?'
        );
        $stmt->execute([$branchId, 'TwilioProvider']);
    }
}
