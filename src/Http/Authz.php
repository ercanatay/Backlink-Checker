<?php

declare(strict_types=1);

namespace BacklinkChecker\Http;

use BacklinkChecker\Exceptions\AuthenticationException;
use BacklinkChecker\Exceptions\AuthorizationException;
use BacklinkChecker\Services\AuthService;
use BacklinkChecker\Services\TokenAuthService;

final class Authz
{
    public function __construct(
        private readonly SessionManager $session,
        private readonly AuthService $authService,
        private readonly ?TokenAuthService $tokenAuth = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function requireUser(): array
    {
        $user = $this->session->user();
        if ($user === null) {
            throw new AuthenticationException();
        }

        return $user;
    }

    public function ensureRole(int $userId, string $role): void
    {
        if (!$this->authService->hasRole($userId, $role)) {
            throw new AuthorizationException();
        }
    }

    public function canManageProject(array $project, array $user): bool
    {
        $role = (string) ($project['membership_role'] ?? 'viewer');

        return in_array($role, ['admin', 'editor'], true) || in_array('admin', $user['roles'] ?? [], true);
    }
}
