<?php

declare(strict_types=1);

namespace BacklinkChecker\Http;

final class Responder
{
    /**
     * @param array<string, string> $securityHeaders
     */
    public function emit(Response $response, array $securityHeaders = []): void
    {
        http_response_code($response->status);

        $headers = array_merge($securityHeaders, $response->headers);
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $response->body;
    }
}
