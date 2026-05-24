# Moka Connect / Private Solution

Dokumen ini menjelaskan plugin `moka-connect-private-solution` yang sekarang tersedia di proyek ini untuk integrasi POS Moka.

Catatan penting:

- plugin ini mengikuti pola `Moka Connect / Private Solution`
- endpoint final dan payload real masih bisa berbeda sesuai approval merchant / partner dari Moka
- karena itu plugin dibuat configurable di level auth, path endpoint, outbound mapping, dan inbound mapping

---

## Cakupan Fitur

Versi saat ini sudah mendukung:

- pengaturan koneksi Moka per cabang
- pengaturan default global
- push order live ke endpoint Moka
- pull katalog live dari endpoint Moka
- queue sinkronisasi order
- retry policy dan retry delay
- status sinkronisasi per order
- webhook inbound untuk sinkron balik ke status order internal
- mapping UI tanpa edit code
- simulasi payload webhook dari dashboard
- runner otomatis untuk memproses queue lewat cron / scheduler

---

## File Utama

Plugin:

- [`plugins/moka-connect-private-solution/plugin.php`](../plugins/moka-connect-private-solution/plugin.php)
- [`plugins/moka-connect-private-solution/MokaConnectPrivateSolutionPlugin.php`](../plugins/moka-connect-private-solution/MokaConnectPrivateSolutionPlugin.php)
- [`plugins/moka-connect-private-solution/MokaConnectRepository.php`](../plugins/moka-connect-private-solution/MokaConnectRepository.php)
- [`plugins/moka-connect-private-solution/MokaConnectClient.php`](../plugins/moka-connect-private-solution/MokaConnectClient.php)
- [`plugins/moka-connect-private-solution/MokaConnectService.php`](../plugins/moka-connect-private-solution/MokaConnectService.php)

Dashboard dan endpoint:

- [`public/dashboard/branch/moka.php`](../public/dashboard/branch/moka.php)
- [`public/dashboard/branch/moka-webhook-test.php`](../public/dashboard/branch/moka-webhook-test.php)
- [`public/dashboard/super/moka.php`](../public/dashboard/super/moka.php)
- [`public/api/plugins/moka/webhook.php`](../public/api/plugins/moka/webhook.php)
- [`public/api/plugins/moka/process-queue.php`](../public/api/plugins/moka/process-queue.php)

---

## Dashboard Cabang

Halaman `branch/moka.php` sekarang menyediakan:

- test connection
- push pending orders
- retry failed orders
- pull live catalog
- snapshot order / product / customer / outlet
- recent sync logs
- status sinkronisasi per order
- shortcut ke halaman test webhook dan mapping

Halaman `branch/moka-webhook-test.php` menyediakan:

- simulasi payload webhook inbound
- export mapping config ke JSON
- import mapping config dari JSON
- audit trail perubahan status order akibat webhook

---

## Dashboard Super Admin

Halaman `super/moka.php` dipakai untuk melihat:

- total log sinkronisasi
- pending / retry queue lintas cabang
- branch status
- recent activity lintas cabang

---

## Queue dan Retry

Plugin menyimpan queue di tabel:

- `moka_sync_logs`
- `moka_order_sync_status`

Status yang dipakai antara lain:

- `pending`
- `success`
- `retry_scheduled`
- `failed`
- `config_missing`

Runner otomatis tersedia di endpoint:

```text
/api/plugins/moka/process-queue.php?token=YOUR_RUNNER_TOKEN
```

Token runner diatur dari settings global plugin. Endpoint ini bisa dipanggil dari:

- cron
- Windows Task Scheduler
- external monitor / worker

---

## Webhook Inbound

Endpoint:

```text
/api/plugins/moka/webhook.php?branch={id}
```

Kemampuan saat ini:

- verifikasi `webhook_secret` sederhana
- mapping payload inbound ke order internal
- update `order_status` internal
- update `payment_status` internal
- audit perubahan status
- proteksi agar sync inbound tidak memicu loop outbound

---

## Mapping UI

Mapping bisa diubah tanpa edit code dari settings plugin per cabang.

Outbound mapping yang bisa diatur:

- top-level container order
- order id key
- receipt key
- status key
- customer key
- payment key
- totals key
- line items key
- metadata key
- fulfillment key

Inbound mapping yang bisa diatur:

- path order number
- path external ref
- path order status
- path payment status
- map pasangan status order
- map pasangan status payment

Path inbound mendukung fallback dengan pemisah `|`, contoh:

```text
order.external_order_id|order.receipt_number|order_number
```

---

## Audit Trail

Audit inbound disimpan di tabel:

```text
moka_webhook_audits
```

Yang dicatat:

- source `webhook` atau `simulation`
- remote order status
- remote payment status
- old vs new internal order status
- old vs new internal payment status
- field yang berubah
- payload preview
- catatan hasil pemetaan

Ini membantu branch admin membedakan:

- payload yang berhasil dipetakan tetapi tidak mengubah status
- payload yang benar-benar memodifikasi order internal
- payload yang gagal dipetakan ke order

---

## Catatan Implementasi

Integrasi ini sudah jauh lebih siap dibanding scaffold awal, tetapi tetap ada dua hal yang biasanya perlu divalidasi di proyek nyata:

1. bentuk payload final dari approval Private Solution Moka
2. aturan status final antara status Moka dan status internal Anda

Karena itu, workflow yang disarankan adalah:

1. isi credential dan endpoint cabang
2. gunakan halaman simulasi webhook
3. finalkan mapping outbound dan inbound
4. aktifkan push live order
5. aktifkan runner otomatis
6. pantau audit trail dan order sync status
