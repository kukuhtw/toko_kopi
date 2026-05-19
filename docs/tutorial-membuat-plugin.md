# Tutorial: Membuat Plugin untuk KopiBot

> ## ☕ AI Agent Coffee Shop Commerce Platform
> Platform AI untuk otomatisasi order, customer service, loyalty customer, dan manajemen multi cabang coffee shop.
>
> Dibuat & Dikembangkan oleh: **Kukuh TW**
>
> 📧 Email: `kukuhtw@gmail.com`
> 📱 WhatsApp: `https://wa.me/628129893706`
> 📷 Instagram: `@kukuhtw`
> 🐦 X/Twitter: `@kukuhtw`
> 👍 Facebook: `https://www.facebook.com/kukuhtw`
> 💼 LinkedIn: `https://linkedin.com/in/kukuhtw`
> 🌐 Demo: `https://botlelang.com/toko_kopi`
>
> © 2026 Kukuh TW. All rights reserved.

Panduan ini memandu kamu membangun **dua plugin nyata** dari nol, lengkap dengan penjelasan mengapa setiap baris kode ditulis seperti itu.

Setelah selesai, kamu akan paham:
- Perbedaan **action** dan **filter**
- Cara menyimpan dan membaca pengaturan per cabang
- Cara menampilkan form pengaturan di dashboard

Referensi lengkap hook dan interface tersedia di [`plugin-system.md`](plugin-system.md).
Penjelasan model lisensi untuk plugin, modifikasi core, dan deployment proprietary tersedia di [`lisensi.md`](lisensi.md).

---

## Prasyarat

- PHP 8.0+, familiar dengan class dan namespace
- Akses ke folder `toko_kopi/plugins/`
- KopiBot berjalan di localhost

---

## Plugin 1 — Notifikasi Email Order Baru

**Tujuan:** setiap kali ada order masuk, kirim email ke admin cabang.

Ini adalah plugin paling sederhana — hanya satu action hook, satu pengaturan, dan tidak ada dependency eksternal.

### Langkah 1 — Buat folder plugin

```
plugins/notifikasi-admin/
├── plugin.php
└── NotifikasiAdminPlugin.php
```

Nama folder wajib **kebab-case** dan unik di seluruh instalasi.

---

### Langkah 2 — Tulis class plugin

Buat file `plugins/notifikasi-admin/NotifikasiAdminPlugin.php`:

```php
<?php

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class NotifikasiAdminPlugin implements PluginInterface
{
    public function getName(): string    { return 'Notifikasi Admin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'Nama Developer'; }

    public function register(): void
    {
        // Daftarkan callback ke action 'order.created'
        // Priority 10 (default) — cukup untuk plugin ini
        HookManager::addAction('order.created', [$this, 'kirimEmail']);
    }

    public function kirimEmail(array $order): void
    {
        $branchId   = (int)($order['branch_id'] ?? 0);
        $emailTujuan = $this->getSetting($branchId, 'email_admin');

        // Plugin tidak dikonfigurasi untuk cabang ini → lewati
        if (!$emailTujuan) {
            return;
        }

        $orderNum  = $order['order_number'] ?? '-';
        $customer  = $order['customer_name'] ?? '-';
        $total     = number_format((float)($order['total_amount'] ?? 0), 0, ',', '.');
        $channel   = strtoupper((string)($order['channel'] ?? ''));

        $subject = "[KopiBot] Order Baru: {$orderNum}";
        $body    = "Order baru masuk!\n\n"
                 . "No. Order : {$orderNum}\n"
                 . "Customer  : {$customer}\n"
                 . "Total     : Rp {$total}\n"
                 . "Channel   : {$channel}\n"
                 . "Waktu     : " . date('d/m/Y H:i') . "\n";

        // mail() menggunakan SMTP yang dikonfigurasi di php.ini
        mail($emailTujuan, $subject, $body, 'From: noreply@tokokopi.com');
    }

    // ── Helper: baca pengaturan dari plugin_branch_settings ──

    private function getSetting(int $branchId, string $key): ?string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute(['notifikasi-admin', $branchId, $key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['setting_val'] : null;
    }
}
```

> **Kenapa `getSetting` tidak di-cache?**
> Plugin dipanggil sekali per request. Cache hanya perlu jika kamu memanggil `getSetting` berkali-kali dalam satu request dengan key yang sama — untuk kasus ini tidak perlu.

---

### Langkah 3 — Tulis entry point

Buat file `plugins/notifikasi-admin/plugin.php`:

```php
<?php

require_once __DIR__ . '/NotifikasiAdminPlugin.php';

return [
    'class'       => NotifikasiAdminPlugin::class,
    'name'        => 'Notifikasi Admin',
    'version'     => '1.0.0',
    'author'      => 'Nama Developer',
    'description' => 'Kirim email ke admin cabang setiap ada order baru.',
    'requires'    => '1.0.0',
];
```

File ini wajib me-`return` array dengan key di atas. `PluginLoader` membaca array ini untuk validasi versi dan menampilkan metadata di dashboard.

---

### Langkah 4 — Aktifkan plugin

Edit `plugins/plugins.json`:

```json
{
    "notifikasi-admin": { "active": true }
}
```

Atau aktifkan lewat **Dashboard → Plugins**.

---

### Langkah 5 — Konfigurasi per cabang (opsional tapi direkomendasikan)

Agar email bisa dikonfigurasi per cabang dari dashboard — tanpa harus edit kode — tambahkan filter `settings.sections` ke `register()`:

```php
public function register(): void
{
    HookManager::addAction('order.created', [$this, 'kirimEmail']);
    HookManager::addFilter('settings.sections', [$this, 'tambahForm'], 10);
}

public function tambahForm(array $sections, int $branchId): array
{
    $emailSaved = htmlspecialchars((string)($this->getSetting($branchId, 'email_admin') ?? ''));

    ob_start();
    ?>
    <div class="card" style="margin-top:16px">
      <div class="card-title">📧 Notifikasi Admin</div>
      <form method="POST">
        <?= \App\Helpers\Csrf::field() ?>
        <input type="hidden" name="action"      value="save_plugin_settings">
        <input type="hidden" name="plugin_slug" value="notifikasi-admin">

        <div class="form-group" style="max-width:400px">
          <label class="form-label" for="na_email">Email Tujuan Notifikasi</label>
          <input type="email" id="na_email" name="email_admin" class="form-control"
                 value="<?= $emailSaved ?>" placeholder="admin@tokokamu.com">
          <small style="color:var(--text-light)">
            Kosongkan untuk menonaktifkan notifikasi di cabang ini.
          </small>
        </div>

        <button type="submit" class="btn btn-primary">💾 Simpan</button>
      </form>
    </div>
    <?php
    $sections['notifikasi-admin'] = ob_get_clean();
    return $sections;
}
```

> **Pola penting:** field name di form menggunakan key yang akan disimpan ke database (`email_admin`). Form menggunakan `action=save_plugin_settings` dan `plugin_slug=notifikasi-admin` — settings page menangani penyimpanannya secara otomatis.

Setelah ini, form akan muncul di **Branch Admin → Settings** di bagian bawah.

---

### Uji Plugin 1

1. Aktifkan plugin di dashboard
2. Buka Settings cabang → isi email admin
3. Simulasi chat → lakukan order sampai checkout
4. Cek inbox email

> Di localhost, `mail()` butuh mail server. Gunakan **Mailtrap** (gratis) atau konfigurasi SMTP di `php.ini` dengan `sendmail_path`.

---

---

## Plugin 2 — Biaya Layanan

**Tujuan:** tambahkan biaya layanan flat (misal Rp 2.000) ke setiap order. Nominal bisa diatur per cabang dari dashboard.

Plugin ini mendemonstrasikan **filter hook** — cara memodifikasi nilai yang dihitung oleh kode inti.

### Cara Kerja Filter

```
cart.total dipanggil:
  HookManager::applyFilters('cart.total', $total, $cartItems, $cart)
                                           ↑
                                  nilai awal dari kode inti

Plugin menerima:
  function tambahBiaya(float $total, array $items, array $cart): float
                              ↑
                     nilai dari filter sebelumnya (bisa dirantai)

Plugin mengembalikan:
  return $total + 2000;
          ↑
  nilai ini diteruskan ke filter berikutnya / hasil akhir
```

Jika ada 3 plugin yang hook ke `cart.total`, mereka dieksekusi berurutan dan nilainya dirantai.

---

### Struktur File

```
plugins/biaya-layanan/
├── plugin.php
└── BiayaLayananPlugin.php
```

---

### Class Plugin

`plugins/biaya-layanan/BiayaLayananPlugin.php`:

```php
<?php

use App\Plugin\{PluginInterface, HookManager};
use App\Config\Database;

class BiayaLayananPlugin implements PluginInterface
{
    public function getName(): string    { return 'Biaya Layanan'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'Nama Developer'; }

    public function register(): void
    {
        // Filter cart.total — priority 20 agar berjalan setelah diskon promo (priority 10)
        HookManager::addFilter('cart.total', [$this, 'tambahBiaya'], 20);

        // Tambah form di settings cabang
        HookManager::addFilter('settings.sections', [$this, 'tambahForm'], 10);

        // Tambahkan keterangan biaya ke balasan chatbot
        HookManager::addFilter('chat.after_ai', [$this, 'sisipkanKeterangan'], 10);
    }

    // ── Filter: cart.total ─────────────────────────────────────

    /**
     * $total  : total harga setelah promo/diskon lain
     * $items  : array item keranjang
     * $cart   : data cart (branch_id, dll.)
     */
    public function tambahBiaya(float $total, array $items, array $cart): float
    {
        $branchId = (int)($cart['branch_id'] ?? 0);
        $nominal  = (float)($this->getSetting($branchId, 'nominal') ?? 0);

        if ($nominal <= 0 || empty($items)) {
            return $total; // tidak aktif atau cart kosong
        }

        return $total + $nominal;
    }

    // ── Filter: chat.after_ai ───────────────────────────────────

    /**
     * Sisipkan keterangan biaya layanan ke balasan chatbot
     * hanya ketika membalas pertanyaan tentang cart/total.
     */
    public function sisipkanKeterangan(string $reply, int $branchId, string $intent): string
    {
        // Hanya tambahkan keterangan pada intent yang relevan
        $relevantIntents = ['lihat_cart', 'tambah_item', 'checkout'];
        if (!in_array($intent, $relevantIntents, true)) {
            return $reply;
        }

        $nominal = (float)($this->getSetting($branchId, 'nominal') ?? 0);
        $label   = (string)($this->getSetting($branchId, 'label')   ?? 'Biaya Layanan');

        if ($nominal <= 0) {
            return $reply;
        }

        $formatted = 'Rp ' . number_format($nominal, 0, ',', '.');
        return $reply . "\n_(+{$formatted} {$label})_";
    }

    // ── Filter: settings.sections ──────────────────────────────

    public function tambahForm(array $sections, int $branchId): array
    {
        $nominal = htmlspecialchars((string)($this->getSetting($branchId, 'nominal') ?? ''));
        $label   = htmlspecialchars((string)($this->getSetting($branchId, 'label')   ?? 'Biaya Layanan'));

        ob_start();
        ?>
        <div class="card" style="margin-top:16px">
          <div class="card-title">💰 Biaya Layanan</div>
          <form method="POST">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="action"      value="save_plugin_settings">
            <input type="hidden" name="plugin_slug" value="biaya-layanan">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="bl_label">Nama Biaya</label>
                <input type="text" id="bl_label" name="label" class="form-control"
                       value="<?= $label ?>" placeholder="Biaya Layanan">
              </div>
              <div class="form-group" style="max-width:200px">
                <label class="form-label" for="bl_nominal">Nominal (Rp)</label>
                <input type="number" id="bl_nominal" name="nominal" class="form-control"
                       min="0" step="500" value="<?= $nominal ?>" placeholder="2000">
                <small style="color:var(--text-light)">0 = nonaktif</small>
              </div>
            </div>

            <button type="submit" class="btn btn-primary">💾 Simpan</button>
          </form>
        </div>
        <?php
        $sections['biaya-layanan'] = ob_get_clean();
        return $sections;
    }

    // ── Helper ─────────────────────────────────────────────────

    private function getSetting(int $branchId, string $key): ?string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_val FROM plugin_branch_settings
             WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute(['biaya-layanan', $branchId, $key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['setting_val'] : null;
    }
}
```

---

### Entry Point

`plugins/biaya-layanan/plugin.php`:

```php
<?php

require_once __DIR__ . '/BiayaLayananPlugin.php';

return [
    'class'       => BiayaLayananPlugin::class,
    'name'        => 'Biaya Layanan',
    'version'     => '1.0.0',
    'author'      => 'Nama Developer',
    'description' => 'Tambahkan biaya layanan flat ke setiap order. Nominal dapat diatur per cabang.',
    'requires'    => '1.0.0',
];
```

---

### Aktifkan & Konfigurasi

```json
// plugins/plugins.json
{
    "biaya-layanan": { "active": true }
}
```

Kemudian buka **Branch Admin → Settings** → isi Nama Biaya dan Nominal.

---

### Uji Plugin 2

1. Set nominal, misal `2000`
2. Chat: `"pesan 2 latte"` → lihat balasan bot, ada keterangan `(+Rp 2.000 Biaya Layanan)`
3. Chat: `"checkout"` → total order sudah termasuk biaya layanan
4. Cek di dashboard: total order = harga menu + biaya layanan

---

---

## Pola Umum yang Perlu Diingat

### Menyimpan & Membaca Pengaturan Per Cabang

```php
// Baca
$stmt = Database::getInstance()->prepare(
    'SELECT setting_val FROM plugin_branch_settings
     WHERE plugin_slug = ? AND branch_id = ? AND setting_key = ? LIMIT 1'
);
$stmt->execute(['slug-plugin-kamu', $branchId, 'nama_key']);
$value = $stmt->fetch()['setting_val'] ?? null;

// Simpan (form plugin menggunakan action=save_plugin_settings — disimpan otomatis)
// Atau simpan manual:
Database::getInstance()->prepare(
    'INSERT INTO plugin_branch_settings (plugin_slug, branch_id, setting_key, setting_val)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)'
)->execute(['slug-plugin-kamu', $branchId, 'nama_key', $value]);
```

---

### Form Pengaturan di Dashboard

Aturan field form untuk disimpan otomatis oleh settings page:

```html
<form method="POST">
    [CSRF field]
    <input type="hidden" name="action"      value="save_plugin_settings">
    <input type="hidden" name="plugin_slug" value="slug-plugin-kamu">

    <!-- field di bawah ini → disimpan ke plugin_branch_settings secara otomatis -->
    <input type="text" name="nama_key" value="...">
    <input type="number" name="nominal" value="...">

    <!-- checkbox: selalu sertakan hidden '0' sebelum checkbox -->
    <input type="hidden"   name="aktif" value="0">
    <input type="checkbox" name="aktif" value="1">

    <button type="submit">Simpan</button>
</form>
```

---

### Priority Hook

```php
// Priority lebih rendah = dieksekusi lebih awal
HookManager::addFilter('cart.total', [$this, 'diskon'],     10); // promo dulu
HookManager::addFilter('cart.total', [$this, 'biayaAdmin'], 20); // biaya setelah diskon
```

Default priority adalah `10`. Gunakan `< 10` untuk berjalan sebelum plugin lain, `> 10` untuk sesudah.

---

### Meneruskan Data Antar Hook dalam Satu Request

Jika dua hook perlu berbagi data (contoh: URL pembayaran dari `order.created` ke `chat.after_ai`), gunakan static property:

```php
class ContohPlugin implements PluginInterface
{
    private static string $pendingUrl = '';

    public function onOrderCreated(array $order): void
    {
        self::$pendingUrl = 'https://bayar.example.com/...';
    }

    public function onAfterAi(string $reply, int $branchId, string $intent): string
    {
        if (self::$pendingUrl === '') { return $reply; }
        $url = self::$pendingUrl;
        self::$pendingUrl = ''; // konsumsi sekali pakai
        return $reply . "\n\nBayar: " . $url;
    }
}
```

Ini aman karena satu PHP request = satu siklus plugin lifecycle.

---

## Troubleshooting

| Gejala | Kemungkinan Penyebab |
|--------|---------------------|
| Plugin tidak muncul di dashboard | `plugin.php` tidak return array dengan key yang benar |
| Hook tidak terpanggil | Lupa `HookManager::addAction/addFilter` di `register()` |
| Pengaturan tidak tersimpan | Nama field form berbeda dengan yang dibaca di `getSetting()` |
| Error saat aktivasi | Cek `storage/logs/php_error.log` |
| `getSetting()` selalu null | Tabel `plugin_branch_settings` belum ada — jalankan `database/schema.sql` |

---

## Langkah Selanjutnya

- **Plugin channel baru** (LINE, Slack): lihat contoh di [`plugin-system.md`](plugin-system.md#menambah-channel-baru-via-plugin)
- **Plugin payment gateway**: lihat `plugins/midtrans-payment/` sebagai referensi lengkap
- **Semua hook tersedia**: lihat tabel di [`plugin-system.md`](plugin-system.md#hook-reference)
