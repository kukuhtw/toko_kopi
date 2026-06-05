<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/runtime.php';
require_once __DIR__ . '/../app/Config/config.php';

$checks = [
    'kopibot_hook_manager' => class_exists(\KopiBot\Core\HookManager::class),
    'kopibot_database_connection' => class_exists(\KopiBot\Core\DatabaseConnection::class),
    'kopibot_branch_repository' => class_exists(\KopiBot\Domains\Branch\BranchRepository::class),
    'legacy_database_adapter' => class_exists(\App\Config\Database::class),
    'legacy_base_model' => class_exists(\App\Models\BaseModel::class),
    'legacy_branch_model_adapter' => class_exists(\App\Models\BranchModel::class),
];

\KopiBot\Core\HookManager::addFilter('migration.bridge.test', static fn (string $value): string => $value . '-ok');
$checks['hook_filter_result'] = \KopiBot\Core\HookManager::applyFilters('migration.bridge.test', 'composer') === 'composer-ok';

$success = !in_array(false, $checks, true);

echo json_encode([
    'success' => $success,
    'checks' => $checks,
], JSON_PRETTY_PRINT) . PHP_EOL;

exit($success ? 0 : 1);
