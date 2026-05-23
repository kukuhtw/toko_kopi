<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Csrf;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;

final class RichChatUiPlugin implements PluginInterface
{
    private const PLUGIN_SLUG = 'rich-chat-ui';

    public function getName(): string { return 'Rich Chat UI'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'Toko Kopi'; }

    public function register(): void
    {
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 22);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 22);
        HookManager::addFilter('webchat.page_config', [$this, 'extendWebChatConfig'], 22);
    }

    public function addBranchSettingsSection(array $sections, int $branchId): array
    {
        $sections[self::PLUGIN_SLUG] = $this->renderSettingsCard($branchId, false);
        return $sections;
    }

    public function addSuperSettingsSection(array $sections, int $branchId): array
    {
        $sections[self::PLUGIN_SLUG] = $this->renderSettingsCard($branchId, true);
        return $sections;
    }

    public function extendWebChatConfig(array $config, int $branchId, array $branch = []): array
    {
        if ($branchId <= 0) {
            return $config;
        }

        $isActive = $this->getSetting($branchId, 'is_active', '1') === '1';
        if (!$isActive) {
            return $config;
        }

        $assistantName = $this->getSetting($branchId, 'assistant_name', 'Kopi Bot');
        $assistantStatus = $this->getSetting($branchId, 'assistant_status', 'Online');
        $brandIcon = $this->getSetting($branchId, 'brand_icon', '🤖');
        $quickActionsRaw = $this->getSetting($branchId, 'quick_actions_json', '');
        $quickActions = json_decode($quickActionsRaw, true);
        if (!is_array($quickActions) || $quickActions === []) {
            $quickActions = [
                ['label' => 'Menu', 'value' => 'menu'],
                ['label' => 'Promo', 'value' => 'promo hari ini'],
                ['label' => 'Rekomendasi', 'value' => 'kasih rekomendasi menu favorit'],
                ['label' => 'Checkout', 'value' => 'checkout'],
            ];
        }

        $config['enabled'] = true;
        $config['theme'] = 'rich-showcase';
        $config['assistant_name'] = $assistantName;
        $config['assistant_status'] = $assistantStatus;
        $config['brand_icon'] = $brandIcon;
        $config['quick_actions'] = $quickActions;
        $config['welcome_prompt'] = $this->getSetting($branchId, 'welcome_prompt', (string)($config['welcome_prompt'] ?? ''));

        return $config;
    }

    private function renderSettingsCard(int $branchId, bool $includeBranchField): string
    {
        $isActive = $this->getSetting($branchId, 'is_active', '1') === '1';
        $assistantName = $this->getSetting($branchId, 'assistant_name', 'Kopi Bot');
        $assistantStatus = $this->getSetting($branchId, 'assistant_status', 'Online');
        $brandIcon = $this->getSetting($branchId, 'brand_icon', '🤖');
        $welcomePrompt = $this->getSetting($branchId, 'welcome_prompt', 'Ketik menu, promo, rekomendasi, atau checkout.');
        $quickActionsRaw = $this->getSetting($branchId, 'quick_actions_json', '');
        if ($quickActionsRaw === '') {
            $quickActionsRaw = json_encode([
                ['label' => 'Menu', 'value' => 'menu'],
                ['label' => 'Promo', 'value' => 'promo hari ini'],
                ['label' => 'Rekomendasi', 'value' => 'kasih rekomendasi menu favorit'],
                ['label' => 'Checkout', 'value' => 'checkout'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Rich Chat UI</div>
          <div style="background:linear-gradient(135deg,#082136,#12354b);border-radius:16px;padding:14px 16px;margin-bottom:14px;color:#e9f9ff">
            <div style="font-size:1rem;font-weight:700;margin-bottom:4px">Tampilan chat web ala messenger premium</div>
            <div style="font-size:.84rem;line-height:1.7;color:rgba(233,249,255,.82)">
              Plugin ini mengaktifkan tema chat yang lebih kaya: header glossy, quick reply chips, dan kartu produk otomatis saat bot menyebut menu yang relevan.
            </div>
          </div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::PLUGIN_SLUG ?>">
            <?php if ($includeBranchField): ?>
            <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
            <?php endif; ?>
            <input type="hidden" name="is_active" value="0">

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan rich UI untuk chat website cabang ini</span>
              </label>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Nama Asisten</label>
                <input type="text" name="assistant_name" class="form-control" value="<?= htmlspecialchars($assistantName) ?>" placeholder="Kopi Bot">
              </div>
              <div class="form-group">
                <label class="form-label">Status Asisten</label>
                <input type="text" name="assistant_status" class="form-control" value="<?= htmlspecialchars($assistantStatus) ?>" placeholder="Online">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Ikon Asisten</label>
                <input type="text" name="brand_icon" class="form-control" value="<?= htmlspecialchars($brandIcon) ?>" placeholder="🤖">
              </div>
              <div class="form-group">
                <label class="form-label">Welcome Prompt</label>
                <input type="text" name="welcome_prompt" class="form-control" value="<?= htmlspecialchars($welcomePrompt) ?>" placeholder="Ketik menu, promo, rekomendasi, atau checkout.">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Quick Actions JSON</label>
              <textarea name="quick_actions_json" class="form-control" rows="6" style="font-family:Consolas,monospace"><?= htmlspecialchars($quickActionsRaw) ?></textarea>
              <small style="color:var(--text-light)">Format: [{"label":"Menu","value":"menu"}]</small>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Rich Chat UI</button>
          </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function getSetting(int $branchId, string $key, string $default = ''): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ?
             LIMIT 1'
        );
        $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : (string)$value;
    }
}
