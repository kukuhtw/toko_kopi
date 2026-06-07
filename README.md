# KopiBot AI Agent Commerce Platform

KopiBot adalah platform commerce modular berbasis PHP yang menggabungkan katalog produk, cart, order, CRM, loyalty, promo, chatbot, payment, delivery, dan plugin bisnis ke dalam satu fondasi aplikasi.

Repo ini sudah bergerak ke arsitektur Composer dengan namespace `KopiBot\...`, bootstrap terpusat, migration runner, seeder, plugin modern, dan kumpulan smoke test untuk verifikasi runtime.

Dokumen gambaran arsitektur yang lebih detail tersedia di:

```text
docs/architecture-overview.md
```

## Ringkasan

- Fokus utama aplikasi:
  - penjualan produk dan checkout
  - order via dashboard, API, dan channel chat
  - CRM, loyalty, promo, FAQ, dan memory chatbot
  - payment gateway, delivery, channel messaging, dan connector eksternal lewat plugin
- Cocok untuk:
  - coffee shop
  - restoran
  - bakery
  - minimarket
  - apotek
  - fresh market
  - retail dan bisnis layanan yang butuh alur order + chatbot + plugin

## Arsitektur Baru

Arsitektur baru memakai Composer sebagai fondasi runtime utama:

- `bootstrap.php`
  Memuat `vendor/autoload.php` dan menjalankan boot aplikasi.
- `src/`
  Berisi core application, domain services, repository, channel, auth, payment, dan state AI.
- `config/runtime.php`
  Menetapkan constant runtime, load `.env`, path penting, dan konfigurasi global.
- `database/migrations/`
  Sumber utama schema inti aplikasi.
- `database/seeders/`
  Seed demo untuk baseline data awal.
- `plugins/`
  Plugin bisnis, payment, delivery, CRM, content, connector, dan channel.

Struktur namespace utama:

- `KopiBot\App`
- `KopiBot\Core\...`
- `KopiBot\Domains\...`
- `KopiBot\Contracts\...`
- `KopiBot\Services\...`

Kompatibilitas legacy masih ada di beberapa titik transisi, tetapi baseline repo sekarang diarahkan ke runtime Composer.

## Cara Kerja Sistem

Secara sederhana, alurnya seperti ini:

1. Produk, promo, FAQ, dan pengaturan bisnis disimpan di database.
2. Customer masuk lewat web, API, atau channel seperti WhatsApp/Telegram.
3. Chatbot atau UI membantu customer memilih produk, membuat cart, lalu checkout.
4. Order diproses ke payment, delivery, CRM, loyalty, dan plugin terkait.
5. Dashboard dipakai admin untuk operasional, monitoring, konten, dan konfigurasi plugin.

Komponen penting di balik alur ini:

- katalog dan pencarian produk
- cart dan checkout
- order dan payment
- customer profile dan CRM event
- loyalty dan promo
- FAQ dan response engine
- channel messaging
- plugin integration layer

## Apps / Modul Bisnis

Di repo ini, "apps" lebih tepat dibaca sebagai modul aplikasi atau kapabilitas bisnis yang bisa dipakai bersama-sama.

Modul inti:

- `Catalog`
  Mengelola produk, kategori, varian, topping, harga, dan pencarian.
- `Cart & Checkout`
  Menangani add-to-cart, session cart, checkout, dan pembentukan order.
- `Order`
  Menyimpan order, item order, status order, dan alur pasca-checkout.
- `Payment`
  Menangani provider payment dan plugin gateway seperti Midtrans, Xendit, iPaymu, Nicepay.
- `Customer & CRM`
  Menyimpan customer, histori interaksi, dan event CRM.
- `Loyalty`
  Menangani poin, transaksi loyalty, dan reward.
- `Promo`
  Menangani promo, validasi promo, dan personalisasi rekomendasi promo.
- `FAQ & Chatbot`
  Menjawab pertanyaan umum, intent routing, dan percakapan berbasis AI/rule.
- `Channel Messaging`
  Menghubungkan WhatsApp, Telegram, Discord, Instagram DM, dan channel lain.
- `Delivery & Connector`
  Menghubungkan GoSend, RajaOngkir, KiriminAja, Moka, SIRCLO, dan connector lain.
- `Content & CMS`
  Modul seperti CMS berita, About Us AI, promo CMS, dan content generator.

## Bisnis Proses

### 1. Discovery dan Konversi

- Customer menemukan brand lewat web, chat, referral, atau campaign.
- Customer melihat produk, promo, FAQ, atau rekomendasi chatbot.
- Customer membuat cart dan checkout.

### 2. Pembayaran dan Fulfillment

- Order dibentuk.
- Payment provider memproses pembayaran.
- Status order diperbarui.
- Jika perlu, plugin delivery atau connector eksternal ikut memproses order.

### 3. Retention

- CRM mencatat aktivitas customer.
- Loyalty memberi poin atau reward.
- Promo engine dan chatbot membantu repeat order.

### 4. Operasional Admin

- Admin mengatur produk, promo, konten, channel, dan plugin.
- Admin memantau order, payment, delivery, CRM, dan AI activity.

## Dashboard dan Login

### Siapa yang Login

Peran utama yang umum di repo ini:

- `super_admin`
  Mengelola aplikasi secara global: konfigurasi sistem, plugin, integrasi, branch, dan runtime.
- `branch_admin`
  Mengelola operasional per cabang: produk, order, promo, channel, konten, dan monitoring cabang.
- `customer`
  Tidak memakai dashboard admin, tetapi berinteraksi lewat storefront, API, atau channel chat.
- `affiliate` atau role/plugin khusus
  Beberapa plugin menambah portal atau flow login sendiri, misalnya affiliate.

### Dashboard Dipakai Untuk Apa

- `Super Admin Dashboard`
  Untuk owner, admin pusat, atau tim implementasi.
  Fokus:
  - aktivasi plugin
  - pengaturan global
  - branch dan tenant
  - connector dan payment runtime
  - audit dan monitoring

- `Branch Admin Dashboard`
  Untuk operator toko/cabang.
  Fokus:
  - produk dan katalog
  - order harian
  - promo dan loyalty
  - CRM dan customer
  - chatbot, FAQ, konten, dan integrasi cabang

### Entry Login

Entry login dapat berbeda tergantung flow lama vs runtime baru, tetapi secara praktik:

- login admin dipakai untuk masuk dashboard
- API login dipakai untuk token JWT
- beberapa plugin punya login atau portal sendiri

Contoh endpoint API login:

```text
POST /api/auth/login
```

## Struktur Folder

```text
.
├── bootstrap.php
├── composer.json
├── config/
│   ├── helpers.php
│   └── runtime.php
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── *.sql
├── docs/
├── plugins/
├── public/
│   ├── api/
│   ├── dashboard/
│   ├── install.php
│   └── webhooks/
├── src/
│   ├── Contracts/
│   ├── Core/
│   ├── Domains/
│   ├── Channels/
│   └── Services/
└── tests/
```

## Tech Stack

- PHP `^8.1`
- Composer
- MySQL
- PDO
- `vlucas/phpdotenv`
- Guzzle
- Monolog
- Firebase JWT
- PHPUnit
- PHPStan
- PHP CS Fixer

## Instalasi Lokal

```bash
git clone https://github.com/kukuhtw/toko_kopi.git
cd toko_kopi
composer install
cp .env.example .env
```

Isi `.env` minimal:

```env
APP_NAME=KopiBot
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kopibot
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=change-this-secret-in-production
JWT_TTL_SECONDS=86400
```

## Database

### Jalur Utama

Gunakan migration runner:

```bash
composer migrate
```

atau:

```bash
php migrate.php
```

Jika ingin seed baseline demo:

```bash
php seed.php
```

### Jalur Web Installer

Installer tersedia di:

```text
public/install.php
```

Installer web sekarang sudah:

- memakai runtime Composer untuk template/plugin modern
- memakai `database/migrations/` bila tersedia
- melanjutkan ke `database/seeders/` bila tersedia
- fallback ke `schema.sql` dan `seed.sql` hanya jika migration runner tidak tersedia

Checklist validasi installer:

```text
tests/smoke_web_installer.md
```

## Menjalankan Aplikasi

```bash
composer serve
```

atau:

```bash
php -S localhost:8000 -t public
```

Health check:

```bash
curl http://localhost:8000/api/health
```

## REST API

Entry point API:

```text
public/api/index.php
```

Endpoint inti:

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/api/health` | Cek status API |
| `POST` | `/api/auth/register` | Register user |
| `POST` | `/api/auth/login` | Login dan token JWT |
| `GET` | `/api/products` | List / search produk |
| `POST` | `/api/cart/add` | Tambah item ke cart |
| `POST` | `/api/cart/checkout` | Checkout cart |
| `POST` | `/api/chatbot/message` | Kirim pesan ke chatbot |

Dokumentasi kontrak API:

```text
docs/openapi.yaml
```

## Webhook dan Channel

Webhook WhatsApp tersedia di:

```text
public/webhooks/whatsapp.php
```

Komponen terkait:

```text
src/Channels/WhatsApp/FonnteGateway.php
src/Channels/WhatsApp/WhatsAppWebhookService.php
src/Channels/WhatsApp/WhatsAppGatewayInterface.php
```

## Plugin System

Plugin aktif disimpan di:

```text
plugins/plugins.json
```

Plugin modern di repo ini umumnya:

- mengimplementasikan `KopiBot\Contracts\PluginInterface`
- memakai `KopiBot\Core\HookManager`
- memakai `KopiBot\Core\DatabaseConnection`
- menambahkan nav/settings/action lewat hook

Contoh kategori plugin:

- payment gateway
- delivery
- connector marketplace / POS
- CRM / loyalty / promo
- channel messaging
- content / AI tools

## Testing dan Verifikasi

Smoke test tersedia di folder `tests/`.

Contoh:

```bash
composer smoke:core
composer smoke:auth
composer smoke:chatbot
composer smoke:migration
composer smoke:plugin
composer smoke:payment
composer smoke:api
```

Atau jalankan semuanya:

```bash
composer smoke:all
```

Checklist manual tambahan:

- `tests/smoke_web_installer.md`
- `tests/smoke_affiliate_marketing.md`
- `tests/smoke_about_us_ai.md`

## Quality Tools

```bash
composer test
composer analyse
composer cs-fix
```

## Status Saat Ini

Secara praktis, baseline repo sekarang adalah:

- arsitektur Composer sudah aktif
- installer web sudah lebih selaras dengan runtime baru
- plugin penting banyak yang sudah dimigrasikan ke kontrak baru
- beberapa plugin masih berupa scaffold atau placeholder dan belum integrasi real end-to-end

Area yang biasanya masih perlu verifikasi manual:

- parity install web vs CLI migration
- plugin scaffold seperti content AI atau connector baru
- flow admin dashboard dan plugin settings
- integrasi provider eksternal nyata

## Maintainer

Dibuat dan dikembangkan oleh Kukuh TW.

- Email: `kukuhtw@gmail.com`
- WhatsApp: `https://wa.me/628129893706`
- GitHub: `https://github.com/kukuhtw/toko_kopi`
