<?php

declare(strict_types=1);

use KopiBot\Contracts\PluginInterface;
use KopiBot\Core\HookManager;
use KopiBot\Security\Csrf;

final class AboutUsAiPlugin implements PluginInterface
{
    private AboutUsAiRepository $repo;
    private AboutUsAiService $service;

    public function __construct()
    {
        $this->repo = new AboutUsAiRepository();
        $this->service = new AboutUsAiService($this->repo);
    }

    public function getName(): string
    {
        return 'About Us AI';
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
        $this->repo->ensureSchema();

        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItems'], 16);
        HookManager::addFilter('settings.sections', [$this, 'addBranchSettingsSection'], 16);
        HookManager::addFilter('super.settings.sections', [$this, 'addSuperSettingsSection'], 16);
    }

    public function addNavItems(array $items, string $role): array
    {
        if ($role === 'super_admin') {
            $items['Content'][] = ['url' => '/dashboard/super/about-us-ai.php', 'icon' => 'AI', 'label' => 'About Us AI'];
        }
        if ($role === 'branch_admin') {
            $items['Content'][] = ['url' => '/dashboard/branch/about-us-ai.php', 'icon' => 'AI', 'label' => 'About Us AI'];
        }

        return $items;
    }

    public function addBranchSettingsSection(array $sections, int $branchId): array
    {
        $sections[self::slug()] = $this->renderSettingsCard($branchId, false);
        return $sections;
    }

    public function addSuperSettingsSection(array $sections, int $branchId): array
    {
        $sections[self::slug()] = $this->renderSettingsCard($branchId, true) . $this->renderGlobalRuntimeCard();
        return $sections;
    }

    public static function slug(): string
    {
        return AboutUsAiRepository::PLUGIN_SLUG;
    }

    private function renderGlobalRuntimeCard(): string
    {
        $provider = $this->repo->getGlobalSetting('provider', 'gemini');
        $model = $this->repo->getGlobalSetting('model', 'scaffold-template');

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">About Us AI Runtime</div>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_global_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::slug() ?>">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Default Provider</label>
                <select name="<?= self::slug() ?>_provider" class="form-control">
                  <option value="gemini" <?= $provider === 'gemini' ? 'selected' : '' ?>>Gemini</option>
                  <option value="openrouter" <?= $provider === 'openrouter' ? 'selected' : '' ?>>OpenRouter</option>
                  <option value="anthropic" <?= $provider === 'anthropic' ? 'selected' : '' ?>>Anthropic</option>
                  <option value="scaffold" <?= $provider === 'scaffold' ? 'selected' : '' ?>>Scaffold</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Default Model</label>
                <input type="text" name="<?= self::slug() ?>_model" class="form-control" value="<?= htmlspecialchars($model) ?>" placeholder="nama model AI">
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Runtime About Us AI</button>
          </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderSettingsCard(int $branchId, bool $includeBranchField): string
    {
        $isActive = $this->repo->getBranchSetting($branchId, 'is_active', '0') === '1';
        $brandName = $this->repo->getBranchSetting($branchId, 'brand_name');
        $businessType = $this->repo->getBranchSetting($branchId, 'business_type');
        $tone = $this->repo->getBranchSetting($branchId, 'tone', 'hangat dan terpercaya');
        $latest = $this->repo->findLatestByBranch($branchId);
        $logs = $this->repo->getRecentLogs($branchId, 5);

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">About Us AI</div>
          <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.84rem;line-height:1.7">
            Plugin ini membantu menyusun konten halaman <strong>Tentang Kami</strong> menggunakan draft manual atau generator AI scaffold.
          </div>

          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::slug() ?>">
            <?php if ($includeBranchField): ?>
            <input type="hidden" name="branch_id" value="<?= (int) $branchId ?>">
            <?php endif; ?>
            <input type="hidden" name="is_active" value="0">
            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <span>Aktifkan About Us AI untuk cabang ini</span>
              </label>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Brand Name</label>
                <input type="text" name="brand_name" class="form-control" value="<?= htmlspecialchars($brandName) ?>" placeholder="Nama bisnis / brand">
              </div>
              <div class="form-group">
                <label class="form-label">Business Type</label>
                <input type="text" name="business_type" class="form-control" value="<?= htmlspecialchars($businessType) ?>" placeholder="Coffee shop, bakery, apotek, dll">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Tone Konten</label>
              <input type="text" name="tone" class="form-control" value="<?= htmlspecialchars($tone) ?>" placeholder="hangat, profesional, premium, ramah keluarga">
            </div>
            <button type="submit" class="btn btn-primary">Simpan Profil About Us AI</button>
          </form>

          <div style="margin-top:16px">
            <form method="POST">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="generate_about_us_draft">
              <input type="hidden" name="plugin_slug" value="<?= self::slug() ?>">
              <?php if ($includeBranchField): ?>
              <input type="hidden" name="branch_id" value="<?= (int) $branchId ?>">
              <?php endif; ?>
              <div class="form-group">
                <label class="form-label">Prompt Tambahan</label>
                <textarea name="about_us_prompt" class="form-control" rows="4" placeholder="Ceritakan keunikan brand, target customer, visi, atau cerita berdirinya bisnis."></textarea>
              </div>
              <button type="submit" class="btn btn-secondary">Generate Draft About Us</button>
            </form>
          </div>

          <div style="margin-top:18px;background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px">
            <strong>Konten Terakhir</strong><br>
            <?php if ($latest !== null): ?>
              <div style="margin-top:8px"><strong><?= htmlspecialchars((string) ($latest['title'] ?? 'Tentang Kami')) ?></strong></div>
              <div style="font-size:.86rem;color:#6b7280;margin-top:4px">Status: <?= htmlspecialchars((string) ($latest['content_status'] ?? 'draft')) ?></div>
              <div style="margin-top:6px;line-height:1.6"><?= nl2br(htmlspecialchars((string) ($latest['short_description'] ?? ''))) ?></div>
            <?php else: ?>
              <div style="margin-top:8px">Belum ada konten About Us untuk cabang ini.</div>
            <?php endif; ?>
          </div>

          <div style="margin-top:14px">
            <strong>Log Generasi Terbaru</strong>
            <div style="overflow:auto;margin-top:8px">
              <table class="table" style="width:100%;font-size:.9rem">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Model</th>
                    <th>Dibuat</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($logs as $log): ?>
                  <tr>
                    <td><?= (int) ($log['id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($log['event_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($log['status'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($log['model'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($log['created_at'] ?? '')) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if ($logs === []): ?>
                  <tr><td colspan="5">Belum ada log generasi.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
