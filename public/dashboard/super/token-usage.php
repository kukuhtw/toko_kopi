<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

$db = Database::getInstance();

// --- Filters ---
$dateFrom = $_GET['from']      ?? date('Y-m-01');
$dateTo   = $_GET['to']        ?? date('Y-m-d');
$branchId = (int)($_GET['branch_id'] ?? 0);

$where  = 'tl.created_at BETWEEN ? AND ?';
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
if ($branchId > 0) { $where .= ' AND tl.branch_id = ?'; $params[] = $branchId; }

// --- Overall summary for period ---
$overall = $db->prepare(
    "SELECT SUM(total_tokens) AS tokens, SUM(cost_estimate) AS cost,
            COUNT(*) AS requests, AVG(total_tokens) AS avg_tokens
     FROM token_usage_logs tl WHERE {$where}"
);
$overall->execute($params);
$overall = $overall->fetch();

// --- Daily data for chart ---
$dailyStmt = $db->prepare(
    "SELECT DATE(tl.created_at) AS day,
            SUM(total_tokens)   AS tokens,
            SUM(cost_estimate)  AS cost,
            COUNT(*)            AS requests
     FROM token_usage_logs tl
     WHERE {$where}
     GROUP BY DATE(tl.created_at)
     ORDER BY day ASC"
);
$dailyStmt->execute($params);
$daily = $dailyStmt->fetchAll();

// --- Per-branch in period ---
$statsStmt = $db->prepare(
    "SELECT tl.branch_id, COALESCE(b.name,'N/A') AS branch_name,
            tl.provider,
            SUM(tl.prompt_tokens)     AS total_prompt,
            SUM(tl.completion_tokens) AS total_completion,
            SUM(tl.total_tokens)      AS total_tokens,
            SUM(tl.cost_estimate)     AS total_cost,
            COUNT(*)                  AS total_requests
     FROM token_usage_logs tl
     LEFT JOIN branches b ON tl.branch_id = b.id
     WHERE {$where}
     GROUP BY tl.branch_id, tl.provider
     ORDER BY total_tokens DESC"
);
$statsStmt->execute($params);
$stats = $statsStmt->fetchAll();

// --- Per-model breakdown ---
$modelsStmt = $db->prepare(
    "SELECT COALESCE(model,'unknown') AS model,
            SUM(total_tokens)   AS tokens,
            SUM(cost_estimate)  AS cost,
            COUNT(*)            AS requests
     FROM token_usage_logs tl
     WHERE {$where}
     GROUP BY model
     ORDER BY tokens DESC"
);
$modelsStmt->execute($params);
$models = $modelsStmt->fetchAll();

// --- Branches list for filter ---
$branches = $db->query('SELECT id, name FROM branches ORDER BY name')->fetchAll();

// --- Prepare chart data ---
$chartDays     = array_column($daily, 'day');
$chartTokens   = array_map(fn($r) => (int)$r['tokens'],   $daily);
$chartCosts    = array_map(fn($r) => round((float)$r['cost'], 4), $daily);
$chartRequests = array_map(fn($r) => (int)$r['requests'], $daily);

// Per-branch bar chart (aggregate regardless of provider)
$branchTotals = [];
foreach ($stats as $s) {
    $k = $s['branch_name'];
    $branchTotals[$k] = ($branchTotals[$k] ?? 0) + (int)$s['total_tokens'];
}
arsort($branchTotals);
$barLabels = array_keys($branchTotals);
$barValues = array_values($branchTotals);

ob_start();
?>

<!-- Filter -->
<form method="GET" style="margin-bottom:16px">
  <div class="card" style="padding:16px">
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
      <div class="form-group" style="margin:0;flex:1;min-width:140px">
        <label class="form-label" for="filter_from">Dari Tanggal</label>
        <input type="date" name="from" id="filter_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:140px">
        <label class="form-label" for="filter_to">Sampai Tanggal</label>
        <input type="date" name="to" id="filter_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div class="form-group" style="margin:0;min-width:180px">
        <label class="form-label" for="filter_branch">Cabang</label>
        <select name="branch_id" id="filter_branch" class="form-control">
          <option value="0">Semua Cabang</option>
          <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $branchId === (int)$b['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($b['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="?" class="btn btn-outline">Reset</a>
    </div>
  </div>
</form>

<!-- Stat Cards -->
<div class="stats-grid" style="margin-bottom:16px">
  <div class="stat-card">
    <div class="stat-label">Total Token</div>
    <div class="stat-value"><?= number_format((int)($overall['tokens'] ?? 0)) ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Estimasi Biaya</div>
    <div class="stat-value">$<?= number_format((float)($overall['cost'] ?? 0), 4) ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Total Requests</div>
    <div class="stat-value"><?= number_format((int)($overall['requests'] ?? 0)) ?></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">Rata-rata Token/Request</div>
    <div class="stat-value"><?= number_format((float)($overall['avg_tokens'] ?? 0), 0) ?></div>
  </div>
</div>

<!-- Charts row -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

  <!-- Daily Line Chart -->
  <div class="card" style="grid-column:1/-1">
    <div class="card-title" style="margin-bottom:12px">📈 Token Harian</div>
    <?php if (empty($daily)): ?>
      <p style="color:var(--text-light);text-align:center;padding:40px 0">Belum ada data pada periode ini.</p>
    <?php else: ?>
      <div style="position:relative;height:260px">
        <canvas id="dailyChart"></canvas>
      </div>
    <?php endif; ?>
  </div>

  <!-- Per-Branch Bar -->
  <div class="card">
    <div class="card-title" style="margin-bottom:12px">🏪 Token per Cabang</div>
    <?php if (empty($barLabels)): ?>
      <p style="color:var(--text-light);text-align:center;padding:40px 0">Belum ada data.</p>
    <?php else: ?>
      <div style="position:relative;height:240px">
        <canvas id="branchChart"></canvas>
      </div>
    <?php endif; ?>
  </div>

  <!-- Per-Model Pie -->
  <div class="card">
    <div class="card-title" style="margin-bottom:12px">🤖 Distribusi Model</div>
    <?php if (empty($models)): ?>
      <p style="color:var(--text-light);text-align:center;padding:40px 0">Belum ada data.</p>
    <?php else: ?>
      <div style="position:relative;height:240px">
        <canvas id="modelChart"></canvas>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- Per-Branch Table -->
<div class="card" style="margin-bottom:16px">
  <div class="card-title">📊 Detail per Cabang</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Cabang</th><th>Provider</th><th>Prompt</th><th>Completion</th><th>Total Token</th><th>Estimasi Biaya</th><th>Requests</th></tr>
      </thead>
      <tbody>
      <?php foreach ($stats as $s): ?>
      <tr>
        <td><?= htmlspecialchars($s['branch_name']) ?></td>
        <td><span class="badge badge-blue"><?= htmlspecialchars($s['provider']) ?></span></td>
        <td><?= number_format((int)$s['total_prompt']) ?></td>
        <td><?= number_format((int)$s['total_completion']) ?></td>
        <td><strong><?= number_format((int)$s['total_tokens']) ?></strong></td>
        <td>$<?= number_format((float)$s['total_cost'], 4) ?></td>
        <td><?= number_format((int)$s['total_requests']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($stats)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-light)">Belum ada data pada periode ini.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Per-Model Table -->
<div class="card">
  <div class="card-title">🤖 Detail per Model</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Model</th><th>Total Token</th><th>Estimasi Biaya</th><th>Requests</th><th>Avg Token/Req</th></tr>
      </thead>
      <tbody>
      <?php foreach ($models as $m): ?>
      <tr>
        <td><code style="font-size:.85rem"><?= htmlspecialchars($m['model']) ?></code></td>
        <td><strong><?= number_format((int)$m['tokens']) ?></strong></td>
        <td>$<?= number_format((float)$m['cost'], 4) ?></td>
        <td><?= number_format((int)$m['requests']) ?></td>
        <td><?= $m['requests'] > 0 ? number_format((int)$m['tokens'] / (int)$m['requests']) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($models)): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--text-light)">Belum ada data.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($daily) || !empty($barLabels) || !empty($models)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const palette = [
  '#6f4e37','#a0522d','#cd853f','#deb887','#8b4513',
  '#d2691e','#bc8a5f','#c19a6b','#967969','#7b4f2e'
];

<?php if (!empty($daily)): ?>
// --- Daily Line Chart ---
new Chart(document.getElementById('dailyChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartDays) ?>,
    datasets: [
      {
        label: 'Total Token',
        data: <?= json_encode($chartTokens) ?>,
        borderColor: '#6f4e37',
        backgroundColor: 'rgba(111,78,55,.12)',
        borderWidth: 2,
        pointRadius: 3,
        tension: 0.3,
        fill: true,
        yAxisID: 'yTokens',
      },
      {
        label: 'Biaya ($)',
        data: <?= json_encode($chartCosts) ?>,
        borderColor: '#3a9e6b',
        backgroundColor: 'rgba(58,158,107,.08)',
        borderWidth: 2,
        pointRadius: 3,
        tension: 0.3,
        fill: false,
        yAxisID: 'yCost',
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { position: 'top' },
      tooltip: {
        callbacks: {
          afterBody: (items) => {
            const idx = items[0].dataIndex;
            return `Requests: <?= json_encode($chartRequests) ?>[idx]`;
          }
        }
      }
    },
    scales: {
      yTokens: {
        type: 'linear', position: 'left',
        ticks: { callback: v => v.toLocaleString('id-ID') },
        grid: { color: 'rgba(0,0,0,.06)' },
      },
      yCost: {
        type: 'linear', position: 'right',
        ticks: { callback: v => '$' + v.toFixed(4) },
        grid: { drawOnChartArea: false },
      },
      x: { grid: { color: 'rgba(0,0,0,.04)' } },
    },
  },
});
<?php endif; ?>

<?php if (!empty($barLabels)): ?>
// --- Per-Branch Bar Chart ---
new Chart(document.getElementById('branchChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($barLabels) ?>,
    datasets: [{
      label: 'Total Token',
      data: <?= json_encode($barValues) ?>,
      backgroundColor: palette.slice(0, <?= count($barLabels) ?>),
      borderRadius: 4,
    }],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { ticks: { callback: v => v.toLocaleString('id-ID') }, grid: { color: 'rgba(0,0,0,.06)' } },
      x: { grid: { display: false } },
    },
  },
});
<?php endif; ?>

<?php if (!empty($models)): ?>
// --- Per-Model Doughnut ---
const modelLabels = <?= json_encode(array_column($models, 'model')) ?>;
const modelTokens = <?= json_encode(array_map(fn($m) => (int)$m['tokens'], $models)) ?>;
new Chart(document.getElementById('modelChart'), {
  type: 'doughnut',
  data: {
    labels: modelLabels,
    datasets: [{
      data: modelTokens,
      backgroundColor: palette.slice(0, modelLabels.length),
      borderWidth: 2,
      borderColor: '#fff',
    }],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'right', labels: { font: { size: 11 }, boxWidth: 14 } },
      tooltip: {
        callbacks: {
          label: ctx => ` ${ctx.label}: ${ctx.parsed.toLocaleString('id-ID')} token`,
        }
      }
    },
  },
});
<?php endif; ?>
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
echo View::renderLayout('Token Usage Report', $content, 'super_admin');
