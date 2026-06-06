# Final Legacy Dependency Audit

Dokumen ini mencatat audit dependency legacy setelah payment, AI provider, customer, cart, loyalty, helper, skill, dan intent subsystem dimigrasikan ke Composer-native layer.

Branch: `composer-mvp-core-migration-next`

## Audit Scope

Target pencarian dependency legacy:

```text
App Models
App Helpers
App Config
App Plugin
App Skills
App Services IntentPatternRegistry
require_once app
include app
Database getConnection facade
```

## GitHub Search Result

Pencarian melalui GitHub code search pada branch kerja tidak menemukan referensi aktif untuk:

```text
App Config Database
App Plugin HookManager
App Plugin PluginInterface
App Models
App Helpers Currency
App Skills
App Services IntentPatternRegistry
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

## AI Provider Subsystem Result

Provider utama sudah Composer-native:

```text
openrouter-llm
gemini-llm
anthropic-llm
```

Dependency yang sudah diganti:

```text
App Config Database -> KopiBot Core DatabaseConnection
App Plugin HookManager -> KopiBot Core HookManager
App Plugin PluginInterface -> KopiBot Contracts PluginInterface
```

## Customer, Cart, Loyalty Result

Composer replacements yang sudah tersedia:

```text
KopiBot Domains Customer CustomerRepository
KopiBot Domains Customer CustomerNormalizer
KopiBot Domains Cart CartRepository
KopiBot Support Currency
KopiBot Skills SkillInterface
KopiBot Skills SkillRegistry
KopiBot Intent IntentPatternRegistry
```

Loyalty repository dan loyalty skill sudah memakai Composer data layer:

```text
LoyaltyPointRepository -> DatabaseConnection + HookManager
LoyaltyPointSkill -> CartRepository
```

## Composer Replacements Now Available

| Legacy | Composer Replacement | Status |
|---|---|---|
| App Config Database | KopiBot Core DatabaseConnection | Done |
| App Plugin HookManager | KopiBot Core HookManager | Done |
| App Plugin PluginInterface | KopiBot Contracts PluginInterface | Done |
| App Models BranchModel | KopiBot Domains Branch BranchRepository | Done |
| App Models OrderModel | KopiBot Domains Order OrderPaymentService | Done |
| App Models CustomerModel | KopiBot Domains Customer CustomerRepository / CustomerNormalizer | Done |
| App Models CartModel | KopiBot Domains Cart CartRepository | Done |
| App Helpers Csrf | KopiBot Security Csrf | Done |
| App Helpers Currency | KopiBot Support Currency | Done |
| App Skills SkillInterface | KopiBot Skills SkillInterface | Done |
| App Skills SkillRegistry | KopiBot Skills SkillRegistry | Done |
| App Services IntentPatternRegistry | KopiBot Intent IntentPatternRegistry | Done |

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
grep -R "App\\Skills" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "App\\Services\\IntentPatternRegistry" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "require_once .*app" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "include .*app" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "Database::getConnection" -n . --exclude-dir=vendor --exclude-dir=.git || true
grep -R "Database::getInstance" -n . --exclude-dir=vendor --exclude-dir=.git || true
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
app/Helpers/Csrf.php
app/Helpers/Currency.php
app/Skills/SkillInterface.php
app/Skills/SkillRegistry.php
app/Services/IntentPatternRegistry.php
src/Core/Database.php
```

Reason:

```text
They protect old UI, old plugin contracts, and old runtime entry points while Composer migration is validated.
```

## Release Candidate Dependency Gate

The branch can pass the dependency gate if:

```text
[ ] composer dump-autoload passes
[ ] composer verify passes
[ ] public landing page renders
[ ] API health endpoint works
[ ] payment plugin settings render
[ ] payment notification handlers do not fatal
[ ] LLM provider selection works
[ ] loyalty point balance, redeem, and clear redeem work
[ ] grep App Models shows only adapters or docs
[ ] grep App Helpers shows only adapters or docs
[ ] grep App Plugin shows only adapters or docs
[ ] grep App Config shows only adapters or docs
[ ] grep App Skills shows only adapters or docs
[ ] grep App Services IntentPatternRegistry shows only adapters or docs
```

## Current Conclusion

The Composer MVP Core migration is architecturally complete for the audited high-risk subsystems:

```text
Database
Hook system
Plugin contract
Payment gateways
AI providers
Customer domain
Cart domain
Loyalty domain
CSRF
Currency formatting
Skill framework
Intent pattern framework
```

Remaining risk is limited to runtime verification of old UI pages, untested channel plugins, and compatibility adapters that are intentionally kept during stabilization.
