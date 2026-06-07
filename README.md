# KopiBot - AI Chatbot Order System
> ## Juni 2026 sedang dalam masa migrasi ke composer. July 2026 akan selesai


> ## AI Agent Commerce Platform
> Platform AI commerce untuk otomatisasi order, customer service, loyalty customer, Customer CRM, Customer Portal, integrasi channel chat, payment gateway, delivery connector, POS connector, dan manajemen multi cabang untuk berbagai jenis bisnis.
>
> **Documentation Language:**
> - [English README](readme_en.md)
> - [French README](readme_fr.md)
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
> - Plugin menu template untuk coffee shop, bakery, fruit store, fresh market, pharmacy, mart, fashion, aksesori HP, tours & travel, dan umrah
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

KopiBot dibangun untuk menjawab kebutuhan bisnis yang ingin memiliki sistem order, customer support, loyalty, dan katalog digital yang benar-benar bisa mereka kontrol sendiri. Sistem ini berbasis PHP 8 native, tanpa framework besar, dengan satu codebase untuk multi-bisnis, multi-cabang, multi-channel, multi-bahasa, promo engine, loyalty point, Customer CRM, Customer Portal, dan plugin system. Walaupun nama repo masih `toko_kopi`, arah pengembangan aplikasi sudah diperluas menjadi platform AI Agent Commerce yang dapat dikonfigurasi untuk berbagai vertical bisnis seperti kuliner, pharmacy, retail, travel, dan layanan berbasis booking.

## Masalah yang Ingin Diselesaikan

Banyak bisnis kecil dan menengah ingin melayani order dari website, WhatsApp, dan channel chat lain, tetapi operasional mereka sering terpecah ke banyak alat yang tidak saling nyambung. Katalog ada di satu tempat, promo di tempat lain, data customer tercecer, loyalty tidak konsisten, dan tim cabang sulit melihat histori customer secara utuh.

Masalah lain yang sering muncul adalah keterbatasan solusi instan. Saat bisnis mulai butuh alur checkout yang spesifik, aturan promo yang berbeda per cabang, integrasi payment tertentu, atau template produk sesuai vertical bisnis, solusi generik cepat terasa sempit. Perubahan kecil sering bergantung pada vendor, biaya bertambah per fitur, dan data bisnis terkurung di platform pihak ketiga.

## Solusi yang Ditawarkan

KopiBot dirancang sebagai fondasi AI Agent Commerce yang bisa dipasang, dimiliki, dan dikembangkan sendiri. Tujuannya bukan sekadar membuat chatbot menjawab pesan, tetapi membantu bisnis menjalankan alur commerce end-to-end:

- menangkap intent customer dari chat atau web order
- menampilkan katalog dan varian produk sesuai cabang
- mendorong upselling, promo, dan loyalty otomatis
- menyimpan histori customer ke CRM yang bisa dipakai ulang
- menghubungkan checkout ke payment, delivery, POS, atau workflow operasional lain

Dari sisi implementasi, pendekatannya sengaja dibuat modular. Satu brand bisa punya banyak cabang, banyak channel, banyak jenis katalog, dan banyak integrasi tanpa harus memecah codebase menjadi beberapa aplikasi terpisah.

## Mengapa Bukan SaaS Biasa?

Platform ini berbeda dari solusi SaaS commerce generik karena fokusnya adalah kontrol dan extensibility. Pada SaaS, bisnis biasanya mengikuti workflow yang sudah ditentukan vendor. Jika ada kebutuhan khusus, pilihannya sering terbatas: menunggu roadmap vendor, membayar add-on, atau menerima kompromi operasional.

Di KopiBot, bisnis atau tim teknis internal bisa:

- meng-host sistem sendiri dan memegang akses penuh ke database serta codebase
- menyesuaikan workflow checkout, prompt AI, CRM, promo, dan rule cabang
- menambah integrasi baru tanpa menunggu vendor pusat
- membuat template bisnis sendiri untuk instalasi cepat per vertical

Pendekatan ini cocok untuk agency, software house, operator multi-cabang, atau bisnis yang ingin membangun aset digital jangka panjang, bukan sekadar menyewa panel SaaS yang seragam untuk semua orang.

## Plugin & Extension

Arsitektur plugin adalah salah satu pilar utama project ini. Fitur baru tidak harus masuk langsung ke core. Dengan action/filter hooks, plugin dapat memperluas perilaku aplikasi tanpa mengubah terlalu banyak kode inti, sehingga upgrade dan eksperimen fitur jadi lebih aman.

Beberapa kategori extension yang sudah didukung:

- plugin template produk/jasa untuk seed katalog awal saat instalasi
- plugin payment gateway seperti Midtrans, Xendit, iPaymu, dan Nicepay
- plugin POS connector seperti Moka Connect / Private Solution
- plugin delivery connector seperti GoSend
- plugin knowledge dan support seperti FAQ RAG serta complaint handling
- plugin branding dan theme untuk nama toko, icon brand, tagline, dan tampilan

Model ini memungkinkan setiap implementasi punya komposisi fitur yang berbeda. Satu deployment bisa fokus sebagai coffee shop order bot, deployment lain sebagai apotek digital, minimarket, travel booking assistant, atau portal umrah, semuanya di atas fondasi yang sama tetapi dengan plugin yang berbeda.

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
| **Chatbot AI** | Multi-intent detection berbasis rule dan LLM — satu pesan customer dapat memicu dan memproses beberapa intent sekaligus (contoh: order + tanya promo). `detectAll()` mendeteksi semua intent, `filterIntents()` membersihkan noise, `dispatchAll()` mengeksekusi berurutan dan menggabungkan semua reply |
| **Multi Business Vertical** | Satu codebase dapat dipakai untuk coffee shop, restoran, bakery, toko buah, fresh meat, sayuran, pharmacy, mini mart, dan retail mart. Setting `business_type` di `branch_settings` membuat semua prompt LLM (intent detector, menu assistant, promo assistant, FAQ assistant) otomatis menyesuaikan konteks bisnis |
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
| **Menu Templates** | Plugin template data menu siap pakai: Coffee Shop, Bakery, Toko Buah, Daging & Sayuran, Pharmacy, Mart, Warung, Baso, Kebab, Burger, Aksesori HP, Fashion Wanita, Tours & Travel, dan Umrah |
| **Dashboard** | Super admin lintas cabang, branch admin per cabang, Customer CRM, histori loyalty customer, dan Customer Portal self-service |
| **Customer CRM** | Normalisasi identitas customer berbasis email/WhatsApp, notifikasi loyalty, dan log CRM per cabang |
| **Customer Portal** | Login customer ringan via kontak + nomor order untuk cek order history, loyalty, profile, dan repeat order |
| **Dokumentasi HTML** | README dan docs Markdown tersedia juga sebagai halaman HTML |
| **Export CSV** | Export order, menu, promo, dan data dashboard terkait |

---

## Catatan Update README

README ini diperbarui untuk menjelaskan arah baru aplikasi sebagai AI Agent Commerce multi-vertical. Informasi yang ditambahkan menyesuaikan fitur plugin terakhir yang sudah tersedia atau sudah disiapkan di arsitektur plugin, yaitu channel chat, payment gateway, POS connector, delivery connector, FAQ RAG, complaint handler, Customer CRM, Customer Portal, dan menu template untuk berbagai jenis bisnis.

---

## Template Produk Tersedia

Template produk/menu berikut sudah tersedia dan dapat dipilih langsung saat proses instalasi web di Langkah 5:

| Template | Jumlah Produk/Jasa | Contoh |
|----------|--------------------|--------|
| **Default Seed Coffee Menu** | ~30 | Espresso, Americano, Cappuccino, Latte |
| **Coffee Shop Template** | 132 | Kopi panas/dingin, cemilan, dessert |
| **Bakery Template** | 70 | Roti tawar, croissant, donat, pastry |
| **Fruit Store Template** | 60 | Apel, jeruk, jus mangga, salad buah |
| **Meat & Veggie Template** | 80 | Daging sapi, ayam fillet, ikan, sayuran |
| **Pharmacy / Apotek Template** | 120 | Paracetamol, vitamin, alat kesehatan |
| **Minimarket Template** | 120 | Beras, Indomie, sabun, minuman kemasan |
| **Resto Indonesia Template** | 125 | Nasi goreng, soto ayam, rendang |
| **Warung Makan Template** | 15 | Nasi goreng, ayam goreng, tempe, kopi tubruk |
| **Resto Baso & Minuman Template** | 15 | Bakso urat, mie spesial, pangsit goreng |
| **Kebab Template** | 15 | Kebab original, shawarma ayam, pita falafel |
| **Burger Template** | 15 | Burger beef, fries, onion ring, milkshake |
| **Toko Aksesori & Casing HP Template** | 80 | Soft case, tempered glass, charger, TWS |
| **Toko Baju Busana Wanita Template** | 80 | Blouse, jeans, dress, blazer, tas |
| **Tours & Travel Template** | 15 | Bali 3D2N, Singapore, visa wisata, airport transfer |
| **Umrah Template** | 15 | Umrah 9 hari, umrah VIP, plus Turki, perlengkapan umrah |

Semua template hanya berfungsi sebagai data awal. Setelah instalasi selesai, produk/jasa dapat diubah, dihapus, atau ditambah dari dashboard admin kapan saja.
