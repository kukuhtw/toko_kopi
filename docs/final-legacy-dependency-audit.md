# Final Legacy Dependency Audit

Dokumen ini mencatat audit dependency legacy setelah payment subsystem dimigrasikan ke Composer-native layer.

Branch: `composer-mvp-core-migration-next`

## Audit Scope

Target pencarian dependency legacy:

```text
App Models
App Helpers
App Config
App Plugin
require_once app
include app
Database getConnection facade
```

## GitHub Search Result

Pencarian melalui GitHub code search pada branch kerja tidak menemukan referensi aktif untuk:

```text
App Models
App Helpers
App Config
App Plugin
```

Catatan penting:

```text
GitHub code search dapat tertinggal dari commit terbaru atau tidak lengkap untuk branch kerja baru.
Karena itu audit final tetap harus dikonfirmasi dengan grep lokal setelah branch ditarik ke environment development.
```

## Payment Subsystem Result

Payment subsystem sudah Composer-native untuk empat gateway utama:

```text
midtrans-payment
ipaymu-payment
nicepay-payment
xendit-payment
```

Semua plugin payment sekarang memakai:

```text
KopiBot Contracts PluginInterface
KopiBot Core HookManager
KopiBot Core DatabaseConnection
KopiBot Domains Branch BranchRepository
KopiBot Domains Order OrderPaymentService
KopiBot Security Csrf
```

Dependency yang sudah dihapus dari payment plugin:

```text
App Models OrderModel
App Helpers Csrf
App Config Database
App Plugin HookManager
App Plugin PluginInterface
App Models BranchModel
```

## Composer Replacements Now Available

| Legacy | Composer Replacement | Status |
|---|---|---|
| App Config Database | KopiBot Core DatabaseConnection | Done |
| App Plugin HookManager | KopiBot Core HookManager | Done |
| App Plugin PluginInterface | KopiBot Contracts PluginInterface | Done |
| App Models BranchModel | KopiBot Domains Branch BranchRepository | Done |
| App Models OrderModel | KopiBot Domains Order OrderPaymentService | Done |
| App Helpers Csrf | KopiBot Security Csrf | Done |

## Local Verification Commands

Run these commands after pulling the branch locally:

```bash
composer dump-autoload
composer verify
```

Then run dependency checks:

```bash
grep -R "App\\Models" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "App\\Helpers" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "App\\Config" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "App\\Plugin" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "require_once .*app" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "include .*app" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "Database::getConnection" -n . --exclude-dir=vendor --exclude-dir=.git || true
```

Expected result:

```text
No critical runtime references outside compatibility adapters or documentation.
```

## Compatibility Adapters Still Allowed Temporarily

These files may remain during final stabilization:

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

Reason:

```text
They protect old UI, old plugin contracts, and old runtime entry points while Composer migration is validated.
```

## Release Candidate Dependency Gate

The branch can pass the dependency gate if:

```text
[ ] composer verify passes
[ ] public landing page renders
[ ] API health endpoint works
[ ] payment plugin settings render
[ ] payment notification handlers do not fatal
[ ] grep App Models shows only adapters or docs
[ ] grep App Helpers shows only adapters or docs
[ ] grep App Plugin shows only adapters or docs
[ ] grep App Config shows only adapters or docs
```

## Current Conclusion

The payment layer is fully Composer-native.

The remaining migration risk is no longer in payment, database, hook, plugin contract, or CSRF. The only remaining risk is hidden legacy usage in non-payment plugins or old UI entry points that have not yet been exhaustively tested in a local runtime.

Recommended next audit target:

```text
LLM provider plugins
CRM and loyalty plugins
Channel plugins
public UI pages
```
