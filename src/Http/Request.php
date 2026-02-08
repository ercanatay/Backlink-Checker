<?php

declare(strict_types=1);

namespace BacklinkChecker\Http;

final class Request
{
    /**
     * @param array<string, string> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly array $server,
        public readonly array $query,
        public readonly array $post,
        public readonly array $cookies,
        public readonly string $rawBody
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_SERVER, $_GET, $_POST, $_COOKIE, file_get_contents('php://input') ?: '');
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return $path === false || $path === null ? '/' : $path;
    }

    public function host(): string
    {
        return (string) ($this->server['HTTP_HOST'] ?? 'localhost');
    }

    public function isJson(): bool
    {
        $contentType = strtolower((string) ($this->server['CONTENT_TYPE'] ?? ''));

        return str_contains($contentType, 'application/json');
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function header(string $key): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));

        return $this->server[$normalized] ?? null;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }
}
