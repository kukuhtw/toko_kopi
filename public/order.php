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
<!-- Rest of existing page content is unchanged in the repository version. -->
<?php require __FILE__; ?>
