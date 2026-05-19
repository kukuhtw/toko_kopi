<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

$db   = Database::getInstance();
$stmt = $db->prepare(
    "SELECT u.name, u.email, u.role, b.name AS branch_name,
            u.is_active, u.last_login, u.created_at
     FROM users u
     LEFT JOIN branches b ON u.branch_id = b.id
     ORDER BY u.role, u.name"
);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="users-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Nama', 'Email', 'Role', 'Cabang', 'Status', 'Login Terakhir', 'Terdaftar']);

foreach ($users as $u) {
    fputcsv($out, [
        $u['name'],
        $u['email'],
        str_replace('_', ' ', $u['role']),
        $u['branch_name'] ?? '—',
        $u['is_active'] ? 'Aktif' : 'Nonaktif',
        $u['last_login'] ?? '',
        $u['created_at'],
    ]);
}

fclose($out);
exit;
