<?php

declare(strict_types=1);

namespace BacklinkChecker\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = []
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): self
    {
        return new self(
            $status,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, array_merge(['Content-Type' => 'text/html; charset=utf-8'], $headers));
    }

    /**
     * @param array<string, string> $headers
     */
    public static function redirect(string $location, int $status = 302, array $headers = []): self
    {
        return new self($status, '', array_merge(['Location' => $location], $headers));
    }
}
