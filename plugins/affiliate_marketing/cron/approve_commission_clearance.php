<?php
require_once __DIR__ . '/../affiliate_marketing.php';

try {
    $approved = affiliate_approve_eligible_commissions();

    echo json_encode([
        'success' => true,
        'approved_count' => $approved,
        'executed_at' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'executed_at' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
