<?php

declare(strict_types=1);

use BacklinkChecker\I18n\Translator;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $translator = new Translator(
        dirname(__DIR__, 2) . '/resources/lang',
        ['en-US', 'tr-TR', 'ar-SA'],
        'en-US'
    );

    $t->test('known locale key loads', static function () use ($t, $translator): void {
        $value = $translator->trans('nav.dashboard', [], 'tr-TR');
        $t->assertNotEmpty($value);
    });

    $t->test('fallback to default locale', static function () use ($t, $translator): void {
        $value = $translator->trans('nav.dashboard', [], 'xx-XX');
        $t->assertSame('Dashboard', $value);
    });

    $t->test('rtl locale detection', static function () use ($t, $translator): void {
        $t->assertTrue($translator->isRtl('ar-SA'));
    });
};
