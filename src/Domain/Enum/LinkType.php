<?php

declare(strict_types=1);

namespace BacklinkChecker\Domain\Enum;

final class LinkType
{
    public const DOFOLLOW = 'dofollow';
    public const NOFOLLOW = 'nofollow';
    public const UGC = 'ugc';
    public const SPONSORED = 'sponsored';
    public const NONE = 'none';
}
