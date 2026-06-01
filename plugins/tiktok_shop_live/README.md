# TikTok Shop Live Connector

Plugin ini menghubungkan AI Agent Commerce dengan TikTok Shop Live.

## Tujuan

1. Sinkron produk dari katalog internal ke TikTok Shop.
2. Menangkap event Live Shopping.
3. Menangkap order dari TikTok Shop.
4. Menampilkan order ke dashboard admin.
5. Mengirim notifikasi WhatsApp atau Telegram.
6. Memberikan rekomendasi AI selama live berlangsung.

## Arsitektur

TikTok Live
    |
    v
TikTok Shop API/Webhook
    |
    v
TikTok Shop Connector Plugin
    |
    +---- Product Sync
    +---- Order Sync
    +---- Live Event Listener
    +---- AI Recommendation Engine
    |
    v
KopiBot Database

## Database Tambahan

### tiktok_live_sessions

- id
- live_id
- title
- start_time
- end_time
- total_viewers
- total_orders

### tiktok_orders

- id
- tiktok_order_id
- customer_name
- total_amount
- order_status
- payload_json
- created_at

### tiktok_live_events

- id
- live_id
- event_type
- payload_json
- created_at

## AI Agent Scenario

Saat live berlangsung:

1. AI memonitor produk paling sering dilihat.
2. AI memonitor produk paling banyak dibeli.
3. AI membuat rekomendasi promo.
4. AI memberikan alert stok hampir habis.
5. AI memberikan insight ke host live.

Contoh:

'Matcha Latte sedang naik 320% dibanding 10 menit sebelumnya.'

'Bundle Matcha + Croissant diprediksi meningkatkan konversi 17%. '

## Integrasi WhatsApp

Jika ada order masuk:

TikTok Shop -> Plugin -> WhatsApp Gateway

Pesan:

Order Baru TikTok Shop
Nomor Order: XXXX
Customer: XXXX
Nilai Order: Rp XXXX

## Roadmap

- Live comment listener
- AI sentiment analysis
- AI host assistant
- Multi account TikTok Shop
- Affiliate performance analytics
