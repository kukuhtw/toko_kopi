<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$target = $root . '/public/index.php';

if (!is_file($target)) {
    fwrite(STDERR, "public/index.php not found\n");
    exit(1);
}

$contents = file_get_contents($target);
if ($contents === false) {
    fwrite(STDERR, "Unable to read public/index.php\n");
    exit(1);
}

$old = <<<'PHP'
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Config/config.php';

use App\Config\Database;
use App\Models\BranchModel;
use App\Plugin\HookManager;
PHP;

$new = <<<'PHP'
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/runtime.php';

if (file_exists(dirname(__DIR__) . '/app/Config/config.php')) {
    require_once dirname(__DIR__) . '/app/Config/config.php';
}

use App\Config\Database;
use App\Models\BranchModel;
use App\Plugin\HookManager;
PHP;

if (str_contains($contents, $new)) {
    echo "public/index.php already uses config/runtime.php\n";
    exit(0);
}

if (!str_contains($contents, $old)) {
    fwrite(STDERR, "Target legacy bootstrap block not found. Migration not applied.\n");
    exit(1);
}

$contents = str_replace($old, $new, $contents);

if (file_put_contents($target, $contents) === false) {
    fwrite(STDERR, "Unable to write public/index.php\n");
    exit(1);
}

echo "public/index.php migrated to config/runtime.php\n";
