<?php
require_once __DIR__ . '/../affiliate_marketing.php';

function affiliate_portal_start_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function affiliate_portal_login($emailOrCode, $password)
{
    affiliate_portal_start_session();

    $db = affiliate_get_pdo();
    $sql = "SELECT * FROM affiliate_users
            WHERE (email = ? OR affiliate_code = ?)
              AND status = 'active'
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$emailOrCode, $emailOrCode]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['affiliate_user_id'] = $user['id'];
    $_SESSION['affiliate_code'] = $user['affiliate_code'];
    $_SESSION['affiliate_name'] = $user['name'];

    return true;
}

function affiliate_portal_logout()
{
    affiliate_portal_start_session();
    unset($_SESSION['affiliate_user_id'], $_SESSION['affiliate_code'], $_SESSION['affiliate_name']);
    return true;
}

function affiliate_portal_require_login()
{
    affiliate_portal_start_session();

    if (empty($_SESSION['affiliate_user_id'])) {
        http_response_code(403);
        echo 'Forbidden. Affiliate login required.';
        exit;
    }
}

function affiliate_portal_current_user_id()
{
    affiliate_portal_start_session();
    return (int) ($_SESSION['affiliate_user_id'] ?? 0);
}

function affiliate_portal_profile()
{
    affiliate_portal_require_login();

    $db = affiliate_get_pdo();
    $stmt = $db->prepare("SELECT id, branch_id, affiliate_code, name, email, phone, status, commission_type, commission_value, created_at FROM affiliate_users WHERE id = ? LIMIT 1");
    $stmt->execute([affiliate_portal_current_user_id()]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function affiliate_portal_summary()
{
    affiliate_portal_require_login();

    $db = affiliate_get_pdo();
    $affiliateUserId = affiliate_portal_current_user_id();

    $sql = "SELECT
                COUNT(DISTINCT c.id) AS total_clicks,
                COUNT(DISTINCT o.order_id) AS total_orders,
                COALESCE(SUM(o.order_total), 0) AS total_sales,
                COALESCE(SUM(CASE WHEN o.status = 'pending' THEN o.commission_amount ELSE 0 END), 0) AS pending_commission,
                COALESCE(SUM(CASE WHEN o.status = 'waiting_clearance' THEN o.commission_amount ELSE 0 END), 0) AS waiting_commission,
                COALESCE(SUM(CASE WHEN o.status = 'approved' THEN o.commission_amount ELSE 0 END), 0) AS approved_commission,
                COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.commission_amount ELSE 0 END), 0) AS paid_commission,
                COALESCE(SUM(CASE WHEN o.status IN ('rejected','disputed','cancelled') THEN o.commission_amount ELSE 0 END), 0) AS rejected_commission
            FROM affiliate_users u
            LEFT JOIN affiliate_clicks c ON c.affiliate_user_id = u.id
            LEFT JOIN affiliate_orders o ON o.affiliate_user_id = u.id
            WHERE u.id = ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([$affiliateUserId]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    $clicks = (int) ($summary['total_clicks'] ?? 0);
    $orders = (int) ($summary['total_orders'] ?? 0);
    $summary['conversion_rate'] = $clicks > 0 ? round(($orders / $clicks) * 100, 2) : 0;

    return $summary;
}

function affiliate_portal_campaign_links($baseUrl = null)
{
    affiliate_portal_require_login();

    $db = affiliate_get_pdo();
    $affiliateUserId = affiliate_portal_current_user_id();
    $profile = affiliate_portal_profile();
    $branchId = $profile['branch_id'] ?? null;

    $sql = "SELECT * FROM affiliate_campaigns
            WHERE status = 'active'
              AND (branch_id IS NULL OR branch_id = ?)
            ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$branchId]);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $links = [];
    foreach ($campaigns as $campaign) {
        $targetUrl = $baseUrl ?: $campaign['target_url'];
        $referralUrl = affiliate_generate_link($affiliateUserId, $campaign['id'], $targetUrl);
        $links[] = [
            'campaign_id' => $campaign['id'],
            'campaign_code' => $campaign['campaign_code'],
            'campaign_name' => $campaign['campaign_name'],
            'target_url' => $campaign['target_url'],
            'referral_url' => $referralUrl,
        ];
    }

    return $links;
}

function affiliate_portal_traffic($filters = [])
{
    affiliate_portal_require_login();

    $db = affiliate_get_pdo();
    $params = [affiliate_portal_current_user_id()];
    $where = ['ac.affiliate_user_id = ?'];

    if (!empty($filters['date_from'])) {
        $where[] = 'ac.clicked_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }

    if (!empty($filters['date_to'])) {
        $where[] = 'ac.clicked_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }

    $sql = "SELECT ac.id, ac.tracking_code, ac.ip_address, ac.referrer, ac.landing_url, ac.clicked_at, ac.converted_order_id, c.campaign_name
            FROM affiliate_clicks ac
            LEFT JOIN affiliate_campaigns c ON c.id = ac.campaign_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ac.clicked_at DESC
            LIMIT 300";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function affiliate_portal_orders($filters = [])
{
    affiliate_portal_require_login();

    $db = affiliate_get_pdo();
    $params = [affiliate_portal_current_user_id()];
    $where = ['ao.affiliate_user_id = ?'];

    if (!empty($filters['status'])) {
        $where[] = 'ao.status = ?';
        $params[] = $filters['status'];
    }

    if (!empty($filters['date_from'])) {
        $where[] = 'ao.created_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }

    if (!empty($filters['date_to'])) {
        $where[] = 'ao.created_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }

    $sql = "SELECT ao.order_id, ao.order_total, ao.order_payment_status, ao.order_paid_at, ao.clearance_until,
                   ao.commission_type, ao.commission_value, ao.commission_amount, ao.status,
                   ao.dispute_status, ao.approved_at, ao.paid_at, ao.created_at, c.campaign_name
            FROM affiliate_orders ao
            LEFT JOIN affiliate_campaigns c ON c.id = ao.campaign_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ao.created_at DESC
            LIMIT 300";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function affiliate_portal_commission_breakdown()
{
    affiliate_portal_require_login();

    $db = affiliate_get_pdo();
    $sql = "SELECT status,
                   COUNT(*) AS total_order,
                   COALESCE(SUM(order_total), 0) AS total_sales,
                   COALESCE(SUM(commission_amount), 0) AS total_commission
            FROM affiliate_orders
            WHERE affiliate_user_id = ?
            GROUP BY status
            ORDER BY status";
    $stmt = $db->prepare($sql);
    $stmt->execute([affiliate_portal_current_user_id()]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
