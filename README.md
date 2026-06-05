# KopiBot MVP Core

KopiBot adalah fondasi **AI Agent Commerce Platform** berbasis PHP native yang sedang dimigrasikan ke arsitektur Composer secara bertahap. Branch `composer-mvp-core` berisi versi MVP core terbaru dengan autoload Composer, konfigurasi `.env`, migration runner, seed demo, REST API, JWT auth, domain service modular, WhatsApp webhook, smoke test, dan plugin payment sederhana.

Repo ini masih mempertahankan arah awal sebagai sistem order coffee shop, tetapi struktur terbaru sudah diperluas menjadi fondasi commerce modular untuk produk, cart, order, customer, CRM, loyalty, promo, FAQ, chatbot, payment, tenant, branch, dan integrasi channel.

## Status Branch

Branch aktif: `composer-mvp-core`

Status utama branch ini:

- Composer foundation sudah tersedia.
- Bootstrap utama sudah tersedia melalui `bootstrap.php`.
- `.env.example` sudah tersedia.
- REST API tersedia di `public/api/index.php`.
- Database migration tersedia di `database/migrations/`.
- Demo seed tersedia di `database/seeders/`.
- Smoke test tersedia di `tests/`.
- Dokumentasi OpenAPI tersedia di `docs/openapi.yaml`.
- Webhook WhatsApp tersedia di `public/webhooks/whatsapp.php`.

## Tech Stack

- PHP `^8.1`
- Composer
- MySQL
- PDO
- Dotenv `vlucas/phpdotenv`
- Guzzle HTTP
- Monolog
- Firebase PHP JWT
- PHPUnit
- PHPStan
- PHP CS Fixer

## Struktur Folder Utama

```text
.
├── bootstrap.php
├── composer.json
├── config/
│   └── helpers.php
├── database/
│   ├── migrations/
│   └── seeders/
├── docs/
│   ├── openapi.yaml
│   └── whatsapp-webhook.md
├── plugins/
│   └── Payment/
├── public/
│   ├── api/
│   │   └── index.php
│   └── webhooks/
│       └── whatsapp.php
├── src/
│   ├── Channels/
│   ├── Core/
│   └── Domains/
└── tests/
```

## Fitur Utama MVP

| Area | Fitur |
|---|---|
| Core | Router, Request, Response, Config, Database, Logger, Exception Handler |
| Auth | Register, login, JWT service, password hashing, role permission service |
| Tenant & Branch | Tenant context, branch context, branch repository, branch service |
| Product | Product repository, product service, product search |
| Cart | Add item, cart service, cart repository, checkout flow |
| Order | Order DTO, order repository, order service, order status |
| Payment | Payment DTO, payment service, payment repository, payment provider interface, mock payment provider |
| Customer | Customer DTO, customer repository, customer service |
| CRM | CRM event DTO, repository, service |
| Loyalty | Loyalty rule, loyalty service, transaction DTO, transaction type |
| Promo | Promo DTO, validator, result, service, repository |
| FAQ | FAQ repository, FAQ service, keyword search |
| Chatbot | Intent detector, message router, cart intent parser, response builder, rule based chatbot |
| AI Commerce | Commerce agent, intent extractor, product resolver, recommendation engine, conversation memory/state |
| Channel | WhatsApp gateway interface, Fonnte gateway, incoming/outgoing DTO, webhook service |
| Migration | Migration runner dan SQL migration berurutan |
| Testing | Smoke test untuk core, auth, product, cart, order, payment, promo, loyalty, CRM, FAQ, chatbot, conversation state |

## Composer Autoload

`composer.json` memakai PSR-4:

```json
{
  "autoload": {
    "psr-4": {
      "KopiBot\\": "src/",
      "KopiBot\\Plugins\\": "plugins/"
    },
    "files": [
      "config/helpers.php"
    ]
  }
}
```

Setelah mengubah autoload, jalankan:

```bash
composer dump-autoload
```

## Instalasi Lokal

```bash
git clone https://github.com/kukuhtw/toko_kopi.git
cd toko_kopi
git checkout composer-mvp-core
composer install
cp .env.example .env
```

Edit `.env` sesuai database lokal:

```env
APP_NAME=KopiBot
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kopibot
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=change-this-secret-in-production
JWT_TTL_SECONDS=86400

FONNTE_TOKEN=
FONNTE_SEND_URL=https://api.fonnte.com/send
```

## Migration dan Seeder

Jalankan migration:

```bash
composer migrate
```

Atau langsung:

```bash
php migrate.php
```

Jalankan demo seed:

```bash
php seed.php
```

## Menjalankan Server

```bash
composer serve
```

Atau:

```bash
php -S localhost:8000 -t public
```

Health check:

```bash
curl http://localhost:8000/api/health
```

Response:

```json
{
  "success": true,
  "service": "KopiBot API",
  "status": "ok"
}
```

## Endpoint REST API MVP

Entry point API ada di:

```text
public/api/index.php
```

Endpoint utama:

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/health` | Cek status API |
| POST | `/api/auth/register` | Register user |
| POST | `/api/auth/login` | Login dan mendapatkan token |
| GET | `/api/products` | Ambil produk atau cari produk |
| POST | `/api/cart/add` | Tambah item ke cart, butuh JWT |
| POST | `/api/cart/checkout` | Checkout cart, butuh JWT |
| POST | `/api/chatbot/message` | Proses pesan chatbot |

Dokumentasi kontrak API tersedia di:

```text
docs/openapi.yaml
```

## Contoh API

### Health Check

```bash
curl http://localhost:8000/api/health
```

### Register

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": 1,
    "name": "Admin",
    "email": "admin@example.com",
    "password": "secret123",
    "role": "merchant_admin"
  }'
```

### Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": 1,
    "email": "admin@example.com",
    "password": "secret123"
  }'
```

### Product List

```bash
curl "http://localhost:8000/api/products?tenant_id=1&branch_id=1"
```

### Product Search

```bash
curl "http://localhost:8000/api/products?tenant_id=1&branch_id=1&q=kopi"
```

### Add Cart Item

```bash
curl -X POST http://localhost:8000/api/cart/add \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "branch_id": 1,
    "customer_id": 1,
    "session_id": "api-session",
    "product_id": 1,
    "product_name": "Cappuccino",
    "qty": 2,
    "price": 25000
  }'
```

### Checkout

```bash
curl -X POST http://localhost:8000/api/cart/checkout \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "branch_id": 1,
    "customer_id": 1,
    "session_id": "api-session",
    "customer_name": "Budi",
    "customer_email": "budi@example.com",
    "customer_phone": "628129893706"
  }'
```

### Chatbot Message

```bash
curl -X POST http://localhost:8000/api/chatbot/message \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": 1,
    "branch_id": 1,
    "channel": "api",
    "sender_id": "guest-1",
    "message": "Saya mau pesan cappuccino 2"
  }'
```

## WhatsApp Webhook

Webhook WhatsApp tersedia di:

```text
public/webhooks/whatsapp.php
```

Service yang terkait:

```text
src/Channels/WhatsApp/FonnteGateway.php
src/Channels/WhatsApp/WhatsAppWebhookService.php
src/Channels/WhatsApp/WhatsAppGatewayInterface.php
src/Channels/WhatsApp/IncomingMessageDTO.php
src/Channels/WhatsApp/OutgoingMessageDTO.php
```

Dokumentasi webhook:

```text
docs/whatsapp-webhook.md
```

## Docker Quick Start

```bash
docker compose up -d
```

Setelah container database aktif, tetap jalankan Composer install, migration, dan seeder di environment PHP yang dipakai:

```bash
composer install
composer migrate
php seed.php
```

## Smoke Test

Composer script yang tersedia:

```bash
composer smoke:core
composer smoke:auth
composer smoke:chatbot
```

Smoke test lain tersedia di folder `tests/`, misalnya:

```bash
php tests/smoke_product.php
php tests/smoke_cart.php
php tests/smoke_order.php
php tests/smoke_payment.php
php tests/smoke_promo.php
php tests/smoke_loyalty.php
php tests/smoke_crm.php
php tests/smoke_faq.php
php tests/smoke_conversation_state.php
```

## Quality Tools

Jalankan PHPUnit:

```bash
composer test
```

Jalankan PHPStan:

```bash
composer analyse
```

Jalankan PHP CS Fixer:

```bash
composer cs-fix
```

Catatan: `phpunit.xml` belum tersedia pada status issue terakhir. Jika `composer test` belum berjalan, tambahkan `phpunit.xml` lebih dulu.

## Plugin dan Compatibility

Branch ini memakai Composer namespace baru `KopiBot\Plugins\` untuk plugin di folder `plugins/`.

Sistem plugin lama berbasis:

```text
app/Plugin/PluginLoader.php
app/Plugin/HookManager.php
app/Plugin/PluginInterface.php
plugins/plugins.json
```

masih dapat dipertahankan selama file lama tersebut tetap ada dan bootstrap lama masih memanggilnya.

Untuk backward compatibility jangka panjang, disarankan menambahkan mapping berikut ke Composer autoload jika kode lama `App\...` masih dipakai:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

## Catatan Issue #8

Sesuai Issue #8, beberapa item Composer foundation sudah selesai, tetapi masih ada beberapa pekerjaan lanjutan yang perlu dirapikan:

- Review `.gitignore` agar `.env` dan `/vendor/` tidak ikut commit.
- Buat `src/App.php` jika ingin entry point aplikasi formal.
- Pastikan semua entry point memakai `vendor/autoload.php` melalui `bootstrap.php`.
- Pastikan `.env` sepenuhnya dimuat melalui `vlucas/phpdotenv`.
- Tambahkan `phpunit.xml`.
- Tambahkan unit test sederhana untuk App atau Database config.

## Maintainer

Dibuat dan dikembangkan oleh Kukuh TW.

- Email: kukuhtw@gmail.com
- WhatsApp: https://wa.me/628129893706
- GitHub: https://github.com/kukuhtw/toko_kopi
