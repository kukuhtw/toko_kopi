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
    "SELECT mi.name AS menu_item, mc.name AS category,
            v.label, v.price_delta AS global_delta_idr,
            bvo.price_delta AS branch_delta,
            v.is_active,
            CASE WHEN bvo.id IS NOT NULL THEN 'Ya' ELSE 'Tidak' END AS has_override
     FROM menu_item_variants v
     JOIN menu_items mi ON v.menu_item_id = mi.id
     JOIN menu_categories mc ON mi.category_id = mc.id
     LEFT JOIN branch_menu_variant_overrides bvo
           ON bvo.variant_id = v.id AND bvo.branch_id = ? AND bvo.is_active = 1
     ORDER BY mc.sort_order, mi.name, v.sort_order, v.label"
);
$stmt->execute([$branchId]);
$variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="menu-variants-branch-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'Menu Item', 'Kategori', 'Label Variant',
    'Selisih Global (IDR)', "Selisih Cabang ({$currency})", 'Ada Override', 'Status'
]);

foreach ($variants as $r) {
    fputcsv($out, [
        $r['menu_item'],
        $r['category'],
        $r['label'],
        $r['global_delta_idr'],
        $r['branch_delta'] ?? '',
        $r['has_override'],
        $r['is_active'] ? 'Aktif' : 'Nonaktif',
    ]);
}

fclose($out);
exit;
