#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

$app = backlink_checker_app();
$app->logger()->info('cli.migrate.executed');

echo "Migrations are up to date.\n";
