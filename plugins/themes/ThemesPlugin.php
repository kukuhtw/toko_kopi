<?php

declare(strict_types=1);

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

/**
 * Plugin Themes & Branding
 *
 * Cara kerja:
 *  1. dashboard.head_styles → inject <style> block yang override CSS vars palette
 *  2. dashboard.brand_html  → override teks logo di sidebar (nama + emoji + tagline)
 *  3. dashboard.app_name    → override nama app di <title> tag
 *  4. dashboard.nav_items   → tambah menu "Tema & Branding" di sidebar super admin
 *
 * Settings disimpan ke tabel app_settings dengan prefix key "theme_".
 */
class ThemesPlugin implements PluginInterface
{
    // ── Preset Palettes ──────────────────────────────────────────

    public const PRESETS = [
        'coffee' => [
            'label'   => 'Coffee',
            'emoji'   => '☕',
            'swatches'=> ['#2C1A0E', '#6F4E37', '#D4A574', '#F5E6D3'],
            'vars'    => [
                '--coffee-dark'   => '#2C1A0E',
                '--coffee-brown'  => '#6F4E37',
                '--coffee-medium' => '#A0724B',
                '--coffee-light'  => '#D4A574',
                '--coffee-cream'  => '#F5E6D3',
                '--coffee-white'  => '#FDFAF7',
                '--border'        => '#E0D5C9',
                '--shadow'        => 'rgba(111,78,55,.12)',
            ],
        ],
        'matcha' => [
            'label'   => 'Matcha',
            'emoji'   => '🍵',
            'swatches'=> ['#1B3A2A', '#3D7A5A', '#9EC8B2', '#E4F0EA'],
            'vars'    => [
                '--coffee-dark'   => '#1B3A2A',
                '--coffee-brown'  => '#3D7A5A',
                '--coffee-medium' => '#5E9E7A',
                '--coffee-light'  => '#9EC8B2',
                '--coffee-cream'  => '#E4F0EA',
                '--coffee-white'  => '#F4FAF6',
                '--border'        => '#BFDDCC',
                '--shadow'        => 'rgba(61,122,90,.12)',
            ],
        ],
        'midnight' => [
            'label'   => 'Midnight',
            'emoji'   => '🌙',
            'swatches'=> ['#111827', '#4B6EC5', '#A0B8F0', '#E8ECF8'],
            'vars'    => [
                '--coffee-dark'   => '#111827',
                '--coffee-brown'  => '#4B6EC5',
                '--coffee-medium' => '#6B8DE0',
                '--coffee-light'  => '#A0B8F0',
                '--coffee-cream'  => '#E8ECF8',
                '--coffee-white'  => '#F4F6FD',
                '--border'        => '#CBD4EE',
                '--shadow'        => 'rgba(75,110,197,.12)',
            ],
        ],
        'sakura' => [
            'label'   => 'Sakura',
            'emoji'   => '🌸',
            'swatches'=> ['#2E1421', '#C05878', '#EBB0C2', '#FAF0F4'],
            'vars'    => [
                '--coffee-dark'   => '#2E1421',
                '--coffee-brown'  => '#C05878',
                '--coffee-medium' => '#D87898',
                '--coffee-light'  => '#EBB0C2',
                '--coffee-cream'  => '#FAF0F4',
                '--coffee-white'  => '#FDF7F9',
                '--border'        => '#EDD0D9',
                '--shadow'        => 'rgba(192,88,120,.12)',
            ],
        ],
        'ocean' => [
            'label'   => 'Ocean',
            'emoji'   => '🌊',
            'swatches'=> ['#0D2233', '#2285A8', '#88CADF', '#E3F3FA'],
            'vars'    => [
                '--coffee-dark'   => '#0D2233',
                '--coffee-brown'  => '#2285A8',
                '--coffee-medium' => '#3FA6C8',
                '--coffee-light'  => '#88CADF',
                '--coffee-cream'  => '#E3F3FA',
                '--coffee-white'  => '#F2F9FD',
                '--border'        => '#B3D9EC',
                '--shadow'        => 'rgba(34,133,168,.12)',
            ],
        ],
    ];

    // Map key "border" ke CSS var yang benar (tidak pakai prefix --coffee-)
    private const CUSTOM_VAR_MAP = [
        'dark'   => '--coffee-dark',
        'brown'  => '--coffee-brown',
        'medium' => '--coffee-medium',
        'light'  => '--coffee-light',
        'cream'  => '--coffee-cream',
        'white'  => '--coffee-white',
        'border' => '--border',
    ];

    public function getName(): string    { return 'Themes & Branding'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'KopiBot Team'; }

    public function register(): void
    {
        HookManager::addFilter('dashboard.head_styles', [$this, 'injectStyles'],    5);
        HookManager::addFilter('dashboard.brand_html',  [$this, 'overrideBrand'],   5);
        HookManager::addFilter('dashboard.app_name',    [$this, 'overrideAppName'], 5);
        HookManager::addFilter('dashboard.nav_items',   [$this, 'addNavItem'],      5);
    }

    // ── Filter: dashboard.head_styles ────────────────────────────

    public function injectStyles(string $html): string
    {
        $preset = $this->getSetting('preset', 'coffee');
        $vars   = [];

        if ($preset === 'custom') {
            foreach (self::CUSTOM_VAR_MAP as $key => $cssVar) {
                $val = $this->getSetting('custom_' . $key, '');
                if (preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
                    $vars[$cssVar] = $val;
                }
            }
        } elseif (isset(self::PRESETS[$preset])) {
            $vars = self::PRESETS[$preset]['vars'];
        }

        // Build :root block
        $lines = [':root {'];
        foreach ($vars as $prop => $val) {
            // Only allow valid CSS var names and safe color values
            $prop = preg_replace('/[^a-z\-]/', '', $prop);
            $val  = preg_replace('/[^a-zA-Z0-9#.,() %\/]/', '', $val);
            $lines[] = "  {$prop}: {$val};";
        }
        $lines[] = '}';

        // Sidebar tagline style — injected here so it's available globally
        $lines[] = '.sidebar-tagline{'
                 . 'font-size:.68rem;color:rgba(255,255,255,.45);'
                 . 'margin-top:3px;font-weight:400;letter-spacing:.3px;'
                 . 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'
                 . 'max-width:196px;}';

        return $html . "\n<style>\n" . implode("\n", $lines) . "\n</style>";
    }

    // ── Filter: dashboard.brand_html ─────────────────────────────

    public function overrideBrand(string $default): string
    {
        $emoji   = trim($this->getSetting('brand_emoji', ''));
        $name    = trim($this->getSetting('app_name', ''));
        $tagline = trim($this->getSetting('tagline', ''));

        // Nothing configured — keep default, just maybe add tagline
        if ($emoji === '' && $name === '') {
            if ($tagline === '') {
                return $default;
            }
            return $default . '<div class="sidebar-tagline">' . htmlspecialchars($tagline) . '</div>';
        }

        // Build custom brand line
        $emojiHtml = $emoji !== '' ? htmlspecialchars($emoji) . ' ' : '';

        if ($name !== '') {
            // Highlight last word with <span> (matches original style pattern)
            $words = explode(' ', $name);
            $last  = array_pop($words);
            $nameHtml = !empty($words)
                ? htmlspecialchars(implode(' ', $words)) . ' <span>' . htmlspecialchars($last) . '</span>'
                : '<span>' . htmlspecialchars($last) . '</span>';
        } else {
            $nameHtml = '';
        }

        $out = $emojiHtml . $nameHtml;
        if ($tagline !== '') {
            $out .= '<div class="sidebar-tagline">' . htmlspecialchars($tagline) . '</div>';
        }

        return $out;
    }

    // ── Filter: dashboard.app_name ───────────────────────────────

    public function overrideAppName(string $name): string
    {
        $configured = trim($this->getSetting('app_name', ''));
        return $configured !== '' ? $configured : $name;
    }

    // ── Filter: dashboard.nav_items ──────────────────────────────

    public function addNavItem(array $items, string $role): array
    {
        if ($role !== 'super_admin') {
            return $items;
        }

        if (isset($items['Settings'])) {
            $items['Settings'][] = [
                'url'   => '/dashboard/super/themes.php',
                'icon'  => '🎨',
                'label' => 'Tema & Branding',
            ];
        }

        return $items;
    }

    // ── Public Helpers (dipakai juga oleh themes.php) ────────────

    public function getSetting(string $key, string $default = ''): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM app_settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->execute(['theme_' . $key]);
        $row = $stmt->fetch();
        return ($row && $row['setting_val'] !== null) ? (string)$row['setting_val'] : $default;
    }

    public function saveSetting(string $key, string $value): void
    {
        Database::getInstance()->prepare(
            'INSERT INTO app_settings (setting_key, setting_val)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
        )->execute(['theme_' . $key, $value]);
    }

    public static function getCustomVarMap(): array
    {
        return self::CUSTOM_VAR_MAP;
    }
}
