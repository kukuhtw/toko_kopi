# Setup Midtrans Payment Gateway

Dokumen ini menjelaskan cara menyiapkan plugin `midtrans-payment` agar siap dipakai di proyek ini.

> Status implementasi:
> - plugin memakai Midtrans Snap
> - order baru akan membuat `snap_url`
> - link bayar bisa muncul di checkout web dan checkout via chat
> - notifikasi pembayaran diproses lewat endpoint internal proyek

---

## Ringkasan Flow

Plugin Midtrans di proyek ini bekerja seperti ini:

1. Order dibuat di sistem.
2. Plugin memanggil Midtrans Snap API.
3. Midtrans mengembalikan URL pembayaran.
4. URL itu disimpan dan ditampilkan ke customer.
5. Midtrans mengirim notifikasi ke endpoint `notify.php`.
6. Plugin memverifikasi signature dan mengubah `payment_status` order.

---

## File Plugin

```text
plugins/midtrans-payment/
|-- plugin.php
|-- MidtransClient.php
`-- MidtransPaymentPlugin.php
```

---

## Pengaturan yang Perlu Diisi

Masuk ke dashboard pengaturan plugin per cabang, lalu isi:

| Field | Wajib | Fungsi |
|------|------|--------|
| `Server Key` | Ya | Kredensial utama Midtrans Snap API |
| `Client Key` | Disarankan | Untuk kebutuhan Snap.js bila nanti dipakai di frontend |
| `Production mode` | Ya | Nonaktif untuk sandbox, aktif untuk production |

Catatan:

- Sandbox `Server Key` biasanya diawali `SB-Mid-server-...`
- Sandbox `Client Key` biasanya diawali `SB-Mid-client-...`

---

## Notification URL

Daftarkan URL ini di Midtrans Dashboard:

```text
/api/payment/notify.php?provider=midtrans&branch={id}
```

Contoh lokal:

```text
http://localhost/toko_kopi/public/api/payment/notify.php?provider=midtrans&branch=3
```

Di kode plugin, URL ini juga ditampilkan di card pengaturan Midtrans.

---

## Payload yang Dikirim

Saat order dibuat, plugin mengirim payload Snap dengan bagian utama:

- `transaction_details.order_id`
- `transaction_details.gross_amount`
- `customer_details.first_name`
- `customer_details.phone`
- `customer_details.email`
- `callbacks.finish`

Nilai `gross_amount` diambil dari `total_amount` order lokal.

---

## Mapping Status Pembayaran

Plugin menganggap order `paid` jika:

- `transaction_status = capture` dan `fraud_status = accept`
- atau `transaction_status = settlement`

Plugin menganggap order gagal jika:

- `cancel`
- `deny`
- `expire`
- `failure`

Selain itu, status order tidak diubah.

---

## Cara Uji Sandbox

1. Aktifkan plugin `midtrans-payment`.
2. Isi `Server Key` sandbox dan `Client Key` sandbox.
3. Pastikan `Production mode` tidak dicentang.
4. Simpan pengaturan.
5. Buat order dari chat atau checkout web.
6. Pastikan sistem menampilkan link `Bayar sekarang`.
7. Selesaikan pembayaran di sandbox Midtrans.
8. Pastikan notifikasi masuk dan `payment_status` order berubah.

---

## Troubleshooting Cepat

Jika link bayar tidak muncul:

- cek `Server Key`
- cek apakah order berhasil dibuat
- cek log PHP di `storage/logs/php_error.log`

Jika pembayaran sukses tapi status order tidak berubah:

- cek Notification URL di dashboard Midtrans
- pastikan parameter `branch` benar
- pastikan signature notifikasi lolos verifikasi

---

## Referensi Source

- [`plugins/midtrans-payment/MidtransPaymentPlugin.php`](../plugins/midtrans-payment/MidtransPaymentPlugin.php)
- [`public/api/payment/notify.php`](../public/api/payment/notify.php)
