<?php

declare(strict_types=1);

namespace BacklinkChecker\Http;

use BacklinkChecker\Config\Config;

final class SessionManager
{
    public function __construct(private readonly Config $config)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($this->config->string('SESSION_NAME', 'backlink_pro_session'));
        session_set_cookie_params([
            'lifetime' => $this->config->int('SESSION_TTL_MINUTES', 120) * 60,
            'path' => '/',
            'domain' => '',
            'secure' => $this->config->bool('COOKIE_SECURE', false),
            'httponly' => true,
            'samesite' => $this->config->string('COOKIE_SAMESITE', 'Lax'),
        ]);

        session_start();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $user = $_SESSION['user'] ?? null;

        return is_array($user) ? $user : null;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function login(array $user): void
    {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'display_name' => (string) $user['display_name'],
            'locale' => (string) ($user['locale'] ?? 'en-US'),
            'roles' => $user['roles'] ?? [],
        ];
        session_regenerate_id(true);
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }
}
