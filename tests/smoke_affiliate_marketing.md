# Affiliate Marketing Smoke Test

## Persiapan

1. Pastikan schema plugin affiliate sudah terpasang dari `plugins/affiliate_marketing/install.sql`.
2. Untuk database existing, jalankan patch `database/add_affiliate_marketing_order_id_unique.sql`.
3. Pastikan ada minimal:
   - 1 admin dengan permission `affiliate.view`, `affiliate.manage_users`, `affiliate.manage_campaigns`, `affiliate.report`, `affiliate.commission`
   - 1 branch aktif
   - 1 campaign affiliate aktif

## Smoke Test Admin

1. Buka `plugins/affiliate_marketing/views/admin_dashboard.php` melalui entry admin yang dipakai aplikasi.
2. Verifikasi dashboard tampil tanpa error dan kartu summary terisi.
3. Buat affiliate baru dari form `Buat Affiliate`.
   Expected:
   - submit berhasil
   - pesan sukses muncul
   - data affiliate baru masuk ke tabel `affiliate_users`
4. Buat campaign baru dari form `Buat Campaign`.
   Expected:
   - submit berhasil
   - campaign masuk ke tabel `affiliate_campaigns`
5. Coba submit form dengan token CSRF invalid atau tanpa token.
   Expected:
   - request ditolak `403`
6. Login sebagai admin tanpa permission affiliate lengkap.
   Expected:
   - endpoint report/action affiliate ditolak `403`
7. Uji export:
   - `plugins/affiliate_marketing/routes.php?page=export_commissions`
   - `plugins/affiliate_marketing/routes.php?page=export_traffic`
   - `plugins/affiliate_marketing/routes.php?page=export_fraud`
   Expected:
   - file CSV terunduh
   - akses tetap mengikuti permission

## Smoke Test Tracking dan Order

1. Siapkan 1 affiliate aktif dan 1 campaign aktif.
2. Buka storefront dengan query `?aff={affiliate_code}&campaign={campaign_code}`.
   Expected:
   - cookie `affiliate_code`, `affiliate_campaign`, `affiliate_tracking_code` terbentuk
   - record klik masuk ke `affiliate_clicks`
3. Buat order checkout dari sesi yang sama.
   Expected:
   - 1 row baru masuk ke `affiliate_orders`
   - `order_payment_status = unpaid`
   - `status = pending`
4. Trigger hook/payment handler yang memanggil `affiliate_plugin_on_payment_paid($orderId)`.
   Expected:
   - row order affiliate berubah ke `order_payment_status = paid`
   - `status = waiting_clearance`
   - `clearance_until` terisi
5. Jalankan script clearance `plugins/affiliate_marketing/cron/approve_commission_clearance.php` setelah memundurkan data `clearance_until` atau memakai data uji yang sudah matang.
   Expected:
   - komisi eligible pindah ke `status = approved`
6. Submit form `Tandai Komisi Paid`.
   Expected:
   - status order affiliate berubah ke `paid`

## Smoke Test Idempotency

1. Panggil `affiliate_plugin_on_order_created($orderId, $orderTotal)` dua kali untuk `order_id` yang sama.
   Expected:
   - tetap hanya ada satu row di `affiliate_orders` untuk `order_id` tersebut
2. Jalankan query:

```sql
SELECT order_id, COUNT(*) AS total
FROM affiliate_orders
GROUP BY order_id
HAVING COUNT(*) > 1;
```

Expected:
- hasil kosong

## Smoke Test Portal Affiliate

1. Login sebagai affiliate melalui flow portal.
2. Buka dashboard affiliate.
   Expected:
   - total click, total order, total sales, dan komisi tampil
   - angka summary tidak berlipat saat affiliate punya banyak click dan banyak order
3. Bandingkan dashboard dengan query manual:

```sql
SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_user_id = ?;
SELECT COUNT(DISTINCT order_id), COALESCE(SUM(order_total), 0) FROM affiliate_orders WHERE affiliate_user_id = ?;
```

Expected:
- angka dashboard sama dengan hasil query manual
