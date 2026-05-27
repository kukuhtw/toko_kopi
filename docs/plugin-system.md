# Plugin System — KopiBot AI

> ## AI Agent Commerce Platform
> Platform AI untuk otomatisasi order, customer service, loyalty customer, Customer CRM, Customer Portal, payment gateway, delivery connector, dan manajemen multi cabang untuk berbagai vertical bisnis.
>
> Sistem plugin pada aplikasi ini dirancang agar satu codebase dapat diperluas menjadi platform commerce untuk coffee shop, kuliner, bakery, fresh market, pharmacy, mini mart, retail mart, dan specialty commerce lainnya.
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
1. Gambaran Umum
2. Filosofi Plugin Architecture
3. Struktur Direktori
4. Cara Membuat Plugin
5. Hook Reference
6. Plugin Business Vertical
7. Interface Reference
8. Database Schema
9. Roadmap Implementasi

---

## Gambaran Umum

KopiBot AI mendukung sistem plugin yang memungkinkan developer menambahkan fitur baru tanpa mengubah kode inti. Plugin bekerja melalui dua mekanisme utama:

- **Action** — bereaksi terhadap event seperti order dibuat, pesan masuk, checkout selesai, payment updated, dan webhook delivery.
- **Filter** — memodifikasi nilai yang dilewatkan seperti total cart, response AI, provider AI, dashboard navigation, dan konfigurasi channel.

Arsitektur ini dipilih agar aplikasi dapat berkembang menjadi platform AI Agent Commerce multi-business tanpa harus membuat codebase terpisah untuk setiap industri.

Contoh:

- coffee shop memakai plugin topping dan beverage menu
- bakery memakai plugin bundle dan pastry catalog
- pharmacy memakai plugin FAQ kesehatan, customer support, dan katalog produk
- mart memakai plugin katalog retail, POS connector, dan promo engine

### Catatan Lisensi Plugin

KopiBot AI memakai model **AGPL + commercial license**.

- Jika kamu memodifikasi **core** untuk mendukung plugin, perubahan pada core mengikuti lisensi utama proyek.
- Plugin yang berdiri sebagai modul terpisah lewat hook atau extension point publik bisa memiliki pertimbangan lisensi sendiri.
- Untuk plugin proprietary yang dibundel dalam deployment tertutup bersama core, lihat juga `lisensi.md`.

```text
Request → Core Engine → HookManager::doAction/applyFilters → Plugin Callbacks → Response
```

---

## Filosofi Plugin Architecture

Plugin system dirancang dengan beberapa tujuan:

| Tujuan | Penjelasan |
|---|---|
| Multi Business Vertical | Satu aplikasi dapat dipakai untuk berbagai jenis bisnis |
| White-label Friendly | Tiap deployment dapat mengaktifkan plugin berbeda |
| Low Coupling | Plugin tidak mengubah core langsung |
| Extensible AI Commerce | AI, payment, delivery, dan POS dapat berkembang mandiri |
| Enterprise Friendly | Memudahkan maintenance dan deployment modular |

Plugin dapat dipakai untuk:

- payment gateway
- channel chat
- AI provider
- delivery connector
- POS connector
- loyalty
- CRM
- FAQ RAG
- complaint handler
- menu templates
- branch dashboard
- customer portal
- analytics
- recommendation engine

---

## Struktur Direktori

```text
toko_kopi/
├── app/
│   └── Plugin/
│       ├── HookManager.php
│       ├── PluginLoader.php
│       ├── PluginInterface.php
│       ├── LlmProviderInterface.php
│       └── ChannelInterface.php
├── plugins/
│   ├── plugins.json
│   └── {slug-plugin}/
│       ├── plugin.php
│       ├── {ClassName}.php
│       └── assets/
└── docs/
    └── plugin-system.md
```

---

## Cara Membuat Plugin

### Langkah 1 — Buat folder plugin

```text
plugins/nama-plugin-kamu/
├── plugin.php
└── NamaPlugin.php
```

Penamaan folder menggunakan `kebab-case`.

Contoh:

- `midtrans-payment`
- `gosend-delivery`
- `faq-rag`
- `pharmacy-template`
- `mart-template`
- `bakery-template`

---

### Langkah 2 — Buat class plugin

```php
<?php

use App\Plugin\PluginInterface;

class NamaPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'Nama Plugin Kamu';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'Nama Developer';
    }

    public function register(): void
    {
        \App\Plugin\HookManager::addAction('order.created', [$this, 'onOrderCreated']);

        \App\Plugin\HookManager::addFilter('cart.total', [$this, 'modifyCartTotal']);
    }

    public function onOrderCreated(array $order): void
    {
        // proses order
    }

    public function modifyCartTotal(float $total, array $cart): float
    {
        return $total;
    }
}
```

---

### Langkah 3 — Buat entry point plugin

```php
<?php

require_once __DIR__ . '/NamaPlugin.php';

return [
    'class'       => NamaPlugin::class,
    'name'        => 'Nama Plugin Kamu',
    'version'     => '1.0.0',
    'author'      => 'Nama Developer',
    'description' => 'Deskripsi singkat fungsi plugin ini.',
    'requires'    => '1.0.0'
];
```

---

### Langkah 4 — Aktifkan plugin

Edit `plugins/plugins.json`:

```json
{
    "nama-plugin-kamu": { "active": true },
    "plugin-lain": { "active": false }
}
```

Plugin aktif pada request berikutnya tanpa restart server.

---

## Plugin Business Vertical

Plugin vertical digunakan untuk mengubah karakter aplikasi sesuai jenis bisnis.

| Plugin | Fungsi |
|---|---|
| `coffee-template` | Template menu coffee shop dan beverage |
| `bakery-template` | Template bakery dan pastry |
| `fruit-template` | Template toko buah, smoothie, dan salad |
| `meat-veggie-template` | Template fresh meat dan sayuran |
| `pharmacy-template` | Template pharmacy dan produk kesehatan |
| `mart-template` | Template mini mart dan retail catalog |

Vertical plugin dapat dikombinasikan dengan:

- loyalty
- payment gateway
- POS connector
- delivery connector
- FAQ RAG
- complaint handling
- CRM
- AI recommendation

---

## Hook Reference

### Commerce Hooks

| Hook | Type | Keterangan |
|---|---|---|
| `order.created` | action | Order baru dibuat |
| `order.status_changed` | action | Status order berubah |
| `order.completed` | action | Order selesai |
| `order.payment_updated` | action | Payment berubah |
| `delivery.status_updated` | action | Status delivery berubah |
| `cart.total` | filter | Modifikasi total cart |
| `cart.before_checkout` | filter | Validasi checkout |

### AI & Chat Hooks

| Hook | Type | Keterangan |
|---|---|---|
| `chat.message_received` | action | Pesan diterima |
| `chat.before_ai` | filter | Sebelum LLM dipanggil |
| `chat.after_ai` | filter | Setelah AI reply |
| `llm.providers` | filter | Tambah provider AI |
| `faq.search` | filter | Modifikasi FAQ retrieval |

### Dashboard Hooks

| Hook | Type | Keterangan |
|---|---|---|
| `dashboard.nav_items` | filter | Tambah menu dashboard |
| `dashboard.widgets` | filter | Tambah widget dashboard |

---

## Interface Reference

Interface utama:

- `PluginInterface`
- `LlmProviderInterface`
- `ChannelInterface`

Developer dapat membuat:

- custom AI provider
- custom WhatsApp bridge
- custom delivery connector
- custom POS connector
- custom dashboard module

---

## Database Schema

Plugin boleh membawa schema sendiri.

Contoh:

```text
plugins/customer-crm/schema.sql
plugins/faq-rag/schema.sql
plugins/complaint-handler/schema.sql
```

Saat plugin aktif, bootstrap schema dapat dijalankan otomatis.

---

## Roadmap Implementasi

Roadmap plugin ecosystem:

| Area | Rencana |
|---|---|
| AI Commerce | Agentic AI workflow dan recommendation engine |
| Delivery | Connector GoSend, GrabExpress, Lalamove |
| POS | Moka, Pawoon, Majoo, custom POS |
| Pharmacy | FAQ kesehatan, prescription workflow, customer support |
| Mart | Retail catalog sync dan inventory connector |
| Omnichannel | Instagram DM, Facebook Messenger, Line |
| Analytics | Customer insight dan recommendation analytics |

---

## Kesimpulan

Plugin system adalah fondasi utama agar aplikasi berkembang dari chatbot coffee shop menjadi platform AI Agent Commerce lintas industri. Dengan pendekatan modular ini, deployment dapat disesuaikan untuk bisnis kuliner, pharmacy, mart, retail, dan specialty commerce tanpa harus membuat aplikasi baru dari nol.
