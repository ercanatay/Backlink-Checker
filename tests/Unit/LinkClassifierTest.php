<?php

declare(strict_types=1);

use BacklinkChecker\Domain\Enum\LinkType;
use BacklinkChecker\Domain\Url\LinkClassifier;
use BacklinkChecker\Tests\TestRunner;

return static function (TestRunner $t): void {
    $classifier = new LinkClassifier();

    $t->test('nofollow classification', static function () use ($t, $classifier): void {
        $t->assertSame(LinkType::NOFOLLOW, $classifier->classify('nofollow ugc'));
    });

    $t->test('sponsored has higher priority', static function () use ($t, $classifier): void {
        $t->assertSame(LinkType::SPONSORED, $classifier->classify('nofollow sponsored'));
    });

    $t->test('dofollow default', static function () use ($t, $classifier): void {
        $t->assertSame(LinkType::DOFOLLOW, $classifier->classify(''));
    });
};
