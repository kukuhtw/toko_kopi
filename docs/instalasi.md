# Panduan Instalasi KopiBot

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

Panduan ini mencakup dua cara instalasi: **Web Installer** (direkomendasikan) dan **Manual**.

---

## Persyaratan Sistem

| Komponen | Versi Minimum |
|----------|--------------|
| PHP | 8.0+ |
| MySQL | 5.7+ / MariaDB 10.3+ |
| Apache | dengan `mod_rewrite` aktif |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `json`, `fileinfo` |

XAMPP sudah memenuhi semua persyaratan di atas.

---

## Cara 1 — Web Installer (Direkomendasikan)

Web Installer menangani pembuatan database, konfigurasi `.env`, dan akun admin secara otomatis dalam 5 langkah wizard.

### Langkah-langkah

**1. Salin folder proyek ke htdocs**

```
C:\xampp\htdocs\toko_kopi\
```

**2. Buka Web Installer di browser**

```
http://localhost/toko_kopi/public/install.php
```

**3. Ikuti 5 langkah wizard:**

- **Langkah 1 — Cek Persyaratan:** wizard memeriksa ekstensi PHP dan permission folder secara otomatis.
- **Langkah 2 — Konfigurasi Database:** masukkan host, nama database, username, dan password MySQL.
- **Langkah 3 — Konfigurasi Aplikasi:** isi `BASE_URL` dan pilih environment (`development` / `production`).
- **Langkah 4 — Import Skema:** wizard mengimpor `database/schema.sql` dan `database/seed.sql` otomatis.
- **Langkah 5 — Selesai:** file `.env` dibuat, akun default siap digunakan.

**4. Hapus file installer setelah selesai**

```
Hapus: public/install.php
```

> File ini harus dihapus sebelum aplikasi digunakan di production untuk mencegah akses tidak sah.

---

## Cara 2 — Instalasi Manual

### Langkah 1 — Salin Folder ke XAMPP

Salin atau ekstrak folder proyek ke:

```
C:\xampp\htdocs\toko_kopi\
```

### Langkah 2 — Buat Database

**Via phpMyAdmin:**

1. Buka `http://localhost/phpmyadmin`
2. Buat database baru bernama `toko_kopi`
3. Pilih database `toko_kopi` → klik tab **Import**
4. Import `database/schema.sql` (struktur tabel)
5. Import `database/seed.sql` (data awal: cabang, menu, akun)

**Via CLI MySQL:**

```bash
mysql -u root -p -e "CREATE DATABASE toko_kopi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p toko_kopi < database/schema.sql
mysql -u root -p toko_kopi < database/seed.sql
```

### Langkah 3 — Konfigurasi `.env`

Salin template konfigurasi:

```bash
# Windows
copy .env.example .env
```

Buka `.env` dan sesuaikan nilai berikut:

```ini
# ── Database ──────────────────────────────────────────────────
DB_HOST=localhost
DB_PORT=3306
DB_NAME=toko_kopi
DB_USER=root
DB_PASS=               ← kosong jika XAMPP default

# ── Aplikasi ──────────────────────────────────────────────────
APP_ENV=development    ← ganti ke "production" saat deploy

# URL publik tanpa trailing slash
BASE_URL=http://localhost/toko_kopi/public
```

> **LLM API Key tidak diisi di `.env`.** Key disimpan di tabel `app_settings` dan dikelola lewat dashboard: **Super Admin → Settings → AI Configuration**.

### Langkah 4 — Permission Folder

Pastikan folder berikut dapat ditulis oleh web server:

| Folder | Kegunaan |
|--------|---------|
| `uploads/` | Foto menu dan promo yang diunggah |
| `storage/logs/` | PHP error log aplikasi |

**Windows XAMPP:** XAMPP berjalan sebagai user saat ini, biasanya sudah punya akses tulis secara default. Jika ada masalah, klik kanan folder → Properties → Security → beri permission Full Control ke user `Everyone`.

### Langkah 5 — Verifikasi Apache & mod_rewrite

1. Buka **XAMPP Control Panel**
2. Pastikan Apache dan MySQL berstatus **Running**
3. Buka `C:\xampp\apache\conf\httpd.conf`, cari baris:
   ```
   #LoadModule rewrite_module modules/mod_rewrite.so
   ```
   Hapus tanda `#` di depannya jika ada, lalu restart Apache.

### Langkah 6 — Akses Aplikasi

Buka browser dan akses salah satu URL berikut:

| URL | Keterangan |
|-----|-----------|
| `http://localhost/toko_kopi/public/` | Landing page |
| `http://localhost/toko_kopi/public/login.php` | Login admin |
| `http://localhost/toko_kopi/public/chat.php` | Demo chat (pilih cabang) |
| `http://localhost/toko_kopi/public/order.php?branch={slug}` | Halaman order per cabang |

---

## Akun Default

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@tokokopi.com | password |
| Admin Jakarta Selatan | admin.jaksel@tokokopi.com | password |
| Admin Bandung | admin.bandung@tokokopi.com | password |
| Admin Surabaya | admin.surabaya@tokokopi.com | password |

> **Penting:** Ganti semua password setelah login pertama via **Settings → Ganti Password**.

---

## Verifikasi Instalasi

Setelah instalasi, lakukan pengecekan berikut:

1. **Landing page** terbuka tanpa error
2. **Login** berhasil dengan akun default
3. **Dashboard** super admin dapat diakses
4. **Chat demo** merespons pesan di `/chat.php`
5. **Menu** tampil setelah seed data berhasil diimport

---

## Instalasi di Server Production

Lihat `.env.prod.example` untuk konfigurasi production. Perbedaan utama dari lokal:

```ini
APP_ENV=production
BASE_URL=https://yourdomain.com/toko_kopi/public
DB_PASS=password_yang_kuat
```

**Checklist sebelum go-live:**

- [ ] Hapus `public/install.php`
- [ ] Set `APP_ENV=production` di `.env`
- [ ] Ganti semua password akun default
- [ ] Pastikan `.env` tidak dapat diakses publik (sudah diproteksi oleh `.htaccess`)
- [ ] Aktifkan HTTPS di server
- [ ] Set permission `uploads/` dan `storage/logs/` hanya untuk web server user

---

## Troubleshooting

**Halaman tampil error 404 / tidak ditemukan**
→ `mod_rewrite` Apache belum aktif. Lihat Langkah 5 di atas.

**Error koneksi database**
→ Periksa nilai `DB_HOST`, `DB_USER`, `DB_PASS` di `.env`. Pastikan MySQL sedang berjalan di XAMPP Control Panel.

**Folder `uploads/` tidak bisa ditulis**
→ Beri permission write ke folder tersebut (lihat Langkah 4).

**Chat tidak merespons**
→ Pastikan seed data berhasil diimport (`database/seed.sql`) sehingga minimal ada satu cabang dan menu aktif.

**LLM tidak berfungsi**
→ Isi API key lewat dashboard: **Super Admin → Settings → AI Configuration**. Key disimpan di tabel `app_settings`, bukan di `.env`.
