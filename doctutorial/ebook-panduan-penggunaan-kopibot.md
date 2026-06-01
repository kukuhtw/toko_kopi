# EBook Panduan Penggunaan KopiBot

## Ringkasan

KopiBot bukan lagi hanya aplikasi chatbot order untuk coffee shop. Pada codebase terbaru, KopiBot sudah berkembang menjadi platform AI Agent Commerce untuk banyak jenis bisnis seperti coffee shop, bakery, fresh market, pharmacy, minimarket, warung, resto, toko aksesori HP, fashion wanita, tours & travel, hingga layanan umrah.

Satu codebase dipakai untuk:

- order via web dan channel chat
- katalog produk dan jasa
- promo, loyalty, dan upselling
- Customer CRM dan Customer Portal
- multi cabang
- plugin payment, delivery, POS, FAQ, complaint handling, dan channel chat

Dokumen ini ditulis sebagai panduan penggunaan yang lebih detail dan menyesuaikan perilaku aplikasi pada code terbaru, terutama alur Web Installer, branding saat instalasi, template bisnis, plugin, dan konfigurasi operasional setelah login.

Developer: Kukuh TW  
Email: kukuhtw@gmail.com  
WhatsApp: https://wa.me/628129893706  
Repository: https://github.com/kukuhtw/toko_kopi

---

## 1. Posisi Produk dan Use Case

KopiBot cocok dipakai ketika bisnis ingin memiliki sistem order dan support yang bisa dikontrol sendiri, bukan sekadar memakai panel SaaS generik. Dengan pendekatan plugin dan template, aplikasi ini bisa dipasang untuk berbagai deployment dengan komposisi fitur yang berbeda.

Contoh use case:

| Jenis Bisnis | Contoh Penggunaan |
|---|---|
| Coffee shop / cafe | Menu minuman, topping, size, loyalty, promo, delivery |
| Bakery / kuliner | Roti, pastry, makanan siap saji, bundling, repeat order |
| Fresh market | Buah, daging, sayur, item retail, katalog harian |
| Pharmacy / apotek | Produk kesehatan, FAQ, complaint handling, CRM |
| Minimarket / retail | Banyak SKU, promo, customer portal, payment |
| Fast food | Burger, kebab, bakso, warung, resto Indonesia |
| Specialty retail | Aksesori HP, fashion wanita, toko niche |
| Services | Tours & travel, umrah, layanan booking |

---

## 2. Kebutuhan Sistem

Environment minimum yang direkomendasikan:

| Komponen | Minimum |
|---|---|
| PHP | 8.0+ |
| Database | MySQL 5.7+ atau MariaDB 10.3+ |
| Web Server | Apache dengan `mod_rewrite` aktif |
| Ekstensi PHP | `pdo`, `pdo_mysql`, `mbstring`, `json`, `curl`, `openssl`, `fileinfo` |

Untuk development lokal, XAMPP umumnya sudah cukup.

Folder yang perlu bisa ditulis:

- `storage/`
- `uploads/`
- `public/uploads/` bila dipakai oleh deployment

---

## 3. Struktur Folder dan URL Dasar

Contoh lokasi project pada Windows:

```text
C:\xampp\htdocs\toko_kopi\
```

Nama folder boleh diganti sesuai kebutuhan, misalnya:

```text
C:\xampp\htdocs\apotek_agent\
C:\xampp\htdocs\mart_commerce\
C:\xampp\htdocs\travel_umrah\
```

Jika folder project adalah `toko_kopi`, maka URL dasarnya:

```text
http://localhost/toko_kopi/public/
```

URL penting yang umum dipakai:

| URL | Fungsi |
|---|---|
| `/public/` | Landing page |
| `/public/install.php` | Web Installer |
| `/public/login.php` | Login admin |
| `/public/chat.php` | Demo chat publik |
| `/public/order.php?branch={slug}` | Halaman order per cabang |
| `/public/customer/login.php` | Login Customer Portal |
| `/public/docs/index.php` | Dokumentasi HTML |

---

## 4. Instalasi Lokal dengan XAMPP

Langkah paling aman untuk development lokal:

1. Install XAMPP dengan PHP 8.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Salin folder project ke `htdocs`.
4. Pastikan file utama ada di `public/index.php`.
5. Buka browser ke URL project.

Contoh:

```text
http://localhost/toko_kopi/public/
```

Jika ingin langsung instal, buka:

```text
http://localhost/toko_kopi/public/install.php
```

---

## 5. Instalasi Menggunakan Web Installer

Web Installer adalah cara yang direkomendasikan karena sudah mengikuti perilaku code terbaru. Installer sekarang bekerja dengan wizard 6 langkah dan tidak lagi hanya mengurus database, tetapi juga branding awal, akun super admin, template bisnis, dan aktivasi plugin.

### 5.1 Gambaran 6 langkah wizard

| Langkah | Nama | Fungsi |
|---|---|---|
| 1 | Persyaratan Sistem | Cek PHP, ekstensi, dan folder penting |
| 2 | Konfigurasi Database | Isi host, port, nama database, user, password |
| 3 | Pengaturan Aplikasi | Isi nama toko, icon toko, tagline, Base URL, environment |
| 4 | Akun Super Admin | Buat akun admin utama pertama |
| 5 | Template Produk & Plugin | Pilih template bisnis dan plugin aktif |
| 6 | Jalankan Instalasi | Review ringkasan lalu eksekusi instalasi |

### 5.2 Langkah 1 - Persyaratan Sistem

Installer akan memeriksa:

- versi PHP
- ekstensi penting
- akses tulis folder tertentu
- kesiapan environment dasar

Jika ada indikator merah, perbaiki dulu sebelum lanjut.

### 5.3 Langkah 2 - Konfigurasi Database

Isi parameter berikut:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

Installer dapat membuat database otomatis jika belum ada dan kredensial MySQL mengizinkan.

### 5.4 Langkah 3 - Pengaturan Aplikasi dan Branding

Ini adalah perubahan penting pada code terbaru. Sekarang installer menyediakan input:

- `Icon Toko`
- `Nama Brand / Toko`
- `Tagline Bisnis`
- `Base URL`
- `Environment`

Fitur tambahan pada langkah ini:

- preview branding live
- preview yang dibuat lebih mirip sidebar tema
- default branding yang bisa mengikuti template bisnis

Nilai branding ini nantinya dipakai oleh theme settings dan memengaruhi tampilan beberapa halaman publik seperti landing page, login, chat, dan order.

### 5.5 Langkah 4 - Akun Super Admin

Isi data super admin pertama:

- nama
- email
- password

Akun ini akan dipakai untuk login ke dashboard super admin setelah instalasi selesai.

### 5.6 Langkah 5 - Template Produk dan Plugin

Pada code terbaru, langkah ini jauh lebih penting karena dua hal:

1. User bisa memilih template bisnis sejak awal.
2. User bisa melihat preview branding mini pada kartu template.

Jika user memilih template tertentu, branding default dapat ikut menyesuaikan bila branding sebelumnya masih generik.

### 5.7 Langkah 6 - Jalankan Instalasi

Installer menampilkan ringkasan konfigurasi lalu menjalankan:

- pembuatan tabel database
- seed data awal
- penyimpanan branding
- pembuatan akun super admin
- aktivasi plugin terpilih
- penerapan template katalog bila dipilih

### 5.8 Lock file installer

Setelah berhasil, installer membuat:

```text
storage/installed.lock
```

Efeknya:

- akses ke `install.php` akan diblokir otomatis
- untuk instal ulang, hapus file lock atau buka `install.php?force=1`

`public/install.php` tidak wajib langsung dihapus karena sudah dilindungi lock file, tetapi menghapusnya tetap direkomendasikan untuk production.

---

## 6. Template Produk dan Jasa yang Tersedia

Pada langkah 5 installer, user dapat memilih template yang paling mendekati bisnisnya.

| Template | Jumlah Produk/Jasa | Contoh |
|---|---:|---|
| Default Seed Coffee Menu | ~30 | Espresso, Americano, Cappuccino, Latte |
| Coffee Shop Template | 132 | Kopi panas/dingin, dessert, snack |
| Bakery Template | 70 | Roti tawar, croissant, donat, pastry |
| Fruit Store Template | 60 | Apel, jeruk, jus mangga, salad buah |
| Meat & Veggie Template | 80 | Daging sapi, ayam, ikan, sayuran |
| Pharmacy / Apotek Template | 120 | Paracetamol, vitamin, alat kesehatan |
| Minimarket Template | 120 | Beras, mie instan, air mineral, sabun |
| Resto Indonesia Template | 125 | Nasi goreng, soto ayam, rendang |
| Warung Makan Template | 15 | Nasi goreng, ayam goreng, tempe, teh |
| Resto Baso & Minuman Template | 15 | Bakso urat, mie, pangsit, es campur |
| Kebab Template | 15 | Kebab original, shawarma, pita falafel |
| Burger Template | 15 | Burger beef, fries, onion ring, milkshake |
| Toko Aksesori & Casing HP Template | 80 | Soft case, charger, tempered glass, TWS |
| Toko Baju Busana Wanita Template | 80 | Blouse, jeans, dress, blazer, tas |
| Tours & Travel Template | 15 | Bali 3D2N, visa wisata, airport transfer |
| Umrah Template | 15 | Umrah 9 hari, umrah VIP, plus Turki |

Catatan:

- template hanya berfungsi sebagai data awal
- setelah instalasi, semua data bisa diubah dari dashboard
- beberapa template juga punya halaman plugin admin sendiri untuk reset dan seed ulang

---

## 7. Plugin yang Umum Dipakai Saat Instalasi

Langkah 5 installer juga memungkinkan user mengaktifkan plugin dasar sesuai kebutuhan deployment.

| Kategori | Contoh Plugin |
|---|---|
| Payment Gateway | Midtrans, Xendit, iPaymu, Nicepay |
| Channel Chat | WhatsApp, Telegram, Discord |
| Delivery | GoSend, RajaOngkir, KiriminAja |
| POS Connector | Moka Connect / Private Solution |
| E-Commerce Connector | SIRCLO Full Connector |
| CRM & Loyalty | Customer CRM, Loyalty Point |
| Support & Knowledge | FAQ RAG, Complaint Handling, Notifikasi Admin |
| AI & Marketing | Upselling, Rekomendasi Promo, Rich Chat UI |
| Affiliate | Affiliate Marketing |
| Content | CMS Berita |
| Branding / Theme | Theme settings dan branding hooks |

Plugin dapat diaktifkan dan dinonaktifkan lagi dari dashboard Super Admin setelah instalasi selesai.

---

## 8. Instalasi Manual

Instalasi manual tetap tersedia untuk developer yang ingin kontrol penuh.

### 8.1 Buat database

Via phpMyAdmin:

1. Buka `http://localhost/phpmyadmin`
2. Buat database baru
3. Import `database/schema.sql`
4. Import `database/seed.sql`

Alternatif CLI:

```bash
mysql -u root -p -e "CREATE DATABASE toko_kopi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p toko_kopi < database/schema.sql
mysql -u root -p toko_kopi < database/seed.sql
```

### 8.2 Siapkan `.env`

```bash
copy .env.example .env
```

Isi contoh dasar:

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=toko_kopi
DB_USER=root
DB_PASS=

APP_ENV=development
BASE_URL=http://localhost/toko_kopi/public
```

Catatan penting:

- API key LLM tidak perlu disimpan di `.env`
- pengaturan AI dikelola via dashboard Super Admin

### 8.3 Seed template bisnis

Setelah import dasar selesai, template bisnis bisa dijalankan dari halaman plugin template terkait, misalnya:

- Coffee template
- Pharmacy template
- Minimarket template
- Burger template
- Tours & Travel template
- Umrah template

### 8.4 Akun super admin

Jika tidak memakai web installer, buat akun super admin manual atau gunakan data seed bila deployment memang menyertakan akun contoh. Untuk environment nyata, selalu ganti password default.

---

## 9. Akun Awal dan Akses Login

Ada dua skenario:

### 9.1 Jika memakai Web Installer

Anda akan membuat akun super admin sendiri pada langkah 4. Ini adalah akun utama yang dipakai setelah instalasi.

### 9.2 Jika memakai seed manual

Beberapa data seed bisa menyediakan akun contoh seperti:

| Role | Email | Password |
|---|---|---|
| Super Admin | admin@tokokopi.com | password |
| Admin Jakarta Selatan | admin.jaksel@tokokopi.com | password |
| Admin Bandung | admin.bandung@tokokopi.com | password |
| Admin Surabaya | admin.surabaya@tokokopi.com | password |

Untuk production:

- ganti seluruh password default
- nonaktifkan akun contoh yang tidak dipakai

---

## 10. Setup Awal Setelah Login Sebagai Super Admin

Checklist awal yang direkomendasikan:

1. Ganti password admin utama.
2. Cek `Settings` dasar dan `Base URL`.
3. Cek branding toko pada halaman tema.
4. Cek daftar cabang.
5. Cek katalog atau template yang terpasang.
6. Cek plugin aktif.
7. Isi AI configuration bila akan memakai mode LLM.
8. Cek payment gateway dan mode sandbox/production.
9. Cek channel chat yang akan dipakai.
10. Buka halaman order per cabang dan lakukan test order.
11. Cek apakah order masuk ke dashboard.
12. Cek Customer Portal dengan data order yang baru dibuat.

---

## 11. Branding, Nama Toko, dan Theme Settings

Pada code terbaru, branding sudah mulai dihubungkan sejak installer.

Data branding yang penting:

- icon toko / brand emoji
- nama toko / app name
- tagline bisnis

Branding ini dipakai untuk beberapa halaman publik, termasuk:

- landing page
- login
- chat
- order page

Langkah praktis:

1. Isi branding saat instalasi.
2. Setelah login, buka halaman tema atau branding.
3. Koreksi icon, nama toko, dan tagline bila masih generik.
4. Cek ulang tampilan halaman publik.

Jika memilih template bisnis saat instalasi, branding default dapat ikut berubah. Contohnya template apotek memakai gaya yang berbeda dari coffee shop atau travel.

---

## 12. Panduan Mengelola Cabang

Cabang adalah unit operasional. Setiap cabang dapat memiliki:

- alamat sendiri
- nomor kontak sendiri
- jam operasional sendiri
- menu dan promo sendiri
- mata uang dan timezone sendiri
- pengaturan payment atau order yang berbeda

Data yang umumnya disiapkan saat membuat cabang:

1. Nama cabang
2. Slug cabang
3. Alamat lengkap
4. Nomor kontak
5. Jam operasional
6. Status aktif / nonaktif
7. Mata uang
8. Timezone
9. Tipe bisnis (`business_type`)

`business_type` penting karena memengaruhi konteks prompt AI untuk cabang tersebut.

Contoh nilai yang umum:

| Jenis Bisnis | Nilai `business_type` |
|---|---|
| Coffee shop / cafe | `coffee shop` |
| Apotek | `apotek` |
| Minimarket / retail | `mart` |
| Toko buah | `toko buah` |
| Bakery | `bakery` |
| Fresh market | `fresh market` |
| Travel | `travel` |
| Umrah | `umrah` |

Contoh URL order cabang:

```text
http://localhost/toko_kopi/public/order.php?branch=bandung
```

Tips:

- pakai slug tanpa spasi
- gunakan nama slug yang stabil untuk promosi dan integrasi
- cek hasilnya langsung di halaman order publik

---

## 13. Panduan Mengelola Menu, Kategori, Variant, dan Topping

Menu adalah jantung pengalaman order. Setiap item idealnya punya:

- nama produk/jasa
- kategori
- deskripsi singkat
- harga dasar
- status aktif
- foto

Untuk bisnis jasa seperti travel atau umrah, item dapat dipakai sebagai paket layanan. Untuk retail dan makanan, item dipakai sebagai katalog produk biasa.

### 13.1 Kategori

Kategori membantu:

- filter di halaman order
- kerapian dashboard
- pemahaman AI terhadap struktur katalog

Contoh kategori:

- Coffee
- Non Coffee
- Tea
- Bakery
- Snack
- Main Course
- Vitamin
- Antibiotik
- Paket Umrah
- Open Trip

### 13.2 Variant

Variant dipakai untuk pilihan seperti:

- ukuran
- suhu minuman
- jenis paket
- tipe colokan
- durasi layanan

Contoh:

| Variant | Keterangan |
|---|---|
| Hot | Minuman panas |
| Ice | Minuman dingin |
| Regular | Ukuran normal |
| Large | Ukuran besar |
| 3 Hari | Paket singkat |
| 5 Hari | Paket lebih panjang |

### 13.3 Topping / add-on

Topping atau add-on dipakai untuk item tambahan.

Contoh:

| Add-on | Contoh Harga |
|---|---:|
| Extra Shot | 7000 |
| Oat Milk | 8000 |
| Travel Insurance | 50000 |
| Bagasi Tambahan | 150000 |

Praktik terbaik:

- gunakan nama produk yang jelas
- hindari nama yang terlalu mirip
- isi deskripsi singkat agar AI dan customer lebih mudah memahami item

---

## 14. Alur Order Customer

Pada level tinggi, alur order bekerja seperti berikut:

1. Customer membuka halaman order atau memulai chat.
2. Customer melihat katalog berdasarkan cabang.
3. Customer memilih item, variant, dan add-on bila ada.
4. Customer menambahkan item ke cart.
5. Customer checkout.
6. Sistem meminta data customer bila belum lengkap.
7. Sistem membuat order.
8. Sistem menampilkan instruksi pembayaran atau status order.
9. Admin memproses order dari dashboard.
10. Customer dapat melihat status order di Customer Portal.

Fitur penting pada code terbaru:

- halaman order memakai branding publik
- kategori tampil sebagai filter
- cart mendukung catatan item
- checkout dapat memakai profile memory di browser
- customer dapat kembali mengecek histori order

---

## 15. Demo Chat, AI Configuration, dan Channel Chat

Halaman demo chat publik:

```text
http://localhost/toko_kopi/public/chat.php
```

Fungsi halaman ini:

- menguji perilaku chatbot
- mengecek respons intent
- memvalidasi branding publik
- menguji flow sebelum WhatsApp atau channel lain diaktifkan

### 15.1 AI Configuration

AI configuration sebaiknya diisi setelah login Super Admin. Umumnya mencakup:

- provider AI
- model AI
- API key
- strategi prompt

Catatan:

- API key tidak harus diletakkan di `.env`
- pengaturan AI disimpan melalui dashboard agar lebih fleksibel per deployment

### 15.2 Channel chat yang didukung lewat plugin

Contoh channel:

- WhatsApp
- Telegram
- Discord

Saran implementasi:

1. Uji flow chat di halaman demo web dulu.
2. Aktifkan plugin channel yang diperlukan.
3. Isi credential channel.
4. Uji webhook atau koneksi.
5. Cek log jika ada pesan masuk tetapi bot tidak merespons.

---

## 16. Customer CRM dan Customer Portal

KopiBot tidak berhenti di level transaksi. Setelah order masuk, data customer bisa dimanfaatkan lagi.

### 16.1 Customer CRM

Customer CRM membantu:

- menyatukan identitas customer
- melihat histori order
- melihat perilaku pembelian
- menjalankan loyalty dan follow-up

### 16.2 Customer Portal

URL:

```text
http://localhost/toko_kopi/public/customer/login.php
```

Fungsi Customer Portal:

1. melihat riwayat order
2. melihat detail order
3. melihat status pembayaran
4. melihat loyalty point
5. mengelola profil
6. melakukan repeat order

Portal ini sangat berguna untuk mengurangi pertanyaan manual ke admin.

---

## 17. Payment Gateway, Delivery, dan POS Connector

### 17.1 Payment Gateway

Plugin payment yang saat ini banyak dipakai:

- Midtrans
- Xendit
- iPaymu
- Nicepay

Checklist setup:

1. aktifkan plugin payment
2. isi credential sandbox
3. uji callback dan status payment
4. pindah ke credential production saat go-live

### 17.2 Delivery

Contoh plugin delivery:

- GoSend
- RajaOngkir
- KiriminAja

Penggunaannya tergantung model bisnis. Untuk pharmacy, kuliner, minimarket, dan fresh market, delivery biasanya penting sejak awal.

### 17.3 POS Connector

POS connector yang menonjol pada codebase ini adalah Moka Connect / Private Solution. Cocok bila bisnis ingin sinkronisasi order dan katalog ke workflow POS.

---

## 18. Plugin System dalam Operasional Harian

Plugin system membuat satu deployment bisa berbeda dari deployment lain tanpa harus fork core terlalu banyak.

Struktur dasar plugin:

```text
plugins/nama-plugin/
|-- plugin.php
`-- NamaPlugin.php
```

Contoh plugin yang relevan dengan penggunaan sehari-hari:

- `customer-crm`
- `loyalty-point`
- `midtrans-payment`
- `xendit-payment`
- `ipaymu-payment`
- `nicepay-payment`
- `telegram-channel`
- `discord-channel`
- `faq-rag`
- `complaint-handler`
- `rich-chat-ui`
- `pharmacy-template`
- `tours-travel-template`
- `umrah-template`

Secara teknis, status plugin aktif tersimpan di:

```text
plugins/plugins.json
```

Praktik terbaik:

- aktifkan plugin seperlunya
- uji di local atau staging lebih dulu
- cek pengaruhnya ke flow order, checkout, payment, dan dashboard

---

## 19. Troubleshooting Umum

### 19.1 Halaman installer tidak bisa dibuka

Periksa:

- URL project benar
- Apache aktif
- file `storage/installed.lock` mungkin sudah ada

Jika ingin instal ulang:

- hapus `storage/installed.lock`
- atau akses `install.php?force=1`

### 19.2 Database error

Periksa:

- MySQL aktif
- nama database benar
- user dan password benar
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` sesuai

### 19.3 Halaman order kosong atau branch tidak ditemukan

Periksa:

- slug cabang pada URL
- data cabang aktif
- cabang memiliki katalog aktif

### 19.4 Chat tidak merespons

Periksa:

- plugin AI atau channel aktif
- API key sudah diisi
- katalog dan cabang tersedia
- mode AI sudah dikonfigurasi

### 19.5 Upload gagal

Periksa permission folder:

- `uploads/`
- `storage/`

### 19.6 CRM, loyalty, atau portal tidak muncul

Periksa plugin terkait di `plugins/plugins.json` atau dashboard plugin.

### 19.7 Template bisnis gagal di-seed

Periksa:

- plugin template aktif
- tabel database sudah lengkap
- tidak ada data varian atau kategori rusak

---

## 20. Checklist Production

Sebelum go-live:

1. `APP_ENV=production`
2. HTTPS aktif
3. Password default sudah diganti
4. `.env` tidak dapat diakses publik
5. `storage/installed.lock` sudah ada, atau `install.php` dihapus
6. Folder upload aman dan writable
7. Payment gateway sudah diuji
8. Delivery connector sudah diuji bila dipakai
9. Channel chat sudah diuji
10. Backup database sudah dijadwalkan
11. Role user sudah ditinjau
12. Branding publik sudah dicek di landing, login, chat, dan order
13. Katalog sudah diverifikasi sesuai bisnis
14. SOP admin untuk order, refund, komplain, dan follow-up sudah siap

---

## 21. Backup dan Maintenance

Data minimal yang wajib dibackup:

1. database MySQL
2. folder `uploads`
3. file `.env`
4. konfigurasi plugin
5. file penting di `storage` bila deployment membutuhkannya

Contoh backup database:

```bash
mysqldump -u root -p toko_kopi > backup_toko_kopi.sql
```

Contoh restore:

```bash
mysql -u root -p toko_kopi < backup_toko_kopi.sql
```

Checklist maintenance rutin:

1. cek error log
2. cek order gagal
3. cek callback payment
4. cek queue delivery atau POS bila ada
5. cek webhook channel chat
6. cek kapasitas uploads dan storage
7. cek admin yang masih aktif
8. ganti password admin secara berkala
9. review plugin yang aktif
10. backup database terjadwal

---

## Penutup

Pada code terbaru, KopiBot sudah layak dipahami sebagai platform AI Agent Commerce yang modular, bukan sekadar chatbot order kopi. Installer, branding, template bisnis, plugin, CRM, portal customer, dan channel integrasi sudah bergerak ke arah deployment multi-vertical.

Jika ebook ini ingin dikembangkan lebih jauh, bab lanjutan yang paling penting untuk ditambahkan adalah:

- panduan dashboard super admin per menu
- panduan branch admin harian
- panduan payment gateway per provider
- panduan FAQ RAG dan complaint handling
- panduan integrasi WhatsApp
- panduan POS dan delivery connector
- panduan white-label deployment dan plugin custom
