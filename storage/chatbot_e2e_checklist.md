# Chatbot E2E Checklist

Gunakan checklist ini untuk menguji chatbot dari UI chat:

- URL contoh:
  - `https://botlelang.com/toko_kopi/public/chat.php?branch=1&debug=1`
  - `https://botlelang.com/toko_kopi/public/chat.php?branch=singapore&debug=1`

Tujuan:
- memastikan intent tidak mudah jatuh ke `out_of_scope`
- memastikan follow-up tetap nyambung ke topik sebelumnya
- memastikan retrieval menu/promo/cart berjalan konsisten

## 1. Menu Overview

Input:
`menu`

Ekspektasi:
- bot menampilkan overview kategori
- tidak menampilkan semua item sekaligus
- debug menunjukkan detector aktif

## 2. Deskripsi Satu Menu

Input:
`paket pagi spesial itu apa?`

Ekspektasi:
- bot menjelaskan `Paket Pagi Spesial`
- menyebut nama item dan harga
- tidak fallback ke daftar kategori

## 3. Deskripsi Banyak Menu

Input:
`jelaskan paket siang produktif, paket sore santai, paket malam relax`

Ekspektasi:
- bot menjelaskan beberapa item sekaligus
- urutan jawaban kurang lebih mengikuti input user
- tidak hanya menjawab satu item

## 4. Copy-Paste Menu + Detail

Input:
`• Paket Siang Produktif — Rp56.000 • Paket Sore Santai — Rp62.000 jelaskan detail`

Ekspektasi:
- intent masuk ke `tanya_menu`
- bot menjelaskan item yang disebut
- tidak jatuh ke `out_of_scope`

## 5. Harga Item

Input:
`harga iced latte berapa?`

Ekspektasi:
- bot menjawab harga item
- tidak menampilkan overview kategori

## 6. Promo Umum

Input:
`ada promo apa?`

Ekspektasi:
- bot menampilkan promo aktif atau bilang tidak ada promo aktif
- jika ada promo, judul dan kode promo tampil jelas

## 7. Follow-up Promo

Input:
1. `ada promo apa?`
2. `kode promonya apa?`

Ekspektasi:
- pesan kedua tetap diarahkan ke promo
- tidak jatuh ke `out_of_scope`

## 8. Tambah Item

Input:
`pesan 2 iced latte dan 1 caramel frappe`

Ekspektasi:
- kedua item masuk ke cart
- ringkasan cart tampil
- quantity sesuai

## 9. Update Item Follow-up

Input:
1. `pesan 2 iced latte`
2. `jadi 3 aja`

Ekspektasi:
- bot memahami ini sebagai update item/cart follow-up
- quantity item terakhir berubah jadi 3

## 10. Hapus Item Follow-up

Input:
1. `pesan 1 iced latte dan 1 croissant`
2. `hapus yang tadi`

Ekspektasi:
- bot tetap membaca konteks cart sebelumnya
- tidak menjawab item tidak ditemukan secara membingungkan

## 11. Apply Promo

Input:
1. `pesan 2 iced latte`
2. `pakai promo kopi10`

Ekspektasi:
- jika promo valid, discount diterapkan
- jika tidak valid, alasan penolakan jelas

## 12. Apply Promo Follow-up

Input:
1. `ada promo apa?`
2. `pakai promo itu`

Ekspektasi:
- jika ada kode promo yang baru dibahas, bot lebih mudah tetap nyambung ke promo context
- minimal tidak jatuh ke jawaban acak

## 13. Checkout Flow

Input:
1. `pesan 2 iced latte`
2. `checkout`
3. isi nama
4. isi email / `skip`
5. isi WA
6. isi alamat
7. isi kode pos
8. `ya`

Ekspektasi:
- flow data collection berjalan berurutan
- ringkasan order tampil sebelum konfirmasi
- order berhasil dibuat

## 14. Edit Data Saat Konfirmasi

Input:
1. jalankan checkout sampai summary muncul
2. `ganti alamat`
3. kirim alamat baru

Ekspektasi:
- bot meminta atau menerima alamat baru
- summary diperbarui

## 15. English Request

Input:
`can you speak english?`

Ekspektasi:
- bot menjawab dalam English
- tidak menolak sebagai `out_of_scope`

## 16. English Branch UI + Chat

Cabang:
`singapore` atau `sydney`

Input:
`what promos do you have?`

Ekspektasi:
- bot menjawab English
- currency dan tax mengikuti branch

## 17. Residual Risk Checks

Coba variasi berikut:
- `yang mana paling murah?`
- `bedanya apa?`
- `lanjut`
- `ya`
- `hapus`

Ekspektasi:
- jika sebelumnya ada konteks menu/promo/cart/checkout, bot tetap relatif nyambung
- jika konteks tidak cukup, bot meminta klarifikasi singkat, bukan fallback ngawur

## Catatan Hasil

Saat testing, catat minimal:
- input user
- `intent`
- `detector`
- reply bot
- apakah hasil sesuai ekspektasi

Template singkat:

```text
Case:
Input:
Intent:
Detector:
Result:
Pass/Fail:
Notes:
```
