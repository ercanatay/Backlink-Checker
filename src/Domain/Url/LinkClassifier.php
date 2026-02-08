<?php

declare(strict_types=1);

namespace BacklinkChecker\Domain\Url;

use BacklinkChecker\Domain\Enum\LinkType;

final class LinkClassifier
{
    public function classify(string $rel): string
    {
        $value = strtolower(trim($rel));
        if ($value === '') {
            return LinkType::DOFOLLOW;
        }

        $parts = preg_split('/\s+/', $value) ?: [];
        if (in_array('sponsored', $parts, true)) {
            return LinkType::SPONSORED;
        }

        if (in_array('nofollow', $parts, true)) {
            return LinkType::NOFOLLOW;
        }

        if (in_array('ugc', $parts, true)) {
            return LinkType::UGC;
        }

        return LinkType::DOFOLLOW;
    }

    public function weight(string $linkType): int
    {
        return match ($linkType) {
            LinkType::DOFOLLOW => 100,
            LinkType::SPONSORED => 70,
            LinkType::UGC => 60,
            LinkType::NOFOLLOW => 50,
            default => 0,
        };
    }
}
