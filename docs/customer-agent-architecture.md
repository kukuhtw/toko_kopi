# Customer-Facing Agent Architecture

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

Dokumen ini merancang evolusi dari chatbot intent-based saat ini menjadi **customer-facing hybrid agent** yang lebih natural seperti agent modern, tetapi tetap aman untuk use case order dan checkout.

## Tujuan

- Menjaga alur order, promo, dan checkout tetap deterministik.
- Menambah layer agentic untuk rekomendasi, penjelasan menu, pemahaman bahasa natural, dan advisory response.
- Menyediakan fondasi memory, planning, tool execution, dan policy guardrails.
- Menghindari migrasi besar yang merusak `ChatbotEngine` yang sudah stabil.

## Prinsip Utama

1. **Commerce core tetap deterministic**
   `ChatbotEngine` tetap menjadi sumber kebenaran untuk cart, promo, checkout, dan order lifecycle.

2. **Agent layer fokus ke reasoning**
   Agent tidak langsung menulis state sensitif. Agent hanya:
   - memahami maksud customer yang lebih kompleks
   - memilih tool yang relevan
   - membangun jawaban natural
   - melakukan handoff ke deterministic flow jika perlu

3. **Tool-gated mutation**
   Perubahan state hanya boleh lewat tool yang eksplisit dan bisa diaudit.

4. **Policy first**
   Sebelum tool mutating dijalankan, `PolicyEngine` memutuskan apakah aksi aman, perlu klarifikasi, atau harus di-handoff.

5. **Memory bounded**
   Untuk customer-facing bot, memory disimpan terbatas dan terstruktur:
   - session memory
   - customer memory
   - conversation summary memory

## Komponen Baru

### 1. `ConversationModeRouter`

File: `app/Agent/Routing/ConversationModeRouter.php`

Tugas:
- memisahkan request menjadi `transactional`, `advisory`, atau `handoff`
- contoh `transactional`: tambah item, ubah item, checkout
- contoh `advisory`: rekomendasi menu, budget-based suggestion, tanya promo

### 2. `CustomerAgentKernel`

File: `app/Agent/CustomerAgentKernel.php`

Tugas:
- entry point untuk advisory mode
- membaca memory customer
- membuat rencana langkah sederhana
- mengeksekusi tools melalui registry
- mengecek policy sebelum mutasi
- menyusun jawaban natural

### 3. `ToolRegistry` + `ToolInterface`

Files:
- `app/Agent/ToolRegistry.php`
- `app/Agent/ToolInterface.php`

Tools awal yang disiapkan:
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

### 4. `PolicyEngine`

Files:
- `app/Agent/Policy/PolicyEngine.php`
- `app/Agent/Policy/PolicyDecision.php`

Tugas:
- mencegah tool mutating dijalankan saat niat customer belum jelas
- menjaga checkout hanya terjadi saat intent memang checkout
- mewajibkan klarifikasi untuk kondisi ambigu

### 5. `CustomerMemoryStore`

Files:
- `app/Agent/Memory/MemoryStoreInterface.php`
- `app/Agent/Memory/CustomerMemoryStore.php`

Tugas:
- menyimpan ringkasan interaksi penting
- menyimpan preferensi customer yang aman
- menjadi dasar personalization lintas sesi

## Skema Database Awal

Migration file:
- `database/add_customer_agent_tables.sql`

Tables:
- `agent_memories`
- `agent_tasks`
- `agent_task_steps`

Tujuan tabel:
- `agent_memories`: simpan memory customer/advisory
- `agent_tasks`: log satu task agent
- `agent_task_steps`: log plan dan tool execution per task

## Integrasi Bertahap dengan Engine Lama

### Fase 1

- `ChatbotEngine` tetap seperti sekarang
- `CustomerAgentKernel` belum dipakai di production flow
- tujuan fase ini: scaffolding + kontrak arsitektur

### Fase 2

Tambahkan adapter di depan `ChatbotEngine`:

1. detect intent seperti biasa
2. build context
3. route via `ConversationModeRouter`
4. jika `transactional` -> `ChatbotEngine`
5. jika `advisory` -> `CustomerAgentKernel`

### Fase 3

Tambahkan tool mutating yang aman:
- `begin_checkout`
- `apply_promo`
- `add_to_cart`

Tetap dengan policy guard sebelum execute.

### Fase 4

Tambahkan reflection ringan:
- ringkas percakapan sukses
- simpan preferensi customer
- simpan phrasing yang membantu rekomendasi

## Perbedaan dengan Arsitektur Lama

### Lama

- deteksi intent
- pilih 1 skill
- skill langsung reply
- context disimpan sebagai state machine

### Baru

- deteksi intent
- route mode
- advisory mode masuk agent kernel
- agent kernel buat plan kecil
- tools execute
- policy check
- hasil dirangkai jadi reply
- memory diperbarui

## Guardrails yang Disarankan

1. Agent tidak boleh menghitung harga final sendiri.
2. Agent tidak boleh mengarang promo atau stok.
3. Agent tidak boleh memulai checkout jika customer masih ragu.
4. Agent tidak boleh menulis ke cart tanpa tool mutating yang eksplisit.
5. Saat confidence rendah, agent harus klarifikasi atau handoff.

## Use Cases yang Cocok

- "Aku pengen yang manis tapi nggak terlalu creamy."
- "Budget aku 30 ribuan, enaknya apa?"
- "Yang mirip pesanan aku minggu lalu apa ya?"
- "Kalau aku suka latte, ada rekomendasi lain?"
- "Promo yang paling cocok buat cart aku sekarang apa?"

## Use Cases yang Harus Tetap Deterministic

- tambah/hapus item final
- validasi promo
- hitung subtotal/diskon/pajak
- status payment
- pembuatan order
- update order status

## Langkah Implementasi Berikutnya

1. Tambahkan adapter `CustomerConversationService` di atas `ChatbotEngine`.
2. Daftarkan `CustomerAgentKernel` ke flow webhook/chat web.
3. Tambahkan tool mutating satu per satu.
4. Tambahkan observability untuk `agent_tasks` dan `agent_task_steps`.
5. Tambahkan halaman dashboard untuk melihat log advisory agent.
