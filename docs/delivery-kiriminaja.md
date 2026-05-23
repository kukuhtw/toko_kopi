# Setup KiriminAja Delivery

Dokumen ini menjelaskan cara memakai plugin `kiriminaja-delivery` di proyek ini.

> Konsep operasional:
> - **super admin** menentukan cabang mana yang memakai plugin KiriminAja
> - **admin cabang** menjalankan operasional delivery di cabangnya sendiri
> - biaya delivery yang masuk ke order memakai **default fee per cabang**

---

## Tujuan Plugin

Plugin ini dipakai untuk menambahkan komponen biaya delivery pada flow order untuk cabang tertentu.

Saat plugin aktif pada cabang:

- checkout dengan metode `delivery ke alamat`
- otomatis menambahkan `delivery_fee`
- menyimpan metadata delivery ke order

Versi saat ini fokus pada operasional cabang:

- aktivasi plugin per cabang
- pengaturan credential dasar KiriminAja
- pengaturan `default delivery fee` oleh admin cabang

---

## File Plugin

```text
plugins/kiriminaja-delivery/
|-- plugin.php
|-- KiriminAjaDeliveryRepository.php
|-- KiriminAjaDeliveryClient.php
|-- KiriminAjaDeliveryService.php
`-- KiriminAjaDeliveryPlugin.php
```

---

## Cara Kerja Singkat

1. Super admin membuka pengaturan plugin per cabang.
2. Super admin mengaktifkan KiriminAja pada cabang tertentu.
3. Admin cabang membuka settings cabangnya.
4. Admin cabang mengisi `default delivery fee`.
5. Saat customer checkout dengan metode `delivery`, fee itu dimasukkan ke total order.

---

## Pengaturan Super Admin

Masuk ke:

```text
Dashboard Super Admin -> App Settings
```

Lalu pilih cabang yang ingin memakai KiriminAja, kemudian isi:

| Field | Fungsi |
|------|--------|
| `Aktifkan KiriminAja untuk cabang ini` | menentukan cabang ikut plugin atau tidak |
| `Mode` | `sandbox` atau `production` |
| `Base URL API` | endpoint dasar API KiriminAja |
| `API Key` | credential API |
| `Courier Label` | label kurir yang tampil di order |
| `Service Label` | label layanan delivery yang tampil di order |

Catatan:

- super admin hanya menentukan implementasi plugin di cabang mana
- super admin tidak perlu mengatur fee harian per cabang satu per satu kalau operasionalnya diserahkan ke admin cabang

---

## Pengaturan Admin Cabang

Masuk ke:

```text
Dashboard Branch -> Settings
```

Jika plugin sudah diaktifkan oleh super admin, admin cabang bisa mengisi:

| Field | Fungsi |
|------|--------|
| `Default Fee Delivery` | biaya delivery default cabang |

Inilah fee yang dipakai saat order delivery dibuat.

Jadi pada versi sekarang:

- delivery **dijalankan oleh admin cabang**
- branch admin yang menentukan default fee delivery cabangnya
- super admin hanya mengaktifkan akses dan konfigurasi global plugin

---

## Pengaruh ke Flow Order

Jika customer memilih:

```text
delivery ke alamat pemesan
```

maka plugin akan:

1. mengecek apakah KiriminAja aktif pada cabang itu
2. membaca `default_delivery_fee`
3. menambahkan nilai itu ke `delivery_fee`
4. menambahkan fee ke `total_amount` order

Metadata yang ikut tersimpan:

- `delivery_fee`
- `delivery_courier`
- `delivery_service`
- `delivery_provider`
- `delivery_reference`

---

## Posisi Branch Admin dalam Operasional

Supaya jelas, pembagian perannya seperti ini:

### Super Admin

- memilih cabang mana yang memakai KiriminAja
- mengisi API credential global cabang
- menentukan mode sandbox/production

### Admin Cabang

- menjalankan delivery di level cabang
- menentukan default fee delivery cabang
- memonitor order delivery dari dashboard cabang

Jadi operasional delivery sehari-hari tetap berada di tangan admin cabang.

---

## Keterbatasan Versi Saat Ini

Versi sekarang **belum**:

- membuat shipment real-time ke API KiriminAja
- mengambil ongkir live otomatis dari KiriminAja
- membuat nomor resi otomatis
- melakukan sinkron status pengiriman

Versi ini adalah fondasi operasional branch-based delivery fee.

---

## Pengembangan Lanjutan yang Disarankan

Tahap berikut yang bisa ditambahkan:

1. create shipment ke KiriminAja setelah order dibuat
2. simpan nomor referensi / tracking
3. sinkron status pengiriman
4. kalkulasi fee dari endpoint KiriminAja, bukan default fee manual

---

## Referensi

- [KiriminAja Developer Docs](https://developer.kiriminaja.com/docs/introduction)
- [`plugins/kiriminaja-delivery/KiriminAjaDeliveryPlugin.php`](../plugins/kiriminaja-delivery/KiriminAjaDeliveryPlugin.php)
- [`plugins/kiriminaja-delivery/KiriminAjaDeliveryService.php`](../plugins/kiriminaja-delivery/KiriminAjaDeliveryService.php)
