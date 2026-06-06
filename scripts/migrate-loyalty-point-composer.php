<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$target = $root . '/plugins/loyalty-point/LoyaltyPointPlugin.php';

if (!is_file($target)) {
    fwrite(STDERR, "LoyaltyPointPlugin.php not found\n");
    exit(1);
}

$contents = file_get_contents($target);
if ($contents === false) {
    fwrite(STDERR, "Unable to read LoyaltyPointPlugin.php\n");
    exit(1);
}

$slash = chr(92);
$replacements = [];

$replacements['use App' . $slash . 'Config' . $slash . 'Database;'] = 'use KopiBot' . $slash . 'Core' . $slash . 'DatabaseConnection;';
$replacements['use App' . $slash . 'Helpers' . $slash . 'Csrf;'] = 'use KopiBot' . $slash . 'Security' . $slash . 'Csrf;';
$replacements['use App' . $slash . 'Plugin' . $slash . 'HookManager;'] = 'use KopiBot' . $slash . 'Core' . $slash . 'HookManager;';
$replacements['use App' . $slash . 'Plugin' . $slash . 'PluginInterface;'] = 'use KopiBot' . $slash . 'Contracts' . $slash . 'PluginInterface;';
$replacements['Database::getInstance()'] = 'DatabaseConnection::getInstance()';

$changed = false;
foreach ($replacements as $search => $replace) {
    if (str_contains($contents, $search)) {
        $contents = str_replace($search, $replace, $contents);
        $changed = true;
    }
}

if (!$changed) {
    echo "LoyaltyPointPlugin.php already appears migrated or no known legacy patterns found\n";
    exit(0);
}

if (file_put_contents($target, $contents) === false) {
    fwrite(STDERR, "Unable to write LoyaltyPointPlugin.php\n");
    exit(1);
}

echo "LoyaltyPointPlugin.php migrated to Composer core dependencies\n";
