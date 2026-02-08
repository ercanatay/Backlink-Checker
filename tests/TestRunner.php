<?php

declare(strict_types=1);

namespace BacklinkChecker\Tests;

final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    /**
     * @param callable():void $test
     */
    public function test(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo "[PASS] {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            echo "[FAIL] {$name}: {$e->getMessage()}\n";
        }
    }

    public function assertTrue(bool $condition, string $message = 'Assertion failed'): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    public function assertSame(mixed $expected, mixed $actual, string $message = 'Values are not equal'): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
        }
    }

    public function assertNotEmpty(mixed $actual, string $message = 'Value is empty'): void
    {
        if (empty($actual)) {
            throw new \RuntimeException($message);
        }
    }

    public function totals(): array
    {
        return ['passed' => $this->passed, 'failed' => $this->failed];
    }
}
