<?php
require_once __DIR__ . '/../affiliate_marketing.php';

function affiliate_admin_require_login()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        echo 'Forbidden. Admin login required.';
        exit;
    }
}

function affiliate_admin_current_branch_id()
{
    return $_SESSION['branch_id'] ?? null;
}

function affiliate_admin_is_super_admin()
{
    $role = $_SESSION['admin_role'] ?? '';
    return in_array($role, ['super_admin', 'admin_pusat', 'owner'], true);
}

function affiliate_admin_list_users($filters = [])
{
    affiliate_admin_require_login();

    $db = affiliate_get_pdo();
    $where = [];
    $params = [];

    if (!affiliate_admin_is_super_admin()) {
        $where[] = 'branch_id = ?';
        $params[] = affiliate_admin_current_branch_id();
    } elseif (!empty($filters['branch_id'])) {
        $where[] = 'branch_id = ?';
        $params[] = $filters['branch_id'];
    }

    if (!empty($filters['status'])) {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
    }

    if (!empty($filters['keyword'])) {
        $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR affiliate_code LIKE ?)';
        $kw = '%' . $filters['keyword'] . '%';
        array_push($params, $kw, $kw, $kw, $kw);
    }

    $sql = 'SELECT * FROM affiliate_users';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 200';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function affiliate_admin_save_user_from_post()
{
    affiliate_admin_require_login();

    $data = [
        'branch_id' => affiliate_admin_is_super_admin() ? ($_POST['branch_id'] ?? null) : affiliate_admin_current_branch_id(),
        'affiliate_code' => $_POST['affiliate_code'] ?? null,
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'password' => $_POST['password'] ?? null,
        'status' => $_POST['status'] ?? 'active',
        'commission_type' => $_POST['commission_type'] ?? 'percent',
        'commission_value' => $_POST['commission_value'] ?? 0,
        'created_by_admin_id' => $_SESSION['admin_id'] ?? null,
    ];

    if ($data['name'] === '') {
        throw new InvalidArgumentException('Affiliate name is required.');
    }

    return affiliate_create_user($data);
}

function affiliate_admin_ban_user_from_post()
{
    affiliate_admin_require_login();

    $affiliateUserId = (int) ($_POST['affiliate_user_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Banned by admin');

    if ($affiliateUserId <= 0) {
        throw new InvalidArgumentException('Invalid affiliate user id.');
    }

    if (!affiliate_admin_can_access_affiliate($affiliateUserId)) {
        http_response_code(403);
        echo 'Forbidden. Affiliate user is outside your branch scope.';
        exit;
    }

    return affiliate_ban_user($affiliateUserId, $reason);
}

function affiliate_admin_can_access_affiliate($affiliateUserId)
{
    if (affiliate_admin_is_super_admin()) {
        return true;
    }

    $db = affiliate_get_pdo();
    $stmt = $db->prepare('SELECT id FROM affiliate_users WHERE id = ? AND branch_id = ? LIMIT 1');
    $stmt->execute([$affiliateUserId, affiliate_admin_current_branch_id()]);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function affiliate_admin_list_campaigns($filters = [])
{
    affiliate_admin_require_login();

    $db = affiliate_get_pdo();
    $where = [];
    $params = [];

    if (!affiliate_admin_is_super_admin()) {
        $where[] = '(branch_id = ? OR branch_id IS NULL)';
        $params[] = affiliate_admin_current_branch_id();
    } elseif (!empty($filters['branch_id'])) {
        $where[] = 'branch_id = ?';
        $params[] = $filters['branch_id'];
    }

    if (!empty($filters['status'])) {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
    }

    $sql = 'SELECT * FROM affiliate_campaigns';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 200';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function affiliate_admin_save_campaign_from_post()
{
    affiliate_admin_require_login();

    $data = [
        'branch_id' => affiliate_admin_is_super_admin() ? ($_POST['branch_id'] ?? null) : affiliate_admin_current_branch_id(),
        'campaign_code' => $_POST['campaign_code'] ?? null,
        'campaign_name' => trim($_POST['campaign_name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'target_url' => trim($_POST['target_url'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
        'start_date' => $_POST['start_date'] ?? null,
        'end_date' => $_POST['end_date'] ?? null,
    ];

    if ($data['campaign_name'] === '' || $data['target_url'] === '') {
        throw new InvalidArgumentException('Campaign name and target URL are required.');
    }

    return affiliate_create_campaign($data);
}

function affiliate_admin_commission_report($filters = [])
{
    affiliate_admin_require_login();

    $db = affiliate_get_pdo();
    $where = [];
    $params = [];

    if (!affiliate_admin_is_super_admin()) {
        $where[] = 'u.branch_id = ?';
        $params[] = affiliate_admin_current_branch_id();
    } elseif (!empty($filters['branch_id'])) {
        $where[] = 'u.branch_id = ?';
        $params[] = $filters['branch_id'];
    }

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

    $sql = "SELECT ao.*, u.name AS affiliate_name, u.affiliate_code, c.campaign_name
            FROM affiliate_orders ao
            JOIN affiliate_users u ON u.id = ao.affiliate_user_id
            LEFT JOIN affiliate_campaigns c ON c.id = ao.campaign_id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY ao.created_at DESC LIMIT 500';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function affiliate_admin_traffic_report($filters = [])
{
    affiliate_admin_require_login();

    $db = affiliate_get_pdo();
    $where = [];
    $params = [];

    if (!affiliate_admin_is_super_admin()) {
        $where[] = 'u.branch_id = ?';
        $params[] = affiliate_admin_current_branch_id();
    } elseif (!empty($filters['branch_id'])) {
        $where[] = 'u.branch_id = ?';
        $params[] = $filters['branch_id'];
    }

    if (!empty($filters['date_from'])) {
        $where[] = 'ac.clicked_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }

    if (!empty($filters['date_to'])) {
        $where[] = 'ac.clicked_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }

    $sql = "SELECT ac.*, u.name AS affiliate_name, u.affiliate_code, c.campaign_name
            FROM affiliate_clicks ac
            JOIN affiliate_users u ON u.id = ac.affiliate_user_id
            LEFT JOIN affiliate_campaigns c ON c.id = ac.campaign_id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY ac.clicked_at DESC LIMIT 500';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function affiliate_admin_dashboard_summary($filters = [])
{
    affiliate_admin_require_login();

    $db = affiliate_get_pdo();
    $branchWhere = '';
    $params = [];

    if (!affiliate_admin_is_super_admin()) {
        $branchWhere = ' WHERE u.branch_id = ?';
        $params[] = affiliate_admin_current_branch_id();
    } elseif (!empty($filters['branch_id'])) {
        $branchWhere = ' WHERE u.branch_id = ?';
        $params[] = $filters['branch_id'];
    }

    $sql = "SELECT
                COUNT(DISTINCT u.id) AS total_affiliates,
                SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) AS active_affiliates,
                COUNT(DISTINCT ao.order_id) AS total_orders,
                COALESCE(SUM(ao.order_total), 0) AS total_sales,
                COALESCE(SUM(CASE WHEN ao.status = 'approved' THEN ao.commission_amount ELSE 0 END), 0) AS approved_commission,
                COALESCE(SUM(CASE WHEN ao.status = 'waiting_clearance' THEN ao.commission_amount ELSE 0 END), 0) AS waiting_commission,
                COALESCE(SUM(CASE WHEN ao.status = 'paid' THEN ao.commission_amount ELSE 0 END), 0) AS paid_commission
            FROM affiliate_users u
            LEFT JOIN affiliate_orders ao ON ao.affiliate_user_id = u.id" . $branchWhere;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function affiliate_admin_mark_commission_paid_from_post()
{
    affiliate_admin_require_login();

    $affiliateOrderId = (int) ($_POST['affiliate_order_id'] ?? 0);
    if ($affiliateOrderId <= 0) {
        throw new InvalidArgumentException('Invalid affiliate order id.');
    }

    return affiliate_mark_commission_paid($affiliateOrderId);
}
