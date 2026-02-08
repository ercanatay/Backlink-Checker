<?php

declare(strict_types=1);

// Legacy compatibility shim: keep /index.php as the primary entrypoint.
require_once __DIR__ . '/bootstrap/app.php';

backlink_checker_app()->run();
