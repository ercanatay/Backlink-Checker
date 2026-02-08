<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use BacklinkChecker\App;

function backlink_checker_app(): App
{
    static $app = null;
    if ($app instanceof App) {
        return $app;
    }

    $app = new App(dirname(__DIR__));

    return $app;
}
