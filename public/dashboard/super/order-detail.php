<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryRepository.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryClient.php';
require_once BASE_PATH . '/plugins/gosend-delivery/GoSendDeliveryService.php';

use App\Helpers\{Auth, View, Currency, Csrf};
use App\Models\{OrderModel, BranchModel};
use App\Config\Database;
use App\Plugin\PluginLoader;

Auth::startSession();
Auth::requireRole('super_admin');

$orderId    = (int)($_GET['id'] ?? 0);
$orderModel = new OrderModel();
$order      = $orderId ? $orderModel->getWithItems($orderId) : null;

if (!$order) {
    header('Location: ' . BASE_URL . '/dashboard/super/orders.php');
    exit;
}

$message = '';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_status') {
            $orderModel->updateStatus($orderId, $_POST['order_status'] ?? 'pending', (int)Auth::user()['id']);
            $message = 'Status diperbarui.';
        } elseif ($action === 'update_payment') {
            $orderModel->updatePayment($orderId, $_POST['payment_status'] ?? 'unpaid', (int)Auth::user()['id']);
            $message = 'Pembayaran diperbarui.';
        } elseif ($action === 'update_admin_notes') {
            $orderModel->updateAdminNotes($orderId, $_POST['admin_notes'] ?? '');
            $message = 'Catatan admin disimpan.';
        } elseif ($action === 'gosend_request_pickup' && PluginLoader::isLoaded('gosend-delivery')) {
            $result = (new GoSendDeliveryService(new GoSendDeliveryRepository()))->requestPickupForOrder($orderId);
            $message = (string)($result['message'] ?? 'Pickup GoSend diproses.');
        } elseif ($action === 'gosend_refresh_status' && PluginLoader::isLoaded('gosend-delivery')) {
            $result = (new GoSendDeliveryService(new GoSendDeliveryRepository()))->refreshStatusForOrder($orderId);
            $message = (string)($result['message'] ?? 'Status GoSend di-refresh.');
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
    $order = $orderModel->getWithItems($orderId);
}

$branchModel = new BranchModel();
$currency    = $branchModel->getCurrency((int)$order['branch_id']);
$timezone    = $branchModel->getTimezone((int)$order['branch_id']);
$tzLabel     = (new \DateTime('now', new \DateTimeZone($timezone)))->format('T (P)');
$fulfillmentType = (string)($order['fulfillment_type'] ?? 'delivery');
$fulfillmentLabel = match ($fulfillmentType) {
    'pickup' => 'Ambil di toko',
    'table' => 'Delivery ke meja',
    default => 'Delivery ke alamat',
};

$db   = Database::getInstance();
$stmt = $db->prepare(
    'SELECT osl.*, u.name AS changed_by_name
     FROM order_status_logs osl
     LEFT JOIN users u ON osl.changed_by = u.id
     WHERE osl.order_id = ?
     ORDER BY osl.created_at DESC'
);
$stmt->execute([$orderId]);
$logs = $stmt->fetchAll();

$gosendStatus = null;
if (PluginLoader::isLoaded('gosend-delivery') && $fulfillmentType === 'delivery') {
    $gosendStatus = (new GoSendDeliveryService(new GoSendDeliveryRepository()))->getDeliveryOrderStatus($orderId) ?: null;
}

ob_start();
?>
<div style="margin-bottom:16px;display:flex;align-items:center;gap:12px">
  <a href="<?= BASE_URL ?>/dashboard/super/orders.php" class="btn btn-outline">&larr; Semua Order</a>
  <h2 style="margin:0"><?= htmlspecialchars($order['order_number']) ?></h2>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
  <div>
    <!-- Order Info -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-title">📦 Detail Order</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:.9rem">
        <div><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name'] ?? '-') ?></div>
        <div><strong>WhatsApp:</strong> <?= htmlspecialchars($order['customer_wa'] ?? '-') ?></div>
        <div><strong>Email:</strong> <?= htmlspecialchars($order['customer_email'] ?? '-') ?></div>
        <div><strong>Channel:</strong> <span class="badge <?= ($order['channel'] ?? '') === 'whatsapp' ? 'badge-green' : 'badge-blue' ?>"><?= htmlspecialchars($order['channel'] ?? '-') ?></span></div>
        <div><strong>Cabang:</strong> <?= htmlspecialchars($order['branch_name'] ?? '-') ?></div>
        <div><strong>Metode:</strong> <?= htmlspecialchars($fulfillmentLabel) ?></div>
        <?php if ($fulfillmentType === 'table'): ?>
        <div><strong>Nomor Meja:</strong> <?= htmlspecialchars((string)($order['table_number'] ?? '-')) ?></div>
        <?php endif; ?>
        <div><strong>Tanggal:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> <span style="color:var(--text-light)"><?= htmlspecialchars($tzLabel) ?></span></div>
        <?php if ($fulfillmentType === 'delivery' && !empty($order['delivery_address'])): ?>
        <div style="grid-column:1/-1"><strong>Alamat:</strong> <?= htmlspecialchars($order['delivery_address']) ?></div>
        <?php endif; ?>
        <?php if ($fulfillmentType === 'delivery' && !empty($order['postal_code'])): ?>
        <div><strong>Kode Pos:</strong> <?= htmlspecialchars($order['postal_code']) ?></div>
        <?php endif; ?>
        <?php if (!empty($order['notes'])): ?>
        <div style="grid-column:1/-1"><strong>Catatan:</strong> <?= htmlspecialchars($order['notes']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Items -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-title">🛒 Item Pesanan</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Menu</th><th>Qty</th><th>Harga Satuan</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach ($order['items'] as $item): ?>
          <tr>
            <td>
              <?= htmlspecialchars($item['menu_name']) ?>
              <?php if (!empty($item['variant_label'])): ?><br><small style="color:var(--coffee-brown);font-weight:600"><?= htmlspecialchars($item['variant_label']) ?></small><?php endif; ?>
              <?php if (!empty($item['notes'])): ?><br><small style="color:var(--text-light)"><?= htmlspecialchars($item['notes']) ?></small><?php endif; ?>
            </td>
            <td><?= $item['quantity'] ?></td>
            <td><?= Currency::format((float)$item['unit_price'], $currency) ?></td>
            <td><?= Currency::format((float)$item['subtotal'], $currency) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="3" style="text-align:right;font-weight:600">Subtotal:</td><td><?= Currency::format((float)($order['subtotal'] ?? 0), $currency) ?></td></tr>
            <?php if (($order['discount_amount'] ?? 0) > 0): ?>
            <tr><td colspan="3" style="text-align:right">Diskon:</td><td>- <?= Currency::format((float)$order['discount_amount'], $currency) ?></td></tr>
            <?php endif; ?>
            <?php if ((float)($order['ppn_amount'] ?? 0) > 0): ?>
            <tr><td colspan="3" style="text-align:right">PPN (<?= (float)$order['ppn_rate'] ?>%):</td><td><?= Currency::format((float)$order['ppn_amount'], $currency) ?></td></tr>
            <?php endif; ?>
            <?php if ((float)($order['delivery_fee'] ?? 0) > 0): ?>
            <tr>
              <td colspan="3" style="text-align:right">
                Biaya Delivery
                <?php if (!empty($order['delivery_courier']) || !empty($order['delivery_service'])): ?>
                <small style="color:var(--text-light)">
                  (<?= htmlspecialchars(trim(strtoupper((string)($order['delivery_courier'] ?? '')) . ' ' . (string)($order['delivery_service'] ?? ''))) ?><?= !empty($order['delivery_etd']) ? ' · ETD ' . htmlspecialchars((string)$order['delivery_etd']) : '' ?>)
                </small>
                <?php endif; ?>
              </td>
              <td><?= Currency::format((float)($order['delivery_fee'] ?? 0), $currency) ?></td>
            </tr>
            <?php endif; ?>
            <tr><td colspan="3" style="text-align:right;font-weight:700;font-size:1.05rem">Total:</td><td style="font-weight:700"><?= Currency::format((float)$order['total_amount'], $currency) ?></td></tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Status Logs -->
    <div class="card">
      <div class="card-title">📋 Log Status</div>
      <?php if (empty($logs)): ?>
        <p style="color:var(--text-light)">Belum ada log status.</p>
      <?php else: ?>
      <?php foreach ($logs as $log): ?>
      <div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:.85rem">
        <strong><?= htmlspecialchars($log['new_status']) ?></strong>
        <?php if ($log['old_status']): ?> ← <?= htmlspecialchars($log['old_status']) ?><?php endif; ?>
        <span style="color:var(--text-light);margin-left:10px"><?= date('d/m/y H:i', strtotime($log['created_at'])) ?></span>
        <?php if ($log['changed_by_name']): ?>
          <span style="color:var(--text-light)"> · <?= htmlspecialchars($log['changed_by_name']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Actions -->
  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-title">⚡ Update Status</div>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="update_status">
        <div class="form-group">
          <label class="form-label" for="order_status">Status Order</label>
          <select id="order_status" name="order_status" class="form-control">
            <?php foreach (['pending','confirmed','processing','ready','delivered','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= ($order['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" style="width:100%;justify-content:center">Update Status</button>
      </form>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-title">📝 Catatan Admin</div>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="update_admin_notes">
        <div class="form-group">
          <label class="form-label" for="admin_notes">Catatan internal (tidak terlihat customer)</label>
          <textarea id="admin_notes" name="admin_notes" class="form-control" rows="4"
            placeholder="Contoh: customer sudah konfirmasi via telepon, siap kirim jam 14.00..."
          ><?= htmlspecialchars($order['admin_notes'] ?? '') ?></textarea>
        </div>
        <button class="btn btn-primary" style="width:100%;justify-content:center">Simpan Catatan</button>
      </form>
    </div>

    <div class="card">
      <div class="card-title">💳 Pembayaran</div>
      <div style="margin-bottom:12px">
        Status: <span class="badge <?= ($order['payment_status'] ?? '') === 'paid' ? 'badge-green' : 'badge-gray' ?>">
          <?= htmlspecialchars($order['payment_status'] ?? 'unpaid') ?>
        </span>
      </div>
      <?php if (!empty($order['paid_at'])): ?>
        <div style="font-size:.85rem;color:var(--text-light);margin-bottom:12px">
          Paid: <?= date('d/m/y H:i', strtotime($order['paid_at'])) ?>
        </div>
      <?php endif; ?>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="update_payment">
        <div class="form-group">
          <label class="form-label" for="payment_status">Status Pembayaran</label>
          <select id="payment_status" name="payment_status" class="form-control">
            <option value="unpaid" <?= ($order['payment_status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
            <option value="paid"   <?= ($order['payment_status'] ?? '') === 'paid'   ? 'selected' : '' ?>>Paid</option>
          </select>
        </div>
        <button class="btn btn-primary" style="width:100%;justify-content:center">Update Payment</button>
      </form>
    </div>
    <?php if ($fulfillmentType === 'delivery' && PluginLoader::isLoaded('gosend-delivery')): ?>
    <div class="card" style="margin-top:20px">
      <div class="card-title">GoSend Delivery</div>
      <div style="font-size:.85rem;line-height:1.75;margin-bottom:12px">
        Status Delivery: <strong><?= htmlspecialchars((string)($gosendStatus['delivery_status'] ?? 'belum dibuat')) ?></strong><br>
        External Ref: <code><?= htmlspecialchars((string)($gosendStatus['external_ref'] ?? '-')) ?></code><br>
        <?php if (!empty($gosendStatus['tracking_url'])): ?>
        Tracking: <a href="<?= htmlspecialchars((string)$gosendStatus['tracking_url']) ?>" target="_blank" rel="noopener">Buka tracking</a><br>
        <?php endif; ?>
        <?php if (!empty($gosendStatus['latest_note'])): ?>
        Catatan: <?= htmlspecialchars((string)$gosendStatus['latest_note']) ?>
        <?php endif; ?>
      </div>
      <form method="POST" style="margin-bottom:10px">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="gosend_request_pickup">
        <button class="btn btn-primary" style="width:100%;justify-content:center">Request Pickup</button>
      </form>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="gosend_refresh_status">
        <button class="btn btn-outline" style="width:100%;justify-content:center">Refresh Status GoSend</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
$content = ob_get_clean();
echo View::renderLayout('Order ' . htmlspecialchars($order['order_number']), $content, 'super_admin');
