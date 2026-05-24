# FAQ RAG dan Complaint Automation

Dokumen ini merangkum dua plugin customer support terbaru di proyek ini:

- `faq-rag`
- `complaint-handler`

Keduanya dirancang agar chatbot tetap bisa membantu customer di tengah flow order tanpa membuat admin cabang kehilangan kontrol atas follow-up penting.

---

## FAQ RAG

Plugin `faq-rag` menambahkan knowledge base FAQ yang bisa dipakai langsung oleh chatbot.

### Cakupan fitur

- FAQ global untuk semua cabang
- FAQ custom per cabang
- override branch terhadap FAQ global tertentu
- vector store lokal di database
- retrieval berbasis similarity untuk pertanyaan customer
- import/export `CSV` dan `Excel XML (.xls)`
- analytics FAQ paling sering ditanya
- logging query yang tidak match

### Dashboard

- [`public/dashboard/super/faqs.php`](../public/dashboard/super/faqs.php)
- [`public/dashboard/branch/faqs.php`](../public/dashboard/branch/faqs.php)

### File utama

- [`plugins/faq-rag/FaqRepository.php`](../plugins/faq-rag/FaqRepository.php)
- [`plugins/faq-rag/FaqVectorService.php`](../plugins/faq-rag/FaqVectorService.php)
- [`plugins/faq-rag/FaqRagResponder.php`](../plugins/faq-rag/FaqRagResponder.php)
- [`plugins/faq-rag/FaqSkill.php`](../plugins/faq-rag/FaqSkill.php)

### Cara kerja singkat

1. FAQ global atau cabang disimpan ke database.
2. Saat create/update/import, vector FAQ ikut diperbarui.
3. Saat customer bertanya, detector mencoba mengenali pertanyaan FAQ.
4. Retriever mengambil FAQ terdekat.
5. Jika ada override cabang aktif, override diprioritaskan.

---

## Complaint Handler

Plugin `complaint-handler` dipakai untuk menangani komplain customer saat chat order sedang berjalan.

### Cakupan fitur

- deteksi intent komplain di flow chat
- klasifikasi komplain yang cukup dijawab AI
- klasifikasi komplain yang perlu human follow-up
- pembuatan tiket komplain untuk branch admin
- dashboard daftar tiket komplain

### Dashboard

- [`public/dashboard/branch/complaints.php`](../public/dashboard/branch/complaints.php)

### File utama

- [`plugins/complaint-handler/ComplaintAnalyzer.php`](../plugins/complaint-handler/ComplaintAnalyzer.php)
- [`plugins/complaint-handler/ComplaintTicketRepository.php`](../plugins/complaint-handler/ComplaintTicketRepository.php)
- [`plugins/complaint-handler/ComplaintSkill.php`](../plugins/complaint-handler/ComplaintSkill.php)

### Cara kerja singkat

1. Pesan customer masuk ke detector intent.
2. Jika terdeteksi sebagai komplain, analyzer menilai tingkat follow-up.
3. Jika cukup aman dijawab AI, skill menjawab langsung.
4. Jika perlu manusia, plugin membuat tiket ke cabang terkait.

---

## Kombinasi di Flow Chat

Dalam implementasi saat ini:

- FAQ tetap bisa dijawab walaupun customer sedang di flow checkout
- komplain tetap bisa dideteksi walaupun customer sedang menambahkan item atau checkout
- branch admin mendapatkan dashboard operasional untuk FAQ maupun tiket komplain

Dengan pola ini, chatbot tidak hanya menjual, tetapi juga menangani pertanyaan operasional dan eskalasi layanan.
