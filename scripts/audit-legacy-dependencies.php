<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$excludedDirs = [
    $root . DIRECTORY_SEPARATOR . '.git',
    $root . DIRECTORY_SEPARATOR . 'vendor',
    $root . DIRECTORY_SEPARATOR . 'node_modules',
];

$patterns = [
    'App\\Config\\Database',
    'App\\Plugin\\HookManager',
    'App\\Plugin\\PluginInterface',
    'App\\Models',
    'App\\Helpers',
    'App\\Skills',
    'App\\Services\\IntentPatternRegistry',
    'Database::getConnection',
    'Database::getInstance',
    'require_once',
    'include_once',
    'require ',
    'include ',
];

$allowedPrefixes = [
    'app/Config/',
    'app/Models/',
    'app/Helpers/',
    'app/Plugin/',
    'app/Skills/',
    'app/Services/IntentPatternRegistry.php',
    'docs/',
    'scripts/audit-legacy-dependencies.php',
    'scripts/migrate-customer-crm-composer.php',
    'scripts/migrate-loyalty-point-composer.php',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$violations = [];

foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (!$file->isFile() || pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
        continue;
    }

    foreach ($excludedDirs as $dir) {
        if (str_starts_with($path, $dir . DIRECTORY_SEPARATOR)) {
            continue 2;
        }
    }

    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
    $contents = file_get_contents($path);
    if ($contents === false) {
        continue;
    }

    foreach ($patterns as $pattern) {
        if (!str_contains($contents, $pattern)) {
            continue;
        }

        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            $violations[] = [$relative, $pattern];
        }
    }
}

if ($violations === []) {
    echo "Legacy dependency audit passed.\n";
    exit(0);
}

fwrite(STDERR, "Legacy dependency audit failed.\n\n");
foreach ($violations as [$file, $pattern]) {
    fwrite(STDERR, "- {$file}: {$pattern}\n");
}

exit(1);
