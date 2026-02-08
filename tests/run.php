<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/autoload.php';
require_once __DIR__ . '/TestRunner.php';

use BacklinkChecker\Tests\TestRunner;

$runner = new TestRunner();
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
foreach ($iterator as $item) {
    if (!$item instanceof SplFileInfo || !$item->isFile()) {
        continue;
    }
    if (str_ends_with($item->getFilename(), 'Test.php')) {
        $files[] = $item->getPathname();
    }
}
sort($files);

foreach ($files as $file) {
    $register = require $file;
    if (is_callable($register)) {
        $register($runner);
    }
}

$totals = $runner->totals();
echo "\nTotal passed: {$totals['passed']}\n";
echo "Total failed: {$totals['failed']}\n";

exit($totals['failed'] > 0 ? 1 : 0);
