# Composer MVP Core Migration Status

Dokumen ini mencatat status migrasi branch `composer-mvp-core-migration-next` dari struktur legacy `app/` menuju Composer architecture `src/`.

## Executive Summary

Migrasi fondasi Composer sudah berada pada fase akhir.

Fokus yang sudah selesai:

```text
Composer bootstrap
Runtime bridge
Database standardization
Plugin system migration
Branch domain migration
Commerce repository standardization
Payment provider registry
AI conversation memory migration
Smoke test expansion
```

Target arsitektur saat ini:

```text
API / UI
  ↓
Service
  ↓
Repository
  ↓
DatabaseConnection
  ↓
PDO
```

## Main Runtime

### Composer Bootstrap

Status:

```text
DONE
```

Files:

```text
composer.json
bootstrap.php
config/runtime.php
config/helpers.php
```

### Legacy Config Shim

Status:

```text
DONE
```

Files:

```text
app/Config/config.php
```

Fungsi saat ini:

```text
Load config/runtime.php
Provide legacy APP_PATH constant
Register App\* autoload fallback
Initialize legacy PluginLoader adapter
```

## Database Migration

Status:

```text
DONE
```

Primary database class:

```text
src/Core/DatabaseConnection.php
```

Deprecated compatibility facade:

```text
src/Core/Database.php
```

Legacy adapter:

```text
app/Config/Database.php
```

Repository yang sudah memakai `DatabaseConnection`:

```text
src/Domains/Branch/BranchRepository.php
src/Domains/Product/ProductRepository.php
src/Domains/Cart/CartRepository.php
src/Domains/Auth/UserRepository.php
src/Domains/Order/OrderRepository.php
src/Domains/Payment/PaymentRepository.php
src/Domains/AI/ConversationMemoryRepository.php
```

## Plugin Migration

Status:

```text
DONE
```

Composer layer:

```text
src/Core/PluginLoader.php
src/Contracts/PluginInterface.php
```

Legacy adapter:

```text
app/Plugin/PluginLoader.php
app/Plugin/PluginInterface.php
```

Smoke test:

```text
tests/smoke_plugin_loader.php
```

## Hook Migration

Status:

```text
DONE
```

Composer layer:

```text
src/Core/HookManager.php
src/Core/Hooks.php
```

## Domain Migration

### Branch

Status:

```text
DONE
```

Files:

```text
src/Domains/Branch/BranchRepository.php
app/Models/BranchModel.php
```

`BranchModel` sekarang menjadi adapter ke `BranchRepository`.

### Product

Status:

```text
MOSTLY DONE
```

Files:

```text
src/Domains/Product/ProductRepository.php
src/Domains/Product/ProductService.php
src/Domains/Product/ProductSearchService.php
src/Domains/Product/ProductDTO.php
```

Remaining:

```text
Add richer product smoke/integration test
Audit plugin dependency to product data
```

### Cart

Status:

```text
MOSTLY DONE
```

Files:

```text
src/Domains/Cart/CartRepository.php
src/Domains/Cart/CartService.php
src/Domains/Cart/CartItemDTO.php
```

### Auth

Status:

```text
MOSTLY DONE
```

Files:

```text
src/Domains/Auth/AuthService.php
src/Domains/Auth/UserRepository.php
src/Domains/Auth/UserDTO.php
src/Domains/Auth/JwtService.php
src/Domains/Auth/PasswordHasher.php
```

### Order

Status:

```text
MOSTLY DONE
```

Files:

```text
src/Domains/Order/OrderRepository.php
src/Domains/Order/OrderService.php
src/Domains/Order/OrderDTO.php
src/Domains/Order/OrderItemDTO.php
```

### Payment

Status:

```text
MOSTLY DONE
```

Files:

```text
src/Domains/Payment/PaymentRepository.php
src/Domains/Payment/PaymentService.php
src/Domains/Payment/PaymentFactory.php
src/Domains/Payment/PaymentProviderInterface.php
```

Payment provider registry sudah tersedia:

```php
PaymentFactory::register('gateway', fn() => new GatewayProvider());
```

Smoke test:

```text
tests/smoke_payment_factory.php
```

### AI / Chatbot

Status:

```text
MOSTLY DONE
```

Files:

```text
src/Domains/Chatbot/ChatbotService.php
src/Domains/AI/ConversationMemoryService.php
src/Domains/AI/ConversationMemoryRepository.php
```

`ConversationMemoryRepository` sudah memakai `DatabaseConnection`.

## API Layer Audit

Status:

```text
PASS
```

File:

```text
public/api/index.php
```

Current endpoints:

```text
GET  /api/health
POST /api/auth/register
POST /api/auth/login
GET  /api/products
POST /api/cart/add
POST /api/cart/checkout
POST /api/chatbot/message
```

API layer hanya membuat DTO dan memanggil service.

Tidak ditemukan akses langsung ke:

```text
PDO
SQL
DatabaseConnection
Database::getConnection()
```

## Smoke Tests

Status:

```text
EXPANDED
```

Files:

```text
tests/smoke_core.php
tests/smoke_auth.php
tests/smoke_chatbot.php
tests/smoke_migration_bridge.php
tests/smoke_plugin_loader.php
tests/smoke_payment_factory.php
```

Composer scripts:

```bash
composer smoke:core
composer smoke:auth
composer smoke:chatbot
composer smoke:migration
composer smoke:plugin
composer smoke:payment
composer smoke:all
```

## Legacy Adapter Layer

Files intentionally kept:

```text
app/Config/config.php
app/Config/Database.php
app/Models/BaseModel.php
app/Models/BranchModel.php
app/Plugin/PluginLoader.php
app/Plugin/PluginInterface.php
```

Purpose:

```text
Prevent breaking old UI and plugin code while Composer migration continues
```

## Deprecated Files

```text
src/Core/Database.php
```

Reason:

```text
Use src/Core/DatabaseConnection.php instead
```

Current behavior:

```text
Database::getConnection() delegates to DatabaseConnection::getInstance()
```

## Remaining Tasks

### High Priority

```text
[ ] Run composer migrate:public-index locally
[ ] Run composer dump-autoload locally
[ ] Run composer smoke:all locally
[ ] Verify public/index.php landing page manually
[ ] Verify plugins load from real plugins/plugins.json
```

### Medium Priority

```text
[ ] Add API smoke test without web server
[ ] Add integration test for checkout flow
[ ] Add integration test for chatbot memory
[ ] Audit real plugin classes under plugins/*
[ ] Register payment gateway plugins through PaymentFactory::register()
```

### Low Priority

```text
[ ] Remove src/Core/Database.php after all references are gone
[ ] Remove App\* autoload fallback after legacy app classes are no longer used
[ ] Deprecate or remove app/ after UI and plugins fully migrate
```

## Local Verification Commands

```bash
composer dump-autoload
composer migrate:public-index
composer smoke:all
```

Search remaining legacy dependencies:

```bash
grep -R "KopiBot\\Core\\Database" -n src plugins app public || true
grep -R "Database::getConnection" -n src plugins app public || true
grep -R "App\\Config" -n src plugins app public || true
grep -R "App\\Models" -n src plugins app public || true
grep -R "App\\Plugin" -n src plugins app public || true
```

## Current Progress Estimate

```text
Composer Foundation       100%
Database Standardization  100%
Plugin Migration          100%
API Layer Audit           100%
Branch Migration          100%
Product Migration          90%
Cart Migration             90%
Auth Migration             90%
Order Migration            90%
Payment Migration          95%
Chatbot Migration          90%
Testing                    70%
Legacy Removal             75%
```

## Recommended Next Step

Create:

```text
tests/smoke_api.php
```

Purpose:

```text
Verify API routes can be registered and basic /api/health behavior works without running a web server.
```
