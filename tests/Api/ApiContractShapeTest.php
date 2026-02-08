<?php

declare(strict_types=1);

use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $appCode = (string) file_get_contents(dirname(__DIR__, 2) . '/src/App.php');

    $requiredRoutes = [
        '/api/v1/auth/login',
        '/api/v1/projects',
        '/api/v1/scans',
        '/api/v1/scans/{scanId}',
        '/api/v1/scans/{scanId}/results',
        '/api/v1/scans/{scanId}/cancel',
        '/api/v1/schedules',
        '/api/v1/exports/{exportId}',
        '/api/v1/webhooks/test',
    ];

    $t->test('required API routes are registered', static function () use ($t, $requiredRoutes, $appCode): void {
        foreach ($requiredRoutes as $route) {
            $t->assertTrue(str_contains($appCode, $route), 'Missing route: ' . $route);
        }
    });

    $apiCode = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Controllers/ApiController.php');
    $t->test('api error payload has code and message', static function () use ($t, $apiCode): void {
        $t->assertTrue(str_contains($apiCode, "'code'"));
        $t->assertTrue(str_contains($apiCode, "'message'"));
    });
};
