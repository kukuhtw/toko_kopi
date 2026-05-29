<?php
require_once __DIR__ . '/affiliate_marketing.php';

function affiliate_leaderboard($filters = [])
{
    $db = affiliate_get_pdo();

    $where = [];
    $params = [];

    if (!empty($filters['branch_id'])) {
        $where[] = 'u.branch_id = ?';
        $params[] = $filters['branch_id'];
    }

    if (!empty($filters['date_from'])) {
        $where[] = 'ao.created_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }

    if (!empty($filters['date_to'])) {
        $where[] = 'ao.created_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }

    $sql = "SELECT
                u.id,
                u.affiliate_code,
                u.name,
                COUNT(DISTINCT ao.order_id) AS total_orders,
                COALESCE(SUM(ao.order_total),0) AS total_sales,
                COALESCE(SUM(CASE WHEN ao.status='paid' THEN ao.commission_amount ELSE 0 END),0) AS paid_commission,
                COALESCE(SUM(CASE WHEN ao.status='approved' THEN ao.commission_amount ELSE 0 END),0) AS approved_commission,
                COALESCE(SUM(CASE WHEN ao.status='waiting_clearance' THEN ao.commission_amount ELSE 0 END),0) AS waiting_commission,
                COUNT(DISTINCT ac.id) AS total_clicks
            FROM affiliate_users u
            LEFT JOIN affiliate_orders ao ON ao.affiliate_user_id=u.id
            LEFT JOIN affiliate_clicks ac ON ac.affiliate_user_id=u.id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' GROUP BY u.id,u.affiliate_code,u.name';
    $sql .= ' ORDER BY total_sales DESC, total_orders DESC';
    $sql .= ' LIMIT 100';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rank = 1;
    foreach ($rows as &$row) {
        $clicks = (int)$row['total_clicks'];
        $orders = (int)$row['total_orders'];

        $row['rank'] = $rank++;
        $row['conversion_rate'] = $clicks > 0 ? round(($orders / $clicks) * 100, 2) : 0;
    }

    return $rows;
}
