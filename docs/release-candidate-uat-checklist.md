# Release Candidate UAT Checklist

Branch: `composer-mvp-core-migration-next`

Dokumen ini dipakai untuk validasi runtime setelah migrasi Composer MVP Core selesai secara arsitektur.

## 1. Pull Branch

```bash
git fetch origin
git checkout composer-mvp-core-migration-next
git pull origin composer-mvp-core-migration-next
```

## 2. Install Dependency

```bash
composer install
composer dump-autoload
```

Expected result:

```text
Autoload generated successfully
```

## 3. Run Release Candidate Gate

```bash
composer audit:legacy
composer verify:rc
```

Expected result:

```text
Legacy dependency audit passed.
Smoke tests passed.
Integration tests passed.
```

## 4. Environment Check

Pastikan file environment tersedia:

```text
.env
```

Minimal konfigurasi:

```text
APP_ENV
APP_URL
DB_HOST
DB_NAME
DB_USER
DB_PASS
OPENROUTER_API_KEY
GEMINI_API_KEY
```

## 5. Public Runtime Check

Jalankan server lokal:

```bash
composer serve
```

Checklist:

```text
[ ] Landing page terbuka
[ ] Login page terbuka
[ ] Dashboard terbuka
[ ] Tidak ada fatal error autoload
[ ] Tidak ada class not found
[ ] Tidak ada namespace App lama yang dipanggil dari plugin baru
```

## 6. API Health Check

Checklist:

```text
[ ] API health endpoint merespons 200
[ ] API checkout endpoint tidak fatal
[ ] API payment notify endpoint tidak fatal
[ ] API chatbot endpoint tidak fatal
```

## 7. Payment Gateway UAT

Gateway yang wajib dicek:

```text
midtrans-payment
ipaymu-payment
nicepay-payment
xendit-payment
```

Checklist per gateway:

```text
[ ] Plugin aktif
[ ] Settings form tampil
[ ] CSRF field muncul
[ ] Credential bisa disimpan
[ ] Order created menghasilkan payment URL
[ ] Checkout response memiliki payment provider
[ ] Payment callback diterima
[ ] Payment status berubah sesuai callback
[ ] Tidak ada fatal error OrderModel
[ ] Tidak ada fatal error Csrf
```

## 8. AI Provider UAT

Provider yang wajib dicek:

```text
openrouter-llm
gemini-llm
anthropic-llm
```

Checklist:

```text
[ ] Provider muncul di pilihan settings
[ ] API key terbaca
[ ] Model list muncul
[ ] Chatbot bisa menjawab
[ ] Intent detector berjalan
[ ] Tidak ada fatal error HookManager
[ ] Tidak ada fatal error PluginInterface
[ ] Tidak ada fatal error Database
```

## 9. Loyalty Point UAT

Checklist:

```text
[ ] Customer bisa cek saldo poin
[ ] Customer bisa redeem poin
[ ] Cart discount berubah setelah redeem
[ ] Customer bisa cancel redeem poin
[ ] Cart discount kembali normal setelah cancel
[ ] Poin diberikan setelah order valid
[ ] Poin tidak double award untuk order yang sama
[ ] Poin dikembalikan saat order cancelled
[ ] Tidak ada fatal error CartModel
[ ] Tidak ada fatal error Currency
```

## 10. Customer CRM UAT

Checklist:

```text
[ ] Customer profile terbaca
[ ] Customer normalizer email berjalan
[ ] Customer normalizer WhatsApp berjalan
[ ] CRM settings form tampil
[ ] CRM notification log berjalan
[ ] Tidak ada fatal error CustomerModel
[ ] Tidak ada fatal error Database
[ ] Tidak ada fatal error Csrf
```

## 11. Chatbot Skill Framework UAT

Checklist:

```text
[ ] SkillRegistry bisa register skill
[ ] SkillInterface dikenali autoload
[ ] IntentPatternRegistry bisa extend pattern
[ ] Intent cek poin loyalty dikenali
[ ] Intent pakai poin loyalty dikenali
[ ] Intent hapus poin loyalty dikenali
```

## 12. Channel Plugin UAT

Channel yang perlu dicek manual:

```text
fonnte-whatsapp
twilio-whatsapp
telegram-channel
```

Checklist:

```text
[ ] Incoming message diterima
[ ] Outgoing message terkirim
[ ] Chatbot response muncul
[ ] Intent routing berjalan
[ ] Tidak ada fatal error namespace lama
```

## 13. Database Verification

Cek tabel penting:

```text
orders
customers
carts
cart_items
plugin_branch_settings
loyalty_point_accounts
loyalty_point_transactions
crm_notification_logs
app_settings
```

Checklist:

```text
[ ] Tabel ada
[ ] Kolom loyalty ada di carts
[ ] Kolom loyalty ada di orders
[ ] Data plugin settings tersimpan
[ ] Data payment URL tersimpan
[ ] Data loyalty transaction tercatat
```

## 14. Final Pass Criteria

Release candidate dianggap lolos jika:

```text
[ ] composer audit:legacy passed
[ ] composer verify:rc passed
[ ] Public page passed
[ ] API health passed
[ ] Payment UAT passed
[ ] AI provider UAT passed
[ ] Loyalty UAT passed
[ ] CRM UAT passed
[ ] Chatbot skill UAT passed
[ ] Channel plugin UAT passed
```

## 15. Rollback Criteria

Rollback atau tahan merge jika ditemukan:

```text
[ ] Payment callback gagal update order
[ ] Checkout tidak bisa membuat order
[ ] Chatbot tidak bisa menjawab
[ ] Loyalty redeem merusak total cart
[ ] Fatal error class not found
[ ] Fatal error autoload
[ ] Data order/payment/customer hilang atau salah update
```

## 16. Final Notes

Migrasi arsitektur sudah selesai. Fokus UAT adalah memastikan runtime lama dan runtime Composer baru berjalan bersama tanpa memutus flow bisnis.
