<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Csrf;
use App\Plugin\{HookManager, PluginInterface};

class BaileysWhatsAppPlugin implements PluginInterface
{
    private const PLUGIN_SLUG = 'baileys-whatsapp';

    public function getName(): string
    {
        return 'Baileys WhatsApp Bridge';
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
        HookManager::addFilter('settings.sections', [$this, 'addSettingsSection'], 18);
        HookManager::addAction('settings.saved', [$this, 'cleanupLegacySetting'], 18);
    }

    public function registerChannel(array $channels): array
    {
        $channels['whatsapp_baileys'] = new BaileysWhatsAppChannel();
        return $channels;
    }

    public function addSettingsSection(array $sections, int $branchId): array
    {
        $waNumber    = $this->getSetting($branchId, 'wa_number');
        $bridgeToken = $this->getSetting($branchId, 'bridge_token');
        $secretKey   = $this->getSetting($branchId, 'secret_key');
        $outboundUrl = $this->getSetting($branchId, 'outbound_url');
        $legacyUsed  = $this->isUsingLegacyFallback($branchId);
        $webhookUrl  = BASE_URL . '/api/channel/webhook.php?channel=whatsapp_baileys&branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Baileys WhatsApp Bridge</div>

          <?php if ($legacyUsed): ?>
          <div style="background:#fff7e6;border:1px solid #f2d19a;color:#8a5a12;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:.85rem;line-height:1.65">
            Konfigurasi ini masih membaca data Baileys lama dari menu WhatsApp legacy.
            Simpan ulang form ini sekali agar setting tersimpan penuh di sistem plugin.
          </div>
          <?php endif; ?>

          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
            <strong>Webhook URL Baileys</strong><br>
            <code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code><br><br>
            Arahkan service Node.js Baileys untuk mengirim inbound message ke URL ini.
            Balasan bot akan diteruskan kembali ke bridge melalui Outbound URL di bawah.
          </div>

          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::PLUGIN_SLUG ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="baileys_wa_number">Nomor WhatsApp Bot / Device</label>
                <input type="text" id="baileys_wa_number" name="wa_number" class="form-control"
                       value="<?= htmlspecialchars($waNumber) ?>"
                       placeholder="6281234567890">
              </div>
              <div class="form-group">
                <label class="form-label" for="baileys_bridge_token">Bridge Token</label>
                <input type="text" id="baileys_bridge_token" name="bridge_token" class="form-control"
                       value="<?= htmlspecialchars($bridgeToken) ?>"
                       placeholder="Token untuk outbound ke service bridge">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="baileys_secret_key">Inbound Secret Key</label>
                <input type="password" id="baileys_secret_key" name="secret_key" class="form-control"
                       value="<?= htmlspecialchars($secretKey) ?>"
                       placeholder="Opsional, cocokkan dengan header X-Baileys-Token / X-Bridge-Token">
              </div>
              <div class="form-group">
                <label class="form-label" for="baileys_outbound_url">Outbound URL</label>
                <input type="url" id="baileys_outbound_url" name="outbound_url" class="form-control"
                       value="<?= htmlspecialchars($outboundUrl) ?>"
                       placeholder="https://bridge.example.com/send-message">
              </div>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan Baileys</button>
          </form>
        </div>
        <?php

        $sections['baileys-whatsapp'] = ob_get_clean();
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
            || $this->getLegacySetting($branchId, 'bridge_token') !== ''
            || $this->getLegacySetting($branchId, 'secret_key') !== ''
            || $this->getLegacySetting($branchId, 'outbound_url') !== '';
    }

    private function getLegacySetting(int $branchId, string $key): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT bws.* FROM branch_whatsapp_settings bws
             JOIN whatsapp_providers wp ON bws.provider_id = wp.id
             WHERE bws.branch_id = ? AND wp.adapter_class = ? AND bws.is_active = 1
             ORDER BY bws.id DESC
             LIMIT 1'
        );
        $stmt->execute([$branchId, 'BaileysBridgeProvider']);
        $row = $stmt->fetch();

        if (!$row) {
            return '';
        }

        if ($key === 'wa_number') {
            return (string) ($row['wa_number'] ?? '');
        }
        if ($key === 'bridge_token') {
            return (string) ($row['api_key'] ?? '');
        }
        if ($key === 'secret_key') {
            return (string) ($row['api_secret'] ?? '');
        }
        if ($key === 'outbound_url') {
            $extra = json_decode((string) ($row['extra_config'] ?? ''), true);
            return is_array($extra) ? (string) ($extra['outbound_url'] ?? '') : '';
        }

        return '';
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
        $stmt->execute([$branchId, 'BaileysBridgeProvider']);
    }
}
