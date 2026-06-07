<?php
require_once __DIR__ . '/../controllers/AffiliateAdminController.php';

use KopiBot\Security\Csrf;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string) ($_POST['action'] ?? '');

        switch ($action) {
            case 'save_user':
                $id = affiliate_admin_save_user_from_post();
                $message = 'Affiliate berhasil dibuat dengan ID #' . (int) $id . '.';
                break;

            case 'ban_user':
                affiliate_admin_ban_user_from_post();
                $message = 'Affiliate berhasil diblokir.';
                break;

            case 'save_campaign':
                $id = affiliate_admin_save_campaign_from_post();
                $message = 'Campaign berhasil dibuat dengan ID #' . (int) $id . '.';
                break;

            case 'mark_commission_paid':
                affiliate_admin_mark_commission_paid_from_post();
                $message = 'Komisi berhasil ditandai paid.';
                break;
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$summary = affiliate_admin_dashboard_summary();
$users = [];
$campaigns = [];
$commissions = [];

try {
    $users = affiliate_admin_list_users();
} catch (Throwable $exception) {
}

try {
    $campaigns = affiliate_admin_list_campaigns();
} catch (Throwable $exception) {
}

try {
    $commissions = affiliate_admin_commission_report(['status' => 'approved']);
} catch (Throwable $exception) {
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Affiliate Admin Dashboard</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;padding:24px;color:#1f2937}
        .wrap{max-width:1200px;margin:0 auto}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
        .card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
        .label{font-size:13px;color:#6b7280;margin-bottom:8px}.value{font-size:28px;font-weight:700}
        .nav a{display:inline-block;margin:0 8px 12px 0;padding:10px 14px;background:#111827;color:#fff;border-radius:10px;text-decoration:none;font-size:14px}
        .stack{display:grid;gap:16px;margin-top:20px}
        .two-col{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}
        .field{display:grid;gap:6px;margin-bottom:10px}
        .field input,.field select,.field textarea{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;background:#fff;box-sizing:border-box}
        .btn{display:inline-block;padding:10px 14px;background:#111827;color:#fff;border:none;border-radius:10px;cursor:pointer}
        .btn-alt{background:#b91c1c}
        .msg{padding:12px 14px;border-radius:12px;margin:14px 0}
        .msg.ok{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
        .msg.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        table{width:100%;border-collapse:collapse;font-size:14px}
        th,td{padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
        .actions{display:flex;gap:10px;flex-wrap:wrap}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Affiliate Admin Dashboard</h1>
    <div class="nav">
        <a href="?page=users">Affiliate Users</a>
        <a href="?page=campaigns">Campaigns</a>
        <a href="?page=traffic">Traffic</a>
        <a href="?page=commissions">Commissions</a>
        <a href="../routes.php?page=export_commissions">Export Commissions CSV</a>
        <a href="../routes.php?page=export_traffic">Export Traffic CSV</a>
        <a href="../routes.php?page=export_fraud">Export Fraud CSV</a>
    </div>
    <?php if ($message !== ''): ?>
        <div class="msg ok"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="msg err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <div class="grid">
        <div class="card"><div class="label">Total Affiliate</div><div class="value"><?= number_format((float)($summary['total_affiliates'] ?? 0)) ?></div></div>
        <div class="card"><div class="label">Affiliate Aktif</div><div class="value"><?= number_format((float)($summary['active_affiliates'] ?? 0)) ?></div></div>
        <div class="card"><div class="label">Total Order</div><div class="value"><?= number_format((float)($summary['total_orders'] ?? 0)) ?></div></div>
        <div class="card"><div class="label">Total Sales</div><div class="value">Rp <?= number_format((float)($summary['total_sales'] ?? 0),0,',','.') ?></div></div>
        <div class="card"><div class="label">Waiting Commission</div><div class="value">Rp <?= number_format((float)($summary['waiting_commission'] ?? 0),0,',','.') ?></div></div>
        <div class="card"><div class="label">Approved Commission</div><div class="value">Rp <?= number_format((float)($summary['approved_commission'] ?? 0),0,',','.') ?></div></div>
        <div class="card"><div class="label">Paid Commission</div><div class="value">Rp <?= number_format((float)($summary['paid_commission'] ?? 0),0,',','.') ?></div></div>
    </div>

    <div class="stack">
        <div class="two-col">
            <div class="card">
                <h2>Buat Affiliate</h2>
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="save_user">
                    <div class="field">
                        <label>Nama</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                    <div class="field">
                        <label>Telepon</label>
                        <input type="text" name="phone">
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <input type="password" name="password">
                    </div>
                    <div class="field">
                        <label>Tipe Komisi</label>
                        <select name="commission_type">
                            <option value="percent">Percent</option>
                            <option value="fixed">Fixed</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Nilai Komisi</label>
                        <input type="number" step="0.01" min="0" name="commission_value" value="0">
                    </div>
                    <button class="btn" type="submit">Simpan Affiliate</button>
                </form>
            </div>

            <div class="card">
                <h2>Buat Campaign</h2>
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="save_campaign">
                    <div class="field">
                        <label>Nama Campaign</label>
                        <input type="text" name="campaign_name" required>
                    </div>
                    <div class="field">
                        <label>Target URL</label>
                        <input type="url" name="target_url" required>
                    </div>
                    <div class="field">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>
                    <div class="field">
                        <label>Status</label>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <button class="btn" type="submit">Simpan Campaign</button>
                </form>
            </div>
        </div>

        <div class="two-col">
            <div class="card">
                <h2>Blokir Affiliate</h2>
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="ban_user">
                    <div class="field">
                        <label>Affiliate User ID</label>
                        <input type="number" min="1" name="affiliate_user_id" required>
                    </div>
                    <div class="field">
                        <label>Alasan</label>
                        <textarea name="reason" rows="3">Banned by admin</textarea>
                    </div>
                    <button class="btn btn-alt" type="submit">Blokir Affiliate</button>
                </form>
            </div>

            <div class="card">
                <h2>Tandai Komisi Paid</h2>
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="mark_commission_paid">
                    <div class="field">
                        <label>Affiliate Order ID</label>
                        <input type="number" min="1" name="affiliate_order_id" required>
                    </div>
                    <button class="btn" type="submit">Set Paid</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>Affiliate Terbaru</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Komisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($users, 0, 10) as $user): ?>
                        <tr>
                            <td><?= (int) ($user['id'] ?? 0) ?></td>
                            <td><?= htmlspecialchars((string) ($user['affiliate_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($user['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($user['commission_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) ($user['commission_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($users === []): ?>
                        <tr><td colspan="5">Belum ada data atau permission tidak tersedia.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="two-col">
            <div class="card">
                <h2>Campaign Terbaru</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($campaigns, 0, 10) as $campaign): ?>
                            <tr>
                                <td><?= (int) ($campaign['id'] ?? 0) ?></td>
                                <td><?= htmlspecialchars((string) ($campaign['campaign_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($campaign['campaign_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($campaign['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($campaigns === []): ?>
                            <tr><td colspan="4">Belum ada data atau permission tidak tersedia.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>Komisi Approved</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Affiliate</th>
                            <th>Order</th>
                            <th>Komisi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($commissions, 0, 10) as $commission): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($commission['affiliate_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>#<?= (int) ($commission['order_id'] ?? 0) ?></td>
                                <td>Rp <?= number_format((float) ($commission['commission_amount'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars((string) ($commission['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($commissions === []): ?>
                            <tr><td colspan="4">Belum ada data approved atau permission tidak tersedia.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
