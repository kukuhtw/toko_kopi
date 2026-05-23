<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/Config/config.php';

use App\Helpers\Auth;
use App\Services\MenuCatalogAiTransferService;

Auth::startSession();
Auth::requireLogin();

$user = Auth::user();
$branchId = (int)$user['branch_id'];
if (!$branchId && !Auth::isSuperAdmin()) {
    http_response_code(403);
    exit('Access denied');
}
if (Auth::isSuperAdmin()) {
    $branchId = (int)($_GET['branch_id'] ?? 0);
    if (!$branchId) {
        http_response_code(400);
        exit('branch_id required');
    }
}

$service = new MenuCatalogAiTransferService();
$export = $service->exportBranchWorkbook($branchId);

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
header('Cache-Control: no-cache');

echo $export['content'];
exit;
