<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};
use App\Models\AgentTaskModel;

Auth::startSession();
Auth::requireLogin();

$user = Auth::user();
$branchId = (int)($user['branch_id'] ?? 0);
if ($branchId <= 0) {
    header('Location: ' . BASE_URL . '/dashboard/super/');
    exit;
}

$agentTaskModel = new AgentTaskModel();
$isAvailable = $agentTaskModel->isAvailable();
$stats = $isAvailable ? $agentTaskModel->getStats($branchId) : [];
$tasks = $isAvailable ? $agentTaskModel->getRecentTasks(50, $branchId) : [];
$selectedTaskId = (int)($_GET['id'] ?? 0);
$selectedTask = $selectedTaskId > 0 && $isAvailable ? $agentTaskModel->findTaskWithSteps($selectedTaskId, $branchId) : false;

$formatJson = static function ($json): string {
    if ($json === null || $json === '') {
        return '-';
    }

    $decoded = json_decode((string)$json, true);
    if (!is_array($decoded)) {
        return htmlspecialchars((string)$json);
    }

    return htmlspecialchars((string)json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};

ob_start();
?>
<div class="section-header">
  <h2>Agent Monitor Cabang</h2>
</div>

<?php if (!$isAvailable): ?>
<div class="card" style="border-left:4px solid #d97706">
  <div class="card-title">Agent Tables Belum Aktif</div>
  <p style="margin:0;color:var(--text-mid)">
    Monitoring agent belum tersedia di cabang ini karena tabel observability belum aktif.
    Jalankan <code>database/add_customer_agent_tables.sql</code> dari instalasi utama.
  </p>
</div>
<?php else: ?>

<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:20px">
  <div class="card"><div class="card-title">Total</div><div style="font-size:1.8rem;font-weight:700"><?= (int)($stats['total_tasks'] ?? 0) ?></div></div>
  <div class="card"><div class="card-title">Advisory</div><div style="font-size:1.8rem;font-weight:700;color:#0f766e"><?= (int)($stats['advisory_tasks'] ?? 0) ?></div></div>
  <div class="card"><div class="card-title">Transactional</div><div style="font-size:1.8rem;font-weight:700;color:#1d4ed8"><?= (int)($stats['transactional_tasks'] ?? 0) ?></div></div>
  <div class="card"><div class="card-title">Hari Ini</div><div style="font-size:1.8rem;font-weight:700"><?= (int)($stats['today_tasks'] ?? 0) ?></div></div>
</div>

<div style="display:grid;grid-template-columns:1.15fr 1fr;gap:20px">
  <div class="card">
    <div class="card-title">Recent Tasks Cabang</div>
    <?php foreach ($tasks as $task): ?>
      <a href="?id=<?= (int)$task['id'] ?>" style="display:block;padding:12px 0;border-bottom:1px solid var(--border);text-decoration:none;color:inherit;<?= $selectedTaskId === (int)$task['id'] ? 'background:var(--coffee-cream);' : '' ?>">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
          <div>
            <div style="font-weight:600;font-size:.92rem"><?= htmlspecialchars($task['goal'] ?? '-') ?></div>
            <div style="font-size:.75rem;color:var(--text-light)">
              <?= htmlspecialchars($task['customer_name'] ?? $task['customer_identifier'] ?? '-') ?>
              · <?= htmlspecialchars($task['intent'] ?? '-') ?>
              · <?= (int)($task['step_count'] ?? 0) ?> step
            </div>
          </div>
          <span class="badge <?= ($task['mode'] ?? '') === 'advisory' ? 'badge-green' : 'badge-blue' ?>"><?= htmlspecialchars($task['mode'] ?? '-') ?></span>
        </div>
      </a>
    <?php endforeach; ?>
    <?php if (empty($tasks)): ?>
      <p style="color:var(--text-light)">Belum ada task agent untuk cabang ini.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <?php if ($selectedTask): ?>
      <div class="card-title">Task #<?= (int)$selectedTask['id'] ?></div>
      <div style="display:grid;gap:10px;font-size:.92rem">
        <div><strong>Customer:</strong> <?= htmlspecialchars($selectedTask['customer_name'] ?? $selectedTask['customer_identifier'] ?? '-') ?></div>
        <div><strong>Intent:</strong> <?= htmlspecialchars($selectedTask['intent'] ?? '-') ?></div>
        <div><strong>Mode:</strong> <?= htmlspecialchars($selectedTask['mode'] ?? '-') ?></div>
        <div><strong>Goal:</strong> <?= htmlspecialchars($selectedTask['goal'] ?? '-') ?></div>
        <div><strong>Summary:</strong><div style="white-space:pre-line;margin-top:6px"><?= htmlspecialchars($selectedTask['summary'] ?? '-') ?></div></div>
      </div>

      <hr style="margin:18px 0;border:none;border-top:1px solid var(--border)">
      <div class="card-title">Steps</div>
      <?php foreach ((array)($selectedTask['steps'] ?? []) as $step): ?>
        <div style="border:1px solid var(--border);border-radius:12px;padding:12px;margin-bottom:12px;background:#fff">
          <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:8px">
            <strong>#<?= (int)$step['step_index'] ?> <?= htmlspecialchars($step['tool_name'] ?? $step['step_type'] ?? '-') ?></strong>
            <span class="badge badge-gray"><?= htmlspecialchars($step['status'] ?? '-') ?></span>
          </div>
          <div style="font-size:.82rem;margin-bottom:6px"><strong>Input</strong></div>
          <pre style="white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:8px;overflow:auto"><?= $formatJson($step['input_json'] ?? null) ?></pre>
          <div style="font-size:.82rem;margin:10px 0 6px"><strong>Output</strong></div>
          <pre style="white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:8px;overflow:auto"><?= $formatJson($step['output_json'] ?? null) ?></pre>
        </div>
      <?php endforeach; ?>
      <?php if (empty($selectedTask['steps'])): ?>
        <p style="color:var(--text-light)">Task ini belum punya step yang tersimpan.</p>
      <?php endif; ?>
    <?php else: ?>
      <div style="display:flex;align-items:center;justify-content:center;height:280px;color:var(--text-light)">
        Pilih task untuk melihat detail tool call di cabang ini.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>
<?php
$content = ob_get_clean();
echo View::renderLayout('Agent Monitor Cabang', $content, 'branch_admin');
