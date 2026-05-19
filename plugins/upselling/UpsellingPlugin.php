<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Models\MenuModel;
use App\Helpers\Currency;
use App\Config\Database;

class UpsellingPlugin implements PluginInterface
{
    private const PLUGIN_SLUG = 'upselling';

    public function getName(): string    { return 'Upselling'; }
    public function getVersion(): string { return '1.1.0'; }
    public function getAuthor(): string  { return 'KopiBot'; }

    public function register(): void
    {
        HookManager::addFilter('chat.after_ai', [$this, 'appendUpsell'], 30);
        HookManager::addFilter('settings.sections', [$this, 'addSettingsSection'], 10);
    }

    public function appendUpsell(string $reply, int $branchId, string $intent, array $ctx = []): string
    {
        if ($intent !== 'tambah_item' || empty($ctx['cart_items'])) {
            return $reply;
        }

        if ($this->getSetting($branchId, 'enabled', '1') === '0') {
            return $reply;
        }

        $lang      = (string)($ctx['language'] ?? 'id');
        $currency  = (string)($ctx['currency'] ?? 'IDR');
        $cartItems = array_values((array)$ctx['cart_items']);

        $cartCategoryIds = array_values(array_unique(array_map(
            static fn(array $item): int => (int)($item['category_id'] ?? 0),
            $cartItems
        )));
        $cartMenuItemIds = array_values(array_unique(array_map(
            static fn(array $item): int => (int)($item['menu_item_id'] ?? 0),
            $cartItems
        )));

        $latestItem         = $cartItems[count($cartItems) - 1] ?? [];
        $latestCategoryId   = (int)($latestItem['category_id'] ?? 0);
        $latestCategoryName = (string)($latestItem['category_name'] ?? '');

        $menuModel          = new MenuModel();
        $targetCategoryIds  = $this->resolveTargetCategoryIds($branchId, $latestCategoryId, $latestCategoryName, $menuModel);
        $allowFallback      = $this->getSetting($branchId, 'allow_random_fallback', '1') !== '0';
        $suggestion         = false;

        if (!empty($targetCategoryIds)) {
            $targetCategoryIds = array_values(array_diff($targetCategoryIds, $cartCategoryIds));
            if (!empty($targetCategoryIds)) {
                $suggestion = $menuModel->getRandomItemInCategories($targetCategoryIds, $branchId, $cartMenuItemIds);
            }
        }

        if (!$suggestion && $allowFallback) {
            $suggestion = $menuModel->getRandomItemNotInCategories($cartCategoryIds, $branchId);
        }

        if (!$suggestion) {
            return $reply;
        }

        $price = Currency::format((float)($suggestion['effective_price'] ?? 0), $currency);
        $name  = (string)($suggestion['name'] ?? '');
        if ($name === '') {
            return $reply;
        }

        $hint = $lang === 'id'
            ? "\n\n☕ *Cocok juga dengan:* {$name} — {$price}\nKetik _pesan 1 {$name}_ untuk tambah."
            : "\n\n☕ *Goes well with:* {$name} — {$price}\nType _order 1 {$name}_ to add.";

        return $reply . $hint;
    }

    public function addSettingsSection(array $sections, int $branchId): array
    {
        $enabled       = $this->getSetting($branchId, 'enabled', '1') !== '0';
        $allowFallback = $this->getSetting($branchId, 'allow_random_fallback', '1') !== '0';
        $pairRules     = $this->getSetting($branchId, 'pair_rules', '');
        $categories    = (new MenuModel())->getCategories();

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">🛍️ Upselling Cerdas</div>

          <form method="POST">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="action" value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::PLUGIN_SLUG ?>">

            <div class="form-group">
              <input type="hidden" name="enabled" value="0">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                <span>Aktifkan rekomendasi upselling setelah customer menambah item</span>
              </label>
            </div>

            <div class="form-group">
              <input type="hidden" name="allow_random_fallback" value="0">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="allow_random_fallback" value="1" <?= $allowFallback ? 'checked' : '' ?>>
                <span>Jika tidak ada pairing rule yang cocok, fallback ke rekomendasi acak lintas kategori</span>
              </label>
            </div>

            <div class="form-group">
              <label class="form-label" for="upsell_pair_rules">Aturan Pairing Kategori</label>
              <textarea id="upsell_pair_rules" name="pair_rules" class="form-control" rows="7"
                        placeholder="Format: kategori_sumber_id:kategori_target_id,kategori_target_id&#10;Contoh:&#10;1:4,5&#10;2:3"><?= htmlspecialchars($pairRules) ?></textarea>
              <small style="color:var(--text-light)">
                Satu baris per kategori sumber. Contoh <code>1:4,5</code> berarti jika item terakhir dari kategori 1, sarankan item dari kategori 4 atau 5.
              </small>
            </div>

            <div style="background:var(--bg-light,#faf9f7);border-radius:8px;padding:12px;margin-bottom:14px;font-size:.82rem;line-height:1.7">
              <strong>Daftar Kategori Aktif</strong><br>
              <?php foreach ($categories as $category): ?>
                <div><code><?= (int)$category['id'] ?></code> — <?= htmlspecialchars((string)$category['name']) ?></div>
              <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan Upselling</button>
          </form>
        </div>
        <?php

        $sections['upselling'] = ob_get_clean();
        return $sections;
    }

    private function resolveTargetCategoryIds(int $branchId, int $sourceCategoryId, string $sourceCategoryName, MenuModel $menuModel): array
    {
        if ($sourceCategoryId <= 0) {
            return [];
        }

        $rules = $this->parsePairRules($this->getSetting($branchId, 'pair_rules', ''));
        if (!empty($rules[$sourceCategoryId])) {
            return $rules[$sourceCategoryId];
        }

        $categoryMap = [];
        foreach ($menuModel->getCategories() as $category) {
            $categoryMap[(int)$category['id']] = (string)($category['name'] ?? '');
        }

        return $this->inferDefaultPairs($sourceCategoryName, $categoryMap);
    }

    private function parsePairRules(string $raw): array
    {
        $result = [];
        $lines = preg_split('/\R+/', trim($raw)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$source, $targets] = array_map('trim', explode(':', $line, 2));
            $sourceId = (int)$source;
            if ($sourceId <= 0) {
                continue;
            }

            $targetIds = array_values(array_unique(array_filter(array_map(
                static fn(string $id): int => (int)trim($id),
                explode(',', $targets)
            ))));

            if (!empty($targetIds)) {
                $result[$sourceId] = $targetIds;
            }
        }

        return $result;
    }

    private function inferDefaultPairs(string $sourceCategoryName, array $categoryMap): array
    {
        $source = mb_strtolower($sourceCategoryName, 'UTF-8');
        $keywords = [];

        if ($this->containsAny($source, ['kopi', 'coffee', 'espresso', 'latte', 'cappuccino'])) {
            $keywords = ['cemilan', 'snack', 'pastry', 'dessert', 'roti'];
        } elseif ($this->containsAny($source, ['cemilan', 'snack', 'pastry', 'roti', 'dessert', 'makanan'])) {
            $keywords = ['kopi', 'coffee', 'non-kopi', 'tea', 'minuman'];
        } elseif ($this->containsAny($source, ['non-kopi', 'tea', 'teh', 'minuman'])) {
            $keywords = ['cemilan', 'snack', 'pastry', 'dessert'];
        }

        if (empty($keywords)) {
            return [];
        }

        $matches = [];
        foreach ($categoryMap as $categoryId => $categoryName) {
            $name = mb_strtolower($categoryName, 'UTF-8');
            if ($this->containsAny($name, $keywords)) {
                $matches[] = (int)$categoryId;
            }
        }

        return array_values(array_unique($matches));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (mb_strpos($haystack, $needle, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }

    private function getSetting(int $branchId, string $key, string $default = ''): string
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT setting_val FROM plugin_branch_settings
                 WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
            );
            $stmt->execute([self::PLUGIN_SLUG, $branchId, $key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? (string)$value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
