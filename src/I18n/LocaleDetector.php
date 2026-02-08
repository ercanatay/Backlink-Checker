<?php

declare(strict_types=1);

namespace BacklinkChecker\I18n;

final class LocaleDetector
{
    /**
     * @param array<int, string> $supported
     */
    public function detect(?string $acceptLanguage, array $supported, string $default): string
    {
        if ($acceptLanguage === null || trim($acceptLanguage) === '') {
            return $default;
        }

        $supportedLookup = array_flip($supported);
        $parts = explode(',', $acceptLanguage);
        foreach ($parts as $part) {
            $locale = trim(explode(';', $part)[0] ?? '');
            if ($locale === '') {
                continue;
            }

            if (isset($supportedLookup[$locale])) {
                return $locale;
            }

            $base = strtolower(explode('-', $locale)[0]);
            foreach ($supported as $candidate) {
                if (strtolower(explode('-', $candidate)[0]) === $base) {
                    return $candidate;
                }
            }
        }

        return $default;
    }
}
