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

$summaryStmt = $db->prepare(
    'SELECT
        COALESCE(SUM(balance_points), 0) AS total_balance_points,
        COALESCE(SUM(lifetime_points), 0) AS total_lifetime_points
     FROM loyalty_point_accounts
     WHERE customer_id = ?'
);
$summaryStmt->execute([$customerId]);
$summary = $summaryStmt->fetch() ?: [];

$accountsStmt = $db->prepare(
    'SELECT
        b.name AS branch_name,
        lpa.balance_points,
        lpa.lifetime_points,
        lpa.updated_at
     FROM loyalty_point_accounts lpa
     JOIN branches b ON b.id = lpa.branch_id
     WHERE lpa.customer_id = ?
     ORDER BY lpa.updated_at DESC, b.name ASC'
);
$accountsStmt->execute([$customerId]);
$accounts = $accountsStmt->fetchAll();

$txStmt = $db->prepare(
    'SELECT
        lpt.*,
        b.name AS branch_name,
        o.order_number
     FROM loyalty_point_transactions lpt
     LEFT JOIN branches b ON b.id = lpt.branch_id
     LEFT JOIN orders o ON o.id = lpt.order_id
     WHERE lpt.customer_id = ?
     ORDER BY lpt.created_at DESC, lpt.id DESC
     LIMIT 50'
);
$txStmt->execute([$customerId]);
$transactions = $txStmt->fetchAll();
customerPortalRenderStart([
    'title' => 'Loyalty - Customer Portal',
    'heading' => (string)($profile['name'] ?: $profile['identifier'] ?: $customer['name']),
    'subtitle' => 'Pantau saldo poin, lifetime points, dan pergerakan loyalty Anda di setiap cabang.',
    'active' => 'loyalty',
    'extra_styles' => <<<CSS
    .stat-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-bottom:20px; }
    .stat-card { background:#fff; border:1px solid var(--border); border-radius:18px; padding:18px; }
    .stat-card small { color:var(--text-light); display:block; margin-bottom:8px; }
    .stat-card strong { font-size:1.45rem; color:var(--coffee-dark); }
    .content-grid { display:grid; grid-template-columns:.9fr 1.1fr; gap:20px; }
    .timeline { display:grid; gap:10px; }
    .timeline-item { border-bottom:1px solid var(--border); padding-bottom:10px; }
    .timeline-item:last-child { border-bottom:none; padding-bottom:0; }
    .loyalty-branch-list { display:grid; gap:10px; }
    .loyalty-branch-item { border:1px solid var(--border); border-radius:14px; padding:12px 14px; }
    @media (max-width: 900px) {
      .content-grid, .stat-grid { grid-template-columns:1fr; }
    }
CSS,
]);
?>

  <div class="stat-grid">
    <div class="stat-card">
      <small>Total Saldo Poin</small>
      <strong><?= number_format((int)($summary['total_balance_points'] ?? 0)) ?></strong>
    </div>
    <div class="stat-card">
      <small>Lifetime Points</small>
      <strong><?= number_format((int)($summary['total_lifetime_points'] ?? 0)) ?></strong>
    </div>
  </div>

  <div class="content-grid">
    <div class="card-panel">
      <h2>Loyalty per Cabang</h2>
      <div class="loyalty-branch-list">
        <?php foreach ($accounts as $account): ?>
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
        <?php if (empty($accounts)): ?>
          <div class="muted">Belum ada akun loyalty aktif di cabang mana pun.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card-panel">
      <h2>Riwayat Loyalty Point</h2>
      <div class="timeline">
        <?php foreach ($transactions as $tx): ?>
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
        <?php if (empty($transactions)): ?>
          <div class="muted">Belum ada transaksi loyalty untuk customer ini.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php customerPortalRenderEnd(); ?>
