<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$includeDocs = in_array('--include-docs', $argv, true);
$targets = ['app', 'public', 'plugins'];
$ignoreFiles = [
    'app/Helpers/Currency.php',
];

if ($includeDocs) {
    $targets[] = 'docs';
}
$extensions = ['php', 'js', 'md'];
$excludeDirs = ['storage', 'uploads', '.git', '.claude', '.sixth'];

$patterns = [
    [
        'label' => 'Hardcoded Rp symbol',
        'regex' => '/\bRp(?:\s|["\'<`]|$)/u',
    ],
    [
        'label' => 'Hardcoded currency label (Rp)',
        'regex' => '/\((?:[^)]*?)Rp(?:[^)]*?)\)/u',
    ],
];

$findings = [];

foreach ($targets as $target) {
    $path = $root . DIRECTORY_SEPARATOR . $target;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $current) use ($excludeDirs): bool {
                if ($current->isDir()) {
                    return !in_array($current->getFilename(), $excludeDirs, true);
                }
                return true;
            }
        )
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $extensions, true)) {
            continue;
        }

        $fullPath = $file->getPathname();
        $relativePath = str_replace($root . DIRECTORY_SEPARATOR, '', $fullPath);
        $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        if (in_array($normalizedPath, $ignoreFiles, true)) {
            continue;
        }
        $lines = file($fullPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $lineNumber => $line) {
            foreach ($patterns as $pattern) {
                if (!preg_match($pattern['regex'], $line)) {
                    continue;
                }

                $findings[] = [
                    'file' => str_replace(DIRECTORY_SEPARATOR, '/', $relativePath),
                    'file' => $normalizedPath,
                    'line' => $lineNumber + 1,
                    'label' => $pattern['label'],
                    'snippet' => trim($line),
                ];
            }
        }
    }
}

if ($findings === []) {
    fwrite(STDOUT, "No hardcoded currency markers found.\n");
    exit(0);
}

fwrite(STDOUT, "Potential hardcoded currency markers found:\n\n");
foreach ($findings as $finding) {
    fwrite(
        STDOUT,
        sprintf(
            "[%s] %s:%d\n%s\n\n",
            $finding['label'],
            $finding['file'],
            $finding['line'],
            $finding['snippet']
        )
    );
}

fwrite(STDOUT, 'Total findings: ' . count($findings) . "\n");
exit(1);
