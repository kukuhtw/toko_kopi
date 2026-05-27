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

## 13. Panduan Mengelola Cabang

Cabang adalah unit operasional coffee shop yang memiliki menu, promo, jam buka, alamat, dan pengaturan pembayaran sendiri. Fitur multi cabang membuat satu aplikasi bisa dipakai oleh banyak lokasi bisnis tanpa membuat aplikasi terpisah.

Data cabang yang perlu disiapkan:

1. Nama cabang.
2. Slug cabang untuk URL order.
3. Alamat lengkap.
4. Nomor kontak cabang.
5. Jam operasional.
6. Status aktif atau nonaktif.
7. Mata uang dan timezone.
8. Pengaturan channel order.

Contoh slug cabang:

```text
jakarta-selatan
bandung
surabaya
```

Contoh URL order cabang:

```text
http://localhost/toko_kopi/public/order.php?branch=jakarta-selatan
```

Saat membuat cabang baru, pastikan slug mudah dibaca, tidak menggunakan spasi, dan konsisten dengan nama cabang. Slug akan dipakai pada link order, link promosi, dan integrasi channel.

---

## 14. Panduan Mengelola Menu, Variant, dan Topping

Menu adalah data utama yang akan dilihat customer. Setiap produk sebaiknya memiliki nama yang jelas, deskripsi singkat, harga, kategori, status aktif, dan foto produk.

Contoh kategori menu:

1. Coffee.
2. Non Coffee.
3. Tea.
4. Bakery.
5. Snack.
6. Main Course.
7. Seasonal Menu.

Variant digunakan untuk pilihan produk. Contoh variant:

| Variant | Keterangan |
|---------|------------|
| Hot | Minuman panas |
| Ice | Minuman dingin |
| Regular | Ukuran normal |
| Large | Ukuran besar |

Topping digunakan untuk tambahan item. Contoh topping:

| Topping | Contoh Harga |
|---------|--------------|
| Extra Shot | 7000 |
| Oat Milk | 8000 |
| Caramel Syrup | 5000 |
| Cheese Cream | 6000 |

Agar chatbot mudah memahami menu, gunakan nama produk yang sederhana dan tidak terlalu mirip satu sama lain. Misalnya, bedakan nama `Kopi Susu Aren` dan `Kopi Susu Pandan` dengan jelas.

---

## 15. Panduan Customer Portal

Customer Portal adalah halaman mandiri untuk customer. Customer dapat melihat riwayat order, status order, loyalty point, profile, dan melakukan repeat order.

URL Customer Portal:

```text
http://localhost/toko_kopi/public/customer/login.php
```

Customer Portal memakai login ringan berbasis kontak dan nomor order. Customer tidak memakai akun admin. Data yang digunakan biasanya email atau nomor WhatsApp yang pernah dipakai saat order.

Fitur Customer Portal:

1. Melihat order history.
2. Melihat detail order.
3. Melihat status pembayaran.
4. Melihat loyalty point.
5. Mengelola profile customer.
6. Melakukan repeat order.

Customer Portal penting untuk mengurangi pertanyaan berulang ke admin, terutama pertanyaan status order dan riwayat pembelian.

---

## 16. Panduan Plugin System

Plugin System membuat aplikasi lebih fleksibel. Fitur tambahan dapat dibuat sebagai plugin tanpa mengubah core aplikasi terlalu banyak.

Struktur plugin sederhana:

```text
plugins/nama-plugin/
|-- plugin.php
`-- NamaPlugin.php
```

Contoh plugin yang tersedia:

1. customer-crm.
2. loyalty-point.
3. midtrans-payment.
4. xendit-payment.
5. ipaymu-payment.
6. nicepay-payment.
7. telegram-channel.
8. discord-channel.
9. fonnte-whatsapp.
10. upselling.
11. rekomendasi-promo.

Plugin perlu didaftarkan atau diaktifkan melalui konfigurasi plugin. File konfigurasi utama plugin berada di:

```text
plugins/plugins.json
```

Sebelum mengaktifkan plugin di production, lakukan pengujian di local atau staging. Pastikan plugin tidak merusak flow order, checkout, payment, dan dashboard.

---

## 17. Panduan Backup dan Maintenance

Backup diperlukan untuk menjaga data order, customer, loyalty, dan konfigurasi aplikasi. Minimal backup yang perlu disiapkan adalah database dan folder upload.

Data yang perlu dibackup:

1. Database MySQL.
2. Folder uploads.
3. File .env.
4. Konfigurasi plugin.
5. Log penting bila diperlukan.

Contoh backup database:

```bash
mysqldump -u root -p toko_kopi > backup_toko_kopi.sql
```

Contoh restore database:

```bash
mysql -u root -p toko_kopi < backup_toko_kopi.sql
```

Checklist maintenance rutin:

1. Cek error log.
2. Cek kapasitas folder uploads.
3. Cek order gagal.
4. Cek callback payment gagal.
5. Cek webhook channel chat.
6. Cek user admin yang masih aktif.
7. Ganti password admin secara berkala.
8. Backup database terjadwal.

---

## Penutup

Draft ebook ini dapat dikembangkan menjadi panduan lengkap per bab, mencakup setup production, konfigurasi plugin, payment gateway, WhatsApp gateway, customer CRM, loyalty point, FAQ RAG, dan integrasi delivery.
