<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Config\Database;

Auth::startSession();
Auth::requireLogin();

$user     = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId) { http_response_code(403); exit; }

$dateFrom = $_GET['from']   ?? date('Y-m-01');
$dateTo   = $_GET['to']     ?? date('Y-m-d');
$status   = $_GET['status'] ?? 'completed';

$allowedStatuses = ['completed', 'cancelled', 'all'];
if (!in_array($status, $allowedStatuses)) { $status = 'completed'; }

$statusClause = $status === 'all'
    ? "o.order_status IN ('completed','cancelled')"
    : "o.order_status = ?";

$params = $status === 'all'
    ? [$branchId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
    : [$branchId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59', $status];

$db   = Database::getInstance();
$stmt = $db->prepare(
    "SELECT o.order_number, o.customer_name, o.customer_wa,
            o.channel, o.subtotal, o.discount_amount, o.ppn_amount, o.total_amount,
            o.order_status, o.payment_status, o.notes, o.created_at
     FROM orders o
     WHERE o.branch_id = ? AND {$statusClause}
       AND o.created_at BETWEEN ? AND ?
     ORDER BY o.created_at DESC"
);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$periodLabel = $dateFrom . '_sd_' . $dateTo;
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="history-' . $periodLabel . '.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'No. Order', 'Customer', 'WhatsApp',
    'Channel', 'Subtotal', 'Diskon', 'PPN', 'Total',
    'Status Order', 'Status Payment', 'Catatan', 'Tanggal'
]);

foreach ($orders as $o) {
    fputcsv($out, [
        $o['order_number'],
        $o['customer_name'],
        $o['customer_wa']    ?? '',
        $o['channel'],
        $o['subtotal'],
        $o['discount_amount'],
        $o['ppn_amount'],
        $o['total_amount'],
        $o['order_status'],
        $o['payment_status'],
        $o['notes']          ?? '',
        $o['created_at'],
    ]);
}

fclose($out);
exit;
