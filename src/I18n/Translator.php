<?php

declare(strict_types=1);

namespace BacklinkChecker\I18n;

use MessageFormatter;

final class Translator
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $catalogs = [];

    /**
     * @param array<int, string> $supportedLocales
     */
    public function __construct(
        private readonly string $langPath,
        private readonly array $supportedLocales,
        private readonly string $defaultLocale
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function trans(string $key, array $params = [], ?string $requestedLocale = null): string
    {
        $locale = $this->resolveLocale($requestedLocale);
        $message = $this->getMessage($locale, $key) ?? $this->getMessage($this->defaultLocale, $key) ?? $key;

        if ($params === []) {
            return (string) $message;
        }

        $formatted = MessageFormatter::formatMessage($locale, (string) $message, $params);
        if ($formatted === false) {
            foreach ($params as $paramKey => $value) {
                $message = str_replace('{' . $paramKey . '}', (string) $value, (string) $message);
            }

            return (string) $message;
        }

        return $formatted;
    }

    public function isRtl(?string $requestedLocale = null): bool
    {
        $locale = $this->resolveLocale($requestedLocale);
        $catalog = $this->loadCatalog($locale);

        return (bool) ($catalog['_meta']['rtl'] ?? false);
    }

    /**
     * @return array<int, string>
     */
    public function supported(): array
    {
        return $this->supportedLocales;
    }

    public function resolveLocale(?string $requested): string
    {
        if ($requested !== null && in_array($requested, $this->supportedLocales, true)) {
            return $requested;
        }

        if ($requested !== null && str_contains($requested, '-')) {
            $base = strtolower(explode('-', $requested)[0]);
            foreach ($this->supportedLocales as $locale) {
                if (strtolower(explode('-', $locale)[0]) === $base) {
                    return $locale;
                }
            }
        }

        return $this->defaultLocale;
    }

    private function getMessage(string $locale, string $key): mixed
    {
        $catalog = $this->loadCatalog($locale);

        return $catalog[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCatalog(string $locale): array
    {
        if (isset($this->catalogs[$locale])) {
            return $this->catalogs[$locale];
        }

        $file = rtrim($this->langPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $locale . '.json';
        if (!is_file($file)) {
            $this->catalogs[$locale] = [];
            return $this->catalogs[$locale];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $this->catalogs[$locale] = $decoded;

        return $decoded;
    }
}
