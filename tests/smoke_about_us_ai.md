# About Us AI Smoke Test

Checklist ini dipakai untuk memvalidasi scaffold `plugins/about-us-ai` setelah migrasi ke arsitektur plugin baru.

## Persiapan

1. Pastikan plugin `about-us-ai` tersedia di `plugins/plugins.json`.
2. Jika database existing dipakai, jalankan patch:

```sql
SOURCE database/add_about_us_ai.sql;
```

3. Pastikan aplikasi sudah memakai runtime Composer dan database aktif.

## Smoke Test Schema

1. Verifikasi tabel berikut ada:

```sql
SHOW TABLES LIKE 'about_us_contents';
SHOW TABLES LIKE 'about_us_generation_logs';
```

Expected:
- kedua tabel ada

2. Verifikasi kolom penting:

```sql
SHOW COLUMNS FROM about_us_contents;
SHOW COLUMNS FROM about_us_generation_logs;
```

Expected:
- `about_us_contents` punya `title`, `content`, `ai_prompt`, `ai_generated_content`, `generation_model`, `content_status`
- `about_us_generation_logs` punya `event_name`, `status`, `model`, `last_error`

## Smoke Test Plugin Registration

1. Aktifkan `"about-us-ai": { "active": true }` di `plugins/plugins.json`.
2. Muat aplikasi / dashboard admin.

Expected:
- tidak ada fatal error plugin load
- menu `About Us AI` muncul di area `Content` untuk `super_admin` atau `branch_admin`

## Smoke Test Settings

1. Buka section settings plugin `About Us AI`.
2. Simpan konfigurasi:
   - `brand_name`
   - `business_type`
   - `tone`

Expected:
- submit berhasil
- data tersimpan ke `plugin_branch_settings`

3. Simpan global runtime:
   - provider
   - model

Expected:
- data tersimpan ke `app_settings`

## Smoke Test Draft Generation

1. Isi profil brand lalu submit generate draft.

Expected:
- draft About Us terbentuk
- row baru atau update masuk ke `about_us_contents`
- `content_status = draft`
- log baru masuk ke `about_us_generation_logs` dengan event `about_us.generate`

2. Verifikasi query:

```sql
SELECT id, branch_id, title, content_status, generation_model
FROM about_us_contents
ORDER BY id DESC
LIMIT 5;

SELECT id, branch_id, event_name, status, model
FROM about_us_generation_logs
ORDER BY id DESC
LIMIT 5;
```

Expected:
- draft terbaru terlihat
- log generasi terbaru terlihat

## Smoke Test Publish

1. Publish konten About Us melalui flow plugin atau panggilan service terkait.

Expected:
- `content_status` berubah ke `published`
- `published_at` terisi
- log event `about_us.publish` tercatat

## Smoke Test Compatibility

1. Jalankan `php -l` untuk file plugin:

```bash
php -l plugins/about-us-ai/AboutUsAiPlugin.php
php -l plugins/about-us-ai/AboutUsAiRepository.php
php -l plugins/about-us-ai/AboutUsAiService.php
php -l plugins/about-us-ai/plugin.php
```

Expected:
- semua lolos tanpa syntax error

## Catatan Risiko

- Saat ini generator masih mode scaffold, belum memanggil provider AI real.
- Belum ada halaman publik/admin final khusus `about-us-ai`, jadi validasi UI bergantung pada section settings plugin.
- Jika nanti ditambah integrasi AI real, perlu smoke test tambahan untuk prompt, provider selection, dan fallback error handling.
