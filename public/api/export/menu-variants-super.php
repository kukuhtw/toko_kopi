<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

$db   = Database::getInstance();
$stmt = $db->prepare(
    "SELECT mi.name AS menu_item, mc.name AS category,
            v.label, v.slug, v.price_delta, v.sort_order, v.is_active
     FROM menu_item_variants v
     JOIN menu_items mi ON v.menu_item_id = mi.id
     JOIN menu_categories mc ON mi.category_id = mc.id
     ORDER BY mc.sort_order, mi.name, v.sort_order, v.label"
);
$stmt->execute();
$variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="menu-variants-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Menu Item', 'Kategori', 'Label Variant', 'Slug', 'Selisih Harga (IDR)', 'Sort Order', 'Status']);

foreach ($variants as $r) {
    fputcsv($out, [
        $r['menu_item'],
        $r['category'],
        $r['label'],
        $r['slug'],
        $r['price_delta'],
        $r['sort_order'],
        $r['is_active'] ? 'Aktif' : 'Nonaktif',
    ]);
}

fclose($out);
exit;
