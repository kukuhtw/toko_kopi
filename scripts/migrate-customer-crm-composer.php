<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$target = $root . '/plugins/customer-crm/CustomerCrmPlugin.php';

if (!is_file($target)) {
    fwrite(STDERR, "CustomerCrmPlugin.php not found\n");
    exit(1);
}

$contents = file_get_contents($target);
if ($contents === false) {
    fwrite(STDERR, "Unable to read CustomerCrmPlugin.php\n");
    exit(1);
}

$slash = chr(92);
$replacements = [];

$replacements['use App' . $slash . 'Config' . $slash . 'Database;'] = 'use KopiBot' . $slash . 'Core' . $slash . 'DatabaseConnection;';
$replacements['use App' . $slash . 'Helpers' . $slash . 'Csrf;'] = 'use KopiBot' . $slash . 'Security' . $slash . 'Csrf;';
$replacements['use App' . $slash . 'Models' . $slash . 'CustomerModel;'] = 'use KopiBot' . $slash . 'Domains' . $slash . 'Customer' . $slash . 'CustomerRepository;' . PHP_EOL . 'use KopiBot' . $slash . 'Domains' . $slash . 'Customer' . $slash . 'CustomerNormalizer;';
$replacements['use App' . $slash . 'Plugin' . $slash . 'PluginInterface;'] = 'use KopiBot' . $slash . 'Contracts' . $slash . 'PluginInterface;';
$replacements['use App' . $slash . 'Plugin' . $slash . 'HookManager;'] = 'use KopiBot' . $slash . 'Core' . $slash . 'HookManager;';

$replacements['Database::getInstance()'] = 'DatabaseConnection::getInstance()';
$replacements['(new CustomerModel())->find($customerId)'] = '(new CustomerRepository())->find($customerId)';
$replacements['CustomerModel::normalizeEmail'] = 'CustomerNormalizer::email';
$replacements['(new CustomerModel())->normalizeWhatsApp'] = 'CustomerNormalizer::whatsapp';

$changed = false;
foreach ($replacements as $search => $replace) {
    if (str_contains($contents, $search)) {
        $contents = str_replace($search, $replace, $contents);
        $changed = true;
    }
}

if (!$changed) {
    echo "CustomerCrmPlugin.php already appears migrated or no known legacy patterns found\n";
    exit(0);
}

if (file_put_contents($target, $contents) === false) {
    fwrite(STDERR, "Unable to write CustomerCrmPlugin.php\n");
    exit(1);
}

echo "CustomerCrmPlugin.php migrated to Composer core dependencies\n";
