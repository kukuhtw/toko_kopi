# Controller and Service Migration Inventory

Dokumen ini mencatat status awal audit controller dan service pada branch `composer-mvp-core-migration-next`.

## Search Result

Pencarian code GitHub pada branch kerja untuk pola berikut belum menemukan hasil:

```text
namespace App\Controllers
namespace App\Services
```

Catatan:

- GitHub code search bisa tidak lengkap untuk branch kerja baru.
- Folder controller/service perlu diverifikasi lagi dari lokal dengan `find`.
- Saat ini entry point yang terlihat jelas adalah `public/index.php` dan `public/api/index.php`.

## Current Entry Points

### Landing Page

```text
public/index.php
```

Status migrasi:

```text
PARTIAL
```

Masih perlu menjalankan:

```bash
composer migrate:public-index
```

Target:

```text
public/index.php
  -> config/runtime.php
  -> Composer bootstrap
```

### REST API

```text
public/api/index.php
```

Status migrasi:

```text
MOSTLY COMPOSER READY
```

Endpoint utama:

```text
GET  /api/health
POST /api/auth/register
POST /api/auth/login
GET  /api/products
POST /api/cart/add
POST /api/cart/checkout
POST /api/chatbot/message
```

## Local Verification Commands

Jalankan dari root repo lokal:

```bash
find app -type f -name '*Controller.php' -print
find app -type f -name '*Service.php' -print
find app -type f -name '*Model.php' -print
find src -type f -name '*Controller.php' -print
find src -type f -name '*Service.php' -print
```

Cari namespace:

```bash
grep -R "namespace App\\Controllers" -n app src public || true
grep -R "namespace App\\Services" -n app src public || true
grep -R "extends BaseModel" -n app src public || true
```

Cari dependency legacy:

```bash
grep -R "use App\\" -n app plugins public src || true
grep -R "App\\Config\\Database" -n app plugins public src || true
grep -R "App\\Models" -n app plugins public src || true
grep -R "App\\Plugin" -n app plugins public src || true
```

## Migration Decision

Jika controller/service legacy memang tidak ada atau minim, prioritas migrasi berikutnya bukan Controller Migration, melainkan Domain Migration dari endpoint API yang sudah terlihat.

Urutan domain yang disarankan:

1. Product
2. Customer
3. Cart
4. Order
5. Payment
6. Promo
7. Loyalty
8. FAQ
9. Chatbot
10. Channel / Messaging

## Product Domain Plan

Target folder:

```text
src/Domains/Product/
```

Target class:

```text
ProductRepository.php
ProductService.php
ProductDTO.php
ProductSearchService.php
```

Minimum method:

```text
getMenu(int $tenantId, int $branchId): array
search(int $tenantId, int $branchId, string $query): array
findById(int $tenantId, int $branchId, int $productId): ?array
```

Dependency:

```text
KopiBot\Core\DatabaseConnection
```

## Cart Domain Plan

Target folder:

```text
src/Domains/Cart/
```

Target class:

```text
CartRepository.php
CartService.php
CartItemDTO.php
```

Minimum method:

```text
addItem(...): array
getItems(...): array
clear(...): void
checkout(...): array
```

## Order Domain Plan

Target folder:

```text
src/Domains/Order/
```

Target class:

```text
OrderRepository.php
OrderService.php
OrderDTO.php
```

Minimum method:

```text
createOrder(...): array
findById(...): ?array
updateStatus(...): bool
```

## Done Criteria

A module is migrated when:

```text
[ ] Composer class exists under src/Domains or src/Application
[ ] Legacy class delegates to Composer class if still needed
[ ] No new business logic is added to app/
[ ] Smoke test exists
[ ] API endpoint works
[ ] No direct DB connection outside DatabaseConnection
```
