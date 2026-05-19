<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Config/config.php';
?>
<!DOCTYPE html>
<html lang="id" id="root-html">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Penjelasan Teknis Multi-Channel Chatbot - KopiBot AI</title>
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
    .doc-hero p { margin: 0; opacity: .86; max-width: 760px; line-height: 1.7; }
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
      <h1 data-t-id="Penjelasan Teknis Multi-Channel Chatbot" data-t-en="Multi-Channel Chatbot Technical Notes">Penjelasan Teknis Multi-Channel Chatbot</h1>
      <p data-t-id="Halaman ini menjelaskan bagaimana satu cabang bisa membuka WhatsApp, Telegram, dan Discord sekaligus, tetapi tetap memakai menu, promo, currency, dan dashboard cabang yang sama."
         data-t-en="This page explains how a single branch can open WhatsApp, Telegram, and Discord at the same time while still using the same branch menu, promos, currency, and dashboard.">
        Halaman ini menjelaskan bagaimana satu cabang bisa membuka WhatsApp, Telegram, dan Discord sekaligus, tetapi tetap memakai menu, promo, currency, dan dashboard cabang yang sama.
      </p>
      <div class="doc-links">
        <a href="<?= BASE_URL ?>/technical-whatsapp.php" class="btn btn-outline"
           data-t-id="Dokumentasi WhatsApp" data-t-en="WhatsApp Documentation">Dokumentasi WhatsApp</a>
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary"
           data-t-id="Buka Dashboard Admin" data-t-en="Open Admin Dashboard">Buka Dashboard Admin</a>
      </div>
    </div>

    <div class="doc-grid">
      <div class="doc-card">
        <h2 data-t-id="1. Konsep Inti" data-t-en="1. Core Concept">1. Konsep Inti</h2>
        <p data-t-id="Satu branch_id dapat memiliki beberapa channel aktif sekaligus. Channel hanya menjadi pintu masuk pesan, sedangkan business logic tetap dipusatkan di ChatbotEngine."
           data-t-en="A single branch_id can have multiple active channels at once. Channels only act as message entry points, while the business logic stays centralized in ChatbotEngine.">
          Satu branch_id dapat memiliki beberapa channel aktif sekaligus. Channel hanya menjadi pintu masuk pesan, sedangkan business logic tetap dipusatkan di ChatbotEngine.
        </p>
        <div class="code-block">Customer -> Channel Webhook -> Provider Adapter -> ChatbotEngine -> Reply</div>
      </div>

      <div class="doc-card">
        <h2 data-t-id="2. Channel yang Didukung" data-t-en="2. Supported Channels">2. Channel yang Didukung</h2>
        <ul>
          <li><strong>web</strong>: <span data-t-id="chat widget di website" data-t-en="website chat widget">chat widget di website</span></li>
          <li><strong>whatsapp</strong>: <span data-t-id="Fonnte, Wablas, Meta Cloud API" data-t-en="Fonnte, Wablas, Meta Cloud API">Fonnte, Wablas, Meta Cloud API</span></li>
          <li><strong>telegram</strong>: <span data-t-id="Telegram Bot webhook" data-t-en="Telegram Bot webhook">Telegram Bot webhook</span></li>
          <li><strong>discord</strong>: <span data-t-id="Discord slash command / interaction webhook" data-t-en="Discord slash command / interaction webhook">Discord slash command / interaction webhook</span></li>
        </ul>
      </div>

      <div class="two-col">
        <div class="doc-card">
          <h2 data-t-id="3. Mapping per Cabang" data-t-en="3. Per-Branch Mapping">3. Mapping per Cabang</h2>
          <p data-t-id="Untuk satu cabang, semua channel tetap mengarah ke data branch yang sama: menu, promo, bahasa, mata uang, PPN, dan dashboard order."
             data-t-en="For one branch, all channels still point to the same branch data: menu, promos, language, currency, VAT, and order dashboard.">
            Untuk satu cabang, semua channel tetap mengarah ke data branch yang sama: menu, promo, bahasa, mata uang, PPN, dan dashboard order.
          </p>
          <div class="code-block">Branch 2 (Bandung)
- WhatsApp  -> /api/whatsapp/webhook.php?branch=2
- Telegram  -> /api/channel/webhook.php?channel=telegram
- Discord   -> /api/channel/webhook.php?channel=discord</div>
        </div>

        <div class="doc-card">
          <h2 data-t-id="4. Tabel Konfigurasi" data-t-en="4. Configuration Tables">4. Tabel Konfigurasi</h2>
          <ul>
            <li><strong>branch_whatsapp_settings</strong>: <span data-t-id="khusus provider WhatsApp" data-t-en="specific to WhatsApp providers">khusus provider WhatsApp</span></li>
            <li><strong>branch_bot_settings</strong>: <span data-t-id="dipakai fallback migrasi dari core lama" data-t-en="used as migration fallback from the old core">dipakai fallback migrasi dari core lama</span></li>
            <li><strong>plugin_branch_settings</strong>: <span data-t-id="konfigurasi channel plugin seperti Telegram dan Discord baru" data-t-en="configuration for plugin channels like the new Telegram and Discord">konfigurasi channel plugin seperti Telegram dan Discord baru</span></li>
          </ul>
          <p class="mini-note"
             data-t-id="WhatsApp dipisah karena sudah punya beberapa adapter legacy. Telegram dan Discord sekarang bisa berjalan penuh sebagai plugin, dengan fallback baca setting lama saat migrasi."
             data-t-en="WhatsApp stays separate because it already has several legacy adapters. Telegram and Discord can now run fully as plugins, with fallback reads from old settings during migration.">
            WhatsApp dipisah karena sudah punya beberapa adapter legacy. Telegram dan Discord sekarang bisa berjalan penuh sebagai plugin, dengan fallback baca setting lama saat migrasi.
          </p>
        </div>
      </div>

      <div class="doc-card">
        <h2 data-t-id="5. Reuse ChatbotEngine" data-t-en="5. Reusing ChatbotEngine">5. Reuse ChatbotEngine</h2>
        <p data-t-id="Semua channel pada akhirnya memanggil method yang sama:" data-t-en="All channels eventually call the same method:">Semua channel pada akhirnya memanggil method yang sama:</p>
        <div class="code-block">$engine->process($channel, $branchId, $customerIdentifier, $message);</div>
        <p data-t-id="Artinya fitur menu, cart, promo, checkout, variant, topping, dan order history tidak perlu dibuat ulang per platform."
           data-t-en="This means menu, cart, promo, checkout, variants, toppings, and order history do not need to be rebuilt per platform.">
          Artinya fitur menu, cart, promo, checkout, variant, topping, dan order history tidak perlu dibuat ulang per platform.
        </p>
      </div>

      <div class="two-col">
        <div class="doc-card">
          <h2 data-t-id="6. Isolasi Customer & Cart" data-t-en="6. Customer & Cart Isolation">6. Isolasi Customer & Cart</h2>
          <p data-t-id="Session key dibangun dari channel, branch, dan identifier customer. Karena itu, customer yang sama di WhatsApp dan Telegram akan dianggap dua sesi yang berbeda."
             data-t-en="The session key is built from the channel, branch, and customer identifier. Because of that, the same person on WhatsApp and Telegram is treated as two different sessions.">
            Session key dibangun dari channel, branch, dan identifier customer. Karena itu, customer yang sama di WhatsApp dan Telegram akan dianggap dua sesi yang berbeda.
          </p>
          <div class="code-block">hash('sha256', "{$channel}:{$branchId}:{$identifier}")</div>
        </div>

        <div class="doc-card">
          <h2 data-t-id="7. Dampaknya ke Order" data-t-en="7. Impact on Orders">7. Dampaknya ke Order</h2>
          <ul>
            <li data-t-id="cart WhatsApp tidak bercampur dengan cart Telegram" data-t-en="a WhatsApp cart does not mix with a Telegram cart">cart WhatsApp tidak bercampur dengan cart Telegram</li>
            <li data-t-id="riwayat customer tetap tersimpan per channel" data-t-en="customer history stays stored per channel">riwayat customer tetap tersimpan per channel</li>
            <li data-t-id="order tetap masuk ke cabang yang sama" data-t-en="orders still go into the same branch">order tetap masuk ke cabang yang sama</li>
            <li data-t-id="dashboard bisa melihat channel asal order" data-t-en="the dashboard can still see the order source channel">dashboard bisa melihat channel asal order</li>
          </ul>
        </div>
      </div>

      <div class="doc-card">
        <h2 data-t-id="8. Contoh Implementasi Cabang" data-t-en="8. Example Branch Setup">8. Contoh Implementasi Cabang</h2>
        <div class="code-block">Cabang Surabaya
- WhatsApp: aktif
- Telegram: aktif
- Discord: aktif

Semua memakai:
- menu Surabaya
- promo Surabaya
- currency Surabaya
- dashboard order Surabaya</div>
        <p class="mini-note"
           data-t-id="Telegram juga bisa dipasang sebagai satu bot host untuk semua cabang. Dalam mode itu, customer memilih cabang di awal chat lalu percakapan diarahkan ke cabang terpilih."
           data-t-en="Telegram can also run as one host bot for all branches. In that mode, the customer chooses a branch at the start of the chat and the conversation is routed to that branch.">
          Telegram juga bisa dipasang sebagai satu bot host untuk semua cabang. Dalam mode itu, customer memilih cabang di awal chat lalu percakapan diarahkan ke cabang terpilih.
        </p>
        <p class="mini-note"
           data-t-id="Discord juga mendukung mode yang sama: satu bot per cabang atau satu bot host untuk semua cabang aktif."
           data-t-en="Discord also supports the same mode: one bot per branch or one host bot for all active branches.">
          Discord juga mendukung mode yang sama: satu bot per cabang atau satu bot host untuk semua cabang aktif.
        </p>
      </div>

      <div class="doc-card">
        <h2 data-t-id="9. Batasan Saat Ini" data-t-en="9. Current Limitations">9. Batasan Saat Ini</h2>
        <ul>
          <li data-t-id="Telegram dan Discord sekarang dikonfigurasi dari halaman Settings branch lewat plugin." data-t-en="Telegram and Discord are now configured from the branch Settings page via plugins.">Telegram dan Discord sekarang dikonfigurasi dari halaman Settings branch lewat plugin.</li>
          <li data-t-id="Discord saat ini dirancang untuk interaction/slash command, bukan membaca semua chat channel bebas." data-t-en="Discord is currently designed for interaction/slash commands, not for reading every free-form channel message.">Discord saat ini dirancang untuk interaction/slash command, bukan membaca semua chat channel bebas.</li>
          <li data-t-id="Belum ada account linking lintas channel secara native." data-t-en="There is no native cross-channel account linking yet.">Belum ada account linking lintas channel secara native.</li>
        </ul>
      </div>

      <div class="doc-card">
        <h2 data-t-id="10. Checklist Aktivasi" data-t-en="10. Activation Checklist">10. Checklist Aktivasi</h2>
        <ul>
          <li data-t-id="Pastikan branch aktif dan menu cabang sudah siap." data-t-en="Make sure the branch is active and the branch menu is ready.">Pastikan branch aktif dan menu cabang sudah siap.</li>
          <li data-t-id="Aktifkan provider WhatsApp bila perlu di branch_whatsapp_settings." data-t-en="Enable a WhatsApp provider in branch_whatsapp_settings when needed.">Aktifkan provider WhatsApp bila perlu di branch_whatsapp_settings.</li>
          <li data-t-id="Untuk Telegram, aktifkan plugin dan isi token di halaman Settings cabang." data-t-en="For Telegram, enable the plugin and fill in the token on the branch Settings page.">Untuk Telegram, aktifkan plugin dan isi token di halaman Settings cabang.</li>
          <li data-t-id="Untuk Discord, aktifkan plugin dan isi token di halaman Settings cabang." data-t-en="For Discord, enable the plugin and fill in the token on the branch Settings page.">Untuk Discord, aktifkan plugin dan isi token di halaman Settings cabang.</li>
          <li data-t-id="Daftarkan webhook masing-masing channel ke endpoint yang sesuai." data-t-en="Register each channel webhook to the correct endpoint.">Daftarkan webhook masing-masing channel ke endpoint yang sesuai.</li>
          <li data-t-id="Uji masing-masing channel untuk memastikan reply memakai branch yang sama." data-t-en="Test each channel to ensure replies use the same branch.">Uji masing-masing channel untuk memastikan reply memakai branch yang sama.</li>
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
        ? 'Multi-Channel Chatbot Technical Notes - KopiBot AI'
        : 'Penjelasan Teknis Multi-Channel Chatbot - KopiBot AI';

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
