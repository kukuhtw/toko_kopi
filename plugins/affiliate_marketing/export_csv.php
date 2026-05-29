<?php
require_once __DIR__ . '/controllers/AffiliateAdminController.php';
require_once __DIR__ . '/fraud_detection.php';

function affiliate_export_csv($filename, array $headers, array $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $key => $label) {
            $line[] = is_string($key) ? ($row[$key] ?? '') : ($row[$label] ?? '');
        }
        fputcsv($output, $line);
    }

    fclose($output);
    exit;
}

function affiliate_export_commissions_csv($filters = [])
{
    $rows = affiliate_admin_commission_report($filters);
    affiliate_export_csv(
        'affiliate_commissions_' . date('Ymd_His') . '.csv',
        [
            'affiliate_code' => 'Affiliate Code',
            'affiliate_name' => 'Affiliate Name',
            'campaign_name' => 'Campaign',
            'order_id' => 'Order ID',
            'order_total' => 'Order Total',
            'order_payment_status' => 'Payment Status',
            'order_paid_at' => 'Paid At',
            'clearance_until' => 'Clearance Until',
            'commission_amount' => 'Commission Amount',
            'status' => 'Commission Status',
            'dispute_status' => 'Dispute Status',
            'created_at' => 'Created At'
        ],
        $rows
    );
}

function affiliate_export_traffic_csv($filters = [])
{
    $rows = affiliate_admin_traffic_report($filters);
    affiliate_export_csv(
        'affiliate_traffic_' . date('Ymd_His') . '.csv',
        [
            'affiliate_code' => 'Affiliate Code',
            'affiliate_name' => 'Affiliate Name',
            'campaign_name' => 'Campaign',
            'tracking_code' => 'Tracking Code',
            'ip_address' => 'IP Address',
            'referrer' => 'Referrer',
            'landing_url' => 'Landing URL',
            'clicked_at' => 'Clicked At',
            'converted_order_id' => 'Converted Order ID'
        ],
        $rows
    );
}

function affiliate_export_fraud_csv($filters = [])
{
    $rows = affiliate_fraud_report($filters);
    affiliate_export_csv(
        'affiliate_fraud_' . date('Ymd_His') . '.csv',
        [
            'affiliate_code' => 'Affiliate Code',
            'affiliate_name' => 'Affiliate Name',
            'order_id' => 'Order ID',
            'risk_score' => 'Risk Score',
            'risk_level' => 'Risk Level',
            'reasons' => 'Reasons',
            'created_at' => 'Created At'
        ],
        $rows
    );
}
