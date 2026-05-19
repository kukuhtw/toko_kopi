<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Config\Database;

Auth::startSession();
Auth::requireRole('super_admin');

// ── Filter params (sama dengan super/orders.php) ──────────
$branchId = (int)($_GET['branch']  ?? 0);
$q        = trim($_GET['q']        ?? '');
$status   = $_GET['status']        ?? '';
$payment  = $_GET['payment']       ?? '';
$period   = $_GET['period']        ?? '';

if (!in_array($status,  ['', 'pending', 'processing', 'completed', 'cancelled'], true)) { $status  = ''; }
if (!in_array($payment, ['', 'paid', 'unpaid'], true))                                  { $payment = ''; }
if (!in_array($period,  ['', 'today', '7d', '30d'], true))                             { $period  = ''; }

$dateFrom = $dateTo = '';
match ($period) {
    'today' => [$dateFrom, $dateTo] = [date('Y-m-d'), date('Y-m-d')],
    '7d'    => [$dateFrom, $dateTo] = [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    '30d'   => [$dateFrom, $dateTo] = [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
    default => null,
};

// ── Build WHERE ───────────────────────────────────────────
$conditions  = [];
$whereParams = [];

if ($branchId > 0) {
    $conditions[]  = 'o.branch_id = ?';
    $whereParams[] = $branchId;
}
if ($q !== '') {
    $conditions[]  = '(o.order_number LIKE ? OR o.customer_name LIKE ?)';
    $like          = '%' . $q . '%';
    $whereParams[] = $like;
    $whereParams[] = $like;
}
if ($status !== '') {
    $conditions[]  = 'o.order_status = ?';
    $whereParams[] = $status;
}
if ($payment !== '') {
    $conditions[]  = 'o.payment_status = ?';
    $whereParams[] = $payment;
}
if ($dateFrom !== '') {
    $conditions[]  = 'DATE(o.created_at) >= ?';
    $whereParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $conditions[]  = 'DATE(o.created_at) <= ?';
    $whereParams[] = $dateTo;
}

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// ── Query ─────────────────────────────────────────────────
$db   = Database::getInstance();
$stmt = $db->prepare(
    "SELECT o.order_number, b.name AS branch_name,
            o.customer_name, o.customer_wa, o.customer_email,
            o.channel, o.subtotal, o.discount_amount, o.ppn_amount, o.total_amount,
            o.order_status, o.payment_status, o.notes, o.created_at
     FROM orders o
     JOIN branches b ON o.branch_id = b.id
     {$whereClause}
     ORDER BY o.created_at DESC"
);
$stmt->execute($whereParams);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Output CSV ────────────────────────────────────────────
if ($period !== '') {
    $suffix = $period;
} elseif ($dateFrom !== '') {
    $suffix = "{$dateFrom}_{$dateTo}";
} else {
    $suffix = 'all';
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="orders-all-' . date('Ymd-His') . '-' . $suffix . '.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'No. Order', 'Cabang', 'Customer', 'WhatsApp', 'Email',
    'Channel', 'Subtotal', 'Diskon', 'PPN', 'Total',
    'Status Order', 'Status Payment', 'Catatan', 'Tanggal',
]);

foreach ($orders as $o) {
    fputcsv($out, [
        $o['order_number'],
        $o['branch_name'],
        $o['customer_name'],
        $o['customer_wa']    ?? '',
        $o['customer_email'] ?? '',
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
