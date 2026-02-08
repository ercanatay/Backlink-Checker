<?php

declare(strict_types=1);

namespace BacklinkChecker\Security;

final class Csrf
{
    public function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf_token'];
    }

    public function validate(?string $token): bool
    {
        $sessionToken = $_SESSION['_csrf_token'] ?? '';

        return is_string($token) && is_string($sessionToken) && hash_equals($sessionToken, $token);
    }
}
