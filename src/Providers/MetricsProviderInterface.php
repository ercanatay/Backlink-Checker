<?php

declare(strict_types=1);

namespace BacklinkChecker\Providers;

interface MetricsProviderInterface
{
    /**
     * @param array<int, string> $urls
     * @return array<string, array{pa: float|null, da: float|null, status: string, error: string|null}>
     */
    public function fetchMetrics(array $urls): array;

    /**
     * @return array<string, mixed>
     */
    public function healthcheck(): array;
}
