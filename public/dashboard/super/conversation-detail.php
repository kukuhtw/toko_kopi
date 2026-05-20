<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};
use App\Models\AgentMemoryModel;
use App\Models\ConversationModel;
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

$convId = (int)($_GET['id'] ?? 0);
if (!$convId) {
    header('Location: ' . BASE_URL . '/dashboard/super/conversations.php');
    exit;
}

$db = Database::getInstance();

$stmt = $db->prepare(
    'SELECT conv.*, b.name AS branch_name, c.name AS customer_name,
            c.identifier AS customer_identifier, c.channel AS customer_channel,
            c.whatsapp AS customer_wa, c.email AS customer_email
     FROM conversations conv
     JOIN branches b ON conv.branch_id = b.id
     JOIN customers c ON conv.customer_id = c.id
     WHERE conv.id = ? LIMIT 1'
);
$stmt->execute([$convId]);
$conv = $stmt->fetch();

if (!$conv) {
    header('Location: ' . BASE_URL . '/dashboard/super/conversations.php');
    exit;
}

$convModel = new ConversationModel();
$messages  = $convModel->getMessages($convId, 200);
$agentMemoryModel = new AgentMemoryModel();
$memoryAvailable = $agentMemoryModel->isAvailable();
$customerKey = $conv['channel'] . ':' . (int)$conv['branch_id'] . ':' . (int)$conv['customer_id'];
$memoryCounts = $memoryAvailable ? $agentMemoryModel->getGroupedCounts('customer', $customerKey) : [];
$selectedMemoryType = trim((string)($_GET['memory_type'] ?? ''));
$memories = $memoryAvailable
    ? ($selectedMemoryType !== ''
        ? $agentMemoryModel->getByEntityKeyAndType('customer', $customerKey, $selectedMemoryType, 40)
        : $agentMemoryModel->getByEntityKey('customer', $customerKey, 40))
    : [];

$memoryBadgeStyle = static function (string $type): string {
    return match ($type) {
        'customer_preference' => 'background:#ecfeff;color:#0f766e;border:1px solid #99f6e4;',
        'successful_advisory' => 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;',
        'recommendation_phrase' => 'background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;',
        'conversation_summary' => 'background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe;',
        default => 'background:#f8fafc;color:#475569;border:1px solid #cbd5e1;',
    };
};

$context = [];
if (!empty($conv['context_data'])) {
    $context = json_decode($conv['context_data'], true) ?? [];
}
$moderation = isset($context['moderation']) && is_array($context['moderation']) ? $context['moderation'] : [];
$suspendedUntil = (string)($moderation['suspended_until'] ?? '');
$lastSuspendedAt = (string)($moderation['last_suspended_at'] ?? '');
$outOfScopeHits = is_array($moderation['out_of_scope_hits'] ?? null) ? count($moderation['out_of_scope_hits']) : 0;

ob_start();
?>
<div style="margin-bottom:16px">
  <a href="<?= BASE_URL ?>/dashboard/super/conversations.php" class="btn btn-outline">&larr; Kembali</a>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start">

  <!-- Info panel -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-title">Info Percakapan #<?= $convId ?></div>
      <table style="width:100%;font-size:.875rem;border-collapse:collapse">
        <tr><td style="color:var(--text-light);padding:4px 0;width:40%">Cabang</td><td><strong><?= htmlspecialchars($conv['branch_name']) ?></strong></td></tr>
        <tr><td style="color:var(--text-light);padding:4px 0">Customer</td><td><?= htmlspecialchars($conv['customer_name'] ?: $conv['customer_identifier']) ?></td></tr>
        <tr><td style="color:var(--text-light);padding:4px 0">Channel</td>
            <td><span class="badge <?= $conv['channel']==='whatsapp' ? 'badge-green' : 'badge-blue' ?>"><?= htmlspecialchars($conv['channel']) ?></span></td></tr>
        <?php if ($conv['customer_wa']): ?>
        <tr><td style="color:var(--text-light);padding:4px 0">WhatsApp</td><td><?= htmlspecialchars($conv['customer_wa']) ?></td></tr>
        <?php endif; ?>
        <?php if ($conv['customer_email']): ?>
        <tr><td style="color:var(--text-light);padding:4px 0">Email</td><td><?= htmlspecialchars($conv['customer_email']) ?></td></tr>
        <?php endif; ?>
        <tr><td style="color:var(--text-light);padding:4px 0">State</td><td><span class="badge badge-gray"><?= htmlspecialchars($conv['state']) ?></span></td></tr>
        <?php if ($suspendedUntil !== ''): ?>
        <tr><td style="color:var(--text-light);padding:4px 0">Suspend Sampai</td><td><?= htmlspecialchars($suspendedUntil) ?></td></tr>
        <?php endif; ?>
        <?php if ($lastSuspendedAt !== ''): ?>
        <tr><td style="color:var(--text-light);padding:4px 0">Suspend Terakhir</td><td><?= htmlspecialchars($lastSuspendedAt) ?></td></tr>
        <?php endif; ?>
        <?php if ($outOfScopeHits > 0): ?>
        <tr><td style="color:var(--text-light);padding:4px 0">Strike Out-of-Scope</td><td><?= $outOfScopeHits ?> / 5</td></tr>
        <?php endif; ?>
        <tr><td style="color:var(--text-light);padding:4px 0">Total Pesan</td><td><?= count($messages) ?></td></tr>
        <tr><td style="color:var(--text-light);padding:4px 0">Aktif Terakhir</td><td style="font-size:.8rem"><?= date('d/m/Y H:i', strtotime($conv['last_activity'])) ?></td></tr>
        <?php if ($conv['ended_at']): ?>
        <tr><td style="color:var(--text-light);padding:4px 0">Selesai</td><td style="font-size:.8rem"><?= date('d/m/Y H:i', strtotime($conv['ended_at'])) ?></td></tr>
        <?php endif; ?>
      </table>
    </div>

    <?php if (!empty($context)): ?>
    <div class="card">
      <div class="card-title">Context Data</div>
      <pre style="font-size:.75rem;color:var(--text-mid);white-space:pre-wrap;word-break:break-all;margin:0"><?= htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
    <?php endif; ?>

    <div class="card" style="margin-top:16px">
      <div class="card-title">Agent Memory</div>
      <?php if (!$memoryAvailable): ?>
        <p style="margin:0;color:var(--text-light)">Tabel memory agent belum aktif. Jalankan <code>database/add_customer_agent_tables.sql</code>.</p>
      <?php elseif (empty($memories)): ?>
        <?php if (!empty($memoryCounts)): ?>
          <form method="get" style="margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="id" value="<?= $convId ?>">
            <label for="memory_type" style="font-size:.82rem;color:var(--text-mid)">Tipe memory</label>
            <select name="memory_type" id="memory_type" class="input" style="max-width:260px">
              <option value="">Semua tipe</option>
              <?php foreach ($memoryCounts as $memoryCount): ?>
                <?php $typeValue = (string)$memoryCount['memory_type']; ?>
                <option value="<?= htmlspecialchars($typeValue) ?>" <?= $selectedMemoryType === $typeValue ? 'selected' : '' ?>>
                  <?= htmlspecialchars($typeValue) ?> (<?= (int)$memoryCount['total'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline">Filter</button>
            <?php if ($selectedMemoryType !== ''): ?>
              <a href="?id=<?= $convId ?>" class="btn btn-sm btn-outline">Reset</a>
            <?php endif; ?>
          </form>
        <?php endif; ?>
        <p style="margin:0;color:var(--text-light)">Belum ada memory agent untuk customer ini.</p>
      <?php else: ?>
        <form method="get" style="margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <input type="hidden" name="id" value="<?= $convId ?>">
          <label for="memory_type" style="font-size:.82rem;color:var(--text-mid)">Tipe memory</label>
          <select name="memory_type" id="memory_type" class="input" style="max-width:260px">
            <option value="">Semua tipe</option>
            <?php foreach ($memoryCounts as $memoryCount): ?>
              <?php $typeValue = (string)$memoryCount['memory_type']; ?>
              <option value="<?= htmlspecialchars($typeValue) ?>" <?= $selectedMemoryType === $typeValue ? 'selected' : '' ?>>
                <?= htmlspecialchars($typeValue) ?> (<?= (int)$memoryCount['total'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-outline">Filter</button>
          <?php if ($selectedMemoryType !== ''): ?>
            <a href="?id=<?= $convId ?>" class="btn btn-sm btn-outline">Reset</a>
          <?php endif; ?>
        </form>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
          <?php foreach ($memoryCounts as $memoryCount): ?>
            <span class="badge" style="<?= $memoryBadgeStyle((string)$memoryCount['memory_type']) ?>"><?= htmlspecialchars((string)$memoryCount['memory_type']) ?>: <?= (int)$memoryCount['total'] ?></span>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;max-height:420px;overflow:auto">
          <?php foreach ($memories as $memory): ?>
            <?php
              $metadata = [];
              if (!empty($memory['metadata_json'])) {
                  $metadata = json_decode((string)$memory['metadata_json'], true) ?? [];
              }
            ?>
            <div style="border:1px solid var(--border);border-radius:12px;padding:12px;background:#fff">
              <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:6px">
                <span class="badge" style="<?= $memoryBadgeStyle((string)$memory['memory_type']) ?>"><?= htmlspecialchars((string)$memory['memory_type']) ?></span>
                <span style="font-size:.72rem;color:var(--text-light)"><?= date('d/m/y H:i', strtotime((string)$memory['created_at'])) ?></span>
              </div>
              <div style="white-space:pre-line;font-size:.84rem;line-height:1.5"><?= htmlspecialchars((string)$memory['content']) ?></div>
              <?php if (!empty($metadata)): ?>
                <pre style="white-space:pre-wrap;background:#f8fafc;padding:8px;border-radius:8px;overflow:auto;font-size:.72rem;margin-top:8px"><?= htmlspecialchars((string)json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Chat messages -->
  <div class="card">
    <div class="card-title">💬 Pesan (<?= count($messages) ?>)</div>
    <?php if (empty($messages)): ?>
      <p style="color:var(--text-light);text-align:center;padding:32px 0">Belum ada pesan dalam percakapan ini.</p>
    <?php else: ?>
    <div style="max-height:620px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:4px 0">
      <?php foreach ($messages as $msg): ?>
      <?php $isBot = $msg['sender'] === 'bot'; ?>
      <div style="display:flex;<?= $isBot ? 'justify-content:flex-start' : 'justify-content:flex-end' ?>">
        <div style="max-width:75%;min-width:120px">
          <div style="padding:10px 14px;font-size:.875rem;line-height:1.4;
            <?= $isBot
              ? 'background:#fff;border:1px solid var(--border);border-radius:4px 14px 14px 14px'
              : 'background:#DCF8C6;border-radius:14px 4px 14px 14px' ?>">
            <div style="white-space:pre-line"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
          </div>
          <div style="font-size:.7rem;color:var(--text-light);margin-top:3px;<?= $isBot ? 'text-align:left' : 'text-align:right' ?>">
            <?= $isBot ? '🤖 Bot' : '👤 Customer' ?>
            &nbsp;·&nbsp;<?= date('d/m H:i', strtotime($msg['created_at'])) ?>
            <?php if ($msg['intent']): ?>&nbsp;·&nbsp;<em><?= htmlspecialchars($msg['intent']) ?></em><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php
$content = ob_get_clean();
echo View::renderLayout('Detail Percakapan #' . $convId, $content, 'super_admin');
