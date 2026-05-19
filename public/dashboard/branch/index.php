<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Currency};
use App\Models\{OrderModel, ConversationModel, BranchModel};
use App\Plugin\HookManager;

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId) { header('Location: ' . BASE_URL . '/dashboard/super/'); exit; }

$orderModel  = new OrderModel();
$currency    = (new BranchModel())->getCurrency($branchId);
$stats       = $orderModel->getStats($branchId);
$pending    = $orderModel->getByBranch($branchId, 10, 0);

$convModel  = new ConversationModel();
$recentConv = $convModel->getVisibleByBranch($branchId, 5);

// Daily chart data — last 30 days for this branch
$db        = \App\Config\Database::getInstance();
$dailyRaw  = $db->prepare(
    "SELECT DATE(created_at) AS day,
            COUNT(*)                                                             AS total_orders,
            COALESCE(SUM(total_amount), 0)                                       AS total_revenue,
            COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END), 0) AS paid_revenue,
            SUM(CASE WHEN order_status='completed' THEN 1 ELSE 0 END)           AS completed,
            SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END)           AS cancelled
     FROM orders
     WHERE branch_id = ?
       AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
);
$dailyRaw->execute([$branchId]);
$dailyRows = $dailyRaw->fetchAll();

// Build 30-value arrays, fill missing days with 0
$dayMap = [];
foreach ($dailyRows as $r) { $dayMap[$r['day']] = $r; }

$chartLabels = $chartOrders = $chartRevenue = $chartPaid = $chartCompleted = [];
for ($i = 29; $i >= 0; $i--) {
    $day            = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[]  = date('d/m', strtotime($day));
    $chartOrders[]  = (int)($dayMap[$day]['total_orders']  ?? 0);
    $chartRevenue[] = (float)($dayMap[$day]['total_revenue'] ?? 0);
    $chartPaid[]    = (float)($dayMap[$day]['paid_revenue']  ?? 0);
    $chartCompleted[] = (int)($dayMap[$day]['completed']    ?? 0);
}

ob_start();
?>
<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Total Order</div>
    <div class="stat-value"><?= number_format((int)($stats['total_orders'] ?? 0)) ?></div>
    <div class="stat-sub">Semua waktu</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Revenue (Paid)</div>
    <div class="stat-value"><?= Currency::format((float)($stats['paid_revenue'] ?? 0), $currency) ?></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">Order Hari Ini</div>
    <div class="stat-value"><?= (int)($stats['today_orders'] ?? 0) ?></div>
    <div class="stat-sub"><?= date('d M Y') ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Order Pending</div>
    <div class="stat-value"><?= (int)($stats['pending_orders'] ?? 0) ?></div>
    <div class="stat-sub">Perlu diproses</div>
  </div>
</div>

<!-- Daily Chart -->
<div class="card" style="margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
    <div class="card-title" style="margin:0">📊 Order & Revenue Harian (<?= htmlspecialchars($currency) ?>)</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <div style="display:flex;border:1px solid var(--border);border-radius:6px;overflow:hidden">
        <button class="branch-seg-btn active" data-mode="orders"
                style="border:none;padding:4px 12px;font-size:.8rem;cursor:pointer;background:none">Jumlah Order</button>
        <button class="branch-seg-btn" data-mode="revenue"
                style="border:none;border-left:1px solid var(--border);padding:4px 12px;font-size:.8rem;cursor:pointer;background:none">Revenue</button>
      </div>
      <div style="display:flex;gap:4px">
        <button class="branch-period-btn active btn btn-xs btn-outline" data-days="30">30H</button>
        <button class="branch-period-btn btn btn-xs btn-outline" data-days="14">14H</button>
        <button class="branch-period-btn btn btn-xs btn-outline" data-days="7">7H</button>
      </div>
    </div>
  </div>
  <div style="position:relative;height:260px">
    <canvas id="branchDailyChart"></canvas>
  </div>
</div>

<?php
$pluginWidgets = HookManager::applyFilters('dashboard.branch_widgets', [], $branchId);
foreach ($pluginWidgets as $widget) {
    echo $widget;
}
?>

<div style="display:grid;grid-template-columns:3fr 1fr;gap:20px">
  <!-- Recent Orders -->
  <div class="card">
    <div class="card-title">📦 Order Terbaru</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>No. Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Payment</th><th>Waktu</th><th>Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
          <td>
            <?= htmlspecialchars($o['customer_name']) ?>
            <?php if ($o['customer_wa']): ?><br><small>📱 <?= htmlspecialchars($o['customer_wa']) ?></small><?php endif; ?>
          </td>
          <td><?= Currency::format((float)$o['total_amount'], $currency) ?></td>
          <td><span class="badge <?= match($o['order_status']) { 'completed'=>'badge-green','cancelled'=>'badge-red',default=>'badge-orange' } ?>">
            <?= htmlspecialchars($o['order_status']) ?>
          </span></td>
          <td><span class="badge <?= $o['payment_status']==='paid' ? 'badge-green' : 'badge-gray' ?>">
            <?= htmlspecialchars($o['payment_status']) ?>
          </span></td>
          <td style="font-size:.8rem"><?= date('d/m H:i', strtotime($o['created_at'])) ?></td>
          <td><a href="<?= BASE_URL ?>/dashboard/branch/order-detail.php?id=<?= $o['id'] ?>" class="btn btn-xs btn-primary">Detail</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($pending)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--text-light)">Belum ada order</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <a href="<?= BASE_URL ?>/dashboard/branch/orders.php" class="btn btn-outline btn-sm" style="margin-top:12px">Lihat Semua Order</a>
  </div>

  <!-- Recent Conversations -->
  <div class="card">
    <div class="card-title">💬 Chat Terbaru</div>
    <?php foreach ($recentConv as $c): ?>
    <div style="padding:10px 0;border-bottom:1px solid var(--border)">
      <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($c['customer_name'] ?? $c['customer_identifier']) ?></div>
      <div style="font-size:.75rem;color:var(--text-light)">
        <?= (int)$c['msg_count'] ?> pesan · <?= htmlspecialchars($c['channel']) ?>
      </div>
      <?php if (!empty($c['is_shared_routed'])): ?>
      <div style="font-size:.72rem;color:#8a5a12">via shared inbox host <?= htmlspecialchars($c['source_branch_name'] ?? '-') ?></div>
      <?php endif; ?>
      <div style="font-size:.75rem;color:var(--text-light)"><?= date('d/m H:i', strtotime($c['last_activity'])) ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($recentConv)): ?><p style="color:var(--text-light);font-size:.85rem">Belum ada percakapan</p><?php endif; ?>
    <a href="<?= BASE_URL ?>/dashboard/branch/conversations.php" class="btn btn-outline btn-sm" style="margin-top:12px">Lihat Semua</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const _labels    = <?= json_encode($chartLabels) ?>;
const _orders    = <?= json_encode($chartOrders) ?>;
const _revenue   = <?= json_encode($chartRevenue) ?>;
const _paid      = <?= json_encode($chartPaid) ?>;
const _completed = <?= json_encode($chartCompleted) ?>;
const _currency  = '<?= htmlspecialchars($currency) ?>';

let _mode = 'orders';
let _days = 30;

const ACTIVE_BG = '#6f4e37';

function fmtTick(v, isRev) {
  if (!isRev) return Number.isInteger(v) ? v : '';
  if (v >= 1e6)  return _currency + (v/1e6).toFixed(1)  + 'jt';
  if (v >= 1e3)  return _currency + (v/1e3).toFixed(0)  + 'rb';
  return _currency + v;
}

function buildBranchData() {
  const s = _labels.length - _days;
  const isRev = _mode === 'revenue';
  const labs  = _labels.slice(s);

  if (isRev) {
    return {
      labels: labs,
      datasets: [
        {
          label: 'Revenue Total',
          type: 'bar',
          data: _revenue.slice(s),
          backgroundColor: 'rgba(111,78,55,.75)',
          borderRadius: 3,
          yAxisID: 'y',
          order: 2,
        },
        {
          label: 'Revenue Paid',
          type: 'line',
          data: _paid.slice(s),
          borderColor: '#3a9e6b',
          backgroundColor: 'rgba(58,158,107,.1)',
          borderWidth: 2,
          pointRadius: 3,
          tension: 0.3,
          fill: true,
          yAxisID: 'y',
          order: 1,
        },
      ],
    };
  }

  return {
    labels: labs,
    datasets: [
      {
        label: 'Total Order',
        type: 'bar',
        data: _orders.slice(s),
        backgroundColor: 'rgba(111,78,55,.75)',
        borderRadius: 3,
        yAxisID: 'y',
        order: 2,
      },
      {
        label: 'Completed',
        type: 'bar',
        data: _completed.slice(s),
        backgroundColor: 'rgba(58,158,107,.7)',
        borderRadius: 3,
        yAxisID: 'y',
        order: 3,
      },
    ],
  };
}

function buildBranchOptions() {
  const isRev = _mode === 'revenue';
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
      tooltip: {
        callbacks: {
          label: ctx => {
            const v = ctx.parsed.y;
            return isRev
              ? ` ${ctx.dataset.label}: ${_currency}${v.toLocaleString('id-ID')}`
              : ` ${ctx.dataset.label}: ${v} order`;
          },
          footer: items => {
            const total = items.reduce((s, i) => s + i.parsed.y, 0);
            if (isRev) return;
            return `Total: ${total} order`;
          },
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 11 } } },
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(0,0,0,.06)' },
        ticks: { font: { size: 11 }, callback: v => fmtTick(v, isRev) },
        title: {
          display: true,
          text: isRev ? `Revenue (${_currency})` : 'Jumlah Order',
          font: { size: 11 },
        },
      },
    },
  };
}

const branchCtx   = document.getElementById('branchDailyChart').getContext('2d');
const branchChart = new Chart(branchCtx, {
  type: 'bar',
  data: buildBranchData(),
  options: buildBranchOptions(),
});

function refreshBranchChart() {
  branchChart.data    = buildBranchData();
  branchChart.options = buildBranchOptions();
  branchChart.update('none');
}

// Mode toggle
document.querySelectorAll('.branch-seg-btn').forEach(btn => {
  if (btn.classList.contains('active')) {
    btn.style.background = ACTIVE_BG; btn.style.color = '#fff'; btn.style.fontWeight = '600';
  }
  btn.addEventListener('click', () => {
    document.querySelectorAll('.branch-seg-btn').forEach(b => {
      b.classList.remove('active'); b.style.background = ''; b.style.color = ''; b.style.fontWeight = '';
    });
    btn.classList.add('active');
    btn.style.background = ACTIVE_BG; btn.style.color = '#fff'; btn.style.fontWeight = '600';
    _mode = btn.dataset.mode;
    refreshBranchChart();
  });
});

// Period toggle
document.querySelectorAll('.branch-period-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.branch-period-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _days = parseInt(btn.dataset.days);
    refreshBranchChart();
  });
});
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Dashboard Cabang', $content, 'branch_admin');
