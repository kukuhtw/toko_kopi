<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Csrf, Currency};
use App\Config\Database;
use App\Models\BranchModel;

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId) { header('Location: ' . BASE_URL . '/dashboard/super/'); exit; }

$db      = Database::getInstance();
$message = '';
$error   = '';
$currency = (new BranchModel())->getCurrency($branchId);

// ── POST: tandai dibaca ──────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare(
                'UPDATE notification_logs SET status = "sent", sent_at = NOW()
                 WHERE id = ? AND branch_id = ? AND channel = "dashboard"'
            )->execute([$id, $branchId]);
        }

    } elseif ($action === 'mark_all_read') {
        $db->prepare(
            'UPDATE notification_logs SET status = "sent", sent_at = NOW()
             WHERE branch_id = ? AND channel = "dashboard" AND status = "pending"'
        )->execute([$branchId]);
        $message = 'Semua notifikasi ditandai sudah dibaca.';
    }
}

// ── Query ────────────────────────────────────────────────────

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$totalStmt = $db->prepare(
    'SELECT COUNT(*) FROM notification_logs WHERE branch_id = ? AND channel = ?'
);
$totalStmt->execute([$branchId, 'dashboard']);
$total = (int)$totalStmt->fetchColumn();

$unreadStmt = $db->prepare(
    'SELECT COUNT(*) FROM notification_logs
     WHERE branch_id = ? AND channel = ? AND status = ?'
);
$unreadStmt->execute([$branchId, 'dashboard', 'pending']);
$unread = (int)$unreadStmt->fetchColumn();

$listStmt = $db->prepare(
    'SELECT n.id, n.type, n.status, n.payload, n.created_at,
            o.order_number, o.customer_name, o.total_amount, o.order_status
     FROM notification_logs n
     LEFT JOIN orders o ON o.id = n.order_id
     WHERE n.branch_id = ? AND n.channel = ?
     ORDER BY n.created_at DESC
     LIMIT ? OFFSET ?'
);
$listStmt->execute([$branchId, 'dashboard', $perPage, $offset]);
$notifications = $listStmt->fetchAll();

$totalPages = (int)ceil($total / $perPage);

// ── Render ───────────────────────────────────────────────────

ob_start();
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div>
    <h2 style="margin:0;font-size:1.2rem">Notifikasi Order Baru</h2>
    <?php if ($unread > 0): ?>
    <span class="badge badge-orange" style="margin-top:4px"><?= $unread ?> belum dibaca</span>
    <?php else: ?>
    <span class="badge badge-green" style="margin-top:4px">Semua sudah dibaca</span>
    <?php endif; ?>
  </div>
  <?php if ($unread > 0): ?>
  <form method="POST" style="margin:0">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="mark_all_read">
    <button type="submit" class="btn btn-sm btn-outline">✓ Tandai Semua Dibaca</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($message): ?>
<div class="alert alert-success" style="margin-bottom:16px"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (empty($notifications)): ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:2.5rem;margin-bottom:12px">🔔</div>
  <p style="color:var(--text-light)">Belum ada notifikasi.</p>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden">
  <table class="data-table" style="margin:0">
    <thead>
      <tr>
        <th style="width:40px"></th>
        <th>Order</th>
        <th>Customer</th>
        <th>Total</th>
        <th>Channel</th>
        <th>Waktu</th>
        <th style="width:130px"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($notifications as $notif):
        $payload = [];
        if ($notif['payload']) {
            $decoded = json_decode($notif['payload'], true);
            if (is_array($decoded)) { $payload = $decoded; }
        }
        $isUnread   = $notif['status'] === 'pending';
        $orderNum   = $notif['order_number'] ?? ($payload['order_number'] ?? '-');
        $customer   = $notif['customer_name'] ?? ($payload['customer'] ?? '-');
        $payloadTotal = $payload['total'] ?? null;
        $totalAmt   = $notif['total_amount']
            ? Currency::format((float)$notif['total_amount'], $currency)
            : (is_numeric($payloadTotal) ? Currency::format((float)$payloadTotal, $currency) : ($payloadTotal ?: '-'));
        $ch         = $payload['channel'] ?? '-';
        $waktu      = date('d/m/Y H:i', strtotime($notif['created_at']));
        $rowStyle   = $isUnread ? 'background:var(--bg-light,#faf9f7);font-weight:500' : '';
    ?>
      <tr style="<?= $rowStyle ?>">
        <td style="text-align:center">
          <?= $isUnread ? '<span style="color:#e07b39;font-size:1rem">●</span>' : '<span style="color:#ccc">○</span>' ?>
        </td>
        <td>
          <?php if ($notif['order_number']): ?>
          <a href="<?= BASE_URL ?>/dashboard/branch/order-detail.php?id=<?= $notif['order_id'] ?? '' ?>"
             style="color:var(--coffee-brown);font-weight:600">#<?= htmlspecialchars($orderNum) ?></a>
          <?php else: ?>
          <span>#<?= htmlspecialchars($orderNum) ?></span>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($customer) ?></td>
        <td><?= htmlspecialchars($totalAmt) ?></td>
        <td><span class="badge badge-blue"><?= htmlspecialchars($ch) ?></span></td>
        <td style="font-size:.82rem;color:var(--text-light)"><?= $waktu ?></td>
        <td>
          <?php if ($isUnread): ?>
          <form method="POST" style="margin:0;display:inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="mark_read">
            <input type="hidden" name="id"     value="<?= (int)$notif['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline" style="padding:3px 10px">Tandai Baca</button>
          </form>
          <?php else: ?>
          <span style="font-size:.8rem;color:var(--text-light)">Sudah dibaca</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:20px">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
  <a href="?page=<?= $p ?>"
     class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>
<?php
$content = ob_get_clean();
echo View::renderLayout('Notifikasi', $content, 'branch_admin');
