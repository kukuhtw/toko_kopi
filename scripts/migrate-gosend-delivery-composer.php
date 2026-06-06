<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/plugins/gosend-delivery/GoSendDeliveryPlugin.php',
    $root . '/plugins/gosend-delivery/GoSendDeliveryRepository.php',
];

$slash = chr(92);
$replacements = [
    'use App' . $slash . 'Helpers' . $slash . 'Csrf;' => 'use KopiBot' . $slash . 'Security' . $slash . 'Csrf;',
    'use App' . $slash . 'Plugin' . $slash . 'HookManager;' => 'use KopiBot' . $slash . 'Core' . $slash . 'HookManager;',
    'use App' . $slash . 'Plugin' . $slash . 'PluginInterface;' => 'use KopiBot' . $slash . 'Contracts' . $slash . 'PluginInterface;',
    'use App' . $slash . 'Plugin' . $slash . '{HookManager, PluginInterface};' => 'use KopiBot' . $slash . 'Contracts' . $slash . 'PluginInterface;' . PHP_EOL . 'use KopiBot' . $slash . 'Core' . $slash . 'HookManager;',
    'use App' . $slash . 'Config' . $slash . 'Database;' => 'use KopiBot' . $slash . 'Core' . $slash . 'DatabaseConnection;',
    'Database::getInstance()' => 'DatabaseConnection::getInstance()',
];

$changedFiles = [];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing file: {$file}\n");
        exit(1);
    }

    $contents = file_get_contents($file);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read file: {$file}\n");
        exit(1);
    }

    $updated = $contents;
    foreach ($replacements as $search => $replace) {
        $updated = str_replace($search, $replace, $updated);
    }

    if ($updated !== $contents) {
        if (file_put_contents($file, $updated) === false) {
            fwrite(STDERR, "Unable to write file: {$file}\n");
            exit(1);
        }
        $changedFiles[] = str_replace($root . '/', '', $file);
    }
}

if ($changedFiles === []) {
    echo "GoSend delivery files already Composer-compatible.\n";
    exit(0);
}

echo "Migrated GoSend delivery files:\n";
foreach ($changedFiles as $file) {
    echo "- {$file}\n";
}
