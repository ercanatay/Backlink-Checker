<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Security\TokenService;

final class TokenAuthService
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly AuthService $auth
    ) {
    }

    /**
     * @param array<int, string> $requiredScopes
     * @return array<string, mixed>|null
     */
    public function authenticateBearer(?string $authHeader, array $requiredScopes = []): ?array
    {
        if ($authHeader === null || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $plain = trim(substr($authHeader, 7));
        if ($plain === '') {
            return null;
        }

        $token = $this->tokens->findActiveToken($plain);
        if ($token === null) {
            return null;
        }

        if (!$this->tokens->hasScopes($requiredScopes, (string) $token['scopes'])) {
            return null;
        }

        $user = $this->auth->userWithRoles((int) $token['user_id']);
        if ($user === null) {
            return null;
        }

        $user['token'] = $token;

        return $user;
    }
}
