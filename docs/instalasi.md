# Panduan Instalasi AI Agent Commerce

> ## AI Agent Commerce Platform
> Platform AI untuk otomatisasi order, customer service, loyalty customer, Customer CRM, Customer Portal, dan manajemen multi cabang untuk berbagai bisnis seperti kuliner, bakery, pharmacy, mart, fresh market, dan retail.
>
> Dibuat dan dikembangkan oleh: **Kukuh TW**
>
> Email: `kukuhtw@gmail.com`
> WhatsApp: `https://wa.me/628129893706`
> Instagram: `@kukuhtw`
> X/Twitter: `@kukuhtw`
> Facebook: `https://www.facebook.com/kukuhtw`
> LinkedIn: `https://linkedin.com/in/kukuhtw`
> Demo: `https://botlelang.com/toko_kopi`
>
> Copyright 2026 Kukuh TW. All rights reserved.

Panduan ini mencakup dua cara instalasi: **Web Installer** (direkomendasikan) dan **Manual**. Satu codebase mendukung berbagai jenis bisnis: coffee shop, cafe, restoran, bakery, toko buah, fresh meat market, toko sayur, pharmacy/apotek, mini mart, retail mart, dan specialty store.

---

## Persyaratan Sistem

| Komponen | Versi Minimum |
|----------|---------------|
| PHP | 8.0+ |
| MySQL | 5.7+ / MariaDB 10.3+ |
| Apache | dengan `mod_rewrite` aktif |
| Ekstensi PHP | `pdo`, `pdo_mysql`, `mbstring`, `json`, `curl`, `openssl` |

> **XAMPP** sudah memenuhi semua persyaratan di atas untuk development lokal.
> Web Installer akan memverifikasi semua persyaratan secara otomatis di Langkah 1.

---

## Cara 1 — Web Installer (Direkomendasikan)

Web Installer menangani pembuatan database, pengisian produk, konfigurasi `.env`, plugin, dan akun admin dalam **6 langkah wizard** tanpa perlu edit file manual.

### Langkah-langkah

**1. Salin folder proyek ke `htdocs`**

```text
C:\xampp\htdocs\toko_kopi\
```

Nama folder boleh tetap `toko_kopi` atau diganti sesuai brand, misalnya `apotek_agent`, `mart_commerce`, `ai_commerce`.

**2. Buka Web Installer di browser**

```text
http://localhost/toko_kopi/public/install.php
```

**3. Ikuti 6 langkah wizard**

| Langkah | Nama | Keterangan |
|---------|------|------------|
| 1 | Persyaratan Sistem | Wizard memeriksa versi PHP, ekstensi, dan izin folder secara otomatis. |
| 2 | Konfigurasi Database | Isi host, port, nama database, username, dan password MySQL. Database dibuat otomatis jika belum ada. |
| 3 | Pengaturan Aplikasi | Isi nama brand, icon emoji, tagline bisnis, Base URL, dan pilih environment (`development` / `production`). Terdapat preview live tampilan sidebar. |
| 4 | Akun Super Admin | Isi nama, email, dan password akun super admin pertama. |
| 5 | Template Produk & Plugin | Pilih template data produk sesuai jenis bisnis dan centang plugin yang ingin diaktifkan. |
| 6 | Jalankan Instalasi | Tampilkan ringkasan konfigurasi, lalu jalankan instalasi dengan satu klik. |

**4. Setelah instalasi selesai**

Web Installer membuat file `storage/installed.lock` sebagai tanda instalasi berhasil. Akses `install.php` selanjutnya akan diblokir otomatis. Untuk instal ulang, hapus file lock tersebut atau akses `install.php?force=1`.

> File `install.php` **tidak perlu dihapus** — sudah dilindungi oleh lock file. Namun untuk production, menghapusnya adalah praktik terbaik.

---

## Template Produk yang Tersedia

Di Langkah 5, pilih template produk yang paling mendekati jenis bisnis:

| Template | Jumlah Produk | Contoh Produk |
|----------|--------------|---------------|
| **Default Seed Coffee Menu** | ~30 | Espresso, Americano, Cappuccino, Latte |
| **Coffee Shop Template** | 132 | Kopi panas/dingin, cemilan, paket hemat, dessert |
| **Bakery Template** | 70 | Roti tawar, croissant, donat, cake slice, pastry |
| **Fruit Store Template** | 60 | Apel, jeruk, pisang, jus mangga, salad buah |
| **Meat & Veggie Template** | 80 | Daging sapi, ayam fillet, ikan, brokoli, bayam |
| **Pharmacy / Apotek Template** | 120 | Paracetamol, Vitamin C, Amoxicillin, tensimeter |
| **Minimarket Template** | 120 | Beras, Indomie, Aqua, Chitato, sabun, deterjen |
| **Resto Indonesia Template** | 125 | Nasi goreng, soto ayam, rendang, ayam bakar |
| **Warung Makan Template** | 15 | Nasi goreng, ayam goreng, tempe, tahu, es teh, kopi tubruk |
| **Resto Baso & Minuman Template** | 15 | Bakso urat, bakso telur, mie spesial, pangsit goreng, es campur |
| **Kebab Template** | 15 | Kebab original, kebab mozarella, shawarma ayam, pita falafel, milkshake |
| **Burger Template** | 15 | Burger classic beef, burger BBQ smoky, french fries, onion ring, milkshake |
| **Toko Aksesori & Casing HP Template** | 80 | Soft case, tempered glass, charger 33W, TWS earbuds, power bank, ring stand |
| **Toko Baju Busana Wanita Template** | 80 | Blouse rayon, jeans skinny, midi dress, blazer, gamis syari, tas tote bag |

> Setelah instalasi, produk dapat ditambah, diubah, atau dihapus kapan saja via dashboard admin. Template dapat di-reset ulang dari halaman plugin terkait.

---

## Plugin yang Tersedia di Langkah 5

| Kategori | Plugin |
|----------|--------|
| **Payment Gateway** | Midtrans, Xendit, iPaymu, Nicepay |
| **Channel Chat** | WhatsApp (Fonnte, Baileys, Twilio, Vonage, MessageBird), Telegram, Discord |
| **Delivery** | GoSend, RajaOngkir, KiriminAja |
| **POS Connector** | Moka Connect / Private Solution |
| **E-Commerce Connector** | SIRCLO Full Connector |
| **CRM & Loyalty** | Customer CRM, Loyalty Point |
| **Layanan Pelanggan** | FAQ RAG & Complaints, Notifikasi Admin |
| **AI & Marketing** | Upselling, Rekomendasi Promo, Rich Chat UI |
| **Affiliate** | Affiliate Marketing |
| **Toko Berita/Konten** | CMS Berita |

Plugin dapat diaktifkan atau dinonaktifkan kapan saja dari dashboard Super Admin → **Plugins** setelah instalasi.

---

## Cara 2 — Instalasi Manual

### Langkah 1 — Salin Folder ke XAMPP

```text
C:\xampp\htdocs\toko_kopi\
```

### Langkah 2 — Buat Database

**Via phpMyAdmin**

1. Buka `http://localhost/phpmyadmin`
2. Buat database baru (contoh: `toko_kopi`, `ai_commerce_pharmacy`, `ai_commerce_mart`)
3. Pilih database, buka tab **Import**
4. Import `database/schema.sql`
5. Import `database/seed.sql`

**Via CLI MySQL**

```bash
mysql -u root -p -e "CREATE DATABASE toko_kopi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p toko_kopi < database/schema.sql
mysql -u root -p toko_kopi < database/seed.sql
```

> Nama database bisa disesuaikan dengan vertical bisnis: `ai_commerce_pharmacy`, `ai_commerce_mart`, `ai_commerce_bakery`. Pastikan nilai `DB_NAME` di `.env` sama.

### Langkah 3 — Konfigurasi `.env`

Salin template konfigurasi:

```bash
copy .env.example .env
```

Sesuaikan nilai berikut di `.env`:

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=toko_kopi
DB_USER=root
DB_PASS=

APP_ENV=development
BASE_URL=http://localhost/toko_kopi/public
```

> LLM API key (OpenAI/Anthropic) **tidak diisi di `.env`** — dikelola lewat dashboard Super Admin agar bisa dikonfigurasi per deployment.

### Langkah 4 — Seed Produk Template (Opsional)

Setelah skema dan seed dasar diimpor, jalankan seed template produk via dashboard:

1. Login sebagai super admin
2. Buka **Settings → [Nama Template]** (Coffee, Pharmacy, Minimarket, dll.)
3. Klik tombol **Reset & Seed**

Atau aktifkan plugin template di `plugins/plugins.json`:

```json
{
  "pharmacy-template": { "active": true },
  "rich-chat-ui": { "active": true },
  "loyalty-point": { "active": true }
}
```

### Langkah 5 — Buat Akun Super Admin

Akses `install.php?step=4` atau buat manual via MySQL:

```sql
INSERT INTO users (name, email, password, role, is_active)
VALUES ('Super Admin', 'admin@example.com', '$2y$10$...', 'super_admin', 1);
```

> Gunakan `password_hash('password_kamu', PASSWORD_BCRYPT)` di PHP untuk generate hash.

---

## Konfigurasi Business Vertical Setelah Instalasi

| Vertical | Plugin & Konfigurasi yang Disarankan |
|----------|--------------------------------------|
| **Coffee shop / cafe** | Rich Chat UI, coffee template, topping, variant size, loyalty point, promo engine, payment gateway, delivery |
| **Bakery / kuliner** | Bakery template, katalog produk, promo bundle, loyalty point, customer CRM, customer portal |
| **Fruit store / fresh market** | Fruit template atau meat-veggie template, delivery connector, customer CRM, promo harian |
| **Pharmacy / apotek** | Pharmacy template (120 produk), FAQ RAG & complaints, customer CRM, payment gateway, delivery |
| **Mini mart / retail mart** | Minimarket template (120 produk), POS connector, barcode scanner, payment gateway, customer portal |
| **Restoran Indonesia** | Resto Indonesia template, topping/varian, loyalty, delivery, complaint handler |
| **Warung makan / warteg** | Warung Makan template, loyalty point, promo sederhana, customer CRM |
| **Warung baso / kedai mie** | Resto Baso & Minuman template, loyalty point, customer portal, delivery |
| **Kedai kebab / shawarma** | Kebab template, loyalty point, promo, customer CRM, delivery |
| **Kedai burger / fast food** | Burger template, loyalty point, upselling, promo bundle, payment gateway, delivery |
| **Toko aksesori & casing HP** | HP Accessories template, customer CRM, loyalty point, payment gateway |
| **Butik / toko baju wanita** | Fashion Wanita template, loyalty point, customer portal, promo, payment gateway |

---

## Mengelola Plugin Setelah Instalasi

Plugin dikelola via **Super Admin → Plugins**. Secara teknis, daftar plugin aktif tersimpan di `plugins/plugins.json`:

```json
{
  "loyalty-point": { "active": true },
  "customer-crm": { "active": true },
  "rich-chat-ui": { "active": true },
  "midtrans-payment": { "active": false },
  "pharmacy-template": { "active": true }
}
```

Setiap plugin yang aktif dapat menambahkan hook, filter, menu dashboard, tabel database tambahan, dan API endpoint baru tanpa mengubah kode inti aplikasi.

---

## Catatan Production

Untuk environment production, pastikan:

- `public/install.php` sudah dihapus (atau `storage/installed.lock` sudah ada).
- File `.env` tidak masuk ke repository publik — tambahkan ke `.gitignore`.
- `APP_ENV=production` di `.env` (menyembunyikan pesan error detail).
- Payment gateway menggunakan credential production yang benar (bukan sandbox).
- Delivery connector menggunakan endpoint partner yang sudah disetujui.
- POS connector seperti Moka sudah melalui UAT sebelum go-live.
- Data customer, order, dan loyalty dilindungi dengan akses role-based.
- Backup database dijadwalkan secara berkala.
- Untuk pharmacy dan mart: siapkan validasi katalog, kebijakan produk, dan SOP operasional sebelum go-live.
- LLM API key (OpenAI/Anthropic) diisi via dashboard Super Admin → Settings → AI Configuration, bukan di `.env`.
