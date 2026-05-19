<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Config\Database;

Auth::startSession();
Auth::requireLogin();

$db   = Database::getInstance();
$stmt = $db->prepare(
    "SELECT t.name, t.slug, t.price_delta, t.sort_order, t.is_active,
            GROUP_CONCAT(mi.name ORDER BY mi.name SEPARATOR ', ') AS linked_items
     FROM menu_toppings t
     LEFT JOIN menu_item_toppings mit ON mit.topping_id = t.id
     LEFT JOIN menu_items mi ON mi.id = mit.menu_item_id
     GROUP BY t.id
     ORDER BY t.sort_order, t.name"
);
$stmt->execute();
$toppings = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="menu-toppings-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Nama Topping', 'Slug', 'Harga Tambahan (IDR)', 'Sort Order', 'Status', 'Dipakai di Menu']);

foreach ($toppings as $r) {
    fputcsv($out, [
        $r['name'],
        $r['slug'],
        $r['price_delta'],
        $r['sort_order'],
        $r['is_active'] ? 'Aktif' : 'Nonaktif',
        $r['linked_items'] ?? '',
    ]);
}

fclose($out);
exit;
