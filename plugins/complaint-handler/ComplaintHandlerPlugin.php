<?php

declare(strict_types=1);

use App\Config\Database;
use App\Helpers\Csrf;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;
use App\Services\IntentPatternRegistry;
use App\Skills\SkillRegistry;

final class ComplaintHandlerPlugin implements PluginInterface
{
    private const SLUG = 'complaint-handler';

    public function getName(): string
    {
        return 'Complaint Handler';
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
        (new ComplaintTicketRepository())->ensureSchema();

        HookManager::addFilter('skills.registered', [$this, 'registerSkill'], 16);
        HookManager::addFilter('intent.patterns', [$this, 'registerIntentPatterns'], 16);
        HookManager::addFilter('dashboard.nav_items', [$this, 'addNavItems'], 16);
        HookManager::addFilter('dashboard.topbar_actions', [$this, 'addTopbarBadge'], 16);
        HookManager::addFilter('settings.sections', [$this, 'addSettingsSection'], 16);
    }

    public function registerSkill(array $skills): array
    {
        return SkillRegistry::register($skills, new ComplaintSkill(), 15);
    }

    public function registerIntentPatterns(array $patterns): array
    {
        return IntentPatternRegistry::extend($patterns, 'komplain_customer', [
            'komplain', 'keluhan', 'complaint', 'complain', 'kecewa', 'marah', 'kesal',
            'kok gini', 'ga sesuai', 'gak sesuai', 'tidak sesuai', 'salah menu',
            'salah minuman', 'pesanan saya salah', 'terlalu lama', 'lama banget',
            'dingin', 'tumpah', 'refund', 'uang kembali', 'double charge',
            'belum datang', 'kurir', 'driver', 'admin manusia', 'cs manusia',
            'human admin', 'human agent',
        ]);
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
            'url' => '/dashboard/branch/complaints.php',
            'icon' => 'CP',
            'label' => 'Tiket Komplain',
        ];

        return $navItems;
    }

    public function addTopbarBadge(string $html, int $branchId, string $role): string
    {
        if ($role !== 'branch_admin' || $branchId <= 0) {
            return $html;
        }

        $summary = (new ComplaintTicketRepository())->getSummaryByBranch($branchId);
        $openHuman = (int)($summary['human_open'] ?? 0);
        $urgentOpen = (int)($summary['urgent_open'] ?? 0);

        $badge = '<div style="display:flex;align-items:center;gap:8px;margin-right:16px">'
            . '<a href="' . BASE_URL . '/dashboard/branch/complaints.php" class="btn btn-sm btn-outline">'
            . 'Komplain: ' . $openHuman . ' open'
            . ($urgentOpen > 0 ? ' | Urgent: ' . $urgentOpen : '')
            . '</a></div>';

        return $html . $badge;
    }

    public function addSettingsSection(array $sections, int $branchId): array
    {
        $autoReplyHuman = htmlspecialchars($this->getSetting($branchId, 'human_label', 'admin manusia'));
        $note = htmlspecialchars($this->getSetting($branchId, 'note', 'Komplain pembayaran, refund, permintaan admin, dan komplain berulang otomatis dibuatkan tiket human follow-up.'));

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">Complaint Handler</div>
          <p style="font-size:.875rem;color:var(--text-light);margin-bottom:14px">
            Plugin ini mendeteksi komplain pada flow chat order, lalu memutuskan apakah cukup dijawab AI atau perlu follow-up manusia.
          </p>

          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::SLUG ?>">

            <div class="form-row">
              <div class="form-group" style="max-width:240px">
                <label class="form-label" for="complaint_human_label">Keyword eskalasi manual</label>
                <input type="text" id="complaint_human_label" name="human_label" class="form-control" value="<?= $autoReplyHuman ?>">
                <small style="color:var(--text-light)">Customer bisa mengetik keyword ini untuk minta staf manusia.</small>
              </div>
            </div>

            <div class="form-group" style="max-width:760px">
              <label class="form-label" for="complaint_note">Catatan Internal</label>
              <textarea id="complaint_note" name="note" class="form-control" rows="3"><?= $note ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan Complaint Handler</button>
          </form>
        </div>
        <?php

        $sections[self::SLUG] = ob_get_clean();
        return $sections;
    }

    private function getSetting(int $branchId, string $key, string $default = ''): string
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT setting_val
                 FROM plugin_branch_settings
                 WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ?
                 LIMIT 1'
            );
            $stmt->execute([self::SLUG, $branchId, $key]);
            $value = $stmt->fetchColumn();

            return $value !== false && $value !== null ? (string)$value : $default;
        } catch (Throwable) {
            return $default;
        }
    }
}
