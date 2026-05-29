<?php
/**
 * Affiliate Marketing Plugin Core Service
 * KopiBot / AI Agent Commerce Platform
 *
 * Business rule:
 * Commission is not payable when order is created.
 * Commission becomes waiting_clearance only after payment status is paid.
 * Commission becomes approved after 7 days without dispute, refund, or cancellation.
 */

if (!defined('AFFILIATE_COOKIE_DAYS')) {
    define('AFFILIATE_COOKIE_DAYS', 30);
}

if (!defined('AFFILIATE_CLEARANCE_DAYS')) {
    define('AFFILIATE_CLEARANCE_DAYS', 7);
}

function affiliate_get_pdo()
{
    global $pdo, $conn, $mysqli;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if ($conn instanceof PDO) {
        return $conn;
    }

    if ($mysqli instanceof mysqli) {
        throw new RuntimeException('Affiliate plugin currently expects PDO. Please map mysqli connection to PDO adapter or update affiliate_get_pdo().');
    }

    throw new RuntimeException('Database connection not found. Expected global $pdo or $conn as PDO.');
}

function affiliate_now()
{
    return date('Y-m-d H:i:s');
}

function affiliate_generate_code($prefix = 'AFF')
{
    return strtoupper($prefix . '-' . bin2hex(random_bytes(4)));
}

function affiliate_find_active_user_by_code($affiliateCode)
{
    $db = affiliate_get_pdo();
    $sql = "SELECT * FROM affiliate_users WHERE affiliate_code = ? AND status = 'active' LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$affiliateCode]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function affiliate_find_campaign_by_code($campaignCode)
{
    if (!$campaignCode) {
        return null;
    }

    $db = affiliate_get_pdo();
    $sql = "SELECT * FROM affiliate_campaigns WHERE campaign_code = ? AND status = 'active' LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$campaignCode]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function affiliate_track_visit_from_request()
{
    $affiliateCode = $_GET['aff'] ?? $_GET['ref'] ?? null;
    $campaignCode = $_GET['campaign'] ?? null;

    if (!$affiliateCode) {
        return false;
    }

    $affiliate = affiliate_find_active_user_by_code($affiliateCode);
    if (!$affiliate) {
        return false;
    }

    $campaign = affiliate_find_campaign_by_code($campaignCode);
    $campaignId = $campaign['id'] ?? null;
    $trackingCode = $affiliateCode . ($campaignCode ? '-' . $campaignCode : '');

    $cookieExpires = time() + (AFFILIATE_COOKIE_DAYS * 24 * 60 * 60);
    setcookie('affiliate_code', $affiliateCode, $cookieExpires, '/');
    setcookie('affiliate_campaign', (string) $campaignCode, $cookieExpires, '/');
    setcookie('affiliate_tracking_code', $trackingCode, $cookieExpires, '/');

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $_SESSION['affiliate_code'] = $affiliateCode;
    $_SESSION['affiliate_campaign'] = $campaignCode;
    $_SESSION['affiliate_tracking_code'] = $trackingCode;

    $db = affiliate_get_pdo();
    $sql = "INSERT INTO affiliate_clicks
        (affiliate_user_id, campaign_id, tracking_code, session_id, ip_address, user_agent, referrer, landing_url, clicked_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $affiliate['id'],
        $campaignId,
        $trackingCode,
        session_id(),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $_SERVER['HTTP_REFERER'] ?? null,
        affiliate_current_url(),
        affiliate_now()
    ]);

    affiliate_increment_link_click($affiliate['id'], $campaignId, $trackingCode);

    return true;
}

function affiliate_current_url()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

function affiliate_increment_link_click($affiliateUserId, $campaignId, $trackingCode)
{
    if (!$campaignId) {
        return;
    }

    $db = affiliate_get_pdo();
    $sql = "UPDATE affiliate_links SET total_click = total_click + 1 WHERE affiliate_user_id = ? AND campaign_id = ? AND tracking_code = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$affiliateUserId, $campaignId, $trackingCode]);
}

function affiliate_create_order_record($orderId, $orderTotal)
{
    $affiliateCode = $_SESSION['affiliate_code'] ?? $_COOKIE['affiliate_code'] ?? null;
    $campaignCode = $_SESSION['affiliate_campaign'] ?? $_COOKIE['affiliate_campaign'] ?? null;
    $trackingCode = $_SESSION['affiliate_tracking_code'] ?? $_COOKIE['affiliate_tracking_code'] ?? null;

    if (!$affiliateCode) {
        return false;
    }

    $affiliate = affiliate_find_active_user_by_code($affiliateCode);
    if (!$affiliate) {
        return false;
    }

    $campaign = affiliate_find_campaign_by_code($campaignCode);
    $campaignId = $campaign['id'] ?? null;
    $commissionType = $affiliate['commission_type'] ?: 'percent';
    $commissionValue = (float) $affiliate['commission_value'];
    $commissionAmount = affiliate_calculate_commission($orderTotal, $commissionType, $commissionValue);

    $db = affiliate_get_pdo();

    $sql = "INSERT INTO affiliate_orders
        (order_id, affiliate_user_id, campaign_id, tracking_code, order_total, order_payment_status,
         commission_type, commission_value, commission_amount, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'unpaid', ?, ?, ?, 'pending', ?)";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $orderId,
        $affiliate['id'],
        $campaignId,
        $trackingCode,
        $orderTotal,
        $commissionType,
        $commissionValue,
        $commissionAmount,
        affiliate_now()
    ]);

    affiliate_update_click_conversion($orderId, $affiliate['id'], $trackingCode);

    return true;
}

function affiliate_calculate_commission($orderTotal, $commissionType, $commissionValue)
{
    $orderTotal = (float) $orderTotal;
    $commissionValue = (float) $commissionValue;

    if ($commissionType === 'fixed') {
        return $commissionValue;
    }

    return round(($orderTotal * $commissionValue) / 100, 2);
}

function affiliate_update_click_conversion($orderId, $affiliateUserId, $trackingCode)
{
    $db = affiliate_get_pdo();
    $sql = "UPDATE affiliate_clicks
            SET converted_order_id = ?
            WHERE affiliate_user_id = ?
              AND tracking_code = ?
              AND converted_order_id IS NULL
            ORDER BY clicked_at DESC
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$orderId, $affiliateUserId, $trackingCode]);
}

function affiliate_mark_order_paid($orderId, $paidAt = null)
{
    $paidAt = $paidAt ?: affiliate_now();
    $clearanceUntil = date('Y-m-d H:i:s', strtotime($paidAt . ' +' . AFFILIATE_CLEARANCE_DAYS . ' days'));

    $db = affiliate_get_pdo();
    $sql = "UPDATE affiliate_orders
            SET order_payment_status = 'paid',
                order_paid_at = ?,
                clearance_days = ?,
                clearance_until = ?,
                status = 'waiting_clearance'
            WHERE order_id = ?
              AND status IN ('pending')";

    $stmt = $db->prepare($sql);
    return $stmt->execute([$paidAt, AFFILIATE_CLEARANCE_DAYS, $clearanceUntil, $orderId]);
}

function affiliate_mark_order_disputed($orderId, $reason = 'Order disputed')
{
    $db = affiliate_get_pdo();
    $now = affiliate_now();

    $sql = "UPDATE affiliate_orders
            SET status = 'disputed',
                dispute_status = 'disputed',
                dispute_at = ?,
                rejection_reason = ?
            WHERE order_id = ?
              AND status IN ('pending','waiting_clearance','approved')";

    $stmt = $db->prepare($sql);
    return $stmt->execute([$now, $reason, $orderId]);
}

function affiliate_mark_order_refunded($orderId, $reason = 'Order refunded')
{
    $db = affiliate_get_pdo();
    $now = affiliate_now();

    $sql = "UPDATE affiliate_orders
            SET order_payment_status = 'refunded',
                status = 'rejected',
                dispute_status = 'refund',
                dispute_at = ?,
                rejection_reason = ?
            WHERE order_id = ?
              AND status IN ('pending','waiting_clearance','approved','disputed')";

    $stmt = $db->prepare($sql);
    return $stmt->execute([$now, $reason, $orderId]);
}

function affiliate_mark_order_cancelled($orderId, $reason = 'Order cancelled')
{
    $db = affiliate_get_pdo();
    $now = affiliate_now();

    $sql = "UPDATE affiliate_orders
            SET order_payment_status = 'cancelled',
                status = 'cancelled',
                dispute_status = 'cancelled',
                dispute_at = ?,
                rejection_reason = ?
            WHERE order_id = ?
              AND status IN ('pending','waiting_clearance','approved','disputed')";

    $stmt = $db->prepare($sql);
    return $stmt->execute([$now, $reason, $orderId]);
}

function affiliate_approve_eligible_commissions()
{
    $db = affiliate_get_pdo();
    $now = affiliate_now();

    $sql = "UPDATE affiliate_orders
            SET status = 'approved',
                approved_eligible_at = ?,
                approved_at = ?
            WHERE order_payment_status = 'paid'
              AND status = 'waiting_clearance'
              AND dispute_status = 'none'
              AND clearance_until <= ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([$now, $now, $now]);

    return $stmt->rowCount();
}

function affiliate_mark_commission_paid($affiliateOrderId)
{
    $db = affiliate_get_pdo();
    $now = affiliate_now();

    $sql = "UPDATE affiliate_orders
            SET status = 'paid', paid_at = ?
            WHERE id = ? AND status = 'approved'";
    $stmt = $db->prepare($sql);
    return $stmt->execute([$now, $affiliateOrderId]);
}

function affiliate_create_user($data)
{
    $db = affiliate_get_pdo();
    $affiliateCode = $data['affiliate_code'] ?? affiliate_generate_code('AFF');
    $passwordHash = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;

    $sql = "INSERT INTO affiliate_users
        (branch_id, affiliate_code, name, email, phone, password_hash, status, commission_type, commission_value, created_by_admin_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $data['branch_id'] ?? null,
        $affiliateCode,
        $data['name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $passwordHash,
        $data['status'] ?? 'active',
        $data['commission_type'] ?? 'percent',
        $data['commission_value'] ?? 0,
        $data['created_by_admin_id'] ?? null,
        affiliate_now()
    ]);

    return $db->lastInsertId();
}

function affiliate_ban_user($affiliateUserId, $reason = null)
{
    $db = affiliate_get_pdo();
    $sql = "UPDATE affiliate_users
            SET status = 'banned', banned_at = ?, banned_reason = ?, updated_at = ?
            WHERE id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute([affiliate_now(), $reason, affiliate_now(), $affiliateUserId]);
}

function affiliate_create_campaign($data)
{
    $db = affiliate_get_pdo();
    $campaignCode = $data['campaign_code'] ?? affiliate_generate_code('CMP');

    $sql = "INSERT INTO affiliate_campaigns
        (branch_id, campaign_code, campaign_name, description, target_url, status, start_date, end_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $data['branch_id'] ?? null,
        $campaignCode,
        $data['campaign_name'],
        $data['description'] ?? null,
        $data['target_url'],
        $data['status'] ?? 'active',
        $data['start_date'] ?? null,
        $data['end_date'] ?? null,
        affiliate_now()
    ]);

    return $db->lastInsertId();
}

function affiliate_generate_link($affiliateUserId, $campaignId, $baseUrl)
{
    $db = affiliate_get_pdo();

    $stmt = $db->prepare("SELECT affiliate_code FROM affiliate_users WHERE id = ? LIMIT 1");
    $stmt->execute([$affiliateUserId]);
    $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT campaign_code FROM affiliate_campaigns WHERE id = ? LIMIT 1");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$affiliate || !$campaign) {
        return null;
    }

    $trackingCode = $affiliate['affiliate_code'] . '-' . $campaign['campaign_code'];
    $separator = strpos($baseUrl, '?') === false ? '?' : '&';
    $referralUrl = $baseUrl . $separator . 'aff=' . urlencode($affiliate['affiliate_code']) . '&campaign=' . urlencode($campaign['campaign_code']);

    $sql = "INSERT INTO affiliate_links
        (affiliate_user_id, campaign_id, tracking_code, referral_url, created_at)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE referral_url = VALUES(referral_url)";

    $stmt = $db->prepare($sql);
    $stmt->execute([$affiliateUserId, $campaignId, $trackingCode, $referralUrl, affiliate_now()]);

    return $referralUrl;
}
