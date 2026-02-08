#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

$app = backlink_checker_app();
$db = $app->db();
$langPath = dirname(__DIR__) . '/resources/lang';
$files = glob($langPath . '/*.json') ?: [];

$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded) || !isset($decoded['_meta'])) {
        continue;
    }

    $locale = basename($file, '.json');
    $meta = $decoded['_meta'];
    $version = (string) ($meta['version'] ?? '1.0.0');
    $reviewed = (string) ($meta['lastReviewed'] ?? gmdate('Y-m-d'));
    $checksum = hash('sha256', $content);

    $db->execute(
        'INSERT INTO i18n_catalog_versions(locale, version, last_reviewed, checksum, created_at) VALUES (?, ?, ?, ?, ?)',
        [$locale, $version, $reviewed, $checksum, gmdate('c')]
    );
    $count++;
}

echo "Synchronized {$count} language catalog entries.\n";
