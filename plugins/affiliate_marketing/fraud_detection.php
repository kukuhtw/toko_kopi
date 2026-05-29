<?php
/**
 * Affiliate Fraud Detection Service
 * Detects suspicious affiliate clicks and conversions before commission payout.
 */

require_once __DIR__ . '/affiliate_marketing.php';

function affiliate_fraud_score_click($affiliateUserId, $ipAddress, $userAgent, $windowMinutes = 60)
{
    $db = affiliate_get_pdo();
    $since = date('Y-m-d H:i:s', strtotime('-' . (int) $windowMinutes . ' minutes'));

    $stmt = $db->prepare("SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_user_id = ? AND ip_address = ? AND clicked_at >= ?");
    $stmt->execute([$affiliateUserId, $ipAddress, $since]);
    $sameIpClicks = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_user_id = ? AND user_agent = ? AND clicked_at >= ?");
    $stmt->execute([$affiliateUserId, $userAgent, $since]);
    $sameAgentClicks = (int) $stmt->fetchColumn();

    $score = 0;
    $reasons = [];

    if ($sameIpClicks >= 20) {
        $score += 50;
        $reasons[] = 'High repeated clicks from same IP within ' . $windowMinutes . ' minutes';
    } elseif ($sameIpClicks >= 10) {
        $score += 25;
        $reasons[] = 'Repeated clicks from same IP within ' . $windowMinutes . ' minutes';
    }

    if ($sameAgentClicks >= 30) {
        $score += 30;
        $reasons[] = 'High repeated clicks from same browser/device signature';
    } elseif ($sameAgentClicks >= 15) {
        $score += 15;
        $reasons[] = 'Repeated clicks from same browser/device signature';
    }

    return [
        'score' => $score,
        'reasons' => $reasons,
        'same_ip_clicks' => $sameIpClicks,
        'same_agent_clicks' => $sameAgentClicks,
    ];
}

function affiliate_fraud_audit_order($orderId)
{
    $db = affiliate_get_pdo();

    $sql = "SELECT ao.*, u.email AS affiliate_email, u.phone AS affiliate_phone
            FROM affiliate_orders ao
            JOIN affiliate_users u ON u.id = ao.affiliate_user_id
            WHERE ao.order_id = ?
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        return null;
    }

    $score = 0;
    $reasons = [];

    $clickStmt = $db->prepare("SELECT * FROM affiliate_clicks WHERE converted_order_id = ? ORDER BY clicked_at DESC LIMIT 1");
    $clickStmt->execute([$orderId]);
    $click = $clickStmt->fetch(PDO::FETCH_ASSOC);

    if (!$click) {
        $score += 30;
        $reasons[] = 'Order has affiliate attribution but no matching converted click record';
    }

    if ($click) {
        $recent = affiliate_fraud_score_click(
            $order['affiliate_user_id'],
            $click['ip_address'] ?? '',
            $click['user_agent'] ?? '',
            60
        );
        $score += $recent['score'];
        $reasons = array_merge($reasons, $recent['reasons']);
    }

    $sameOrderStmt = $db->prepare("SELECT COUNT(*) FROM affiliate_orders WHERE affiliate_user_id = ? AND order_total = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $sameOrderStmt->execute([$order['affiliate_user_id'], $order['order_total']]);
    $sameAmountOrders = (int) $sameOrderStmt->fetchColumn();

    if ($sameAmountOrders >= 5) {
        $score += 25;
        $reasons[] = 'Multiple same amount orders from same affiliate within 1 day';
    }

    $riskLevel = affiliate_fraud_risk_level($score);

    affiliate_fraud_log($order['affiliate_user_id'], $orderId, $score, $riskLevel, $reasons);

    if ($riskLevel === 'high') {
        affiliate_mark_order_disputed($orderId, 'Auto flagged by affiliate fraud detection: ' . implode('; ', $reasons));
    }

    return [
        'order_id' => $orderId,
        'affiliate_user_id' => $order['affiliate_user_id'],
        'score' => $score,
        'risk_level' => $riskLevel,
        'reasons' => $reasons,
    ];
}

function affiliate_fraud_risk_level($score)
{
    if ($score >= 70) {
        return 'high';
    }
    if ($score >= 40) {
        return 'medium';
    }
    if ($score > 0) {
        return 'low';
    }
    return 'clean';
}

function affiliate_fraud_log($affiliateUserId, $orderId, $score, $riskLevel, array $reasons)
{
    $db = affiliate_get_pdo();

    $sql = "INSERT INTO affiliate_fraud_logs
        (affiliate_user_id, order_id, risk_score, risk_level, reasons, created_at)
        VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $affiliateUserId,
        $orderId,
        $score,
        $riskLevel,
        json_encode($reasons, JSON_UNESCAPED_UNICODE),
        affiliate_now()
    ]);
}

function affiliate_fraud_report($filters = [])
{
    $db = affiliate_get_pdo();
    $where = [];
    $params = [];

    if (!empty($filters['risk_level'])) {
        $where[] = 'f.risk_level = ?';
        $params[] = $filters['risk_level'];
    }

    if (!empty($filters['affiliate_user_id'])) {
        $where[] = 'f.affiliate_user_id = ?';
        $params[] = $filters['affiliate_user_id'];
    }

    $sql = "SELECT f.*, u.name AS affiliate_name, u.affiliate_code
            FROM affiliate_fraud_logs f
            JOIN affiliate_users u ON u.id = f.affiliate_user_id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY f.created_at DESC LIMIT 500';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
