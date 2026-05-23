# Tutorial Integrasi SIRCLO dengan Sirclo Full Connector

Dokumen ini menjelaskan cara memakai plugin `sirclo-full-connector` yang sudah disiapkan di proyek ini untuk fondasi integrasi order, produk, dan customer ke ekosistem SIRCLO.

> Catatan:
> - Nama plugin di source code memakai kata `Sirclo` agar konsisten dengan slug folder yang sudah dibuat.
> - Brand/platform yang dimaksud di dokumen ini adalah **SIRCLO**.
> - Versi plugin saat ini adalah **scaffold integrasi**: konfigurasi, dashboard, queue log, dan snapshot data sudah siap, tetapi request HTTP real ke API SIRCLO belum diimplementasikan.

---

## Tujuan Plugin

Plugin ini dibuat untuk menjadi fondasi integrasi tiga arah utama:

1. **Order sync**
   Mencatat event `order.created`, `order.status_changed`, dan `order.payment_updated` agar nantinya bisa dikirim ke API SIRCLO.
2. **Product sync**
   Menyiapkan snapshot katalog produk per cabang dari `menu_items`, kategori, dan availability override.
3. **Customer sync**
   Menyiapkan snapshot customer aktif per cabang berdasarkan histori order.

---

## File yang Terlibat

Struktur file plugin:

```text
plugins/sirclo-full-connector/
|-- plugin.php
|-- SircloFullConnectorPlugin.php
|-- SircloConnectorRepository.php
`-- SircloConnectorService.php
```

Halaman dashboard:

```text
public/dashboard/branch/sirclo.php
public/dashboard/super/sirclo.php
```

---

## Cara Kerja Singkat

Saat plugin dimuat:

- plugin memastikan tabel `sirclo_sync_logs` tersedia
- plugin menambahkan menu sidebar untuk `branch_admin` dan `super_admin`
- plugin menambahkan form pengaturan di halaman settings
- plugin mendaftarkan listener untuk event order

Saat order berubah:

- plugin membaca konfigurasi cabang
- plugin mengecek apakah sinkronisasi order aktif
- plugin membentuk payload internal
- plugin menyimpan antrean/log ke `sirclo_sync_logs`

Untuk sinkronisasi manual:

- branch admin bisa membuka halaman `Sirclo Connector`
- admin dapat menekan tombol `Queue Order Sync`, `Queue Product Sync`, atau `Queue Customer Sync`
- plugin menyimpan snapshot data ke log untuk dipakai sebagai dasar implementasi API berikutnya

---

## Aktivasi Plugin

Plugin sudah ditambahkan ke:

```json
plugins/plugins.json
```

dan dalam state saat ini sudah aktif:

```json
"sirclo-full-connector": {
  "active": true
}
```

Kalau ingin menonaktifkan sementara, ubah nilai `active` menjadi `false`.

---

## Pengaturan yang Perlu Diisi

Masuk ke salah satu halaman berikut:

- `Dashboard Branch → Pengaturan Cabang`
- `Dashboard Super Admin → App Settings → Pengaturan Plugin Per Cabang`

Lalu isi field berikut:

| Field | Fungsi |
|------|--------|
| `API Base URL` | base endpoint API SIRCLO |
| `Store ID` | identitas store/cabang di SIRCLO |
| `API Key` | credential utama API |
| `API Secret` | secret tambahan jika dibutuhkan integrasi |
| `Webhook Secret` | secret validasi webhook inbound |
| `Order Status Mapping` | pemetaan status lokal ke status SIRCLO |
| `Aktifkan konektor` | menghidupkan plugin untuk cabang |
| `Sinkronkan order / produk / customer` | memilih domain sinkronisasi yang aktif |

Pengaturan global tambahan:

| Field | Fungsi |
|------|--------|
| `Connection Mode` | `sandbox` atau `production` |
| `Timeout` | batas waktu request HTTP nanti |
| `Batch Limit` | batas item per batch sync |

---

## Halaman Dashboard Plugin

### Branch Dashboard

Halaman:

```text
/dashboard/branch/sirclo.php
```

Fungsi utama:

- melihat status koneksi cabang
- melihat apakah credential sudah lengkap
- menjalankan queue sync manual
- membaca log sinkronisasi terbaru

### Super Admin Dashboard

Halaman:

```text
/dashboard/super/sirclo.php
```

Fungsi utama:

- melihat status semua cabang
- melihat jumlah log sukses/pending/gagal
- memantau activity sinkronisasi terakhir

---

## Tabel Log Sinkronisasi

Plugin membuat tabel:

```text
sirclo_sync_logs
```

Kolom penting:

| Kolom | Fungsi |
|------|--------|
| `branch_id` | cabang asal |
| `entity_type` | `order`, `product`, atau `customer` |
| `direction` | arah sync, default `outbound` |
| `event_name` | nama event pemicu |
| `status` | `pending`, `success`, `failed`, atau `config_missing` |
| `reference_id` | order number atau penanda referensi |
| `payload_preview` | snapshot payload JSON |
| `response_preview` | response/stub result |

Status `config_missing` berarti plugin aktif, tetapi konfigurasi wajib seperti `base_url`, `store_id`, atau `api_key` belum lengkap.

---

## Event yang Sudah Tersambung

Hook yang sudah dipakai plugin:

| Hook | Kegunaan |
|------|----------|
| `dashboard.nav_items` | menambah menu sidebar |
| `settings.sections` | menambah form settings di cabang |
| `super.settings.sections` | menambah form settings di super admin |
| `order.created` | antre sinkronisasi order baru |
| `order.status_changed` | antre sinkronisasi perubahan status |
| `order.payment_updated` | antre sinkronisasi pembayaran |

---

## Keterbatasan Versi Saat Ini

Versi yang ada sekarang **belum**:

- memanggil endpoint API SIRCLO secara real
- menerima webhook inbound SIRCLO dan memprosesnya
- melakukan retry otomatis
- menyimpan job queue terpisah selain log activity
- melakukan mapping field produk/customer sesuai spesifikasi resmi SIRCLO

Artinya plugin ini aman dipakai sebagai fondasi internal, tetapi belum final sebagai konektor produksi penuh.

---

## Langkah Lanjutan untuk Menjadi Integrasi Produksi

Jika ingin melanjutkan sampai benar-benar terhubung ke SIRCLO, langkah berikut biasanya diperlukan:

1. Dapatkan dokumentasi resmi API SIRCLO:
   endpoint order, catalog, customer, webhook, auth, dan rate limit.
2. Tentukan arah integrasi:
   push-only, pull-only, atau two-way sync.
3. Implementasikan HTTP client di `SircloConnectorService.php`.
4. Tambahkan endpoint webhook:
   misalnya `public/api/plugins/sirclo/webhook.php`.
5. Tambahkan retry policy dan dead-letter handling untuk sync gagal.
6. Tambahkan mapping field final:
   SKU, external product ID, branch/store code, customer external ID, payment state, fulfillment state, dan inventory state.

---

## Skenario Implementasi yang Disarankan

Untuk rollout yang aman, lakukan bertahap:

1. **Tahap 1**
   Aktifkan hanya `sync_orders`, cek log payload, dan finalkan mapping status.
2. **Tahap 2**
   Tambahkan `sync_products`, terutama SKU, harga, dan availability.
3. **Tahap 3**
   Tambahkan `sync_customers`, lalu sinkronkan identifier eksternal.
4. **Tahap 4**
   Baru aktifkan webhook inbound dan retry production.

---

## Referensi Cepat

- [`plugins/sirclo-full-connector/plugin.php`](../plugins/sirclo-full-connector/plugin.php)
- [`plugins/sirclo-full-connector/SircloFullConnectorPlugin.php`](../plugins/sirclo-full-connector/SircloFullConnectorPlugin.php)
- [`plugins/sirclo-full-connector/SircloConnectorRepository.php`](../plugins/sirclo-full-connector/SircloConnectorRepository.php)
- [`plugins/sirclo-full-connector/SircloConnectorService.php`](../plugins/sirclo-full-connector/SircloConnectorService.php)
- [`public/dashboard/branch/sirclo.php`](../public/dashboard/branch/sirclo.php)
- [`public/dashboard/super/sirclo.php`](../public/dashboard/super/sirclo.php)
