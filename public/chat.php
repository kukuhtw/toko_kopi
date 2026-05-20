<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat Demo — Toko Kopi</title>
  <?php
  require_once dirname(__DIR__) . '/app/Config/config.php';
  use App\Models\BranchModel;
  use App\Helpers\Auth;
  Auth::startSession();
  $branchModel = new BranchModel();
  $branches    = $branchModel->getActive();

  // Start session for chat identification
  $sessionId   = session_id();
  $selectedBranchId = (int)($_GET['branch'] ?? ($_SESSION['chat_branch_id'] ?? 0));
  if ($selectedBranchId) {
      $_SESSION['chat_branch_id'] = $selectedBranchId;
      $selectedBranch = $branchModel->find($selectedBranchId);
  }
  ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <style>
    body { margin:0; background:var(--coffee-cream); }
    .demo-wrapper { display:flex; height:100vh; overflow:hidden; }
    .mobile-sidebar-backdrop {
      position:fixed; inset:0; z-index:39;
      background:rgba(24,14,8,.45);
      opacity:0; pointer-events:none; transition:opacity .25s ease;
    }
    body.sidebar-open .mobile-sidebar-backdrop {
      opacity:1; pointer-events:auto;
    }
    .branch-sidebar {
      width:280px; background:var(--coffee-dark); color:#fff;
      display:flex; flex-direction:column; flex-shrink:0; position:relative; z-index:40;
    }
    .branch-sidebar h2 { padding:20px; font-size:1.1rem; border-bottom:1px solid rgba(255,255,255,.1); margin:0; }
    .sidebar-header {
      display:flex; align-items:center; justify-content:space-between;
    }
    .sidebar-header h2 {
      flex:1;
    }
    .sidebar-close {
      display:none; margin-right:14px;
      width:36px; height:36px; border-radius:10px;
      border:1px solid rgba(255,255,255,.18);
      background:rgba(255,255,255,.08); color:#fff; cursor:pointer;
    }
    .sidebar-home {
      display:flex; align-items:center; gap:8px;
      margin:16px 16px 8px; padding:10px 12px;
      border:1px solid rgba(255,255,255,.18); border-radius:10px;
      color:#fff; text-decoration:none; font-size:.88rem; font-weight:600;
      background:rgba(255,255,255,.06); transition:background .2s, border-color .2s;
    }
    .sidebar-home:hover { background:rgba(255,255,255,.12); border-color:rgba(255,255,255,.3); }
    .branch-list { flex:1; overflow-y:auto; padding:8px 0; }
    .branch-item {
      padding:14px 20px; cursor:pointer; border-left:3px solid transparent;
      transition:all .2s; display:flex; flex-direction:column; gap:4px;
    }
    .branch-item:hover { background:rgba(255,255,255,.08); }
    .branch-item.active { background:rgba(255,255,255,.12); border-left-color:var(--coffee-light); }
    .branch-item strong { font-size:.9rem; }
    .branch-item span { font-size:.75rem; color:rgba(255,255,255,.6); }
    .chat-area { flex:1; display:flex; flex-direction:column; position:relative; min-width:0; }
    .no-branch-selected {
      flex:1; display:flex; align-items:center; justify-content:center;
      flex-direction:column; gap:12px; color:var(--text-light);
      background:var(--coffee-cream); text-align:center; padding:24px;
    }
    .no-branch-selected h3 { color:var(--coffee-brown); }
    .no-branch-actions { display:none; }

    /* Identity form overlay */
    .identity-overlay {
      position:absolute; inset:0; z-index:50;
      background:rgba(44,26,14,.55);
      backdrop-filter:blur(3px);
      display:flex; align-items:center; justify-content:center;
    }
    .identity-card {
      background:#fff; border-radius:20px; padding:36px 32px;
      width:100%; max-width:400px;
      box-shadow:0 16px 48px rgba(0,0,0,.25);
      animation:slideUp .3s ease;
    }
    @keyframes slideUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
    .identity-card .brand { text-align:center; margin-bottom:24px; }
    .identity-card .brand .icon { font-size:2.8rem; }
    .identity-card .brand h2 { font-size:1.3rem; color:var(--coffee-dark); margin:8px 0 4px; }
    .identity-card .brand p  { font-size:.85rem; color:var(--text-mid); }
    .identity-card .form-group { margin-bottom:14px; }
    .identity-card .form-label { font-size:.85rem; font-weight:500; color:var(--text-dark); display:block; margin-bottom:5px; }
    .identity-card .form-control { width:100%; padding:10px 14px; border:1.5px solid var(--border); border-radius:var(--radius); font-size:.9rem; box-sizing:border-box; }
    .identity-card .form-control:focus { outline:none; border-color:var(--coffee-brown); box-shadow:0 0 0 3px rgba(111,78,55,.12); }
    .identity-card .form-error { color:var(--accent-red); font-size:.78rem; margin-top:3px; display:none; }
    .start-btn {
      width:100%; padding:12px; border:none; border-radius:var(--radius);
      background:var(--coffee-brown); color:#fff; font-size:1rem; font-weight:600;
      cursor:pointer; margin-top:6px; transition:background .2s;
    }
    .start-btn:hover { background:var(--coffee-dark); }
    .start-btn:disabled { background:var(--text-light); cursor:not-allowed; }
    .identity-note { text-align:center; font-size:.75rem; color:var(--text-light); margin-top:14px; }
    .user-badge {
      display:flex; align-items:center; gap:8px;
      padding:8px 16px; background:rgba(255,255,255,.15);
      border-radius:20px; font-size:.8rem;
    }
    .user-badge .avatar-sm {
      width:26px; height:26px; border-radius:50%;
      background:var(--coffee-light); color:var(--coffee-dark);
      display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:.75rem;
    }
    .chat-home-link {
      margin-left:12px; color:#fff; text-decoration:none; font-size:.8rem;
      padding:8px 12px; border-radius:999px; border:1px solid rgba(255,255,255,.18);
      background:rgba(255,255,255,.08); white-space:nowrap; transition:background .2s, border-color .2s;
    }
    .chat-home-link:hover { background:rgba(255,255,255,.14); border-color:rgba(255,255,255,.3); }
    .mobile-branch-toggle,
    .chat-mobile-toggle {
      display:none; align-items:center; justify-content:center;
      border:none; cursor:pointer;
      background:var(--coffee-brown); color:#fff;
      box-shadow:0 10px 24px rgba(111,78,55,.18);
    }
    .mobile-branch-toggle {
      gap:8px; padding:10px 14px; border-radius:999px; font-size:.86rem; font-weight:600;
    }
    .chat-mobile-toggle {
      width:38px; height:38px; border-radius:12px; font-size:1rem;
      background:rgba(255,255,255,.12); box-shadow:none;
      margin-left:auto;
    }
    .chat-mobile-actions { display:none; margin-left:auto; }
    @media (max-width: 900px) {
      .branch-sidebar {
        position:fixed; top:0; left:0; bottom:0;
        width:min(86vw, 320px); max-width:320px;
        transform:translateX(-100%);
        transition:transform .25s ease;
        box-shadow:0 18px 40px rgba(0,0,0,.28);
      }
      body.sidebar-open .branch-sidebar {
        transform:translateX(0);
      }
      .sidebar-close,
      .chat-mobile-actions,
      .no-branch-actions {
        display:flex;
      }
      .chat-container {
        height:100dvh; max-height:100dvh;
      }
      .chat-header {
        flex-wrap:wrap; align-items:flex-start;
      }
      .chat-header-info {
        min-width:0; flex:1;
      }
      .chat-header-info h3 {
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
      }
      .chat-home-link {
        display:none;
      }
      .user-badge {
        width:100%; margin-left:0 !important; margin-top:8px;
      }
      .message-bubble {
        max-width:86%;
      }
      .identity-overlay {
        padding:16px;
      }
      .identity-card {
        max-width:none; padding:24px 20px;
      }
    }
    @media (max-width: 560px) {
      .demo-wrapper,
      .chat-container {
        height:100dvh; max-height:100dvh;
      }
      .branch-sidebar h2 {
        padding:18px 16px; font-size:1rem;
      }
      .sidebar-home {
        margin:14px 14px 8px;
      }
      .branch-item {
        padding:13px 16px;
      }
      .chat-header {
        padding:12px;
      }
      .chat-header-avatar {
        width:36px; height:36px; font-size:1rem;
      }
      .chat-header-info h3 {
        font-size:.95rem;
      }
      .chat-header-info span {
        font-size:.72rem;
      }
      .chat-messages {
        padding:10px;
      }
      .message-bubble {
        max-width:92%; padding:10px 12px; font-size:.88rem;
      }
      .chat-input-area {
        padding:10px; gap:10px; align-items:center;
      }
      .chat-input {
        font-size:16px; min-height:44px; max-height:110px; padding:10px 14px;
      }
      .chat-send-btn {
        width:44px; height:44px;
      }
      .mobile-branch-toggle,
      .chat-mobile-toggle {
        display:inline-flex;
      }
    }
  </style>
</head>
<body>
<div class="mobile-sidebar-backdrop" onclick="closeSidebar()"></div>
<div class="demo-wrapper">
  <!-- Branch Selector -->
  <div class="branch-sidebar">
    <div class="sidebar-header">
      <h2>☕ Pilih Cabang</h2>
      <button type="button" class="sidebar-close" onclick="closeSidebar()" aria-label="Tutup daftar cabang">✕</button>
    </div>
    <a href="<?= BASE_URL ?>/index.php" class="sidebar-home">← Kembali ke Home</a>
    <div class="branch-list">
      <?php foreach ($branches as $b): ?>
      <div class="branch-item <?= $selectedBranchId === (int)$b['id'] ? 'active' : '' ?>"
           onclick="selectBranch(<?= $b['id'] ?>,'<?= htmlspecialchars($b['name'], ENT_QUOTES) ?>')">
        <strong><?= htmlspecialchars($b['name']) ?></strong>
        <span>📍 <?= htmlspecialchars($b['city'] ?? 'Indonesia') ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="padding:16px;border-top:1px solid rgba(255,255,255,.1)">
      <button onclick="clearIdentity()" style="font-size:.72rem;color:rgba(255,255,255,.45);background:none;border:1px solid rgba(255,255,255,.2);border-radius:6px;padding:5px 10px;cursor:pointer;width:100%">
        🔄 Ganti Akun / Clear Session
      </button>
    </div>
  </div>

  <!-- Chat Area -->
  <div class="chat-area" id="chatArea">
    <?php if ($selectedBranchId && isset($selectedBranch) && $selectedBranch): ?>

    <!-- Identity Form Overlay (ditampilkan via JS jika belum ada data) -->
    <div class="identity-overlay" id="identityOverlay">
      <div class="identity-card">
        <div class="brand">
          <div class="icon">☕</div>
          <h2>Sebelum Mulai Chat</h2>
          <p>Perkenalkan dirimu dulu, ya!</p>
        </div>
        <div class="form-group">
          <label class="form-label" for="idName">Nama Lengkap <span style="color:var(--accent-red)">*</span></label>
          <input type="text" id="idName" class="form-control" placeholder="Contoh: Budi Santoso" autocomplete="name">
          <div class="form-error" id="errName">Nama wajib diisi (min. 2 karakter).</div>
        </div>
        <div class="form-group">
          <label class="form-label" for="idWa">Nomor WhatsApp <span style="color:var(--accent-red)">*</span></label>
          <input type="tel" id="idWa" class="form-control" placeholder="08123456789" autocomplete="tel">
          <div class="form-error" id="errWa">Nomor WhatsApp tidak valid.</div>
        </div>
        <div class="form-group">
          <label class="form-label" for="idEmail">Email <span style="color:var(--text-light);font-weight:400">(opsional)</span></label>
          <input type="email" id="idEmail" class="form-control" placeholder="budi@email.com" autocomplete="email">
          <div class="form-error" id="errEmail">Format email tidak valid.</div>
        </div>
        <button class="start-btn" id="startChatBtn" onclick="startChat()">
          Mulai Chat ☕
        </button>
        <p class="identity-note">Data tersimpan di browser dan digunakan hanya untuk keperluan pesanan.</p>
      </div>
    </div>

    <!-- Chat UI -->
    <div class="chat-container">
      <div class="chat-header">
        <div class="chat-header-avatar">☕</div>
        <div class="chat-header-info">
          <h3><?= htmlspecialchars($selectedBranch['name']) ?></h3>
          <span>Kopi Bot · Online</span>
        </div>
        <div class="chat-mobile-actions">
          <button type="button" class="chat-mobile-toggle" onclick="openSidebar()" aria-label="Pilih cabang">☰</button>
        </div>
        <a href="<?= BASE_URL ?>/customer/login.php" class="chat-home-link">Customer Portal</a>
        <a href="<?= BASE_URL ?>/index.php" class="chat-home-link">Home</a>
        <!-- User badge (muncul setelah login) -->
        <div class="user-badge" id="userBadge" style="display:none;margin-left:auto">
          <div class="avatar-sm" id="userInitial">?</div>
          <span id="userDisplayName"></span>
        </div>
      </div>
      <div class="chat-messages" id="chatMessages">
        <!-- Pesan selamat datang diisi oleh JS setelah form disubmit -->
      </div>
      <!-- Typing indicator -->
      <div class="message-wrap bot" id="typingIndicator" style="display:none">
        <div class="message-bubble">
          <div class="typing-indicator">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
          </div>
        </div>
      </div>
      <div class="chat-input-area">
        <textarea id="chatInput" class="chat-input" placeholder="Ketik pesan..." rows="1"
                  onkeydown="handleKey(event)" disabled></textarea>
        <button class="chat-send-btn" onclick="sendMessage()" id="sendBtn" disabled
                style="opacity:.5">➤</button>
      </div>
    </div>

    <?php else: ?>
    <div class="no-branch-selected">
      <div style="font-size:4rem">☕</div>
      <h3>Toko Kopi Chatbot</h3>
      <p>Pilih cabang di sebelah kiri untuk mulai chat</p>
      <div class="no-branch-actions">
        <button type="button" class="mobile-branch-toggle" onclick="openSidebar()">☰ Lihat Cabang</button>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const BRANCH_ID   = <?= $selectedBranchId ?>;
const SESSION_ID  = '<?= $sessionId ?>';
const BASE_URL    = '<?= BASE_URL ?>';
const BRANCH_NAME = '<?= addslashes(htmlspecialchars($selectedBranch['name'] ?? '')) ?>';
const DEBUG_MODE  = <?= isset($_GET['debug']) && $_GET['debug'] === '1' ? 'true' : 'false' ?>;

// ── Global storage key (same user across branches) ────────────
const STORAGE_KEY = 'toko_kopi_user';

// ── Identity state ────────────────────────────────────────────
let chatUser = null;  // { name, wa, email }

// ── On load: check if identity already saved in localStorage ──
window.addEventListener('DOMContentLoaded', () => {
  if (!BRANCH_ID) return;

  const saved = localStorage.getItem(STORAGE_KEY);
  if (saved) {
    try {
      chatUser = JSON.parse(saved);
      if (chatUser?.name && chatUser?.wa) {
        showChatReady(false);
      } else {
        localStorage.removeItem(STORAGE_KEY);
        prefillForm(chatUser);
      }
    } catch {
      localStorage.removeItem(STORAGE_KEY);
    }
  }

  // Keyboard navigation
  document.getElementById('idName')?.addEventListener('keydown',  e => { if (e.key === 'Enter') document.getElementById('idWa').focus(); });
  document.getElementById('idWa')?.addEventListener('keydown',   e => { if (e.key === 'Enter') document.getElementById('idEmail').focus(); });
  document.getElementById('idEmail')?.addEventListener('keydown', e => { if (e.key === 'Enter') startChat(); });
});

function prefillForm(user) {
  if (!user) return;
  if (user.name)  document.getElementById('idName').value  = user.name;
  if (user.wa)    document.getElementById('idWa').value    = user.wa;
  if (user.email) document.getElementById('idEmail').value = user.email;
}

// ── Branch selector ───────────────────────────────────────────
function selectBranch(id) {
  closeSidebar();
  window.location.href = BASE_URL + '/chat.php?branch=' + id;
}

function openSidebar() {
  document.body.classList.add('sidebar-open');
}

function closeSidebar() {
  document.body.classList.remove('sidebar-open');
}

// ── Form validation & start ───────────────────────────────────
function startChat() {
  const nameEl  = document.getElementById('idName');
  const waEl    = document.getElementById('idWa');
  const emailEl = document.getElementById('idEmail');
  const btn     = document.getElementById('startChatBtn');

  ['errName','errWa','errEmail'].forEach(id => document.getElementById(id).style.display = 'none');

  const name  = nameEl.value.trim();
  const wa    = waEl.value.trim().replace(/\D/g, '');
  const email = emailEl.value.trim();
  let valid   = true;

  if (name.length < 2) {
    document.getElementById('errName').style.display = 'block';
    nameEl.focus(); valid = false;
  }
  if (wa.length < 8) {
    document.getElementById('errWa').style.display = 'block';
    if (valid) waEl.focus(); valid = false;
  }
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    document.getElementById('errEmail').style.display = 'block';
    if (valid) emailEl.focus(); valid = false;
  }

  if (!valid) return;

  chatUser = { name, wa, email };
  localStorage.setItem(STORAGE_KEY, JSON.stringify(chatUser));

  btn.disabled = true;
  btn.textContent = 'Memulai...';
  showChatReady(true);
}

// ── Clear identity (ganti akun) ───────────────────────────────
function clearIdentity() {
  if (!confirm('Ganti akun? Data nama dan nomor WA akan dihapus.')) return;
  localStorage.removeItem(STORAGE_KEY);
  location.reload();
}

// ── Show chat UI, hide overlay ────────────────────────────────
function showChatReady(sendWelcome) {
  // Hide overlay
  const overlay = document.getElementById('identityOverlay');
  if (overlay) {
    overlay.style.transition = 'opacity .3s';
    overlay.style.opacity = '0';
    setTimeout(() => overlay.remove(), 300);
  }

  // Enable input
  const input  = document.getElementById('chatInput');
  const btn    = document.getElementById('sendBtn');
  if (input) { input.disabled = false; input.focus(); }
  if (btn)   { btn.disabled = false; btn.style.opacity = '1'; }

  // Show user badge
  const badge   = document.getElementById('userBadge');
  const initial = document.getElementById('userInitial');
  const dispName= document.getElementById('userDisplayName');
  if (badge && chatUser) {
    badge.style.display   = 'flex';
    initial.textContent   = chatUser.name.charAt(0).toUpperCase();
    dispName.textContent  = chatUser.name.split(' ')[0]; // first name only
  }

  if (sendWelcome) {
    // Show welcome message from bot
    const greeting = `Halo, <strong>${escapeHtml(chatUser.name)}</strong>! 👋 Selamat datang di <strong>${BRANCH_NAME}</strong>!<br><br>` +
      `Saya Kopi Bot, siap membantu pesananmu. Ketik <strong>menu</strong> untuk melihat pilihan kami, atau langsung sebutkan pesananmu! ☕`;
    appendRawMessage(greeting, 'bot');

    // Register name+email silently via first chat ping
    registerCustomer();
  } else {
    // Returning visitor — restore minimal greeting
    appendRawMessage(`Halo lagi, <strong>${escapeHtml(chatUser.name)}</strong>! ☕ Ada yang bisa saya bantu?`, 'bot');
  }
}

// ── Register customer in the background ──────────────────────
async function registerCustomer() {
  if (!chatUser?.name) return;
  try {
    await fetch(BASE_URL + '/api/chat/send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        branch_id:         BRANCH_ID,
        message:           '__register__',
        session_id:        SESSION_ID,
        customer_name:     chatUser.name,
        customer_email:    chatUser.email || '',
        customer_whatsapp: chatUser.wa    || '',
      }),
    });
  } catch { /* silent */ }
}

// ── Chat helpers ──────────────────────────────────────────────
function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function escapeHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatBotText(text) {
  return text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/_(.*?)_/g, '<em>$1</em>')
    .replace(/`(.*?)`/g, '<code>$1</code>')
    .replace(/\n/g, '<br>');
}

function appendRawMessage(htmlContent, sender) {
  const container = document.getElementById('chatMessages');
  if (!container) return;

  const wrap   = document.createElement('div');
  wrap.className = 'message-wrap ' + sender;

  const bubble = document.createElement('div');
  bubble.className = 'message-bubble';
  bubble.innerHTML = htmlContent;

  const time = document.createElement('div');
  time.className = 'message-time';
  const now = new Date();
  time.textContent = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
  bubble.appendChild(time);
  wrap.appendChild(bubble);
  container.appendChild(wrap);
  container.scrollTop = container.scrollHeight;
}

function detectorDebugLabel(detector) {
  if (!detector || !DEBUG_MODE) return '';
  const type = detector.type || 'unknown';
  if (type === 'llm') {
    const provider = detector.provider || 'llm';
    const model = detector.model || 'default';
    return `<div style="margin-top:8px;font-size:.72rem;color:var(--text-light)">debug: ${escapeHtml(provider)} / ${escapeHtml(model)} / intent-llm</div>`;
  }
  return `<div style="margin-top:8px;font-size:.72rem;color:var(--text-light)">debug: rule-based intent detector</div>`;
}

function appendMessage(text, sender) {
  appendRawMessage(sender === 'bot' ? formatBotText(text) : escapeHtml(text), sender);
}

function showTyping(show) {
  const el = document.getElementById('typingIndicator');
  if (!el) return;
  el.style.display = show ? 'flex' : 'none';
  if (show) document.getElementById('chatMessages').scrollTop = 99999;
}

// ── Send message ──────────────────────────────────────────────
async function sendMessage() {
  if (!BRANCH_ID || !chatUser) return;
  const input = document.getElementById('chatInput');
  const text  = input.value.trim();
  if (!text) return;

  input.value = '';
  input.style.height = 'auto';
  appendMessage(text, 'user');
  showTyping(true);

  try {
    const res = await fetch(BASE_URL + '/api/chat/send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        branch_id:         BRANCH_ID,
        message:           text,
        session_id:        SESSION_ID,
        customer_name:     chatUser.name,
        customer_email:    chatUser.email    || '',
        customer_whatsapp: chatUser.wa       || '',
      }),
    });
    showTyping(false);

    let data = null;
    try { data = await res.json(); } catch { /* non-JSON response */ }

    if (data?.success && data.data?.reply_message) {
      const debugHtml = detectorDebugLabel(data.data?.detector);
      const replyHtml = formatBotText(data.data.reply_message) + debugHtml;
      setTimeout(() => appendRawMessage(replyHtml, 'bot'), 300);
    } else {
      appendMessage('Maaf, terjadi kesalahan. Silakan coba lagi.', 'bot');
    }
  } catch {
    showTyping(false);
    appendMessage('Koneksi gagal. Periksa jaringan kamu.', 'bot');
  }
}

// ── Auto-resize textarea ──────────────────────────────────────
const chatInput = document.getElementById('chatInput');
if (chatInput) {
  chatInput.addEventListener('input', () => {
    chatInput.style.height = 'auto';
    chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
  });
}
</script>
</body>
</html>
