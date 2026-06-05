# Composer MVP Core Migration

Dokumen ini mencatat langkah migrasi bertahap dari struktur legacy `app/` ke struktur Composer `src/` pada branch `composer-mvp-core`.

## Status

Sudah tersedia:

- `composer.json`
- `bootstrap.php`
- `config/runtime.php`
- `public/api/index.php`
- `scripts/migrate-public-index-runtime.php`

## Langkah 1: Landing page memakai Composer runtime

Jalankan:

```bash
php scripts/migrate-public-index-runtime.php
```

Script ini mengubah bootstrap `public/index.php` dari config legacy langsung menjadi runtime bridge Composer.

Sebelum:

```php
require_once dirname(__DIR__) . '/app/Config/config.php';
```

Sesudah:

```php
require_once dirname(__DIR__) . '/config/runtime.php';

if (file_exists(dirname(__DIR__) . '/app/Config/config.php')) {
    require_once dirname(__DIR__) . '/app/Config/config.php';
}
```

## Langkah 2: Migrasi HookManager

Target akhir:

```php
use KopiBot\Core\HookManager;
```

menggantikan:

```php
use App\Plugin\HookManager;
```

Rekomendasi lokasi class baru:

```text
src/Core/HookManager.php
```

Kontrak minimum yang harus dipertahankan:

```text
addAction(string $hook, callable $callback, int $priority = 10): void
doAction(string $hook, mixed ...$args): void
addFilter(string $hook, callable $callback, int $priority = 10): void
applyFilters(string $hook, mixed $value, mixed ...$args): mixed
hasAction(string $hook): bool
hasFilter(string $hook): bool
clear(): void
```

## Langkah 3: Migrasi Database

Target akhir:

```php
use KopiBot\Core\Database;
```

menggantikan:

```php
use App\Config\Database;
```

Database harus memakai konfigurasi dari `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kopibot
DB_USERNAME=root
DB_PASSWORD=
```

## Langkah 4: Migrasi BranchModel

Target rekomendasi:

```text
src/Domains/Branch/BranchRepository.php
src/Domains/Branch/BranchService.php
```

Landing page saat ini membutuhkan method:

```text
getActive(): array
getAllSettings(int $branchId): array
```

## Langkah 5: Hapus autoloader manual

Setelah semua class legacy penting pindah ke `src/`, hapus autoloader manual dari:

```text
app/Config/config.php
```

Composer menjadi satu-satunya autoloader.

## Test setelah setiap tahap

```bash
composer dump-autoload
composer serve
curl http://localhost:8000/
curl http://localhost:8000/api/health
composer smoke:core
composer smoke:auth
composer smoke:chatbot
```
