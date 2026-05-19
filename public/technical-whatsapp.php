<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Config/config.php';
?>
<!DOCTYPE html>
<html lang="id" id="root-html">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Penjelasan Teknis WhatsApp Webhook - KopiBot AI</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <style>
    body { background: var(--coffee-cream); margin: 0; }
    .doc-wrap { max-width: 980px; margin: 0 auto; padding: 28px 20px 56px; }
    .doc-topbar {
      display: flex; align-items: center; justify-content: space-between; gap: 14px;
      margin-bottom: 18px;
    }
    .lang-btn {
      background: none; border: 1.5px solid var(--border); border-radius: 10px;
      padding: 7px 14px; font-size: .85rem; font-weight: 700; cursor: pointer;
      color: var(--text-mid); transition: border-color .2s, color .2s;
    }
    .lang-btn:hover { border-color: var(--coffee-brown); color: var(--coffee-brown); }
    .doc-hero {
      background: linear-gradient(135deg, #1a0e07, #3d2010);
      color: #fff; border-radius: 24px; padding: 32px 28px; margin-bottom: 24px;
    }
    .doc-hero h1 { margin: 0 0 10px; font-size: clamp(1.8rem, 4vw, 2.6rem); }
    .doc-hero p { margin: 0; opacity: .86; max-width: 740px; line-height: 1.7; }
    .doc-links { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
    .doc-links a { text-decoration: none; }
    .doc-grid { display: grid; gap: 18px; }
    .doc-card {
      background: #fff; border: 1px solid var(--border); border-radius: 20px;
      padding: 22px 22px 18px;
    }
    .doc-card h2 { margin: 0 0 12px; color: var(--coffee-dark); font-size: 1.15rem; }
    .doc-card p, .doc-card li { color: var(--text-mid); line-height: 1.7; }
    .doc-card ul { margin: 0; padding-left: 18px; }
    .doc-card code, .code-block {
      background: var(--coffee-cream); border-radius: 12px; display: block;
      padding: 12px 14px; overflow: auto; font-size: .9rem; color: var(--coffee-dark);
    }
    .mini-note { font-size: .86rem; color: var(--text-light); margin-top: 10px; }
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    @media (max-width: 760px) {
      .doc-topbar { flex-direction: column; align-items: flex-start; }
      .two-col { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="doc-wrap">
    <div class="doc-topbar">
      <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline"
         data-t-id="Kembali ke landing page" data-t-en="Back to landing page">Kembali ke landing page</a>
      <button class="lang-btn" id="lang-btn" onclick="toggleLang()" title="Switch language">EN</button>
    </div>

    <div class="doc-hero">
      <h1 data-t-id="Penjelasan Teknis WhatsApp Webhook" data-t-en="WhatsApp Webhook Technical Notes">Penjelasan Teknis WhatsApp Webhook</h1>
      <p data-t-id="Halaman ini menjelaskan arsitektur webhook WhatsApp di sistem toko_kopi, termasuk endpoint masuk, cara membedakan provider Wablas dan Meta Cloud API, serta plugin channel untuk Fonnte, Twilio, Baileys, MessageBird, dan Vonage, bentuk payload, proses verifikasi, dan alur pesan sampai masuk ke chatbot."
         data-t-en="This page explains the WhatsApp webhook architecture in the toko_kopi system, including the inbound endpoint, how Wablas and Meta Cloud API are distinguished, plus plugin channels for Fonnte, Twilio, Baileys, MessageBird, and Vonage, payload formats, verification flow, and how messages reach the chatbot.">
        Halaman ini menjelaskan arsitektur webhook WhatsApp di sistem toko_kopi, termasuk endpoint masuk, cara membedakan provider Wablas dan Meta Cloud API, serta plugin channel untuk Fonnte, Twilio, Baileys, MessageBird, dan Vonage, bentuk payload, proses verifikasi, dan alur pesan sampai masuk ke chatbot.
      </p>
      <div class="doc-links">
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary"
           data-t-id="Buka Dashboard Admin" data-t-en="Open Admin Dashboard">Buka Dashboard Admin</a>
        <a href="<?= BASE_URL ?>/dashboard/super/whatsapp.php" class="btn btn-outline"
           data-t-id="Pengaturan WhatsApp" data-t-en="WhatsApp Settings">Pengaturan WhatsApp</a>
        <a href="<?= BASE_URL ?>/technical-multichannel.php" class="btn btn-outline"
           data-t-id="Arsitektur Multi-Channel" data-t-en="Multi-Channel Architecture">Arsitektur Multi-Channel</a>
      </div>
    </div>

    <div class="doc-grid">
      <div class="doc-card">
        <h2 data-t-id="1. Endpoint Utama" data-t-en="1. Main Endpoint">1. Endpoint Utama</h2>
        <p data-t-id="Provider WhatsApp legacy masuk lewat satu endpoint yang sama:" data-t-en="Legacy WhatsApp providers enter through the same endpoint:">Provider WhatsApp legacy masuk lewat satu endpoint yang sama:</p>
        <code><?= BASE_URL ?>/api/whatsapp/webhook.php</code>
        <p data-t-id="Untuk konfigurasi per cabang, sistem juga mendukung URL khusus cabang:" data-t-en="For per-branch configuration, the system also supports a branch-specific URL:">Untuk konfigurasi per cabang, sistem juga mendukung URL khusus cabang:</p>
        <code><?= BASE_URL ?>/api/whatsapp/webhook.php?branch=&lt;BRANCH_ID&gt;</code>
        <p class="mini-note"
           data-t-id="URL khusus cabang paling aman dipakai untuk provider legacy seperti Wablas karena branch bisa diikat langsung dari query string."
           data-t-en="The branch-specific URL is the safest choice for legacy providers such as Wablas because the branch can be bound directly from the query string.">
          URL khusus cabang paling aman dipakai untuk provider legacy seperti Wablas karena branch bisa diikat langsung dari query string.
        </p>
        <p class="mini-note"
           data-t-id="Fonnte, Twilio, dan Vonage sekarang memakai endpoint plugin channel per cabang di /api/channel/webhook.php."
           data-t-en="Fonnte, Twilio, and Vonage now use per-branch plugin channel endpoints under /api/channel/webhook.php.">
          Fonnte, Twilio, dan Vonage sekarang memakai endpoint plugin channel per cabang di /api/channel/webhook.php.
        </p>
      </div>

      <div class="doc-card">
        <h2 data-t-id="2. File Teknis yang Terlibat" data-t-en="2. Technical Files Involved">2. File Teknis yang Terlibat</h2>
        <ul>
          <li><strong data-t-id="Endpoint webhook:" data-t-en="Webhook endpoint:">Endpoint webhook:</strong> <code>public/api/whatsapp/webhook.php</code></li>
          <li><strong data-t-id="Factory provider:" data-t-en="Provider factory:">Factory provider:</strong> <code>app/WhatsAppProviders/ProviderFactory.php</code></li>
          <li><strong data-t-id="Adapter Meta Cloud API:" data-t-en="Meta Cloud API adapter:">Adapter Meta Cloud API:</strong> <code>app/WhatsAppProviders/MetaCloudApiProvider.php</code></li>
          <li><strong data-t-id="Plugin channel Fonnte:" data-t-en="Fonnte plugin channel:">Plugin channel Fonnte:</strong> <code>plugins/fonnte-whatsapp/FonnteWhatsAppChannel.php</code></li>
          <li><strong data-t-id="Plugin channel Twilio:" data-t-en="Twilio plugin channel:">Plugin channel Twilio:</strong> <code>plugins/twilio-whatsapp/TwilioWhatsAppChannel.php</code></li>
          <li><strong data-t-id="Plugin channel Baileys:" data-t-en="Baileys plugin channel:">Plugin channel Baileys:</strong> <code>plugins/baileys-whatsapp/BaileysWhatsAppChannel.php</code></li>
          <li><strong data-t-id="Plugin channel MessageBird:" data-t-en="MessageBird plugin channel:">Plugin channel MessageBird:</strong> <code>plugins/messagebird-whatsapp/MessageBirdWhatsAppChannel.php</code></li>
          <li><strong data-t-id="Plugin channel Vonage:" data-t-en="Vonage plugin channel:">Plugin channel Vonage:</strong> <code>plugins/vonage-whatsapp/VonageWhatsAppChannel.php</code></li>
          <li><strong data-t-id="Engine chatbot:" data-t-en="Chatbot engine:">Engine chatbot:</strong> <code>app/Services/ChatbotEngine.php</code></li>
        </ul>
        <p class="mini-note"
           data-t-id="Halaman ini ditautkan dari landing page dan halaman pengaturan WhatsApp supaya tim admin bisa berpindah dari setup ke dokumentasi teknis dengan cepat."
           data-t-en="This page is linked from the landing page and WhatsApp settings pages so the admin team can move quickly from setup to technical documentation.">
          Halaman ini ditautkan dari landing page dan halaman pengaturan WhatsApp supaya tim admin bisa berpindah dari setup ke dokumentasi teknis dengan cepat.
        </p>
        <p class="mini-note"
           data-t-id="Untuk skenario satu cabang membuka WhatsApp, Telegram, dan Discord sekaligus, lihat juga halaman arsitektur multi-channel."
           data-t-en="For the scenario where one branch opens WhatsApp, Telegram, and Discord at the same time, see the multi-channel architecture page as well.">
          Untuk skenario satu cabang membuka WhatsApp, Telegram, dan Discord sekaligus, lihat juga halaman arsitektur multi-channel.
        </p>
      </div>

      <div class="doc-card">
        <h2 data-t-id="3. Alur Masuk Pesan" data-t-en="3. Incoming Message Flow">3. Alur Masuk Pesan</h2>
        <ul>
          <li data-t-id="Provider mengirim request ke endpoint webhook." data-t-en="The provider sends a request to the webhook endpoint.">Provider mengirim request ke endpoint webhook.</li>
          <li data-t-id="Sistem mendeteksi provider dari query string, header, atau struktur payload." data-t-en="The system detects the provider from the query string, headers, or payload structure.">Sistem mendeteksi provider dari query string, header, atau struktur payload.</li>
          <li data-t-id="Adapter provider memverifikasi request bila perlu." data-t-en="The provider adapter verifies the request when needed.">Adapter provider memverifikasi request bila perlu.</li>
          <li data-t-id="Payload dinormalisasi ke format internal: from, message, raw." data-t-en="The payload is normalized into the internal format: from, message, raw.">Payload dinormalisasi ke format internal: from, message, raw.</li>
          <li data-t-id="Pesan masuk ke ChatbotEngine untuk diproses sebagai order, pertanyaan, promo, atau checkout." data-t-en="The message enters ChatbotEngine to be processed as an order, question, promo, or checkout.">Pesan masuk ke ChatbotEngine untuk diproses sebagai order, pertanyaan, promo, atau checkout.</li>
          <li data-t-id="Balasan dikirim lagi lewat adapter provider yang sama." data-t-en="The reply is sent back through the same provider adapter.">Balasan dikirim lagi lewat adapter provider yang sama.</li>
        </ul>
      </div>

      <div class="two-col">
        <div class="doc-card">
          <h2 data-t-id="4. Format Payload Fonnte" data-t-en="4. Fonnte Payload Format">4. Format Payload Fonnte</h2>
          <p data-t-id="Deteksi Fonnte dilakukan saat payload memiliki field sender." data-t-en="Fonnte is detected when the payload contains the sender field.">Deteksi Fonnte dilakukan saat payload memiliki field sender.</p>
          <div class="code-block">{
  "sender": "6281234567890",
  "message": "pesan 2 latte",
  "device": "6281234567891"
}</div>
          <ul>
            <li><strong>sender</strong>: <span data-t-id="nomor WhatsApp customer" data-t-en="customer WhatsApp number">nomor WhatsApp customer</span></li>
            <li><strong>message</strong>: <span data-t-id="isi pesan yang dikirim customer" data-t-en="message body sent by the customer">isi pesan yang dikirim customer</span></li>
            <li><strong>device</strong>: <span data-t-id="nomor atau device branch yang menerima pesan" data-t-en="the branch number or device receiving the message">nomor atau device branch yang menerima pesan</span></li>
          </ul>
          <p class="mini-note"
             data-t-id="Di implementasi saat ini, Fonnte tidak memakai signature verification yang ketat. Validasi utamanya adalah setting provider aktif dan API key tersedia."
             data-t-en="In the current implementation, Fonnte does not use strict signature verification. The main validation is that the provider setting is active and the API key is available.">
            Di implementasi saat ini, Fonnte tidak memakai signature verification yang ketat. Validasi utamanya adalah setting provider aktif dan API key tersedia.
          </p>
        </div>

        <div class="doc-card">
          <h2 data-t-id="5. Format Payload Meta Cloud API" data-t-en="5. Meta Cloud API Payload Format">5. Format Payload Meta Cloud API</h2>
          <p data-t-id="Deteksi Meta dilakukan saat payload memiliki entry[0].changes." data-t-en="Meta is detected when the payload contains entry[0].changes.">Deteksi Meta dilakukan saat payload memiliki entry[0].changes.</p>
          <div class="code-block">{
  "object": "whatsapp_business_account",
  "entry": [{
    "changes": [{
      "field": "messages",
      "value": {
        "messages": [{
          "from": "6281234567890",
          "type": "text",
          "text": { "body": "pesan 1 americano" }
        }]
      }
    }]
  }]
}</div>
          <ul>
            <li><strong>messages[0].from</strong>: <span data-t-id="nomor customer" data-t-en="customer number">nomor customer</span></li>
            <li><strong>messages[0].text.body</strong>: <span data-t-id="isi pesan" data-t-en="message content">isi pesan</span></li>
          </ul>
        </div>
      </div>

      <div class="two-col">
        <div class="doc-card">
          <h2 data-t-id="6. Format Payload Twilio" data-t-en="6. Twilio Payload Format">6. Format Payload Twilio</h2>
          <p data-t-id="Twilio mengirim webhook dalam format application/x-www-form-urlencoded. Field utama yang dipakai adalah From, To, Body, dan AccountSid."
             data-t-en="Twilio sends webhooks as application/x-www-form-urlencoded. The main fields used here are From, To, Body, and AccountSid.">
            Twilio mengirim webhook dalam format application/x-www-form-urlencoded. Field utama yang dipakai adalah From, To, Body, dan AccountSid.
          </p>
          <div class="code-block">From=whatsapp:%2B6281234567890
To=whatsapp:%2B14155238886
Body=pesan%201%20latte
AccountSid=ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX</div>
          <p class="mini-note"
             data-t-id="Provider Twilio di project ini mengirim balasan lewat REST API Messages dan dapat memverifikasi header X-Twilio-Signature bila Auth Token diisi."
             data-t-en="The Twilio provider in this project sends replies through the REST API Messages resource and can verify the X-Twilio-Signature header when the Auth Token is filled in.">
            Provider Twilio di project ini mengirim balasan lewat REST API Messages dan dapat memverifikasi header X-Twilio-Signature bila Auth Token diisi.
          </p>
        </div>

        <div class="doc-card">
          <h2 data-t-id="7. Format Payload Baileys Bridge" data-t-en="7. Baileys Bridge Payload Format">7. Format Payload Baileys Bridge</h2>
          <p data-t-id="Baileys bukan provider native PHP. Ia harus berjalan sebagai service Node.js yang meneruskan pesan ke endpoint plugin channel branch ini."
             data-t-en="Baileys is not a native PHP provider. It should run as a Node.js service that forwards messages to this branch plugin channel endpoint.">
            Baileys bukan provider native PHP. Ia harus berjalan sebagai service Node.js yang meneruskan pesan ke endpoint plugin channel branch ini.
          </p>
          <div class="code-block">{
  "bridge": "baileys",
  "from": "6281234567890",
  "message": "pesan 1 americano"
}</div>
          <p class="mini-note"
             data-t-id="Balasan dari PHP dikirim kembali ke service bridge melalui setting outbound_url plugin, lalu service Node Baileys yang meneruskannya ke WhatsApp."
             data-t-en="Replies from PHP are sent back to the bridge service through the plugin outbound_url setting, and then the Node Baileys service forwards them to WhatsApp.">
            Balasan dari PHP dikirim kembali ke service bridge melalui setting outbound_url plugin, lalu service Node Baileys yang meneruskannya ke WhatsApp.
          </p>
        </div>
      </div>

      <div class="two-col">
        <div class="doc-card">
          <h2 data-t-id="8. Format Payload MessageBird" data-t-en="8. MessageBird Payload Format">8. Format Payload MessageBird</h2>
          <p data-t-id="MessageBird Conversations API mengirim webhook JSON saat ada pesan masuk dengan type message.created dan direction received."
             data-t-en="The MessageBird Conversations API sends a JSON webhook on inbound messages with type message.created and direction received.">
            MessageBird Conversations API mengirim webhook JSON saat ada pesan masuk dengan type message.created dan direction received.
          </p>
          <div class="code-block">{
  "type": "message.created",
  "message": {
    "id": "msg_id_xxx",
    "direction": "received",
    "type": "text",
    "source": "6281234567890",
    "content": { "text": "pesan 1 latte" }
  }
}</div>
          <ul>
            <li><strong>message.source</strong>: <span data-t-id="nomor WhatsApp customer" data-t-en="customer WhatsApp number">nomor WhatsApp customer</span></li>
            <li><strong>message.content.text</strong>: <span data-t-id="isi pesan" data-t-en="message content">isi pesan</span></li>
          </ul>
          <p class="mini-note"
             data-t-id="Plugin channel MessageBird membaca payload type=message.created. Signature webhook diverifikasi via HMAC-SHA256 menggunakan Signing Key dari dashboard MessageBird."
             data-t-en="The MessageBird plugin channel reads payloads with type=message.created. Webhook signatures are verified via HMAC-SHA256 using the Signing Key from the MessageBird dashboard.">
            Plugin channel MessageBird membaca payload type=message.created. Signature webhook diverifikasi via HMAC-SHA256 menggunakan Signing Key dari dashboard MessageBird.
          </p>
        </div>

        <div class="doc-card">
          <h2 data-t-id="9. Format Payload Vonage" data-t-en="9. Vonage Payload Format">9. Format Payload Vonage</h2>
          <p data-t-id="Vonage Messages API mengirim webhook JSON untuk pesan WhatsApp masuk dengan field channel=whatsapp."
             data-t-en="The Vonage Messages API sends a JSON webhook for inbound WhatsApp messages with the channel=whatsapp field.">
            Vonage Messages API mengirim webhook JSON untuk pesan WhatsApp masuk dengan field channel=whatsapp.
          </p>
          <div class="code-block">{
  "channel": "whatsapp",
  "message_type": "text",
  "text": "pesan 1 americano",
  "from": "6281234567890",
  "to": "6281234567891",
  "message_uuid": "uuid-xxx",
  "timestamp": "2024-01-01T10:00:00Z"
}</div>
          <ul>
            <li><strong>from</strong>: <span data-t-id="nomor customer" data-t-en="customer number">nomor customer</span></li>
            <li><strong>text</strong>: <span data-t-id="isi pesan" data-t-en="message content">isi pesan</span></li>
          </ul>
          <p class="mini-note"
             data-t-id="Vonage menggunakan Basic Auth (API Key:API Secret) untuk pengiriman pesan. Verifikasi webhook dilakukan via header X-Nexmo-Signature atau X-Vonage-Signature dengan HMAC-SHA256."
             data-t-en="Vonage uses Basic Auth (API Key:API Secret) for sending messages. Webhook verification is done via the X-Nexmo-Signature or X-Vonage-Signature header using HMAC-SHA256.">
            Vonage menggunakan Basic Auth (API Key:API Secret) untuk pengiriman pesan. Verifikasi webhook dilakukan via header X-Nexmo-Signature atau X-Vonage-Signature dengan HMAC-SHA256.
          </p>
        </div>
      </div>

      <div class="two-col">
        <div class="doc-card">
          <h2 data-t-id="10. Verifikasi Meta Cloud API" data-t-en="10. Meta Cloud API Verification">10. Verifikasi Meta Cloud API</h2>
          <p data-t-id="Meta membutuhkan verifikasi webhook lewat request GET dengan parameter challenge:" data-t-en="Meta requires webhook verification through a GET request with challenge parameters:">Meta membutuhkan verifikasi webhook lewat request GET dengan parameter challenge:</p>
          <div class="code-block">GET /api/whatsapp/webhook.php?hub_mode=subscribe&amp;hub_verify_token=TOKEN&amp;hub_challenge=12345</div>
          <p data-t-id="Sistem akan:" data-t-en="The system will:">Sistem akan:</p>
          <ul>
            <li data-t-id="mencari webhook_token yang cocok di branch_whatsapp_settings" data-t-en="look up a matching webhook_token in branch_whatsapp_settings">mencari webhook_token yang cocok di branch_whatsapp_settings</li>
            <li data-t-id="mengembalikan hub_challenge bila token valid" data-t-en="return hub_challenge when the token is valid">mengembalikan hub_challenge bila token valid</li>
            <li data-t-id="mengembalikan HTTP 403 bila token salah" data-t-en="return HTTP 403 when the token is invalid">mengembalikan HTTP 403 bila token salah</li>
          </ul>
        </div>

        <div class="doc-card">
          <h2 data-t-id="11. Signature / Security" data-t-en="11. Signature / Security">11. Signature / Security</h2>
          <p data-t-id="Untuk Meta, sistem mendukung pengecekan X-Hub-Signature-256 bila api_secret diisi." data-t-en="For Meta, the system supports X-Hub-Signature-256 verification when api_secret is filled in.">Untuk Meta, sistem mendukung pengecekan X-Hub-Signature-256 bila api_secret diisi.</p>
          <p data-t-id="MessageBird menggunakan HMAC-SHA256 dari gabungan timestamp, URL, dan SHA256(body) via header MessageBird-Signature. Vonage menggunakan HMAC-SHA256 dari body raw via header X-Nexmo-Signature atau X-Vonage-Signature."
             data-t-en="MessageBird uses HMAC-SHA256 of the timestamp, URL, and SHA256(body) combination via the MessageBird-Signature header. Vonage uses HMAC-SHA256 of the raw body via the X-Nexmo-Signature or X-Vonage-Signature header.">
            MessageBird menggunakan HMAC-SHA256 dari gabungan timestamp, URL, dan SHA256(body) via header MessageBird-Signature. Vonage menggunakan HMAC-SHA256 dari body raw via header X-Nexmo-Signature atau X-Vonage-Signature.
          </p>
          <p data-t-id="Bila api_secret kosong, webhook tetap diterima agar setup lebih fleksibel saat tahap integrasi awal." data-t-en="If api_secret is empty, the webhook is still accepted to keep setup more flexible during early integration.">Bila api_secret kosong, webhook tetap diterima agar setup lebih fleksibel saat tahap integrasi awal.</p>
          <p class="mini-note"
             data-t-id="Rekomendasi production: selalu isi api_secret untuk Meta, MessageBird, dan Vonage agar request bisa diverifikasi dengan HMAC SHA-256."
             data-t-en="Production recommendation: always fill in api_secret for Meta, MessageBird, and Vonage so requests can be verified with HMAC SHA-256.">
            Rekomendasi production: selalu isi api_secret untuk Meta, MessageBird, dan Vonage agar request bisa diverifikasi dengan HMAC SHA-256.
          </p>
        </div>
      </div>

      <div class="doc-card">
        <h2 data-t-id="12. Normalisasi Internal" data-t-en="12. Internal Normalization">12. Normalisasi Internal</h2>
        <p data-t-id="Setelah payload lolos verifikasi, setiap provider diubah ke bentuk internal yang seragam seperti ini:" data-t-en="After the payload passes verification, each provider is transformed into a consistent internal structure like this:">Setelah payload lolos verifikasi, setiap provider diubah ke bentuk internal yang seragam seperti ini:</p>
        <div class="code-block">[
  'from'    =&gt; '6281234567890',
  'message' =&gt; 'pesan 1 latte',
  'raw'     =&gt; [ ...original payload... ]
]</div>
        <p data-t-id="Format inilah yang kemudian dikirim ke engine chatbot, jadi skill order, promo, dan cart tidak perlu tahu pesan itu datang dari Fonnte atau Meta."
           data-t-en="This is the format sent into the chatbot engine, so the order, promo, and cart skills do not need to know whether the message came from Fonnte or Meta.">
          Format inilah yang kemudian dikirim ke engine chatbot, jadi skill order, promo, dan cart tidak perlu tahu pesan itu datang dari Fonnte atau Meta.
        </p>
      </div>

      <div class="doc-card">
        <h2 data-t-id="13. Kaitan dengan Dashboard" data-t-en="13. Relation to the Dashboard">13. Kaitan dengan Dashboard</h2>
        <ul>
          <li data-t-id="Branch admin: atur provider, nomor WA, API key, secret, dan verify token dari halaman pengaturan WhatsApp cabang."
              data-t-en="Branch admin: manage provider, WA number, API key, secret, and verify token from the branch WhatsApp settings page.">
            Branch admin: atur provider, nomor WA, API key, secret, dan verify token dari halaman pengaturan WhatsApp cabang.
          </li>
          <li data-t-id="Super admin: bisa melihat dan mengatur semua provider di seluruh cabang."
              data-t-en="Super admin: can view and manage all providers across all branches.">
            Super admin: bisa melihat dan mengatur semua provider di seluruh cabang.
          </li>
          <li data-t-id="Webhook token: terutama penting untuk Meta Cloud API."
              data-t-en="Webhook token: especially important for Meta Cloud API.">
            Webhook token: terutama penting untuk Meta Cloud API.
          </li>
          <li data-t-id="Nomor WA branch: dipakai untuk pencocokan request provider ke cabang yang tepat."
              data-t-en="Branch WA number: used to match incoming provider requests to the correct branch.">
            Nomor WA branch: dipakai untuk pencocokan request provider ke cabang yang tepat.
          </li>
        </ul>
      </div>

      <div class="doc-card">
        <h2 data-t-id="14. Checklist Setup" data-t-en="14. Setup Checklist">14. Checklist Setup</h2>
        <ul>
          <li data-t-id="Pastikan branch sudah aktif." data-t-en="Make sure the branch is active.">Pastikan branch sudah aktif.</li>
          <li data-t-id="Pilih provider WhatsApp yang benar di dashboard." data-t-en="Choose the correct WhatsApp provider in the dashboard.">Pilih provider WhatsApp yang benar di dashboard.</li>
          <li data-t-id="Isi nomor WhatsApp branch." data-t-en="Fill in the branch WhatsApp number.">Isi nomor WhatsApp branch.</li>
          <li data-t-id="Isi api_key sesuai provider." data-t-en="Fill in api_key according to the provider.">Isi api_key sesuai provider.</li>
          <li data-t-id="Untuk Meta, isi juga api_secret dan webhook_token." data-t-en="For Meta, also fill in api_secret and webhook_token.">Untuk Meta, isi juga api_secret dan webhook_token.</li>
          <li data-t-id="Untuk MessageBird, isi api_key (Access Key), api_secret (Signing Key), dan Channel ID di section plugin MessageBird." data-t-en="For MessageBird, fill in api_key (Access Key), api_secret (Signing Key), and the Channel ID in the MessageBird plugin section.">Untuk MessageBird, isi api_key (Access Key), api_secret (Signing Key), dan Channel ID di section plugin MessageBird.</li>
          <li data-t-id="Untuk Vonage, isi api_key dan api_secret dari dashboard Vonage." data-t-en="For Vonage, fill in api_key and api_secret from the Vonage dashboard.">Untuk Vonage, isi api_key dan api_secret dari dashboard Vonage.</li>
          <li data-t-id="Daftarkan URL webhook ke provider." data-t-en="Register the webhook URL with the provider.">Daftarkan URL webhook ke provider.</li>
          <li data-t-id="Uji kirim pesan masuk dan cek apakah balasan bot muncul." data-t-en="Send a test message and check whether the bot reply appears.">Uji kirim pesan masuk dan cek apakah balasan bot muncul.</li>
        </ul>
      </div>
    </div>
  </div>

  <script>
  (function () {
    var current = localStorage.getItem('kopibot_tech_lang')
      || localStorage.getItem('kopibot_lang')
      || 'id';

    function applyLang(lang) {
      document.querySelectorAll('[data-t-id]').forEach(function (el) {
        el.innerHTML = lang === 'en'
          ? (el.dataset.tEn || el.dataset.tId)
          : el.dataset.tId;
      });

      var btn = document.getElementById('lang-btn');
      if (btn) btn.textContent = lang === 'id' ? 'EN' : 'ID';

      document.getElementById('root-html').setAttribute('lang', lang === 'en' ? 'en' : 'id');
      document.title = lang === 'en'
        ? 'WhatsApp Webhook Technical Notes - KopiBot AI'
        : 'Penjelasan Teknis WhatsApp Webhook - KopiBot AI';

      localStorage.setItem('kopibot_tech_lang', lang);
      localStorage.setItem('kopibot_lang', lang);
      current = lang;
    }

    window.toggleLang = function () {
      applyLang(current === 'id' ? 'en' : 'id');
    };

    document.addEventListener('DOMContentLoaded', function () {
      applyLang(current);
    });
  })();
  </script>
</body>
</html>
