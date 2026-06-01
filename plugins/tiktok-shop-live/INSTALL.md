# TikTok Shop Live Installation

## Aktivasi Plugin

Tambahkan ke plugins/plugins.json:

```json
"tiktok-shop-live": {
  "active": true
}
```

## Jalankan Cron

```bash
php plugins/tiktok-shop-live/cron_sync.php 1
```

## Webhook

```text
/plugins/tiktok-shop-live/webhook.php?branch=1
```

## Dashboard

```text
/plugins/tiktok-shop-live/dashboard.php
```

## Roadmap

- OAuth callback.
- Product mapping UI.
- Order mapping ke order internal.
- AI live copilot.
- WhatsApp admin notification.
