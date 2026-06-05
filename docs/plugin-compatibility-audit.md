# Plugin Compatibility Audit

Dokumen ini mencatat status audit plugin nyata pada branch `composer-mvp-core-migration-next`.

## Audit Goal

Memastikan plugin tidak lagi bergantung pada core legacy jika sudah tersedia pengganti Composer.

Target utama:

```text
PluginInterface -> KopiBot Contracts PluginInterface
HookManager -> KopiBot Core HookManager
Database -> KopiBot Core DatabaseConnection
BranchModel -> KopiBot Domains Branch BranchRepository
```

## Status Legend

```text
READY          Sudah memakai Composer core untuk contract, hook, dan database.
NEEDS_ADAPTER  Masih butuh legacy adapter untuk model atau UI admin.
BLOCKED_BY_UI  Masih bergantung pada helper atau form dashboard legacy.
PENDING        Belum diaudit.
```

## Audited Plugins

| Plugin | Status | Composer Ready Parts | Legacy Dependency Remaining | Notes |
|---|---|---|---|---|
| midtrans-payment | READY with adapter | PluginInterface, HookManager, DatabaseConnection | OrderModel, Csrf helper | Payment settings and notification flow still use legacy UI/model adapter. |
| ipaymu-payment | READY with adapter | PluginInterface, HookManager, DatabaseConnection, BranchRepository | OrderModel, Csrf helper | Branch currency moved to BranchRepository. |
| nicepay-payment | READY with adapter | PluginInterface, HookManager, DatabaseConnection, BranchRepository | OrderModel, Csrf helper | Branch profile and currency moved to BranchRepository. |
| xendit-payment | READY with adapter | PluginInterface, HookManager, DatabaseConnection, BranchRepository | OrderModel, Csrf helper | Branch currency moved to BranchRepository. |

## Midtrans Payment Audit

File:

```text
plugins/midtrans-payment/MidtransPaymentPlugin.php
```

Migrated:

```text
App PluginInterface -> KopiBot Contracts PluginInterface
App HookManager -> KopiBot Core HookManager
App Config Database -> KopiBot Core DatabaseConnection
```

Remaining:

```text
App Models OrderModel
App Helpers Csrf
```

Reason:

```text
Order payment update flow and dashboard settings form still depend on legacy model and helper.
```

## iPaymu Payment Audit

File:

```text
plugins/ipaymu-payment/IPaymuPaymentPlugin.php
```

Migrated:

```text
App PluginInterface -> KopiBot Contracts PluginInterface
App HookManager -> KopiBot Core HookManager
App Config Database -> KopiBot Core DatabaseConnection
App Models BranchModel -> KopiBot Domains Branch BranchRepository
```

Remaining:

```text
App Models OrderModel
App Helpers Csrf
```

Reason:

```text
Payment notification flow still updates legacy order model.
Settings form still uses legacy CSRF helper.
```

## Nicepay Payment Audit

File:

```text
plugins/nicepay-payment/NicepayPaymentPlugin.php
```

Migrated:

```text
App PluginInterface -> KopiBot Contracts PluginInterface
App HookManager -> KopiBot Core HookManager
App Config Database -> KopiBot Core DatabaseConnection
App Models BranchModel -> KopiBot Domains Branch BranchRepository
```

Remaining:

```text
App Models OrderModel
App Helpers Csrf
```

Reason:

```text
Payment notification flow still updates legacy order model.
Settings form still uses legacy CSRF helper.
```

## Xendit Payment Audit

File:

```text
plugins/xendit-payment/XenditPaymentPlugin.php
```

Migrated:

```text
App PluginInterface -> KopiBot Contracts PluginInterface
App HookManager -> KopiBot Core HookManager
App Config Database -> KopiBot Core DatabaseConnection
App Models BranchModel -> KopiBot Domains Branch BranchRepository
```

Remaining:

```text
App Models OrderModel
App Helpers Csrf
```

Reason:

```text
Payment notification flow still updates legacy order model.
Settings form still uses legacy CSRF helper.
```

## Payment Plugin Audit Summary

```text
midtrans-payment  READY with adapter
ipaymu-payment    READY with adapter
nicepay-payment   READY with adapter
xendit-payment    READY with adapter
```

Payment plugin audit is now complete for the four payment gateways currently listed in the payment priority group.

## Remaining Plugin Categories To Audit

### LLM Provider

```text
gemini-llm
anthropic-llm
openrouter-llm
```

Priority:

```text
HIGH
```

Reason:

```text
LLM plugins may hook into chatbot response, prompt generation, token usage, or provider selection.
```

### WhatsApp and Channels

```text
fonnte-whatsapp
vonage-whatsapp
twilio-whatsapp
baileys-whatsapp
messagebird-whatsapp
telegram-channel
discord-channel
instagram-dm
```

Priority:

```text
MEDIUM
```

Reason:

```text
Channel plugins may depend on webhook payload, outgoing message service, and legacy settings UI.
```

### CRM and Loyalty

```text
customer-crm
loyalty-point
notifikasi-admin
```

Priority:

```text
HIGH
```

Reason:

```text
Likely depends on CustomerModel, OrderModel, notification hooks, and dashboard UI.
```

### Commerce Intelligence

```text
faq-rag
rekomendasi-promo
upselling
complaint-handler
```

Priority:

```text
MEDIUM
```

Reason:

```text
Likely depends on chatbot hooks, product data, customer history, and LLM provider.
```

### POS and Connector

```text
sirclo-full-connector
moka-connect-private-solution
kitchen-display
```

Priority:

```text
MEDIUM
```

Reason:

```text
Likely depends on order sync, product sync, and kitchen/order status flow.
```

## Known Legacy Dependencies Still Allowed Temporarily

```text
App Models OrderModel
App Helpers Csrf
```

Reason:

```text
OrderModel still acts as legacy adapter for dashboard and payment notification update.
Csrf helper still supports old admin form rendering.
```

## Recommended Next Steps

1. Create an OrderPaymentService under Composer domain.
2. Replace direct OrderModel payment update calls inside payment plugins.
3. Create Composer CSRF helper or keep legacy helper explicitly documented.
4. Audit LLM provider plugins.
5. Add plugin compatibility smoke test that loads audited plugins with Composer HookManager.

## Done Criteria For Each Plugin

```text
[ ] Uses KopiBot Contracts PluginInterface
[ ] Uses KopiBot Core HookManager
[ ] Uses KopiBot Core DatabaseConnection where direct DB access is still needed
[ ] Does not create a second hook registry
[ ] Does not create standalone database connection
[ ] Any remaining App dependency is documented as adapter or UI legacy
```

## Current Audit Progress

```text
Payment Plugin Audit     100 percent
Plugin Core Migration    100 percent
Hook Registry Migration  100 percent
Database Plugin Migration complete for audited payment plugins
Legacy UI Dependency     still present
```
