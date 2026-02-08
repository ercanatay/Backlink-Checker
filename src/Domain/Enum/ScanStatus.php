<?php

declare(strict_types=1);

namespace BacklinkChecker\Domain\Enum;

final class ScanStatus
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
}
