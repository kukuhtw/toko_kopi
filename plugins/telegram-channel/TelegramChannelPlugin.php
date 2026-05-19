<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Csrf;
use App\Plugin\{HookManager, PluginInterface};

class TelegramChannelPlugin implements PluginInterface
{
    private const PLUGIN_SLUG = 'telegram-channel';

    public function getName(): string
    {
        return 'Telegram Channel';
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
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 14);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 14);
    }

    public function registerChannel(array $channels): array
    {
        $channels['telegram'] = new TelegramChannel();
        return $channels;
    }

    public function addBranchSettingsSection(array $sections, int $branchId): array
    {
        $sections['telegram-channel'] = $this->renderSettingsCard($branchId, false);
        return $sections;
    }

    public function addSuperSettingsSection(array $sections, int $branchId): array
    {
        $sections['telegram-channel'] = $this->renderSettingsCard($branchId, true);
        return $sections;
    }

    private function renderSettingsCard(int $branchId, bool $includeBranchField): string
    {
        $botUsername = $this->getSetting($branchId, 'bot_username');
        $botToken    = $this->getSetting($branchId, 'api_key');
        $secretToken = $this->getSetting($branchId, 'webhook_token');
        $isActive    = $this->getSetting($branchId, 'is_active') === '1';
        $sharedInbox = $this->getSetting($branchId, 'telegram_shared_inbox_enabled') === '1';
        $legacyUsed  = $this->isUsingLegacyFallback($branchId);
        $webhookUrl  = BASE_URL . '/api/channel/webhook.php?channel=telegram';
        $branchWebhookUrl = $webhookUrl . '&branch=' . $branchId;
        $setWebhookUrl = 'https://api.telegram.org/bot'
            . htmlspecialchars($botToken !== '' ? $botToken : 'TOKEN', ENT_QUOTES, 'UTF-8')
            . '/setWebhook';

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Telegram Channel Plugin</div>

          <?php if ($legacyUsed): ?>
          <div style="background:#fff7e6;border:1px solid #f2d19a;color:#8a5a12;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:.85rem;line-height:1.65">
            Plugin ini masih membaca konfigurasi Telegram lama dari core.
            Simpan ulang form ini sekali supaya plugin memakai konfigurasi baru sebagai sumber utama.
          </div>
          <?php endif; ?>

          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
            <strong>Webhook URL Telegram Utama</strong><br>
            <code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code><br><br>
            <strong>Webhook URL Telegram per-cabang</strong><br>
            <code style="word-break:break-all"><?= htmlspecialchars($branchWebhookUrl) ?></code><br><br>
            <strong>Set webhook via Telegram Bot API</strong><br>
            <code style="word-break:break-all"><?= $setWebhookUrl ?></code><br><br>
            Body JSON contoh:<br>
            <code>{"url":"<?= htmlspecialchars($webhookUrl) ?>","secret_token":"<?= htmlspecialchars($secretToken !== '' ? $secretToken : 'isi-secret-unik') ?>"}</code><br><br>
            Secret token wajib unik per konfigurasi.
            Untuk bot per cabang, pakai token bot berbeda di tiap cabang.
            Untuk satu bot semua cabang, cukup pakai satu konfigurasi bot sebagai host dan aktifkan shared inbox.
          </div>

          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::PLUGIN_SLUG ?>">
            <?php if ($includeBranchField): ?>
            <input type="hidden" name="branch_id" value="<?= (int) $branchId ?>">
            <?php endif; ?>
            <input type="hidden" name="is_active" value="0">
            <input type="hidden" name="telegram_shared_inbox_enabled" value="0">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="telegram_bot_username_<?= (int) $branchId ?>">Bot Username</label>
                <input type="text" id="telegram_bot_username_<?= (int) $branchId ?>" name="bot_username" class="form-control"
                       value="<?= htmlspecialchars($botUsername) ?>"
                       placeholder="@nama_bot_kamu">
              </div>
              <div class="form-group">
                <label class="form-label" for="telegram_secret_token_<?= (int) $branchId ?>">Secret Token</label>
                <input type="text" id="telegram_secret_token_<?= (int) $branchId ?>" name="webhook_token" class="form-control"
                       value="<?= htmlspecialchars($secretToken) ?>"
                       placeholder="string-rahasia-unik">
                <small style="color:var(--text-mid);font-size:.8rem">Dikirim Telegram lewat header <code>X-Telegram-Bot-Api-Secret-Token</code>.</small>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="telegram_bot_token_<?= (int) $branchId ?>">Bot Token</label>
              <input type="password" id="telegram_bot_token_<?= (int) $branchId ?>" name="api_key" class="form-control"
                     value="<?= htmlspecialchars($botToken) ?>"
                     placeholder="123456:AA...">
            </div>

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan channel Telegram untuk branch ini</span>
              </label>
            </div>

            <div class="form-group">
              <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer">
                <input type="checkbox" name="telegram_shared_inbox_enabled" value="1" <?= $sharedInbox ? 'checked' : '' ?> style="margin-top:3px">
                <span>
                  Pakai 1 bot Telegram ini untuk semua cabang aktif<br>
                  <small style="color:var(--text-mid);font-size:.8rem">
                    Jika aktif, bot ini menjadi pintu masuk bersama dan customer akan diminta memilih cabang di awal chat.
                  </small>
                </span>
              </label>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan Telegram</button>
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

        return $this->getLegacySetting($branchId, 'bot_username') !== ''
            || $this->getLegacySetting($branchId, 'api_key') !== ''
            || $this->getLegacySetting($branchId, 'webhook_token') !== '';
    }

    private function getLegacySetting(int $branchId, string $key): string
    {
        $legacyFieldMap = [
            'bot_username'  => 'bot_identifier',
            'api_key'       => 'api_key',
            'webhook_token' => 'webhook_token',
            'is_active'     => 'is_active',
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
        $stmt->execute([$branchId, 'telegram']);

        return (string) ($stmt->fetchColumn() ?: '');
    }
}
