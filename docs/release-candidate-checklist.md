# Release Candidate Checklist

Checklist ini digunakan untuk memvalidasi branch `composer-mvp-core-migration-next` sebelum merge atau deployment internal.

## 1. Preparation

```bash
composer install
composer dump-autoload
```

Expected:

```text
No dependency error
No autoload error
No missing class error
```

## 2. Automated Verification

```bash
composer verify
```

Command ini menjalankan:

```text
smoke:core
smoke:auth
smoke:chatbot
smoke:migration
smoke:plugin
smoke:payment
smoke:api
integration:checkout
integration:chat-memory
```

Expected:

```text
All tests return success true
Exit code 0
```

## 3. Landing Page Migration

Jalankan:

```bash
composer migrate:public-index
```

Expected:

```text
public/index.php migrated to Composer runtime and landing dependencies
```

Validasi manual:

```text
Landing page render normal
Site name tampil
Hero section tampil
Branch count tampil
Plugin filter tetap aktif
Tidak ada fatal error class not found
```

## 4. API Validation

Jalankan built-in server:

```bash
composer serve
```

Cek endpoint:

```text
GET /api/health
POST /api/auth/register
POST /api/auth/login
GET /api/products
POST /api/cart/add
POST /api/cart/checkout
POST /api/chatbot/message
```

Expected:

```text
/api/health returns success true
Route not found tidak muncul untuk endpoint valid
Unauthorized hanya muncul untuk endpoint yang memang perlu auth
```

## 5. Database Validation

Pastikan seluruh domain utama memakai:

```text
KopiBot Core DatabaseConnection
```

Domain yang perlu divalidasi:

```text
BranchRepository
ProductRepository
CartRepository
UserRepository
OrderRepository
PaymentRepository
ConversationMemoryRepository
```

Expected:

```text
No direct standalone database connection outside DatabaseConnection
Legacy Database facade only delegates to DatabaseConnection
```

## 6. Plugin Validation

Validasi:

```text
plugins/plugins.json terbaca
Plugin aktif bisa diload
Plugin register hook ke HookManager Composer
Tidak ada duplicate hook registry
```

Expected:

```text
App PluginLoader delegates to Composer PluginLoader
App PluginInterface extends Composer PluginInterface
App HookManager delegates to Composer HookManager
```

## 7. Payment Validation

Validasi:

```text
PaymentFactory::register works
PaymentFactory::make works
Mock provider works
Real gateway plugin can register itself
```

Expected:

```text
Checkout flow can create payment result
checkout_url exists
reference_no exists
```

## 8. Chatbot Validation

Validasi:

```text
ChatbotService can process message
ConversationMemoryService can remember user message
ConversationMemoryService can remember assistant message
Recent history can be retrieved
```

Expected:

```text
No database facade mismatch
Conversation memory uses DatabaseConnection through repository
```

## 9. Legacy Dependency Audit

Cek dependency legacy yang masih tersisa.

Target ideal:

```text
App Config only appears in adapter layer
App Models only appears in adapter layer or old UI
App Plugin only appears in adapter layer or old plugins
Legacy Database facade only appears in compatibility layer or docs
```

Files yang boleh tetap ada sementara:

```text
app/Config/config.php
app/Config/Database.php
app/Models/BaseModel.php
app/Models/BranchModel.php
app/Plugin/PluginLoader.php
app/Plugin/PluginInterface.php
app/Plugin/HookManager.php
src/Core/Database.php
```

## 10. Release Decision

Branch bisa dianggap release candidate jika:

```text
[ ] composer install sukses
[ ] composer dump-autoload sukses
[ ] composer verify sukses
[ ] composer migrate:public-index sukses
[ ] Landing page render normal
[ ] API health endpoint sukses
[ ] Plugin config real tidak fatal
[ ] Checkout integration flow sukses
[ ] Chat memory integration flow sukses
[ ] Tidak ada dependency legacy kritikal di luar adapter
```

## 11. Rollback Plan

Jika terjadi error setelah migrasi landing page:

```text
Restore public/index.php dari commit sebelum migrate script dijalankan
Keep app/Config/config.php adapter aktif
Keep App Plugin adapter aktif
Keep App Model adapter aktif
```

Jangan hapus folder `app/` sampai semua validasi release candidate lulus.
