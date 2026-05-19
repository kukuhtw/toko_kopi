<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

$db   = Database::getInstance();
$stmt = $db->prepare(
    "SELECT mc.name AS category, mi.name, mi.description, mi.price,
            mi.min_toppings, mi.max_toppings,
            mi.is_available, mi.is_active, mi.created_at
     FROM menu_items mi
     JOIN menu_categories mc ON mi.category_id = mc.id
     ORDER BY mc.sort_order, mi.sort_order, mi.name"
);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="menu-items-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'Kategori', 'Nama', 'Deskripsi', 'Harga (IDR)',
    'Min Topping', 'Max Topping', 'Tersedia', 'Status', 'Dibuat'
]);

foreach ($items as $r) {
    fputcsv($out, [
        $r['category'],
        $r['name'],
        $r['description'] ?? '',
        $r['price'],
        $r['min_toppings'],
        $r['max_toppings'],
        $r['is_available'] ? 'Ya' : 'Tidak',
        $r['is_active'] ? 'Aktif' : 'Nonaktif',
        $r['created_at'],
    ]);
}

fclose($out);
exit;
