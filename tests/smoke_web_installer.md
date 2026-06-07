# Web Installer Smoke Test

Checklist ini dipakai untuk memvalidasi `public/install.php` setelah migrasi installer ke arsitektur Composer, migration runner, dan seeders.

## Persiapan

1. Pastikan dependency Composer sudah terpasang:

```bash
composer install
```

2. Pastikan web server lokal aktif, misalnya:

```bash
php -S localhost:8000 -t public
```

3. Siapkan database kosong atau kredensial database yang boleh dibuat ulang oleh installer.

## Smoke Test UI Installer

1. Buka `http://localhost:8000/install.php?force=1`.
2. Selesaikan langkah 1 sampai 6 dengan data valid.
3. Pada langkah plugin, pilih minimal satu template katalog dan beberapa plugin umum.

Expected:
- wizard berpindah langkah tanpa fatal error
- daftar plugin tampil dengan nama yang masuk akal, termasuk plugin modern yang metadata-nya berasal dari class

## Smoke Test Database Bootstrap

1. Jalankan instalasi sampai selesai.

Expected:
- tabel `migrations` terbentuk
- migration dari `database/migrations/*.sql` tercatat di tabel `migrations`
- data demo dari `database/seeders/*.sql` masuk
- file `.env` tertulis
- file `plugins/plugins.json` tertulis
- file `storage/installed.lock` terbentuk

2. Verifikasi cepat di database:

```sql
SELECT COUNT(*) FROM migrations;
SELECT COUNT(*) FROM tenants;
SELECT COUNT(*) FROM branches;
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM products;
```

Expected:
- `migrations` berisi data
- tabel inti tidak kosong setelah install sukses

## Smoke Test Template Seed

1. Ulangi instalasi dengan template selain `keep-seed`, misalnya `coffee-template` atau `pharmacy-template`.

Expected:
- installer tidak gagal saat memanggil `resetAndSeed()`
- jumlah produk/kategori berubah sesuai template yang dipilih
- branding default ikut menyesuaikan template jika field branding tidak diisi manual

## Smoke Test Runtime Hasil Install

1. Buka `http://localhost:8000/api/health`
2. Login dengan akun super admin yang dibuat installer
3. Buka dashboard admin
4. Verifikasi plugin yang dipilih muncul aktif di `plugins/plugins.json`

Expected:
- health endpoint mengembalikan status `ok`
- login berhasil
- dashboard terbuka tanpa error DB
- plugin selection hasil installer tersimpan

## Smoke Test Konsistensi Dengan CLI

1. Bandingkan hasil install web dengan baseline CLI:

```bash
composer migrate
```

2. Bandingkan tabel inti dan data demo utama.

Expected:
- schema inti hasil install web sama secara praktis dengan hasil `composer migrate`
- tidak ada tabel penting yang hanya ada di satu jalur install

## Catatan Risiko

- Jika `vendor/autoload.php` tidak ada, installer akan fallback ke jalur lama dan coverage arsitektur baru berkurang.
- Jika `database/migrations/` tersedia tetapi ada migration gagal, installer harus menampilkan error dan tidak lanjut diam-diam.
- Template yang masih bergantung pada perilaku runtime lama perlu diuji ulang setelah setiap batch migrasi plugin template.
