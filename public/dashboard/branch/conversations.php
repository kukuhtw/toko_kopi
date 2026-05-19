<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};
use App\Models\AgentMemoryModel;
use App\Models\ConversationModel;

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId) { exit; }

$convModel     = new ConversationModel();
$conversations = $convModel->getVisibleByBranch($branchId, 30);
$agentMemoryModel = new AgentMemoryModel();
$memoryAvailable = $agentMemoryModel->isAvailable();
$memoryBadgeStyle = static function (string $type): string {
    return match ($type) {
        'customer_preference' => 'background:#ecfeff;color:#0f766e;border:1px solid #99f6e4;',
        'successful_advisory' => 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;',
        'recommendation_phrase' => 'background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;',
        'conversation_summary' => 'background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe;',
        default => 'background:#f8fafc;color:#475569;border:1px solid #cbd5e1;',
    };
};

// View specific conversation
$viewConvId = (int)($_GET['id'] ?? 0);
$selectedMemoryType = trim((string)($_GET['memory_type'] ?? ''));
$messages   = [];
$selectedConversation = null;
$memories = [];
$memoryCounts = [];
if ($viewConvId) {
    $selectedConversation = $convModel->findVisibleByBranch($viewConvId, $branchId);
    if ($selectedConversation) {
        $messages = $convModel->getMergedVisibleMessages($viewConvId, $branchId, 200);
        if ($memoryAvailable) {
            $customerKey = (string)$selectedConversation['channel'] . ':' . $branchId . ':' . (int)($selectedConversation['customer_id'] ?? 0);
            $memoryCounts = $agentMemoryModel->getGroupedCounts('customer', $customerKey);
            $memories = $selectedMemoryType !== ''
                ? $agentMemoryModel->getByEntityKeyAndType('customer', $customerKey, $selectedMemoryType, 30)
                : $agentMemoryModel->getByEntityKey('customer', $customerKey, 30);
        }
    }
}

ob_start();
?>
<div class="section-header">
  <h2>Percakapan Customer</h2>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px">
  <div class="card">
    <div class="card-title">Riwayat Chat</div>
    <?php foreach ($conversations as $c): ?>
    <a href="?id=<?= (int)$c['id'] ?>" style="display:block;padding:12px 0;border-bottom:1px solid var(--border);text-decoration:none;color:inherit;<?= $viewConvId===(int)$c['id'] ? 'background:var(--coffee-cream);' : '' ?>">
      <div style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($c['customer_name'] ?? $c['customer_identifier']) ?></div>
      <div style="font-size:.75rem;color:var(--text-light)">
        <?= (int)$c['msg_count'] ?> pesan · <?= htmlspecialchars($c['channel']) ?>
        · <?= date('d/m H:i', strtotime($c['last_activity'])) ?>
      </div>
      <?php if (!empty($c['is_shared_routed'])): ?>
      <div style="font-size:.72rem;color:#8a5a12">via shared inbox host <?= htmlspecialchars($c['source_branch_name'] ?? '-') ?></div>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php if (empty($conversations)): ?>
      <p style="color:var(--text-light)">Belum ada percakapan.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <?php if ($viewConvId && !empty($messages)): ?>
    <div class="card-title">Detail Percakapan #<?= $viewConvId ?></div>
    <?php if (!empty($selectedConversation['shared_conversation_id']) && (int)$selectedConversation['shared_conversation_id'] !== (int)$selectedConversation['id']): ?>
    <div style="font-size:.78rem;color:var(--text-mid);margin-bottom:12px;background:var(--bg-light,#faf9f7);border-radius:8px;padding:10px 12px;line-height:1.6">
      Timeline ini sudah digabung:
      tahap awal shared inbox dari host <strong><?= htmlspecialchars($selectedConversation['source_branch_name'] ?? '-') ?></strong>
      + percakapan bisnis cabang ini.
    </div>
    <?php endif; ?>
    <div style="max-height:600px;overflow-y:auto;display:flex;flex-direction:column;gap:8px">
      <?php foreach ($messages as $msg): ?>
      <div style="display:flex;<?= $msg['sender']==='bot' ? 'justify-content:flex-start' : 'justify-content:flex-end' ?>">
        <div style="max-width:70%;padding:10px 14px;border-radius:12px;font-size:.9rem;
          <?= $msg['sender']==='bot'
            ? 'background:#fff;border:1px solid var(--border);border-radius:0 12px 12px 12px'
            : 'background:#DCF8C6;border-radius:12px 0 12px 12px' ?>">
          <div style="white-space:pre-line"><?= htmlspecialchars($msg['message']) ?></div>
          <div style="font-size:.65rem;color:var(--text-light);margin-top:4px;text-align:right">
            <?= htmlspecialchars($msg['sender']) ?> · <?= date('H:i', strtotime($msg['created_at'])) ?>
            <?php if ($msg['intent']): ?> · <em><?= htmlspecialchars($msg['intent']) ?></em><?php endif; ?>
            <?php if (!empty($msg['is_shared_inbox_message'])): ?> · <em>shared inbox</em><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:18px;border-top:1px solid var(--border);padding-top:16px">
      <div class="card-title">Agent Memory</div>
      <?php if (!$memoryAvailable): ?>
        <p style="color:var(--text-light)">Tabel memory agent belum aktif. Jalankan <code>database/add_customer_agent_tables.sql</code>.</p>
      <?php elseif (empty($memories)): ?>
        <?php if (!empty($memoryCounts)): ?>
          <form method="get" style="margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="id" value="<?= $viewConvId ?>">
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
              <a href="?id=<?= $viewConvId ?>" class="btn btn-sm btn-outline">Reset</a>
            <?php endif; ?>
          </form>
        <?php endif; ?>
        <p style="color:var(--text-light)">Belum ada memory agent untuk customer ini.</p>
      <?php else: ?>
        <form method="get" style="margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <input type="hidden" name="id" value="<?= $viewConvId ?>">
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
            <a href="?id=<?= $viewConvId ?>" class="btn btn-sm btn-outline">Reset</a>
          <?php endif; ?>
        </form>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
          <?php foreach ($memoryCounts as $memoryCount): ?>
            <span class="badge" style="<?= $memoryBadgeStyle((string)$memoryCount['memory_type']) ?>"><?= htmlspecialchars((string)$memoryCount['memory_type']) ?>: <?= (int)$memoryCount['total'] ?></span>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px">
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
    <?php elseif ($viewConvId): ?>
      <p style="color:var(--text-light)">Percakapan tidak ditemukan atau belum ada pesan.</p>
    <?php else: ?>
      <div style="display:flex;align-items:center;justify-content:center;height:300px;color:var(--text-light)">
        Pilih percakapan untuk melihat pesan
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
$content = ob_get_clean();
echo View::renderLayout('Percakapan Customer', $content, 'branch_admin');
