<?php
require_once __DIR__ . '/controllers/AffiliateAdminController.php';

$page = $_GET['page'] ?? 'dashboard';

switch ($page) {

    case 'dashboard':
        header('Content-Type: application/json');
        echo json_encode(affiliate_admin_dashboard_summary(), JSON_PRETTY_PRINT);
        break;

    case 'users':
        header('Content-Type: application/json');
        echo json_encode(affiliate_admin_list_users($_GET), JSON_PRETTY_PRINT);
        break;

    case 'campaigns':
        header('Content-Type: application/json');
        echo json_encode(affiliate_admin_list_campaigns($_GET), JSON_PRETTY_PRINT);
        break;

    case 'traffic':
        header('Content-Type: application/json');
        echo json_encode(affiliate_admin_traffic_report($_GET), JSON_PRETTY_PRINT);
        break;

    case 'commissions':
        header('Content-Type: application/json');
        echo json_encode(affiliate_admin_commission_report($_GET), JSON_PRETTY_PRINT);
        break;

    default:
        http_response_code(404);
        echo 'Affiliate route not found';
}
