# WhatsApp Webhook Adapter

Endpoint:

```http
POST /webhooks/whatsapp.php
```

Payload minimal:

```json
{
  "tenant_id": 1,
  "branch_id": 1,
  "phone": "628123456789",
  "message": "jam buka?",
  "send_reply": false
}
```

Jika `send_reply=false`, endpoint hanya memproses chatbot dan mengembalikan response JSON tanpa mengirim via Fonnte. Ini cocok untuk testing lokal.

Untuk mengirim reply via Fonnte, isi `.env`:

```env
FONNTE_TOKEN=your-fonnte-token
FONNTE_SEND_URL=https://api.fonnte.com/send
```

Test lokal:

```bash
curl -X POST http://localhost:8000/webhooks/whatsapp.php \
  -H "Content-Type: application/json" \
  -d '{"tenant_id":1,"branch_id":1,"phone":"628123456789","message":"jam buka?","send_reply":false}'
```
