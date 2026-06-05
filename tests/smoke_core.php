<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use KopiBot\Core\BranchContext;
use KopiBot\Core\EventDispatcher;
use KopiBot\Core\TenantContext;

TenantContext::set(['id' => 1, 'tenant_name' => 'Demo Tenant']);
BranchContext::set(['id' => 1, 'branch_name' => 'Demo Branch']);

$dispatcher = new EventDispatcher();
$fired = false;

$dispatcher->listen('core.smoke', function () use (&$fired): void {
    $fired = true;
});

$dispatcher->dispatch('core.smoke');

echo json_encode([
    'success' => $fired,
    'tenant_id' => TenantContext::id(),
    'branch_id' => BranchContext::id(),
], JSON_PRETTY_PRINT) . PHP_EOL;
