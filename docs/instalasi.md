# Panduan Instalasi KopiBot

> ## AI Agent Coffee Shop Commerce Platform
> Platform AI untuk otomatisasi order, customer service, loyalty customer, Customer CRM, Customer Portal, dan manajemen multi cabang coffee shop.
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

Panduan ini mencakup dua cara instalasi: **Web Installer** dan **Manual**. Dokumentasi ini juga menandai komponen yang saat ini aktif secara default, termasuk plugin `loyalty-point`, `customer-crm`, dan portal customer self-service.

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

> Catatan: schema dasar diimpor dari `database/schema.sql`. Beberapa plugin juga membawa schema-nya sendiri, misalnya `plugins/customer-crm/schema.sql`, yang akan dipastikan saat plugin dimuat aplikasi.

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

`LLM API Key` tidak diisi di `.env`. Key disimpan di tabel `app_settings` dan dikelola lewat dashboard Super Admin.

### Langkah 4 - Permission Folder

Pastikan folder berikut dapat ditulis oleh web server:

| Folder | Kegunaan |
|--------|----------|
| `uploads/` | Foto menu dan promo yang diunggah |
| `storage/logs/` | PHP error log aplikasi |

Pada Windows + XAMPP, biasanya permission ini sudah cukup karena Apache berjalan dengan user lokal yang sama. Jika ada masalah, beri permission tulis ke folder tersebut.

### Langkah 5 - Verifikasi Apache dan mod_rewrite

1. Buka **XAMPP Control Panel**
2. Pastikan Apache dan MySQL berstatus **Running**
3. Buka `C:\xampp\apache\conf\httpd.conf`
4. Cari baris berikut:

```text
#LoadModule rewrite_module modules/mod_rewrite.so
```

5. Hapus tanda `#` jika masih ada, lalu restart Apache.

### Langkah 6 - Akses Aplikasi

| URL | Keterangan |
|-----|------------|
| `http://localhost/toko_kopi/public/` | Landing page |
| `http://localhost/toko_kopi/public/login.php` | Login admin |
| `http://localhost/toko_kopi/public/chat.php` | Demo chat |
| `http://localhost/toko_kopi/public/order.php?branch={slug}` | Halaman order per cabang |
| `http://localhost/toko_kopi/public/customer/login.php` | Login Customer Portal |
| `http://localhost/toko_kopi/public/customer/` | Overview Customer Portal |

---

## Akun Default

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@tokokopi.com | password |
| Admin Jakarta Selatan | admin.jaksel@tokokopi.com | password |
| Admin Bandung | admin.bandung@tokokopi.com | password |
| Admin Surabaya | admin.surabaya@tokokopi.com | password |

Ganti semua password setelah login pertama.

---

## Verifikasi Instalasi

Setelah instalasi, lakukan pengecekan berikut:

1. Landing page terbuka tanpa error.
2. Login berhasil dengan akun default.
3. Dashboard super admin bisa diakses.
4. Chat demo merespons pesan di `/chat.php`.
5. Menu tampil setelah seed data berhasil diimport.
6. Customer Portal bisa dibuka di `/customer/login.php`.
7. Dashboard branch menampilkan menu `Customer CRM` dan `Loyalty Member` untuk role `branch_admin`.

---

## Instalasi di Server Production

Lihat `.env.prod.example` untuk acuan production. Contoh nilai inti:

```ini
APP_ENV=production
BASE_URL=https://yourdomain.com/toko_kopi/public
DB_PASS=password_yang_kuat
```

Checklist sebelum go-live:

- Hapus `public/install.php`
- Set `APP_ENV=production`
- Ganti semua password akun default
- Pastikan `.env` tidak dapat diakses publik
- Aktifkan HTTPS
- Pastikan `uploads/` dan `storage/logs/` hanya bisa ditulis oleh user yang tepat

---

## Troubleshooting

**Halaman tampil 404 atau tidak ditemukan**  
Aktifkan `mod_rewrite` Apache dan restart Apache.

**Error koneksi database**  
Periksa nilai `DB_HOST`, `DB_USER`, `DB_PASS`, dan pastikan MySQL sedang berjalan.

**Folder `uploads/` tidak bisa ditulis**  
Periksa permission folder dan pastikan Apache punya akses tulis.

**Chat tidak merespons**  
Pastikan `database/seed.sql` berhasil diimport sehingga minimal ada satu cabang dan menu aktif.

**LLM tidak berfungsi**  
Isi API key lewat dashboard Super Admin. Key disimpan di tabel `app_settings`, bukan di `.env`.

**Menu CRM atau loyalty tidak muncul di dashboard branch**  
Pastikan plugin `customer-crm` dan `loyalty-point` aktif di `plugins/plugins.json`.

**Customer Portal tidak bisa login**  
Gunakan email atau WhatsApp yang dipakai saat order, lalu cocokkan dengan nomor order yang valid. Customer Portal memakai verifikasi ringan berbasis data order, bukan akun admin.
