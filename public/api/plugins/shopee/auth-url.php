<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$branchId = (int) ($_GET['branch'] ?? 0);
$result = $shopeeService->generateAuthUrl($branchId > 0 ? $branchId : null);
$status = !empty($result['success']) ? 200 : 422;

Response::json($result, $status);
