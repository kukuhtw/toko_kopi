# KopiBot - AI Chatbot Order System

> ## AI Agent Commerce Platform
> Platform AI commerce untuk otomatisasi order, customer service, loyalty customer, Customer CRM, Customer Portal, integrasi channel chat, payment gateway, delivery connector, POS connector, dan manajemen multi cabang untuk berbagai jenis bisnis.
>
> **Documentation Language:**
> - [English README](README_EN.md)
> - [French README](README_FR.md)
>
> Aplikasi ini awalnya dikembangkan untuk coffee shop, lalu diperluas menjadi AI Agent Commerce yang dapat dipakai untuk bisnis kuliner, bakery, beverage, toko buah, fresh meat, sayuran, pharmacy, mini mart, retail mart, dan model toko lain yang membutuhkan order berbasis chat, katalog produk, promo, loyalty, checkout, delivery, dan integrasi sistem eksternal.
>
> ### Features
> - AI Chatbot Order Menu
> - WhatsApp / Telegram / Discord Integration
> - Multi Branch Management
> - AI Upselling & Promo Recommendation
> - Order via Website & Chat Apps
> - Variant Product & Topping Support
> - Product Photo Upload & AI Image Generation
> - Loyalty Point, Redeem Point, and Customer CRM
> - Customer Self-Service Dashboard
> - Multi Currency, Tax, and Timezone
> - Plugin menu template untuk coffee shop, bakery, fruit store, fresh meat, vegetables, pharmacy, dan mart
> - Plugin payment gateway, POS connector, delivery connector, FAQ RAG, complaint handling, dan customer support automation
>
> ### Tech Stack
> PHP Native - MySQL - OpenAI - Anthropic
> WhatsApp Gateway - REST API - LLM AI
>
> ### Suitable For
> Coffee Shop - Cafe - Restaurant - Bakery - Beverage Store - Fruit Store - Fresh Meat Market - Vegetable Store - Pharmacy - Mini Mart - Retail Mart - Specialty Store
>
> Dibuat dan dikembangkan oleh:
> Kukuh TW
>
> Email     : kukuhtw@gmail.com
> WhatsApp  : https://wa.me/628129893706
> Instagram : @kukuhtw
> X/Twitter : @kukuhtw
> GitHub    : https://github.com/kukuhtw/toko_kopi
> Facebook  : https://www.facebook.com/kukuhtw
> LinkedIn  : https://linkedin.com/in/kukuhtw
>
> Demo:
> https://botlelang.com/toko_kopi
>
> Copyright 2026 Kukuh TW. All rights reserved.

Sistem chatbot pemesanan dan AI commerce berbasis PHP 8 native, tanpa framework besar, dengan satu codebase untuk multi-bisnis, multi-cabang, multi-channel, multi-bahasa, promo engine, loyalty point, Customer CRM, Customer Portal, dan plugin system. Walaupun nama repo masih `toko_kopi`, arah pengembangan aplikasi sudah diperluas menjadi platform AI Agent Commerce yang dapat dikonfigurasi untuk berbagai vertical bisnis seperti kuliner, pharmacy, dan mart.

---

## Perluasan Business Vertical

Aplikasi ini sekarang tidak hanya fokus pada coffee shop. Dengan pendekatan plugin system dan menu template, aplikasi dapat dijadikan fondasi commerce chatbot untuk beberapa jenis bisnis berikut:

| Business Vertical | Contoh Penggunaan | Dukungan Fitur |
|----------|--------|----------|
| **Kuliner / F&B** | Coffee shop, cafe, restoran, bakery, beverage store | Menu order, varian produk, topping, promo, loyalty, upselling, delivery, payment gateway |
| **Fresh Market** | Toko buah, jus, smoothie, salad, daging segar, sayuran | Template menu produk segar, katalog item, harga per item, multi cabang, checkout, customer CRM |
| **Pharmacy** | Apotek, toko obat umum, produk kesehatan non-resep, vitamin, alat kesehatan ringan | Katalog produk, FAQ customer, complaint handler, CRM, payment gateway, delivery connector |
| **Mart / Retail** | Mini mart, convenience store, toko kelontong modern, retail mart | Katalog banyak item, cart, promo, multi cabang, customer portal, payment gateway, POS connector |
| **Specialty Store** | Toko produk niche, toko komunitas, toko cabang kecil | Plugin modular, channel chat, dashboard admin, export data, integrasi eksternal |

Fitur plugin terakhir yang memperkuat perluasan ini antara lain menu templates, FAQ RAG, complaint handling, payment gateway tambahan iPaymu dan Nicepay, Moka POS connector, GoSend delivery connector, SIRCLO connector scaffold, Customer CRM, dan Customer Portal. Kombinasi fitur ini membuat aplikasi dapat dipakai sebagai platform order, support, loyalty, dan commerce automation lintas industri, bukan hanya chatbot pemesanan kopi.

---

## Fitur

| Kategori | Detail |
|----------|--------|
| **Chatbot AI** | Intent detection berbasis rule dan LLM untuk order, promo, FAQ, komplain, rekomendasi produk, dan customer interaction |
| **Multi Business Vertical** | Satu codebase dapat dipakai untuk coffee shop, restoran, bakery, toko buah, fresh meat, sayuran, pharmacy, mini mart, dan retail mart |
| **Multi Cabang** | Satu brand, banyak cabang dengan menu, promo, pengaturan, mata uang, dan timezone terpisah |
| **Multi Channel** | Website, WhatsApp, Telegram, dan Discord dengan logika chatbot yang sama |
| **Plugin System** | Tambah fitur tanpa ubah kode inti melalui action/filter hooks |
| **Shopping Cart** | Tambah, edit, hapus, clear, promo, loyalty redeem, dan checkout berbasis session |
| **Checkout Flow** | Chatbot meminta data customer langkah demi langkah sampai order siap dibuat |
| **Checkout Profile Memory** | Data customer (nama, email, WA, alamat) disimpan di browser dan diisi otomatis saat checkout berikutnya |
| **Loyalty Point** | Earn point otomatis, cek saldo, redeem point via chatbot dan halaman order web |
| **Promo Engine** | Diskon persen, nominal, promo code, jadwal promo, min order, dan rekomendasi promo |
| **FAQ RAG** | FAQ global + custom per cabang, override branch, import/export CSV/XLS, analytics, dan vector store lokal |
| **Complaint Handling** | Deteksi komplain di flow chat, klasifikasi AI vs human follow-up, dan tiket komplain untuk cabang |
| **Payment Gateway** | Midtrans, Xendit, iPaymu, dan Nicepay via plugin |
| **POS Connector** | Scaffold + live sync queue untuk Moka Connect / Private Solution, inbound webhook sync, dan retry runner |
| **Delivery Connector** | GoSend partner connector dengan live-ready endpoint config, queue booking, pickup trigger, webhook status, dan audit |
| **Menu Management** | Upload CSV, variant size/price, topping, override per cabang, upload foto produk, dan generate foto produk dengan AI |
| **Menu Templates** | Plugin template data menu siap pakai: Coffee Shop, Bakery, Toko Buah, Daging & Sayuran, Pharmacy, dan Mart, dengan seed data dan override mata uang per cabang |
| **Dashboard** | Super admin lintas cabang, branch admin per cabang, Customer CRM, histori loyalty customer, dan Customer Portal self-service |
| **Customer CRM** | Normalisasi identitas customer berbasis email/WhatsApp, notifikasi loyalty, dan log CRM per cabang |
| **Customer Portal** | Login customer ringan via kontak + nomor order untuk cek order history, loyalty, profile, dan repeat order |
| **Dokumentasi HTML** | README dan docs Markdown tersedia juga sebagai halaman HTML |
| **Export CSV** | Export order, menu, promo, dan data dashboard terkait |

---

## Catatan Update README

README ini diperbarui untuk menjelaskan arah baru aplikasi sebagai AI Agent Commerce multi-vertical. Informasi yang ditambahkan menyesuaikan fitur plugin terakhir yang sudah tersedia atau sudah disiapkan di arsitektur plugin, yaitu channel chat, payment gateway, POS connector, delivery connector, FAQ RAG, complaint handler, Customer CRM, Customer Portal, dan menu template untuk berbagai jenis bisnis.
