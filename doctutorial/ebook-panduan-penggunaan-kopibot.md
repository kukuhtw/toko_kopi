# EBook Panduan Penggunaan KopiBot

## Ringkasan

KopiBot adalah aplikasi AI chatbot order system untuk coffee shop, cafe, bakery, restoran, beverage store, dan bisnis F&B. Aplikasi ini memakai PHP Native 8, MySQL, REST API, plugin channel chat, payment gateway, loyalty point, Customer CRM, Customer Portal, dan fitur multi cabang.

Dokumen ini adalah draft ebook versi pendek untuk membantu user melakukan setup instalasi awal, login admin, mencoba order, serta memahami alur dasar penggunaan aplikasi.

Developer: Kukuh TW
Email: kukuhtw@gmail.com
WhatsApp: https://wa.me/628129893706
Repository: https://github.com/kukuhtw/toko_kopi

---

## 1. Kebutuhan Sistem

Aplikasi membutuhkan environment berikut:

| Komponen | Minimum |
|----------|---------|
| PHP | 8.0+ |
| Database | MySQL 5.7+ atau MariaDB 10.3+ |
| Web Server | Apache dengan mod_rewrite aktif |
| PHP Extension | pdo_mysql, mbstring, json, fileinfo |

Untuk development lokal, XAMPP sudah cukup.

---

## 2. Instalasi Lokal dengan XAMPP

1. Install XAMPP dengan PHP 8.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Copy folder project ke:

```text
C:\xampp\htdocs\toko_kopi\
```

4. Pastikan file utama ada di:

```text
C:\xampp\htdocs\toko_kopi\public\index.php
```

5. Buka aplikasi melalui browser:

```text
http://localhost/toko_kopi/public/
```

---

## 3. Instalasi Menggunakan Web Installer

Buka URL berikut:

```text
http://localhost/toko_kopi/public/install.php
```

Ikuti wizard instalasi:

1. Cek persyaratan sistem.
2. Isi konfigurasi database.
3. Isi konfigurasi aplikasi.
4. Import schema dan seed database.
5. Selesai, akun admin siap digunakan.

Setelah selesai, hapus file installer:

```text
public/install.php
```

File installer wajib dihapus sebelum aplikasi dipakai di production.

---

## 4. Instalasi Manual

Buat database baru melalui phpMyAdmin:

```text
toko_kopi
```

Import file berikut secara berurutan:

```text
database/schema.sql
database/seed.sql
```

Alternatif via command line:

```bash
mysql -u root -p -e "CREATE DATABASE toko_kopi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p toko_kopi < database/schema.sql
mysql -u root -p toko_kopi < database/seed.sql
```

---

## 5. Konfigurasi File .env

Copy file contoh:

```bash
copy .env.example .env
```

Untuk Linux atau Mac:

```bash
cp .env.example .env
```

Isi konfigurasi dasar:

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=toko_kopi
DB_USER=root
DB_PASS=
APP_ENV=development
BASE_URL=http://localhost/toko_kopi/public
```

Catatan: API key LLM tidak diisi di .env. API key dikelola melalui dashboard Super Admin.

---

## 6. URL Penting

| URL | Fungsi |
|-----|--------|
| http://localhost/toko_kopi/public/ | Landing page |
| http://localhost/toko_kopi/public/login.php | Login admin |
| http://localhost/toko_kopi/public/chat.php | Demo chat |
| http://localhost/toko_kopi/public/order.php?branch={slug} | Order per cabang |
| http://localhost/toko_kopi/public/customer/login.php | Login Customer Portal |
| http://localhost/toko_kopi/public/docs/index.php | Dokumentasi HTML |

---

## 7. Akun Default

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@tokokopi.com | password |
| Admin Jakarta Selatan | admin.jaksel@tokokopi.com | password |
| Admin Bandung | admin.bandung@tokokopi.com | password |
| Admin Surabaya | admin.surabaya@tokokopi.com | password |

Ganti password setelah login pertama.

---

## 8. Setup Awal Setelah Login

Setelah login sebagai Super Admin, lakukan langkah berikut:

1. Ganti password default.
2. Cek daftar cabang.
3. Cek menu awal.
4. Cek plugin aktif di plugins/plugins.json.
5. Isi API key LLM melalui dashboard.
6. Cek payment gateway bila diperlukan.
7. Coba demo chat.
8. Coba halaman order per cabang.
9. Pastikan order masuk ke dashboard.

---

## 9. Alur Order Customer

Alur order dasar:

1. Customer membuka halaman order atau chat.
2. Customer memilih menu.
3. Customer menambahkan item ke cart.
4. Customer checkout.
5. Sistem meminta nama, kontak, alamat, dan delivery method.
6. Sistem membuat order.
7. Sistem menampilkan instruksi pembayaran.
8. Admin memproses order.
9. Customer dapat melihat status order melalui Customer Portal.

---

## 10. Fitur Utama

Fitur utama KopiBot:

1. AI Chatbot Order Menu.
2. Multi Branch Management.
3. Menu, variant, topping, dan foto produk.
4. Promo Engine.
5. Loyalty Point.
6. Customer CRM.
7. Customer Portal.
8. Payment Gateway plugin.
9. WhatsApp, Telegram, dan Discord channel.
10. Complaint Handling.
11. Plugin System.

---

## 11. Troubleshooting Awal

### Halaman 404

Cek BASE_URL, lokasi folder project, dan pastikan mod_rewrite Apache aktif.

### Database error

Pastikan MySQL running, database sudah dibuat, lalu cek DB_HOST, DB_USER, DB_PASS, dan DB_NAME di file .env.

### Chat tidak merespons

Pastikan seed.sql sudah diimport, cabang aktif, menu aktif, dan API key LLM sudah diisi bila memakai mode LLM.

### Upload gagal

Pastikan folder uploads bisa ditulis oleh web server.

### CRM atau Loyalty tidak muncul

Cek apakah plugin customer-crm dan loyalty-point aktif di plugins/plugins.json.

---

## 12. Checklist Production

Sebelum go live, pastikan:

1. APP_ENV=production.
2. HTTPS aktif.
3. public/install.php sudah dihapus.
4. Password default sudah diganti.
5. File .env tidak bisa diakses publik.
6. Folder uploads dan storage/logs bisa ditulis dengan aman.
7. Payment gateway sudah diuji.
8. Channel chat sudah diuji.
9. Backup database sudah disiapkan.

---

## Penutup

Draft ebook ini dapat dikembangkan menjadi panduan lengkap per bab, mencakup setup production, konfigurasi plugin, payment gateway, WhatsApp gateway, customer CRM, loyalty point, FAQ RAG, dan integrasi delivery.
