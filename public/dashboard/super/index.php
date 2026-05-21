<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View, Currency};
use App\Models\{OrderModel, BranchModel, ConversationModel};
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

$db         = Database::getInstance();
$orderModel = new OrderModel();
$branchModel= new BranchModel();

// Reusable SQL: konversi total_amount ke IDR
// Prioritas: idr_rate di branch_settings → fallback CASE per currency code
$idrRateExpr = "COALESCE(
    CAST(bs_rate.setting_val AS DECIMAL(15,4)),
    CASE COALESCE(bs_cur.setting_val,'IDR')
        WHEN 'SGD' THEN 11500
        WHEN 'AUD' THEN 10000
        WHEN 'USD' THEN 15800
        WHEN 'MYR' THEN 3400
        ELSE 1
    END
)";
$idrRateJoin = "LEFT JOIN branch_settings bs_cur  ON bs_cur.branch_id  = o.branch_id  AND bs_cur.setting_key  = 'currency'
     LEFT JOIN branch_settings bs_rate ON bs_rate.branch_id = o.branch_id  AND bs_rate.setting_key = 'idr_rate'";

// Global stats — revenue dikonversi ke IDR
$totalOrders  = (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$totalRevenue = (float) $db->query(
    "SELECT COALESCE(SUM(o.total_amount * {$idrRateExpr}), 0)
     FROM orders o {$idrRateJoin}
     WHERE o.payment_status = 'paid'"
)->fetchColumn();
$todayOrders  = (int) $db->query('SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()')->fetchColumn();
$totalBranches= (int) $db->query('SELECT COUNT(*) FROM branches WHERE is_active=1')->fetchColumn();

// Recent orders
$recentOrders = $orderModel->getAll(10, 0);

// Branch summary — revenue lokal + currency per cabang
$branchStats = $db->query(
    "SELECT b.name,
            COALESCE(bs_cur.setting_val,'IDR') AS currency,
            COUNT(o.id) AS orders,
            COALESCE(SUM(o.total_amount),0)                       AS revenue_local,
            COALESCE(SUM(o.total_amount * {$idrRateExpr}),0)      AS revenue_idr
     FROM branches b
     LEFT JOIN orders o ON o.branch_id = b.id
     LEFT JOIN branch_settings bs_cur  ON bs_cur.branch_id  = b.id AND bs_cur.setting_key  = 'currency'
     LEFT JOIN branch_settings bs_rate ON bs_rate.branch_id = b.id AND bs_rate.setting_key = 'idr_rate'
     GROUP BY b.id ORDER BY revenue_idr DESC"
)->fetchAll();

// Daily orders per branch — revenue dikonversi ke IDR
$dailyRaw = $db->query(
    "SELECT DATE(o.created_at) AS day,
            o.branch_id,
            b.name              AS branch_name,
            COUNT(*)            AS total_orders,
            COALESCE(SUM(o.total_amount * {$idrRateExpr}), 0) AS total_revenue
     FROM orders o
     JOIN branches b ON o.branch_id = b.id
     {$idrRateJoin}
     WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY DATE(o.created_at), o.branch_id
     ORDER BY day ASC, b.name ASC"
)->fetchAll();

ob_start();
?>
<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Total Cabang Aktif</div>
    <div class="stat-value"><?= $totalBranches ?></div>
    <div class="stat-sub">Semua cabang</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Revenue (Paid, ~IDR)</div>
    <div class="stat-value"><?= Currency::format($totalRevenue) ?></div>
    <div class="stat-sub">Semua cabang</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Total Order</div>
    <div class="stat-value"><?= number_format($totalOrders) ?></div>
    <div class="stat-sub">Semua waktu</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">Order Hari Ini</div>
    <div class="stat-value"><?= $todayOrders ?></div>
    <div class="stat-sub"><?= date('d M Y') ?></div>
  </div>
</div>

<?php
// Build 30-day label list
$chartDays = [];
for ($i = 29; $i >= 0; $i--) {
    $chartDays[] = date('Y-m-d', strtotime("-{$i} days"));
}

// Collect all branches from raw data
$branchNames = [];
foreach ($dailyRaw as $r) {
    $branchNames[$r['branch_id']] = $r['branch_name'];
}

// Build per-branch data maps: branch_id => [day => [orders, revenue]]
$branchDayMap = [];
foreach ($dailyRaw as $r) {
    $branchDayMap[$r['branch_id']][$r['day']] = [
        'orders'  => (int)$r['total_orders'],
        'revenue' => (float)$r['total_revenue'],
    ];
}

// Build ordered datasets (one per branch, 30 values each)
$branchDatasets = [];
$palette = [
    '#6f4e37','#3a9e6b','#d97706','#3b82f6','#9333ea',
    '#e11d48','#0891b2','#84cc16','#f97316','#64748b',
];
$pi = 0;
foreach ($branchNames as $bid => $bname) {
    $orders = $revenue = [];
    foreach ($chartDays as $day) {
        $orders[]  = $branchDayMap[$bid][$day]['orders']  ?? 0;
        $revenue[] = $branchDayMap[$bid][$day]['revenue'] ?? 0;
    }
    $color = $palette[$pi % count($palette)];
    $branchDatasets[] = [
        'branch_id'   => $bid,
        'branch_name' => $bname,
        'color'       => $color,
        'orders'      => $orders,
        'revenue'     => $revenue,
    ];
    $pi++;
}

// Labels formatted dd/mm
$chartLabels = array_map(fn($d) => date('d/m', strtotime($d)), $chartDays);
?>

<!-- Daily Chart -->
<div class="card" style="margin-bottom:20px">
  <div class="chart-card-header">
    <div class="card-title" style="margin:0">📊 Order & Revenue per Cabang</div>
    <div class="chart-toolbar">
      <!-- Group toggle -->
      <div style="display:flex;border:1px solid var(--border);border-radius:6px;overflow:hidden">
        <button class="chart-seg-btn active" data-group="day"
                style="border:none;padding:4px 12px;font-size:.8rem;cursor:pointer;background:none">📅 Per Hari</button>
        <button class="chart-seg-btn" data-group="branch"
                style="border:none;border-left:1px solid var(--border);padding:4px 12px;font-size:.8rem;cursor:pointer;background:none">🏪 Per Cabang</button>
      </div>
      <!-- Mode toggle -->
      <div style="display:flex;border:1px solid var(--border);border-radius:6px;overflow:hidden">
        <button class="chart-seg-btn active" data-mode="orders"
                style="border:none;padding:4px 12px;font-size:.8rem;cursor:pointer;background:none">Jumlah Order</button>
        <button class="chart-seg-btn" data-mode="revenue"
                style="border:none;border-left:1px solid var(--border);padding:4px 12px;font-size:.8rem;cursor:pointer;background:none">Revenue</button>
      </div>
      <!-- Period toggle (hidden in Per Cabang view via JS) -->
      <div class="chart-toolbar-group" id="periodBtns">
        <button class="btn btn-xs btn-outline chart-period-btn active" data-days="30">30H</button>
        <button class="btn btn-xs btn-outline chart-period-btn" data-days="14">14H</button>
        <button class="btn btn-xs btn-outline chart-period-btn" data-days="7">7H</button>
      </div>
    </div>
  </div>
  <div class="chart-canvas-wrap">
    <canvas id="dailyOrderChart"></canvas>
  </div>
</div>

<div class="dashboard-grid-main-aside">
  <!-- Recent Orders -->
  <div class="card">
    <div class="card-title">📦 Order Terbaru</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Order</th><th>Cabang</th><th>Customer</th>
            <th>Total</th><th>Status</th><th>Payment</th><th>Waktu</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
            <td><?= htmlspecialchars($o['branch_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($o['customer_name']) ?></td>
            <td><?= Currency::format((float)$o['total_amount']) ?></td>
            <td>
              <span class="badge <?= $o['order_status']==='completed' ? 'badge-green' : ($o['order_status']==='cancelled' ? 'badge-red' : 'badge-orange') ?>">
                <?= htmlspecialchars($o['order_status']) ?>
              </span>
            </td>
            <td>
              <span class="badge <?= $o['payment_status']==='paid' ? 'badge-green' : 'badge-gray' ?>">
                <?= htmlspecialchars($o['payment_status']) ?>
              </span>
            </td>
            <td style="white-space:nowrap;font-size:.8rem"><?= date('d/m H:i', strtotime($o['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($recentOrders)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--text-light)">Belum ada order</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <a href="<?= BASE_URL ?>/dashboard/super/orders.php" class="btn btn-outline btn-sm" style="margin-top:12px">Lihat Semua Order</a>
  </div>

  <!-- Branch Summary -->
  <div class="card">
    <div class="card-title">🏪 Per Cabang</div>
    <?php foreach ($branchStats as $b): ?>
    <div style="padding:10px 0;border-bottom:1px solid var(--border)">
      <div style="font-weight:600;margin-bottom:2px"><?= htmlspecialchars($b['name']) ?></div>
      <div style="font-size:.82rem;color:var(--text-mid)">
        <?= $b['orders'] ?> order
        · <span title="Nilai lokal"><?= Currency::format((float)$b['revenue_local'], $b['currency']) ?></span>
        <?php if ($b['currency'] !== 'IDR'): ?>
        · <span style="color:var(--coffee-brown);font-weight:500" title="Dikonversi ke IDR">≈ <?= Currency::format((float)$b['revenue_idr']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/dashboard/super/branches.php" class="btn btn-outline btn-sm" style="margin-top:12px">Kelola Cabang</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const allLabels  = <?= json_encode($chartLabels) ?>;
const branchData = <?= json_encode(array_values($branchDatasets), JSON_UNESCAPED_UNICODE) ?>;

let currentGroup = 'day';     // 'day' | 'branch'
let currentMode  = 'orders';  // 'orders' | 'revenue'
let currentDays  = 30;

const ACTIVE_BG  = '#6f4e37';
const ACTIVE_CLR = '#fff';

function hexToRgba(hex, alpha) {
  const r = parseInt(hex.slice(1,3),16);
  const g = parseInt(hex.slice(3,5),16);
  const b = parseInt(hex.slice(5,7),16);
  return `rgba(${r},${g},${b},${alpha})`;
}

function fmtRev(v) {
  return 'IDR ' + (v >= 1e6 ? (v/1e6).toFixed(1)+'jt' : v >= 1e3 ? (v/1e3).toFixed(0)+'rb' : v);
}

/* ── Per Hari: stacked bar (X = days, datasets = branches) ── */
function buildDayData() {
  const s = allLabels.length - currentDays;
  const isRev = currentMode === 'revenue';
  return {
    labels: allLabels.slice(s),
    datasets: branchData.map(b => ({
      label: b.branch_name,
      data: (isRev ? b.revenue : b.orders).slice(s),
      backgroundColor: hexToRgba(b.color, 0.82),
      hoverBackgroundColor: b.color,
      borderRadius: 2,
      stack: 'main',
    })),
  };
}

/* ── Per Cabang: bar tunggal per cabang (total periode) ── */
function buildBranchData() {
  const s = allLabels.length - currentDays;
  const isRev = currentMode === 'revenue';
  return {
    labels: branchData.map(b => b.branch_name),
    datasets: [{
      label: isRev ? `Revenue ${currentDays} hari` : `Order ${currentDays} hari`,
      data: branchData.map(b =>
        (isRev ? b.revenue : b.orders).slice(s).reduce((a, v) => a + v, 0)
      ),
      backgroundColor: branchData.map(b => hexToRgba(b.color, 0.82)),
      hoverBackgroundColor: branchData.map(b => b.color),
      borderRadius: 4,
    }],
  };
}

function buildData() {
  return currentGroup === 'day' ? buildDayData() : buildBranchData();
}

function buildOptions() {
  const isRev    = currentMode === 'revenue';
  const isDay    = currentGroup === 'day';
  const stacked  = isDay;

  const yTick = isRev
    ? v => fmtRev(v)
    : v => (Number.isInteger(v) ? v : '');

  const tooltipLabel = ctx => {
    const v = ctx.parsed.y;
    return isRev ? ` ${ctx.dataset.label}: IDR ${v.toLocaleString('id-ID')}` : ` ${ctx.dataset.label}: ${v} order`;
  };
  const tooltipFooter = isDay
    ? items => {
        const total = items.reduce((s, i) => s + i.parsed.y, 0);
        return isRev ? `Total: IDR ${total.toLocaleString('id-ID')}` : `Total: ${total} order`;
      }
    : undefined;

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: {
        position: 'top',
        display: isDay,            // sembunyikan legend saat per-cabang (sudah jelas dari X-axis)
        labels: { boxWidth: 12, font: { size: 11 } },
      },
      tooltip: { callbacks: { label: tooltipLabel, footer: tooltipFooter } },
    },
    scales: {
      x: {
        stacked,
        grid: { display: false },
        ticks: { font: { size: isDay ? 11 : 12 } },
      },
      y: {
        stacked,
        beginAtZero: true,
        grid: { color: 'rgba(0,0,0,.06)' },
        ticks: { font: { size: 11 }, callback: yTick },
        title: {
          display: true,
          text: isRev ? 'Revenue (IDR)' : 'Jumlah Order',
          font: { size: 11 },
        },
      },
    },
  };
}

const chartCtx = document.getElementById('dailyOrderChart').getContext('2d');
const chart    = new Chart(chartCtx, { type: 'bar', data: buildData(), options: buildOptions() });

function refreshChart() {
  chart.data    = buildData();
  chart.options = buildOptions();
  chart.update('none');
  // Sembunyikan period toggle saat Per Cabang (masih berlaku untuk agregasi, tapi opsional tampilkan)
  document.getElementById('periodBtns').style.opacity = currentGroup === 'branch' ? '0.5' : '1';
}

/* ── Segment buttons (group + mode) ── */
function initSegBtns(selector, stateKey) {
  const btns = document.querySelectorAll(selector);
  btns.forEach(btn => {
    // Init style
    if (btn.classList.contains('active')) {
      btn.style.background = ACTIVE_BG; btn.style.color = ACTIVE_CLR; btn.style.fontWeight = '600';
    }
    btn.addEventListener('click', () => {
      btns.forEach(b => { b.classList.remove('active'); b.style.background = ''; b.style.color = ''; b.style.fontWeight = ''; });
      btn.classList.add('active');
      btn.style.background = ACTIVE_BG; btn.style.color = ACTIVE_CLR; btn.style.fontWeight = '600';
      if (stateKey === 'group') currentGroup = btn.dataset.group;
      if (stateKey === 'mode')  currentMode  = btn.dataset.mode;
      refreshChart();
    });
  });
}

initSegBtns('[data-group]', 'group');
initSegBtns('[data-mode]',  'mode');

/* ── Period buttons ── */
document.querySelectorAll('.chart-period-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.chart-period-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentDays = parseInt(btn.dataset.days);
    refreshChart();
  });
});
</script>

<?php
$content = ob_get_clean();
echo View::renderLayout('Super Admin Dashboard', $content, 'super_admin');
