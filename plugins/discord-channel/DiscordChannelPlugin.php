<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Csrf;
use App\Plugin\{HookManager, PluginInterface};

class DiscordChannelPlugin implements PluginInterface
{
    private const PLUGIN_SLUG = 'discord-channel';

    public function getName(): string
    {
        return 'Discord Channel';
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
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 15);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 15);
    }

    public function registerChannel(array $channels): array
    {
        $channels['discord'] = new DiscordChannel();
        return $channels;
    }

    public function addBranchSettingsSection(array $sections, int $branchId): array
    {
        $sections['discord-channel'] = $this->renderSettingsCard($branchId, false);
        return $sections;
    }

    public function addSuperSettingsSection(array $sections, int $branchId): array
    {
        $sections['discord-channel'] = $this->renderSettingsCard($branchId, true);
        return $sections;
    }

    private function renderSettingsCard(int $branchId, bool $includeBranchField): string
    {
        $applicationId = $this->getSetting($branchId, 'bot_identifier');
        $botToken      = $this->getSetting($branchId, 'api_key');
        $publicKey     = $this->getSetting($branchId, 'api_secret');
        $isActive      = $this->getSetting($branchId, 'is_active') === '1';
        $sharedInbox   = $this->getSetting($branchId, 'discord_shared_inbox_enabled') === '1';
        $legacyUsed    = $this->isUsingLegacyFallback($branchId);
        $webhookUrl    = BASE_URL . '/api/channel/webhook.php?channel=discord';
        $branchWebhookUrl = $webhookUrl . '&branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Discord Channel Plugin</div>

          <?php if ($legacyUsed): ?>
          <div style="background:#fff7e6;border:1px solid #f2d19a;color:#8a5a12;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:.85rem;line-height:1.65">
            Plugin ini masih membaca konfigurasi Discord lama dari core.
            Simpan ulang form ini sekali supaya plugin memakai konfigurasi baru sebagai sumber utama.
          </div>
          <?php endif; ?>

          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
            <strong>Interactions Endpoint Utama</strong><br>
            <code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code><br><br>
            <strong>Interactions Endpoint per-cabang</strong><br>
            <code style="word-break:break-all"><?= htmlspecialchars($branchWebhookUrl) ?></code><br><br>
            Daftarkan URL ini di Discord Developer Portal → General Information → Interactions Endpoint URL.<br><br>
            Untuk bot per cabang, tiap cabang bisa memakai application Discord berbeda.
            Untuk satu bot semua cabang, cukup aktifkan satu konfigurasi bot host dan nyalakan shared inbox Discord.
          </div>

          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::PLUGIN_SLUG ?>">
            <?php if ($includeBranchField): ?>
            <input type="hidden" name="branch_id" value="<?= (int) $branchId ?>">
            <?php endif; ?>
            <input type="hidden" name="is_active" value="0">
            <input type="hidden" name="discord_shared_inbox_enabled" value="0">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="discord_application_id_<?= (int) $branchId ?>">Application ID</label>
                <input type="text" id="discord_application_id_<?= (int) $branchId ?>" name="bot_identifier" class="form-control"
                       value="<?= htmlspecialchars($applicationId) ?>"
                       placeholder="123456789012345678">
              </div>
              <div class="form-group">
                <label class="form-label" for="discord_public_key_<?= (int) $branchId ?>">Application Public Key</label>
                <input type="text" id="discord_public_key_<?= (int) $branchId ?>" name="api_secret" class="form-control"
                       value="<?= htmlspecialchars($publicKey) ?>"
                       placeholder="a1b2c3d4e5f6...">
                <small style="color:var(--text-mid);font-size:.8rem">Dipakai untuk verifikasi signature Discord.</small>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="discord_bot_token_<?= (int) $branchId ?>">Bot Token</label>
              <input type="password" id="discord_bot_token_<?= (int) $branchId ?>" name="api_key" class="form-control"
                     value="<?= htmlspecialchars($botToken) ?>"
                     placeholder="MTxxxxxxxxxxxxxxxx">
            </div>

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan channel Discord untuk branch ini</span>
              </label>
            </div>

            <div class="form-group">
              <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer">
                <input type="checkbox" name="discord_shared_inbox_enabled" value="1" <?= $sharedInbox ? 'checked' : '' ?> style="margin-top:3px">
                <span>
                  Pakai 1 bot Discord ini untuk semua cabang aktif<br>
                  <small style="color:var(--text-mid);font-size:.8rem">
                    Jika aktif, bot ini menjadi pintu masuk bersama dan user akan diminta memilih cabang di awal command.
                  </small>
                </span>
              </label>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan Discord</button>
          </form>
        </div>
        <?php

        return ob_get_clean();
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

        return $this->getLegacySetting($branchId, 'bot_identifier') !== ''
            || $this->getLegacySetting($branchId, 'api_key') !== ''
            || $this->getLegacySetting($branchId, 'api_secret') !== '';
    }

    private function getLegacySetting(int $branchId, string $key): string
    {
        $legacyFieldMap = [
            'bot_identifier' => 'bot_identifier',
            'api_key'        => 'api_key',
            'api_secret'     => 'api_secret',
            'is_active'      => 'is_active',
        ];
        $legacyColumn = $legacyFieldMap[$key] ?? null;
        if ($legacyColumn === null) {
            return '';
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT ' . $legacyColumn . '
             FROM branch_bot_settings
             WHERE branch_id = ? AND platform = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$branchId, 'discord']);

        return (string) ($stmt->fetchColumn() ?: '');
    }
}
