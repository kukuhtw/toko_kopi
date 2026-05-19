# KopiBot - AI Chatbot Order System

> ## AI Agent Coffee Shop Commerce Platform
> Platform AI untuk otomatisasi order, customer service, loyalty customer, dan manajemen multi cabang coffee shop.
>
> ### Features
> - AI Chatbot Order Menu
> - WhatsApp / Telegram / Discord Integration
> - Multi Branch Management
> - AI Upselling & Promo Recommendation
> - Order via Website & Chat Apps
> - Variant Product & Topping Support
> - Loyalty Point and Redeem Point
> - Multi Currency, Tax, and Timezone
>
> ### Tech Stack
> PHP Native - MySQL - OpenAI - Anthropic
> WhatsApp Gateway - REST API - LLM AI
>
> ### Suitable For
> Coffee Shop - Cafe - Restaurant - Bakery - Beverage Store
>
> Dibuat dan dikembangkan oleh:
> Kukuh TW
>
> Email     : kukuhtw@gmail.com
> WhatsApp  : https://wa.me/628129893706
> Instagram : @kukuhtw
> X/Twitter : @kukuhtw
> Facebook  : https://www.facebook.com/kukuhtw
> LinkedIn  : https://linkedin.com/in/kukuhtw
>
> Demo:
> https://botlelang.com/toko_kopi
>
> Copyright 2026 Kukuh TW. All rights reserved.

Sistem chatbot pemesanan kopi berbasis PHP 8 native, tanpa framework besar, dengan satu codebase untuk multi-cabang, multi-channel, multi-bahasa, promo engine, loyalty point, dan plugin system.

---

## Fitur

| Kategori | Detail |
|----------|--------|
| **Chatbot AI** | Intent detection berbasis rule dan LLM untuk order, promo, dan customer interaction |
| **Multi Cabang** | Satu brand, banyak cabang dengan menu, promo, pengaturan, mata uang, dan timezone terpisah |
| **Multi Channel** | Website, WhatsApp, Telegram, dan Discord dengan logika chatbot yang sama |
| **Plugin System** | Tambah fitur tanpa ubah kode inti melalui action/filter hooks |
| **Shopping Cart** | Tambah, edit, hapus, clear, promo, dan checkout berbasis session |
| **Checkout Flow** | Chatbot meminta data customer langkah demi langkah sampai order siap dibuat |
| **Loyalty Point** | Earn point otomatis, cek saldo, redeem point via chatbot dan halaman order web |
| **Promo Engine** | Diskon persen, nominal, promo code, jadwal promo, min order, dan rekomendasi promo |
| **Payment Gateway** | Midtrans dan Xendit via plugin |
| **Menu Management** | Upload CSV, variant size/price, topping, dan override per cabang |
| **Dashboard** | Super admin lintas cabang, branch admin per cabang, histori loyalty customer |
| **Dokumentasi HTML** | README dan docs Markdown tersedia juga sebagai halaman HTML |
| **Export CSV** | Export order, menu, promo, dan data dashboard terkait |

---

## Persyaratan

- PHP **8.0+** dengan ekstensi: `pdo_mysql`, `mbstring`, `json`, `fileinfo`
- MySQL **5.7+** atau MariaDB **10.3+**
- Apache dengan `mod_rewrite` aktif
- XAMPP sudah cukup untuk development lokal

---

## Instalasi

Panduan lengkap ada di [`docs/instalasi.md`](docs/instalasi.md).

### Cara 1 - Web Installer

1. Salin folder proyek ke `C:\xampp\htdocs\toko_kopi\`
2. Buka:

```text
http://localhost/toko_kopi/public/install.php
```

3. Ikuti wizard sampai database, `.env`, dan akun admin selesai dibuat.
4. Hapus `public/install.php` setelah instalasi selesai.

### Cara 2 - Instalasi Manual

1. Buat database `toko_kopi`
2. Import:

```text
database/schema.sql
database/seed.sql
```

3. Salin `.env.example` menjadi `.env`
4. Isi konfigurasi database dan `BASE_URL`
5. Pastikan folder berikut bisa ditulis:

```text
uploads/
storage/logs/
```

LLM API key tidak diisi di `.env`, tetapi dikelola lewat dashboard Super Admin.

---

## URL Penting

| URL | Keterangan |
|-----|-----------|
| `http://localhost/toko_kopi/public/` | Landing page |
| `http://localhost/toko_kopi/public/readme.php` | README versi HTML |
| `http://localhost/toko_kopi/public/docs/index.php` | Pusat dokumentasi HTML |
| `http://localhost/toko_kopi/public/login.php` | Login admin |
| `http://localhost/toko_kopi/public/chat.php` | Chat demo |
| `http://localhost/toko_kopi/public/order.php?branch={slug}` | Halaman order per cabang |

---

## Akun Default

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@tokokopi.com | password |
| Admin Jakarta Selatan | admin.jaksel@tokokopi.com | password |
| Admin Bandung | admin.bandung@tokokopi.com | password |
| Admin Surabaya | admin.surabaya@tokokopi.com | password |

Ganti password setelah login pertama.

---

## Struktur Direktori

```text
toko_kopi/
|-- app/
|   |-- Config/
|   |-- Helpers/
|   |-- Models/
|   |-- Plugin/
|   `-- Services/
|-- database/
|   |-- schema.sql
|   |-- seed.sql
|   `-- add_loyalty_point_plugin.sql
|-- plugins/
|   |-- loyalty-point/
|   |-- midtrans-payment/
|   |-- xendit-payment/
|   |-- telegram-channel/
|   |-- discord-channel/
|   |-- upselling/
|   |-- rekomendasi-promo/
|   |-- cms-berita/
|   `-- plugins.json
|-- public/
|   |-- index.php
|   |-- readme.php
|   |-- chat.php
|   |-- order.php
|   |-- docs/
|   |-- api/
|   `-- dashboard/
|-- docs/
|   |-- instalasi.md
|   |-- lisensi.md
|   |-- plugin-system.md
|   |-- tutorial-membuat-plugin.md
|   `-- customer-agent-architecture.md
|-- uploads/
`-- storage/logs/
```

---

## Channel Configuration

### WhatsApp

Didukung beberapa provider:

- Fonnte
- Wablas
- Meta Cloud API
- Twilio
- Baileys Bridge
- MessageBird
- Vonage

Webhook:

```text
POST /api/whatsapp/webhook.php?branch={id}
```

### Telegram

- Aktifkan plugin `telegram-channel`
- Gunakan webhook:

```text
POST /api/channel/webhook.php?channel=telegram
```

### Discord

- Aktifkan plugin `discord-channel`
- Gunakan webhook:

```text
POST /api/channel/webhook.php?channel=discord
```

### Channel Custom

Tambahkan lewat implementasi `ChannelInterface` dan filter `channel.registered`.

---

## Plugin System

Plugin memungkinkan developer menambah fitur tanpa mengubah kode inti.

### Struktur Plugin Minimal

```text
plugins/nama-plugin/
|-- plugin.php
`-- NamaPlugin.php
```

### Hook Penting

| Hook | Type | Keterangan |
|------|------|-----------|
| `order.created` | action | Order baru berhasil dibuat |
| `order.status_changed` | action | Status order berubah |
| `order.completed` | action | Order selesai |
| `order.payment_updated` | action | Status bayar berubah |
| `cart.total` | filter | Modifikasi total harga |
| `cart.before_checkout` | filter | Validasi sebelum checkout |
| `chat.message_received` | action | Pesan masuk dari channel manapun |
| `chat.before_ai` | filter | Sebelum dikirim ke LLM |
| `chat.after_ai` | filter | Setelah LLM merespons |
| `llm.providers` | filter | Tambah AI provider custom |
| `channel.registered` | filter | Tambah channel baru |
| `dashboard.nav_items` | filter | Tambah menu sidebar |

Referensi:

- [`docs/plugin-system.md`](docs/plugin-system.md)
- [`docs/tutorial-membuat-plugin.md`](docs/tutorial-membuat-plugin.md)
- [`public/docs/index.php`](public/docs/index.php)

### Plugin yang Sudah Tersedia

- `loyalty-point` - loyalty earn/redeem dan dashboard loyalty
- `midtrans-payment` - payment gateway Midtrans
- `xendit-payment` - payment gateway Xendit
- `telegram-channel` - channel Telegram
- `discord-channel` - channel Discord
- `upselling` - upsell recommendation
- `rekomendasi-promo` - promo recommendation
- `cms-berita` - branch news/content
- `instagram-dm` - integrasi DM Instagram
- `anthropic-llm`, `gemini-llm`, `openrouter-llm` - provider AI tambahan

---

## Loyalty Point

Fitur loyalty saat ini mendukung:

- earn point otomatis saat order `completed`
- opsi branch untuk hanya memberi poin jika payment `paid`
- cek saldo lewat chatbot: `poin saya`, `cek poin`, `my points`
- redeem point lewat chatbot: `pakai poin 10`, `pakai semua poin`, `hapus poin`
- redeem point lewat halaman order web
- sinkronisasi diskon promo dan loyalty di cart
- refund point otomatis saat order dibatalkan
- dashboard branch untuk histori member loyalty dan mutasi poin per customer

File utama:

- [`plugins/loyalty-point/LoyaltyPointPlugin.php`](plugins/loyalty-point/LoyaltyPointPlugin.php)
- [`plugins/loyalty-point/LoyaltyPointRepository.php`](plugins/loyalty-point/LoyaltyPointRepository.php)
- [`plugins/loyalty-point/LoyaltyPointSkill.php`](plugins/loyalty-point/LoyaltyPointSkill.php)
- [`public/dashboard/branch/loyalty.php`](public/dashboard/branch/loyalty.php)

---

## LLM Integration

Konfigurasi lewat dashboard Super Admin.

| Provider | Contoh Model |
|----------|--------------|
| OpenAI | `gpt-4o`, `gpt-4o-mini` |
| Anthropic | `claude-3-5-haiku`, `claude-3-5-sonnet` |
| Rule-based | tanpa API key |

Provider custom bisa ditambahkan lewat plugin.

---

## API Endpoints

| Endpoint | Method | Keterangan |
|----------|--------|-----------|
| `/api/chat/send.php` | POST | Kirim pesan ke chatbot web |
| `/api/whatsapp/webhook.php` | POST | Webhook WhatsApp |
| `/api/channel/webhook.php?channel={name}&branch={id}` | POST | Webhook channel plugin |
| `/api/cart/add.php` | POST | Tambah item ke cart |
| `/api/cart/update.php` | POST | Update quantity item |
| `/api/cart/clear.php` | POST | Kosongkan cart |
| `/api/loyalty/status.php` | GET | Cek saldo loyalty customer |
| `/api/loyalty/redeem.php` | POST | Pakai atau batalkan redeem point |
| `/api/order/checkout.php` | POST | Buat order dari cart |
| `/api/order/status.php` | GET | Cek status order |
| `/api/upload/menu.php` | POST | Upload CSV menu |
| `/api/upload/promo.php` | POST | Upload CSV promo |
| `/api/export/orders-branch.php` | GET | Export order branch |
| `/api/export/orders-super.php` | GET | Export order semua cabang |

---

## Simulasi Chat

Coba percakapan berikut di `/chat.php`:

```text
menu
harga latte
promo
pesan 2 latte
cart
ubah latte jadi 1
poin saya
pakai poin 10
hapus poin
checkout
```

Di halaman order web, redeem point juga bisa dipakai langsung dari panel loyalty saat checkout.

---

## Dokumentasi

Versi HTML yang bisa dibuka langsung di browser:

- [`public/readme.php`](public/readme.php)
- [`public/docs/index.php`](public/docs/index.php)
- [`public/docs/instalasi.php`](public/docs/instalasi.php)
- [`public/docs/lisensi.php`](public/docs/lisensi.php)
- [`public/docs/plugin-system.php`](public/docs/plugin-system.php)
- [`public/docs/tutorial-membuat-plugin.php`](public/docs/tutorial-membuat-plugin.php)

Dokumen Markdown sumber:

- [`docs/instalasi.md`](docs/instalasi.md)
- [`docs/lisensi.md`](docs/lisensi.md)
- [`docs/plugin-system.md`](docs/plugin-system.md)
- [`docs/tutorial-membuat-plugin.md`](docs/tutorial-membuat-plugin.md)
- [`docs/customer-agent-architecture.md`](docs/customer-agent-architecture.md)

---

## Keamanan

- Password memakai `password_hash` / `password_verify`
- Query memakai PDO prepared statements
- Form dashboard memakai CSRF token
- RBAC untuk `super_admin` dan `branch_admin`
- Branch admin hanya bisa akses data cabangnya
- Credentials disimpan di `.env`

---

## Kontribusi

1. Fork repo ini
2. Buat branch fitur
3. Commit dengan pesan jelas
4. Buka Pull Request

Untuk fitur besar, pertimbangkan bentuk plugin agar core tetap bersih.

---

## Lisensi

[GNU Affero General Public License v3.0 (AGPL-3.0)](https://www.gnu.org/licenses/agpl-3.0.html)

Proyek ini menggunakan model **dual license**:

- **AGPL-3.0** untuk penggunaan open source
- **Commercial License** untuk penggunaan proprietary / tertutup yang tidak ingin tunduk pada kewajiban AGPL

### Kapan AGPL cocok

- kamu membuat fork open source
- kamu nyaman membuka source code turunan
- kamu menjalankan deployment yang siap memenuhi kewajiban copyleft

### Kapan commercial license dibutuhkan

- kamu ingin white-label
- kamu ingin SaaS proprietary
- kamu ingin modifikasi core tetap tertutup
- kamu menjual solusi ke klien tanpa membuka source code turunan

Detail lebih lengkap ada di [`docs/lisensi.md`](docs/lisensi.md).

Untuk commercial inquiry:

- Email: `kukuhtw@gmail.com`
- WhatsApp: `https://wa.me/628129893706`

Catatan: penjelasan ini bersifat praktis untuk proyek dan bukan nasihat hukum formal.
