<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Config\Database;
use App\Helpers\{Auth, View};
use App\Models\CustomerModel;

Auth::startSession();
Auth::requireLogin();

$user = Auth::user();
$branchId = (int)($user['branch_id'] ?? 0);
if (!$branchId) {
    http_response_code(403);
    exit('Access denied');
}

$db = Database::getInstance();
$customerModel = new CustomerModel();
$query = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$selectedCustomerId = max(0, (int)($_GET['customer_id'] ?? 0));

$rawLike = '%' . $query . '%';
$normalizedEmail = CustomerModel::normalizeEmail($query);
$normalizedWhatsapp = $query !== '' ? $customerModel->normalizeWhatsApp($query) : '';

$where = [
    '(EXISTS (SELECT 1 FROM crm_notification_logs l WHERE l.branch_id = ? AND l.customer_id = c.id)
      OR EXISTS (SELECT 1 FROM loyalty_point_accounts lpa WHERE lpa.branch_id = ? AND lpa.customer_id = c.id)
      OR EXISTS (SELECT 1 FROM orders o WHERE o.branch_id = ? AND o.customer_id = c.id))',
];
$params = [$branchId, $branchId, $branchId];

if ($query !== '') {
    $where[] = '(c.name LIKE ? OR c.identifier LIKE ? OR c.email LIKE ? OR c.whatsapp LIKE ?'
        . ($normalizedEmail !== '' ? ' OR LOWER(c.email) = ?' : '')
        . ($normalizedWhatsapp !== '' ? ' OR c.whatsapp = ?' : '')
        . ')';
    array_push($params, $rawLike, $rawLike, $rawLike, $rawLike);
    if ($normalizedEmail !== '') {
        $params[] = $normalizedEmail;
    }
    if ($normalizedWhatsapp !== '') {
        $params[] = $normalizedWhatsapp;
    }
}

$listSql = '
    SELECT
        c.id,
        c.name,
        c.identifier,
        c.email,
        c.whatsapp,
        notif.last_notification_at,
        COALESCE(notif.total_notifications, 0) AS total_notifications,
        COALESCE(notif.sent_notifications, 0) AS sent_notifications,
        COALESCE(lpa.balance_points, 0) AS balance_points,
        COALESCE(lpa.lifetime_points, 0) AS lifetime_points,
        ord.last_order_at
    FROM customers c
    LEFT JOIN (
        SELECT
            customer_id,
            MAX(created_at) AS last_notification_at,
            COUNT(*) AS total_notifications,
            SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS sent_notifications
        FROM crm_notification_logs
        WHERE branch_id = ?
        GROUP BY customer_id
    ) notif
        ON notif.customer_id = c.id
    LEFT JOIN loyalty_point_accounts lpa
        ON lpa.branch_id = ? AND lpa.customer_id = c.id
    LEFT JOIN (
        SELECT customer_id, MAX(created_at) AS last_order_at
        FROM orders
        WHERE branch_id = ?
        GROUP BY customer_id
    ) ord
        ON ord.customer_id = c.id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY
        CASE WHEN notif.last_notification_at IS NULL THEN 1 ELSE 0 END,
        notif.last_notification_at DESC,
        ord.last_order_at DESC,
        c.id DESC
    LIMIT ? OFFSET ?';

$listParams = array_merge([$branchId, $branchId, $branchId], $params, [$limit, $offset]);
$stmt = $db->prepare($listSql);
$stmt->execute($listParams);
$customers = $stmt->fetchAll();

$countSql = '
    SELECT COUNT(*) FROM (
        SELECT c.id
        FROM customers c
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY c.id
    ) t';
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$selectedCustomer = [];
$selectedNotifications = [];
$selectedOrders = [];

if ($selectedCustomerId > 0) {
    $detailStmt = $db->prepare(
        'SELECT
            c.*,
            COALESCE(lpa.balance_points, 0) AS balance_points,
            COALESCE(lpa.lifetime_points, 0) AS lifetime_points
         FROM customers c
         LEFT JOIN loyalty_point_accounts lpa
            ON lpa.branch_id = ? AND lpa.customer_id = c.id
         WHERE c.id = ?
           AND (
               EXISTS (SELECT 1 FROM crm_notification_logs l WHERE l.branch_id = ? AND l.customer_id = c.id)
               OR EXISTS (SELECT 1 FROM loyalty_point_accounts la WHERE la.branch_id = ? AND la.customer_id = c.id)
               OR EXISTS (SELECT 1 FROM orders o WHERE o.branch_id = ? AND o.customer_id = c.id)
           )
         LIMIT 1'
    );
    $detailStmt->execute([$branchId, $selectedCustomerId, $branchId, $branchId, $branchId]);
    $selectedCustomer = $detailStmt->fetch() ?: [];

    if (!empty($selectedCustomer)) {
        $notifStmt = $db->prepare(
            'SELECT *
             FROM crm_notification_logs
             WHERE branch_id = ? AND customer_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 30'
        );
        $notifStmt->execute([$branchId, $selectedCustomerId]);
        $selectedNotifications = $notifStmt->fetchAll();

        $orderStmt = $db->prepare(
            'SELECT id, order_number, order_status, payment_status, total_amount, created_at
             FROM orders
             WHERE branch_id = ? AND customer_id = ?
             ORDER BY created_at DESC
             LIMIT 10'
        );
        $orderStmt->execute([$branchId, $selectedCustomerId]);
        $selectedOrders = $orderStmt->fetchAll();
    }
}

$queryBase = array_filter([
    'q' => $query,
    'customer_id' => $selectedCustomerId > 0 ? $selectedCustomerId : null,
]);

ob_start();
?>
<div class="section-header">
  <h2>Customer CRM</h2>
  <div style="color:var(--text-light);font-size:.9rem"><?= number_format($total) ?> customer terindeks</div>
</div>

<div class="card" style="margin-bottom:16px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:1;min-width:220px">
      <label class="form-label" for="q">Cari Customer</label>
      <input type="text" id="q" name="q" class="form-control"
             value="<?= htmlspecialchars($query) ?>"
             placeholder="Nama, email, WhatsApp, atau identifier">
      <small style="color:var(--text-light)">
        WhatsApp akan dicocokkan juga dalam format country code normalisasi.
      </small>
    </div>
    <?php if ($selectedCustomerId > 0): ?>
      <input type="hidden" name="customer_id" value="<?= $selectedCustomerId ?>">
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="<?= BASE_URL ?>/dashboard/branch/crm.php" class="btn btn-outline">Reset</a>
  </form>
</div>

<div style="display:grid;grid-template-columns:1.35fr 1fr;gap:20px">
  <div class="card">
    <div class="card-title">Direktori Customer</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Customer</th>
            <th>Loyalty</th>
            <th>Notifikasi</th>
            <th>Aktivitas Terakhir</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $row): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars((string)($row['name'] ?: $row['identifier'])) ?></strong>
              <?php if (!empty($row['whatsapp'])): ?><br><small>WA: <?= htmlspecialchars((string)$row['whatsapp']) ?></small><?php endif; ?>
              <?php if (!empty($row['email'])): ?><br><small>Email: <?= htmlspecialchars((string)$row['email']) ?></small><?php endif; ?>
            </td>
            <td>
              <strong><?= number_format((int)($row['balance_points'] ?? 0)) ?></strong> poin
              <br><small>Lifetime <?= number_format((int)($row['lifetime_points'] ?? 0)) ?></small>
            </td>
            <td>
              <strong><?= number_format((int)($row['sent_notifications'] ?? 0)) ?></strong> terkirim
              <br><small>Total log <?= number_format((int)($row['total_notifications'] ?? 0)) ?></small>
            </td>
            <td style="font-size:.82rem">
              <?php
              $lastActivity = $row['last_notification_at'] ?: $row['last_order_at'];
              echo $lastActivity ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$lastActivity))) : '-';
              ?>
            </td>
            <td>
              <a class="btn btn-xs btn-outline"
                 href="?<?= http_build_query(array_filter(['q' => $query, 'customer_id' => (int)$row['id']])) ?>">
                Detail
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($customers)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--text-light)">Belum ada customer yang cocok dengan filter ini.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
          <a href="?<?= http_build_query(array_merge($queryBase, ['page' => $p])) ?>"
             class="page-btn <?= $p === $page ? 'active' : '' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-title">Detail Customer</div>
    <?php if (!empty($selectedCustomer)): ?>
      <div style="margin-bottom:14px">
        <div style="font-weight:700"><?= htmlspecialchars((string)($selectedCustomer['name'] ?: $selectedCustomer['identifier'])) ?></div>
        <div style="font-size:.82rem;color:var(--text-light)">Identifier: <?= htmlspecialchars((string)$selectedCustomer['identifier']) ?></div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div style="background:var(--bg-light,#faf9f7);padding:12px;border-radius:10px">
          <div style="font-size:.78rem;color:var(--text-light)">Email</div>
          <div style="font-size:.98rem;font-weight:600"><?= htmlspecialchars((string)($selectedCustomer['email'] ?: '-')) ?></div>
        </div>
        <div style="background:var(--bg-light,#faf9f7);padding:12px;border-radius:10px">
          <div style="font-size:.78rem;color:var(--text-light)">WhatsApp</div>
          <div style="font-size:.98rem;font-weight:600"><?= htmlspecialchars((string)($selectedCustomer['whatsapp'] ?: '-')) ?></div>
        </div>
        <div style="background:var(--bg-light,#faf9f7);padding:12px;border-radius:10px">
          <div style="font-size:.78rem;color:var(--text-light)">Saldo Poin</div>
          <div style="font-size:1.25rem;font-weight:700"><?= number_format((int)($selectedCustomer['balance_points'] ?? 0)) ?></div>
        </div>
        <div style="background:var(--bg-light,#faf9f7);padding:12px;border-radius:10px">
          <div style="font-size:.78rem;color:var(--text-light)">Lifetime Poin</div>
          <div style="font-size:1.25rem;font-weight:700"><?= number_format((int)($selectedCustomer['lifetime_points'] ?? 0)) ?></div>
        </div>
      </div>

      <div style="font-weight:600;margin-bottom:8px">Riwayat Notifikasi CRM</div>
      <div style="display:grid;gap:10px;margin-bottom:18px">
        <?php foreach ($selectedNotifications as $log): ?>
          <?php
          $badgeClass = match ((string)($log['status'] ?? 'pending')) {
              'sent' => 'badge-green',
              'failed' => 'badge-orange',
              default => 'badge-gray',
          };
          ?>
          <div style="padding:10px 0;border-bottom:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
              <div>
                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars((string)($log['status'] ?? 'pending')) ?></span>
                <span class="badge badge-blue"><?= htmlspecialchars((string)($log['channel'] ?? '-')) ?></span>
                <div style="margin-top:6px;font-weight:600"><?= htmlspecialchars((string)($log['event_type'] ?? '-')) ?></div>
                <div style="font-size:.78rem;color:var(--text-light)"><?= htmlspecialchars((string)($log['recipient'] ?? '-')) ?></div>
                <div style="font-size:.78rem;color:var(--text-light)"><?= date('d/m/Y H:i', strtotime((string)$log['created_at'])) ?></div>
              </div>
            </div>
            <?php if (!empty($log['message_preview'])): ?>
              <div style="margin-top:8px;font-size:.84rem;color:var(--text-mid);white-space:pre-wrap"><?= htmlspecialchars((string)$log['message_preview']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (empty($selectedNotifications)): ?>
          <div style="color:var(--text-light)">Belum ada notifikasi CRM untuk customer ini.</div>
        <?php endif; ?>
      </div>

      <div style="font-weight:600;margin-bottom:8px">Order Terakhir</div>
      <div style="display:grid;gap:10px">
        <?php foreach ($selectedOrders as $order): ?>
          <div style="padding:10px 0;border-bottom:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;gap:12px">
              <div>
                <a href="<?= BASE_URL ?>/dashboard/branch/order-detail.php?id=<?= (int)$order['id'] ?>" style="font-weight:600">
                  <?= htmlspecialchars((string)$order['order_number']) ?>
                </a>
                <div style="font-size:.78rem;color:var(--text-light)">
                  <?= htmlspecialchars((string)$order['order_status']) ?> · <?= htmlspecialchars((string)$order['payment_status']) ?>
                </div>
              </div>
              <div style="text-align:right">
                <div style="font-weight:700"><?= number_format((float)$order['total_amount'], 0, ',', '.') ?></div>
                <div style="font-size:.78rem;color:var(--text-light)"><?= date('d/m/Y H:i', strtotime((string)$order['created_at'])) ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($selectedOrders)): ?>
          <div style="color:var(--text-light)">Belum ada order untuk customer ini di cabang ini.</div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div style="color:var(--text-light)">Pilih customer dari tabel kiri untuk melihat email, WhatsApp, loyalty, dan histori notifikasi.</div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('Customer CRM', $content, 'branch_admin');
