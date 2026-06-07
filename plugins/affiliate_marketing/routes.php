<?php
require_once __DIR__ . '/controllers/AffiliateAdminController.php';
require_once __DIR__ . '/export_csv.php';

$page = $_GET['page'] ?? 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $action = (string) ($_POST['action'] ?? '');

        switch ($action) {
            case 'save_user':
                echo json_encode([
                    'success' => true,
                    'id' => affiliate_admin_save_user_from_post(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            case 'ban_user':
                echo json_encode([
                    'success' => (bool) affiliate_admin_ban_user_from_post(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            case 'save_campaign':
                echo json_encode([
                    'success' => true,
                    'id' => affiliate_admin_save_campaign_from_post(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            case 'mark_commission_paid':
                echo json_encode([
                    'success' => (bool) affiliate_admin_mark_commission_paid_from_post(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            default:
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unknown affiliate admin action.',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;
        }
    } catch (Throwable $exception) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => $exception->getMessage(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return;
}

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

    case 'export_commissions':
        affiliate_export_commissions_csv($_GET);
        break;

    case 'export_traffic':
        affiliate_export_traffic_csv($_GET);
        break;

    case 'export_fraud':
        affiliate_export_fraud_csv($_GET);
        break;

    default:
        http_response_code(404);
        echo 'Affiliate route not found';
}
