<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\{Auth, View};
use App\Models\ConversationModel;

Auth::startSession();
Auth::requireRole('super_admin');

$convModel     = new ConversationModel();
$conversations = $convModel->getAll(50);

ob_start();
?>
<div class="section-header">
  <h2>History Percakapan</h2>
</div>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>ID</th><th>Cabang</th><th>Customer</th><th>Channel</th><th>Pesan</th><th>State</th><th>Aktif Terakhir</th><th>Aksi</th></tr>
      </thead>
      <tbody>
      <?php foreach ($conversations as $c): ?>
      <tr>
        <td><?= $c['id'] ?></td>
        <td><?= htmlspecialchars($c['branch_name'] ?? '-') ?></td>
        <td><?= htmlspecialchars($c['customer_name'] ?? $c['customer_identifier'] ?? '-') ?></td>
        <td><span class="badge <?= $c['channel']==='whatsapp' ? 'badge-green' : 'badge-blue' ?>"><?= htmlspecialchars($c['channel']) ?></span></td>
        <td><?= (int)$c['msg_count'] ?> pesan</td>
        <td><span class="badge badge-gray"><?= htmlspecialchars($c['state']) ?></span></td>
        <td style="font-size:.8rem"><?= date('d/m/y H:i', strtotime($c['last_activity'])) ?></td>
        <td><a href="<?= BASE_URL ?>/dashboard/super/conversation-detail.php?id=<?= $c['id'] ?>" class="btn btn-xs btn-outline">Lihat</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($conversations)): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--text-light)">Belum ada percakapan</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$content = ob_get_clean();
echo View::renderLayout('History Percakapan', $content, 'super_admin');
