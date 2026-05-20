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
        c.id,
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

$loyaltyBranchStmt = $db->prepare(
    'SELECT
        b.name AS branch_name,
        lpa.branch_id,
        lpa.balance_points,
        lpa.lifetime_points,
        lpa.updated_at
     FROM loyalty_point_accounts lpa
     JOIN branches b ON b.id = lpa.branch_id
     WHERE lpa.customer_id = ?
     ORDER BY lpa.updated_at DESC, b.name ASC'
);
$loyaltyBranchStmt->execute([$customerId]);
$loyaltyAccounts = $loyaltyBranchStmt->fetchAll();

$loyaltySummaryStmt = $db->prepare(
    'SELECT
        COALESCE(SUM(balance_points), 0) AS total_balance_points,
        COALESCE(SUM(lifetime_points), 0) AS total_lifetime_points
     FROM loyalty_point_accounts
     WHERE customer_id = ?'
);
$loyaltySummaryStmt->execute([$customerId]);
$loyaltySummary = $loyaltySummaryStmt->fetch() ?: [];

$txStmt = $db->prepare(
    'SELECT
        lpt.*,
        b.name AS branch_name,
        o.order_number,
        o.order_status,
        o.payment_status
     FROM loyalty_point_transactions lpt
     LEFT JOIN branches b ON b.id = lpt.branch_id
     LEFT JOIN orders o ON o.id = lpt.order_id
     WHERE lpt.customer_id = ?
     ORDER BY lpt.created_at DESC, lpt.id DESC
     LIMIT 20'
);
$txStmt->execute([$customerId]);
$loyaltyTransactions = $txStmt->fetchAll();

$ordersStmt = $db->prepare(
    'SELECT
        o.*,
        b.name AS branch_name
     FROM orders o
     JOIN branches b ON b.id = o.branch_id
     WHERE o.customer_id = ?
     ORDER BY o.created_at DESC
     LIMIT 20'
);
$ordersStmt->execute([$customerId]);
$orders = $ordersStmt->fetchAll();

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

function customerStatusBadgeClass(string $type, ?string $status): string
{
    $normalized = strtolower(trim((string)$status));

    if ($type === 'payment') {
        return match ($normalized) {
            'paid', 'settlement', 'success', 'completed' => 'badge-success',
            'pending', 'waiting', 'unpaid' => 'badge-warning',
            'failed', 'expired', 'cancelled', 'canceled', 'refunded' => 'badge-danger',
            default => 'badge-neutral',
        };
    }

    return match ($normalized) {
        'delivered', 'completed', 'done', 'ready', 'picked_up' => 'badge-success',
        'processing', 'confirmed', 'preparing', 'on_delivery', 'shipped' => 'badge-info',
        'pending', 'new' => 'badge-warning',
        'cancelled', 'canceled', 'failed', 'rejected' => 'badge-danger',
        default => 'badge-neutral',
    };
}
customerPortalRenderStart([
    'title' => 'Customer Portal - ' . APP_NAME,
    'heading' => (string)($profile['name'] ?: $profile['identifier'] ?: $customer['name']),
    'subtitle' => 'Cek loyalty point, histori order, dan ringkasan aktivitas customer Anda di satu tempat.',
    'active' => 'overview',
    'extra_styles' => <<<CSS
    .stat-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:20px; }
    .stat-card { background:#fff; border:1px solid var(--border); border-radius:18px; padding:18px; }
    .stat-card small { color:var(--text-light); display:block; margin-bottom:8px; }
    .stat-card strong { font-size:1.45rem; color:var(--coffee-dark); }
    .content-grid { display:grid; grid-template-columns:1.1fr .9fr; gap:20px; }
    .stack { display:grid; gap:20px; }
    .timeline { display:grid; gap:10px; }
    .timeline-item { border-bottom:1px solid var(--border); padding-bottom:10px; }
    .timeline-item:last-child { border-bottom:none; padding-bottom:0; }
    .loyalty-branch-list { display:grid; gap:10px; }
    .loyalty-branch-item { border:1px solid var(--border); border-radius:14px; padding:12px 14px; }
    @media (max-width: 980px) {
      .stat-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
      .content-grid { grid-template-columns:1fr; }
    }
    @media (max-width: 640px) {
      .stat-grid { grid-template-columns:1fr; }
    }
CSS,
]);
?>

  <div class="stat-grid">
    <div class="stat-card" id="overview">
      <small>Total Order</small>
      <strong><?= number_format((int)($stats['total_orders'] ?? 0)) ?></strong>
    </div>
    <div class="stat-card">
      <small>Total Belanja</small>
      <strong>Rp <?= number_format((float)($stats['total_spent'] ?? 0), 0, ',', '.') ?></strong>
    </div>
    <div class="stat-card">
      <small>Total Saldo Poin</small>
      <strong><?= number_format((int)($loyaltySummary['total_balance_points'] ?? 0)) ?></strong>
    </div>
    <div class="stat-card">
      <small>Lifetime Points</small>
      <strong><?= number_format((int)($loyaltySummary['total_lifetime_points'] ?? 0)) ?></strong>
    </div>
  </div>

  <div class="content-grid">
    <div class="stack">
      <div class="card-panel" id="orders">
        <h2>Riwayat Order</h2>
        <?php if (!empty($orders)): ?>
          <table class="table-lite">
            <thead>
              <tr>
                <th>Order</th>
                <th>Cabang</th>
                <th>Status</th>
                <th>Total</th>
                <th>Tanggal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
                <tr>
                  <td>
                    <a href="<?= BASE_URL ?>/customer/order-detail.php?id=<?= (int)$order['id'] ?>" style="font-weight:700">
                      <?= htmlspecialchars((string)$order['order_number']) ?>
                    </a>
                    <div class="muted"><?= htmlspecialchars((string)($order['notes'] ?: '-')) ?></div>
                  </td>
                  <td><?= htmlspecialchars((string)$order['branch_name']) ?></td>
                  <td>
                    <span class="status-badge <?= customerStatusBadgeClass('order', (string)$order['order_status']) ?>">
                      <?= htmlspecialchars((string)$order['order_status']) ?>
                    </span>
                    <div style="margin-top:6px">
                      <span class="status-badge <?= customerStatusBadgeClass('payment', (string)$order['payment_status']) ?>">
                        <?= htmlspecialchars((string)$order['payment_status']) ?>
                      </span>
                    </div>
                  </td>
                  <td>Rp <?= number_format((float)$order['total_amount'], 0, ',', '.') ?></td>
                  <td><?= date('d/m/Y H:i', strtotime((string)$order['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="muted">Belum ada histori order untuk customer ini.</div>
        <?php endif; ?>
      </div>

      <div class="card-panel" id="loyalty">
        <h2>Riwayat Loyalty Point</h2>
        <div class="timeline">
          <?php foreach ($loyaltyTransactions as $tx): ?>
            <?php $points = (int)($tx['points'] ?? 0); ?>
            <div class="timeline-item">
              <div class="row-head">
                <div>
                  <strong><?= htmlspecialchars((string)($tx['description'] ?: ucfirst((string)$tx['transaction_type']))) ?></strong>
                  <div class="muted">
                    <?= htmlspecialchars((string)($tx['branch_name'] ?? '-')) ?>
                    <?php if (!empty($tx['order_number'])): ?>
                      · <?= htmlspecialchars((string)$tx['order_number']) ?>
                    <?php endif; ?>
                  </div>
                </div>
                <div style="text-align:right">
                  <div style="font-weight:700;color:<?= $points >= 0 ? '#2f855a' : '#2b6cb0' ?>">
                    <?= $points >= 0 ? '+' : '' ?><?= number_format($points) ?> poin
                  </div>
                  <div class="muted"><?= date('d/m/Y H:i', strtotime((string)$tx['created_at'])) ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($loyaltyTransactions)): ?>
            <div class="muted">Belum ada transaksi loyalty untuk customer ini.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="stack">
      <div class="card-panel" id="profile">
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
          <small>Order Terakhir</small>
          <strong><?= !empty($stats['last_order_at']) ? date('d/m/Y H:i', strtotime((string)$stats['last_order_at'])) : '-' ?></strong>
        </div>
      </div>

      <div class="card-panel">
        <h2>Loyalty per Cabang</h2>
        <div class="loyalty-branch-list">
          <?php foreach ($loyaltyAccounts as $account): ?>
            <div class="loyalty-branch-item">
              <div class="row-head">
                <div>
                  <strong><?= htmlspecialchars((string)$account['branch_name']) ?></strong>
                  <div class="muted">Update <?= date('d/m/Y H:i', strtotime((string)$account['updated_at'])) ?></div>
                </div>
                <span class="badge-soft"><?= number_format((int)$account['balance_points']) ?> poin</span>
              </div>
              <div class="muted" style="margin-top:8px">Lifetime <?= number_format((int)$account['lifetime_points']) ?> poin</div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($loyaltyAccounts)): ?>
            <div class="muted">Belum ada akun loyalty aktif di cabang mana pun.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card-panel">
        <h2>Preferensi</h2>
        <div class="mini-box">
          <small>Favorite Items</small>
          <strong><?= !empty($favoriteItemNames) ? htmlspecialchars(implode(', ', $favoriteItemNames)) : '-' ?></strong>
        </div>
        <div class="mini-box" style="margin-top:12px">
          <small>Catatan Customer</small>
          <strong><?= htmlspecialchars((string)($profile['notes'] ?: '-')) ?></strong>
        </div>
      </div>
    </div>
  </div>
<?php customerPortalRenderEnd(); ?>
