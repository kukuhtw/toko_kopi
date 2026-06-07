# Architecture Overview

## Gambaran Umum

KopiBot adalah platform commerce modular yang menggabungkan:

- katalog produk
- cart dan checkout
- order dan payment
- CRM dan loyalty
- promo dan FAQ
- chatbot dan memory AI
- channel messaging
- delivery dan connector
- plugin bisnis dan konten

Arsitektur saat ini berpusat pada runtime Composer dengan namespace `KopiBot\...`, lalu diperluas lewat plugin yang memakai kontrak modern.

## Tujuan Arsitektur

Arsitektur ini dirancang untuk:

- mendukung banyak jenis bisnis dalam satu codebase
- memisahkan core commerce dari plugin vertikal
- menjaga alur order tetap konsisten walau payment, delivery, atau channel berbeda
- memudahkan onboarding, migrasi, dan verifikasi runtime

## Pilar Sistem

### 1. Core Runtime

Core runtime memegang fondasi aplikasi:

- bootstrap
- load `.env`
- path runtime
- database connection
- router, request, response
- hook manager
- auth, permission, dan exception handling

File penting:

- `bootstrap.php`
- `config/runtime.php`
- `src/Core/*`

### 2. Domain Layer

Layer ini berisi logika bisnis yang lebih stabil:

- product
- cart
- order
- payment
- customer
- CRM
- loyalty
- promo
- FAQ
- auth
- branch
- AI memory / state

Sebisa mungkin perubahan fitur baru diarahkan ke repository dan service di `src/Domains/*`, bukan ditumpuk di entry point publik.

### 3. Public Entry Points

Entry point publik menerima request dari luar:

- `public/api/index.php`
- `public/install.php`
- `public/webhooks/*`
- halaman dashboard lama atau transisi di `public/dashboard/*`

Peran entry point idealnya tipis:

- validasi request
- panggil service/domain
- kembalikan response

### 4. Plugin Layer

Plugin layer memperluas sistem tanpa mengubah core langsung.

Contoh kategori plugin:

- payment gateway
- delivery
- channel messaging
- connector marketplace / POS
- CRM / loyalty / promo
- content / AI tools

Plugin modern umumnya:

- implement `KopiBot\Contracts\PluginInterface`
- register hook lewat `KopiBot\Core\HookManager`
- simpan setting di `plugin_branch_settings` atau `app_settings`
- simpan log/audit di tabel plugin sendiri bila perlu

## Business Process

### Discovery

Customer datang dari:

- storefront
- dashboard internal
- API
- WhatsApp / Telegram / channel lain
- campaign / referral / plugin eksternal

### Intent dan Interaksi

Customer:

- melihat produk
- bertanya lewat chatbot
- menerima jawaban FAQ, promo, atau rekomendasi
- membuat cart

### Checkout

Saat checkout:

- cart dikonversi menjadi order
- payment provider ikut memproses pembayaran
- plugin lain dapat bereaksi pada event order

### Fulfillment

Setelah order:

- CRM event dapat dicatat
- loyalty dapat ditambahkan
- promo dapat ditandai terpakai
- delivery atau connector dapat mengirim order ke pihak ketiga

### Retention

Sesudah transaksi:

- histori customer dipakai untuk CRM
- chatbot dapat membantu repeat order
- promo dan loyalty mendorong pembelian ulang

## Dashboard dan Role

### Super Admin

Dipakai oleh:

- owner pusat
- admin implementasi
- operator sistem

Fokus:

- plugin dan integrasi
- branch / tenant
- runtime global
- audit dan monitoring
- konfigurasi lintas cabang

### Branch Admin

Dipakai oleh:

- operator toko
- manager cabang
- admin operasional harian

Fokus:

- katalog
- order
- promo
- loyalty
- CRM
- chatbot / FAQ / konten
- connector cabang

### Customer

Customer tidak memakai dashboard admin. Mereka masuk lewat:

- storefront
- API
- channel chat
- portal tertentu bila disediakan plugin

### Role Tambahan

Beberapa plugin dapat menambah flow sendiri, misalnya:

- affiliate portal
- portal customer khusus
- dashboard connector tertentu

## Alur Data

Secara ringkas:

1. Request masuk ke entry point publik.
2. Runtime memuat config dan database.
3. Domain service memproses logika inti.
4. Hook dijalankan bila ada event yang relevan.
5. Plugin yang aktif dapat menambah efek samping:
   - sync order
   - kirim notifikasi
   - catat CRM
   - generate content
6. Response dikembalikan ke UI, API, atau channel.

## Database Strategy

Jalur utama sekarang:

- `database/migrations/` untuk schema inti
- `database/seeders/` untuk baseline demo
- `database/*.sql` untuk patch tambahan atau migrasi plugin tertentu

Installer web sudah diarahkan ke migration runner baru, dengan fallback aman ke jalur lama bila runtime Composer tidak tersedia.

## Integrasi Eksternal

Integrasi eksternal biasanya berada di plugin:

- payment gateway
- delivery
- marketplace / POS
- LLM provider
- messaging channel

Prinsip integrasi:

- core order tidak boleh rusak hanya karena integrasi gagal
- log dan audit sebaiknya tersimpan
- setting global dan branch harus dipisah jelas

## Status Arsitektur Saat Ini

Yang sudah kuat:

- Composer runtime
- migration runner
- domain modular
- plugin contract modern
- banyak plugin penting sudah dimigrasikan

Yang masih butuh perhatian:

- beberapa plugin masih scaffold
- beberapa flow dashboard masih transisi
- verifikasi parity install web vs CLI migrate
- integrasi eksternal nyata masih perlu smoke test end-to-end

## Dokumen Terkait

- `README.md`
- `docs/plugin-system.md`
- `docs/composer-migration-status.md`
- `docs/customer-agent-architecture.md`
- `tests/smoke_web_installer.md`
