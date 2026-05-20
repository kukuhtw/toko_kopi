<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Config/config.php';
require_once __DIR__ . '/_portal.php';

use App\Config\Database;
use App\Helpers\CustomerAuth;

CustomerAuth::startSession();
CustomerAuth::requireLogin();

$customer = CustomerAuth::customer();
$customerId = (int)($customer['id'] ?? 0);

$db = Database::getInstance();

$profileStmt = $db->prepare(
    'SELECT name, identifier
     FROM customers
     WHERE id = ?
     LIMIT 1'
);
$profileStmt->execute([$customerId]);
$profile = $profileStmt->fetch() ?: [];

$ordersStmt = $db->prepare(
    'SELECT
        o.*,
        b.name AS branch_name
     FROM orders o
     JOIN branches b ON b.id = o.branch_id
     WHERE o.customer_id = ?
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 50'
);
$ordersStmt->execute([$customerId]);
$orders = $ordersStmt->fetchAll();

function customerOrdersStatusBadgeClass(string $type, ?string $status): string
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
    'title' => 'Orders - Customer Portal',
    'heading' => (string)($profile['name'] ?: $profile['identifier'] ?: $customer['name']),
    'subtitle' => 'Lihat semua histori order Anda, lengkap dengan status order dan pembayaran.',
    'active' => 'orders',
    'extra_styles' => <<<CSS
    .table-lite th, .table-lite td { padding:12px 10px; }
    @media (max-width: 740px) {
      .table-wrap { overflow-x:auto; }
    }
CSS,
]);
?>

  <div class="card-panel">
    <h2>Semua Riwayat Order</h2>
    <?php if (!empty($orders)): ?>
      <div class="table-wrap">
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
                  <span class="status-badge <?= customerOrdersStatusBadgeClass('order', (string)$order['order_status']) ?>">
                    <?= htmlspecialchars((string)$order['order_status']) ?>
                  </span>
                  <div style="margin-top:6px">
                    <span class="status-badge <?= customerOrdersStatusBadgeClass('payment', (string)$order['payment_status']) ?>">
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
      </div>
    <?php else: ?>
      <div class="muted">Belum ada histori order untuk customer ini.</div>
    <?php endif; ?>
  </div>
<?php customerPortalRenderEnd(); ?>
