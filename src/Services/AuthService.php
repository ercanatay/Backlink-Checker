<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Security\PasswordHasher;

final class AuthService
{
    public function __construct(
        private readonly Database $db,
        private readonly PasswordHasher $hasher,
        private readonly Config $config
    ) {
    }

    public function bootstrapDefaults(): void
    {
        $existingRoles = $this->db->fetchAll('SELECT name FROM roles');
        if ($existingRoles === []) {
            foreach (['admin', 'editor', 'viewer'] as $role) {
                $this->db->execute('INSERT INTO roles(name, created_at) VALUES (?, ?)', [$role, gmdate('c')]);
            }
        }

        $count = $this->db->fetchOne('SELECT COUNT(*) AS c FROM users');
        if ((int) ($count['c'] ?? 0) > 0) {
            return;
        }

        $email = $this->config->string('BOOTSTRAP_ADMIN_EMAIL', 'admin@example.com');
        $name = $this->config->string('BOOTSTRAP_ADMIN_NAME', 'Administrator');
        $password = $this->config->string('BOOTSTRAP_ADMIN_PASSWORD', 'ChangeThisNow!');

        $this->db->execute(
            'INSERT INTO users(email, password_hash, display_name, locale, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$email, $this->hasher->hash($password), $name, $this->config->string('APP_DEFAULT_LOCALE', 'en-US'), gmdate('c'), gmdate('c')]
        );
        $userId = $this->db->lastInsertId();

        $adminRole = $this->db->fetchOne('SELECT id FROM roles WHERE name = ?', ['admin']);
        if ($adminRole !== null) {
            $this->db->execute('INSERT INTO user_roles(user_id, role_id, created_at) VALUES (?, ?, ?)', [$userId, $adminRole['id'], gmdate('c')]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->db->fetchOne('SELECT * FROM users WHERE email = ? AND is_active = 1', [trim(strtolower($email))]);
        if ($user === null) {
            return null;
        }

        if (!$this->hasher->verify($password, (string) $user['password_hash'])) {
            return null;
        }

        $this->db->execute('UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?', [gmdate('c'), gmdate('c'), $user['id']]);

        return $this->userWithRoles((int) $user['id']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function userWithRoles(int $userId): ?array
    {
        $user = $this->db->fetchOne('SELECT id, email, display_name, locale, is_active FROM users WHERE id = ?', [$userId]);
        if ($user === null) {
            return null;
        }

        $roles = $this->db->fetchAll(
            'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? ORDER BY r.name',
            [$userId]
        );

        $user['roles'] = array_map(static fn(array $row): string => (string) $row['name'], $roles);

        return $user;
    }

    /**
     * @return array<int, string>
     */
    public function rolesForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
            [$userId]
        );

        return array_map(static fn(array $row): string => (string) $row['name'], $rows);
    }

    public function hasRole(int $userId, string $role): bool
    {
        $roles = $this->rolesForUser($userId);

        return in_array($role, $roles, true);
    }

    public function updateLocale(int $userId, string $locale): void
    {
        $this->db->execute('UPDATE users SET locale = ?, updated_at = ? WHERE id = ?', [$locale, gmdate('c'), $userId]);
    }
}
