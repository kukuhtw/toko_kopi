# Panduan Instalasi KopiBot

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

Panduan ini mencakup dua cara instalasi: **Web Installer** dan **Manual**. Walaupun nama folder dan database default masih menggunakan `toko_kopi`, aplikasi sudah diarahkan menjadi platform AI Agent Commerce multi-vertical. Satu codebase dapat dipakai untuk coffee shop, cafe, restoran, bakery, toko buah, fresh meat market, toko sayur, pharmacy, mini mart, retail mart, dan specialty store.

Dokumentasi ini juga menandai komponen yang saat ini aktif secara default, termasuk plugin `loyalty-point`, `customer-crm`, portal customer self-service, serta plugin pendukung commerce seperti payment gateway, channel chat, POS connector, delivery connector, FAQ RAG, dan complaint handling.

---

## Persyaratan Sistem

| Komponen | Versi Minimum |
|----------|---------------|
| PHP | 8.0+ |
| MySQL | 5.7+ / MariaDB 10.3+ |
| Apache | dengan `mod_rewrite` aktif |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `json`, `fileinfo` |

XAMPP sudah memenuhi semua persyaratan di atas untuk development lokal.

---

## Cara 1 - Web Installer

Web Installer menangani pembuatan database, konfigurasi `.env`, dan akun admin secara otomatis dalam 5 langkah wizard.

### Langkah-langkah

**1. Salin folder proyek ke `htdocs`**

```text
C:\xampp\htdocs\toko_kopi\
```

Nama folder boleh tetap `toko_kopi` untuk kompatibilitas awal. Untuk white-label atau vertical bisnis lain, nama folder bisa diganti menjadi nama brand, misalnya `ai_commerce`, `pharmacy_agent`, atau `mart_agent`.

**2. Buka Web Installer di browser**

```text
http://localhost/toko_kopi/public/install.php
```

**3. Ikuti 5 langkah wizard**

- Langkah 1 - Cek Persyaratan: wizard memeriksa ekstensi PHP dan permission folder.
- Langkah 2 - Konfigurasi Database: isi host, nama database, username, dan password MySQL.
- Langkah 3 - Konfigurasi Aplikasi: isi `BASE_URL` dan pilih environment (`development` atau `production`).
- Langkah 4 - Import Skema: wizard mengimpor `database/schema.sql` dan `database/seed.sql`.
- Langkah 5 - Selesai: file `.env` dibuat dan akun default siap digunakan.

> Catatan: schema dasar diimpor dari `database/schema.sql`. Beberapa plugin juga membawa schema sendiri, misalnya `plugins/customer-crm/schema.sql`, yang akan dipastikan saat plugin dimuat aplikasi.

**4. Hapus file installer setelah selesai**

```text
Hapus: public/install.php
```

File ini harus dihapus sebelum aplikasi dipakai di production untuk mencegah akses tidak sah.

---

## Cara 2 - Instalasi Manual

### Langkah 1 - Salin Folder ke XAMPP

Salin atau ekstrak folder proyek ke:

```text
C:\xampp\htdocs\toko_kopi\
```

### Langkah 2 - Buat Database

**Via phpMyAdmin**

1. Buka `http://localhost/phpmyadmin`
2. Buat database baru bernama `toko_kopi`
3. Pilih database `toko_kopi`, lalu buka tab **Import**
4. Import `database/schema.sql`
5. Import `database/seed.sql`

**Via CLI MySQL**

```bash
mysql -u root -p -e "CREATE DATABASE toko_kopi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p toko_kopi < database/schema.sql
mysql -u root -p toko_kopi < database/seed.sql
```

> Nama database boleh diganti mengikuti brand atau vertical bisnis, misalnya `ai_commerce_pharmacy`, `ai_commerce_mart`, atau `ai_commerce_bakery`. Pastikan nilai `DB_NAME` di `.env` sama dengan database yang dibuat.

> Jika plugin tertentu membutuhkan schema tambahan, aplikasi akan melakukan bootstrap schema plugin saat plugin aktif. Untuk `customer-crm`, referensinya ada di `plugins/customer-crm/schema.sql`.

### Langkah 3 - Konfigurasi `.env`

Salin template konfigurasi:

```bash
copy .env.example .env
```

Buka `.env` lalu sesuaikan nilai berikut:

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=toko_kopi
DB_USER=root
DB_PASS=

APP_ENV=development
BASE_URL=http://localhost/toko_kopi/public
```

LLM API key tidak diisi di `.env`, tetapi dikelola lewat dashboard Super Admin agar bisa dikonfigurasi per deployment.

---

## Konfigurasi Business Vertical Setelah Instalasi

Setelah instalasi selesai, admin dapat menyesuaikan aplikasi sesuai jenis bisnis:

| Vertical | Konfigurasi Awal yang Disarankan |
|----------|----------------------------------|
| Coffee shop / cafe | Aktifkan template coffee, topping, variant size, promo, loyalty, payment gateway, delivery |
| Bakery / kuliner | Aktifkan template bakery, katalog produk, promo bundle, loyalty, customer portal |
| Fruit store / fresh market | Aktifkan template fruit, meat, veggie, delivery, customer CRM, promo harian |
| Pharmacy | Gunakan katalog produk kesehatan, FAQ RAG, complaint handler, customer CRM, payment gateway, delivery |
| Mini mart / retail mart | Gunakan katalog banyak item, promo engine, POS connector, payment gateway, customer portal |

Plugin dapat diaktifkan melalui `plugins/plugins.json` atau lewat mekanisme dashboard bila sudah tersedia pada deployment terkait.

---

## Catatan Production

Untuk production, pastikan:

- `public/install.php` sudah dihapus.
- `.env` tidak masuk ke repository publik.
- Payment gateway memakai credential production yang benar.
- Delivery connector memakai endpoint partner yang sudah disetujui.
- POS connector seperti Moka atau integrasi lain sudah melalui UAT.
- Data customer, order, dan loyalty dilindungi dengan akses role-based.
- Pharmacy dan mart sebaiknya memiliki validasi katalog, kebijakan produk, dan SOP operasional internal sebelum go-live.
