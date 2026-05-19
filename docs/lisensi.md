# Lisensi KopiBot AI: AGPL + Commercial License

> ## ☕ AI Agent Coffee Shop Commerce Platform
> Platform AI untuk otomatisasi order, customer service, loyalty customer, dan manajemen multi cabang coffee shop.
>
> Dibuat & Dikembangkan oleh: **Kukuh TW**
>
> 📧 Email: `kukuhtw@gmail.com`
> 📱 WhatsApp: `https://wa.me/628129893706`
> 📷 Instagram: `@kukuhtw`
> 🐦 X/Twitter: `@kukuhtw`
> 👍 Facebook: `https://www.facebook.com/kukuhtw`
> 💼 LinkedIn: `https://linkedin.com/in/kukuhtw`
> 🌐 Demo: `https://botlelang.com/toko_kopi`
>
> © 2026 Kukuh TW. All rights reserved.

Dokumen ini menjelaskan model lisensi proyek **KopiBot AI** secara praktis: kapan kamu bisa memakai jalur **AGPL**, kapan perlu **commercial license**, dan apa implikasinya untuk modifikasi core, SaaS, plugin, serta deployment ke klien.

---

## Ringkasan Singkat

KopiBot AI memakai model **dual license**:

- **AGPL-3.0** untuk penggunaan open source dan turunan yang tetap terbuka.
- **Commercial License** untuk penggunaan proprietary / closed source / white-label tanpa kewajiban membuka source code turunan.

Kalau kamu nyaman dengan kewajiban copyleft AGPL, gunakan jalur AGPL.
Kalau kamu ingin menjaga modifikasi tetap tertutup, gunakan commercial license.

---

## Jalur 1: AGPL-3.0

Lisensi AGPL cocok untuk:

- developer individu yang ingin belajar, audit, dan memodifikasi sistem,
- agency atau tim internal yang siap membuka perubahan source code ketika diwajibkan oleh AGPL,
- komunitas open source yang ingin membuat fork publik,
- organisasi yang ingin memakai sistem ini sebagai basis proyek terbuka.

### Kewajiban penting AGPL

Jika kamu memakai jalur AGPL, maka secara praktis kamu harus siap untuk:

- mempertahankan lisensi AGPL pada bagian turunan yang berasal dari codebase ini,
- menyediakan source code ketika mendistribusikan software hasil modifikasi,
- menyediakan source code versi berjalan ketika software dimodifikasi dan diakses pengguna melalui jaringan,
- menjaga pemberitahuan hak cipta dan lisensi tetap ada.

### Contoh yang masih cocok memakai AGPL

- fork publik di GitHub dengan fitur tambahan baru,
- deployment internal untuk eksperimen yang tidak keberatan membuka perubahan jika nanti didistribusikan,
- SaaS komunitas yang memang ingin tetap open source,
- pengembangan plugin dan perbaikan bug yang akan dikontribusikan balik ke komunitas.

---

## Jalur 2: Commercial License

Commercial license cocok untuk:

- perusahaan yang ingin memakai KopiBot AI sebagai bagian dari produk proprietary,
- software house yang menjual solusi ke klien tanpa ingin membuka source code modifikasi,
- deployment white-label,
- layanan SaaS komersial tertutup,
- integrasi enterprise yang ingin menjaga modifikasi core dan business logic tetap privat.

### Keuntungan jalur commercial license

Dengan commercial license, kamu dapat:

- memakai core secara legal tanpa tunduk pada kewajiban copyleft AGPL untuk turunan tertutup,
- menutup source code modifikasi sesuai kebutuhan bisnis,
- melakukan bundling ke solusi komersial klien,
- mengatur skema kerja sama deployment, maintenance, atau white-label secara lebih fleksibel.

---

## Kapan Harus Pilih yang Mana

Gunakan **AGPL** jika:

- kamu siap membuka source code turunan ketika kewajiban AGPL aktif,
- proyekmu memang open source,
- kamu ingin kontribusi balik ke komunitas tetap sederhana.

Gunakan **Commercial License** jika:

- kamu ingin modifikasi tetap tertutup,
- kamu menjual atau melisensikan solusi ke klien sebagai produk proprietary,
- kamu menjalankan layanan komersial tanpa ingin membagikan source code versi modifikasi ke user,
- kamu butuh jalur lisensi yang lebih aman untuk kebutuhan bisnis.

---

## Dampak ke Modifikasi Core

Yang termasuk **modifikasi core** antara lain perubahan pada:

- folder `app/`
- folder `public/`
- loader plugin, skill, channel, provider, model, controller, dan flow utama aplikasi
- database schema atau perilaku utama yang menjadi bagian langsung dari codebase

Jika perubahan ini dipakai dalam jalur AGPL dan memicu kewajiban distribusi / network copyleft, maka source code turunannya harus tersedia sesuai AGPL.

Jika kamu ingin perubahan core tersebut tetap privat, jalur yang dianjurkan adalah commercial license.

---

## Dampak ke Plugin dan Integrasi

KopiBot AI memang dirancang plugin-first, tetapi aspek lisensi tetap tergantung cara modul itu dibangun dan didistribusikan.

Secara praktis:

- plugin yang terpisah dan hanya memanfaatkan hook / extension point publik bisa saja memiliki lisensi berbeda,
- tetapi jika plugin sangat tergantung pada internal core atau didistribusikan sebagai satu solusi proprietary bersama core, analisis lisensinya bisa menjadi lebih sensitif,
- modifikasi langsung ke file core hampir pasti mengikuti model lisensi utama proyek.

Karena itu:

- untuk plugin komunitas atau plugin yang akan dibuka, jalur AGPL biasanya paling sederhana,
- untuk plugin komersial tertutup yang dibundel ke deployment proprietary, commercial license untuk penggunaan core sering kali menjadi opsi paling aman.

---

## Dampak ke SaaS dan Layanan Online

Inilah bagian yang paling sering disalahpahami.

Pada lisensi AGPL:

- bukan hanya distribusi file yang penting,
- **penggunaan lewat jaringan** juga relevan,
- jadi jika kamu menjalankan versi modifikasi dari sistem ini sebagai layanan online, kewajiban AGPL bisa tetap aktif kepada user yang berinteraksi dengan layanan tersebut.

Kalau model bisnis kamu adalah:

- SaaS tertutup,
- white-label service,
- hosted service untuk banyak merchant,
- platform komersial yang tidak ingin membuka source code modifikasi,

maka commercial license biasanya adalah jalur yang lebih tepat.

---

## Skenario Praktis

### Skenario A: Fork komunitas

Kamu fork repo ini, tambah fitur loyalty dan integrasi baru, lalu publikasikan repo hasilnya.

Status: **AGPL cocok**.

### Skenario B: Agency membuat solusi proprietary untuk klien

Kamu ubah dashboard, flow checkout, integrasi payment, dan branding untuk klien, tetapi tidak ingin source code modifikasi dibuka.

Status: **commercial license dianjurkan**.

### Skenario C: SaaS tertutup untuk banyak tenant

Kamu host satu versi modifikasi dan menjualnya sebagai platform berlangganan.

Status: **commercial license sangat dianjurkan**.

### Skenario D: Belajar lokal / proof of concept

Kamu install di localhost untuk evaluasi teknis dan tidak mendistribusikannya.

Status: **AGPL masih cocok**, selama kamu memahami batasannya jika nanti berlanjut ke produk komersial tertutup.

---

## Cara Mendapatkan Commercial License

Untuk pembelian commercial license, kerja sama deployment, OEM, white-label, atau kebutuhan enterprise, hubungi:

- Email: `kukuhtw@gmail.com`
- WhatsApp: `https://wa.me/628129893706`

Saat menghubungi, biasanya akan lebih cepat jika kamu sertakan:

- model penggunaan: internal, SaaS, white-label, atau jual ke klien,
- jumlah cabang / tenant,
- kebutuhan channel: web, WhatsApp, Telegram, Discord,
- apakah ada kebutuhan modifikasi core atau plugin proprietary.

---

## Catatan Penting

- Dokumen ini adalah **penjelasan praktis proyek**, bukan opini hukum formal.
- Teks ini tidak menggantikan naskah lisensi resmi AGPL-3.0.
- Jika ada kebutuhan legal yang sensitif, lakukan review dengan penasihat hukum.

---

## Referensi

- [GNU AGPL-3.0 Official Text](https://www.gnu.org/licenses/agpl-3.0.html)
- [README.md](../README.md)
- [plugin-system.md](plugin-system.md)
- [tutorial-membuat-plugin.md](tutorial-membuat-plugin.md)
