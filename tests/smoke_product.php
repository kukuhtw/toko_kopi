<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Domains\Product\ProductSearchService;
use KopiBot\Domains\Product\ProductService;

$tenantId = (int) ($_GET['tenant_id'] ?? 1);
$branchId = (int) ($_GET['branch_id'] ?? 1);
$keyword = (string) ($_GET['q'] ?? 'kopi');

if ($keyword === '') {
    $result = (new ProductService())->getMenu($tenantId, $branchId);
} else {
    $result = (new ProductSearchService())->search($tenantId, $branchId, $keyword);
}

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
