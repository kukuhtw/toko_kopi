# KopiBot - AI Chatbot Order System

> ## AI Agent Coffee Shop Commerce Platform
> Platform AI untuk otomatisasi order, customer service, loyalty customer, Customer CRM, Customer Portal, dan manajemen multi cabang coffee shop.
>
> ### Features
> - AI Chatbot Order Menu
> - WhatsApp / Telegram / Discord Integration
> - Multi Branch Management
> - AI Upselling & Promo Recommendation
> - Order via Website & Chat Apps
> - Variant Product & Topping Support
> - Product Photo Upload & AI Image Generation
> - Loyalty Point, Redeem Point, and Customer CRM
> - Customer Self-Service Dashboard
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
> GitHub    : https://github.com/kukuhtw/toko_kopi
> Facebook  : https://www.facebook.com/kukuhtw
> LinkedIn  : https://linkedin.com/in/kukuhtw
>
> Demo:
> https://botlelang.com/toko_kopi
>
> Copyright 2026 Kukuh TW. All rights reserved.

Sistem chatbot pemesanan kopi berbasis PHP 8 native, tanpa framework besar, dengan satu codebase untuk multi-cabang, multi-channel, multi-bahasa, promo engine, loyalty point, Customer CRM, Customer Portal, dan plugin system.

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
| **Checkout Profile Memory** | Data customer (nama, email, WA, alamat) disimpan di browser, diisi otomatis saat checkout berikutnya — customer hanya perlu memilih metode delivery |
| **Loyalty Point** | Earn point otomatis, cek saldo, redeem point via chatbot dan halaman order web |
| **Promo Engine** | Diskon persen, nominal, promo code, jadwal promo, min order, dan rekomendasi promo |
| **FAQ RAG** | FAQ global + custom per cabang, override branch, import/export CSV/XLS, analytics, dan vector store lokal |
| **Complaint Handling** | Deteksi komplain di flow chat, klasifikasi AI vs human follow-up, dan tiket komplain untuk cabang |
| **Payment Gateway** | Midtrans, Xendit, iPaymu, dan Nicepay via plugin |
| **POS Connector** | Scaffold + live sync queue untuk Moka Connect / Private Solution, inbound webhook sync, dan retry runner |
| **Menu Management** | Upload CSV, variant size/price, topping, override per cabang, upload foto produk, dan generate foto produk dengan AI |
| **Menu Templates** | Plugin template data menu siap pakai: Coffee Shop (132 item), Bakery (70 item), Toko Buah (60 item), Daging & Sayuran (80 item) — dengan seed data, harga IDR + override mata uang per cabang otomatis |
| **Dashboard** | Super admin lintas cabang, branch admin per cabang, Customer CRM, histori loyalty customer, dan Customer Portal self-service |
| **Customer CRM** | Normalisasi identitas customer berbasis email/WhatsApp, notifikasi loyalty, dan log CRM per cabang |
| **Customer Portal** | Login customer ringan via kontak + nomor order untuk cek order history, loyalty, profile, dan repeat order |
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
https://yourbranddomain.com/public/install.php
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
| `https://yourbranddomain.com/public/` | Landing page |
| `https://yourbranddomain.com/public/readme.php` | README versi HTML |
| `https://yourbranddomain.com/public/docs/index.php` | Pusat dokumentasi HTML |
| `https://yourbranddomain.com/public/docs/sirclo-full-connector.php` | Tutorial integrasi SIRCLO |
| `https://yourbranddomain.com/public/docs/payment-gateway-ipaymu.php` | Setup sandbox iPaymu |
| `https://yourbranddomain.com/public/docs/payment-gateway-nicepay.php` | Setup sandbox Nicepay |
| `https://yourbranddomain.com/public/login.php` | Login admin |
| `https://yourbranddomain.com/public/chat.php` | Chat demo |
| `https://yourbranddomain.com/public/order.php?branch={slug}` | Halaman order per cabang |
| `https://yourbranddomain.com/public/customer/login.php` | Login Customer Portal |
| `https://yourbranddomain.com/public/customer/` | Overview Customer Portal |
| `https://yourbranddomain.com/public/customer/orders.php` | Riwayat order customer |
| `https://yourbranddomain.com/public/customer/loyalty.php` | Dashboard loyalty customer |
| `https://yourbranddomain.com/public/customer/profile.php` | Profil dan preferensi customer |

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
|   |-- customer-crm/
|   |-- loyalty-point/
|   |-- midtrans-payment/
|   |-- xendit-payment/
|   |-- ipaymu-payment/
|   |-- nicepay-payment/
|   |-- telegram-channel/
|   |-- discord-channel/
|   |-- fonnte-whatsapp/
|   |-- twilio-whatsapp/
|   |-- vonage-whatsapp/
|   |-- baileys-whatsapp/
|   |-- messagebird-whatsapp/
|   |-- upselling/
|   |-- rekomendasi-promo/
|   |-- cms-berita/
|   |-- sirclo-full-connector/
|   |-- coffee-template/
|   |-- bakery-template/
|   |-- fruit-template/
|   |-- meat-veggie-template/
|   `-- plugins.json
|-- public/
|   |-- index.php
|   |-- readme.php
|   |-- chat.php
|   |-- order.php
|   |-- docs/
|   |-- api/
|   |-- customer/
|   `-- dashboard/
|-- docs/
|   |-- instalasi.md
|   |-- lisensi.md
|   |-- plugin-system.md
|   |-- sirclo-full-connector.md
|   |-- payment-gateway-ipaymu.md
|   |-- payment-gateway-nicepay.md
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

---

## Payment Gateway Sandbox

Proyek ini sekarang punya empat plugin payment gateway utama:

- `midtrans-payment`
- `xendit-payment`
- `ipaymu-payment`
- `nicepay-payment`

Untuk setup sandbox dua gateway baru, gunakan panduan berikut:

- [`docs/payment-gateway-ipaymu.md`](docs/payment-gateway-ipaymu.md)
- [`docs/payment-gateway-nicepay.md`](docs/payment-gateway-nicepay.md)

Ringkasnya:

- `iPaymu` di repo ini memakai flow redirect payment dan webhook internal `notify.php`
- `Nicepay` di repo ini memakai flow `Registration -> Redirect Payment -> DB Process URL`
- keduanya bisa dikonfigurasi per cabang dari dashboard settings
- link bayar akan ikut muncul di checkout web maupun checkout via chat setelah order dibuat

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
- `customer-crm` - normalisasi identitas customer, log CRM, dan notifikasi loyalty ke customer
- `midtrans-payment` - payment gateway Midtrans
- `xendit-payment` - payment gateway Xendit
- `telegram-channel` - channel Telegram
- `discord-channel` - channel Discord
- `fonnte-whatsapp`, `twilio-whatsapp`, `vonage-whatsapp`, `baileys-whatsapp`, `messagebird-whatsapp` - adapter WhatsApp berbasis plugin
- `upselling` - upsell recommendation
- `rekomendasi-promo` - promo recommendation
- `cms-berita` - branch news/content
- `notifikasi-admin` - helper notifikasi/admin mailer support
- `themes` - pengelolaan tema/tampilan
- `instagram-dm` - integrasi DM Instagram
- `complaint-handler` - deteksi komplain, klasifikasi AI vs human, tiket follow-up cabang
- `faq-rag` - FAQ global/cabang dengan retrieval vector lokal, analytics, dan import/export
- `sirclo-full-connector` - fondasi integrasi SIRCLO untuk order, produk, dan customer
- `moka-connect-private-solution` - konektor Moka dengan live push order, pull katalog, retry queue, webhook inbound, simulasi payload, dan mapping UI
- `anthropic-llm`, `gemini-llm`, `openrouter-llm` - provider AI tambahan
- `coffee-template` - template seed 132 menu toko kopi (data dari database asli) lengkap dengan override harga multi-currency per cabang
- `bakery-template` - template seed 70 menu toko bakery + roti dengan harga IDR dan override USD/SGD/AUD otomatis
- `fruit-template` - template seed 60 menu toko buah segar, jus, smoothie, dan salad
- `meat-veggie-template` - template seed 80 menu toko daging & sayuran

Plugin diaktifkan lewat [`plugins/plugins.json`](plugins/plugins.json). Pada state proyek saat ini, `loyalty-point` dan `customer-crm` aktif dan saling terhubung.

---

## Integrasi SIRCLO

Proyek ini sekarang memiliki plugin `sirclo-full-connector` sebagai fondasi integrasi ke SIRCLO.

Cakupan yang sudah tersedia:

- pengaturan koneksi per cabang dan global
- menu dashboard branch dan super admin untuk monitoring
- log sinkronisasi ke tabel `sirclo_sync_logs`
- queue event order untuk `created`, `status_changed`, dan `payment_updated`
- snapshot manual untuk sinkronisasi order, produk, dan customer

Penting:

- versi saat ini masih **scaffold integrasi**
- request HTTP real ke API SIRCLO belum diimplementasikan
- webhook inbound SIRCLO juga belum dibuat

Dokumentasi lengkap:

- [`docs/sirclo-full-connector.md`](docs/sirclo-full-connector.md)
- [`public/docs/sirclo-full-connector.php`](public/docs/sirclo-full-connector.php)

File utama plugin:

- [`plugins/sirclo-full-connector/SircloFullConnectorPlugin.php`](plugins/sirclo-full-connector/SircloFullConnectorPlugin.php)
- [`plugins/sirclo-full-connector/SircloConnectorRepository.php`](plugins/sirclo-full-connector/SircloConnectorRepository.php)
- [`plugins/sirclo-full-connector/SircloConnectorService.php`](plugins/sirclo-full-connector/SircloConnectorService.php)
- [`public/dashboard/branch/sirclo.php`](public/dashboard/branch/sirclo.php)
- [`public/dashboard/super/sirclo.php`](public/dashboard/super/sirclo.php)

---

## FAQ RAG dan Komplain

Proyek ini sekarang juga memiliki dua plugin service layer untuk customer support di flow chat:

- `faq-rag`
- `complaint-handler`

### FAQ RAG

Fitur yang sudah tersedia:

- FAQ global dan FAQ custom per cabang
- branch override terhadap FAQ global tertentu
- retrieval berbasis vector store lokal di database
- import/export `CSV` dan `Excel XML (.xls)`
- analytics FAQ paling sering ditanya dan unmatched query
- starter seed 5 FAQ global bila data masih kosong

File utama:

- [`plugins/faq-rag/FaqRepository.php`](plugins/faq-rag/FaqRepository.php)
- [`plugins/faq-rag/FaqVectorService.php`](plugins/faq-rag/FaqVectorService.php)
- [`plugins/faq-rag/FaqSkill.php`](plugins/faq-rag/FaqSkill.php)
- [`public/dashboard/super/faqs.php`](public/dashboard/super/faqs.php)
- [`public/dashboard/branch/faqs.php`](public/dashboard/branch/faqs.php)

### Complaint Handler

Fitur yang sudah tersedia:

- deteksi intent komplain di flow order/chat
- klasifikasi komplain yang masih bisa dijawab AI vs yang harus di-follow up manusia
- pembuatan tiket komplain ke cabang untuk kasus human follow-up
- dashboard tiket komplain branch

File utama:

- [`plugins/complaint-handler/ComplaintAnalyzer.php`](plugins/complaint-handler/ComplaintAnalyzer.php)
- [`plugins/complaint-handler/ComplaintTicketRepository.php`](plugins/complaint-handler/ComplaintTicketRepository.php)
- [`plugins/complaint-handler/ComplaintSkill.php`](plugins/complaint-handler/ComplaintSkill.php)
- [`public/dashboard/branch/complaints.php`](public/dashboard/branch/complaints.php)

---

## Integrasi Moka Connect / Private Solution

Selain SIRCLO, proyek ini sekarang memiliki plugin `moka-connect-private-solution` untuk fondasi dan live sync integrasi Moka POS.

Cakupan yang sudah tersedia:

- pengaturan koneksi Moka per cabang dan global
- push order live ke endpoint Moka
- pull katalog live dari endpoint Moka
- queue sinkronisasi order dengan retry policy
- status sinkronisasi per order
- webhook inbound untuk sinkron balik ke `order_status` dan `payment_status` internal
- halaman simulasi payload webhook
- mapping UI yang bisa diubah tanpa edit code
- runner otomatis untuk memproses queue via cron / scheduler

Halaman penting:

- [`public/dashboard/branch/moka.php`](public/dashboard/branch/moka.php)
- [`public/dashboard/branch/moka-webhook-test.php`](public/dashboard/branch/moka-webhook-test.php)
- [`public/dashboard/super/moka.php`](public/dashboard/super/moka.php)
- [`public/api/plugins/moka/webhook.php`](public/api/plugins/moka/webhook.php)
- [`public/api/plugins/moka/process-queue.php`](public/api/plugins/moka/process-queue.php)

Catatan:

- integrasi ini sudah bisa request HTTP live, tetapi payload final tetap mungkin perlu penyesuaian mengikuti approval dan dokumentasi Private Solution Moka yang Anda dapatkan

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
- operasi earn/redeem/refund dibungkus transaksi database agar saldo akun dan ledger tetap konsisten
- mutasi loyalty memicu event `loyalty.points_changed` untuk plugin lain seperti `customer-crm`

File utama:

- [`plugins/loyalty-point/LoyaltyPointPlugin.php`](plugins/loyalty-point/LoyaltyPointPlugin.php)
- [`plugins/loyalty-point/LoyaltyPointRepository.php`](plugins/loyalty-point/LoyaltyPointRepository.php)
- [`plugins/loyalty-point/LoyaltyPointSkill.php`](plugins/loyalty-point/LoyaltyPointSkill.php)
- [`public/dashboard/branch/loyalty.php`](public/dashboard/branch/loyalty.php)

---

## Customer CRM

Plugin `customer-crm` menambahkan lapisan Customer CRM yang fokus pada identitas dan komunikasi loyalty.

Fitur utamanya:

- normalisasi customer berbasis email dan WhatsApp
- log notifikasi loyalty ke tabel plugin `crm_notification_logs`
- dashboard branch CRM untuk melihat customer, loyalty, order terakhir, dan histori notifikasi
- backfill satu kali dari histori `loyalty_point_transactions` agar CRM tetap punya jejak event lama
- schema plugin terpisah di [`plugins/customer-crm/schema.sql`](plugins/customer-crm/schema.sql)

File utama:

- [`plugins/customer-crm/CustomerCrmPlugin.php`](plugins/customer-crm/CustomerCrmPlugin.php)
- [`plugins/customer-crm/schema.sql`](plugins/customer-crm/schema.sql)
- [`public/dashboard/branch/crm.php`](public/dashboard/branch/crm.php)

---

## Customer Portal

Selain dashboard admin, proyek ini sekarang memiliki Customer Portal self-service.

Alur login:

- customer masuk lewat `email atau WhatsApp`
- verifikasi ringan memakai `nomor order`
- session customer dipisah dari session admin

Halaman utama:

- [`public/customer/login.php`](public/customer/login.php)
- [`public/customer/index.php`](public/customer/index.php)
- [`public/customer/orders.php`](public/customer/orders.php)
- [`public/customer/loyalty.php`](public/customer/loyalty.php)
- [`public/customer/profile.php`](public/customer/profile.php)
- [`public/customer/order-detail.php`](public/customer/order-detail.php)

Yang bisa dilakukan customer:

- cek ringkasan order dan total belanja
- cek saldo poin dan histori loyalty
- lihat detail order lengkap
- ulang order ke cabang asal dari halaman detail
- akses dashboard customer langsung dari landing page, halaman chat, dan halaman order

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
| `/api/loyalty/status.php` | POST | Cek saldo loyalty customer dan status redeem di cart |
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

Portal customer juga dapat diakses langsung setelah checkout lewat tombol `Dashboard Customer`, dengan prefill kontak dan nomor order terakhir.

### Regression Check

Untuk cek cepat flow entity extraction dan chatbot tanpa browser, jalankan:

```text
php scripts/chat-regression.php
php scripts/chat-regression.php --verbose
php scripts/chat-regression.php --branch=1
```

Script ini menguji skenario umum seperti deskripsi menu, query budget, order dengan varian, order dengan hint harga, dan lookup promo.

---

## Dokumentasi

Versi HTML yang bisa dibuka langsung di browser:

- [`public/readme.php`](public/readme.php)
- [`public/docs/index.php`](public/docs/index.php)
- [`public/docs/instalasi.php`](public/docs/instalasi.php)
- [`public/docs/lisensi.php`](public/docs/lisensi.php)
- [`public/docs/plugin-system.php`](public/docs/plugin-system.php)
- [`public/docs/faq-rag-and-complaints.php`](public/docs/faq-rag-and-complaints.php)
- [`public/docs/moka-connect-private-solution.php`](public/docs/moka-connect-private-solution.php)
- [`public/docs/sirclo-full-connector.php`](public/docs/sirclo-full-connector.php)
- [`public/docs/tutorial-membuat-plugin.php`](public/docs/tutorial-membuat-plugin.php)

Dokumen Markdown sumber:

- [`docs/instalasi.md`](docs/instalasi.md)
- [`docs/lisensi.md`](docs/lisensi.md)
- [`docs/plugin-system.md`](docs/plugin-system.md)
- [`docs/faq-rag-and-complaints.md`](docs/faq-rag-and-complaints.md)
- [`docs/moka-connect-private-solution.md`](docs/moka-connect-private-solution.md)
- [`docs/sirclo-full-connector.md`](docs/sirclo-full-connector.md)
- [`docs/tutorial-membuat-plugin.md`](docs/tutorial-membuat-plugin.md)
- [`docs/customer-agent-architecture.md`](docs/customer-agent-architecture.md)

---

## Keamanan

- Password memakai `password_hash` / `password_verify`
- Query memakai PDO prepared statements
- Form dashboard memakai CSRF token
- RBAC untuk `super_admin` dan `branch_admin`
- Branch admin hanya bisa akses data cabangnya
- Customer portal hanya bisa membuka data yang cocok dengan sesi customer yang sudah diverifikasi
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
