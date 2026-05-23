<?php
/*
 * ☕ AI Agent Coffee Shop Commerce Platform
 * Platform AI untuk otomatisasi order, customer service,
 * loyalty customer, dan manajemen multi cabang coffee shop.
 *
 * 🚀 Features:
 * - AI Chatbot Order Menu
 * - WhatsApp / Telegram / Discord Integration
 * - Multi Branch Management
 * - AI Upselling & Promo Recommendation
 * - Order via Website & Chat Apps
 * - Variant Product & Topping Support
 * - Multi Currency, Tax & Timezone
 * - AI Customer Interaction Automation
 *
 * 💻 Tech Stack:
 * PHP Native • MySQL • OpenAI • Anthropic
 * WhatsApp Gateway • REST API • LLM AI
 *
 * ☕ Suitable For:
 * Coffee Shop • Cafe • Restaurant • Bakery • Beverage Store
 *
 * Dibuat & Dikembangkan oleh:
 * Kukuh TW
 *
 * 📧 Email     : kukuhtw@gmail.com
 * 📱 WhatsApp  : https://wa.me/628129893706
 * 📷 Instagram : @kukuhtw
 * 🐦 X/Twitter : @kukuhtw
 * 👍 Facebook  : https://www.facebook.com/kukuhtw
 * 💼 LinkedIn  : https://linkedin.com/in/kukuhtw
 *
 * 🌐 Demo:
 * https://botlelang.com/toko_kopi
 *
 * © 2026 Kukuh TW. All rights reserved.
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Config/config.php';
use App\Models\BranchModel;
$branchModel = new BranchModel();
$branches    = $branchModel->getActive();
?>
<!DOCTYPE html>
<html lang="id" id="root-html">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KopiBot AI — Chatbot Pemesanan untuk Toko Kopi</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <style>
    :root { --gold:#c8922a; --body-text:#4a3020; --body-text-light:#6b4c35; }
    * { box-sizing:border-box; }
    body { background:#fff; margin:0; font-family:'Segoe UI',sans-serif; }

    /* ── NAV ── */
    .nav {
      position:sticky; top:0; z-index:100;
      display:flex; align-items:center; justify-content:space-between;
      padding:14px 40px; background:rgba(255,255,255,.97);
      backdrop-filter:blur(8px); border-bottom:1px solid var(--border);
    }
    .nav-brand { display:flex; align-items:center; gap:10px; font-size:1.15rem; font-weight:700; color:var(--coffee-dark); }
    .nav-links  { display:flex; gap:20px; align-items:center; }
    .nav-links a { color:#4a3020; text-decoration:none; font-size:.9rem; font-weight:500; transition:color .2s; }
    .nav-links a:hover { color:var(--coffee-brown); }
    .lang-btn {
      background:none; border:1.5px solid var(--border); border-radius:6px;
      padding:4px 10px; font-size:.8rem; font-weight:700; cursor:pointer;
      color:#4a3020; transition:border-color .2s,color .2s;
    }
    .lang-btn:hover { border-color:var(--coffee-brown); color:var(--coffee-brown); }
    .nav .btn-primary { color:#fff !important; }

    /* ── HERO ── */
    .hero {
      background:linear-gradient(135deg, #1a0e07 0%, #3d2010 60%, #6f4e37 100%);
      color:#fff; text-align:center; padding:100px 20px 80px;
      position:relative; overflow:hidden;
    }
    .hero::before {
      content:''; position:absolute; inset:0;
      background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .hero-badge {
      display:inline-block; background:rgba(200,146,42,.3); border:1px solid rgba(200,146,42,.6);
      color:#f5d080; font-size:.78rem; font-weight:700; letter-spacing:.06em;
      padding:5px 16px; border-radius:20px; margin-bottom:22px; text-transform:uppercase;
    }
    .hero h1 { font-size:clamp(2rem,5vw,3.4rem); font-weight:800; line-height:1.15; margin:0 0 20px; }
    .hero h1 span { color:#f0c060; }
    .hero p { font-size:1.1rem; color:rgba(255,255,255,.93); max-width:560px; margin:0 auto 36px; line-height:1.7; }
    .hero-btns { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }
    .btn-gold {
      background:linear-gradient(135deg,#c8922a,#e8b84b); color:#1a0e07;
      font-weight:700; border:none; border-radius:var(--radius); cursor:pointer;
      padding:14px 28px; font-size:1rem; text-decoration:none; display:inline-flex; align-items:center; gap:8px;
      box-shadow:0 4px 16px rgba(200,146,42,.4); transition:transform .2s, box-shadow .2s;
    }
    .btn-gold:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(200,146,42,.5); }
    .btn-ghost {
      background:rgba(255,255,255,.14); color:#fff; border:1.5px solid rgba(255,255,255,.4);
      border-radius:var(--radius); padding:14px 28px; font-size:1rem; text-decoration:none;
      display:inline-flex; align-items:center; gap:8px; transition:background .2s;
    }
    .btn-ghost:hover { background:rgba(255,255,255,.22); }
    .hero-stats {
      display:flex; gap:40px; justify-content:center; flex-wrap:wrap;
      margin-top:56px; padding-top:40px; border-top:1px solid rgba(255,255,255,.15);
    }
    .hero-stat { text-align:center; }
    .hero-stat strong { display:block; font-size:2rem; font-weight:800; color:#f0c060; }
    .hero-stat > span { font-size:.83rem; color:rgba(255,255,255,.88); }
    .hero-remark {
      max-width:1100px; margin:28px auto 0; text-align:left;
      background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.16);
      border-radius:24px; padding:24px; backdrop-filter:blur(8px);
      box-shadow:0 18px 40px rgba(0,0,0,.16);
    }
    .hero-remark-head { display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:14px; }
    .hero-remark-chip {
      display:inline-flex; align-items:center; gap:8px; padding:8px 14px;
      border-radius:999px; background:rgba(240,192,96,.16); border:1px solid rgba(240,192,96,.28);
      color:#ffe2ad; font-size:.78rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase;
    }
    .hero-remark-title { font-size:1.15rem; font-weight:800; color:#fff3d8; }
    .hero-remark-desc { margin:0 0 18px; max-width:760px; color:rgba(255,255,255,.9); line-height:1.75; }
    .hero-remark-grid {
      display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px;
    }
    .hero-remark-card {
      background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12);
      border-radius:18px; padding:18px;
    }
    .hero-remark-card h3 { margin:0 0 10px; font-size:.95rem; color:#ffe2ad; }
    .hero-remark-card p, .hero-remark-card li {
      margin:0; color:rgba(255,255,255,.9); font-size:.9rem; line-height:1.7;
    }
    .hero-remark-card ul { margin:0; padding-left:18px; }
    .hero-remark-card li + li { margin-top:4px; }
    .hero-remark-card a { color:#ffe2ad; text-decoration:none; }
    .hero-remark-card a:hover { text-decoration:underline; }

    /* ── SECTION ── */
    .section { padding:72px 20px; }
    .section-inner { max-width:1040px; margin:0 auto; }
    .section-label { font-size:.78rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--coffee-brown); margin-bottom:10px; }
    .section-title { font-size:clamp(1.5rem,3vw,2.2rem); font-weight:800; color:var(--coffee-dark); margin:0 0 14px; }
    .section-sub   { font-size:1rem; color:var(--body-text); max-width:600px; line-height:1.75; margin-bottom:48px; }

    /* ── FEATURES ── */
    .feature-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; }
    .feature-card {
      background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg);
      padding:26px 22px; transition:box-shadow .2s, transform .2s;
    }
    .feature-card:hover { box-shadow:0 8px 28px rgba(0,0,0,.1); transform:translateY(-3px); }
    .feature-icon  { font-size:2.2rem; margin-bottom:12px; }
    .feature-title { font-weight:700; color:var(--coffee-dark); margin-bottom:8px; font-size:.95rem; }
    .feature-desc  { font-size:.85rem; color:var(--body-text); line-height:1.65; }

    /* ── PROMO DETAIL ── */
    .promo-detail {
      background:var(--coffee-cream); border-radius:var(--radius-lg);
      padding:36px; margin-top:40px; display:grid;
      grid-template-columns:1fr 1fr; gap:24px;
    }
    .promo-detail-item { display:flex; gap:14px; align-items:flex-start; }
    .promo-detail-icon  { font-size:1.5rem; flex-shrink:0; margin-top:2px; }
    .promo-detail-title { font-weight:700; color:var(--coffee-dark); font-size:.9rem; margin-bottom:4px; }
    .promo-detail-desc  { font-size:.84rem; color:var(--body-text); line-height:1.6; }

    /* ── HOW IT WORKS ── */
    .bg-cream { background:var(--coffee-cream); }
    .steps { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:0; }
    .step { text-align:center; padding:28px 20px; position:relative; }
    .step:not(:last-child)::after {
      content:'→'; position:absolute; right:-10px; top:50%; transform:translateY(-50%);
      font-size:1.4rem; color:var(--coffee-brown); opacity:.4;
    }
    .step-num {
      width:48px; height:48px; border-radius:50%; background:var(--coffee-brown);
      color:#fff; font-weight:800; font-size:1.2rem;
      display:flex; align-items:center; justify-content:center; margin:0 auto 16px;
    }
    .step-title { font-weight:700; color:var(--coffee-dark); margin-bottom:8px; }
    .step-desc  { font-size:.86rem; color:var(--body-text); line-height:1.65; }

    /* ── DEMO ── */
    .branch-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:16px; }
    .branch-card {
      background:#fff; border-radius:var(--radius-lg); padding:22px;
      border:1px solid var(--border); box-shadow:0 2px 10px var(--shadow);
      transition:transform .2s, box-shadow .2s;
    }
    .branch-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px var(--shadow); }
    .branch-card h3 { color:var(--coffee-dark); margin:10px 0 6px; font-size:1rem; }
    .branch-card p  { font-size:.85rem; color:var(--body-text); margin-bottom:14px; }
    .branch-card-btns { display:flex; gap:8px; }

    /* ── TECH SPECS ── */
    .specs-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; }
    .spec-row {
      display:flex; align-items:baseline; gap:10px; padding:10px 0;
      border-bottom:1px solid var(--border); font-size:.875rem;
    }
    .spec-label { font-weight:700; color:var(--coffee-dark); min-width:140px; }
    .spec-value { color:var(--body-text); }

    /* ── OPEN SOURCE ── */
    .oss-panel {
      background:linear-gradient(135deg, rgba(200,146,42,.12), rgba(111,78,55,.08));
      border:1px solid rgba(111,78,55,.16); border-radius:var(--radius-lg);
      padding:32px; display:grid; grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr); gap:24px;
    }
    .oss-note {
      display:inline-flex; align-items:center; gap:8px;
      background:#fff; border:1px solid rgba(111,78,55,.14); border-radius:999px;
      padding:6px 14px; font-size:.78rem; font-weight:700; color:var(--coffee-dark);
      letter-spacing:.04em; text-transform:uppercase; margin-bottom:16px;
    }
    .oss-panel h3 { margin:0 0 12px; color:var(--coffee-dark); font-size:1.4rem; }
    .oss-panel p { margin:0; color:var(--body-text); line-height:1.75; }
    .oss-list {
      list-style:none; padding:0; margin:0;
      display:grid; gap:12px;
    }
    .oss-list li {
      background:#fff; border:1px solid rgba(111,78,55,.14); border-radius:16px;
      padding:16px 18px; color:var(--body-text); line-height:1.65;
    }
    .oss-list strong { display:block; color:var(--coffee-dark); margin-bottom:4px; }
    .license-grid {
      display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:20px;
    }
    .license-card {
      background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg);
      padding:26px 24px; box-shadow:0 10px 28px rgba(0,0,0,.06);
    }
    .license-card h3 { margin:0 0 10px; color:var(--coffee-dark); font-size:1.1rem; }
    .license-card p { margin:0 0 14px; color:var(--body-text); line-height:1.72; font-size:.92rem; }
    .license-card ul { margin:0; padding-left:18px; color:var(--body-text); }
    .license-card li + li { margin-top:6px; }
    .license-card-highlight {
      border-color:rgba(111,78,55,.28);
      background:linear-gradient(180deg, rgba(255,250,243,.96), rgba(246,239,232,.96));
    }

    /* ── PRICING ── */
    .pricing-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:24px; margin-top:8px; }
    .pricing-card {
      border:2px solid var(--border); border-radius:var(--radius-lg);
      padding:36px 28px; position:relative; transition:transform .2s;
    }
    .pricing-card:hover { transform:translateY(-4px); }
    .pricing-card.featured { border-color:var(--coffee-brown); box-shadow:0 8px 28px rgba(111,78,55,.2); }
    .pricing-badge {
      position:absolute; top:-13px; left:50%; transform:translateX(-50%);
      background:var(--coffee-brown); color:#fff; font-size:.72rem; font-weight:700;
      padding:4px 16px; border-radius:20px; letter-spacing:.05em; text-transform:uppercase;
    }
    .pricing-name  { font-size:1rem; font-weight:700; color:var(--body-text); margin-bottom:8px; }
    .pricing-price { font-size:2.2rem; font-weight:800; color:var(--coffee-dark); margin-bottom:4px; }
    .pricing-price small { font-size:1rem; font-weight:500; color:var(--body-text); }
    .pricing-desc  { font-size:.85rem; color:var(--body-text); margin-bottom:24px; line-height:1.6; }
    .pricing-list  { list-style:none; padding:0; margin:0 0 28px; }
    .pricing-list li { font-size:.875rem; padding:7px 0; border-bottom:1px solid var(--border); color:var(--coffee-dark); }
    .pricing-list li::before { content:'✓ '; color:var(--coffee-brown); font-weight:700; }

    /* ── CTA ── */
    .cta-section {
      background:linear-gradient(135deg, #1a0e07, #3d2010);
      color:#fff; text-align:center; padding:80px 20px;
    }
    .cta-section h2 { font-size:clamp(1.6rem,4vw,2.4rem); font-weight:800; margin-bottom:14px; }
    .cta-section p  { color:rgba(255,255,255,.88); max-width:500px; margin:0 auto 36px; line-height:1.75; }
    .cta-contacts { display:flex; gap:16px; justify-content:center; flex-wrap:wrap; }

    /* ── FOOTER ── */
    .footer {
      background:var(--coffee-dark); color:rgba(255,255,255,.6);
      text-align:center; padding:28px 20px; font-size:.83rem;
    }

    @media(max-width:640px) {
      .nav { padding:14px 20px; }
      .hero-remark { padding:20px 16px; border-radius:20px; }
      .hero-remark-grid { grid-template-columns:1fr; }
      .step:not(:last-child)::after { display:none; }
      .promo-detail { grid-template-columns:1fr; }
      .oss-panel { grid-template-columns:1fr; padding:24px; }
    }
  </style>
</head>
<body>

<!-- Nav -->
<nav class="nav">
  <div class="nav-brand">☕ KopiBot <span style="font-weight:400;color:var(--body-text-light)">AI</span></div>
  <div class="nav-links">
    <a href="#fitur"><span data-t-id="Fitur" data-t-en="Features">Fitur</span></a>
    <a href="#demo">Demo</a>
    <a href="#harga"><span data-t-id="Harga" data-t-en="Pricing">Harga</span></a>
    <a href="<?= BASE_URL ?>/docs/index.php"><span data-t-id="Docs" data-t-en="Docs">Docs</span></a>
    <a href="<?= BASE_URL ?>/readme.php"><span data-t-id="README" data-t-en="README">README</span></a>
    <a href="#kontak" class="btn btn-primary btn-sm"><span data-t-id="Hubungi Kami" data-t-en="Contact Us">Hubungi Kami</span></a>
    <button class="lang-btn" id="lang-btn" onclick="toggleLang()" title="Switch language">EN</button>
  </div>
</nav>

<!-- Hero -->
<section class="hero">
  <div class="hero-badge" data-t-id="☕ Siap Deploy · Multi-Cabang · Tanpa Biaya Langganan" data-t-en="☕ Ready to Deploy · Multi-Branch · No Monthly Fees">☕ Siap Deploy · Multi-Cabang · Tanpa Biaya Langganan</div>
  <h1 id="hero-h1">Chatbot Pemesanan Otomatis<br>untuk <span>Toko Kopi</span> Kamu</h1>
  <p data-t-id="Terima pesanan kapan saja lewat Website &amp; WhatsApp — tanpa jaga kasir, tanpa biaya bulanan. Source code tersedia dengan model lisensi AGPL untuk open source dan commercial license untuk deployment proprietary."
     data-t-en="Accept orders anytime via Website &amp; WhatsApp — no cashier needed, no monthly fees. The source code is offered under AGPL for open source usage and a commercial license for proprietary deployments.">Terima pesanan kapan saja lewat Website &amp; WhatsApp — tanpa jaga kasir, tanpa biaya bulanan. Source code tersedia dengan model lisensi AGPL untuk open source dan commercial license untuk deployment proprietary.</p>
  <div class="hero-btns">
    <a href="#demo" class="btn-gold"><span data-t-id="🚀 Coba Demo Gratis" data-t-en="🚀 Try Free Demo">🚀 Coba Demo Gratis</span></a>
    <a href="https://github.com/kukuhtw/toko_kopi" class="btn-ghost" target="_blank" rel="noopener"><span data-t-id="🔗 Lihat GitHub" data-t-en="🔗 View GitHub">🔗 Lihat GitHub</span></a>
    <a href="#kontak" class="btn-ghost"><span data-t-id="💬 Tanya Harga" data-t-en="💬 Ask for Pricing">💬 Tanya Harga</span></a>
  </div>
  <div class="hero-stats">
    <div class="hero-stat"><strong>24/7</strong><span data-t-id="Buka terus, tanpa libur" data-t-en="Always open, never closed">Buka terus, tanpa libur</span></div>
    <div class="hero-stat"><strong>4 Channel</strong><span>Web, WhatsApp, Telegram, Discord</span></div>
    <div class="hero-stat"><strong data-t-id="Multi Cabang" data-t-en="Multi Branch">Multi Cabang</strong><span data-t-id="Satu dashboard, semua cabang" data-t-en="One dashboard, all branches">Satu dashboard, semua cabang</span></div>
    <div class="hero-stat"><strong>Plugin Ready</strong><span data-t-id="Ekstensi fitur tanpa ubah core" data-t-en="Extend features without touching core">Ekstensi fitur tanpa ubah core</span></div>
  </div>
  <div class="hero-remark">
    <div class="hero-remark-head">
      <div class="hero-remark-chip">&#9749; Product Remark</div>
      <div class="hero-remark-title">AI Agent Coffee Shop Commerce Platform</div>
    </div>
    <p class="hero-remark-desc">Platform AI untuk otomatisasi order, customer service, loyalty customer, dan manajemen multi cabang coffee shop.</p>
    <div class="hero-remark-grid">
      <div class="hero-remark-card">
        <h3>&#128640; Features</h3>
        <ul>
          <li>AI Chatbot Order Menu</li>
          <li>WhatsApp / Telegram / Discord Integration</li>
          <li>Multi Branch Management</li>
          <li>AI Upselling &amp; Promo Recommendation</li>
          <li>Order via Website &amp; Chat Apps</li>
          <li>Variant Product &amp; Topping Support</li>
          <li>Multi Currency, Tax &amp; Timezone</li>
          <li>AI Customer Interaction Automation</li>
        </ul>
      </div>
      <div class="hero-remark-card">
        <h3>&#128187; Tech Stack</h3>
        <p>PHP Native &bull; MySQL &bull; OpenAI &bull; Anthropic</p>
        <p>WhatsApp Gateway &bull; REST API &bull; LLM AI</p>
        <h3 style="margin-top:14px">&#9749; Suitable For</h3>
        <p>Coffee Shop &bull; Cafe &bull; Restaurant &bull; Bakery &bull; Beverage Store</p>
      </div>
      <div class="hero-remark-card">
        <h3>Dibuat &amp; Dikembangkan oleh</h3>
        <p>Kukuh TW</p>
        <p style="margin-top:10px">&#128231; <a href="mailto:kukuhtw@gmail.com">kukuhtw@gmail.com</a></p>
        <p>&#128241; <a href="https://wa.me/628129893706" target="_blank">wa.me/628129893706</a></p>
        <p>&#127760; <a href="https://botlelang.com/toko_kopi" target="_blank">botlelang.com/toko_kopi</a></p>
        <p style="margin-top:10px">&copy; 2026 Kukuh TW. All rights reserved.</p>
      </div>
    </div>
  </div>
</section>

<!-- Features -->
<section class="section" id="fitur">
  <div class="section-inner">
    <p class="section-label" data-t-id="Fitur Unggulan" data-t-en="Key Features">Fitur Unggulan</p>
    <h2 class="section-title" data-t-id="Satu Sistem, Semua Kebutuhan" data-t-en="One System, Everything You Need">Satu Sistem, Semua Kebutuhan</h2>
    <p class="section-sub"
       data-t-id="Chatbot pemesanan, manajemen order, promo, dan analitik — semua dalam satu sistem PHP yang ringan. Siap deploy hari ini."
       data-t-en="Ordering chatbot, order management, promos, and analytics — all in one lightweight PHP system. Ready to deploy today.">Chatbot pemesanan, manajemen order, promo, dan analitik — semua dalam satu sistem PHP yang ringan. Siap deploy hari ini.</p>
    <div class="feature-grid">
      <div class="feature-card">
        <div class="feature-icon">🤖</div>
        <div class="feature-title" data-t-id="AI Memahami Bahasa Natural" data-t-en="AI Understands Natural Language">AI Memahami Bahasa Natural</div>
        <div class="feature-desc"
             data-t-id="Customer cukup ketik seperti biasa — 'pesan 2 latte', 'ganti jadi 3', 'batalkan' — tanpa command atau format khusus."
             data-t-en="Customers type naturally — 'order 2 lattes', 'change to 3', 'cancel' — no special commands or formats needed.">Customer cukup ketik seperti biasa — 'pesan 2 latte', 'ganti jadi 3', 'batalkan' — tanpa command atau format khusus.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📱</div>
        <div class="feature-title" data-t-id="WhatsApp &amp; Website" data-t-en="WhatsApp &amp; Website">WhatsApp &amp; Website</div>
        <div class="feature-desc"
             data-t-id="Terintegrasi dengan Fonnte, Wablas, Meta Cloud API, Twilio, dan Baileys Bridge. Customer bisa pesan dari channel manapun."
             data-t-en="Integrated with Fonnte, Wablas, Meta Cloud API, Twilio, and Baileys Bridge. Customers can order from any channel.">Terintegrasi dengan Fonnte, Wablas, Meta Cloud API, Twilio, dan Baileys Bridge. Customer bisa pesan dari channel manapun.</div>
        <div style="margin-top:12px">
          <a href="<?= BASE_URL ?>/technical-whatsapp.php" class="btn btn-outline" style="font-size:.82rem;padding:9px 14px"
             data-t-id="Lihat Penjelasan Teknis" data-t-en="View Technical Notes">Lihat Penjelasan Teknis</a>
        </div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🏪</div>
        <div class="feature-title" data-t-id="Multi Cabang Tanpa Batas" data-t-en="Unlimited Branches">Multi Cabang Tanpa Batas</div>
        <div class="feature-desc"
             data-t-id="Setiap cabang punya menu, harga, promo, nomor WA, dan pengaturan sendiri. Semua terpantau dari satu dashboard super admin."
             data-t-en="Each branch has its own menu, prices, promos, WA number, and settings. All monitored from one super admin dashboard.">Setiap cabang punya menu, harga, promo, nomor WA, dan pengaturan sendiri. Semua terpantau dari satu dashboard super admin.</div>
        <div style="margin-top:12px">
          <a href="<?= BASE_URL ?>/technical-multichannel.php" class="btn btn-outline" style="font-size:.82rem;padding:9px 14px"
             data-t-id="Arsitektur Multi-Channel" data-t-en="Multi-Channel Architecture">Arsitektur Multi-Channel</a>
        </div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🍽️</div>
        <div class="feature-title" data-t-id="Katalog Menu Besar" data-t-en="Large Menu Catalog">Katalog Menu Besar</div>
        <div class="feature-desc"
             data-t-id="Dirancang untuk menampung hingga 1.000 item menu per sistem — tetap cepat dicari lewat chat maupun dashboard."
             data-t-en="Designed to handle up to 1,000 menu items per system — still fast to search from chat or dashboard.">Dirancang untuk menampung hingga 1.000 item menu per sistem — tetap cepat dicari lewat chat maupun dashboard.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🥤</div>
        <div class="feature-title" data-t-id="Varian &amp; Ukuran Menu" data-t-en="Menu Variants &amp; Sizes">Varian &amp; Ukuran Menu</div>
        <div class="feature-desc"
             data-t-id="Satu produk bisa punya pilihan Small, Medium, Large, atau varian lain dengan harga berbeda — cocok untuk minuman hingga paket custom."
             data-t-en="One product can have Small, Medium, Large, or custom variants with different prices — perfect for drinks, bundles, and custom items.">Satu produk bisa punya pilihan Small, Medium, Large, atau varian lain dengan harga berbeda — cocok untuk minuman hingga paket custom.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🌐</div>
        <div class="feature-title" data-t-id="Bilingual Indonesia &amp; English" data-t-en="Bilingual Indonesian &amp; English">Bilingual Indonesia &amp; English</div>
        <div class="feature-desc"
             data-t-id="Bot bisa merespons dalam Bahasa Indonesia, English, atau bilingual — dapat diatur per cabang sesuai target pelanggan."
             data-t-en="Bot can respond in Indonesian, English, or bilingual — configurable per branch based on target customers.">Bot bisa merespons dalam Bahasa Indonesia, English, atau bilingual — dapat diatur per cabang sesuai target pelanggan.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">⚙️</div>
        <div class="feature-title" data-t-id="Pengaturan Per Cabang" data-t-en="Per-Branch Settings">Pengaturan Per Cabang</div>
        <div class="feature-desc"
             data-t-id="Atur mata uang (IDR, USD, dll.), zona waktu (WIB/WITA/WIT), tarif PPN, dan nomor WhatsApp masing-masing cabang secara independen."
             data-t-en="Set currency (IDR, USD, etc.), timezone (WIB/WITA/WIT), VAT rate, and WhatsApp number per branch independently.">Atur mata uang (IDR, USD, dll.), zona waktu (WIB/WITA/WIT), tarif PPN, dan nomor WhatsApp masing-masing cabang secara independen.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🎉</div>
        <div class="feature-title" data-t-id="Sistem Promo Lengkap" data-t-en="Complete Promo System">Sistem Promo Lengkap</div>
        <div class="feature-desc"
             data-t-id="Promo global + per cabang, auto-apply, berbasis loyalitas, dan diskon per kategori. Cabang bisa ikut, override, atau opt-out dari promo global."
             data-t-en="Global + per-branch promos, auto-apply, loyalty-based, and category discounts. Branches can join, override, or opt out of global promos.">Promo global + per cabang, auto-apply, berbasis loyalitas, dan diskon per kategori. Cabang bisa ikut, override, atau opt-out dari promo global.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🕐</div>
        <div class="feature-title" data-t-id="Promo Berbasis Waktu" data-t-en="Time-Based Promos">Promo Berbasis Waktu</div>
        <div class="feature-desc"
             data-t-id="Promo dengan batas tanggal dan jam yang presisi, otomatis menyesuaikan zona waktu masing-masing cabang."
             data-t-en="Promos with precise date and time limits, automatically adjusted to each branch's local timezone.">Promo dengan batas tanggal dan jam yang presisi, otomatis menyesuaikan zona waktu masing-masing cabang.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🛒</div>
        <div class="feature-title" data-t-id="Keranjang via Chat" data-t-en="Cart via Chat">Keranjang via Chat</div>
        <div class="feature-desc"
             data-t-id="Tambah, ubah jumlah, hapus item, atau kosongkan keranjang lewat chat biasa. Ringkasan ditampilkan otomatis setiap ada perubahan."
             data-t-en="Add, edit quantity, remove items, or clear cart via chat. Summary shown automatically after every change.">Tambah, ubah jumlah, hapus item, atau kosongkan keranjang lewat chat biasa. Ringkasan ditampilkan otomatis setiap ada perubahan.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">💳</div>
        <div class="feature-title" data-t-id="PPN Otomatis" data-t-en="Auto VAT">PPN Otomatis</div>
        <div class="feature-desc"
             data-t-id="Tarif PPN diatur per cabang (0–100%). Dihitung otomatis setelah diskon promo, ditampilkan transparan di ringkasan order."
             data-t-en="VAT rate configurable per branch (0–100%). Auto-calculated after promo discounts, shown transparently in the order summary.">Tarif PPN diatur per cabang (0–100%). Dihitung otomatis setelah diskon promo, ditampilkan transparan di ringkasan order.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📊</div>
        <div class="feature-title" data-t-id="Dashboard Dua Level" data-t-en="Two-Level Dashboard">Dashboard Dua Level</div>
        <div class="feature-desc"
             data-t-id="Super admin memantau semua cabang dan konversi revenue ke IDR. Admin cabang kelola menu, promo, dan order cabangnya sendiri."
             data-t-en="Super admin monitors all branches and revenue in IDR. Branch admin manages their own menu, promos, and orders.">Super admin memantau semua cabang dan konversi revenue ke IDR. Admin cabang kelola menu, promo, dan order cabangnya sendiri.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📋</div>
        <div class="feature-title" data-t-id="Manajemen Order Lengkap" data-t-en="Full Order Management">Manajemen Order Lengkap</div>
        <div class="feature-desc"
             data-t-id="Tracking status (pending → delivered), log perubahan, catatan internal admin, dan status pembayaran — semuanya tercatat."
             data-t-en="Status tracking (pending → delivered), change log, internal admin notes, and payment status — all recorded.">Tracking status (pending → delivered), log perubahan, catatan internal admin, dan status pembayaran — semuanya tercatat.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">💰</div>
        <div class="feature-title" data-t-id="Monitoring Biaya AI" data-t-en="AI Cost Monitoring">Monitoring Biaya AI</div>
        <div class="feature-desc"
             data-t-id="Pantau biaya API LLM (OpenAI / Anthropic) per cabang dan per hari — tidak ada angka tersembunyi."
             data-t-en="Monitor LLM API costs (OpenAI / Anthropic) per branch and per day — no hidden numbers.">Pantau biaya API LLM (OpenAI / Anthropic) per cabang dan per hari — tidak ada angka tersembunyi.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🔧</div>
        <div class="feature-title" data-t-id="Pilih AI Sendiri" data-t-en="Choose Your AI">Pilih AI Sendiri</div>
        <div class="feature-desc"
             data-t-id="Gunakan OpenAI, Anthropic, atau rule-based gratis. Bisa diganti kapan saja tanpa mengubah kode bisnis."
             data-t-en="Use OpenAI, Anthropic, or free rule-based. Switch anytime without touching business logic.">Gunakan OpenAI, Anthropic, atau rule-based gratis. Bisa diganti kapan saja tanpa mengubah kode bisnis.</div>
      </div>
    </div>

    <!-- Promo system detail -->
    <div class="promo-detail">
      <div style="grid-column:1/-1;margin-bottom:8px">
        <h3 style="font-size:1.1rem;font-weight:800;color:var(--coffee-dark);margin:0 0 6px"
            data-t-id="🎉 Detail Sistem Promo" data-t-en="🎉 Promo System Details">🎉 Detail Sistem Promo</h3>
        <p style="font-size:.875rem;color:var(--body-text);margin:0;line-height:1.6"
           data-t-id="Dua lapis promo: super admin kelola promo global, admin cabang bisa salin, kustomisasi, atau opt-out kapan saja."
           data-t-en="Two-tier promos: super admin manages global promos, branch admin can copy, customize, or opt out anytime.">Dua lapis promo: super admin kelola promo global, admin cabang bisa salin, kustomisasi, atau opt-out kapan saja.</p>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">🌐</div>
        <div>
          <div class="promo-detail-title" data-t-id="Promo Global" data-t-en="Global Promos">Promo Global</div>
          <div class="promo-detail-desc"
               data-t-id="Dibuat oleh super admin, berlaku di semua cabang secara default."
               data-t-en="Created by super admin, active across all branches by default.">Dibuat oleh super admin, berlaku di semua cabang secara default.</div>
        </div>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">🏪</div>
        <div>
          <div class="promo-detail-title" data-t-id="Promo Eksklusif Cabang" data-t-en="Branch-Exclusive Promos">Promo Eksklusif Cabang</div>
          <div class="promo-detail-desc"
               data-t-id="Admin cabang buat promo khusus yang hanya berlaku di cabangnya."
               data-t-en="Branch admin creates promos exclusive to their own branch.">Admin cabang buat promo khusus yang hanya berlaku di cabangnya.</div>
        </div>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">📋</div>
        <div>
          <div class="promo-detail-title" data-t-id="Salin &amp; Override" data-t-en="Copy &amp; Override">Salin &amp; Override</div>
          <div class="promo-detail-desc"
               data-t-id="Salin promo global ke cabang tertentu, lalu edit nilai diskon, periode, atau nonaktifkan sesuai kebutuhan."
               data-t-en="Copy a global promo to a specific branch, then edit the discount, period, or deactivate as needed.">Salin promo global ke cabang tertentu, lalu edit nilai diskon, periode, atau nonaktifkan sesuai kebutuhan.</div>
        </div>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">🚫</div>
        <div>
          <div class="promo-detail-title" data-t-id="Opt-Out Per Cabang" data-t-en="Per-Branch Opt-Out">Opt-Out Per Cabang</div>
          <div class="promo-detail-desc"
               data-t-id="Cabang bisa tidak mengikuti promo global tertentu tanpa mempengaruhi cabang lain."
               data-t-en="A branch can skip specific global promos without affecting other branches.">Cabang bisa tidak mengikuti promo global tertentu tanpa mempengaruhi cabang lain.</div>
        </div>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">⚡</div>
        <div>
          <div class="promo-detail-title" data-t-id="Auto-Apply" data-t-en="Auto-Apply">Auto-Apply</div>
          <div class="promo-detail-desc"
               data-t-id="Promo terbaik diterapkan otomatis — customer tidak perlu tahu atau input kode apapun."
               data-t-en="Best promo applied automatically — customers don't need to know or enter any code.">Promo terbaik diterapkan otomatis — customer tidak perlu tahu atau input kode apapun.</div>
        </div>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">👑</div>
        <div>
          <div class="promo-detail-title" data-t-id="Promo Loyalitas" data-t-en="Loyalty Promos">Promo Loyalitas</div>
          <div class="promo-detail-desc"
               data-t-id="Promo eksklusif untuk pelanggan setia dengan jumlah transaksi minimum dalam periode tertentu."
               data-t-en="Exclusive promos for loyal customers who meet a minimum transaction count in a set period.">Promo eksklusif untuk pelanggan setia dengan jumlah transaksi minimum dalam periode tertentu.</div>
        </div>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">🏷️</div>
        <div>
          <div class="promo-detail-title" data-t-id="Diskon Per Kategori" data-t-en="Category Discounts">Diskon Per Kategori</div>
          <div class="promo-detail-desc"
               data-t-id="Diskon hanya dihitung dari subtotal kategori tertentu — misalnya hanya menu kopi, bukan seluruh order."
               data-t-en="Discount calculated only on a specific category's subtotal — e.g., coffee items only, not the entire order.">Diskon hanya dihitung dari subtotal kategori tertentu — misalnya hanya menu kopi, bukan seluruh order.</div>
        </div>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">🕐</div>
        <div>
          <div class="promo-detail-title" data-t-id="Periode Presisi + Timezone" data-t-en="Precise Period + Timezone">Periode Presisi + Timezone</div>
          <div class="promo-detail-desc"
               data-t-id="Batas waktu sampai jam dan menit, menggunakan zona waktu cabang masing-masing (WIB, WITA, WIT, dll.)."
               data-t-en="Time limits down to the hour and minute, using each branch's local timezone (WIB, WITA, WIT, etc.).">Batas waktu sampai jam dan menit, menggunakan zona waktu cabang masing-masing (WIB, WITA, WIT, dll.).</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- How it works -->
<section class="section bg-cream">
  <div class="section-inner">
    <p class="section-label" data-t-id="Cara Kerja" data-t-en="How It Works">Cara Kerja</p>
    <h2 class="section-title" data-t-id="Pesan Semudah Chat Biasa" data-t-en="Order as Easy as a Chat">Pesan Semudah Chat Biasa</h2>
    <p class="section-sub"
       data-t-id="Tidak perlu install app, tidak perlu registrasi. Customer cukup chat — bot proses pesanan dari awal sampai konfirmasi."
       data-t-en="No app to install, no registration required. Just chat — the bot handles the order from start to confirmation.">Tidak perlu install app, tidak perlu registrasi. Customer cukup chat — bot proses pesanan dari awal sampai konfirmasi.</p>
    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <div class="step-title" data-t-id="Customer Chat" data-t-en="Customer Chats">Customer Chat</div>
        <div class="step-desc"
             data-t-id="Kirim pesan lewat WhatsApp atau website dalam bahasa sehari-hari, Indonesia maupun Inggris."
             data-t-en="Send a message via WhatsApp or the website in everyday language — Indonesian or English.">Kirim pesan lewat WhatsApp atau website dalam bahasa sehari-hari, Indonesia maupun Inggris.</div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div class="step-title" data-t-id="AI Tangkap Pesanan" data-t-en="AI Captures the Order">AI Tangkap Pesanan</div>
        <div class="step-desc"
             data-t-id="Bot memahami maksud pesan, mencocokkan menu, dan menambahkan item ke keranjang secara otomatis."
             data-t-en="Bot understands the message, matches the menu, and adds items to the cart automatically.">Bot memahami maksud pesan, mencocokkan menu, dan menambahkan item ke keranjang secara otomatis.</div>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div class="step-title" data-t-id="Promo &amp; Checkout" data-t-en="Promo &amp; Checkout">Promo &amp; Checkout</div>
        <div class="step-desc"
             data-t-id="Bot terapkan promo terbaik secara otomatis, kumpulkan data pengiriman, lalu tampilkan ringkasan sebelum konfirmasi."
             data-t-en="Bot auto-applies the best promo, collects delivery details, then shows a summary before confirmation.">Bot terapkan promo terbaik secara otomatis, kumpulkan data pengiriman, lalu tampilkan ringkasan sebelum konfirmasi.</div>
      </div>
      <div class="step">
        <div class="step-num">4</div>
        <div class="step-title" data-t-id="Order Masuk Dashboard" data-t-en="Order Enters Dashboard">Order Masuk Dashboard</div>
        <div class="step-desc"
             data-t-id="Admin lihat order masuk, proses, dan update status — customer dapat notifikasi realtime."
             data-t-en="Admin sees the order, processes it, and updates status — customer gets a real-time notification.">Admin lihat order masuk, proses, dan update status — customer dapat notifikasi realtime.</div>
      </div>
    </div>
  </div>
</section>

<!-- Demo -->
<section class="section" id="demo">
  <div class="section-inner">
    <p class="section-label">Live Demo</p>
    <h2 class="section-title" data-t-id="Coba Langsung Sekarang" data-t-en="Try It Now">Coba Langsung Sekarang</h2>
    <p class="section-sub"
       data-t-id="Ini sistem nyata yang sedang berjalan — bukan mockup. Pilih cabang dan rasakan pengalaman pesan lewat chatbot AI."
       data-t-en="This is a real running system — not a mockup. Pick a branch and experience AI chatbot ordering yourself.">Ini sistem nyata yang sedang berjalan — bukan mockup. Pilih cabang dan rasakan pengalaman pesan lewat chatbot AI.</p>
    <div class="branch-grid">
      <?php foreach ($branches as $b): ?>
      <div class="branch-card">
        <div style="font-size:2rem">🏪</div>
        <h3><?= htmlspecialchars($b['name']) ?></h3>
        <p>📍 <?= htmlspecialchars($b['address'] ?? $b['city'] ?? 'Indonesia') ?></p>
      <div class="branch-card-btns">
          <a href="<?= BASE_URL ?>/chat.php?branch=<?= $b['id'] ?>" class="btn btn-primary btn-sm">💬 Chat Demo</a>
          <a href="<?= BASE_URL ?>/order.php?branch=<?= htmlspecialchars($b['slug']) ?>" class="btn btn-outline btn-sm">🛒 Order</a>
          <a href="<?= BASE_URL ?>/customer/login.php" class="btn btn-outline btn-sm">👤 Customer Dashboard</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p style="text-align:center;margin-top:28px;font-size:.85rem;color:var(--body-text-light)">
    <span data-t-id="Order yang masuk bisa dipantau di"
          data-t-en="Incoming orders can be monitored in the">Order yang masuk bisa dipantau di</span>
    <a href="<?= BASE_URL ?>/login.php" style="color:var(--coffee-brown);font-weight:600" data-t-id="dashboard admin" data-t-en="admin dashboard">dashboard admin</a>.
    <span style="margin-left:6px">Customer juga bisa cek poin dan history di</span>
    <a href="<?= BASE_URL ?>/customer/login.php" style="color:var(--coffee-brown);font-weight:600">dashboard customer</a>.
  </p>
  </div>
</section>

<!-- SIRCLO Integration -->
<section class="section">
  <div class="section-inner">
    <p class="section-label" data-t-id="Integrasi Enterprise" data-t-en="Enterprise Integration">Integrasi Enterprise</p>
    <h2 class="section-title" data-t-id="Fondasi Integrasi SIRCLO Sudah Disiapkan" data-t-en="The SIRCLO Integration Foundation Is Ready">Fondasi Integrasi SIRCLO Sudah Disiapkan</h2>
    <p class="section-sub"
       data-t-id="KopiBot sekarang punya plugin Sirclo Full Connector untuk menyiapkan sinkronisasi order, katalog produk, dan customer per cabang. Versi saat ini fokus pada konfigurasi, dashboard monitoring, queue log, dan snapshot data sebagai dasar implementasi API SIRCLO berikutnya."
       data-t-en="KopiBot now includes a Sirclo Full Connector plugin that prepares per-branch order, product catalog, and customer synchronization. The current version focuses on configuration, monitoring dashboards, queue logs, and data snapshots as the foundation for the next SIRCLO API implementation.">KopiBot sekarang punya plugin Sirclo Full Connector untuk menyiapkan sinkronisasi order, katalog produk, dan customer per cabang. Versi saat ini fokus pada konfigurasi, dashboard monitoring, queue log, dan snapshot data sebagai dasar implementasi API SIRCLO berikutnya.</p>

    <div class="feature-grid">
      <div class="feature-card">
        <div class="feature-icon">🔁</div>
        <div class="feature-title" data-t-id="Order Sync Queue" data-t-en="Order Sync Queue">Order Sync Queue</div>
        <div class="feature-desc"
             data-t-id="Event order baru, perubahan status, dan update pembayaran otomatis dicatat ke log sinkronisasi sehingga mapping ke API SIRCLO bisa diuji bertahap."
             data-t-en="New orders, status changes, and payment updates are automatically logged into the sync queue so mapping to the SIRCLO API can be tested step by step.">Event order baru, perubahan status, dan update pembayaran otomatis dicatat ke log sinkronisasi sehingga mapping ke API SIRCLO bisa diuji bertahap.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📦</div>
        <div class="feature-title" data-t-id="Product Snapshot" data-t-en="Product Snapshot">Product Snapshot</div>
        <div class="feature-desc"
             data-t-id="Katalog menu, kategori, harga efektif, dan availability per cabang bisa di-queue sebagai snapshot sebelum koneksi HTTP real diaktifkan."
             data-t-en="Menu catalogs, categories, effective prices, and per-branch availability can be queued as snapshots before the real HTTP connection is enabled.">Katalog menu, kategori, harga efektif, dan availability per cabang bisa di-queue sebagai snapshot sebelum koneksi HTTP real diaktifkan.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">👥</div>
        <div class="feature-title" data-t-id="Customer Snapshot" data-t-en="Customer Snapshot">Customer Snapshot</div>
        <div class="feature-desc"
             data-t-id="Customer aktif per cabang disiapkan dari histori order supaya integrasi CRM atau customer sync ke SIRCLO bisa dibangun di atas data yang sudah rapi."
             data-t-en="Active customers per branch are prepared from order history so CRM or customer sync into SIRCLO can be built on top of already-clean data.">Customer aktif per cabang disiapkan dari histori order supaya integrasi CRM atau customer sync ke SIRCLO bisa dibangun di atas data yang sudah rapi.</div>
      </div>
    </div>

    <div class="promo-detail" style="margin-top:28px">
      <div class="promo-detail-item">
        <div class="promo-detail-icon">1.</div>
        <div>
          <div class="promo-detail-title" data-t-id="Isi konfigurasi cabang" data-t-en="Fill branch configuration">Isi konfigurasi cabang</div>
          <div class="promo-detail-desc"
               data-t-id="Masukkan API base URL, store ID, API key, API secret, dan status mapping di pengaturan plugin per cabang."
               data-t-en="Enter the API base URL, store ID, API key, API secret, and status mapping in the per-branch plugin settings.">Masukkan API base URL, store ID, API key, API secret, dan status mapping di pengaturan plugin per cabang.</div>
        </div>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">2.</div>
        <div>
          <div class="promo-detail-title" data-t-id="Pantau queue sinkronisasi" data-t-en="Monitor the sync queue">Pantau queue sinkronisasi</div>
          <div class="promo-detail-desc"
               data-t-id="Buka dashboard Sirclo Connector di branch atau super admin untuk melihat status koneksi, credential, dan activity log terbaru."
               data-t-en="Open the Sirclo Connector dashboard in branch or super admin mode to see connection status, credentials, and the latest activity logs.">Buka dashboard Sirclo Connector di branch atau super admin untuk melihat status koneksi, credential, dan activity log terbaru.</div>
        </div>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">3.</div>
        <div>
          <div class="promo-detail-title" data-t-id="Lanjutkan ke API produksi" data-t-en="Continue to the production API">Lanjutkan ke API produksi</div>
          <div class="promo-detail-desc"
               data-t-id="Setelah payload dan mapping final, service plugin bisa dilanjutkan untuk request HTTP real, webhook inbound, retry policy, dan sinkronisasi dua arah."
               data-t-en="Once payloads and mappings are final, the plugin service can be extended with real HTTP requests, inbound webhooks, retry policies, and two-way synchronization.">Setelah payload dan mapping final, service plugin bisa dilanjutkan untuk request HTTP real, webhook inbound, retry policy, dan sinkronisasi dua arah.</div>
        </div>
      </div>
      <div class="promo-detail-item">
        <div class="promo-detail-icon">ℹ️</div>
        <div>
          <div class="promo-detail-title" data-t-id="Status saat ini" data-t-en="Current status">Status saat ini</div>
          <div class="promo-detail-desc"
               data-t-id="Plugin SIRCLO yang ada sekarang masih berupa fondasi integrasi. Ia sudah siap untuk konfigurasi, snapshot, dan monitoring, tetapi belum mengirim request HTTP real ke API SIRCLO."
               data-t-en="The current SIRCLO plugin is still an integration foundation. It is ready for configuration, snapshots, and monitoring, but it does not yet send real HTTP requests to the SIRCLO API.">Plugin SIRCLO yang ada sekarang masih berupa fondasi integrasi. Ia sudah siap untuk konfigurasi, snapshot, dan monitoring, tetapi belum mengirim request HTTP real ke API SIRCLO.</div>
        </div>
      </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:22px">
      <a href="<?= BASE_URL ?>/docs/sirclo-full-connector.php" class="btn btn-primary btn-sm"
         data-t-id="Baca Tutorial SIRCLO" data-t-en="Read the SIRCLO Tutorial">Baca Tutorial SIRCLO</a>
      <a href="<?= BASE_URL ?>/readme.php" class="btn btn-outline btn-sm"
         data-t-id="Lihat README Lengkap" data-t-en="View the Full README">Lihat README Lengkap</a>
    </div>
  </div>
</section>

<!-- Tech specs -->
<section class="section bg-cream">
  <div class="section-inner">
    <p class="section-label" data-t-id="Spesifikasi Teknis" data-t-en="Technical Specs">Spesifikasi Teknis</p>
    <h2 class="section-title" data-t-id="Dibangun di Atas Teknologi Terbuka" data-t-en="Built on Open Technology">Dibangun di Atas Teknologi Terbuka</h2>
    <p class="section-sub"
       data-t-id="PHP 8 murni, tanpa framework besar — ringan, cepat, dan mudah dimodifikasi. Codebase ini memakai model dual license: AGPL untuk ekosistem terbuka dan commercial license untuk kebutuhan proprietary."
       data-t-en="Pure PHP 8, no heavy framework — lightweight, fast, and easy to modify. This codebase uses a dual-license model: AGPL for open ecosystems and a commercial license for proprietary use.">PHP 8 murni, tanpa framework besar — ringan, cepat, dan mudah dimodifikasi. Codebase ini memakai model dual license: AGPL untuk ekosistem terbuka dan commercial license untuk kebutuhan proprietary.</p>
    <div class="specs-grid">
      <div>
        <div class="spec-row"><span class="spec-label">Backend</span><span class="spec-value">PHP 8 (native, no framework)</span></div>
        <div class="spec-row"><span class="spec-label">Database</span><span class="spec-value">MySQL (PDO)</span></div>
        <div class="spec-row"><span class="spec-label">Server</span><span class="spec-value">Apache / Nginx (XAMPP ready)</span></div>
        <div class="spec-row"><span class="spec-label">AI / LLM</span><span class="spec-value">OpenAI, Anthropic, Rule-based</span></div>
        <div class="spec-row"><span class="spec-label">Lisensi</span><span class="spec-value">AGPL-3.0 + Commercial License</span></div>
        <div class="spec-row"><span class="spec-label">WhatsApp</span><span class="spec-value">Fonnte, Wablas, Meta Cloud API, Twilio, Baileys</span></div>
        <div class="spec-row"><span class="spec-label" data-t-id="Channel Bot" data-t-en="Bot Channels">Channel Bot</span><span class="spec-value">Web, WhatsApp, Telegram, Discord</span></div>
        <div class="spec-row"><span class="spec-label" data-t-id="Kapasitas Menu" data-t-en="Menu Capacity">Kapasitas Menu</span><span class="spec-value" data-t-id="Hingga 1.000 item" data-t-en="Up to 1,000 items">Hingga 1.000 item</span></div>
        <div class="spec-row"><span class="spec-label" data-t-id="Varian Menu" data-t-en="Menu Variants">Varian Menu</span><span class="spec-value" data-t-id="Size / varian harga per produk" data-t-en="Per-product size / price variants">Size / varian harga per produk</span></div>
        <div style="margin-top:14px">
          <a href="<?= BASE_URL ?>/technical-whatsapp.php" class="btn btn-outline" style="font-size:.82rem;padding:9px 14px"
             data-t-id="Dok. Webhook" data-t-en="Webhook Docs">Dok. Webhook</a>
          <a href="<?= BASE_URL ?>/technical-multichannel.php" class="btn btn-outline" style="font-size:.82rem;padding:9px 14px;margin-left:8px"
             data-t-id="Dok. Multi-Channel" data-t-en="Multi-Channel Docs">Dok. Multi-Channel</a>
        </div>
      </div>
      <div>
        <div class="spec-row"><span class="spec-label" data-t-id="Multi Bahasa" data-t-en="Multi Language">Multi Bahasa</span><span class="spec-value" data-t-id="Indonesia &amp; English (per cabang)" data-t-en="Indonesian &amp; English (per branch)">Indonesia &amp; English (per cabang)</span></div>
        <div class="spec-row"><span class="spec-label" data-t-id="Multi Mata Uang" data-t-en="Multi Currency">Multi Mata Uang</span><span class="spec-value" data-t-id="IDR, USD, SGD, AUD, MYR (per cabang)" data-t-en="IDR, USD, SGD, AUD, MYR (per branch)">IDR, USD, SGD, AUD, MYR (per cabang)</span></div>
        <div class="spec-row"><span class="spec-label" data-t-id="Zona Waktu" data-t-en="Timezone">Zona Waktu</span><span class="spec-value" data-t-id="Per cabang (WIB, WITA, WIT, dll.)" data-t-en="Per branch (WIB, WITA, WIT, etc.)">Per cabang (WIB, WITA, WIT, dll.)</span></div>
        <div class="spec-row"><span class="spec-label">PPN / VAT</span><span class="spec-value" data-t-id="Configurable per cabang (0–100%)" data-t-en="Configurable per branch (0–100%)">Configurable per cabang (0–100%)</span></div>
        <div class="spec-row"><span class="spec-label" data-t-id="Keamanan" data-t-en="Security">Keamanan</span><span class="spec-value">CSRF, session auth, role-based ACL</span></div>
        <div class="spec-row"><span class="spec-label" data-t-id="Plugin System" data-t-en="Plugin System">Plugin System</span><span class="spec-value" data-t-id="Hook action/filter, provider LLM, skill, dan channel extensible" data-t-en="Extensible action/filter hooks, LLM providers, skills, and channels">Hook action/filter, provider LLM, skill, dan channel extensible</span></div>
      </div>
    </div>
  </div>
</section>

<!-- Open Source -->
<section class="section">
  <div class="section-inner">
    <p class="section-label" data-t-id="Untuk Developer" data-t-en="For Developers">Untuk Developer</p>
    <h2 class="section-title" data-t-id="Disiapkan untuk Open Source dan Kontribusi" data-t-en="Prepared for Open Source Contribution">Disiapkan untuk Open Source dan Kontribusi</h2>
    <p class="section-sub"
       data-t-id="KopiBot AI disiapkan sebagai codebase yang ramah kontribusi dengan dual license: AGPL untuk turunan open source, dan commercial license untuk implementasi proprietary atau white-label."
       data-t-en="KopiBot AI is structured as a contribution-friendly codebase with dual licensing: AGPL for open source derivatives, and a commercial license for proprietary or white-label implementations.">KopiBot AI disiapkan sebagai codebase yang ramah kontribusi dengan dual license: AGPL untuk turunan open source, dan commercial license untuk implementasi proprietary atau white-label.</p>
    <div class="oss-panel">
      <div>
        <div class="oss-note" data-t-id="Open + Commercial" data-t-en="Open + Commercial">Open + Commercial</div>
        <h3 data-t-id="AGPL untuk komunitas, commercial license untuk kebutuhan tertutup." data-t-en="AGPL for the community, commercial licensing for closed deployments.">AGPL untuk komunitas, commercial license untuk kebutuhan tertutup.</h3>
        <p data-t-id="Developer bisa audit, pakai, modifikasi, dan berkontribusi lewat jalur AGPL. Di saat yang sama, bisnis yang ingin white-label, SaaS proprietary, atau modifikasi core tertutup bisa memakai commercial license."
           data-t-en="Developers can audit, use, modify, and contribute through the AGPL path. At the same time, businesses that need white-label, proprietary SaaS, or closed core modifications can use a commercial license.">Developer bisa audit, pakai, modifikasi, dan berkontribusi lewat jalur AGPL. Di saat yang sama, bisnis yang ingin white-label, SaaS proprietary, atau modifikasi core tertutup bisa memakai commercial license.</p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:18px">
          <a href="<?= BASE_URL ?>/docs/index.php" class="btn btn-primary btn-sm"
             data-t-id="Buka Semua Docs" data-t-en="Open All Docs">Buka Semua Docs</a>
          <a href="https://github.com/kukuhtw/toko_kopi" class="btn btn-outline btn-sm" target="_blank" rel="noopener"
             data-t-id="Buka GitHub Repo" data-t-en="Open GitHub Repo">Buka GitHub Repo</a>
          <a href="<?= BASE_URL ?>/docs/lisensi.php" class="btn btn-outline btn-sm"
             data-t-id="Baca Detail Lisensi" data-t-en="Read License Details">Baca Detail Lisensi</a>
        </div>
      </div>
      <ul class="oss-list">
        <li>
          <strong data-t-id="AGPL menjaga turunan tetap terbuka" data-t-en="AGPL keeps derivatives open">AGPL menjaga turunan tetap terbuka</strong>
          <span data-t-id="Jika core dimodifikasi dan didistribusikan atau dijalankan sebagai layanan jaringan, kewajiban berbagi source code turunan tetap berlaku sesuai AGPL."
                data-t-en="If the core is modified and distributed or run as a network service, the obligation to share derivative source code still applies under the AGPL.">Jika core dimodifikasi dan didistribusikan atau dijalankan sebagai layanan jaringan, kewajiban berbagi source code turunan tetap berlaku sesuai AGPL.</span>
        </li>
        <li>
          <strong data-t-id="Commercial license untuk deployment proprietary" data-t-en="Commercial licensing for proprietary deployment">Commercial license untuk deployment proprietary</strong>
          <span data-t-id="Kalau bisnis tidak ingin membuka modifikasi core, ingin white-label, atau menjualnya sebagai solusi tertutup, jalur komersial adalah opsi yang tepat."
                data-t-en="If a business does not want to open its core modifications, needs white-labeling, or wants to sell it as a closed solution, the commercial path is the right option.">Kalau bisnis tidak ingin membuka modifikasi core, ingin white-label, atau menjualnya sebagai solusi tertutup, jalur komersial adalah opsi yang tepat.</span>
        </li>
        <li>
          <strong data-t-id="Plugin dan integrasi tetap perlu dilihat konteksnya" data-t-en="Plugins and integrations still depend on context">Plugin dan integrasi tetap perlu dilihat konteksnya</strong>
          <span data-t-id="Modul yang berdiri sendiri lewat extension point publik bisa lebih fleksibel, tetapi bundling proprietary bersama core sebaiknya ditinjau dengan model lisensi komersial."
                data-t-en="Modules that stand on their own through public extension points can be more flexible, but proprietary bundling with the core should be reviewed under a commercial licensing model.">Modul yang berdiri sendiri lewat extension point publik bisa lebih fleksibel, tetapi bundling proprietary bersama core sebaiknya ditinjau dengan model lisensi komersial.</span>
        </li>
      </ul>
    </div>
  </div>
</section>

<!-- License Model -->
<section class="section bg-cream">
  <div class="section-inner">
    <p class="section-label" data-t-id="Model Lisensi" data-t-en="License Model">Model Lisensi</p>
    <h2 class="section-title" data-t-id="Pilih Jalur Lisensi yang Sesuai Dengan Cara Pakai Kamu" data-t-en="Choose the Licensing Path That Matches Your Usage">Pilih Jalur Lisensi yang Sesuai Dengan Cara Pakai Kamu</h2>
    <p class="section-sub"
       data-t-id="Agar tidak abu-abu, KopiBot AI memisahkan jalur open source dan jalur komersial. Ringkasnya: pakai AGPL jika siap berbagi turunan, pilih commercial license jika ingin deployment proprietary."
       data-t-en="To avoid ambiguity, KopiBot AI separates the open source path from the commercial path. In short: use AGPL if you are ready to share derivatives, choose a commercial license if you want a proprietary deployment.">Agar tidak abu-abu, KopiBot AI memisahkan jalur open source dan jalur komersial. Ringkasnya: pakai AGPL jika siap berbagi turunan, pilih commercial license jika ingin deployment proprietary.</p>
    <div class="license-grid">
      <div class="license-card">
        <h3 data-t-id="AGPL-3.0" data-t-en="AGPL-3.0">AGPL-3.0</h3>
        <p data-t-id="Cocok untuk fork komunitas, kontribusi open source, audit kode, dan deployment yang siap memenuhi kewajiban copyleft."
           data-t-en="Best for community forks, open source contributions, code audits, and deployments that are comfortable meeting copyleft obligations.">Cocok untuk fork komunitas, kontribusi open source, audit kode, dan deployment yang siap memenuhi kewajiban copyleft.</p>
        <ul>
          <li data-t-id="Turunan core tetap terbuka" data-t-en="Core derivatives remain open">Turunan core tetap terbuka</li>
          <li data-t-id="Berlaku juga untuk layanan lewat jaringan" data-t-en="Also applies to network-delivered services">Berlaku juga untuk layanan lewat jaringan</li>
          <li data-t-id="Ideal untuk ekosistem komunitas" data-t-en="Ideal for community ecosystems">Ideal untuk ekosistem komunitas</li>
        </ul>
      </div>
      <div class="license-card license-card-highlight">
        <h3 data-t-id="Commercial License" data-t-en="Commercial License">Commercial License</h3>
        <p data-t-id="Cocok untuk white-label, SaaS proprietary, bundling ke proyek klien, dan modifikasi core yang ingin tetap tertutup."
           data-t-en="Best for white-labeling, proprietary SaaS, bundling into client projects, and closed core modifications.">Cocok untuk white-label, SaaS proprietary, bundling ke proyek klien, dan modifikasi core yang ingin tetap tertutup.</p>
        <ul>
          <li data-t-id="Tanpa kewajiban membuka source code turunan" data-t-en="No obligation to open derivative source code">Tanpa kewajiban membuka source code turunan</li>
          <li data-t-id="Lebih aman untuk deployment bisnis" data-t-en="Safer for business deployments">Lebih aman untuk deployment bisnis</li>
          <li data-t-id="Cocok untuk implementasi tertutup" data-t-en="Fits closed implementations">Cocok untuk implementasi tertutup</li>
        </ul>
      </div>
      <div class="license-card">
        <h3 data-t-id="Perlu Kepastian?" data-t-en="Need Certainty?">Perlu Kepastian?</h3>
        <p data-t-id="Kalau model penggunaanmu campuran atau plugin/proyekmu dibundel bersama core, baca dokumentasi lisensi atau hubungi langsung untuk jalur komersial yang tepat."
           data-t-en="If your usage model is mixed or your plugin/project is bundled with the core, read the licensing docs or contact us directly for the right commercial path.">Kalau model penggunaanmu campuran atau plugin/proyekmu dibundel bersama core, baca dokumentasi lisensi atau hubungi langsung untuk jalur komersial yang tepat.</p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px">
          <a href="<?= BASE_URL ?>/docs/lisensi.php" class="btn btn-outline btn-sm"
             data-t-id="Dokumentasi Lisensi" data-t-en="License Documentation">Dokumentasi Lisensi</a>
          <a href="https://wa.me/628129893706?text=Halo%2C%20saya%20ingin%20tanya%20commercial%20license%20KopiBot%20AI" class="btn btn-primary btn-sm" target="_blank"
             data-t-id="Tanya Lisensi Komersial" data-t-en="Ask About Commercial Licensing">Tanya Lisensi Komersial</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Pricing -->
<section class="section" id="harga">
  <div class="section-inner">
    <p class="section-label" data-t-id="Harga &amp; Paket" data-t-en="Pricing">Harga &amp; Paket</p>
    <h2 class="section-title" data-t-id="Beli Sekali, Pakai Selamanya" data-t-en="Pay Once, Own It Forever">Beli Sekali, Pakai Selamanya</h2>
    <p class="section-sub"
       data-t-id="Tidak ada biaya langganan platform. Kamu hanya bayar hosting sendiri dan biaya API LLM sesuai pemakaian — transparan dan terkontrol."
       data-t-en="No platform subscription fees. You only pay for your own hosting and LLM API usage — transparent and controlled.">Tidak ada biaya langganan platform. Kamu hanya bayar hosting sendiri dan biaya API LLM sesuai pemakaian — transparan dan terkontrol.</p>
    <div class="pricing-grid">
      <div class="pricing-card">
        <div class="pricing-name">Starter</div>
        <div class="pricing-price"><span data-t-id="Hubungi" data-t-en="Contact">Hubungi</span><br><small data-t-id="kami untuk harga" data-t-en="us for pricing">kami untuk harga</small></div>
        <div class="pricing-desc" data-t-id="Cocok untuk 1 cabang yang ingin langsung aktif menerima pesanan." data-t-en="Ideal for 1 branch ready to start accepting orders right away.">Cocok untuk 1 cabang yang ingin langsung aktif menerima pesanan.</div>
        <ul class="pricing-list">
          <li data-t-id="Chatbot Website + WhatsApp" data-t-en="Website + WhatsApp Chatbot">Chatbot Website + WhatsApp</li>
          <li data-t-id="1 cabang &amp; 1 akun admin" data-t-en="1 branch &amp; 1 admin account">1 cabang &amp; 1 akun admin</li>
          <li data-t-id="Menu, promo, dan manajemen order" data-t-en="Menu, promos, and order management">Menu, promo, dan manajemen order</li>
          <li data-t-id="PPN, multi bahasa, zona waktu" data-t-en="VAT, multi language, timezone">PPN, multi bahasa, zona waktu</li>
          <li data-t-id="Rule-based AI (tanpa biaya API)" data-t-en="Rule-based AI (no API cost)">Rule-based AI (tanpa biaya API)</li>
          <li data-t-id="Setup &amp; konfigurasi awal" data-t-en="Initial setup &amp; configuration">Setup &amp; konfigurasi awal</li>
        </ul>
        <a href="#kontak" class="btn btn-outline" style="width:100%;justify-content:center" data-t-id="Tanya Harga" data-t-en="Ask for Pricing">Tanya Harga</a>
      </div>
      <div class="pricing-card featured">
        <div class="pricing-badge" data-t-id="Paling Populer" data-t-en="Most Popular">Paling Populer</div>
        <div class="pricing-name" data-t-id="Multi Cabang" data-t-en="Multi Branch">Multi Cabang</div>
        <div class="pricing-price"><span data-t-id="Hubungi" data-t-en="Contact">Hubungi</span><br><small data-t-id="kami untuk harga" data-t-en="us for pricing">kami untuk harga</small></div>
        <div class="pricing-desc" data-t-id="Untuk bisnis dengan beberapa cabang atau yang berencana berkembang." data-t-en="For businesses with multiple branches or planning to expand.">Untuk bisnis dengan beberapa cabang atau yang berencana berkembang.</div>
        <ul class="pricing-list">
          <li data-t-id="Semua fitur Starter" data-t-en="All Starter features">Semua fitur Starter</li>
          <li data-t-id="Cabang tidak terbatas" data-t-en="Unlimited branches">Cabang tidak terbatas</li>
          <li data-t-id="Dashboard super admin" data-t-en="Super admin dashboard">Dashboard super admin</li>
          <li data-t-id="Promo global + override per cabang" data-t-en="Global promos + per-branch override">Promo global + override per cabang</li>
          <li data-t-id="Integrasi LLM (OpenAI / Anthropic)" data-t-en="LLM integration (OpenAI / Anthropic)">Integrasi LLM (OpenAI / Anthropic)</li>
          <li data-t-id="Monitoring biaya API per cabang" data-t-en="API cost monitoring per branch">Monitoring biaya API per cabang</li>
          <li data-t-id="Dukungan teknis 30 hari" data-t-en="30-day technical support">Dukungan teknis 30 hari</li>
        </ul>
        <a href="#kontak" class="btn btn-primary" style="width:100%;justify-content:center" data-t-id="Tanya Harga" data-t-en="Ask for Pricing">Tanya Harga</a>
      </div>
      <div class="pricing-card">
        <div class="pricing-name">Custom</div>
        <div class="pricing-price"><span data-t-id="Diskusi" data-t-en="Discuss">Diskusi</span><br><small data-t-id="sesuai kebutuhan" data-t-en="based on your needs">sesuai kebutuhan</small></div>
        <div class="pricing-desc" data-t-id="Pengembangan fitur atau integrasi khusus sesuai kebutuhan spesifik bisnis kamu." data-t-en="Custom feature development or integrations for your specific business needs.">Pengembangan fitur atau integrasi khusus sesuai kebutuhan spesifik bisnis kamu.</div>
        <ul class="pricing-list">
          <li data-t-id="Semua fitur Multi Cabang" data-t-en="All Multi Branch features">Semua fitur Multi Cabang</li>
          <li data-t-id="Integrasi sistem POS / ERP" data-t-en="POS / ERP system integration">Integrasi sistem POS / ERP</li>
          <li data-t-id="Custom flow &amp; branding" data-t-en="Custom flow &amp; branding">Custom flow &amp; branding</li>
          <li data-t-id="Integrasi payment gateway" data-t-en="Payment gateway integration">Integrasi payment gateway</li>
          <li data-t-id="Laporan &amp; analitik lanjutan" data-t-en="Advanced reports &amp; analytics">Laporan &amp; analitik lanjutan</li>
          <li data-t-id="Dukungan teknis ongoing" data-t-en="Ongoing technical support">Dukungan teknis ongoing</li>
        </ul>
        <a href="#kontak" class="btn btn-outline" style="width:100%;justify-content:center"
           data-t-id="Diskusi Kebutuhan" data-t-en="Discuss Requirements">Diskusi Kebutuhan</a>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section" id="kontak">
  <div style="max-width:600px;margin:0 auto">
    <h2 data-t-id="Siap Otomatiskan Pesanan? ☕" data-t-en="Ready to Automate Orders? ☕">Siap Otomatiskan Pesanan? ☕</h2>
    <p data-t-id="Ceritakan kebutuhan toko kamu. Kami bantu pilih paket yang tepat dan mulai setup dalam hitungan hari."
       data-t-en="Tell us about your shop. We'll help you pick the right package and get it set up within days.">Ceritakan kebutuhan toko kamu. Kami bantu pilih paket yang tepat dan mulai setup dalam hitungan hari.</p>
    <div class="cta-contacts">
      <a href="https://wa.me/628129893706?text=Halo%2C%20saya%20tertarik%20dengan%20KopiBot%20AI"
         target="_blank" class="btn-gold">
        💬 <span data-t-id="WhatsApp Sekarang" data-t-en="WhatsApp Now">WhatsApp Sekarang</span>
      </a>
      <a href="mailto:kukuhtw@gmail.com?subject=Inquiry%20KopiBot%20AI" class="btn-ghost">
        📧 <span data-t-id="Kirim Email" data-t-en="Send Email">Kirim Email</span>
      </a>
    </div>
    <p style="margin-top:28px;font-size:.82rem;color:rgba(255,255,255,.6)"
       data-t-id="Respon cepat · Konsultasi gratis · Garansi kepuasan"
       data-t-en="Fast response · Free consultation · Satisfaction guarantee">Respon cepat · Konsultasi gratis · Garansi kepuasan</p>
  </div>
</section>

<!-- Footer -->
<footer class="footer">
  <p>© <?= date('Y') ?> KopiBot AI —
    <span data-t-id="Chatbot Pemesanan untuk Toko Kopi Indonesia" data-t-en="AI Order Chatbot for Coffee Shops">Chatbot Pemesanan untuk Toko Kopi Indonesia</span>
    &nbsp;·&nbsp;
    <a href="<?= BASE_URL ?>/login.php" style="color:rgba(255,255,255,.5)" data-t-id="Admin Login" data-t-en="Admin Login">Admin Login</a>
    &nbsp;·&nbsp;
    <a href="<?= BASE_URL ?>/customer/login.php" style="color:rgba(255,255,255,.5)">Customer Dashboard</a>
  </p>
</footer>

<script>
(function () {
    var HERO = {
        id: 'Chatbot Pemesanan Otomatis<br>untuk <span>Toko Kopi</span> Kamu',
        en: 'Automated Order Chatbot<br>for Your <span>Coffee Shop</span>'
    };

    var current = localStorage.getItem('kopibot_lang') || 'id';

    function applyLang(lang) {
        document.querySelectorAll('[data-t-id]').forEach(function (el) {
            el.innerHTML = lang === 'en'
                ? (el.dataset.tEn || el.dataset.tId)
                : el.dataset.tId;
        });

        var h1 = document.getElementById('hero-h1');
        if (h1) h1.innerHTML = HERO[lang];

        var btn = document.getElementById('lang-btn');
        if (btn) btn.textContent = lang === 'id' ? 'EN' : 'ID';

        document.getElementById('root-html').setAttribute('lang', lang === 'en' ? 'en' : 'id');

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
