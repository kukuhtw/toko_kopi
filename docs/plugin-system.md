# Plugin System — KopiBot AI

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

## Daftar Isi
1. [Gambaran Umum](#gambaran-umum)
2. [Struktur Direktori](#struktur-direktori)
3. [Cara Membuat Plugin](#cara-membuat-plugin)
4. [Hook Reference](#hook-reference)
5. [Interface Reference](#interface-reference)
6. [Database Schema](#database-schema)
7. [Roadmap Implementasi](#roadmap-implementasi)

---

## Gambaran Umum

KopiBot AI mendukung sistem plugin yang memungkinkan developer menambahkan fitur baru tanpa mengubah kode inti. Plugin bekerja melalui dua mekanisme:

- **Action** — bereaksi terhadap event (order dibuat, pesan masuk, dll.)
- **Filter** — memodifikasi nilai yang dilewatkan (total harga, response bot, daftar nav, dll.)

### Catatan Lisensi Plugin

KopiBot AI memakai model **AGPL + commercial license**.

- Jika kamu memodifikasi **core** untuk mendukung plugin, perubahan pada core mengikuti lisensi utama proyek.
- Plugin yang berdiri sebagai modul terpisah lewat hook / extension point publik bisa memiliki pertimbangan lisensi sendiri, tetapi konteks bundling dan distribusinya tetap penting.
- Untuk plugin proprietary yang dibundel dalam deployment tertutup bersama core, lihat juga [`lisensi.md`](lisensi.md).

```
Request → Kode Inti → HookManager::doAction/applyFilters → Plugin Callbacks → Response
```

---

## Struktur Direktori

```
toko_kopi/
├── app/
│   └── Plugin/
│       ├── HookManager.php          ← engine action/filter
│       ├── PluginLoader.php         ← auto-discovery & bootstrap
│       ├── PluginInterface.php      ← kontrak wajib tiap plugin
│       ├── LlmProviderInterface.php ← kontrak LLM provider custom
│       └── ChannelInterface.php     ← kontrak channel baru
├── plugins/
│   ├── plugins.json                 ← daftar plugin aktif
│   └── {slug-plugin}/
│       ├── plugin.php               ← entry point (wajib)
│       ├── {ClassName}.php          ← implementasi plugin
│       └── assets/                  ← JS/CSS opsional
└── docs/
    └── plugin-system.md             ← dokumen ini
```

---

## Cara Membuat Plugin

### Langkah 1 — Buat folder plugin

```
plugins/nama-plugin-kamu/
├── plugin.php
└── NamaPlugin.php
```

Penamaan folder menggunakan **kebab-case** (contoh: `midtrans-payment`, `loyalty-points`).

---

### Langkah 2 — Buat class plugin

```php
<?php
// plugins/nama-plugin-kamu/NamaPlugin.php

use App\Plugin\PluginInterface;

class NamaPlugin implements PluginInterface
{
    public function getName(): string    { return 'Nama Plugin Kamu'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'Nama Developer'; }

    public function register(): void
    {
        // Daftarkan hook di sini
        \App\Plugin\HookManager::addAction('order.created', [$this, 'onOrderCreated']);
        \App\Plugin\HookManager::addFilter('cart.total',    [$this, 'tambahBiaya']);
    }

    public function onOrderCreated(array $order): void
    {
        // Lakukan sesuatu saat order baru masuk
        // Contoh: kirim notifikasi ke sistem eksternal
    }

    public function tambahBiaya(float $total, array $cart): float
    {
        return $total + 1000; // tambah biaya admin Rp1.000
    }
}
```

---

### Langkah 3 — Buat entry point plugin

```php
<?php
// plugins/nama-plugin-kamu/plugin.php

require_once __DIR__ . '/NamaPlugin.php';

return [
    'class'       => NamaPlugin::class,
    'name'        => 'Nama Plugin Kamu',
    'version'     => '1.0.0',
    'author'      => 'Nama Developer',
    'description' => 'Deskripsi singkat fungsi plugin ini.',
    'requires'    => '1.0.0', // versi minimum KopiBot
];
```

---

### Langkah 4 — Aktifkan plugin

Edit `plugins/plugins.json`:

```json
{
    "nama-plugin-kamu": { "active": true },
    "plugin-lain":      { "active": false }
}
```

Plugin langsung aktif pada request berikutnya — tidak perlu restart server.

---

## Hook Reference

### Chat & AI

| Hook | Type | Parameter | Keterangan |
|------|------|-----------|------------|
| `chat.message_received` | action | `$message`, `$branchId` | Pesan baru masuk dari channel manapun |
| `chat.before_ai` | filter | `$messages[]`, `$branchId` | Sebelum dikirim ke LLM — bisa modifikasi prompt |
| `chat.after_ai` | filter | `$response`, `$branchId` | Setelah LLM merespons — bisa modifikasi teks |
| `chat.intent_detected` | action | `$intent`, `$message` | Setelah intent terdeteksi |
| `llm.providers` | filter | `$providers[]` | Daftar provider AI — tambah provider baru di sini |
| `skills.registered` | filter | `$skills[]` | Daftar skill chatbot — tambah skill baru di sini |

### Order Lifecycle

| Hook | Type | Parameter | Keterangan |
|------|------|-----------|------------|
| `order.before_create` | filter | `$orderData[]` | Sebelum order disimpan ke DB |
| `order.created` | action | `$order[]` | Order baru berhasil dibuat |
| `order.status_changed` | action | `$order[]`, `$oldStatus`, `$newStatus` | Status order berubah |
| `order.payment_updated` | action | `$order[]`, `$paymentStatus` | Status pembayaran berubah |
| `order.completed` | action | `$order[]` | Order selesai |

### Cart & Checkout

| Hook | Type | Parameter | Keterangan |
|------|------|-----------|------------|
| `cart.item_added` | action | `$item[]`, `$sessionId` | Item ditambahkan ke keranjang |
| `cart.total` | filter | `$total`, `$cart[]` | Total harga — tambah biaya/diskon custom |
| `cart.before_checkout` | filter | `$cartData[]` | Validasi sebelum checkout |
| `checkout.data` | filter | `$data[]` | Data checkout — bisa tambah field custom |

### Dashboard & UI

| Hook | Type | Parameter | Keterangan |
|------|------|-----------|------------|
| `dashboard.nav_items` | filter | `$items[]`, `$role` | Tambah item menu sidebar |
| `dashboard.branch_widgets` | filter | `$widgets[]` | Widget di dashboard cabang |
| `dashboard.super_widgets` | filter | `$widgets[]` | Widget di dashboard super admin |
| `settings.sections` | filter | `$sections[]`, `$branchId` | Tambah section di halaman settings |
| `settings.saved` | action | `$branchId`, `$settings[]` | Pengaturan cabang disimpan |

### Channel

| Hook | Type | Parameter | Keterangan |
|------|------|-----------|------------|
| `channel.registered` | filter | `$channels[]` | Tambah channel baru (implementasi ChannelInterface) |
| `channel.message_sent` | action | `$recipient`, `$message`, `$channel` | Setelah pesan dikirim ke customer |

---

## Interface Reference

### PluginInterface

```php
interface PluginInterface {
    public function getName(): string;
    public function getVersion(): string;
    public function getAuthor(): string;
    public function register(): void;  // ← daftarkan semua hook di sini
}
```

### LlmProviderInterface

Implementasikan ini untuk menambah AI provider baru (Gemini, Groq, Mistral, dll.):

```php
interface LlmProviderInterface {
    public function getName(): string;   // 'gemini', 'groq', dll.
    public function chat(array $messages, array $options = []): string;
    public function estimateCost(int $promptTokens, int $completionTokens): float;
    public function isAvailable(): bool;
}
```

Kemudian daftarkan via filter:

```php
HookManager::addFilter('llm.providers', function(array $providers): array {
    $providers['gemini'] = new GeminiProvider();
    return $providers;
});
```

### ChannelInterface

Implementasikan ini untuk menambah channel baru (LINE, Slack, dll.):

```php
interface ChannelInterface {
    public function getName(): string;
    public function handleWebhook(array $payload, int $branchId): void;
    public function sendMessage(string $recipient, string $message, array $options = []): bool;
    public function isAvailable(int $branchId): bool;
}
```

---

## Database Schema

Untuk plugin yang perlu menyimpan konfigurasi:

```sql
-- Tabel manajemen plugin (global)
CREATE TABLE plugins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(100) UNIQUE NOT NULL,
    name       VARCHAR(200) NOT NULL,
    version    VARCHAR(20),
    is_active  TINYINT(1) DEFAULT 1,
    settings   JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Pengaturan plugin per cabang
CREATE TABLE plugin_branch_settings (
    plugin_slug VARCHAR(100)  NOT NULL,
    branch_id   INT           NOT NULL,
    setting_key VARCHAR(100)  NOT NULL,
    setting_val TEXT,
    PRIMARY KEY (plugin_slug, branch_id, setting_key)
);
```

Plugin bisa menggunakan tabel ini untuk menyimpan API key, konfigurasi, dll. per cabang.

---

## Roadmap Implementasi

| Fase | Komponen | Status |
|------|----------|--------|
| **1** | `HookManager`, `PluginInterface`, `PluginLoader` | ✅ Done |
| **2** | Hook di order lifecycle (`OrderModel`) | ✅ Done |
| **3** | Hook di chat/AI flow (`ChatbotEngine`, cart endpoints) | ✅ Done |
| **4** | `LlmProviderInterface` — filter `llm.providers` di `ChatbotEngine` | ✅ Done |
| **5** | `ChannelInterface` + `ChannelRouter` + generic webhook endpoint | ✅ Done |
| **6** | Plugin manager UI di dashboard (`super/plugins.php`) | ✅ Done |

---

## Contoh Plugin Lengkap

Lihat `plugins/example-plugin/` untuk contoh plugin minimal yang bisa dijadikan template.
Untuk contoh skill chatbot yang siap di-clone, lihat `plugins/example-skill-plugin/`.

Template ini juga mudah ditemukan dari dashboard super admin di `super/plugins.php`:

- Plugin template diberi badge `Template Plugin` atau `Template Skill`.
- Gunakan filter `Template` untuk menampilkan starter plugin khusus developer.
- Gunakan kotak pencarian untuk mencari plugin berdasarkan nama, slug, deskripsi, atau author.

---

## Checklist Currency-Safe

Saat menambah fitur baru yang menampilkan harga, nominal promo, revenue, atau total order:

- Jangan hardcode `Rp`, `$`, `A$`, atau symbol lain langsung di view, API, atau skill chatbot.
- Untuk nominal uang, selalu pakai `Currency::format($amount, $currency)`.
- Untuk label field atau heading, gunakan konteks yang jelas:
  branch/local memakai kode cabang seperti `AUD`, `USD`, `IDR`
  global memakai label eksplisit seperti `Global (IDR)`
- Untuk halaman cabang, ambil currency dari `BranchModel::getCurrency($branchId)`.
- Untuk output chatbot, pastikan `currency` ikut diteruskan di `context` skill.
- Untuk halaman global super admin, pastikan kalau nilainya memang hasil agregasi/normalisasi pusat, labelnya menyebut `IDR` atau `Global (IDR)`.
- Sebelum merge, cari cepat string raw seperti `Rp`, `Nominal (Rp)`, `Harga (Rp)`, `Min. Order (Rp)` untuk memastikan tidak ada hardcode baru.

Helper yang bisa dipakai:

- `Currency::format($amount, $currency)`
- `Currency::code($currency)`
- `Currency::contextLabel($currency, $global = false)`
- `Currency::fieldLabel($field, $currency, $global = false)`

Audit cepat yang bisa dijalankan:

```bash
php scripts/audit-currency-hardcode.php
```

Script default memeriksa code yang dieksekusi di `app/`, `public/`, dan `plugins/`.
Kalau mau ikut memeriksa dokumentasi dan contoh, jalankan:

```bash
php scripts/audit-currency-hardcode.php --include-docs
```

Script ini mencari marker seperti `Rp` atau label `(Rp)` agar hardcode currency baru lebih cepat ketahuan.

---

## Menambah Channel Baru via Plugin

Implementasikan `ChannelInterface` dan daftarkan via filter `channel.registered`:

```php
use App\Plugin\{PluginInterface, HookManager, ChannelInterface};

class LineChannelProvider implements ChannelInterface
{
    public function getName(): string { return 'line'; }

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $signature = $headers['X-Line-Signature'] ?? '';
        $secret    = 'your-channel-secret';
        return hash_equals(base64_encode(hash_hmac('sha256', $rawBody, $secret, true)), $signature);
    }

    public function parseMessage(array $payload): ?array
    {
        $event = $payload['events'][0] ?? null;
        if (!$event || $event['type'] !== 'message') return null;
        return [
            'from'    => $event['source']['userId'],
            'message' => $event['message']['text'] ?? '',
        ];
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        // Panggil LINE Messaging API
        return true;
    }

    public function isAvailable(int $branchId): bool { return true; }
}

class LinePlugin implements PluginInterface
{
    public function getName(): string    { return 'LINE Messenger'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'Developer'; }

    public function register(): void
    {
        HookManager::addFilter('channel.registered', function(array $channels): array {
            $channels['line'] = new LineChannelProvider();
            return $channels;
        });
    }
}
```

**Webhook URL:** `POST /api/channel/webhook.php?channel=line&branch={id}`

Catatan desain:

- Channel plugin boleh memakai URL per-cabang seperti `?branch={id}`.
- Channel plugin juga boleh memakai satu webhook URL global lalu melakukan resolve branch sendiri dari header, secret token, atau payload.
- Contoh implementasi ini dipakai oleh plugin Telegram, sehingga ia bisa mendukung:
  satu bot per cabang, atau
  satu bot host untuk semua cabang aktif.
- Pola yang sama juga dipakai oleh plugin Discord, dengan resolve branch berdasarkan Discord public key yang terverifikasi.

Hook `channel.message_sent` otomatis difire setelah pesan dikirim di semua channel yang aktif, termasuk channel plugin seperti Telegram, WA, LINE, atau Discord.

---

## Menambah Skill Chatbot via Plugin

Skill adalah unit kemampuan chatbot — menangani satu jenis intent dan mengembalikan reply. Plugin bisa mendaftarkan skill baru via filter `skills.registered` tanpa mengubah kode inti.

Mulai sekarang, registrasi skill disarankan memakai helper `SkillRegistry` agar developer tidak perlu `array_splice()` manual.

### SkillInterface

```php
namespace App\Skills;

interface SkillInterface
{
    /**
     * @param  array $context {branch_id, branch, customer, conversation, cart, intent,
     *                         message, language, currency, ppn_rate, conv_context, ...}
     * @return array {reply, state, action_result, conv_context}
     */
    public function handle(array $context): array;

    public function canHandle(string $intent): bool;
}
```

### Contoh — Skill Reservasi Meja

```php
<?php
// plugins/reservasi-meja/ReservasiSkill.php

use App\Skills\SkillInterface;

class ReservasiSkill implements SkillInterface
{
    public function canHandle(string $intent): bool
    {
        return $intent === 'reservasi_meja';
    }

    public function handle(array $context): array
    {
        $lang  = $context['language'];
        $reply = $lang === 'id'
            ? "Untuk reservasi meja, silakan hubungi kami di 0812-xxxx-xxxx atau isi form di website kami."
            : "To reserve a table, please contact us at 0812-xxxx-xxxx or fill in the form on our website.";

        return [
            'reply'         => $reply,
            'state'         => 'idle',
            'action_result' => null,
            'conv_context'  => $context['conv_context'],
        ];
    }
}
```

```php
<?php
// plugins/reservasi-meja/ReservasiPlugin.php

use App\Plugin\{PluginInterface, HookManager};
use App\Skills\SkillRegistry;
use App\Services\IntentPatternRegistry;

class ReservasiPlugin implements PluginInterface
{
    public function getName(): string    { return 'Reservasi Meja'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string  { return 'Developer'; }

    public function register(): void
    {
        HookManager::addFilter('skills.registered', [$this, 'registerSkill']);
        HookManager::addFilter('intent.patterns',   [$this, 'registerIntentPatterns']);
    }

    public function registerSkill(array $skills): array
    {
        return SkillRegistry::register($skills, new ReservasiSkill(), 60);
    }

    public function registerIntentPatterns(array $patterns): array
    {
        return IntentPatternRegistry::extend($patterns, 'reservasi_meja', [
            'reservasi', 'booking meja', 'book table', 'reserve table', 'pesan meja',
        ]);
    }
}
```

```php
<?php
// plugins/reservasi-meja/plugin.php

require_once __DIR__ . '/ReservasiSkill.php';
require_once __DIR__ . '/ReservasiPlugin.php';

return [
    'class'       => ReservasiPlugin::class,
    'name'        => 'Reservasi Meja',
    'version'     => '1.0.0',
    'author'      => 'Developer',
    'description' => 'Menambah kemampuan chatbot untuk menjawab pertanyaan reservasi meja.',
    'requires'    => '1.0.0',
];
```

### Catatan Penting

- **Urutan skill penting.** Gunakan prioritas angka saat registrasi skill. Semakin kecil angkanya, semakin dulu skill dijalankan. `RefusalSkill` bawaan dipasang di prioritas `999` sebagai fallback.
- **Intent baru** bisa didaftarkan lewat filter `intent.patterns`, jadi plugin tidak perlu mengubah `IntentDetector` core.
- Context yang tersedia di `handle()` mencakup: `branch_id`, `branch`, `customer`, `conversation`, `cart`, `intent`, `message`, `language`, `currency`, `ppn_rate`, `conv_context`, `now_local`, `branch_timezone`.

### Helper yang Disediakan

```php
use App\Skills\SkillRegistry;
use App\Services\IntentPatternRegistry;
```

- `SkillRegistry::register($skills, $skill, $priority)` → menambahkan skill dengan prioritas eksplisit.
- `IntentPatternRegistry::extend($patterns, 'intent_baru', ['keyword 1', 'keyword 2'])` → menambah keyword intent rule-based dari plugin.
