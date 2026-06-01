# Shopee Live Commerce Installation

## Aktivasi Plugin

Tambahkan plugin ke konfigurasi plugin manager aplikasi.

## Endpoint

Webhook:

```text
/plugins/shopee-live-commerce/webhook.php?branch=1
```

Dashboard:

```text
/plugins/shopee-live-commerce/dashboard.php
```

Dashboard API:

```text
/plugins/shopee-live-commerce/ShopeeDashboardApi.php?branch=1
```

Status:

```text
/plugins/shopee-live-commerce/status.php
```

Cron:

```bash
php plugins/shopee-live-commerce/cron_sync.php 1
```

## Produksi

Diperlukan Partner ID, Partner Key, Shop ID, Access Token, dan Refresh Token dari Shopee Open Platform.
