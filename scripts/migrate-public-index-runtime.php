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

$slash = chr(92);
$replacements = [];
$replacements["require_once dirname(__DIR__) . '/app/Config/config.php';"] = "require_once dirname(__DIR__) . '/config/runtime.php';";
$replacements['use App' . $slash . 'Config' . $slash . 'Database;'] = 'use KopiBot' . $slash . 'Core' . $slash . 'DatabaseConnection;';
$replacements['use App' . $slash . 'Models' . $slash . 'BranchModel;'] = 'use KopiBot' . $slash . 'Domains' . $slash . 'Branch' . $slash . 'BranchRepository;';
$replacements['use App' . $slash . 'Plugin' . $slash . 'HookManager;'] = 'use KopiBot' . $slash . 'Core' . $slash . 'HookManager;';
$replacements['$branchModel = new BranchModel();'] = '$branchRepository = new BranchRepository();';
$replacements['$branches    = $branchModel->getActive();'] = '$branches    = $branchRepository->getActive();';
$replacements['Database::getInstance()'] = 'DatabaseConnection::getInstance()';
$replacements['$primaryBranchSettings = $primaryBranch ? $branchModel->getAllSettings((int) $primaryBranch[\'id\']) : [];'] = '$primaryBranchSettings = $primaryBranch ? $branchRepository->getAllSettings((int) $primaryBranch[\'id\']) : [];';

$changed = false;
foreach ($replacements as $search => $replace) {
    if (str_contains($contents, $search)) {
        $contents = str_replace($search, $replace, $contents);
        $changed = true;
    }
}

if (!$changed) {
    echo "public/index.php already appears migrated or no known legacy patterns found\n";
    exit(0);
}

if (file_put_contents($target, $contents) === false) {
    fwrite(STDERR, "Unable to write public/index.php\n");
    exit(1);
}

echo "public/index.php migrated to Composer runtime and landing dependencies\n";
