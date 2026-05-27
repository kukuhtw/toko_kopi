# Customer-Facing Agent Architecture

> ## AI Agent Commerce Platform
> Platform AI untuk otomatisasi order, customer service, loyalty customer, Customer CRM, Customer Portal, payment gateway, dan customer-facing AI agent untuk berbagai jenis bisnis.
>
> Arsitektur ini dirancang agar AI agent dapat melayani bisnis kuliner, bakery, fresh market, pharmacy, mini mart, retail mart, dan specialty commerce dengan pendekatan hybrid antara deterministic commerce engine dan reasoning berbasis LLM.
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

Dokumen ini merancang evolusi dari chatbot intent-based menjadi **customer-facing hybrid AI commerce agent** yang lebih natural seperti AI agent modern, tetapi tetap aman untuk use case order, promo, loyalty, checkout, payment, dan delivery.

---

## Tujuan

- Menjaga alur order, promo, loyalty, payment, dan checkout tetap deterministic.
- Menambah layer agentic untuk rekomendasi produk, penjelasan katalog, pemahaman bahasa natural, dan advisory response.
- Menyediakan fondasi memory, planning, tool execution, dan policy guardrails.
- Menghindari migrasi besar yang merusak `ChatbotEngine` yang sudah stabil.
- Membuat AI commerce agent yang dapat dipakai lintas vertical bisnis.

---

## Prinsip Utama

### 1. Commerce Core Tetap Deterministic

`ChatbotEngine` tetap menjadi sumber kebenaran untuk:

- cart
- promo
- checkout
- payment
- delivery
- order lifecycle
- loyalty

### 2. Agent Layer Fokus ke Reasoning

Agent tidak langsung menulis state sensitif.

Agent hanya:

- memahami maksud customer
- memilih tool yang relevan
- membangun jawaban natural
- memberi rekomendasi
- melakukan handoff ke deterministic flow bila perlu

### 3. Tool-Gated Mutation

Perubahan state hanya boleh lewat tool eksplisit dan bisa diaudit.

### 4. Policy First

Sebelum tool mutating dijalankan, `PolicyEngine` menentukan apakah aksi aman, perlu klarifikasi, atau harus di-handoff.

### 5. Memory Bounded

Memory customer disimpan terbatas dan terstruktur:

- session memory
- customer memory
- conversation summary memory
- preference memory

---

## Business Vertical Coverage

Arsitektur agent ini dirancang agar fleksibel untuk beberapa vertical:

| Vertical | Contoh Use Case AI Agent |
|---|---|
| Coffee shop | rekomendasi minuman, upselling topping, promo combo |
| Bakery | rekomendasi pastry, paket sarapan, bundling |
| Fresh market | rekomendasi buah segar, stok harian, promo sayur |
| Pharmacy | FAQ produk kesehatan, customer support, pencarian produk |
| Mini mart | pencarian produk retail, promo item, repeat order |
| Specialty commerce | conversational ordering dan advisory commerce |

---

## Komponen Baru

### 1. `ConversationModeRouter`

File:

```text
app/Agent/Routing/ConversationModeRouter.php
```

Tugas:

- memisahkan request menjadi `transactional`, `advisory`, atau `handoff`
- contoh `transactional`: tambah item, checkout, payment
- contoh `advisory`: rekomendasi produk, promo, FAQ, suggestion

---

### 2. `CustomerAgentKernel`

File:

```text
app/Agent/CustomerAgentKernel.php
```

Tugas:

- entry point untuk advisory mode
- membaca memory customer
- membuat rencana langkah sederhana
- mengeksekusi tools melalui registry
- mengecek policy sebelum mutasi
- menyusun jawaban natural

---

### 3. `ToolRegistry` + `ToolInterface`

Files:

```text
app/Agent/ToolRegistry.php
app/Agent/ToolInterface.php
```

Tools awal:

- `get_branch_menu`
- `get_cart_snapshot`
- `get_active_promos`
- `begin_checkout`

Evolusi berikutnya:

- `add_to_cart`
- `update_cart_item`
- `remove_cart_item`
- `apply_promo`
- `get_branch_info`
- `get_order_history_summary`
- `get_delivery_status`
- `get_loyalty_status`
- `search_product_catalog`

---

### 4. `PolicyEngine`

Files:

```text
app/Agent/Policy/PolicyEngine.php
app/Agent/Policy/PolicyDecision.php
```

Tugas:

- mencegah tool mutating dijalankan saat intent customer belum jelas
- menjaga checkout hanya terjadi saat intent memang checkout
- mewajibkan klarifikasi untuk kondisi ambigu
- membatasi halusinasi AI terhadap harga, promo, stok, dan payment

---

### 5. `CustomerMemoryStore`

Files:

```text
app/Agent/Memory/MemoryStoreInterface.php
app/Agent/Memory/CustomerMemoryStore.php
```

Tugas:

- menyimpan ringkasan interaksi penting
- menyimpan preferensi customer yang aman
- mendukung personalization lintas sesi
- membantu repeat order dan recommendation

---

## Skema Database Awal

Migration file:

```text
database/add_customer_agent_tables.sql
```

Tables:

- `agent_memories`
- `agent_tasks`
- `agent_task_steps`

Tujuan:

| Table | Fungsi |
|---|---|
| `agent_memories` | memory customer dan advisory |
| `agent_tasks` | log task AI agent |
| `agent_task_steps` | log planning dan tool execution |

---

## Integrasi Bertahap dengan Engine Lama

### Fase 1

- `ChatbotEngine` tetap seperti sekarang
- `CustomerAgentKernel` belum dipakai di production flow
- fokus pada scaffolding dan kontrak arsitektur

### Fase 2

Tambahkan adapter di depan `ChatbotEngine`:

1. detect intent
2. build context
3. route via `ConversationModeRouter`
4. jika `transactional` → `ChatbotEngine`
5. jika `advisory` → `CustomerAgentKernel`

### Fase 3

Tambahkan tool mutating yang aman:

- `begin_checkout`
- `apply_promo`
- `add_to_cart`
- `request_delivery`

Semua tetap melalui policy guard.

### Fase 4

Tambahkan reflection ringan:

- ringkasan percakapan sukses
- penyimpanan preferensi customer
- recommendation learning
- conversational memory

---

## Perbedaan dengan Arsitektur Lama

### Lama

- deteksi intent
- pilih satu skill
- skill langsung reply
- context disimpan sebagai state machine

### Baru

- deteksi intent
- route mode
- advisory mode masuk agent kernel
- agent membuat mini plan
- tools execute
- policy check
- hasil dirangkai jadi reply natural
- memory diperbarui

---

## Guardrails yang Disarankan

1. Agent tidak boleh menghitung harga final sendiri.
2. Agent tidak boleh mengarang promo atau stok.
3. Agent tidak boleh memulai checkout jika customer masih ragu.
4. Agent tidak boleh menulis ke cart tanpa tool mutating eksplisit.
5. Saat confidence rendah, agent harus klarifikasi atau handoff.
6. Pharmacy flow harus memiliki pembatasan untuk advisory sensitif.
7. Payment dan loyalty tetap harus deterministic.

---

## Use Cases yang Cocok

- “Aku pengen yang manis tapi nggak terlalu creamy.”
- “Budget aku 30 ribuan, enaknya apa?”
- “Ada promo bakery hari ini?”
- “Vitamin yang cocok untuk aktivitas harian apa?”
- “Mart cabang BSD masih ada susu ini nggak?”
- “Yang mirip pesanan aku minggu lalu apa ya?”
- “Promo yang paling cocok buat cart aku sekarang apa?”

---

## Use Cases yang Harus Tetap Deterministic

- tambah/hapus item final
- validasi promo
- hitung subtotal/diskon/pajak
- status payment
- pembuatan order
- update order status
- redeem loyalty
- delivery booking

---

## Evolusi Jangka Panjang

Roadmap AI commerce agent:

| Area | Arah Pengembangan |
|---|---|
| AI Recommendation | Recommendation engine berbasis behavior customer |
| Omnichannel | WhatsApp, Telegram, Discord, Instagram DM |
| Delivery | GoSend, GrabExpress, Lalamove |
| POS | Moka, Pawoon, Majoo, custom POS |
| Commerce AI | Dynamic upselling dan conversational commerce |
| CRM | Memory personalization dan customer segmentation |
| Retail | Product search dan retail catalog reasoning |

---

## Kesimpulan

Arsitektur ini memungkinkan aplikasi berkembang dari chatbot order sederhana menjadi AI Agent Commerce Platform yang mampu menangani conversational ordering, recommendation, loyalty, customer support, dan advisory commerce lintas industri dengan tetap mempertahankan deterministic commerce core untuk keamanan transaksi.
