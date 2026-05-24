# GoSend Delivery

Dokumen ini menjelaskan plugin `gosend-delivery` untuk integrasi delivery partner GoSend pada flow order delivery.

Catatan penting:

- berdasarkan halaman resmi GoSend yang tersedia publik, dokumentasi API final dan kredensial produksi diberikan setelah NDA, staging, dan UAT
- karena itu plugin ini dibuat `live-ready` dan configurable, bukan hardcode ke satu bentuk payload yang bisa berubah antar partner
- integrasi ini sudah siap untuk request HTTP live, webhook, pickup trigger, dan queue runner

---

## Cakupan Fitur

Versi saat ini sudah mendukung:

- queue booking GoSend saat order delivery dibuat
- push booking live ke endpoint partner GoSend
- trigger pickup langsung dari halaman detail order
- refresh / lookup status booking dari halaman detail order
- webhook inbound untuk update `order_status` internal
- retry queue dan runner otomatis
- audit webhook dan monitoring status delivery per order
- konfigurasi auth, header, path, dan method endpoint dari dashboard

---

## File Utama

Plugin:

- [`plugins/gosend-delivery/plugin.php`](../plugins/gosend-delivery/plugin.php)
- [`plugins/gosend-delivery/GoSendDeliveryPlugin.php`](../plugins/gosend-delivery/GoSendDeliveryPlugin.php)
- [`plugins/gosend-delivery/GoSendDeliveryRepository.php`](../plugins/gosend-delivery/GoSendDeliveryRepository.php)
- [`plugins/gosend-delivery/GoSendDeliveryClient.php`](../plugins/gosend-delivery/GoSendDeliveryClient.php)
- [`plugins/gosend-delivery/GoSendDeliveryService.php`](../plugins/gosend-delivery/GoSendDeliveryService.php)

Dashboard dan endpoint:

- [`public/dashboard/branch/gosend.php`](../public/dashboard/branch/gosend.php)
- [`public/dashboard/super/gosend.php`](../public/dashboard/super/gosend.php)
- [`public/dashboard/branch/order-detail.php`](../public/dashboard/branch/order-detail.php)
- [`public/dashboard/super/order-detail.php`](../public/dashboard/super/order-detail.php)
- [`public/api/plugins/gosend/webhook.php`](../public/api/plugins/gosend/webhook.php)
- [`public/api/plugins/gosend/process-queue.php`](../public/api/plugins/gosend/process-queue.php)

---

## Dashboard Cabang

Halaman `branch/gosend.php` sekarang menyediakan:

- overview mode runtime
- queue processing manual
- list status delivery per order
- recent logs booking / retry
- audit webhook inbound
- simulasi estimate
- informasi endpoint webhook dan queue runner

Selain itu, halaman detail order cabang sekarang punya:

- tombol `Request Pickup`
- tombol `Refresh Status GoSend`
- status delivery terakhir
- external reference
- shortcut tracking

---

## Dashboard Super Admin

Halaman `super/gosend.php` dipakai untuk melihat:

- total log GoSend lintas cabang
- pending / failed queue lintas cabang
- branch status
- recent logs
- link cepat ke settings dan docs GoSend

Halaman detail order super admin juga punya tombol:

- `Request Pickup`
- `Refresh Status GoSend`

---

## Konfigurasi Endpoint Live

Konfigurasi yang bisa diubah per cabang:

- `base_url`
- `auth_mode`
- `client_id`
- `pass_key`
- `client_id_header`
- `pass_key_header`
- `booking_path` dan `booking_method`
- `estimate_path` dan `estimate_method`
- `pickup_path` dan `pickup_method`
- `status_path` dan `status_method`
- `cancel_path` dan `cancel_method`
- `extra_headers`
- `merchant_key`
- `shop_id`

Path endpoint mendukung token:

```text
{external_ref}
{booking_id}
{order_number}
```

Contoh:

```text
/api/v1/bookings/{external_ref}/pickup
```

Mode auth yang tersedia:

- `header_pair`
- `basic`
- `bearer`

---

## Queue dan Pickup Trigger

Saat order delivery dibuat, plugin akan membuat queue event:

- `order.created`

Saat admin menekan `Request Pickup`, plugin akan:

1. memastikan booking GoSend sudah ada
2. jika belum ada, membuat booking dulu
3. memanggil endpoint pickup trigger
4. menyimpan `external_ref`, `tracking_url`, dan `delivery_status`

Queue disimpan di tabel:

- `gosend_delivery_logs`
- `gosend_delivery_orders`

Status queue yang digunakan:

- `pending`
- `success`
- `retry_scheduled`
- `failed`

---

## Webhook Inbound

Endpoint:

```text
/api/plugins/gosend/webhook.php?branch={id}
```

Kemampuan saat ini:

- verifikasi HMAC berbasis `webhook_secret`
- mapping `order_number` atau `external_ref`
- update status delivery internal plugin
- sinkron `order_status` internal bila status GoSend berubah
- audit payload inbound

Pemetaan status default:

- `confirmed`, `allocated`, `searching_driver`, `driver_assigned`, `picked_up`, `in_delivery`, `on_delivery` -> `on_delivery`
- `delivered`, `completed` -> `completed`
- `cancelled`, `canceled`, `failed` -> `cancelled`

---

## Runner Otomatis

Runner queue tersedia di endpoint:

```text
/api/plugins/gosend/process-queue.php?branch={id}&token=YOUR_RUNNER_TOKEN
```

Endpoint ini bisa dipanggil dari:

- cron Linux
- Windows Task Scheduler
- external worker / monitor

---

## Catatan Implementasi

Karena spesifikasi partner GoSend final biasanya diberikan setelah approval, workflow yang disarankan adalah:

1. isi credential dan endpoint cabang
2. uji booking di mode staging
3. sesuaikan header, path, dan method sesuai dokumen partner resmi
4. uji webhook inbound
5. aktifkan runner otomatis
6. gunakan `Request Pickup` dari detail order saat operasional berjalan
