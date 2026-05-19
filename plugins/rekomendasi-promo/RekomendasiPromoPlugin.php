<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Models\PromoModel;
use App\Helpers\{Currency, Csrf};
use App\Config\Database;

class RekomendasiPromoPlugin implements PluginInterface
{
    private const SLUG = 'rekomendasi-promo';

    public function getName(): string    { return 'Rekomendasi Promo'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'KopiBot'; }

    public function register(): void
    {
        HookManager::addFilter('chat.after_ai',     [$this, 'appendPromoHint'], 20);
        HookManager::addFilter('settings.sections', [$this, 'tambahForm'],      20);
    }

    // ── Filter: chat.after_ai ───────────────────────────────────

    public function appendPromoHint(string $reply, int $branchId, string $intent, array $ctx = []): string
    {
        if ($intent !== 'tambah_item' || empty($ctx['cart_items']) || !$this->isEnabled($branchId)) {
            return $reply;
        }

        $promos = (new PromoModel())->getActiveForBranch($branchId, $ctx['now_local'] ?? '');
        $lang   = $ctx['language'] ?? 'id';
        $hints  = $this->buildHints(
            $promos,
            (float)($ctx['cart_total'] ?? 0),
            $lang,
            $ctx['currency'] ?? 'IDR',
            (int)($this->getSetting($branchId, 'max_hints') ?? 2),
            ($this->getSetting($branchId, 'show_code_promo') ?? '1') === '1',
            ($this->getSetting($branchId, 'show_auto_promo') ?? '1') === '1'
        );

        if (empty($hints)) {
            return $reply;
        }

        $header = $lang === 'id' ? "\n\n💡 *Info Promo:*" : "\n\n💡 *Promo Info:*";
        return $reply . $header . "\n" . implode("\n", array_map(fn($h) => "• {$h}", $hints));
    }

    // ── Filter: settings.sections ───────────────────────────────

    public function tambahForm(array $sections, int $branchId): array
    {
        $enabled       = ($this->getSetting($branchId, 'enabled')         ?? '1') === '1';
        $maxHints      = (int)($this->getSetting($branchId, 'max_hints')  ?? 2);
        $showCodePromo = ($this->getSetting($branchId, 'show_code_promo') ?? '1') === '1';
        $showAutoPromo = ($this->getSetting($branchId, 'show_auto_promo') ?? '1') === '1';

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">💡 Rekomendasi Promo</div>
          <p style="font-size:.875rem;color:var(--text-light);margin-bottom:14px">
            Menampilkan info promo aktif saat customer menambah item ke keranjang.
          </p>
          <form method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action"      value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="<?= self::SLUG ?>">

            <div class="form-group">
              <label class="form-label" for="rp_enabled">Status</label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" id="rp_enabled" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                Aktifkan rekomendasi promo untuk cabang ini
              </label>
            </div>

            <div class="form-group">
              <label class="form-label" for="rp_max_hints">Maks Hint Ditampilkan</label>
              <select id="rp_max_hints" name="max_hints" class="form-control" style="max-width:120px">
                <option value="1" <?= $maxHints === 1 ? 'selected' : '' ?>>1</option>
                <option value="2" <?= $maxHints === 2 ? 'selected' : '' ?>>2</option>
              </select>
            </div>

            <fieldset style="border:none;padding:0;margin:0" class="form-group">
              <legend class="form-label">Jenis Promo yang Ditampilkan</legend>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:6px">
                <input type="checkbox" name="show_code_promo" value="1" <?= $showCodePromo ? 'checked' : '' ?>>
                Promo dengan kode (contoh: JUMAT15)
              </label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="show_auto_promo" value="1" <?= $showAutoPromo ? 'checked' : '' ?>>
                Promo otomatis (tanpa kode, berdasarkan min. order)
              </label>
            </fieldset>

            <button type="submit" class="btn btn-primary">💾 Simpan</button>
          </form>
        </div>
        <?php
        $sections[self::SLUG] = ob_get_clean();
        return $sections;
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function buildHints(
        array  $promos,
        float  $cartTotal,
        string $lang,
        string $currency,
        int    $maxHints,
        bool   $showCodePromo,
        bool   $showAutoPromo
    ): array {
        $hints = [];
        foreach ($promos as $promo) {
            if (count($hints) >= $maxHints) break;
            $hint = $this->buildHintForPromo($promo, $cartTotal, $lang, $currency, $showCodePromo, $showAutoPromo);
            if ($hint !== null) {
                $hints[] = $hint;
            }
        }
        return $hints;
    }

    private function buildHintForPromo(
        array  $promo,
        float  $cartTotal,
        string $lang,
        string $currency,
        bool   $showCodePromo,
        bool   $showAutoPromo
    ): ?string {
        $minOrder     = (float)($promo['min_order'] ?? 0);
        $promoCode    = trim((string)($promo['promo_code'] ?? ''));
        $discount     = (float)($promo['discount_value'] ?? 0);
        $type         = $promo['discount_type'] ?? 'percent';
        $discountText = $type === 'percent'
            ? "{$discount}%"
            : Currency::format($discount, $currency);

        if ($promoCode !== '' && $showCodePromo) {
            return $this->buildCodePromoHint($promoCode, $discountText, $minOrder, $cartTotal, $lang, $currency);
        }

        if ($promoCode === '' && $showAutoPromo && $minOrder > 0 && $cartTotal < $minOrder) {
            $gap = Currency::format($minOrder - $cartTotal, $currency);
            return $lang === 'id'
                ? "Tambah {$gap} lagi untuk dapat diskon {$discountText} otomatis!"
                : "Add {$gap} more for automatic {$discountText} off!";
        }

        return null;
    }

    private function buildCodePromoHint(
        string $promoCode,
        string $discountText,
        float  $minOrder,
        float  $cartTotal,
        string $lang,
        string $currency
    ): string {
        if ($minOrder <= 0 || $cartTotal >= $minOrder) {
            return $lang === 'id'
                ? "Ketik *pakai {$promoCode}* untuk diskon {$discountText}!"
                : "Type *use {$promoCode}* to get {$discountText} off!";
        }

        $gap = Currency::format($minOrder - $cartTotal, $currency);
        return $lang === 'id'
            ? "Tambah {$gap} lagi untuk bisa pakai kode *{$promoCode}* (diskon {$discountText})!"
            : "Add {$gap} more to unlock code *{$promoCode}* ({$discountText} off)!";
    }

    private function isEnabled(int $branchId): bool
    {
        return ($this->getSetting($branchId, 'enabled') ?? '1') === '1';
    }

    private function getSetting(int $branchId, string $key): ?string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute([self::SLUG, $branchId, $key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['setting_val'] : null;
    }
}
