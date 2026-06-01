# TikTok Shop Live Connector

Plugin ini menambahkan fondasi integrasi TikTok Shop Live untuk AI Agent Commerce Platform.

## Fitur Awal

- Menerima webhook order dan live event.
- Menyimpan order TikTok ke tabel `tiktok_orders`.
- Menyimpan metrik live ke tabel `tiktok_live_metrics`.
- Menyimpan log sinkronisasi ke tabel `tiktok_sync_logs`.
- Membuat rekomendasi host live ke tabel `tiktok_ai_recommendations`.
- Menyiapkan mapping katalog lokal ke TikTok melalui `tiktok_catalog_maps`.

## Endpoint Webhook

Contoh endpoint lokal:

```text
/plugins/tiktok-shop-live/webhook.php?branch=1
```

## Contoh Payload Order

```json
{
  "event": "ORDER_CREATED",
  "order_id": "TTS-1001",
  "customer_name": "Budi Santoso",
  "total_amount": 125000,
  "status": "paid"
}
```

## Contoh Payload Live

```json
{
  "event": "LIVE_UPDATE",
  "live_id": "ROOM-001",
  "viewers": 2500,
  "comments": 480,
  "orders": 35,
  "revenue": 5750000
}
```

## Cara Test dengan curl

```bash
curl -X POST "https://domain-anda.com/plugins/tiktok-shop-live/webhook.php?branch=1" \
  -H "Content-Type: application/json" \
  -d '{"event":"LIVE_UPDATE","live_id":"ROOM-001","viewers":2500,"comments":480,"orders":35,"revenue":5750000}'
```

## Roadmap

- OAuth TikTok Shop Open Platform.
- Product sync dari `menu_items` ke TikTok Shop.
- Order import ke tabel order internal.
- Dashboard live monitor.
- AI Live Host Assistant berbasis LLM.
- Integrasi WhatsApp admin notification.
