# Plugin Migration Inventory

Dokumen ini mencatat inventory awal plugin pada branch `composer-mvp-core-migration-next` berdasarkan `plugins/plugins.json`.

## Status Plugin Migration Layer

Selesai:

- `src/Core/PluginLoader.php`
- `src/Contracts/PluginInterface.php`
- `app/Plugin/PluginLoader.php` sebagai adapter ke Composer
- `app/Plugin/PluginInterface.php` sebagai adapter ke Composer contract
- `tests/smoke_plugin_loader.php`

## Active Plugins

### Core Example

| Plugin | Status | Prioritas Migrasi |
|---|---:|---:|
| example-plugin | active | Low |
| example-skill-plugin | active | Low |

### Payment

| Plugin | Status | Prioritas Migrasi |
|---|---:|---:|
| midtrans-payment | active | High |
| ipaymu-payment | active | High |
| nicepay-payment | active | High |
| xendit-payment | inactive | Medium |

Target domain:

```text
src/Domains/Payment/
src/Infrastructure/Payment/
```

Hal yang perlu dicek:

```text
API key handling
Webhook callback
Payment status mapping
Order update dependency
Database write dependency
```

### LLM Provider

| Plugin | Status | Prioritas Migrasi |
|---|---:|---:|
| gemini-llm | active | High |
| anthropic-llm | active | High |
| openrouter-llm | active | High |

Target domain:

```text
src/Infrastructure/LLM/
src/Domains/Chatbot/
```

Hal yang perlu dicek:

```text
API key handling
Prompt template
Retry handling
Timeout handling
Token usage logging
```

### WhatsApp / Channel Provider

| Plugin | Status | Prioritas Migrasi |
|---|---:|---:|
| fonnte-whatsapp | active | High |
| vonage-whatsapp | active | High |
| twilio-whatsapp | active | High |
| baileys-whatsapp | active | Medium |
| messagebird-whatsapp | active | Medium |
| telegram-channel | active | Medium |
| discord-channel | active | Medium |
| instagram-dm | active | Medium |

Target domain:

```text
src/Channels/
src/Infrastructure/Messaging/
```

Hal yang perlu dicek:

```text
Webhook payload
Outgoing message format
Provider credential storage
Message status callback
Rate limit handling
```

### Delivery Provider

| Plugin | Status | Prioritas Migrasi |
|---|---:|---:|
| rajaongkir-delivery | active | Medium |
| kiriminaja-delivery | active | Medium |
| gosend-delivery | active | Medium |

Target domain:

```text
src/Domains/Delivery/
src/Infrastructure/Delivery/
```

Hal yang perlu dicek:

```text
Shipping rate calculation
Courier service mapping
Delivery status mapping
Order address dependency
```

### Commerce Intelligence

| Plugin | Status | Prioritas Migrasi |
|---|---:|---:|
| rekomendasi-promo | active | Medium |
| upselling | active | Medium |
| complaint-handler | active | Medium |
| faq-rag | active | High |

Target domain:

```text
src/Domains/Promo/
src/Domains/Recommendation/
src/Domains/FAQ/
src/Domains/Complaint/
```

Hal yang perlu dicek:

```text
Hook dependency
Product dependency
Customer history dependency
LLM dependency
Vector store dependency
```

### CRM and Loyalty

| Plugin | Status | Prioritas Migrasi |
|---|---:|---:|
| loyalty-point | active | High |
| customer-crm | active | High |
| notifikasi-admin | active | Medium |

Target domain:

```text
src/Domains/Loyalty/
src/Domains/Customer/
src/Domains/Notification/
```

Hal yang perlu dicek:

```text
Customer model dependency
Order event dependency
Point transaction table
Notification channel dependency
```

### POS and Commerce Connector

| Plugin | Status | Prioritas Migrasi |
|---|---:|---:|
| sirclo-full-connector | active | High |
| moka-connect-private-solution | active | High |
| cms-berita | active | Low |
| kitchen-display | active | Medium |

Target domain:

```text
src/Infrastructure/POS/
src/Domains/Kitchen/
src/Domains/CMS/
```

Hal yang perlu dicek:

```text
Sync product
Sync order
Webhook callback
Credential handling
Conflict handling
```

### UI and Template

| Plugin | Status | Prioritas Migrasi |
|---|---:|---:|
| themes | active | Medium |
| rich-chat-ui | active | Medium |
| bakery-template | active | Low |
| fruit-template | active | Low |
| meat-veggie-template | active | Low |
| coffee-template | active | Low |
| pharmacy-template | active | Low |
| indonesian-resto-template | active | Low |

Target domain:

```text
src/Domains/Template/
src/Infrastructure/UI/
```

Hal yang perlu dicek:

```text
Seed data dependency
Theme hook dependency
Asset path dependency
Landing page dependency
```

## Dependency Audit Checklist

For every plugin, search these patterns:

```php
use App\Config\Database;
use App\Models\;
use App\Plugin\HookManager;
use App\Plugin\PluginInterface;
use App\Plugin\PluginLoader;
```

Migration target:

```php
use KopiBot\Core\DatabaseConnection;
use KopiBot\Core\HookManager;
use KopiBot\Contracts\PluginInterface;
```

## Recommended Migration Order

1. Payment plugins
2. LLM provider plugins
3. WhatsApp/channel plugins
4. CRM and Loyalty plugins
5. FAQ/RAG and complaint plugins
6. POS connector plugins
7. Delivery plugins
8. UI/theme/template plugins
9. Example plugins

## Done Criteria

A plugin is considered migrated when:

```text
[ ] No direct dependency to App\Config\Database
[ ] No direct dependency to App\Models\*
[ ] No direct dependency to App\Plugin\HookManager
[ ] Uses KopiBot\Contracts\PluginInterface or compatible legacy adapter
[ ] Uses KopiBot\Core\HookManager
[ ] Has smoke test or integration test
[ ] Can be loaded by src/Core/PluginLoader.php
```
