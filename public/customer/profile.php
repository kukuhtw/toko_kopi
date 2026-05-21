<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Config/config.php';
require_once __DIR__ . '/_portal.php';

use App\Config\Database;
use App\Helpers\CustomerAuth;
use App\Models\MenuModel;

CustomerAuth::startSession();
CustomerAuth::requireLogin();

$customer = CustomerAuth::customer();
$customerId = (int)($customer['id'] ?? 0);

$db = Database::getInstance();

$profileStmt = $db->prepare(
    'SELECT
        c.name,
        c.identifier,
        c.email,
        c.whatsapp,
        cp.address,
        cp.postal_code,
        cp.city,
        cp.favorite_items,
        cp.order_count,
        cp.notes
     FROM customers c
     LEFT JOIN customer_profiles cp ON cp.customer_id = c.id
     WHERE c.id = ?
     LIMIT 1'
);
$profileStmt->execute([$customerId]);
$profile = $profileStmt->fetch() ?: [];

$statsStmt = $db->prepare(
    'SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_spent,
        MAX(created_at) AS last_order_at
     FROM orders
     WHERE customer_id = ?'
);
$statsStmt->execute([$customerId]);
$stats = $statsStmt->fetch() ?: [];

$favoriteItems = [];
if (!empty($profile['favorite_items'])) {
    $favoriteItems = json_decode((string)$profile['favorite_items'], true) ?: [];
}

$favoriteItemNames = [];
if (!empty($favoriteItems)) {
    $favoriteItemIds = array_values(array_unique(array_filter(array_map('intval', $favoriteItems), static fn(int $id): bool => $id > 0)));
    if (!empty($favoriteItemIds)) {
        $menuModel = new MenuModel();
        $placeholders = implode(',', array_fill(0, count($favoriteItemIds), '?'));
        $rows = $menuModel->query(
            "SELECT id, name FROM menu_items WHERE id IN ({$placeholders}) ORDER BY name ASC",
            $favoriteItemIds
        )->fetchAll();
        $favoriteItemNames = array_map(static fn(array $row): string => (string)($row['name'] ?? ''), $rows);
        $favoriteItemNames = array_values(array_filter($favoriteItemNames, static fn(string $name): bool => $name !== ''));
    }
}
customerPortalRenderStart([
    'title' => 'Profile - Customer Portal',
    'heading' => (string)($profile['name'] ?: $profile['identifier'] ?: $customer['name']),
    'subtitle' => 'Lihat profil customer, preferensi menu, dan ringkasan akun Anda.',
    'active' => 'profile',
    'extra_styles' => <<<CSS
    .content-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    @media (max-width: 900px) {
      .content-grid, .mini-grid { grid-template-columns:1fr; }
    }
    @media (max-width: 640px) {
      .content-grid { gap:16px; }
    }
CSS,
]);
?>

  <div class="content-grid">
    <div class="card-panel">
      <h2>Profil Customer</h2>
      <div class="mini-grid">
        <div class="mini-box">
          <small>Email</small>
          <strong><?= htmlspecialchars((string)($profile['email'] ?: '-')) ?></strong>
        </div>
        <div class="mini-box">
          <small>WhatsApp</small>
          <strong><?= htmlspecialchars((string)($profile['whatsapp'] ?: '-')) ?></strong>
        </div>
        <div class="mini-box">
          <small>Kota</small>
          <strong><?= htmlspecialchars((string)($profile['city'] ?: '-')) ?></strong>
        </div>
        <div class="mini-box">
          <small>Kode Pos</small>
          <strong><?= htmlspecialchars((string)($profile['postal_code'] ?: '-')) ?></strong>
        </div>
      </div>
      <div class="mini-box" style="margin-top:12px">
        <small>Alamat</small>
        <strong><?= htmlspecialchars((string)($profile['address'] ?: '-')) ?></strong>
      </div>
      <div class="mini-box" style="margin-top:12px">
        <small>Catatan Customer</small>
        <strong><?= htmlspecialchars((string)($profile['notes'] ?: '-')) ?></strong>
      </div>
    </div>

    <div class="card-panel">
      <h2>Preferensi dan Aktivitas</h2>
      <div class="mini-grid">
        <div class="mini-box">
          <small>Total Order</small>
          <strong><?= number_format((int)($stats['total_orders'] ?? 0)) ?></strong>
        </div>
        <div class="mini-box">
          <small>Total Belanja</small>
          <strong>Rp <?= number_format((float)($stats['total_spent'] ?? 0), 0, ',', '.') ?></strong>
        </div>
      </div>
      <div class="mini-box" style="margin-top:12px">
        <small>Order Terakhir</small>
        <strong><?= !empty($stats['last_order_at']) ? date('d/m/Y H:i', strtotime((string)$stats['last_order_at'])) : '-' ?></strong>
      </div>
      <div class="mini-box" style="margin-top:12px">
        <small>Favorite Items</small>
        <strong><?= !empty($favoriteItemNames) ? htmlspecialchars(implode(', ', $favoriteItemNames)) : '-' ?></strong>
      </div>
      <div class="muted" style="margin-top:12px">
        Preferensi menu ditarik dari profil customer yang sudah tersimpan, jadi tetap konsisten dengan data CRM dan histori order.
      </div>
    </div>
  </div>
<?php customerPortalRenderEnd(); ?>
