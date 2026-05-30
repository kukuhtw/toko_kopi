<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Config/config.php';
use App\Models\{BranchModel, MenuModel};
use App\Helpers\{Auth, Currency, MenuImage};
use App\Plugin\HookManager;
Auth::startSession();

$slug        = $_GET['branch'] ?? '';
$branchModel = new BranchModel();
$branch      = $slug ? $branchModel->findBySlug($slug) : null;
if (!$branch) { header('Location: ' . BASE_URL . '/'); exit; }

$menuModel = new MenuModel();
$currency  = $branchModel->getCurrency((int)$branch['id']);
$language  = $branchModel->getLanguage((int)$branch['id']);
$ppnRate   = $branchModel->getPpnRate((int)$branch['id']);
$sessionId = session_id();
$rajaOngkirEnabled = \App\Plugin\PluginLoader::isLoaded('rajaongkir-delivery');

$isEnglish = $language === 'en';
$t = [
    'search_menu'   => $isEnglish ? 'Search menu...' : 'Cari menu...',
    'all'           => $isEnglish ? 'All' : 'Semua',
    'cart_title'    => $isEnglish ? 'Shopping Cart' : 'Keranjang Belanja',
    'no_items'      => $isEnglish ? 'No items yet' : 'Belum ada item',
    'delivery_data' => $isEnglish ? 'Delivery Details' : 'Data Pengiriman',
    'fulfillment'   => $isEnglish ? 'Order Method *' : 'Metode Pesanan *',
    'pickup'        => $isEnglish ? 'Pick up in store' : 'Ambil di toko',
    'table'         => $isEnglish ? 'Delivery to table number' : 'Delivery ke nomor meja',
    'delivery'      => $isEnglish ? 'Delivery to customer address' : 'Delivery ke alamat pemesan',
    'table_number'  => $isEnglish ? 'Table number *' : 'Nomor meja *',
    'full_name'     => $isEnglish ? 'Full name *' : 'Nama lengkap *',
    'email_opt'     => $isEnglish ? 'Email (optional)' : 'Email (opsional)',
    'wa'            => $isEnglish ? 'WhatsApp number *' : 'Nomor WhatsApp *',
    'address'       => $isEnglish ? 'Full delivery address *' : 'Alamat pengiriman lengkap *',
    'postal'        => $isEnglish ? 'Postal code *' : 'Kode Pos *',
    'notes'         => $isEnglish ? 'Order notes (optional)' : 'Catatan order (opsional)',
    'promo_code'    => $isEnglish ? 'Promo Code' : 'Kode Promo',
    'promo_input'   => $isEnglish ? 'Enter promo code' : 'Masukkan kode promo',
    'apply'         => $isEnglish ? 'Apply' : 'Terapkan',
    'use_points'    => $isEnglish ? 'Use Points' : 'Pakai Poin',
    'remove_points' => $isEnglish ? 'Remove Points' : 'Batalkan Poin',
    'create_order'  => $isEnglish ? 'Create Order' : 'Buat Order',
    'chat_bot'      => $isEnglish ? 'Chat with Bot' : 'Chat dengan Bot',
    'order_ok'      => $isEnglish ? 'Order Successful!' : 'Order Berhasil!',
    'order_again'   => $isEnglish ? 'Order Again' : 'Pesan Lagi',
    'customize'     => $isEnglish ? 'Customize' : 'Atur',
    'pay_now'       => $isEnglish ? 'Pay Now' : 'Bayar Sekarang',
];

// Build JS-ready data — only available items, no DOM rendering in PHP
$menuData = [];
$catMap   = [];
foreach ($menuModel->getMenuForBranch($branch['id']) as $item) {
    if (!(bool)$item['effective_available']) {
        continue;
    }
    $catId = (int)$item['category_id'];
    if (!isset($catMap[$catId])) {
        $catMap[$catId] = [
            'id'    => $catId,
            'name'  => $item['category_name'],
            'slug'  => $item['category_slug'] ?? '',
            'count' => 0,
        ];
    }
    $catMap[$catId]['count']++;
    $menuData[] = [
        'id'            => (int)$item['id'],
        'name'          => $item['name'],
        'description'   => $item['description'] ?? '',
        'image_url'     => MenuImage::publicUrl($item['image_path'] ?? null),
        'category_id'   => $catId,
        'category_name' => $item['category_name'],
        'category_slug' => $item['category_slug'] ?? '',
        'price'         => (float)$item['effective_price'],
        'has_variants'  => !empty($item['variants']),
        'variants'      => array_map(static fn(array $variant): array => [
            'id' => (int)($variant['id'] ?? 0),
            'label' => (string)($variant['label'] ?? ''),
            'price' => (float)($variant['effective_price'] ?? 0),
        ], $item['variants'] ?? []),
        'has_toppings'  => !empty($item['toppings']),
        'min_toppings'  => (int)($item['min_toppings'] ?? 0),
        'max_toppings'  => (int)($item['max_toppings'] ?? 0),
        'toppings'      => array_map(static fn(array $topping): array => [
            'id' => (int)($topping['id'] ?? 0),
            'name' => (string)($topping['name'] ?? ''),
            'slug' => (string)($topping['slug'] ?? ''),
        ], $item['toppings'] ?? []),
    ];
}
$categories = array_values($catMap);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Online — <?= htmlspecialchars($branch['name']) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <?= HookManager::applyFilters('site.head_styles', '') ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/order-mobile.css">
  <?= HookManager::applyFilters('site.head_styles', '') ?>
  <style>
    body { background:var(--coffee-cream); }
    .order-page  { max-width:1100px; margin:0 auto; padding:20px; }
    .order-layout { display:grid; grid-template-columns:1fr 380px; gap:24px; align-items:start; }

    /* Branch hero */
    .branch-hero { background:var(--coffee-dark); color:#fff; padding:32px; border-radius:var(--radius-lg); margin-bottom:24px; }
    .branch-hero-top { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
    .branch-hero h1 { font-size:1.8rem; margin-bottom:8px; }
    .branch-hero p  { opacity:.8; }
    .home-link {
      display:inline-flex; align-items:center; gap:8px;
      color:#fff; text-decoration:none; font-size:.9rem; font-weight:600;
      padding:10px 14px; border-radius:999px;
      border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.08);
      white-space:nowrap; transition:background .2s, border-color .2s;
    }
    .home-link:hover { background:rgba(255,255,255,.14); border-color:rgba(255,255,255,.28); }

    /* Filter bar */
    .menu-filters {
      background:#fff; border-radius:var(--radius-lg);
      padding:14px 16px; margin-bottom:14px;
      border:1px solid var(--border);
    }
    .menu-search {
      width:100%; padding:10px 14px; border-radius:var(--radius);
      border:1.5px solid var(--border); font-size:.95rem;
      outline:none; transition:border-color .2s; box-sizing:border-box;
      margin-bottom:12px;
    }
    .menu-search:focus { border-color:var(--coffee-brown); }
    .cat-tabs  { display:flex; gap:8px; flex-wrap:wrap; }
    .cat-tab {
      background:none; border:1.5px solid var(--border); border-radius:20px;
      padding:5px 14px; font-size:.82rem; cursor:pointer;
      color:var(--text-mid); transition:all .15s; white-space:nowrap;
    }
    .cat-tab:hover { border-color:var(--coffee-brown); color:var(--coffee-brown); }
    .cat-tab.active { background:var(--coffee-brown); border-color:var(--coffee-brown); color:#fff; font-weight:600; }
    .cat-tab .cnt {
      display:inline-block; border-radius:10px; padding:0 7px;
      font-size:.72rem; margin-left:4px;
      background:var(--coffee-cream); color:var(--text-mid);
    }
    .cat-tab.active .cnt { background:rgba(255,255,255,.25); color:#fff; }

    /* Menu items */
    .cat-section    { margin-bottom:20px; }
    .cat-header     {
      font-size:1rem; font-weight:700; color:var(--coffee-dark);
      margin:0 0 10px; padding-bottom:8px;
      border-bottom:2px solid var(--coffee-cream);
      display:flex; justify-content:space-between; align-items:center;
    }
    .cat-see-all    { font-size:.8rem; font-weight:500; color:var(--coffee-brown); cursor:pointer; }
    .menu-card {
      background:#fff; border-radius:var(--radius-lg); padding:14px 16px;
      margin-bottom:10px; border:1px solid var(--border);
      display:flex; gap:14px; align-items:center;
      transition:box-shadow .15s;
    }
    .menu-card:hover { box-shadow:0 2px 12px var(--shadow); }
    .menu-card-img  {
      width:68px; height:68px; border-radius:10px; flex-shrink:0;
      background:var(--coffee-cream); display:flex; align-items:center;
      justify-content:center; font-size:1.9rem;
    }
    .menu-card-info  { flex:1; min-width:0; }
    .menu-card-name  { font-weight:600; margin-bottom:3px; }
    .menu-card-desc  { font-size:.82rem; color:var(--text-mid); margin-bottom:5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .menu-card-price { font-weight:700; color:var(--coffee-brown); font-size:.95rem; }
    .qty-ctrl   { display:flex; align-items:center; gap:8px; flex-shrink:0; }
    .qty-btn    {
      width:28px; height:28px; border-radius:50%;
      border:2px solid var(--coffee-brown); background:none; cursor:pointer;
      font-size:1rem; font-weight:600; color:var(--coffee-brown);
      display:flex; align-items:center; justify-content:center;
    }
    .qty-btn:hover  { background:var(--coffee-brown); color:#fff; }
    .qty-display    { min-width:24px; text-align:center; font-weight:600; }
    .load-more-btn  {
      width:100%; padding:11px; margin-top:4px;
      background:var(--coffee-cream); border:1.5px solid var(--border);
      border-radius:var(--radius); cursor:pointer; font-size:.88rem;
      color:var(--text-mid); transition:background .2s;
    }
    .load-more-btn:hover { background:#e8d5c0; }
    .no-results { text-align:center; padding:48px 20px; color:var(--text-light); font-size:.95rem; }

    /* Cart */
    .cart-box        { position:sticky; top:20px; }
    .cart-items-list { max-height:380px; overflow-y:auto; }
    .cart-item       { display:flex; flex-direction:column; align-items:stretch; padding:9px 0; border-bottom:1px solid var(--border); font-size:.9rem; }
    .cart-item-row   { display:flex; justify-content:space-between; align-items:center; }
    .cart-item-meta  { display:block; color:var(--text-mid); font-size:.78rem; margin-top:3px; }
    .item-notes-input {
      width:100%; border:none; border-top:1px dashed var(--border);
      font-size:.78rem; color:var(--text-mid); padding:5px 0 2px;
      margin-top:5px; outline:none; background:transparent; box-sizing:border-box;
    }
    .item-notes-input::placeholder { color:var(--border); }
    .item-notes-input:focus { color:var(--coffee-dark); border-top-color:var(--coffee-brown); }
    .cart-total      { font-size:1.15rem; font-weight:700; color:var(--coffee-dark); padding-top:12px; display:flex; justify-content:space-between; }
    .checkout-form   { margin-top:16px; border-top:1px solid var(--border); padding-top:16px; }
    .fulfillment-options { display:grid; gap:8px; margin-bottom:12px; }
    .fulfillment-option {
      display:flex; align-items:flex-start; gap:10px; padding:10px 12px; border:1px solid var(--border);
      border-radius:12px; background:var(--bg-light,#faf9f7); cursor:pointer;
    }
    .fulfillment-option input { margin-top:3px; }
    .customize-btn {
      margin-top:8px; border:1px solid var(--coffee-brown); background:#fff; color:var(--coffee-brown);
      border-radius:999px; padding:7px 14px; font-size:.82rem; font-weight:600; cursor:pointer;
    }
    .customize-btn:hover { background:var(--coffee-brown); color:#fff; }
    .option-list { display:grid; gap:8px; margin-top:10px; }
    .option-row {
      display:flex; justify-content:space-between; align-items:center; gap:12px;
      padding:10px 12px; border:1px solid var(--border); border-radius:12px;
    }
    .topping-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px; }
    .topping-option {
      display:flex; align-items:center; gap:8px; padding:9px 10px; border:1px solid var(--border);
      border-radius:12px; font-size:.88rem;
    }

    /* Profile recognition */
    .profile-banner {
      background:rgba(101,67,33,.06); border:1.5px solid rgba(101,67,33,.2);
      border-radius:10px; padding:10px 14px; margin-bottom:14px;
      display:flex; align-items:center; justify-content:space-between; gap:10px;
    }
    .profile-banner-name { font-weight:600; font-size:.9rem; color:var(--coffee-dark); }
    .profile-banner-sub  { font-size:.78rem; color:var(--text-mid); margin-top:2px; }
    .profile-banner-clear {
      font-size:.78rem; color:var(--text-light); cursor:pointer; white-space:nowrap;
      border:none; background:none; padding:4px 8px; border-radius:6px; flex-shrink:0;
    }
    .profile-banner-clear:hover { background:rgba(0,0,0,.06); color:var(--text-mid); }

    /* Floating chat button */
    .chatbot-float {
      position:fixed; bottom:24px; right:24px;
      width:56px; height:56px; border-radius:50%;
      background:var(--coffee-brown); color:#fff; text-decoration:none;
      display:flex; align-items:center; justify-content:center;
      font-size:1.5rem; box-shadow:0 4px 16px var(--shadow);
      z-index:100; transition:transform .2s;
    }
    .chatbot-float:hover { transform:scale(1.1); }

    @media(max-width:768px) {
      .order-layout { grid-template-columns:1fr; }
      .cart-box     { position:static; }
      .branch-hero-top { flex-direction:column; align-items:flex-start; }
    }
  </style>
</head>
<body>
<div class="order-page">

  <div class="branch-hero">
    <div class="branch-hero-top">
      <div>
        <h1>☕ <?= htmlspecialchars($branch['name']) ?></h1>
        <p><?= htmlspecialchars($branch['address'] ?? 'Indonesia') ?></p>
        <?php if ($branch['phone']): ?><p>📞 <?= htmlspecialchars($branch['phone']) ?></p><?php endif; ?>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end">
        <a href="<?= BASE_URL ?>/customer/login.php" class="home-link">👤 Customer Dashboard</a>
        <a href="<?= BASE_URL ?>/index.php" class="home-link">← Home</a>
      </div>
    </div>
  </div>

  <div class="order-layout">

    <!-- ── Menu column ── -->
    <div>
      <div class="menu-filters">
        <input type="search" id="menuSearch" class="menu-search"
               placeholder="🔍 <?= htmlspecialchars($t['search_menu']) ?>" autocomplete="off"
               oninput="onSearch(this.value)">
        <div class="cat-tabs" id="catTabs">
          <button class="cat-tab active" id="tab-all" onclick="setCategory(null)">
            <?= htmlspecialchars($t['all']) ?> <span class="cnt" id="cnt-all">0</span>
          </button>
        </div>
      </div>
      <div id="menuContainer"></div>
    </div>

    <!-- ── Cart column ── -->
    <div class="cart-box">
      <div class="card">
        <div class="card-title">🛒 <?= htmlspecialchars($t['cart_title']) ?></div>
        <div class="cart-items-list" id="cartList">
          <p style="color:var(--text-light);text-align:center"><?= htmlspecialchars($t['no_items']) ?></p>
        </div>
        <div class="cart-total">
          <span>Total:</span>
          <span id="cartTotal"><?= Currency::format(0, $currency) ?></span>
        </div>

        <div class="checkout-form" id="checkoutForm" style="display:none">
          <h4 style="margin-bottom:12px;color:var(--coffee-dark)"><?= htmlspecialchars($t['delivery_data']) ?></h4>

          <div id="profileBanner" class="profile-banner" style="display:none">
            <div>
              <div class="profile-banner-name" id="profileBannerName"></div>
              <div class="profile-banner-sub"><?= $isEnglish ? 'Your details have been pre-filled.' : 'Data kamu sudah diisi otomatis.' ?></div>
            </div>
            <button type="button" class="profile-banner-clear" onclick="clearSavedProfile()">
              <?= $isEnglish ? 'Not you? ×' : 'Bukan kamu? ×' ?>
            </button>
          </div>

          <div class="form-group">
            <label style="font-size:.85rem;font-weight:600;margin-bottom:8px;display:block"><?= htmlspecialchars($t['fulfillment']) ?></label>
            <div class="fulfillment-options">
              <label class="fulfillment-option">
                <input type="radio" name="fulfillmentType" value="pickup" onchange="onFulfillmentChange()" checked>
                <span><?= htmlspecialchars($t['pickup']) ?></span>
              </label>
              <label class="fulfillment-option">
                <input type="radio" name="fulfillmentType" value="table" onchange="onFulfillmentChange()">
                <span><?= htmlspecialchars($t['table']) ?></span>
              </label>
              <label class="fulfillment-option">
                <input type="radio" name="fulfillmentType" value="delivery" onchange="onFulfillmentChange()">
                <span><?= htmlspecialchars($t['delivery']) ?></span>
              </label>
            </div>
          </div>
          <div class="form-group"><input type="text"  id="custName"    class="form-control" placeholder="<?= htmlspecialchars($t['full_name']) ?>"></div>
          <div class="form-group"><input type="email" id="custEmail"   class="form-control" placeholder="<?= htmlspecialchars($t['email_opt']) ?>"></div>
          <div class="form-group"><input type="text"  id="custWa"      class="form-control" placeholder="<?= htmlspecialchars($t['wa']) ?>"></div>
          <div class="form-group" id="tableNumberGroup" style="display:none"><input type="text" id="custTableNumber" class="form-control" placeholder="<?= htmlspecialchars($t['table_number']) ?>"></div>
          <div class="form-group" id="addressGroup" style="display:none"><textarea id="custAddress" class="form-control" rows="2" placeholder="<?= htmlspecialchars($t['address']) ?>"></textarea></div>
          <div class="form-group" id="postalGroup" style="display:none"><input type="text" id="custPostal" class="form-control" placeholder="<?= htmlspecialchars($t['postal']) ?>"></div>
          <div class="form-group" id="deliveryFeeGroup" style="display:none">
            <button type="button" class="btn btn-outline" onclick="calculateDeliveryFee()"><?= $isEnglish ? 'Calculate delivery fee' : 'Hitung biaya delivery' ?></button>
            <div id="deliveryFeeMsg" style="font-size:.82rem;margin-top:6px;color:var(--text-mid)"></div>
          </div>
          <div class="form-group"><textarea            id="custNotes"   class="form-control" rows="2" placeholder="<?= htmlspecialchars($t['notes']) ?>"></textarea></div>
          <div class="form-group">
            <label for="promoCode" style="font-size:.85rem;font-weight:600;margin-bottom:4px;display:block"><?= htmlspecialchars($t['promo_code']) ?></label>
            <div style="display:flex;gap:8px">
              <input type="text" id="promoCode" class="form-control"
                     placeholder="<?= htmlspecialchars($t['promo_input']) ?>"
                     style="text-transform:uppercase;flex:1">
              <button type="button" class="btn btn-outline" onclick="applyPromo()" style="white-space:nowrap"><?= htmlspecialchars($t['apply']) ?></button>
            </div>
            <div id="promoMsg" style="font-size:.82rem;margin-top:4px"></div>
          </div>
          <div class="form-group" id="loyaltyBox" style="display:none">
            <label style="font-size:.85rem;font-weight:600;margin-bottom:4px;display:block">Loyalty Point</label>
            <div id="loyaltyInfo" style="font-size:.82rem;color:var(--text-mid);margin-bottom:6px"></div>
            <div style="display:flex;gap:8px">
              <input type="number" id="loyaltyPointsInput" class="form-control" min="0" step="1" placeholder="<?= $isEnglish ? 'Points to redeem' : 'Jumlah poin dipakai' ?>">
              <button type="button" class="btn btn-outline" id="loyaltyApplyBtn" onclick="applyLoyaltyPoints()" style="white-space:nowrap"><?= htmlspecialchars($t['use_points']) ?></button>
              <button type="button" class="btn btn-outline" id="loyaltyClearBtn" onclick="clearLoyaltyPoints()" style="white-space:nowrap;display:none"><?= htmlspecialchars($t['remove_points']) ?></button>
            </div>
            <div id="loyaltyMsg" style="font-size:.82rem;margin-top:4px"></div>
          </div>
          <button id="placeOrderBtn" class="btn btn-primary"
                  style="width:100%;justify-content:center" onclick="placeOrder()">
            🛒 <?= htmlspecialchars($t['create_order']) ?>
          </button>
        </div>

        <button id="checkoutBtn" class="btn btn-primary"
                style="width:100%;justify-content:center;margin-top:12px;display:none"
                onclick="showCheckout()">
          Checkout →
        </button>
      </div>
    </div>
  </div>
</div>

<a href="<?= BASE_URL ?>/chat.php?branch=<?= $branch['id'] ?>" class="chatbot-float" title="<?= htmlspecialchars($t['chat_bot']) ?>">💬</a>

<div id="orderSuccess" class="modal-overlay hidden">
  <div class="modal-box" style="text-align:center">
    <div style="font-size:3rem;margin-bottom:16px">✅</div>
    <div class="modal-title"><?= htmlspecialchars($t['order_ok']) ?></div>
    <p id="orderSuccessMsg" style="color:var(--text-mid);margin-bottom:20px"></p>
    <p id="orderPaymentHint" style="color:var(--text-mid);margin-bottom:16px;display:none"></p>
    <a id="orderPaymentBtn" href="#" target="_blank" rel="noopener" class="btn btn-primary hidden" style="margin-bottom:12px">
      💳 <?= htmlspecialchars($t['pay_now']) ?>
    </a>
    <br>
    <a id="orderCustomerDashboardBtn" href="<?= BASE_URL ?>/customer/login.php" class="btn btn-outline hidden" style="margin-bottom:12px">
      👤 Customer Dashboard
    </a>
    <br>
    <a href="<?= BASE_URL ?>/order.php?branch=<?= $slug ?>" class="btn btn-primary"><?= htmlspecialchars($t['order_again']) ?></a>
  </div>
</div>

<div id="customizeModal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-title" id="customizeTitle"><?= htmlspecialchars($t['customize']) ?></div>
    <div id="customizeBody"></div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeCustomizeModal()"><?= $isEnglish ? 'Cancel' : 'Batal' ?></button>
      <button type="button" class="btn btn-primary" onclick="confirmCustomize()"><?= $isEnglish ? 'Add to Cart' : 'Tambah ke Keranjang' ?></button>
    </div>
  </div>
</div>

<script>
const BRANCH_ID  = <?= (int)$branch['id'] ?>;
const SESSION_ID = <?= json_encode($sessionId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const BASE_URL   = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const CURRENCY   = <?= json_encode($currency, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const LANGUAGE   = <?= json_encode($language, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const PPN_RATE   = <?= (float)$ppnRate ?>;
const RAJAONGKIR_ENABLED = <?= $rajaOngkirEnabled ? 'true' : 'false' ?>;
const MENU_DATA  = <?= json_encode($menuData,   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]' ?>;
const CATEGORIES = <?= json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]' ?>;
const TEXT = <?= json_encode([
    'no_match_prefix' => $isEnglish ? 'No menu matched "' : 'Tidak ada menu yang cocok dengan "',
    'no_category_items' => $isEnglish ? 'No menu is available in this category.' : 'Tidak ada menu tersedia di kategori ini.',
    'see_all' => $isEnglish ? 'See all' : 'Lihat semua',
    'item_label' => $isEnglish ? 'items' : 'item',
    'show_more' => $isEnglish ? 'Show' : 'Tampilkan',
    'more' => $isEnglish ? 'more' : 'lagi',
    'remaining' => $isEnglish ? 'remaining' : 'tersisa',
    'no_items' => $t['no_items'],
    'promo_discount' => $isEnglish ? 'Promo discount' : 'Diskon promo',
    'loyalty_discount' => $isEnglish ? 'Point discount' : 'Diskon poin',
    'loyalty_balance' => $isEnglish ? 'Point balance' : 'Saldo poin',
    'loyalty_redeemed' => $isEnglish ? 'Redeemed' : 'Dipakai',
    'loyalty_loading' => $isEnglish ? 'Loading loyalty points...' : 'Memuat loyalty point...',
    'loyalty_apply_ok' => $isEnglish ? 'Points applied.' : 'Poin berhasil dipakai.',
    'loyalty_clear_ok' => $isEnglish ? 'Point redemption removed.' : 'Pemakaian poin dibatalkan.',
    'vat' => $isEnglish ? 'VAT' : 'PPN',
    'add_items_first' => $isEnglish ? 'Add items to cart first.' : 'Tambahkan item ke keranjang dulu.',
    'checking_promo' => $isEnglish ? 'Checking promo code...' : 'Memeriksa kode promo…',
    'discount_label' => $isEnglish ? 'Discount:' : 'Diskon:',
    'delivery_fee' => $isEnglish ? 'Delivery fee' : 'Biaya delivery',
    'calculate_delivery' => $isEnglish ? 'Calculate delivery fee' : 'Hitung biaya delivery',
    'calculating_delivery' => $isEnglish ? 'Calculating delivery fee...' : 'Menghitung biaya delivery...',
    'delivery_unavailable' => $isEnglish ? 'Delivery fee is unavailable for this address.' : 'Biaya delivery tidak tersedia untuk alamat ini.',
    'invalid_promo' => $isEnglish ? 'Promo code is invalid.' : 'Kode promo tidak valid.',
    'server_failed' => $isEnglish ? 'Failed to contact server.' : 'Gagal menghubungi server.',
    'required_pickup' => $isEnglish ? 'Name and WhatsApp are required.' : 'Nama dan WhatsApp wajib diisi.',
    'required_table' => $isEnglish ? 'Name, WhatsApp, and table number are required.' : 'Nama, WhatsApp, dan nomor meja wajib diisi.',
    'required_delivery' => $isEnglish ? 'Name, WhatsApp, address, and postal code are required.' : 'Nama, WhatsApp, alamat, dan kode pos wajib diisi.',
    'processing_order' => $isEnglish ? '⏳ Processing your order...' : '⏳ Pesanan sedang diproses...',
    'order_no' => $isEnglish ? 'Your order number is' : 'Nomor order kamu:',
    'order_processing' => $isEnglish ? 'Our admin will process your order shortly!' : 'Admin kami akan segera memproses pesananmu!',
    'payment_ready' => $isEnglish ? 'Your payment link is ready.' : 'Link pembayaran kamu sudah siap.',
    'payment_redirecting' => $isEnglish ? 'Redirecting you to the payment page...' : 'Mengarahkan kamu ke halaman pembayaran...',
    'create_failed' => $isEnglish ? 'Failed to create order:' : 'Gagal membuat order:',
    'generic_error' => $isEnglish ? 'An error occurred.' : 'Terjadi kesalahan.',
    'connection_failed' => $isEnglish ? 'Connection failed. Please try again.' : 'Koneksi gagal. Silakan coba lagi.',
    'create_order' => $t['create_order'],
    'customize' => $t['customize'],
    'starting_from' => $isEnglish ? 'From ' : 'Mulai ',
    'choose_size' => $isEnglish ? 'Choose size' : 'Pilih ukuran',
    'choose_toppings' => $isEnglish ? 'Choose toppings' : 'Pilih topping',
    'choose_exact_toppings' => $isEnglish ? 'Choose exactly' : 'Pilih tepat',
    'toppings' => $isEnglish ? 'toppings' : 'topping',
    'qty' => 'Qty',
    'select_item_first' => $isEnglish ? 'Please choose your size and toppings first.' : 'Pilih ukuran dan topping dulu ya.',
    'invalid_topping_count' => $isEnglish ? 'Selected topping count is not valid.' : 'Jumlah topping yang dipilih belum sesuai.',
    'added_to_cart' => $isEnglish ? 'added to cart' : 'ditambahkan ke keranjang',
    'topping_limit_reached' => $isEnglish ? 'Maximum topping choice reached.' : 'Batas pilihan topping sudah tercapai.',
    'notes_placeholder' => $isEnglish ? 'Notes (e.g. less sugar, no ice)' : 'Catatan (contoh: sedikit gula, tanpa es)',
    'profile_hello' => $isEnglish ? 'Hello, {name}!' : 'Halo, {name}!',
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;

// ── Constants ─────────────────────────────────────────────────────────────────
const CAT_EMOJI  = {'kopi-panas':'☕','kopi-dingin':'🧋','non-kopi':'🍵','cemilan':'🥐','yoghurt-dessert':'🥣'};
const PAGE_SIZE  = 24;  // flat-view: items per load-more step
const PREVIEW    = 6;   // grouped "Semua" view: items shown per category

// ── State ─────────────────────────────────────────────────────────────────────
let cart         = {};
let activeCatId  = null;   // null = all categories
let searchQuery  = '';
let visibleCount = PAGE_SIZE;
let appliedDiscount = 0;
let appliedPromoCode = '';
let loyaltyBalancePoints = 0;
let loyaltyLifetimePoints = 0;
let loyaltyRedeemedPoints = 0;
let loyaltyRedeemedDiscount = 0;
let loyaltyRedeemPointsUnit = 0;
let loyaltyRedeemValueAmount = 0;
let loyaltyMinRedeemPoints = 0;
let customizingItem = null;
let paymentRedirectTimer = null;
let deliveryFee = 0;
let deliveryMeta = null;

function getFulfillmentType() {
    const selected = document.querySelector('input[name="fulfillmentType"]:checked');
    return selected ? selected.value : 'pickup';
}

function onFulfillmentChange() {
    const type = getFulfillmentType();
    document.getElementById('tableNumberGroup').style.display = type === 'table' ? 'block' : 'none';
    document.getElementById('addressGroup').style.display = type === 'delivery' ? 'block' : 'none';
    document.getElementById('postalGroup').style.display = type === 'delivery' ? 'block' : 'none';
    document.getElementById('deliveryFeeGroup').style.display = (type === 'delivery' && RAJAONGKIR_ENABLED) ? 'block' : 'none';
    if (type !== 'delivery') {
        deliveryFee = 0;
        deliveryMeta = null;
        const info = document.getElementById('deliveryFeeMsg');
        if (info) info.textContent = '';
        renderCart();
    }
}

// ── Utilities ─────────────────────────────────────────────────────────────────
function h(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function catEmoji(slug) { return CAT_EMOJI[slug] ?? '🍽️'; }

function fmt(amount) {
    return new Intl.NumberFormat(LANGUAGE === 'en' ? 'en-SG' : 'id-ID', {
        style: 'currency',
        currency: CURRENCY === 'IDR' ? 'IDR' : CURRENCY,
        minimumFractionDigits: CURRENCY === 'IDR' ? 0 : 2,
    }).format(amount);
}

// ── Menu rendering ────────────────────────────────────────────────────────────
function filteredItems() {
    const q = searchQuery.toLowerCase();
    return MENU_DATA.filter(item => {
        if (activeCatId !== null && item.category_id !== activeCatId) return false;
        if (q && !item.name.toLowerCase().includes(q) && !item.description.toLowerCase().includes(q)) return false;
        return true;
    });
}

function itemHtml(item) {
    const descRaw = item.description ?? '';
    const desc = descRaw.length > 72
        ? descRaw.substring(0, 72) + '…'
        : descRaw;
    const thumb = item.image_url
        ? `<div class="menu-card-img" style="background-image:url('${h(item.image_url)}');background-size:cover;background-position:center;background-repeat:no-repeat"></div>`
        : `<div class="menu-card-img">${catEmoji(item.category_slug)}</div>`;

    if (item.has_toppings) {
        return `<div class="menu-card">
          ${thumb}
          <div class="menu-card-info">
            <div class="menu-card-name">${h(item.name)}</div>
            ${desc ? `<div class="menu-card-desc">${h(desc)}</div>` : ''}
            <div class="menu-card-price">${TEXT.starting_from}${fmt(item.variants[0]?.price ?? item.price)}</div>
            <button type="button" class="customize-btn" onclick="openCustomizeModal(${item.id})">${h(TEXT.customize)}</button>
          </div>
        </div>`;
    }

    const variantRows = item.has_variants
        ? item.variants.map(variant => {
            const key = `${item.id}:${variant.id}`;
            const qty = cart[key]?.qty ?? 0;
            return `<div class="cart-item" style="border-bottom:none;padding:4px 0">
              <span>${h(variant.label)}</span>
              <div class="qty-ctrl">
                <span style="min-width:84px;text-align:right;color:var(--coffee-brown);font-weight:600">${fmt(variant.price)}</span>
                <button type="button" class="qty-btn qty-minus" data-id="${item.id}" data-variant-id="${variant.id}">−</button>
                <span class="qty-display" id="qty_${item.id}_${variant.id}">${qty}</span>
                <button type="button" class="qty-btn qty-plus" data-id="${item.id}" data-variant-id="${variant.id}">+</button>
              </div>
            </div>`;
        }).join('')
        : '';

    const singleQty = cart[`${item.id}:0`]?.qty ?? 0;

    return `<div class="menu-card">
      ${thumb}
      <div class="menu-card-info">
        <div class="menu-card-name">${h(item.name)}</div>
        ${desc ? `<div class="menu-card-desc">${h(desc)}</div>` : ''}
        <div class="menu-card-price">${item.has_variants ? 'Mulai ' + fmt(item.variants[0]?.price ?? item.price) : fmt(item.price)}</div>
        ${item.has_variants ? `<div style="margin-top:8px">${variantRows}</div>` : ''}
      </div>
      ${item.has_variants ? '' : `<div class="qty-ctrl">
        <button type="button" class="qty-btn qty-minus" data-id="${item.id}">−</button>
        <span class="qty-display" id="qty_${item.id}_0">${singleQty}</span>
        <button type="button" class="qty-btn qty-plus" data-id="${item.id}">+</button>
      </div>`}
    </div>`;
}

function renderMenu() {
    const container = document.getElementById('menuContainer');
    const items     = filteredItems();

    if (items.length === 0) {
        container.innerHTML = `<div class="no-results">
          😔 ${searchQuery
            ? TEXT.no_match_prefix + '<b>' + h(searchQuery) + '</b>".'
            : TEXT.no_category_items}
        </div>`;
        return;
    }

    // Grouped view: "Semua" tab with no search — show PREVIEW items per category
    if (activeCatId === null && !searchQuery) {
        let html = '';
        CATEGORIES.forEach(cat => {
            const catItems = items.filter(i => i.category_id === cat.id);
            if (!catItems.length) return;
            const preview  = catItems.slice(0, PREVIEW);
            const extra    = catItems.length - preview.length;
            html += `<div class="cat-section">
              <div class="cat-header">
                <span>${catEmoji(cat.slug)} ${h(cat.name)}</span>
                ${extra > 0
                  ? `<span class="cat-see-all" onclick="setCategory(${cat.id})">${TEXT.see_all} ${catItems.length} ${TEXT.item_label} →</span>`
                  : ''}
              </div>
              ${preview.map(itemHtml).join('')}
            </div>`;
        });
        container.innerHTML = html;
        bindMenuQtyButtons();
        return;
    }

    // Flat view: specific category or search result — paginate with load-more
    const shown     = items.slice(0, visibleCount);
    const remaining = items.length - shown.length;
    let   html      = shown.map(itemHtml).join('');
    if (remaining > 0) {
        const next = Math.min(remaining, PAGE_SIZE);
        html += `<button class="load-more-btn" onclick="loadMore()">
          ${TEXT.show_more} ${next} ${TEXT.item_label} ${TEXT.more} <span style="opacity:.6">(${remaining} ${TEXT.remaining})</span>
        </button>`;
    }
    container.innerHTML = html;
    bindMenuQtyButtons();
}

function bindMenuQtyButtons() {
    document.querySelectorAll('.qty-minus').forEach(btn => {
        btn.onclick = () => updateQty(
            parseInt(btn.dataset.id, 10),
            -1,
            btn.dataset.variantId ? parseInt(btn.dataset.variantId, 10) : null
        );
    });

    document.querySelectorAll('.qty-plus').forEach(btn => {
        btn.onclick = () => updateQty(
            parseInt(btn.dataset.id, 10),
            1,
            btn.dataset.variantId ? parseInt(btn.dataset.variantId, 10) : null
        );
    });
}

function loadMore() {
    visibleCount += PAGE_SIZE;
    renderMenu();
}

// ── Category tabs ─────────────────────────────────────────────────────────────
function buildCatTabs() {
    const tabs  = document.getElementById('catTabs');
    const total = CATEGORIES.reduce((s, c) => s + c.count, 0);
    document.getElementById('cnt-all').textContent = total;

    CATEGORIES.forEach(cat => {
        const btn      = document.createElement('button');
        btn.className  = 'cat-tab';
        btn.id         = 'tab-' + cat.id;
        btn.innerHTML  = h(cat.name) + ` <span class="cnt">${cat.count}</span>`;
        btn.onclick    = () => setCategory(cat.id);
        tabs.appendChild(btn);
    });
}

function setCategory(catId) {
    activeCatId  = catId;
    visibleCount = PAGE_SIZE;
    document.getElementById('menuSearch').value = '';
    searchQuery  = '';
    document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
    const tab = document.getElementById(catId === null ? 'tab-all' : 'tab-' + catId);
    if (tab) tab.classList.add('active');
    renderMenu();
}

function onSearch(val) {
    searchQuery  = val.trim();
    visibleCount = PAGE_SIZE;
    // Remove active state from tabs while searching
    document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
    renderMenu();
}

function openCustomizeModal(itemId) {
    const item = MENU_DATA.find(row => row.id === itemId);
    if (!item) return;
    customizingItem = item;

    const sizeOptions = (item.variants ?? []).map((variant, index) => `
      <label class="option-row">
        <span>${h(variant.label)}</span>
        <span>
          <input type="radio" name="customizeVariant" value="${variant.id}" ${index === 0 ? 'checked' : ''}>
          ${fmt(variant.price)}
        </span>
      </label>
    `).join('');

    const toppingOptions = (item.toppings ?? []).map(topping => `
      <label class="topping-option">
        <input type="checkbox" name="customizeTopping" value="${topping.id}" data-name="${h(topping.name)}">
        <span>${h(topping.name)}</span>
      </label>
    `).join('');

    const sizeSection = item.has_variants ? `
      <div style="margin-bottom:14px">
        <div style="font-weight:600">${h(TEXT.choose_size)}</div>
        <div class="option-list">${sizeOptions}</div>
      </div>
    ` : '';

    const toppingRule = item.max_toppings > 0
        ? `${TEXT.choose_exact_toppings} ${item.max_toppings} ${TEXT.toppings}.`
        : '';

    const toppingSection = item.has_toppings ? `
      <div style="margin-bottom:14px">
        <div style="font-weight:600">${h(TEXT.choose_toppings)}</div>
        <div id="customizeToppingHint" style="font-size:.82rem;color:var(--text-mid);margin-top:4px">${h(toppingRule)}</div>
        <div class="topping-grid">${toppingOptions}</div>
      </div>
    ` : '';

    document.getElementById('customizeTitle').textContent = item.name;
    document.getElementById('customizeBody').innerHTML = `
      ${sizeSection}
      ${toppingSection}
      <div>
        <div style="font-weight:600;margin-bottom:8px">${h(TEXT.qty)}</div>
        <input type="number" id="customizeQty" class="form-control" min="1" step="1" value="1">
      </div>
    `;
    document.getElementById('customizeModal').classList.remove('hidden');
    document.querySelectorAll('input[name="customizeTopping"]').forEach(el => {
        el.addEventListener('change', syncCustomizeToppingState);
    });
    syncCustomizeToppingState();
}

function closeCustomizeModal() {
    customizingItem = null;
    document.getElementById('customizeModal').classList.add('hidden');
}

function syncCustomizeToppingState() {
    if (!customizingItem || !customizingItem.has_toppings) return;

    const checkboxes = Array.from(document.querySelectorAll('input[name="customizeTopping"]'));
    const checkedCount = checkboxes.filter(el => el.checked).length;
    const max = customizingItem.max_toppings || 0;
    const min = customizingItem.min_toppings || 0;
    const hint = document.getElementById('customizeToppingHint');

    if (max > 0) {
        checkboxes.forEach(el => {
            el.disabled = !el.checked && checkedCount >= max;
        });
    }

    if (hint) {
        const baseText = max > 0 ? `${TEXT.choose_exact_toppings} ${max} ${TEXT.toppings}.` : '';
        hint.textContent = checkedCount >= max && max > 0
            ? `${baseText} ${TEXT.topping_limit_reached}`
            : baseText;
    }
}

function confirmCustomize() {
    if (!customizingItem) return;

    const qty = Math.max(1, parseInt(document.getElementById('customizeQty')?.value || '1', 10));
    const selectedVariantId = parseInt(document.querySelector('input[name=\"customizeVariant\"]:checked')?.value || '0', 10) || null;
    const selectedToppings = Array.from(document.querySelectorAll('input[name=\"customizeTopping\"]:checked'))
        .map(el => ({id: parseInt(el.value, 10), name: el.dataset.name || ''}));

    if (customizingItem.has_toppings) {
        if (selectedToppings.length < (customizingItem.min_toppings || 0) || selectedToppings.length > (customizingItem.max_toppings || 0)) {
            alert(TEXT.invalid_topping_count);
            return;
        }
    }

    const variant = selectedVariantId
        ? (customizingItem.variants || []).find(row => row.id === selectedVariantId)
        : null;
    const toppingNames = selectedToppings.map(row => row.name);
    const notes = toppingNames.length ? `Toppings: ${toppingNames.join(', ')}` : '';
    const key = [
        customizingItem.id,
        selectedVariantId || 0,
        selectedToppings.map(row => row.id).sort((a, b) => a - b).join('-')
    ].join(':');

    const existingQty = cart[key]?.qty ?? 0;
    cart[key] = {
        menu_item_id: customizingItem.id,
        variant_id: selectedVariantId,
        variant_label: variant?.label ?? '',
        name: variant ? `${customizingItem.name} - ${variant.label}` : customizingItem.name,
        base_name: customizingItem.name,
        price: Number(variant?.price ?? customizingItem.price),
        qty: existingQty + qty,
        notes,
    };

    appliedDiscount = 0;
    appliedPromoCode = '';
    loyaltyRedeemedPoints = 0;
    loyaltyRedeemedDiscount = 0;
    document.getElementById('promoMsg').textContent = '';
    document.getElementById('promoCode').value = '';
    const loyaltyMsg = document.getElementById('loyaltyMsg');
    if (loyaltyMsg) loyaltyMsg.textContent = '';
    renderCart();
    closeCustomizeModal();
    if (document.getElementById('checkoutForm').style.display !== 'none') {
        autoApplyPromo();
    }
}

// ── Cart ──────────────────────────────────────────────────────────────────────
function updateQty(id, delta, variantId = null) {
    const menuItem = MENU_DATA.find(item => item.id === id);
    if (!menuItem) return;
    const variant = variantId ? menuItem.variants.find(row => row.id === variantId) : null;
    const key = `${id}:${variantId ?? 0}`;

    let qty = (cart[key]?.qty ?? 0) + delta;
    if (qty < 0) qty = 0;

    if (qty > 0) {
        cart[key] = {
            menu_item_id: menuItem.id,
            variant_id: variantId,
            variant_label: variant?.label ?? '',
            name: variant ? `${menuItem.name} - ${variant.label}` : menuItem.name,
            base_name: menuItem.name,
            price: Number(variant?.price ?? menuItem.price),
            qty,
            notes: cart[key]?.notes ?? '',
        };
    } else {
        delete cart[key];
    }

    document.querySelectorAll('#qty_' + id + '_' + (variantId ?? 0)).forEach(el => {
        el.textContent = qty;
    });

    appliedDiscount = 0;
    appliedPromoCode = '';
    loyaltyRedeemedPoints = 0;
    loyaltyRedeemedDiscount = 0;
    document.getElementById('promoMsg').textContent = '';
    document.getElementById('promoCode').value = '';
    renderCart();
    if (document.getElementById('checkoutForm').style.display !== 'none') {
        autoApplyPromo();
    }
}

function renderCart() {
    const list     = document.getElementById('cartList');
    const btn      = document.getElementById('checkoutBtn');
    const keys     = Object.keys(cart);
    const subtotal = Object.values(cart).reduce((s, i) => s + i.price * i.qty, 0);
    const afterDisc = Math.max(0, subtotal - appliedDiscount - loyaltyRedeemedDiscount);
    const ppnAmount = PPN_RATE > 0 ? Math.round(afterDisc * PPN_RATE) / 100 : 0;
    const total     = afterDisc + ppnAmount + deliveryFee;

    if (keys.length === 0) {
        list.innerHTML = '<p style="color:var(--text-light);text-align:center">' + h(TEXT.no_items) + '</p>';
        btn.style.display = 'none';
        document.getElementById('checkoutForm').style.display = 'none';
        appliedDiscount = 0;
        appliedPromoCode = '';
        loyaltyRedeemedPoints = 0;
        loyaltyRedeemedDiscount = 0;
        document.getElementById('promoMsg').textContent  = '';
        document.getElementById('promoCode').value = '';
    } else {
        let rows = Object.entries(cart).map(([key, i]) =>
            `<div class="cart-item">
              <div class="cart-item-row">
                <span>${h(i.name)} x${i.qty}</span>
                <span style="white-space:nowrap">${fmt(i.price * i.qty)}</span>
              </div>
              <input type="text" class="item-notes-input"
                     placeholder="${h(TEXT.notes_placeholder)}"
                     value="${h(i.notes || '')}"
                     oninput="updateItemNote('${key}', this.value)">
            </div>`
        ).join('');
        if (appliedDiscount > 0) {
            rows += `<div class="cart-item" style="color:green">
              <span>🎉 ${h(TEXT.promo_discount)}</span><span>−${fmt(appliedDiscount)}</span>
            </div>`;
        }
        if (loyaltyRedeemedDiscount > 0) {
            rows += `<div class="cart-item" style="color:#2b6cb0">
              <span>⭐ ${h(TEXT.loyalty_discount)}</span><span>−${fmt(loyaltyRedeemedDiscount)}</span>
            </div>`;
        }
        if (ppnAmount > 0) {
            rows += `<div class="cart-item" style="color:var(--text-mid)">
              <span>${h(TEXT.vat)} (${PPN_RATE}%)</span><span>${fmt(ppnAmount)}</span>
            </div>`;
        }
        if (deliveryFee > 0) {
            const label = deliveryMeta?.courier
                ? `${TEXT.delivery_fee} (${deliveryMeta.courier.toUpperCase()} ${deliveryMeta.service || ''})`
                : TEXT.delivery_fee;
            rows += `<div class="cart-item" style="color:var(--text-mid)">
              <span>${h(label.trim())}</span><span>${fmt(deliveryFee)}</span>
            </div>`;
        }
        list.innerHTML    = rows;
        btn.style.display = 'flex';
    }
    document.getElementById('cartTotal').textContent = fmt(total);
}

function updateItemNote(key, value) {
    if (cart[key]) cart[key].notes = value;
}

function showCheckout() {
    if (!Object.keys(cart).length) return;
    document.getElementById('checkoutForm').style.display = 'block';
    document.getElementById('checkoutBtn').style.display  = 'none';
    onFulfillmentChange();
    loadSavedProfile();
    const firstEmpty = ['custName','custEmail','custWa'].find(id => !document.getElementById(id)?.value.trim());
    document.getElementById(firstEmpty || 'custName')?.focus();
    refreshLoyaltyStatus();
    autoApplyPromo();
}

async function calculateDeliveryFee() {
    const msg = document.getElementById('deliveryFeeMsg');
    const fulfillmentType = getFulfillmentType();
    const address = document.getElementById('custAddress')?.value.trim() || '';
    const postal = document.getElementById('custPostal')?.value.trim() || '';

    if (!msg || !RAJAONGKIR_ENABLED || fulfillmentType !== 'delivery') {
        return false;
    }

    if (!Object.keys(cart).length) {
        msg.style.color = 'var(--danger,#e53e3e)';
        msg.textContent = TEXT.add_items_first;
        return false;
    }

    if (!address || !postal) {
        msg.style.color = 'var(--danger,#e53e3e)';
        msg.textContent = TEXT.required_delivery;
        return false;
    }

    msg.style.color = 'var(--text-mid)';
    msg.textContent = TEXT.calculating_delivery;

    try {
        const synced = await syncCartToServer();
        if (!synced) {
            throw new Error('sync-failed');
        }

        const res = await fetch(BASE_URL + '/api/plugins/rajaongkir/calculate.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                branch_id: BRANCH_ID,
                session_id: SESSION_ID,
                address,
                postal_code: postal,
            }),
        });
        const data = await res.json().catch(() => null);

        if (data?.success) {
            deliveryFee = Number(data.data?.cost || 0);
            deliveryMeta = data.data || null;
            const etd = data.data?.etd ? `, ETD ${data.data.etd}` : '';
            const serviceLabel = [data.data?.courier?.toUpperCase?.(), data.data?.service].filter(Boolean).join(' ');
            msg.style.color = 'green';
            msg.textContent = `OK ${serviceLabel} ${fmt(deliveryFee)}${etd}`.trim();
            renderCart();
            return true;
        }

        deliveryFee = 0;
        deliveryMeta = null;
        msg.style.color = 'var(--danger,#e53e3e)';
        msg.textContent = data?.message || TEXT.delivery_unavailable;
        renderCart();
        return false;
    } catch {
        deliveryFee = 0;
        deliveryMeta = null;
        msg.style.color = 'var(--danger,#e53e3e)';
        msg.textContent = TEXT.delivery_unavailable;
        renderCart();
        return false;
    }
}

// ── Promo ─────────────────────────────────────────────────────────────────────
async function applyPromo() {
    const code = document.getElementById('promoCode').value.trim().toUpperCase();
    const msg  = document.getElementById('promoMsg');
    if (!code) { msg.textContent = ''; return; }
    if (!Object.keys(cart).length) {
        msg.style.color = 'var(--danger,#e53e3e)';
        msg.textContent = TEXT.add_items_first;
        return;
    }
    msg.style.color = 'var(--text-mid)';
    msg.textContent = TEXT.checking_promo;
    try {
        const synced = await syncCartToServer();
        if (!synced) {
            throw new Error('sync-failed');
        }
        const res  = await fetch(BASE_URL + '/api/cart/apply-promo.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({branch_id:BRANCH_ID, session_id:SESSION_ID, promo_code:code}),
        });
        const data = await res.json().catch(() => null);
        if (data?.success) {
            appliedDiscount = data.data.discount_amount;
            appliedPromoCode = data.data.promo_code || code;
            msg.style.color = 'green';
            msg.textContent = '✅ ' + data.message + ' ' + TEXT.discount_label + ' ' + fmt(appliedDiscount);
            renderCart();
            refreshLoyaltyStatus();
        } else {
            appliedDiscount = 0;
            appliedPromoCode = '';
            msg.style.color = 'var(--danger,#e53e3e)';
            msg.textContent = '❌ ' + (data?.message || TEXT.invalid_promo);
        }
    } catch {
        msg.style.color = 'var(--danger,#e53e3e)';
        msg.textContent = TEXT.server_failed;
    }
}

// ── Checkout ──────────────────────────────────────────────────────────────────
async function syncCartToServer() {
    if (!Object.keys(cart).length) {
        return false;
    }

    const customerPayload = getCustomerIdentityPayload();

    await fetch(BASE_URL + '/api/cart/clear.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({branch_id: BRANCH_ID, session_id: SESSION_ID}),
    }).catch(() => null);

    for (const [id, item] of Object.entries(cart)) {
        const res = await fetch(BASE_URL + '/api/cart/add.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                branch_id: BRANCH_ID,
                menu_item_id: item.menu_item_id,
                variant_id: item.variant_id || null,
                quantity: item.qty,
                notes: item.notes || '',
                session_id: SESSION_ID,
                ...customerPayload,
            }),
        });
        if (!res.ok) {
            return false;
        }
    }

    if (loyaltyRedeemedPoints > 0) {
        const loyaltyRes = await fetch(BASE_URL + '/api/loyalty/redeem.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                branch_id: BRANCH_ID,
                session_id: SESSION_ID,
                action: 'apply',
                points: loyaltyRedeemedPoints,
                ...customerPayload,
            }),
        }).catch(() => null);

        const loyaltyData = loyaltyRes ? await loyaltyRes.json().catch(() => null) : null;
        if (!loyaltyData?.success) {
            loyaltyRedeemedPoints = 0;
            loyaltyRedeemedDiscount = 0;
            return false;
        }

        loyaltyRedeemedPoints = loyaltyData.data?.cart_loyalty_points ?? loyaltyRedeemedPoints;
        loyaltyRedeemedDiscount = loyaltyData.data?.cart_loyalty_discount ?? loyaltyRedeemedDiscount;
    }

    return true;
}

async function autoApplyPromo() {
    const msg = document.getElementById('promoMsg');
    const codeInput = document.getElementById('promoCode');

    if (!Object.keys(cart).length || codeInput.value.trim() !== '') {
        return;
    }

    try {
        const synced = await syncCartToServer();
        if (!synced) {
            return;
        }

        const res = await fetch(BASE_URL + '/api/cart/auto-apply.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({branch_id: BRANCH_ID, session_id: SESSION_ID}),
        });
        const data = await res.json().catch(() => null);
        if (data?.success && (data.data?.discount_amount ?? 0) > 0) {
            appliedDiscount = data.data.discount_amount;
            appliedPromoCode = data.data.promo_code || '';
            if (appliedPromoCode) {
                codeInput.value = appliedPromoCode;
            }
            msg.style.color = 'green';
            msg.textContent = '✅ ' + (data.data?.promo_title || 'Auto promo') + ' ' + TEXT.discount_label + ' ' + fmt(appliedDiscount);
            renderCart();
            refreshLoyaltyStatus();
        }
    } catch {
        // Keep checkout usable even if auto-apply preview fails.
    }
}

async function placeOrder() {
    const fulfillmentType = getFulfillmentType();
    const name    = document.getElementById('custName').value.trim();
    const email   = document.getElementById('custEmail').value.trim();
    const wa      = document.getElementById('custWa').value.trim();
    const tableNumber = document.getElementById('custTableNumber').value.trim();
    const address = document.getElementById('custAddress').value.trim();
    const postal  = document.getElementById('custPostal').value.trim();
    const notes   = document.getElementById('custNotes').value.trim();

    if (!name || !wa) {
        alert(TEXT.required_pickup);
        return;
    }
    if (fulfillmentType === 'table' && !tableNumber) {
        alert(TEXT.required_table);
        return;
    }
    if (fulfillmentType === 'delivery' && (!address || !postal)) {
        alert(TEXT.required_delivery);
        return;
    }

    const btn = document.getElementById('placeOrderBtn');
    btn.disabled    = true;
    btn.textContent = TEXT.processing_order;
    btn.style.opacity = '0.75';

    try {
        const synced = await syncCartToServer();
        if (!synced) {
            throw new Error('sync-failed');
        }

        if (fulfillmentType === 'delivery' && RAJAONGKIR_ENABLED) {
            const deliveryReady = await calculateDeliveryFee();
            if (!deliveryReady) {
                throw new Error('delivery-fee-failed');
            }
        }

        const res = await fetch(BASE_URL + '/api/order/checkout.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                session_id: SESSION_ID, branch_id: BRANCH_ID,
                name, email, whatsapp: wa,
                fulfillment_type: fulfillmentType,
                table_number: tableNumber,
                address,
                postal_code: postal, notes,
                promo_code: document.getElementById('promoCode').value.trim().toUpperCase() || null,
            }),
        });
        const data = await res.json().catch(() => null);

        if (data?.success) {
            saveCustomerProfile(name, email, wa,
                document.getElementById('custAddress')?.value.trim() || '',
                document.getElementById('custPostal')?.value.trim()  || '');
            const paymentUrl = typeof data?.data?.payment?.url === 'string' ? data.data.payment.url : '';
            const paymentBtn = document.getElementById('orderPaymentBtn');
            const paymentHint = document.getElementById('orderPaymentHint');
            const customerDashboardBtn = document.getElementById('orderCustomerDashboardBtn');
            const loginContact = email || wa;
            const customerDashboardUrl = new URL(BASE_URL + '/customer/login.php', window.location.origin);
            const recentCustomerLogin = {
                contact: loginContact || '',
                orderNumber: data?.data?.order_number || '',
                name,
                branchId: BRANCH_ID,
                savedAt: new Date().toISOString(),
            };
            if (loginContact) {
                customerDashboardUrl.searchParams.set('contact', loginContact);
            }
            if (data?.data?.order_number) {
                customerDashboardUrl.searchParams.set('order_number', data.data.order_number);
            }
            try {
                localStorage.setItem('customerPortalRecentLogin', JSON.stringify(recentCustomerLogin));
            } catch (error) {
                console.warn('Failed to save customer portal shortcut.', error);
            }

            if (paymentRedirectTimer) {
                clearTimeout(paymentRedirectTimer);
                paymentRedirectTimer = null;
            }

            document.getElementById('orderSuccessMsg').textContent = paymentUrl
                ? `${TEXT.order_no} ${data.data.order_number}. ${TEXT.payment_ready}`
                : `${TEXT.order_no} ${data.data.order_number}. ${TEXT.order_processing}`;

            customerDashboardBtn.href = customerDashboardUrl.toString();
            customerDashboardBtn.classList.remove('hidden');

            if (paymentUrl) {
                paymentBtn.href = paymentUrl;
                paymentBtn.classList.remove('hidden');
                paymentHint.textContent = TEXT.payment_redirecting;
                paymentHint.style.display = 'block';
                paymentRedirectTimer = setTimeout(() => {
                    window.location.href = paymentUrl;
                }, 1200);
            } else {
                paymentBtn.href = '#';
                paymentBtn.classList.add('hidden');
                paymentHint.textContent = '';
                paymentHint.style.display = 'none';
            }

            document.getElementById('orderSuccess').classList.remove('hidden');
            cart = {};
            loyaltyRedeemedPoints = 0;
            loyaltyRedeemedDiscount = 0;
            renderCart();
        } else {
            alert(TEXT.create_failed + ' ' + (data?.message || TEXT.generic_error));
            btn.disabled  = false;
            btn.innerHTML = '🛒 ' + TEXT.create_order;
            btn.style.opacity = '1';
        }
    } catch {
        alert(TEXT.connection_failed);
        btn.disabled  = false;
        btn.innerHTML = '🛒 ' + TEXT.create_order;
        btn.style.opacity = '1';
    }
}

async function refreshLoyaltyStatus() {
    const box = document.getElementById('loyaltyBox');
    const info = document.getElementById('loyaltyInfo');
    const msg = document.getElementById('loyaltyMsg');
    const input = document.getElementById('loyaltyPointsInput');
    const clearBtn = document.getElementById('loyaltyClearBtn');

    if (!box || !info || !msg || !input || !clearBtn) return;

    box.style.display = 'block';
    info.textContent = TEXT.loyalty_loading;
    msg.textContent = '';

    try {
        const customerPayload = getCustomerIdentityPayload();
        const res = await fetch(BASE_URL + '/api/loyalty/status.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({branch_id: BRANCH_ID, session_id: SESSION_ID, ...customerPayload}),
        });
        const data = await res.json().catch(() => null);
        if (!data?.success) {
            box.style.display = 'none';
            return;
        }

        loyaltyBalancePoints = data.data?.balance_points ?? 0;
        loyaltyLifetimePoints = data.data?.lifetime_points ?? 0;
        loyaltyRedeemedPoints = data.data?.cart_loyalty_points ?? 0;
        loyaltyRedeemedDiscount = data.data?.cart_loyalty_discount ?? 0;
        loyaltyRedeemPointsUnit = data.data?.redeem_points_unit ?? 0;
        loyaltyRedeemValueAmount = data.data?.redeem_value_amount ?? 0;
        loyaltyMinRedeemPoints = data.data?.min_redeem_points ?? 0;

        input.value = loyaltyRedeemedPoints > 0 ? loyaltyRedeemedPoints : '';
        input.placeholder = `${TEXT.loyalty_balance}: ${loyaltyBalancePoints}`;
        info.textContent = `${TEXT.loyalty_balance}: ${loyaltyBalancePoints} poin`;

        if (loyaltyRedeemedPoints > 0) {
            msg.style.color = '#2b6cb0';
            msg.textContent = `${TEXT.loyalty_redeemed}: ${loyaltyRedeemedPoints} poin (${fmt(loyaltyRedeemedDiscount)})`;
            clearBtn.style.display = 'inline-flex';
        } else {
            msg.textContent = '';
            clearBtn.style.display = 'none';
        }

        renderCart();
    } catch {
        box.style.display = 'none';
    }
}

async function applyLoyaltyPoints() {
    const msg = document.getElementById('loyaltyMsg');
    const input = document.getElementById('loyaltyPointsInput');
    if (!msg || !input) return;

    if (!Object.keys(cart).length) {
        msg.style.color = 'var(--danger,#e53e3e)';
        msg.textContent = TEXT.add_items_first;
        return;
    }

    const points = Math.max(0, parseInt(input.value || '0', 10) || 0);

    try {
        const synced = await syncCartToServer();
        if (!synced) throw new Error('sync-failed');
        const customerPayload = getCustomerIdentityPayload();

        const res = await fetch(BASE_URL + '/api/loyalty/redeem.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({branch_id: BRANCH_ID, session_id: SESSION_ID, action: 'apply', points, ...customerPayload}),
        });
        const data = await res.json().catch(() => null);

        if (data?.success) {
            loyaltyRedeemedPoints = data.data?.cart_loyalty_points ?? 0;
            loyaltyRedeemedDiscount = data.data?.cart_loyalty_discount ?? 0;
            msg.style.color = 'green';
            msg.textContent = `✅ ${TEXT.loyalty_apply_ok} ${TEXT.loyalty_redeemed}: ${loyaltyRedeemedPoints} poin (${fmt(loyaltyRedeemedDiscount)})`;
            renderCart();
            refreshLoyaltyStatus();
        } else {
            msg.style.color = 'var(--danger,#e53e3e)';
            msg.textContent = '❌ ' + (data?.message || TEXT.server_failed);
        }
    } catch {
        msg.style.color = 'var(--danger,#e53e3e)';
        msg.textContent = TEXT.server_failed;
    }
}

async function clearLoyaltyPoints() {
    const msg = document.getElementById('loyaltyMsg');
    if (!msg) return;

    try {
        const customerPayload = getCustomerIdentityPayload();
        const res = await fetch(BASE_URL + '/api/loyalty/redeem.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({branch_id: BRANCH_ID, session_id: SESSION_ID, action: 'clear', ...customerPayload}),
        });
        const data = await res.json().catch(() => null);

        if (data?.success) {
            loyaltyRedeemedPoints = 0;
            loyaltyRedeemedDiscount = 0;
            msg.style.color = 'green';
            msg.textContent = '✅ ' + TEXT.loyalty_clear_ok;
            renderCart();
            refreshLoyaltyStatus();
        } else {
            msg.style.color = 'var(--danger,#e53e3e)';
            msg.textContent = '❌ ' + (data?.message || TEXT.server_failed);
        }
    } catch {
        msg.style.color = 'var(--danger,#e53e3e)';
        msg.textContent = TEXT.server_failed;
    }
}

function getCustomerIdentityPayload() {
    return {
        customer_name: document.getElementById('custName')?.value.trim() || '',
        customer_email: document.getElementById('custEmail')?.value.trim() || '',
        customer_whatsapp: document.getElementById('custWa')?.value.trim() || '',
    };
}

// ── Customer profile (localStorage) ──────────────────────────────────────────
const PROFILE_KEY     = 'customerProfile_v1';
const PROFILE_MAX_AGE = 90; // days

function loadSavedProfile() {
    try {
        const raw = localStorage.getItem(PROFILE_KEY);
        if (!raw) return;
        const p = JSON.parse(raw);
        const ageDays = (Date.now() - new Date(p.savedAt).getTime()) / 86400000;
        if (ageDays > PROFILE_MAX_AGE) { localStorage.removeItem(PROFILE_KEY); return; }

        const nameEl    = document.getElementById('custName');
        const emailEl   = document.getElementById('custEmail');
        const waEl      = document.getElementById('custWa');
        const addrEl    = document.getElementById('custAddress');
        const postalEl  = document.getElementById('custPostal');
        if (p.name     && nameEl)   nameEl.value   = p.name;
        if (p.email    && emailEl)  emailEl.value  = p.email;
        if (p.whatsapp && waEl)     waEl.value     = p.whatsapp;
        if (p.address  && addrEl)   addrEl.value   = p.address;
        if (p.postal   && postalEl) postalEl.value = p.postal;

        if (p.name) {
            const banner = document.getElementById('profileBanner');
            const label  = document.getElementById('profileBannerName');
            if (banner && label) {
                label.textContent = TEXT.profile_hello.replace('{name}', p.name);
                banner.style.display = 'flex';
            }
        }
    } catch {}
}

function clearSavedProfile() {
    try { localStorage.removeItem(PROFILE_KEY); } catch {}
    document.getElementById('profileBanner').style.display = 'none';
    ['custName','custEmail','custWa','custAddress','custPostal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('custName')?.focus();
}

function saveCustomerProfile(name, email, whatsapp, address, postal) {
    try {
        localStorage.setItem(PROFILE_KEY, JSON.stringify({
            name, email, whatsapp, address, postal,
            savedAt: new Date().toISOString(),
        }));
    } catch {}
}

// ── Init ──────────────────────────────────────────────────────────────────────
buildCatTabs();
renderMenu();
</script>
</body>
</html>

    
