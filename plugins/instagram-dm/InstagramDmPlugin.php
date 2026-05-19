<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class InstagramDmPlugin implements PluginInterface
{
    private const PLUGIN_SLUG = 'instagram-dm';

    public function getName(): string    { return 'Instagram DM Chatbot'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'Toko Kopi'; }

    public function register(): void
    {
        HookManager::addFilter('channel.registered', [$this, 'registerChannel']);
        HookManager::addFilter('settings.sections', [$this, 'addSettingsSection'], 10);
    }

    public function registerChannel(array $channels): array
    {
        $channels['instagram'] = new InstagramDmChannel();
        return $channels;
    }

    public function addSettingsSection(array $sections, int $branchId): array
    {
        $accessToken = $this->getSetting($branchId, 'access_token');
        $appSecret   = $this->getSetting($branchId, 'app_secret');
        $verifyToken = $this->getSetting($branchId, 'verify_token');
        $businessId  = $this->getSetting($branchId, 'instagram_business_id');
        $graphVer    = $this->getSetting($branchId, 'graph_version') ?: 'v20.0';
        $webhookUrl  = BASE_URL . '/api/channel/webhook.php?channel=instagram&branch=' . $branchId;

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">📷 Instagram DM Chatbot</div>

          <form method="POST">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::PLUGIN_SLUG ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="ig_business_id">Instagram Business ID</label>
                <input type="text" id="ig_business_id" name="instagram_business_id" class="form-control"
                       value="<?= htmlspecialchars($businessId) ?>"
                       placeholder="1784xxxxxxxxxxxx">
              </div>
              <div class="form-group">
                <label class="form-label" for="ig_graph_version">Graph API Version</label>
                <input type="text" id="ig_graph_version" name="graph_version" class="form-control"
                       value="<?= htmlspecialchars($graphVer) ?>"
                       placeholder="v20.0">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="ig_access_token">Access Token</label>
              <input type="password" id="ig_access_token" name="access_token" class="form-control"
                     value="<?= htmlspecialchars($accessToken) ?>"
                     placeholder="EAAG...">
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="ig_app_secret">App Secret</label>
                <input type="password" id="ig_app_secret" name="app_secret" class="form-control"
                       value="<?= htmlspecialchars($appSecret) ?>"
                       placeholder="Meta app secret">
              </div>
              <div class="form-group">
                <label class="form-label" for="ig_verify_token">Verify Token</label>
                <input type="text" id="ig_verify_token" name="verify_token" class="form-control"
                       value="<?= htmlspecialchars($verifyToken) ?>"
                       placeholder="bebas-isi-token-verifikasi">
              </div>
            </div>

            <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
              <strong>Webhook URL</strong><br>
              <code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code><br><br>
              <strong>Setup Meta</strong><br>
              1. Hubungkan akun Instagram Business ke Facebook Page.<br>
              2. Aktifkan permission messaging Instagram di aplikasi Meta kamu.<br>
              3. Pakai URL di atas sebagai callback webhook dan isi verify token yang sama persis.<br>
              4. Subscribe event pesan Instagram DM di dashboard Meta.
            </div>

            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan Instagram</button>
          </form>
        </div>
        <?php

        $sections['instagram-dm'] = ob_get_clean();
        return $sections;
    }

    private function getSetting(int $branchId, string $key): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        return (string)($stmt->fetchColumn() ?: '');
    }
}
