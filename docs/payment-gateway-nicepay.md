# Setup Sandbox Nicepay

Dokumen ini menjelaskan cara menyiapkan plugin `nicepay-payment` agar siap dipakai untuk uji sandbox di proyek ini.

> Status implementasi:
> - plugin memakai flow resmi `Registration -> Redirect Payment`
> - plugin membuat `merchanttoken` SHA-256
> - plugin menyimpan `txid`, lalu membangun checkout URL Nicepay
> - plugin menerima callback server-to-server di endpoint internal proyek

---

## Ringkasan Flow

Plugin ini mengikuti pola Checkout API Nicepay:

1. Saat order dibuat, sistem mengirim registrasi ke endpoint Nicepay.
2. Nicepay mengembalikan `txid`.
3. Sistem mengarahkan user ke `redirect/v2/payment?txid=...`.
4. Nicepay mengirim callback customer-facing ke `callbackurl`.
5. Nicepay mengirim server-to-server notification ke `dbprocessurl`.
6. Plugin memperbarui `payment_status` order lokal.

Referensi resmi:

- [NICEPAY Registration - API Checkout](https://docs.nicepay.co.id/nicepay-api-v2-registration-api-checkout)
- [NICEPAY Payment - API Checkout](https://docs.nicepay.co.id/nicepay-api-v2-payment-api-checkout)
- [NICEPAY QRIS Registration](https://docs.nicepay.co.id/nicepay-api-non-snap-registration-api-qris)

---

## File Plugin

```text
plugins/nicepay-payment/
|-- plugin.php
|-- NicepayClient.php
`-- NicepayPaymentPlugin.php
```

---

## Pengaturan yang Perlu Diisi

Masuk ke:

- `Dashboard Branch -> Pengaturan Cabang`
- atau `Dashboard Super Admin -> App Settings -> Pengaturan Plugin Per Cabang`

Lalu cari section `Nicepay Payment Gateway`.

Field utama:

| Field | Wajib | Fungsi |
|------|------|--------|
| `Aktifkan Nicepay untuk cabang ini` | Ya | Menghidupkan plugin |
| `Mode` | Ya | `sandbox` atau `production` |
| `Registration Base URL` | Ya | Default sandbox: `https://dev.nicepay.co.id` |
| `Checkout URL` | Ya | Default sandbox: `https://dev.nicepay.co.id/nicepay/redirect/v2/payment` |
| `IMID / Merchant ID` | Ya | IMID dari akun sandbox Nicepay |
| `Merchant Key` | Ya | Merchant key sandbox |
| `Pay Method` | Opsional | Kosong = semua metode; isi kode jika ingin membatasi |
| `Expiry Minutes` | Disarankan | 5 sampai 20 menit |
| `Success Redirect URL` | Disarankan | Halaman kembali customer |
| `Callback Token` | Opsional | Proteksi endpoint webhook internal |

---

## URL Penting

Plugin otomatis memakai endpoint ini:

### DB Process URL

```text
POST /api/payment/notify.php?provider=nicepay&branch={id}
```

Contoh lokal:

```text
http://localhost/toko_kopi/public/api/payment/notify.php?provider=nicepay&branch=1
```

### Callback URL

Default:

```text
http://localhost/toko_kopi/public/order.php
```

Nilai ini bisa diganti lewat field `Success Redirect URL`.

---

## Payload Registrasi yang Dikirim

Plugin membangun payload registrasi dengan field utama:

- `timestamp`
- `imid`
- `merchanttoken`
- `referenceno`
- `amt`
- `currency`
- `goodsnm`
- `billingnm`
- `billingphone`
- `billingemail`
- `billingaddr`
- `billingcity`
- `billingstate`
- `billingpostcd`
- `billingcountry`
- `callbackurl`
- `dbprocessurl`
- `userip`
- `cartdata`
- `paymentexpdt`
- `paymentexptm`

Jika `Pay Method` diisi, plugin juga menambahkan `paymethod`.

---

## Merchant Token

Plugin memakai rumus resmi Nicepay:

```text
sha256(timestamp + imid + referenceno + amt + merchantkey)
```

Perhitungan ini dilakukan di `NicepayClient.php`.

---

## Cara Uji Sandbox

1. Aktifkan plugin `nicepay-payment`.
2. Isi `IMID`, `Merchant Key`, `Registration Base URL`, dan `Checkout URL`.
3. Simpan pengaturan.
4. Buat order dari chat atau checkout web.
5. Pastikan sistem menampilkan `Bayar via Nicepay`.
6. Klik link yang terbentuk dari `txid`.
7. Selesaikan simulasi pembayaran di sandbox Nicepay.
8. Pastikan callback masuk dan status order berubah.

---

## Checklist Jika Registrasi Gagal

- plugin sudah aktif untuk cabang
- `IMID` benar
- `Merchant Key` benar
- `Registration Base URL` masih domain sandbox
- `Checkout URL` masih domain sandbox
- `Pay Method` sesuai channel yang diaktifkan merchant
- `Expiry Minutes` berada di rentang yang diizinkan

Lihat juga:

```text
storage/logs/php_error.log
```

---

## Catatan Teknis

- Plugin mengirim request ke `application/x-www-form-urlencoded`, sesuai pola dokumentasi Checkout API Nicepay.
- Setelah `txid` diterima, plugin membentuk URL redirect sendiri dengan format `.../payment?txid=...`.
- `cartdata` diisi dari item order lokal agar tetap ada konteks item di sisi sandbox.
- Callback saat ini dipetakan defensif berdasarkan `status` atau `resultcd`.

---

## Batasan Saat Ini

- Untuk kasus fraud-sensitive, dokumentasi Nicepay menyarankan verifikasi lanjutan dengan status inquiry. Langkah inquiry itu belum ditambahkan di plugin ini.
- Mapping callback dibuat generik agar tetap berguna lintas metode bayar, jadi bila merchant sandbox Anda memakai field callback tambahan, bisa ditambahkan relatif mudah.
- Default billing fallback masih mengambil data order atau cabang lokal; bila merchant memerlukan data yang lebih ketat, isi profil customer dan cabang dengan lengkap.
