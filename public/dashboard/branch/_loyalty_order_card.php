<?php

declare(strict_types=1);

$loyaltyRepo = new LoyaltyPointRepository();
$loyaltyAccount = $loyaltyRepo->getCustomerAccount((int)$order['branch_id'], (int)$order['customer_id']);
$loyaltyTransactions = $loyaltyRepo->getCustomerTransactions((int)$order['branch_id'], (int)$order['customer_id'], 8);
$orderTransactions = $loyaltyRepo->getOrderTransactions((int)$order['id']);
$orderEarnedPoints = 0;
$orderRedeemedPoints = (int)($order['loyalty_points_redeemed'] ?? 0);
$orderRedeemedAmount = (float)($order['loyalty_discount_amount'] ?? 0);
foreach ($orderTransactions as $orderTx) {
    if (($orderTx['transaction_type'] ?? '') === 'earn') {
        $orderEarnedPoints += (int)($orderTx['points'] ?? 0);
    }
}
?>
<div class="card" style="margin-top:20px">
  <div class="card-title">⭐ Histori Loyalty Customer</div>
  <?php if (!empty($loyaltyAccount)): ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
      <div style="background:var(--bg-light,#faf9f7);padding:12px;border-radius:10px">
        <div style="font-size:.78rem;color:var(--text-light)">Poin Dipakai di Order Ini</div>
        <div style="font-size:1.2rem;font-weight:700">
          <?= number_format($orderRedeemedPoints) ?> poin
          <?php if ($orderRedeemedAmount > 0): ?>
            <div style="font-size:.8rem;color:var(--text-light);font-weight:400">Diskon <?= Currency::format($orderRedeemedAmount, $currency) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div style="background:var(--bg-light,#faf9f7);padding:12px;border-radius:10px">
        <div style="font-size:.78rem;color:var(--text-light)">Poin Dari Order Ini</div>
        <div style="font-size:1.2rem;font-weight:700"><?= number_format($orderEarnedPoints) ?> poin</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
      <div style="background:var(--bg-light,#faf9f7);padding:12px;border-radius:10px">
        <div style="font-size:.78rem;color:var(--text-light)">Saldo Saat Ini</div>
        <div style="font-size:1.2rem;font-weight:700"><?= number_format((int)($loyaltyAccount['balance_points'] ?? 0)) ?> poin</div>
      </div>
      <div style="background:var(--bg-light,#faf9f7);padding:12px;border-radius:10px">
        <div style="font-size:.78rem;color:var(--text-light)">Lifetime</div>
        <div style="font-size:1.2rem;font-weight:700"><?= number_format((int)($loyaltyAccount['lifetime_points'] ?? 0)) ?> poin</div>
      </div>
    </div>
    <?php foreach ($loyaltyTransactions as $tx): ?>
      <?php
      $type = (string)($tx['transaction_type'] ?? '');
      $points = (int)($tx['points'] ?? 0);
      $badgeColor = match ($type) {
          'earn' => 'badge-green',
          'redeem' => 'badge-blue',
          'refund' => 'badge-orange',
          default => 'badge-gray',
      };
      ?>
      <div style="padding:8px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;gap:10px">
          <div>
            <span class="badge <?= $badgeColor ?>"><?= htmlspecialchars($type) ?></span>
            <div style="margin-top:6px;font-weight:600"><?= htmlspecialchars((string)($tx['description'] ?? '-')) ?></div>
            <div style="font-size:.78rem;color:var(--text-light)">
              <?= date('d/m/Y H:i', strtotime((string)$tx['created_at'])) ?>
              <?php if (!empty($tx['order_number'])): ?> · <?= htmlspecialchars((string)$tx['order_number']) ?><?php endif; ?>
            </div>
          </div>
          <div style="font-weight:700;color:<?= $points >= 0 ? '#2f855a' : '#2b6cb0' ?>">
            <?= $points >= 0 ? '+' : '' ?><?= number_format($points) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/dashboard/branch/loyalty.php?customer_id=<?= (int)$order['customer_id'] ?>" class="btn btn-outline btn-sm" style="margin-top:12px">
      Lihat Histori Lengkap
    </a>
  <?php else: ?>
    <div style="color:var(--text-light)">Customer ini belum memiliki histori loyalty point.</div>
  <?php endif; ?>
</div>
