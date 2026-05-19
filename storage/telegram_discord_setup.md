# Telegram & Discord Chatbot Setup

## Endpoint

- Telegram webhook:
  - `https://botlelang.com/toko_kopi/public/api/telegram/webhook.php?branch=<BRANCH_ID>`
- Discord interaction webhook:
  - `https://botlelang.com/toko_kopi/public/api/discord/webhook.php?branch=<BRANCH_ID>`

## Database Migration

Jalankan:

```sql
SOURCE database/add_telegram_discord_channels.sql;
```

## Telegram

Simpan setting bot per cabang ke `branch_bot_settings`:

```sql
INSERT INTO branch_bot_settings
    (branch_id, platform, bot_identifier, api_key, webhook_token, is_active)
VALUES
    (2, 'telegram', 'kopibot_bandung_bot', '<TELEGRAM_BOT_TOKEN>', '<SECRET_TOKEN>', 1)
ON DUPLICATE KEY UPDATE
    bot_identifier = VALUES(bot_identifier),
    api_key = VALUES(api_key),
    webhook_token = VALUES(webhook_token),
    is_active = VALUES(is_active);
```

Set webhook di Telegram:

```text
https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook?url=https://botlelang.com/toko_kopi/public/api/telegram/webhook.php?branch=2&secret_token=<SECRET_TOKEN>
```

## Discord

Discord diimplementasikan sebagai **slash command / interaction webhook**.
Perintah yang didukung:

- `/chat message:<isi pesan>`
- `/kopibot message:<isi pesan>`
- `/menu`
- `/promo`

Simpan setting bot per cabang:

```sql
INSERT INTO branch_bot_settings
    (branch_id, platform, bot_identifier, api_key, api_secret, is_active)
VALUES
    (2, 'discord', '<DISCORD_APPLICATION_ID>', '<DISCORD_BOT_TOKEN>', '<DISCORD_PUBLIC_KEY>', 1)
ON DUPLICATE KEY UPDATE
    bot_identifier = VALUES(bot_identifier),
    api_key = VALUES(api_key),
    api_secret = VALUES(api_secret),
    is_active = VALUES(is_active);
```

Catatan:

- `bot_identifier` diisi `application_id`.
- `api_secret` diisi `public key` dari Discord developer portal.
- endpoint memverifikasi header `X-Signature-Ed25519` dan `X-Signature-Timestamp`.

## Channel Value

Chatbot engine sekarang mendukung channel:

- `web`
- `whatsapp`
- `telegram`
- `discord`

## Catatan Implementasi

- Telegram membalas lewat `sendMessage`.
- Discord membalas langsung sebagai interaction response.
- Untuk Discord, mode yang didukung saat ini adalah **interaction command**, bukan membaca semua pesan channel secara bebas.
