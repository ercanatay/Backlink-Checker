<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$phpFiles = [];

foreach ($rii as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)
    ) {
        continue;
    }

    if (str_ends_with($path, '.php')) {
        $phpFiles[] = $path;
    }
}

$errors = 0;
foreach ($phpFiles as $path) {
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $out, $status);
    if ($status !== 0) {
        $errors++;
        echo implode(PHP_EOL, $out) . PHP_EOL;
    }
}

$langFiles = glob($root . '/resources/lang/*.json') ?: [];
$baseKeys = null;
foreach ($langFiles as $file) {
    $decoded = json_decode((string) file_get_contents($file), true);
    if (!is_array($decoded)) {
        $errors++;
        echo "Invalid JSON: {$file}\n";
        continue;
    }
    unset($decoded['_meta']);
    $keys = array_keys($decoded);
    sort($keys);

    if ($baseKeys === null) {
        $baseKeys = $keys;
        continue;
    }

    if ($keys !== $baseKeys) {
        $errors++;
        echo "Translation key mismatch: {$file}\n";
    }
}

if ($errors > 0) {
    echo "Lint failed with {$errors} issue(s).\n";
    exit(1);
}

echo "Lint passed.\n";
