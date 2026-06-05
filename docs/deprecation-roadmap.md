# Deprecation Roadmap

Dokumen ini menjadi panduan penghapusan bertahap layer legacy setelah migrasi Composer MVP Core.

Branch: `composer-mvp-core-migration-next`

## Goal

Mengurangi ketergantungan terhadap folder legacy `app/` tanpa memutus UI lama, plugin lama, checkout flow, dan chatbot flow yang sudah berjalan.

Target akhir:

```text
public entry point
  -> bootstrap.php
  -> config/runtime.php
  -> Composer Autoload
  -> src/Core and src/Domains
```

## Status Category

```text
KEEP        Masih dibutuhkan langsung oleh runtime.
ADAPTER     Legacy bridge yang sengaja dipertahankan sementara.
DEPRECATED  Sudah ada pengganti Composer, boleh dihapus setelah semua referensi hilang.
REMOVE      Aman dihapus setelah verification suite lulus.
```

## Legacy Adapter Inventory

| File | Status | Composer Replacement | Notes |
|---|---|---|---|
| app/Config/config.php | ADAPTER | config/runtime.php | Compatibility shim untuk autoload fallback dan plugin init. |
| app/Config/Database.php | ADAPTER | src/Core/DatabaseConnection.php | Legacy DB facade mengarah ke DatabaseConnection. |
| app/Models/BaseModel.php | ADAPTER | Repository classes | Masih berguna untuk model legacy yang belum dipetakan. |
| app/Models/BranchModel.php | ADAPTER | src/Domains/Branch/BranchRepository.php | Sudah tipis, hanya delegasi ke repository Composer. |
| app/Plugin/PluginLoader.php | ADAPTER | src/Core/PluginLoader.php | Sudah delegasi ke Composer PluginLoader. |
| app/Plugin/PluginInterface.php | ADAPTER | src/Contracts/PluginInterface.php | Sudah extends Composer contract. |
| src/Core/Database.php | DEPRECATED | src/Core/DatabaseConnection.php | Compatibility facade, tidak membuat koneksi mandiri lagi. |

## Phase 1: Stabilization

Status: `IN PROGRESS`

Checklist:

```text
[ ] composer dump-autoload
[ ] composer verify
[ ] composer migrate:public-index
[ ] Manual test public/index.php
[ ] Manual test public/api/index.php
[ ] Validate plugins/plugins.json with real plugins
```

Exit criteria:

```text
composer verify returns success
Landing page still renders
API health endpoint works
Cart checkout integration passes
Chat memory integration passes
```

## Phase 2: Reference Audit

Status: `PENDING LOCAL VERIFICATION`

Tujuan:

```text
Cari semua penggunaan App Config
Cari semua penggunaan App Models
Cari semua penggunaan App Plugin
Cari semua penggunaan legacy Database facade
```

Expected result:

```text
Only adapter files or intentionally legacy plugin files should remain.
```

## Phase 3: Deprecate Legacy App Models

Candidate:

```text
app/Models/BranchModel.php
```

Replacement:

```text
src/Domains/Branch/BranchRepository.php
```

Safe removal requirements:

```text
[ ] No new BranchModel usage outside legacy UI
[ ] public/index.php uses BranchRepository or BranchService directly
[ ] composer verify passes
[ ] Landing page still works
```

## Phase 4: Deprecate Legacy Plugin Layer

Candidates:

```text
app/Plugin/PluginLoader.php
app/Plugin/PluginInterface.php
```

Replacement:

```text
src/Core/PluginLoader.php
src/Contracts/PluginInterface.php
```

Safe removal requirements:

```text
[ ] All plugins use Composer plugin contract
[ ] All plugins use Composer HookManager
[ ] No plugin references legacy plugin interface
[ ] No plugin references legacy plugin loader
[ ] Plugin smoke test passes with real plugin config
```

## Phase 5: Remove Deprecated Database Facade

Candidate:

```text
src/Core/Database.php
```

Replacement:

```text
src/Core/DatabaseConnection.php
```

Safe removal requirements:

```text
[ ] No source code references legacy Database facade
[ ] No source code calls legacy getConnection facade
[ ] composer verify passes
```

## Phase 6: Remove App Autoload Fallback

Candidate:

```text
app/Config/config.php
```

Replacement:

```text
config/runtime.php
```

Safe removal requirements:

```text
[ ] No runtime dependency on App namespace classes
[ ] public/index.php loads config/runtime.php directly
[ ] plugin system uses Composer classes only
[ ] all smoke and integration tests pass
```

## Risk Register

| Risk | Impact | Mitigation |
|---|---|---|
| Real plugin still imports legacy plugin interface | Plugin load failure | Keep adapter until all plugins audited. |
| Landing page still imports BranchModel | UI failure | Keep BranchModel adapter until public index fully migrated. |
| Hidden model still extends BaseModel | Runtime DB error | Keep BaseModel adapter until local audit confirms no usage. |
| Payment plugin not registered in PaymentFactory | Checkout fallback to mock | Require payment plugin registration test. |
| Composer autoload cache stale | Class not found | Run composer dump-autoload after migration batch. |

## Verification Commands

```bash
composer dump-autoload
composer verify
composer smoke:all
composer integration:all
```

## Release Candidate Checklist

```text
[ ] composer install succeeds
[ ] composer dump-autoload succeeds
[ ] composer verify succeeds
[ ] public/index.php renders landing page
[ ] public/api/index.php responds to health endpoint
[ ] plugins config loads without fatal error
[ ] PaymentFactory registered gateways are visible
[ ] Chatbot memory flow works
[ ] Checkout flow works
```

## Current Recommendation

Do not remove `app/` yet.

Saat ini `app/` sudah berfungsi sebagai compatibility layer. Penghapusan aman dilakukan setelah:

```text
1. composer verify sukses di lokal
2. dependency legacy audit bersih
3. real plugin audit selesai
4. landing page tidak bergantung ke legacy model
```
