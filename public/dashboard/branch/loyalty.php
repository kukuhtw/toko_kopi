<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)($user['branch_id'] ?? 0);
if (!$branchId) {
    http_response_code(403);
    exit('Access denied');
}

$repo = new LoyaltyPointRepository();
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$selectedCustomerId = max(0, (int)($_GET['customer_id'] ?? 0));

$members = $repo->fetchCustomerSummaries($branchId, $q, $limit, $offset);
$total   = $repo->countCustomerSummaries($branchId, $q);
$pages   = max(1, (int)ceil($total / $limit));
$selectedAccount = $selectedCustomerId > 0 ? $repo->getCustomerAccount($branchId, $selectedCustomerId) : [];
$selectedTransactions = $selectedCustomerId > 0 ? $repo->getCustomerTransactions($branchId, $selectedCustomerId, 30) : [];
$queryBase = array_filter([
    'q' => $q,
    'customer_id' => $selectedCustomerId > 0 ? $selectedCustomerId : null,
]);

ob_start();
?>
<div class="section-header">
  <h2>Loyalty Member</h2>
  <div style="color:var(--text-light);font-size:.9rem"><?= number_format($total) ?> member loyalty</div>
</div>

<div class="card" style="margin-bottom:16px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:1;min-width:220px">
      <label class="form-label" for="q">Cari Customer</label>
      <input type="text" id="q" name="q" class="form-control"
             value="<?= htmlspecialchars($q) ?>"
             placeholder="Nama, email, WhatsApp, atau identifier">
    </div>
    <?php if ($selectedCustomerId > 0): ?>
      <input type="hidden" name="customer_id" value="<?= $selectedCustomerId ?>">
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="<?= BASE_URL ?>/dashboard/branch/loyalty.php" class="btn btn-outline">Reset</a>
  </form>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px">
  <div class="card">
    <div class="card-title">Daftar Member Loyalty</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Customer</th>
            <th>Saldo</th>
            <th>Lifetime</th>
            <th>Transaksi Terakhir</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $member): ?>
          <?php
          $lastActivity = $member['last_notification_at']
              ?: $member['last_order_at']
              ?: $member['last_transaction_at']
              ?: $member['updated_at']
              ?: null;
          ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars((string)($member['name'] ?: $member['identifier'])) ?></strong>
              <?php if (!empty($member['whatsapp'])): ?><br><small>📱 <?= htmlspecialchars((string)$member['whatsapp']) ?></small><?php endif; ?>
              <?php if (!empty($member['email'])): ?><br><small>✉️ <?= htmlspecialchars((string)$member['email']) ?></small><?php endif; ?>
            </td>
            <td><strong><?= number_format((int)($member['balance_points'] ?? 0)) ?></strong> poin</td>
            <td><?= number_format((int)($member['lifetime_points'] ?? 0)) ?> poin</td>
            <td style="font-size:.82rem">
              <?= $lastActivity ? date('d/m/Y H:i', strtotime((string)$lastActivity)) : '-' ?>
            </td>
            <td>
              <a class="btn btn-xs btn-outline"
                 href="?<?= http_build_query(array_filter(['q' => $q, 'customer_id' => (int)$member['customer_id']])) ?>">
                Detail
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($members)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--text-light)">Belum ada member loyalty di cabang ini.</td></tr>
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
    <div class="card-title">Detail Histori Poin</div>
    <?php if (!empty($selectedAccount)): ?>
      <div style="margin-bottom:14px">
        <div style="font-weight:700"><?= htmlspecialchars((string)($selectedAccount['name'] ?: $selectedAccount['identifier'])) ?></div>
        <div style="font-size:.82rem;color:var(--text-light)">
          <?= htmlspecialchars((string)($selectedAccount['whatsapp'] ?: $selectedAccount['email'] ?: $selectedAccount['identifier'])) ?>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div style="background:var(--bg-light,#faf9f7);padding:12px;border-radius:10px">
          <div style="font-size:.78rem;color:var(--text-light)">Saldo Saat Ini</div>
          <div style="font-size:1.35rem;font-weight:700"><?= number_format((int)($selectedAccount['balance_points'] ?? 0)) ?></div>
        </div>
        <div style="background:var(--bg-light,#faf9f7);padding:12px;border-radius:10px">
          <div style="font-size:.78rem;color:var(--text-light)">Lifetime Poin</div>
          <div style="font-size:1.35rem;font-weight:700"><?= number_format((int)($selectedAccount['lifetime_points'] ?? 0)) ?></div>
        </div>
      </div>

      <div style="display:grid;gap:10px">
        <?php foreach ($selectedTransactions as $tx): ?>
          <?php
          $type = (string)($tx['transaction_type'] ?? '');
          $badgeColor = match ($type) {
              'earn' => 'badge-green',
              'redeem' => 'badge-blue',
              'refund' => 'badge-orange',
              default => 'badge-gray',
          };
          $points = (int)($tx['points'] ?? 0);
          ?>
          <div style="padding:10px 0;border-bottom:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
              <div>
                <span class="badge <?= $badgeColor ?>"><?= htmlspecialchars($type) ?></span>
                <div style="margin-top:6px;font-weight:600"><?= htmlspecialchars((string)($tx['description'] ?? '-')) ?></div>
                <?php if (!empty($tx['order_number'])): ?>
                  <div style="font-size:.78rem;color:var(--text-light)">
                    Order:
                    <a href="<?= BASE_URL ?>/dashboard/branch/order-detail.php?id=<?= (int)($tx['order_id'] ?? 0) ?>">
                      <?= htmlspecialchars((string)$tx['order_number']) ?>
                    </a>
                    · <?= htmlspecialchars((string)($tx['order_status'] ?? '-')) ?>
                  </div>
                <?php endif; ?>
                <div style="font-size:.78rem;color:var(--text-light)"><?= date('d/m/Y H:i', strtotime((string)$tx['created_at'])) ?></div>
              </div>
              <div style="font-weight:700;color:<?= $points >= 0 ? '#2f855a' : '#2b6cb0' ?>">
                <?= $points >= 0 ? '+' : '' ?><?= number_format($points) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($selectedTransactions)): ?>
          <div style="color:var(--text-light)">Belum ada transaksi poin untuk customer ini.</div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div style="color:var(--text-light)">Pilih customer dari tabel kiri untuk melihat histori poin.</div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('Loyalty Member', $content, 'branch_admin');
