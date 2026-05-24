<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat Demo — Toko Kopi</title>
  <?php
  require_once dirname(__DIR__) . '/app/Config/config.php';
  use App\Models\BranchModel;
  use App\Models\MenuModel;
  use App\Helpers\Auth;
  use App\Plugin\HookManager;
  Auth::startSession();
  $branchModel = new BranchModel();
  $branches    = $branchModel->getActive();
  $currency = 'IDR';
  $language = 'id';

  // Start session for chat identification
  $sessionId   = session_id();
  $selectedBranchId = (int)($_GET['branch'] ?? ($_SESSION['chat_branch_id'] ?? 0));
  if ($selectedBranchId) {
      $_SESSION['chat_branch_id'] = $selectedBranchId;
      $selectedBranch = $branchModel->find($selectedBranchId);
      $currency = $branchModel->getCurrency($selectedBranchId);
      $language = $branchModel->getLanguage($selectedBranchId);
  }
  $menuModel = new MenuModel();
  $chatMenuItems = [];
  $chatCategories = [];
  if (!empty($selectedBranch['id'])) {
      foreach ($menuModel->getCategoriesWithCount((int)$selectedBranch['id']) as $category) {
          $chatCategories[] = [
              'id' => (int)($category['id'] ?? 0),
              'name' => (string)($category['name'] ?? ''),
              'slug' => (string)($category['slug'] ?? ''),
              'item_count' => (int)($category['item_count'] ?? 0),
          ];
      }
      foreach ($menuModel->getMenuForBranch((int)$selectedBranch['id']) as $item) {
          if (!(bool)($item['effective_available'] ?? false)) {
              continue;
          }
          $chatMenuItems[] = [
              'id' => (int)($item['id'] ?? 0),
              'name' => (string)($item['name'] ?? ''),
              'description' => (string)($item['description'] ?? ''),
              'price' => (float)($item['effective_price'] ?? 0),
              'image_url' => \App\Helpers\MenuImage::publicUrl($item['image_path'] ?? null),
              'variants' => array_map(static fn(array $variant): array => [
                  'id' => (int)($variant['id'] ?? 0),
                  'label' => (string)($variant['label'] ?? ''),
                  'price' => (float)($variant['effective_price'] ?? 0),
              ], $item['variants'] ?? []),
          ];
      }
  }
  $webChatConfig = HookManager::applyFilters('webchat.page_config', [
      'enabled' => false,
      'theme' => 'default',
      'brand_icon' => '☕',
      'assistant_name' => 'Kopi Bot',
      'assistant_status' => 'Online',
      'welcome_prompt' => 'Ketik menu, promo, rekomendasi, atau checkout.',
      'quick_actions' => [],
      'menu_items' => $chatMenuItems,
      'categories' => $chatCategories,
      'branch_slug' => (string)($selectedBranch['slug'] ?? ''),
  ], $selectedBranchId, $selectedBranch ?? []);
  $webChatHeadHtml = HookManager::applyFilters('webchat.head_html', '', $webChatConfig, $selectedBranchId, $selectedBranch ?? []);
  ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <?= HookManager::applyFilters('site.head_styles', '') ?>
  <?= $webChatHeadHtml ?>
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
      transition:width .25s ease, transform .25s ease, opacity .25s ease;
      overflow:hidden;
    }
    body.sidebar-collapsed .branch-sidebar {
      width:0;
      opacity:0;
      pointer-events:none;
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
    .sidebar-desktop-toggle {
      display:inline-flex; align-items:center; justify-content:center;
      width:40px; height:40px; border-radius:12px;
      border:1px solid rgba(255,255,255,.18);
      background:rgba(255,255,255,.08); color:#fff; cursor:pointer;
      margin-right:12px;
    }
    @media (max-width: 900px) {
      .sidebar-desktop-toggle {
        display:none;
      }
    }
    @media (max-width: 900px) {
      body.sidebar-collapsed .branch-sidebar {
        width:min(86vw, 320px);
        opacity:1;
        pointer-events:auto;
      }
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
    body.chat-theme-rich { background:#041425; }
    body.chat-theme-rich .chat-area {
      background:
        linear-gradient(180deg, rgba(6,27,41,.98), rgba(3,17,31,.98)),
        radial-gradient(circle at top right, rgba(35,179,190,.18), transparent 28%);
    }
    body.chat-theme-rich .chat-container {
      margin:14px;
      border-radius:28px;
      overflow:hidden;
      background:
        linear-gradient(180deg, rgba(4,22,36,.96), rgba(8,23,36,.98)),
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
      background-size:auto, 28px 28px, 28px 28px;
      border:1px solid rgba(112,225,237,.12);
      box-shadow:0 24px 64px rgba(0,0,0,.32);
    }
    body.chat-theme-rich .chat-header {
      background:linear-gradient(135deg, #177f83, #1c8f90);
      padding:18px 20px;
      border-bottom:none;
      color:#fff;
    }
    body.chat-theme-rich .chat-header-avatar {
      background:rgba(255,255,255,.12);
      color:#fff;
      box-shadow:none;
    }
    body.chat-theme-rich .chat-header-info h3,
    body.chat-theme-rich .chat-header-info span,
    body.chat-theme-rich .chat-home-link,
    body.chat-theme-rich .user-badge {
      color:#fff;
    }
    body.chat-theme-rich .chat-home-link,
    body.chat-theme-rich .user-badge,
    body.chat-theme-rich .chat-mobile-toggle {
      background:rgba(255,255,255,.12);
      border-color:rgba(255,255,255,.18);
    }
    body.chat-theme-rich .chat-messages {
      background:transparent;
      padding:16px 14px 10px;
    }
    body.chat-theme-rich .message-wrap.bot .message-bubble {
      background:#11293a;
      color:#e9f2fb;
      border-top-left-radius:10px;
    }
    body.chat-theme-rich .message-wrap.user .message-bubble {
      background:linear-gradient(135deg, #1d6d8f, #245f92);
      color:#fff;
      border-top-right-radius:10px;
    }
    body.chat-theme-rich .message-time { color:rgba(255,255,255,.58); }
    body.chat-theme-rich .chat-input-area {
      background:rgba(6,19,30,.96);
      border-top:1px solid rgba(112,225,237,.12);
    }
    body.chat-theme-rich .chat-input {
      background:#f5f6f8;
      border:none;
      border-radius:999px;
      color:#223;
      box-shadow:inset 0 1px 1px rgba(0,0,0,.06);
    }
    body.chat-theme-rich .chat-send-btn {
      background:linear-gradient(135deg, #0fa8c3, #1493e0);
      box-shadow:0 10px 22px rgba(15,168,195,.28);
    }
    .chat-quick-actions {
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin:12px 0 4px;
    }
    .chat-quick-chip {
      border:none;
      padding:9px 14px;
      border-radius:999px;
      cursor:pointer;
      font-size:.82rem;
      font-weight:700;
      background:rgba(99,216,231,.14);
      color:#8cebf4;
      border:1px solid rgba(99,216,231,.22);
      transition:transform .18s ease, background .18s ease;
    }
    .chat-quick-chip:hover { transform:translateY(-1px); background:rgba(99,216,231,.22); }
    .chat-reply-actions {
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      margin-top:12px;
    }
    .chat-reply-btn {
      border-radius:999px;
      padding:10px 16px;
      font-size:.86rem;
      font-weight:800;
      cursor:pointer;
      border:1px solid rgba(90,214,233,.24);
      background:rgba(14,92,120,.12);
      color:#a5f3fc;
      transition:transform .18s ease, background .18s ease;
    }
    .chat-reply-btn:hover { transform:translateY(-1px); background:rgba(14,92,120,.22); }
    .chat-reply-btn.primary {
      background:linear-gradient(135deg, #63d8e7, #5dc8ee);
      color:#083148;
      border:none;
      box-shadow:0 10px 20px rgba(95,199,239,.2);
    }
    .chat-reply-btn.danger {
      background:rgba(117,42,60,.18);
      color:#fecaca;
      border:1px solid rgba(244,114,182,.22);
    }
    .chat-cart-sheet {
      position:fixed;
      right:18px;
      bottom:22px;
      width:min(420px, calc(100vw - 24px));
      max-height:min(78vh, 760px);
      z-index:120;
      display:none;
      flex-direction:column;
      border-radius:24px;
      overflow:hidden;
      background:linear-gradient(180deg, rgba(10,29,45,.98), rgba(8,24,39,.99));
      border:1px solid rgba(101,223,236,.14);
      box-shadow:0 30px 80px rgba(0,0,0,.45);
    }
    .chat-cart-sheet.open { display:flex; }
    .chat-side-sheet {
      position:fixed;
      right:18px;
      bottom:22px;
      width:min(420px, calc(100vw - 24px));
      max-height:min(78vh, 760px);
      z-index:121;
      display:none;
      flex-direction:column;
      border-radius:24px;
      overflow:hidden;
      background:linear-gradient(180deg, rgba(10,29,45,.98), rgba(8,24,39,.99));
      border:1px solid rgba(101,223,236,.14);
      box-shadow:0 30px 80px rgba(0,0,0,.45);
    }
    .chat-side-sheet.open { display:flex; }
    .chat-cart-sheet-header {
      display:flex; justify-content:space-between; align-items:center;
      padding:16px 18px; color:#effaff;
      background:linear-gradient(135deg, #177f83, #1c8f90);
    }
    .chat-cart-sheet-close {
      width:38px; height:38px; border:none; border-radius:12px;
      background:rgba(255,255,255,.12); color:#fff; cursor:pointer;
    }
    .chat-cart-sheet-body {
      padding:16px;
      overflow:auto;
      display:grid;
      gap:12px;
    }
    .chat-cart-item {
      background:rgba(16,39,56,.96);
      border:1px solid rgba(101,223,236,.1);
      border-radius:20px;
      padding:14px;
      display:grid;
      gap:10px;
    }
    .chat-cart-item-top {
      display:flex; justify-content:space-between; gap:12px; align-items:flex-start;
      color:#eaf8ff;
    }
    .chat-cart-item-name { font-weight:800; line-height:1.45; }
    .chat-cart-item-meta { color:#b5c9d6; font-size:.84rem; margin-top:4px; }
    .chat-cart-item-price { color:#77effa; font-weight:800; white-space:nowrap; }
    .chat-cart-qty-row {
      display:flex; justify-content:space-between; align-items:center; gap:10px;
    }
    .chat-qty-control {
      display:flex; align-items:center; gap:10px;
      background:rgba(255,255,255,.04);
      border:1px solid rgba(101,223,236,.12);
      border-radius:999px;
      padding:6px 8px;
      color:#e8f7ff;
    }
    .chat-qty-control strong { color:#e8f7ff; font-weight:800; min-width:18px; text-align:center; }
    .chat-qty-btn {
      width:34px; height:34px; border:none; border-radius:999px;
      background:rgba(96,209,235,.14); color:#95f6ff; font-size:1.05rem; cursor:pointer;
    }
    .chat-cart-actions {
      display:flex; flex-wrap:wrap; gap:10px;
    }
    .chat-cart-footer {
      border-top:1px solid rgba(101,223,236,.12);
      padding:16px;
      display:grid;
      gap:12px;
      background:rgba(8,20,30,.98);
    }
    .chat-cart-total {
      display:flex; justify-content:space-between; align-items:center;
      color:#f3fbff; font-weight:800; font-size:1.02rem;
    }
    .chat-cart-empty {
      color:#c7d9e4; text-align:center; padding:20px 10px;
    }
    .chat-inline-cart {
      display:grid;
      gap:12px;
      margin-top:12px;
    }
    .chat-inline-cart-card {
      background:rgba(16,39,56,.96);
      border:1px solid rgba(101,223,236,.1);
      border-radius:18px;
      padding:14px;
      display:grid;
      gap:10px;
    }
    .chat-inline-cart-top {
      display:flex;
      justify-content:space-between;
      gap:12px;
      align-items:flex-start;
      color:#eaf8ff;
    }
    .chat-inline-cart-name {
      font-weight:800;
      line-height:1.45;
    }
    .chat-inline-cart-meta {
      color:#b5c9d6;
      font-size:.84rem;
      margin-top:4px;
    }
    .chat-inline-cart-total {
      color:#77effa;
      font-weight:800;
      white-space:nowrap;
    }
    .chat-choice-stack {
      display:grid;
      gap:12px;
      margin-top:12px;
    }
    .chat-choice-summary {
      color:#d7ecf6;
      font-size:.86rem;
      line-height:1.5;
    }
    .chat-choice-grid {
      display:flex;
      flex-wrap:wrap;
      gap:10px;
    }
    .chat-choice-chip {
      border-radius:999px;
      padding:10px 14px;
      cursor:pointer;
      font-size:.86rem;
      font-weight:800;
      border:1px solid rgba(90,214,233,.2);
      background:rgba(14,92,120,.1);
      color:#a5f3fc;
      transition:transform .18s ease, background .18s ease, border-color .18s ease, opacity .18s ease;
    }
    .chat-choice-chip:hover { transform:translateY(-1px); background:rgba(14,92,120,.18); }
    .chat-choice-chip.active {
      background:linear-gradient(135deg, #63d8e7, #5dc8ee);
      color:#083148;
      border-color:transparent;
      box-shadow:0 10px 20px rgba(95,199,239,.18);
    }
    .chat-choice-chip:disabled {
      opacity:.45;
      cursor:not-allowed;
      transform:none;
    }
    .chat-sheet-body {
      padding:18px 16px;
      overflow:auto;
      display:grid;
      gap:14px;
    }
    .chat-sheet-hero {
      display:grid;
      grid-template-columns:84px 1fr;
      gap:14px;
      align-items:start;
    }
    .chat-sheet-thumb {
      width:84px; height:84px; border-radius:20px;
      object-fit:cover; background:#fff; display:block;
      box-shadow:0 10px 22px rgba(0,0,0,.18);
    }
    .chat-sheet-fallback {
      width:84px; height:84px; border-radius:20px;
      display:flex; align-items:center; justify-content:center;
      background:linear-gradient(135deg, #102a3c, #204861);
      font-size:2rem; color:#9ff6ff;
    }
    .chat-sheet-title {
      color:#f6fbff; font-size:1.1rem; font-weight:900; line-height:1.35;
    }
    .chat-sheet-price {
      color:#ff7b66; font-size:1rem; font-weight:900; margin-top:6px;
    }
    .chat-sheet-text {
      color:#c9d7e3; font-size:.9rem; line-height:1.6;
    }
    .rich-message-stack { display:grid; gap:12px; }
    .rich-caption {
      font-size:.8rem;
      color:rgba(255,255,255,.66);
      margin-top:2px;
    }
    .product-card-list { display:grid; gap:14px; margin-top:10px; }
    .product-rich-card {
      background:rgba(17,42,58,.96);
      border:1px solid rgba(99,216,231,.14);
      border-radius:22px;
      padding:14px;
      display:grid;
      gap:12px;
      position:relative;
      overflow:hidden;
    }
    .product-rich-main { display:flex; gap:14px; align-items:flex-start; }
    .product-rich-thumb {
      width:74px; height:74px; flex-shrink:0; border-radius:18px;
      background:#fff; object-fit:cover; display:block;
      box-shadow:0 10px 22px rgba(0,0,0,.18);
    }
    .product-rich-fallback {
      width:74px; height:74px; flex-shrink:0; border-radius:18px;
      display:flex; align-items:center; justify-content:center;
      background:linear-gradient(135deg, #102a3c, #204861);
      font-size:1.8rem; color:#9ff6ff;
    }
    .product-rich-meta { min-width:0; flex:1; }
    .product-rich-name {
      font-size:1.05rem; font-weight:800; color:#f5fbff; margin:2px 0 6px;
    }
    .product-rich-price {
      color:#ff6d5e; font-size:1.05rem; font-weight:900; margin-bottom:6px;
    }
    .product-rich-desc {
      color:#c9d7e3; font-size:.88rem; line-height:1.55;
      display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
    }
    .product-variant-select {
      width:100%;
      border:none;
      border-radius:999px;
      padding:12px 16px;
      background:#f6f4f2;
      color:#25313b;
      font-size:.92rem;
    }
    .product-rich-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .product-rich-btn {
      border:none;
      border-radius:999px;
      padding:11px 16px;
      font-weight:800;
      font-size:.9rem;
      cursor:pointer;
    }
    .product-rich-btn.secondary {
      background:rgba(18,116,143,.12);
      color:#9deeff;
      border:1px solid rgba(70,219,239,.22);
    }
    .product-rich-btn.primary {
      background:linear-gradient(135deg, #58d6e8, #5fc7ef);
      color:#083148;
      box-shadow:0 10px 20px rgba(95,199,239,.2);
    }
    @media (max-width: 560px) {
      body.chat-theme-rich .chat-container { margin:0; border-radius:0; min-height:100dvh; }
      .product-rich-thumb, .product-rich-fallback { width:64px; height:64px; border-radius:16px; }
      .product-rich-name { font-size:1rem; }
      .product-rich-btn { flex:1 1 140px; }
    }
  </style>
</head>
<body class="<?= !empty($webChatConfig['enabled']) ? 'chat-theme-rich' : '' ?>">
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
        <button type="button" class="sidebar-desktop-toggle" onclick="toggleSidebar()" aria-label="Buka atau tutup daftar cabang">☰</button>
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

  <div class="chat-cart-sheet" id="chatCartSheet">
    <div class="chat-cart-sheet-header">
      <div>
        <div style="font-size:1rem;font-weight:800">Keranjang Anda</div>
        <div style="font-size:.8rem;opacity:.8"><span id="chatCartItemCountLabel">0 item</span> • Kelola item tanpa keluar dari chat</div>
      </div>
      <button type="button" class="chat-cart-sheet-close" onclick="closeCartSheet()">✕</button>
    </div>
    <div class="chat-cart-sheet-body" id="chatCartSheetBody"></div>
    <div class="chat-cart-footer">
      <div class="chat-cart-total" style="font-size:.92rem;font-weight:700;color:#d2e7f2">
        <span>Subtotal</span>
        <span id="chatCartSubtotal">Rp 0</span>
      </div>
      <div class="chat-cart-total" id="chatCartPromoRow" style="display:none;font-size:.88rem;color:#d2e7f2">
        <span>Promo<span id="chatCartPromoCode"></span></span>
        <span id="chatCartPromo">- Rp 0</span>
      </div>
      <div class="chat-cart-total" id="chatCartLoyaltyRow" style="display:none;font-size:.88rem;color:#d2e7f2">
        <span>Diskon Loyalty</span>
        <span id="chatCartLoyalty">- Rp 0</span>
      </div>
      <div class="chat-cart-total" id="chatCartPpnRow" style="display:none;font-size:.88rem;color:#d2e7f2">
        <span data-role="ppn-label">PPN</span>
        <span id="chatCartPpn">Rp 0</span>
      </div>
      <div class="chat-cart-total">
        <span>Total <span id="chatCartFooterCount" style="font-size:.8rem;opacity:.8;font-weight:600"></span></span>
        <span id="chatCartTotal">Rp 0</span>
      </div>
      <div class="chat-cart-actions">
        <button type="button" class="chat-reply-btn primary" onclick="sendPresetMessage('checkout')">Checkout</button>
        <button type="button" class="chat-reply-btn" onclick="openCheckoutForm()">Checkout di Chat</button>
        <button type="button" class="chat-reply-btn danger" onclick="clearChatCart()">Batal Keranjang</button>
      </div>
    </div>
  </div>

  <div class="chat-side-sheet" id="chatProductSheet">
    <div class="chat-cart-sheet-header">
      <div>
        <div style="font-size:1rem;font-weight:800">Detail Produk</div>
        <div style="font-size:.8rem;opacity:.8">Lihat detail tanpa keluar dari chat</div>
      </div>
      <button type="button" class="chat-cart-sheet-close" onclick="closeProductDetail()">&times;</button>
    </div>
    <div class="chat-sheet-body" id="chatProductSheetBody"></div>
    <div class="chat-cart-footer">
      <div class="chat-cart-actions">
        <button type="button" class="chat-reply-btn" onclick="openCartSheet()">Lihat Keranjang</button>
      </div>
    </div>
  </div>
</div>

<script>
const BRANCH_ID   = <?= $selectedBranchId ?>;
const SESSION_ID  = '<?= $sessionId ?>';
const BASE_URL    = '<?= BASE_URL ?>';
const BRANCH_NAME = '<?= addslashes(htmlspecialchars($selectedBranch['name'] ?? '')) ?>';
const DEBUG_MODE  = <?= isset($_GET['debug']) && $_GET['debug'] === '1' ? 'true' : 'false' ?>;
const CHAT_CURRENCY = <?= json_encode($currency, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const CHAT_LANGUAGE = <?= json_encode($language, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const WEBCHAT_CONFIG = <?= json_encode($webChatConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}' ?>;

// ── Global storage key (same user across branches) ────────────
const STORAGE_KEY = 'toko_kopi_user';

// ── Identity state ────────────────────────────────────────────
let chatUser = null;  // { name, wa, email }
let chatCartState = {
  items: [],
  total: 0,
  cart: null,
  summary: {
    subtotal: 0,
    discount_amount: 0,
    promo_code: '',
    loyalty_discount_amount: 0,
    ppn_rate: 0,
    ppn_amount: 0,
    total: 0,
  },
};
let activeProductDetail = null;

// ── On load: check if identity already saved in localStorage ──
window.addEventListener('DOMContentLoaded', () => {
  if (!BRANCH_ID) return;

  const productSheetClose = document.querySelector('#chatProductSheet .chat-cart-sheet-close');
  if (productSheetClose) productSheetClose.innerHTML = '&times;';

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
  document.body.classList.remove('sidebar-collapsed');
  document.body.classList.add('sidebar-open');
}

function closeSidebar() {
  document.body.classList.remove('sidebar-open');
}

function toggleSidebar() {
  if (window.innerWidth <= 900) {
    if (document.body.classList.contains('sidebar-open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
    return;
  }
  document.body.classList.toggle('sidebar-collapsed');
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
    renderQuickActions();

    // Register name+email silently via first chat ping
    registerCustomer();
  } else {
    // Returning visitor — restore minimal greeting
    appendRawMessage(`Halo lagi, <strong>${escapeHtml(chatUser.name)}</strong>! ☕ Ada yang bisa saya bantu?`, 'bot');
    renderQuickActions();
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
  const escaped = escapeHtml(String(text ?? ''));
  return escaped
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/_(.*?)_/g, '<em>$1</em>')
    .replace(/`(.*?)`/g, '<code>$1</code>')
    .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>')
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
  const meta = arguments.length > 2 ? arguments[2] : {};
  decorateRichMessage(bubble, sender, htmlContent, meta);

  const time = document.createElement('div');
  time.className = 'message-time';
  const now = new Date();
  time.textContent = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
  bubble.appendChild(time);
  wrap.appendChild(bubble);
  container.appendChild(wrap);
  container.scrollTop = container.scrollHeight;
}

function formatCurrency(amount) {
  const value = Number(amount || 0);
  const locale = CHAT_LANGUAGE === 'en'
    ? (CHAT_CURRENCY === 'SGD' ? 'en-SG' : 'en-US')
    : 'id-ID';
  const fractionDigits = CHAT_CURRENCY === 'IDR' ? 0 : 2;
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: CHAT_CURRENCY || 'IDR',
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  }).format(value);
}

function getQuickActions() {
  const actions = Array.isArray(WEBCHAT_CONFIG?.quick_actions) ? WEBCHAT_CONFIG.quick_actions : [];
  return actions.filter(action => action && action.label && action.value);
}

function renderQuickActions() {
  if (!WEBCHAT_CONFIG?.enabled) return;
  const container = document.getElementById('chatMessages');
  if (!container || container.querySelector('.chat-quick-actions')) return;
  const actions = getQuickActions();
  if (!actions.length) return;

  const wrap = document.createElement('div');
  wrap.className = 'chat-quick-actions';
  actions.forEach(action => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'chat-quick-chip';
    btn.textContent = action.label;
    btn.addEventListener('click', () => sendPresetMessage(action.value));
    wrap.appendChild(btn);
  });
  container.appendChild(wrap);
  container.scrollTop = container.scrollHeight;
}

async function sendPresetMessage(value) {
  const input = document.getElementById('chatInput');
  if (!input) return;
  input.value = value;
  await sendMessage();
}

function openCheckoutForm() {
  closeCartSheet();
  const input = document.getElementById('chatInput');
  if (input) {
    input.focus();
  }
  appendRawMessage('Checkout tetap dilanjutkan di chat ini. Saya bantu pandu data pesanan Anda langkah demi langkah.', 'bot');
  sendPresetMessage('checkout');
}

async function fetchChatCartState() {
  if (!BRANCH_ID) return chatCartState;
  try {
    const res = await fetch(`${BASE_URL}/api/chat/cart-state.php?branch_id=${encodeURIComponent(BRANCH_ID)}&session_id=${encodeURIComponent(SESSION_ID)}`);
    const data = await res.json().catch(() => null);
    if (data?.success) {
      chatCartState = {
        items: Array.isArray(data.data?.items) ? data.data.items : [],
        total: Number(data.data?.total || 0),
        cart: data.data?.cart || null,
        summary: {
          item_count: Number(data.data?.summary?.item_count || 0),
          line_count: Number(data.data?.summary?.line_count || 0),
          subtotal: Number(data.data?.summary?.subtotal || 0),
          discount_amount: Number(data.data?.summary?.discount_amount || 0),
          promo_code: String(data.data?.summary?.promo_code || ''),
          loyalty_discount_amount: Number(data.data?.summary?.loyalty_discount_amount || 0),
          ppn_rate: Number(data.data?.summary?.ppn_rate || 0),
          ppn_amount: Number(data.data?.summary?.ppn_amount || 0),
          total: Number(data.data?.summary?.total || data.data?.total || 0),
        },
      };
    }
  } catch {
      // keep previous state
  }
  return chatCartState;
}

function normalizeCartName(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .replace(/[^\p{L}\p{N}\- ]/gu, '')
    .trim();
}

function findCartItemByReplyName(name) {
  const target = normalizeCartName(name);
  return (chatCartState.items || []).find(item => {
    const full = normalizeCartName(item.name || '');
    const base = normalizeCartName(item.base_name || '');
    return full === target || base === target || full.includes(target) || target.includes(full);
  }) || null;
}

async function updateChatCartItem(item, quantity) {
  if (!item) return;
  await fetch(`${BASE_URL}/api/cart/update.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      branch_id: BRANCH_ID,
      session_id: SESSION_ID,
      cart_item_id: Number(item.id || item.cart_item_id || 0),
      menu_item_id: Number(item.menu_item_id || 0),
      variant_id: item.variant_id ? Number(item.variant_id) : null,
      quantity,
      customer_name: chatUser?.name || '',
      customer_email: chatUser?.email || '',
      customer_whatsapp: chatUser?.wa || '',
    }),
  }).catch(() => null);
  await fetchChatCartState();
  renderCartSheet();
}

async function removeChatCartItemByName(name) {
  await fetchChatCartState();
  const item = findCartItemByReplyName(name);
  if (!item) return;
  await updateChatCartItem(item, 0);
  appendRawMessage(`${escapeHtml(item.name || name)} dihapus dari keranjang.`, 'bot');
}

async function clearChatCart() {
  await fetch(`${BASE_URL}/api/cart/clear.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      branch_id: BRANCH_ID,
      session_id: SESSION_ID,
    }),
  }).catch(() => null);
  await fetchChatCartState();
  renderCartSheet();
  appendRawMessage('Keranjang dibatalkan. Kalau mau, saya bisa bantu pilih menu lagi.', 'bot');
}

function renderCartSheet() {
  const body = document.getElementById('chatCartSheetBody');
  const total = document.getElementById('chatCartTotal');
  const subtotal = document.getElementById('chatCartSubtotal');
  const promo = document.getElementById('chatCartPromo');
  const loyalty = document.getElementById('chatCartLoyalty');
  const ppn = document.getElementById('chatCartPpn');
  const promoRow = document.getElementById('chatCartPromoRow');
  const loyaltyRow = document.getElementById('chatCartLoyaltyRow');
  const ppnRow = document.getElementById('chatCartPpnRow');
  const promoCode = document.getElementById('chatCartPromoCode');
  const itemCountLabel = document.getElementById('chatCartItemCountLabel');
  const footerCount = document.getElementById('chatCartFooterCount');
  if (!body || !total) return;

  const summary = chatCartState.summary || {};
  const itemCount = Number(summary.item_count || 0);
  total.textContent = formatCurrency(summary.total || chatCartState.total || 0);
  if (itemCountLabel) itemCountLabel.textContent = `${itemCount} item`;
  if (footerCount) footerCount.textContent = itemCount > 0 ? `(${itemCount} item)` : '';
  if (subtotal) subtotal.textContent = formatCurrency(summary.subtotal || 0);
  if (promo) promo.textContent = `- ${formatCurrency(summary.discount_amount || 0)}`;
  if (loyalty) loyalty.textContent = `- ${formatCurrency(summary.loyalty_discount_amount || 0)}`;
  if (ppn) ppn.textContent = formatCurrency(summary.ppn_amount || 0);
  if (promoCode) promoCode.textContent = summary.promo_code ? ` (${summary.promo_code})` : '';
  if (promoRow) promoRow.style.display = Number(summary.discount_amount || 0) > 0 ? 'flex' : 'none';
  if (loyaltyRow) loyaltyRow.style.display = Number(summary.loyalty_discount_amount || 0) > 0 ? 'flex' : 'none';
  if (ppnRow) ppnRow.style.display = Number(summary.ppn_amount || 0) > 0 ? 'flex' : 'none';
  if (ppnRow) {
    const label = ppnRow.querySelector('[data-role="ppn-label"]');
    if (label) {
      label.textContent = `PPN (${Number(summary.ppn_rate || 0)}%)`;
    }
  }

  if (!Array.isArray(chatCartState.items) || !chatCartState.items.length) {
    body.innerHTML = '<div class="chat-cart-empty">Keranjang masih kosong.</div>';
    return;
  }

  body.innerHTML = '';
  chatCartState.items.forEach(item => {
    const lineTotal = Number(item.quantity || 0) * Number(item.unit_price || 0);
    const card = document.createElement('div');
    card.className = 'chat-cart-item';
    card.innerHTML = `
      <div class="chat-cart-item-top">
        <div>
          <div class="chat-cart-item-name">${escapeHtml(item.name || '')}</div>
          <div class="chat-cart-item-meta">${escapeHtml(item.notes || 'Tanpa catatan')}</div>
        </div>
        <div class="chat-cart-item-price">${formatCurrency(lineTotal)}</div>
      </div>
      <div class="chat-cart-qty-row">
        <div class="chat-qty-control">
          <button type="button" class="chat-qty-btn" data-action="minus">−</button>
          <strong>${Number(item.quantity || 0)}</strong>
          <button type="button" class="chat-qty-btn" data-action="plus">+</button>
        </div>
        <div class="chat-cart-actions">
          <button type="button" class="chat-reply-btn danger" data-action="remove">Batal Item</button>
        </div>
      </div>
    `;

    card.querySelector('[data-action="minus"]')?.addEventListener('click', async () => {
      await updateChatCartItem(item, Math.max(0, Number(item.quantity || 0) - 1));
    });
    card.querySelector('[data-action="plus"]')?.addEventListener('click', async () => {
      await updateChatCartItem(item, Number(item.quantity || 0) + 1);
    });
    card.querySelector('[data-action="remove"]')?.addEventListener('click', async () => {
      await updateChatCartItem(item, 0);
    });
    body.appendChild(card);
  });
}

async function openCartSheet() {
  await fetchChatCartState();
  renderCartSheet();
  document.getElementById('chatCartSheet')?.classList.add('open');
}

function closeCartSheet() {
  document.getElementById('chatCartSheet')?.classList.remove('open');
}

function closeProductDetail() {
  document.getElementById('chatProductSheet')?.classList.remove('open');
}

function stripHtmlToText(html) {
  const el = document.createElement('div');
  el.innerHTML = String(html || '');
  return (el.textContent || el.innerText || '').trim();
}

function findReferencedProducts(text) {
  const menuItems = Array.isArray(WEBCHAT_CONFIG?.menu_items) ? WEBCHAT_CONFIG.menu_items : [];
  const normalized = text.toLowerCase();
  const matches = menuItems.filter(item => item?.name && normalized.includes(String(item.name).toLowerCase()));
  return matches;
}

function getBranchCategories() {
  return Array.isArray(WEBCHAT_CONFIG?.categories) ? WEBCHAT_CONFIG.categories : [];
}

function isCategoryPayload(payload) {
  return Array.isArray(payload)
    && payload.length > 0
    && payload.every(item => item && typeof item.name === 'string' && Object.prototype.hasOwnProperty.call(item, 'item_count'));
}

function isToppingSelectionPayload(payload) {
  return Boolean(
    payload
    && typeof payload === 'object'
    && payload.type === 'topping_selection'
    && Array.isArray(payload.toppings)
    && payload.toppings.length
  );
}

function isCartItemsPayload(payload) {
  return Array.isArray(payload)
    && payload.length > 0
    && payload.every(item => item && (
      Object.prototype.hasOwnProperty.call(item, 'quantity')
      || Object.prototype.hasOwnProperty.call(item, 'qty')
      || Object.prototype.hasOwnProperty.call(item, 'cart_item_id')
    ));
}

function isProductItemsPayload(payload) {
  return Array.isArray(payload)
    && payload.length > 0
    && payload.every(item => item
      && typeof item.name === 'string'
      && !Object.prototype.hasOwnProperty.call(item, 'item_count')
      && (
        Object.prototype.hasOwnProperty.call(item, 'effective_price')
        || Object.prototype.hasOwnProperty.call(item, 'price')
        || Object.prototype.hasOwnProperty.call(item, 'effective_available')
      ));
}

function hasActionLabel(actions, label) {
  return Array.isArray(actions) && actions.some(action => String(action?.label || '') === label);
}

function appendDefaultReplyActions(actions, options = {}) {
  if (options?.suppressDefaults) {
    return Array.isArray(actions) ? [...actions] : [];
  }
  const nextActions = Array.isArray(actions) ? [...actions] : [];

  if (!hasActionLabel(nextActions, 'Lihat Keranjang')) {
    nextActions.push({ label: 'Lihat Keranjang', value: 'keranjang saya', tone: '', action: 'open-cart' });
  }
  if (!hasActionLabel(nextActions, 'Menu')) {
    nextActions.push({ label: 'Menu', value: 'menu', tone: '' });
  }
  if (!hasActionLabel(nextActions, 'Promo')) {
    nextActions.push({ label: 'Promo', value: 'promo hari ini', tone: '' });
  }

  return nextActions;
}

function isCheckoutStage(text, meta = {}) {
  const state = String(meta?.conversationState || meta?.state || '');
  if (state.startsWith('awaiting_')) {
    return true;
  }

  const lower = String(text || '').toLowerCase();
  return lower.includes('ringkasan order')
    || lower.includes('order summary')
    || lower.includes('nomor whatsapp kamu')
    || lower.includes('your whatsapp number')
    || lower.includes('alamat email kamu')
    || lower.includes('your email address')
    || lower.includes('metode pesanan')
    || lower.includes('fulfillment method')
    || lower.includes('kode pos daerah kamu')
    || lower.includes('your postal code')
    || lower.includes('alamat pengiriman lengkap kamu')
    || lower.includes('complete delivery address')
    || lower.includes('ketik ya untuk konfirmasi order')
    || lower.includes('type yes to confirm order');
}

function resolveCategoryResults(text, meta = {}) {
  if (isCategoryPayload(meta?.actionResult)) {
    return meta.actionResult.slice(0, 8);
  }

  const lower = String(text || '').toLowerCase();
  if (lower.includes('kategori') || lower.includes('categories')) {
    return getBranchCategories().slice(0, 8);
  }

  return [];
}

function mapPayloadProduct(item) {
  const menuItems = Array.isArray(WEBCHAT_CONFIG?.menu_items) ? WEBCHAT_CONFIG.menu_items : [];
  const cached = menuItems.find(m => Number(m?.id) === Number(item?.id || 0));
  return {
    id: Number(item?.id || 0),
    name: String(item?.name || ''),
    description: String(item?.description || ''),
    price: Number(item?.effective_price ?? item?.price ?? 0),
    image_url: item?.image_url || cached?.image_url || '',
    variants: Array.isArray(item?.variants) ? item.variants.map(variant => ({
      id: Number(variant?.id || 0),
      label: String(variant?.label || ''),
      price: Number(variant?.effective_price ?? variant?.price ?? 0),
    })) : [],
  };
}

function resolveProductResults(text, meta = {}) {
  if (isProductItemsPayload(meta?.actionResult)) {
    return meta.actionResult.map(mapPayloadProduct);
  }
  return findReferencedProducts(text);
}

function extractCartItemName(text) {
  const match = text.match(/^(.+?)\s+ditambahkan ke keranjang/i);
  if (match && match[1]) return match[1].trim();
  const removedMatch = text.match(/^(?:✅\s*)?(.+?)\s+dihapus dari keranjang/i);
  if (removedMatch && removedMatch[1]) return removedMatch[1].trim();
  return '';
}

function buildReplyActions(text, meta = {}) {
  const lower = text.toLowerCase();
  const itemName = extractCartItemName(text);
  const actions = [];
  const categories = resolveCategoryResults(text, meta);
  const checkoutStage = isCheckoutStage(text, meta);
  const conversationState = String(meta?.conversationState || '');

  if (conversationState === 'awaiting_confirmation') {
    actions.push({ label: 'Ya, Konfirmasi', value: 'ya', tone: 'primary' });
    actions.push({ label: 'Batal', value: 'batal', tone: 'danger' });
    actions.push({ label: 'Lihat Keranjang', value: 'keranjang saya', tone: '', action: 'open-cart' });
    return appendDefaultReplyActions(actions, { suppressDefaults: true });
  }

  if (itemName) {
    actions.push({ label: 'Edit Qty', value: 'keranjang saya', tone: '', action: 'open-cart' });
    actions.push({ label: 'Batal Item', value: `hapus ${itemName}`, tone: 'danger', action: 'remove-item', meta: { itemName } });
    actions.push({ label: 'Checkout', value: 'checkout', tone: 'primary' });
    actions.push({ label: 'Lihat Keranjang', value: 'keranjang saya', tone: '', action: 'open-cart' });
    return appendDefaultReplyActions(actions);
  }

  if (lower.includes('keranjang kamu') || lower.includes('keranjang anda') || lower.includes('your cart')) {
    actions.push({ label: 'Checkout', value: 'checkout', tone: 'primary' });
    actions.push({ label: 'Checkout di Chat', value: 'checkout', tone: '', action: 'open-checkout-form' });
    actions.push({ label: 'Lihat Keranjang', value: 'keranjang saya', tone: '', action: 'open-cart' });
    actions.push({ label: 'Batal Keranjang', value: 'kosongkan keranjang', tone: 'danger', action: 'clear-cart' });
    return appendDefaultReplyActions(actions);
  }

  if (lower.includes('alamat email kamu') || lower.includes('your email address') || lower.includes('format email tidak valid') || lower.includes('invalid email format')) {
    actions.push({ label: 'Skip Email', value: 'skip', tone: '' });
    return appendDefaultReplyActions(actions, { suppressDefaults: true });
  }

  if (lower.includes('nomor whatsapp kamu') || lower.includes('your whatsapp number') || lower.includes('nomor whatsapp tidak valid') || lower.includes('invalid whatsapp number')) {
    actions.push({ label: 'Lihat Keranjang', value: 'keranjang saya', tone: '', action: 'open-cart' });
    return appendDefaultReplyActions(actions, { suppressDefaults: true });
  }

  if (lower.includes('metode pesanan') || lower.includes('fulfillment method') || lower.includes('ambil di toko') || lower.includes('pickup in store')) {
    actions.push({ label: 'Ambil di Toko', value: 'ambil di toko', tone: '' });
    actions.push({ label: 'Delivery ke Meja', value: 'delivery ke meja', tone: '' });
    actions.push({ label: 'Delivery ke Alamat', value: 'delivery ke alamat', tone: 'primary' });
    return appendDefaultReplyActions(actions, { suppressDefaults: true });
  }

  if (lower.includes('nomor meja kamu') || lower.includes('what is your table number')) {
    actions.push({ label: 'Meja 1', value: '1', tone: '' });
    actions.push({ label: 'Meja 2', value: '2', tone: '' });
    actions.push({ label: 'Meja 3', value: '3', tone: '' });
    actions.push({ label: 'Meja 4', value: '4', tone: '' });
    return appendDefaultReplyActions(actions, { suppressDefaults: true });
  }

  if (lower.includes('ketik ya untuk konfirmasi order') || lower.includes('type yes to confirm order') || lower.includes('ringkasan order') || lower.includes('order summary')) {
    actions.push({ label: 'Ya, Konfirmasi', value: 'ya', tone: 'primary' });
    actions.push({ label: 'Batal', value: 'batal', tone: 'danger' });
    actions.push({ label: 'Lihat Keranjang', value: 'keranjang saya', tone: '', action: 'open-cart' });
    return appendDefaultReplyActions(actions, { suppressDefaults: true });
  }

  if (categories.length && !meta?.categoryBrowser) {
    actions.push({ label: 'Lihat Kategori', value: 'menu', tone: '', action: 'browse-categories', meta: { categories } });
  }

  if (lower.includes('menu') || lower.includes('produk')) {
    actions.push({ label: 'Lihat Produk', value: 'menu', tone: '' });
    actions.push({ label: 'Lihat Keranjang', value: 'keranjang saya', tone: '', action: 'open-cart' });
  }

  if (lower.includes('promo')) {
    actions.push({ label: 'Pakai Promo', value: 'promo hari ini', tone: '' });
  }

  return appendDefaultReplyActions(actions, { suppressDefaults: checkoutStage });
}

function createReplyActionBar(actions) {
  if (!Array.isArray(actions) || !actions.length) return null;
  const wrap = document.createElement('div');
  wrap.className = 'chat-reply-actions';

  actions.forEach(action => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `chat-reply-btn ${action.tone || ''}`.trim();
    btn.textContent = action.label;
    btn.addEventListener('click', async () => {
      if (action.action === 'open-cart') {
        await openCartSheet();
        return;
      }
      if (action.action === 'open-checkout-form') {
        openCheckoutForm();
        return;
      }
      if (action.action === 'clear-cart') {
        await clearChatCart();
        return;
      }
      if (action.action === 'remove-item') {
        await removeChatCartItemByName(action.meta?.itemName || '');
        return;
      }
      if (action.action === 'browse-categories') {
        appendCategoryBrowserMessage(action.meta?.categories || []);
        return;
      }
      await sendPresetMessage(action.value);
    });
    wrap.appendChild(btn);
  });

  return wrap;
}

function createProductCard(item) {
  const card = document.createElement('div');
  card.className = 'product-rich-card';

  const thumbHtml = item.image_url
    ? `<img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.name)}" class="product-rich-thumb">`
    : `<div class="product-rich-fallback">&#9749;</div>`;

  card.innerHTML = `
    <div class="product-rich-main">
      ${thumbHtml}
      <div class="product-rich-meta">
        <div class="product-rich-name">${escapeHtml(item.name || '')}</div>
        <div class="product-rich-price">${formatCurrency(item.price || 0)}</div>
        <div class="product-rich-desc">${escapeHtml(item.description || 'Menu pilihan cabang ini siap dipesan lewat chat atau checkout web.')}</div>
      </div>
    </div>
  `;

  if (Array.isArray(item.variants) && item.variants.length) {
    const select = document.createElement('select');
    select.className = 'product-variant-select';
    item.variants.forEach(variant => {
      const option = document.createElement('option');
      option.value = variant.label || '';
      option.textContent = `${variant.label} - ${formatCurrency(variant.price || item.price || 0)}`;
      select.appendChild(option);
    });
    card.appendChild(select);
  }

  const actions = document.createElement('div');
  actions.className = 'product-rich-actions';

  const detailBtn = document.createElement('button');
  detailBtn.type = 'button';
  detailBtn.className = 'product-rich-btn secondary';
  detailBtn.textContent = 'Lihat';
  detailBtn.addEventListener('click', () => openProductDetail(item));

  const addBtn = document.createElement('button');
  addBtn.type = 'button';
  addBtn.className = 'product-rich-btn primary';
  addBtn.textContent = 'Masuk Keranjang';
  addBtn.addEventListener('click', async () => {
    const select = card.querySelector('.product-variant-select');
    const variantLabel = select ? select.value.trim() : '';
    const command = variantLabel
      ? `pesan 1 ${item.name} varian ${variantLabel}`
      : `pesan 1 ${item.name}`;
    await sendPresetMessage(command);
  });

  actions.appendChild(detailBtn);
  actions.appendChild(addBtn);
  card.appendChild(actions);

  return card;
}

function createToppingSelectionSection(payload) {
  if (!isToppingSelectionPayload(payload)) return null;

  const toppings = payload.toppings.filter(option => option && option.name);
  if (!toppings.length) return null;

  const stack = document.createElement('div');
  stack.className = 'chat-choice-stack';

  const min = Number(payload.min_toppings || 0);
  const max = Number(payload.max_toppings || 0);
  const requiredText = min === max && max > 0
    ? `${max} topping`
    : `${min}-${max} topping`;

  const summary = document.createElement('div');
  summary.className = 'chat-choice-summary';

  const chipGrid = document.createElement('div');
  chipGrid.className = 'chat-choice-grid';

  const selectedIds = new Set(
    (Array.isArray(payload.selected_toppings) ? payload.selected_toppings : [])
      .map(option => Number(option?.id || 0))
      .filter(Boolean)
  );

  function updateSummary() {
    const count = selectedIds.size;
    const selectedNames = toppings
      .filter(option => selectedIds.has(Number(option.id || 0)))
      .map(option => option.name);
    summary.textContent = `Pilih ${requiredText}. Terpilih ${count}${max > 0 ? `/${max}` : ''}: ${selectedNames.length ? selectedNames.join(', ') : 'belum ada'}.`;
  }

  function refreshChipStates() {
    chipGrid.querySelectorAll('[data-topping-id]').forEach(btn => {
      const id = Number(btn.getAttribute('data-topping-id') || 0);
      const isActive = selectedIds.has(id);
      btn.classList.toggle('active', isActive);
      btn.disabled = !isActive && max > 0 && selectedIds.size >= max;
    });
    updateSummary();
  }

  toppings.forEach(option => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'chat-choice-chip';
    btn.setAttribute('data-topping-id', String(Number(option.id || 0)));
    btn.textContent = String(option.name || '');
    btn.addEventListener('click', () => {
      const id = Number(option.id || 0);
      if (!id) return;
      if (selectedIds.has(id)) {
        selectedIds.delete(id);
      } else {
        if (max > 0 && selectedIds.size >= max) {
          return;
        }
        selectedIds.add(id);
      }
      refreshChipStates();
    });
    chipGrid.appendChild(btn);
  });

  const actions = document.createElement('div');
  actions.className = 'chat-reply-actions';

  const confirmBtn = document.createElement('button');
  confirmBtn.type = 'button';
  confirmBtn.className = 'chat-reply-btn primary';
  confirmBtn.textContent = 'Pakai Topping Ini';
  confirmBtn.addEventListener('click', async () => {
    if (selectedIds.size < min || (max > 0 && selectedIds.size > max)) {
      updateSummary();
      return;
    }
    const chosenNames = toppings
      .filter(option => selectedIds.has(Number(option.id || 0)))
      .map(option => option.name);
    if (!chosenNames.length) return;
    await sendPresetMessage(chosenNames.join(', '));
  });

  const resetBtn = document.createElement('button');
  resetBtn.type = 'button';
  resetBtn.className = 'chat-reply-btn';
  resetBtn.textContent = 'Reset Pilihan';
  resetBtn.addEventListener('click', () => {
    selectedIds.clear();
    refreshChipStates();
  });

  const cancelBtn = document.createElement('button');
  cancelBtn.type = 'button';
  cancelBtn.className = 'chat-reply-btn danger';
  cancelBtn.textContent = 'Batal';
  cancelBtn.addEventListener('click', async () => {
    await sendPresetMessage('batal');
  });

  actions.appendChild(confirmBtn);
  actions.appendChild(resetBtn);
  actions.appendChild(cancelBtn);

  stack.appendChild(summary);
  stack.appendChild(chipGrid);
  stack.appendChild(actions);

  refreshChipStates();

  return stack;
}

function createInlineCartSection(items) {
  if (!Array.isArray(items) || !items.length) return null;

  const stack = document.createElement('div');
  stack.className = 'chat-inline-cart';

  items.forEach(rawItem => {
    const sourceItem = rawItem.item || rawItem;
    const sourceVariant = rawItem.variant || rawItem;
    const item = {
      menu_item_id: Number(rawItem.menu_item_id || sourceItem.id || rawItem.id || 0),
      variant_id: rawItem.variant_id ? Number(rawItem.variant_id) : null,
      name: String(rawItem.name || sourceItem.name || rawItem.menu_name || ''),
      base_name: String(rawItem.base_name || sourceItem.name || rawItem.name || rawItem.menu_name || ''),
      notes: String(rawItem.notes || ''),
      quantity: Number(rawItem.quantity || rawItem.qty || 0),
      unit_price: Number(rawItem.unit_price || sourceVariant.effective_price || sourceItem.effective_price || rawItem.price || 0),
      variant_label: String(rawItem.variant_label || sourceVariant.label || ''),
    };

    const card = document.createElement('div');
    card.className = 'chat-inline-cart-card';
    const lineTotal = Number(item.quantity || 0) * Number(item.unit_price || 0);
    card.innerHTML = `
      <div class="chat-inline-cart-top">
        <div>
          <div class="chat-inline-cart-name">${escapeHtml(item.name || '')}</div>
          <div class="chat-inline-cart-meta">${escapeHtml(item.notes || 'Tanpa catatan')}</div>
        </div>
        <div class="chat-inline-cart-total">${formatCurrency(lineTotal)}</div>
      </div>
      <div class="chat-reply-actions">
        <button type="button" class="chat-reply-btn" data-action="edit">Edit Qty</button>
        <button type="button" class="chat-reply-btn danger" data-action="remove">Batal Item</button>
      </div>
    `;

    card.querySelector('[data-action="edit"]')?.addEventListener('click', async () => {
      await fetchChatCartState();
      const liveItem = findCartItemByReplyName(item.name) || item;
      await openCartSheet();
      if (liveItem?.menu_item_id) {
        appendRawMessage(`Edit qty untuk ${escapeHtml(item.name)} bisa langsung lewat panel keranjang di samping chat.`, 'bot');
      }
    });

    card.querySelector('[data-action="remove"]')?.addEventListener('click', async () => {
      await removeChatCartItemByName(item.name);
    });

    stack.appendChild(card);
  });

  return stack;
}

async function appendInlineCartSectionFromState(bubble) {
  await fetchChatCartState();
  const section = createInlineCartSection(chatCartState.items || []);
  if (section) {
    bubble.appendChild(section);
  }
}

function getSelectedVariant(item, selectedLabel = '') {
  const variants = Array.isArray(item?.variants) ? item.variants : [];
  if (!variants.length) return null;
  return variants.find(variant => String(variant?.label || '') === String(selectedLabel || '')) || variants[0] || null;
}

function renderProductDetailSheet(item, selectedLabel = '') {
  const body = document.getElementById('chatProductSheetBody');
  if (!body || !item) return;

  const variant = getSelectedVariant(item, selectedLabel);
  const thumbHtml = item.image_url
    ? `<img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.name || '')}" class="chat-sheet-thumb">`
    : `<div class="chat-sheet-fallback">&#9749;</div>`;
  const variants = Array.isArray(item.variants) ? item.variants : [];
  const activePrice = variant ? Number(variant.price || item.price || 0) : Number(item.price || 0);

  body.innerHTML = `
    <div class="chat-sheet-hero">
      ${thumbHtml}
      <div>
        <div class="chat-sheet-title">${escapeHtml(item.name || '')}</div>
        <div class="chat-sheet-price">${formatCurrency(activePrice)}</div>
      </div>
    </div>
    <div class="chat-sheet-text">${escapeHtml(item.description || 'Produk ini siap dipesan langsung dari chat.')}</div>
    ${variants.length ? '<select class="product-variant-select" id="chatProductVariantSelect"></select>' : ''}
    <div class="chat-cart-actions">
      <button type="button" class="chat-reply-btn primary" id="chatProductAddBtn">Masuk Keranjang</button>
      <button type="button" class="chat-reply-btn" id="chatProductAskBtn">Tanya Produk</button>
      <button type="button" class="chat-reply-btn" id="chatProductCartBtn">Lihat Keranjang</button>
    </div>
  `;

  if (!item.image_url) {
    const fallback = body.querySelector('.chat-sheet-fallback');
    if (fallback) fallback.innerHTML = '&#9749;';
  }

  const select = body.querySelector('#chatProductVariantSelect');
  if (select && variants.length) {
    variants.forEach(optionItem => {
      const option = document.createElement('option');
      option.value = optionItem.label || '';
      option.textContent = `${optionItem.label} - ${formatCurrency(optionItem.price || item.price || 0)}`;
      if ((optionItem.label || '') === (variant?.label || '')) {
        option.selected = true;
      }
      select.appendChild(option);
    });
    select.addEventListener('change', () => renderProductDetailSheet(item, select.value));
  }

  body.querySelector('#chatProductAddBtn')?.addEventListener('click', async () => {
    const selectedVariant = body.querySelector('#chatProductVariantSelect')?.value?.trim() || '';
    const command = selectedVariant
      ? `pesan 1 ${item.name} varian ${selectedVariant}`
      : `pesan 1 ${item.name}`;
    closeProductDetail();
    await sendPresetMessage(command);
  });
  body.querySelector('#chatProductAskBtn')?.addEventListener('click', async () => {
    closeProductDetail();
    await sendPresetMessage(`info ${item.name}`);
  });
  body.querySelector('#chatProductCartBtn')?.addEventListener('click', async () => {
    closeProductDetail();
    await openCartSheet();
  });
}

function openProductDetail(item) {
  activeProductDetail = item || null;
  renderProductDetailSheet(item);
  document.getElementById('chatProductSheet')?.classList.add('open');
}

function createCategorySection(categories, title = 'Pilih kategori untuk langsung lihat isi menunya:') {
  if (!Array.isArray(categories) || !categories.length) return null;

  const stack = document.createElement('div');
  stack.className = 'rich-message-stack';

  const caption = document.createElement('div');
  caption.className = 'rich-caption';
  caption.textContent = title;
  stack.appendChild(caption);

  const actions = document.createElement('div');
  actions.className = 'chat-reply-actions';

  categories.forEach(category => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'chat-reply-btn';
    const count = Number(category?.item_count || 0);
    btn.textContent = count > 0
      ? `${category.name} (${count})`
      : String(category?.name || '');
    btn.addEventListener('click', async () => {
      await sendPresetMessage(String(category?.name || '').trim());
    });
    actions.appendChild(btn);
  });

  stack.appendChild(actions);
  return stack;
}

function appendCategoryBrowserMessage(categories = []) {
  const source = Array.isArray(categories) && categories.length ? categories : getBranchCategories().slice(0, 8);
  if (!source.length) return;
  appendRawMessage(
    'Silakan pilih kategori yang ingin Anda lihat.',
    'bot',
    { actionResult: source, categoryBrowser: true }
  );
}

function decorateRichMessage(bubble, sender, htmlContent, meta = {}) {
  if (!WEBCHAT_CONFIG?.enabled || sender !== 'bot') return;
  const plainText = stripHtmlToText(htmlContent);
  const checkoutStage = isCheckoutStage(plainText, meta);
  const replyActions = buildReplyActions(plainText, meta);
  const actionBar = createReplyActionBar(replyActions);
  if (actionBar) {
    bubble.appendChild(actionBar);
  }

  const categories = resolveCategoryResults(plainText, meta);
  if (categories.length) {
    const categorySection = createCategorySection(
      categories,
      meta?.categoryBrowser
        ? 'Kategori tersedia untuk cabang ini:'
        : 'Pilih kategori untuk langsung lihat isi menunya:'
    );
    if (categorySection) {
      bubble.appendChild(categorySection);
    }
  }

  const toppingSection = createToppingSelectionSection(meta?.actionResult || null);
  if (toppingSection) {
    bubble.appendChild(toppingSection);
  }

  const stateIsConfirmation = String(meta?.conversationState || '').startsWith('awaiting_');

  if (!stateIsConfirmation) {
    if (isCartItemsPayload(meta?.actionResult)) {
      const cartSection = createInlineCartSection(meta.actionResult);
      if (cartSection) {
        bubble.appendChild(cartSection);
      }
    } else if (/keranjang|your cart|total:/i.test(plainText)) {
      appendInlineCartSectionFromState(bubble);
    }
  }

  if (checkoutStage || stateIsConfirmation) {
    return;
  }

  const products = resolveProductResults(plainText, meta);
  if (!products.length) return;

  const stack = document.createElement('div');
  stack.className = 'rich-message-stack';

  const caption = document.createElement('div');
  caption.className = 'rich-caption';
  caption.textContent = 'Produk terkait yang bisa langsung Anda eksplor atau pesan:';
  stack.appendChild(caption);

  const list = document.createElement('div');
  list.className = 'product-card-list';
  products.forEach(item => list.appendChild(createProductCard(item)));
  stack.appendChild(list);
  bubble.appendChild(stack);
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
      setTimeout(() => appendRawMessage(replyHtml, 'bot', {
        actionResult: data.data?.action_result ?? null,
        intent: data.data?.intent || '',
        conversationState: data.data?.conversation?.state || '',
      }), 300);
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
