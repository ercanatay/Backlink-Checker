<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Security\SsrfGuard;

final class HttpClient
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return array{ok: bool, status: int, headers: array<string, string>, body: string, redirect_chain: array<int, string>, final_url: string, error: string|null}
     */
    public function getWithRedirects(string $url): array
    {
        $current = $url;
        $chain = [];
        $maxRedirects = $this->config->int('SCAN_MAX_REDIRECTS', 5);

        for ($i = 0; $i <= $maxRedirects; $i++) {
            $response = $this->singleGet($current);
            if (!$response['ok']) {
                $response['redirect_chain'] = $chain;
                $response['final_url'] = $current;
                return $response;
            }

            $status = $response['status'];
            if ($status >= 300 && $status < 400) {
                $location = $response['headers']['location'] ?? '';
                if ($location === '') {
                    $response['redirect_chain'] = $chain;
                    $response['final_url'] = $current;
                    return $response;
                }

                $resolved = $this->resolveLocation($current, $location);
                $chain[] = $resolved;
                $current = $resolved;
                continue;
            }

            $response['redirect_chain'] = $chain;
            $response['final_url'] = $current;
            return $response;
        }

        return [
            'ok' => false,
            'status' => 0,
            'headers' => [],
            'body' => '',
            'redirect_chain' => $chain,
            'final_url' => $current,
            'error' => 'Too many redirects',
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return array{ok: bool, status: int, headers: array<string, string>, body: string, error: string|null}
     */
    public function postJson(string $url, array $payload, array $headers = []): array
    {
        try {
            SsrfGuard::assertExternalUrl($url);
        } catch (\InvalidArgumentException $e) {
            return [
                'ok' => false,
                'status' => 0,
                'headers' => [],
                'body' => '',
                'error' => 'SSRF: ' . $e->getMessage(),
            ];
        }

        $ch = curl_init();
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $headerLines = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
        ];

        foreach ($headers as $key => $value) {
            $headerLines[] = $key . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $this->config->int('SCAN_TIMEOUT_SECONDS', 20),
            CURLOPT_CONNECTTIMEOUT => $this->config->int('SCAN_CONNECT_TIMEOUT_SECONDS', 10),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => true,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'headers' => [], 'body' => '', 'error' => $error ?: 'Unknown cURL error'];
        }

        $headerBlock = substr($raw, 0, $headerSize);
        $responseBody = substr($raw, $headerSize);

        return [
            'ok' => $status >= 200 && $status < 500,
            'status' => $status,
            'headers' => $this->parseHeaders($headerBlock),
            'body' => $responseBody,
            'error' => $status >= 500 ? 'Upstream server error' : null,
        ];
    }

    /**
     * @return array{ok: bool, status: int, headers: array<string, string>, body: string, error: string|null}
     */
    private function singleGet(string $url): array
    {
        try {
            SsrfGuard::assertExternalUrl($url);
        } catch (\InvalidArgumentException $e) {
            return [
                'ok' => false,
                'status' => 0,
                'headers' => [],
                'body' => '',
                'error' => 'SSRF: ' . $e->getMessage(),
            ];
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->config->int('SCAN_TIMEOUT_SECONDS', 20),
            CURLOPT_CONNECTTIMEOUT => $this->config->int('SCAN_CONNECT_TIMEOUT_SECONDS', 10),
            CURLOPT_USERAGENT => $this->config->string('SCAN_USER_AGENT', 'CybokronBacklinkCheckerBot/2.0'),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => true,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false,
                'status' => $status,
                'headers' => [],
                'body' => '',
                'error' => $error ?: 'Unknown cURL error',
            ];
        }

        $headerBlock = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        return [
            'ok' => $status > 0,
            'status' => $status,
            'headers' => $this->parseHeaders($headerBlock),
            'body' => $body,
            'error' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $headerBlock): array
    {
        $headers = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($headerBlock)) ?: [];
        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }

        return $headers;
    }

    private function resolveLocation(string $current, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($current);
        if (!is_array($parts)) {
            return $location;
        }

        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');

        if ($host === '') {
            return $location;
        }

        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $location;
        }

        $path = (string) ($parts['path'] ?? '/');
        $dir = rtrim(dirname($path), '/');

        return $scheme . '://' . $host . ($dir === '' ? '' : $dir) . '/' . $location;
    }
}
