# Setup Sandbox iPaymu

Dokumen ini menjelaskan cara menyiapkan plugin `ipaymu-payment` agar siap dipakai untuk uji sandbox di proyek ini.

> Status implementasi:
> - plugin sudah terhubung ke flow `order.created`, checkout web, dan checkout via chat
> - plugin membuat link bayar redirect iPaymu
> - plugin menerima notifikasi ke endpoint internal proyek
> - endpoint API dan path dibuat fleksibel karena dokumentasi resmi iPaymu memakai contoh merchant-specific

---

## Ringkasan Flow

Plugin ini mengikuti pola redirect payment iPaymu:

1. Setelah order dibuat, plugin mengirim detail order ke endpoint iPaymu.
2. iPaymu mengembalikan `Url` atau `SessionID`.
3. Sistem menampilkan link bayar ke user.
4. Setelah pembayaran selesai, iPaymu mengirim notifikasi ke `notifyUrl` atau `unotify`.
5. Plugin memperbarui `payment_status` order lokal.

Referensi resmi:

- [iPaymu API Documentation](https://ipaymu.com/en/api-documentation/)
- [iPaymu Redirect Payment](https://ipaymu.com/en/api-redirect-payment/)
- [iPaymu Webstore Integration](https://ipaymu.com/en/webstore-integration/)

---

## File Plugin

```text
plugins/ipaymu-payment/
|-- plugin.php
|-- IPaymuClient.php
`-- IPaymuPaymentPlugin.php
```

---

## Pengaturan yang Perlu Diisi

Masuk ke:

- `Dashboard Branch -> Pengaturan Cabang`
- atau `Dashboard Super Admin -> App Settings -> Pengaturan Plugin Per Cabang`

Lalu cari section `iPaymu Payment Gateway`.

Field utama:

| Field | Wajib | Fungsi |
|------|------|--------|
| `Aktifkan iPaymu untuk cabang ini` | Ya | Menghidupkan plugin untuk cabang |
| `Mode` | Ya | `sandbox` atau `production` |
| `Base URL API` | Ya | Default sandbox: `https://sandbox.ipaymu.com/api/v2` |
| `Endpoint Path` | Ya | Default: `/payment` |
| `API Key` | Ya | API key dari dashboard iPaymu |
| `VA Number / Merchant VA` | Ya | Nomor VA merchant |
| `Success Redirect URL` | Disarankan | Halaman kembali setelah bayar |
| `Callback Token` | Opsional | Proteksi endpoint webhook internal |
| `Payment Method` | Opsional | Memaksa channel tertentu bila akun sandbox mendukung |

---

## URL Penting

Plugin akan memakai URL ini secara otomatis:

```text
POST /api/payment/notify.php?provider=ipaymu&branch={id}
```

Contoh lokal:

```text
http://localhost/toko_kopi/public/api/payment/notify.php?provider=ipaymu&branch=1
```

Gunakan URL ini sebagai `notifyUrl` / `unotify` di akun sandbox iPaymu bila diperlukan.

---

## Payload yang Dikirim

Plugin mengirim payload redirect payment berbasis item order, antara lain:

- `referenceId`
- `product`
- `qty`
- `price`
- `buyerName`
- `buyerPhone`
- `buyerEmail`
- `returnUrl`
- `notifyUrl`
- `comments`
- `currency`

Jika `Payment Method` diisi, plugin juga menambahkan `paymentMethod`.

---

## Cara Uji Sandbox

1. Aktifkan plugin `ipaymu-payment`.
2. Isi `API Key`, `VA`, `Base URL API`, dan `Endpoint Path`.
3. Simpan pengaturan.
4. Buat order lewat chat atau halaman order.
5. Pastikan respons checkout menampilkan `Bayar via iPaymu`.
6. Klik link pembayaran dan selesaikan skenario sandbox dari akun iPaymu.
7. Pastikan notifikasi masuk ke endpoint lokal dan `payment_status` order berubah.

---

## Checklist Jika Link Bayar Tidak Muncul

- plugin sudah aktif untuk cabang
- `API Key` terisi
- `VA Number / Merchant VA` terisi
- `Base URL API` benar
- `Endpoint Path` sesuai akun sandbox
- order memang berhasil dibuat

Lihat juga log PHP:

```text
storage/logs/php_error.log
```

---

## Catatan Teknis

- Plugin mencoba request JSON lebih dulu, lalu fallback ke `application/x-www-form-urlencoded`.
- Respons iPaymu dinormalisasi dari beberapa kemungkinan field seperti `Url`, `SessionUrl`, `SessionID`, dan `TransactionId`.
- Untuk callback, plugin mendukung pencarian order berdasarkan `referenceId`, lalu fallback ke `SessionID` atau `transaction_id` yang pernah disimpan saat link bayar dibuat.

---

## Batasan Saat Ini

- Implementasi sudah siap untuk sandbox redirect flow, tetapi tetap bergantung pada kecocokan endpoint akun merchant iPaymu.
- Validasi callback saat ini memakai token internal opsional; belum memakai verifikasi tanda tangan khusus iPaymu.
- Mapping status callback dibuat defensif berdasarkan nilai umum seperti `paid`, `success`, `settlement`, `pending`, dan `failed`.

Kalau merchant sandbox Anda memakai nama field callback yang sedikit berbeda, penyesuaian biasanya cukup kecil di `IPaymuPaymentPlugin.php`.
