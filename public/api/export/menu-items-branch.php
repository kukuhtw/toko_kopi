<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Models\BranchModel;
use App\Config\Database;

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId && !Auth::isSuperAdmin()) { http_response_code(403); exit; }
if (Auth::isSuperAdmin()) {
    $branchId = (int)($_GET['branch_id'] ?? 0);
    if (!$branchId) { http_response_code(400); exit; }
}

$currency = (new BranchModel())->getCurrency($branchId);

$db   = Database::getInstance();
$stmt = $db->prepare(
    "SELECT mc.name AS category, mi.name, mi.description,
            mi.price AS global_price_idr,
            COALESCE(bmo.custom_price, mi.price) AS branch_price,
            mi.min_toppings, mi.max_toppings,
            COALESCE(bmo.is_available, mi.is_available) AS is_available,
            mi.is_active
     FROM menu_items mi
     JOIN menu_categories mc ON mi.category_id = mc.id
     LEFT JOIN branch_menu_overrides bmo
           ON bmo.menu_item_id = mi.id AND bmo.branch_id = ?
     ORDER BY mc.sort_order, mi.sort_order, mi.name"
);
$stmt->execute([$branchId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="menu-items-branch-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'Kategori', 'Nama', 'Deskripsi',
    'Harga Global (IDR)', "Harga Cabang ({$currency})",
    'Min Topping', 'Max Topping', 'Tersedia', 'Status'
]);

foreach ($items as $r) {
    fputcsv($out, [
        $r['category'],
        $r['name'],
        $r['description'] ?? '',
        $r['global_price_idr'],
        $r['branch_price'],
        $r['min_toppings'],
        $r['max_toppings'],
        $r['is_available'] ? 'Ya' : 'Tidak',
        $r['is_active'] ? 'Aktif' : 'Nonaktif',
    ]);
}

fclose($out);
exit;
