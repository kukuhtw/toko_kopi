<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\View;

Auth::startSession();
Auth::requireRole('branch_admin');

$user = Auth::user();
$branchId = (int)$user['branch_id'];
$repo = new ComplaintTicketRepository();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? 'open'));
    $note = trim((string)($_POST['internal_note'] ?? ''));
    if ($ticketId > 0 && in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
        $ticket = $repo->findByIdAndBranch($ticketId, $branchId);
        if ($ticket) {
            $repo->updateStatus($ticketId, $status, $note !== '' ? $note : null);
            $message = 'Status tiket komplain berhasil diperbarui.';
        }
    }
}

$filterStatus = trim((string)($_GET['status'] ?? 'all'));
if (!in_array($filterStatus, ['all', 'open', 'in_progress', 'resolved', 'closed'], true)) {
    $filterStatus = 'all';
}

$summary = $repo->getSummaryByBranch($branchId);
$tickets = $repo->fetchByBranch($branchId, $filterStatus, 100);

ob_start();
?>
<?php if ($message !== ''): ?>
  <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-title">Ringkasan Komplain</div>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px">
    <div>
      <div style="font-size:.78rem;color:var(--text-light)">Human Follow-up Open</div>
      <div style="font-size:1.35rem;font-weight:700"><?= (int)($summary['human_open'] ?? 0) ?></div>
    </div>
    <div>
      <div style="font-size:.78rem;color:var(--text-light)">Urgent Open</div>
      <div style="font-size:1.35rem;font-weight:700;color:#b91c1c"><?= (int)($summary['urgent_open'] ?? 0) ?></div>
    </div>
    <div>
      <div style="font-size:.78rem;color:var(--text-light)">AI-handled Logged</div>
      <div style="font-size:1.35rem;font-weight:700"><?= (int)($summary['ai_total'] ?? 0) ?></div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-title">Filter Tiket</div>
  <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <select name="status" class="form-control" style="max-width:220px">
      <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua status</option>
      <option value="open" <?= $filterStatus === 'open' ? 'selected' : '' ?>>Open</option>
      <option value="in_progress" <?= $filterStatus === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
      <option value="resolved" <?= $filterStatus === 'resolved' ? 'selected' : '' ?>>Resolved</option>
      <option value="closed" <?= $filterStatus === 'closed' ? 'selected' : '' ?>>Closed</option>
    </select>
    <button type="submit" class="btn btn-outline">Terapkan Filter</button>
  </form>
</div>

<div style="display:flex;flex-direction:column;gap:16px">
  <?php foreach ($tickets as $ticket): ?>
    <?php
      $priority = (string)$ticket['priority'];
      $priorityStyle = $priority === 'high'
          ? 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;'
          : ($priority === 'medium'
              ? 'background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;'
              : 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;');
      $statusValue = (string)$ticket['status'];
    ?>
    <div class="card">
      <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
        <div>
          <div class="card-title" style="margin-bottom:6px">#<?= (int)$ticket['id'] ?> - <?= htmlspecialchars((string)$ticket['subject']) ?></div>
          <div style="font-size:.82rem;color:var(--text-light)">
            <?= htmlspecialchars((string)($ticket['customer_name'] ?: $ticket['customer_identifier'])) ?>
            | <?= htmlspecialchars((string)$ticket['source_channel']) ?>
            | <?= date('d/m/Y H:i', strtotime((string)$ticket['created_at'])) ?>
            <?php if (!empty($ticket['order_number'])): ?>
              | Order <?= htmlspecialchars((string)$ticket['order_number']) ?>
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <span class="badge" style="<?= $priorityStyle ?>"><?= htmlspecialchars(strtoupper($priority)) ?></span>
          <span class="badge" style="background:#f8fafc;color:#334155;border:1px solid #cbd5e1"><?= htmlspecialchars(strtoupper((string)$ticket['handling_mode'])) ?></span>
          <span class="badge" style="background:#ecfdf5;color:#166534;border:1px solid #bbf7d0"><?= htmlspecialchars(strtoupper($statusValue)) ?></span>
        </div>
      </div>

      <div style="margin-top:14px;display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <div style="font-size:.78rem;color:var(--text-light);margin-bottom:6px">Pesan Customer</div>
          <div style="white-space:pre-line;background:#fff;border:1px solid var(--border);border-radius:10px;padding:12px"><?= htmlspecialchars((string)$ticket['customer_message']) ?></div>
        </div>
        <div>
          <div style="font-size:.78rem;color:var(--text-light);margin-bottom:6px">Balasan AI / Reason</div>
          <div style="white-space:pre-line;background:#fff;border:1px solid var(--border);border-radius:10px;padding:12px"><?= htmlspecialchars(trim((string)($ticket['ai_reply'] ?? '')) !== '' ? (string)$ticket['ai_reply'] : (string)($ticket['follow_up_reason'] ?? '-')) ?></div>
        </div>
      </div>

      <?php if (!empty($ticket['follow_up_reason'])): ?>
        <div style="margin-top:12px;font-size:.82rem;color:var(--text-mid)">
          Alasan routing: <?= htmlspecialchars((string)$ticket['follow_up_reason']) ?>
        </div>
      <?php endif; ?>

      <form method="POST" style="margin-top:14px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <?= Csrf::field() ?>
        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
        <div class="form-group" style="margin-bottom:0;max-width:220px">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="open" <?= $statusValue === 'open' ? 'selected' : '' ?>>Open</option>
            <option value="in_progress" <?= $statusValue === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="resolved" <?= $statusValue === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            <option value="closed" <?= $statusValue === 'closed' ? 'selected' : '' ?>>Closed</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0;min-width:320px;flex:1">
          <label class="form-label">Catatan Internal</label>
          <input type="text" name="internal_note" class="form-control" value="<?= htmlspecialchars((string)($ticket['internal_note'] ?? '')) ?>" placeholder="Contoh: Sudah dihubungi via WA oleh barista shift pagi">
        </div>
        <button type="submit" class="btn btn-primary">Update Tiket</button>
      </form>
    </div>
  <?php endforeach; ?>

  <?php if (empty($tickets)): ?>
    <div class="card">
      <div style="color:var(--text-light)">Belum ada tiket komplain untuk filter ini.</div>
    </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

echo View::renderLayout('Tiket Komplain', $content, 'branch_admin');
