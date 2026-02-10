<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;
use BacklinkChecker\Domain\Url\UrlNormalizer;
use BacklinkChecker\Exceptions\ValidationException;

final class ProjectService
{
    public function __construct(
        private readonly Database $db,
        private readonly UrlNormalizer $normalizer
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, pm.role AS membership_role FROM projects p '
            . 'JOIN project_members pm ON pm.project_id = p.id '
            . 'WHERE pm.user_id = ? AND p.archived_at IS NULL ORDER BY p.updated_at DESC',
            [$userId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $projectId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT p.*, pm.role AS membership_role FROM projects p '
            . 'JOIN project_members pm ON pm.project_id = p.id '
            . 'WHERE p.id = ? AND pm.user_id = ? AND p.archived_at IS NULL',
            [$projectId, $userId]
        );
    }

    public function create(int $userId, string $name, string $rootDomain, ?string $description): int
    {
        $name = trim($name);
        $rootDomain = $this->normalizer->rootDomain($rootDomain);

        if ($name === '') {
            throw new ValidationException('Project name is required');
        }
        if ($rootDomain === '') {
            throw new ValidationException('Valid root domain is required');
        }

        $now = gmdate('c');

        return $this->db->transaction(function () use ($name, $description, $rootDomain, $userId, $now): int {
            $this->db->execute(
                'INSERT INTO projects(name, description, root_domain, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$name, $description, $rootDomain, $userId, $now, $now]
            );
            $projectId = $this->db->lastInsertId();

            $this->db->execute(
                'INSERT INTO project_members(project_id, user_id, role, created_at) VALUES (?, ?, ?, ?)',
                [$projectId, $userId, 'admin', $now]
            );

            return $projectId;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function members(int $projectId): array
    {
        return $this->db->fetchAll(
            'SELECT u.id, u.email, u.display_name, pm.role FROM project_members pm '
            . 'JOIN users u ON u.id = pm.user_id WHERE pm.project_id = ? ORDER BY u.display_name',
            [$projectId]
        );
    }

    public function addOrUpdateMember(int $projectId, string $email, string $role): void
    {
        $email = trim(strtolower($email));
        $role = in_array($role, ['admin', 'editor', 'viewer'], true) ? $role : 'viewer';

        $user = $this->db->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
        if ($user === null) {
            throw new ValidationException('User email not found');
        }

        $this->db->execute(
            'INSERT INTO project_members(project_id, user_id, role, created_at) VALUES (?, ?, ?, ?) '
            . 'ON CONFLICT(project_id, user_id) DO UPDATE SET role = excluded.role',
            [$projectId, $user['id'], $role, gmdate('c')]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function notifications(int $projectId): array
    {
        return $this->db->fetchAll('SELECT * FROM notifications WHERE project_id = ? ORDER BY created_at DESC', [$projectId]);
    }

    public function addNotification(int $projectId, int $createdBy, string $channel, string $destination, ?string $secret): int
    {
        $channel = strtolower(trim($channel));
        if (!in_array($channel, ['email', 'slack', 'webhook'], true)) {
            throw new ValidationException('Invalid notification channel');
        }

        $this->db->execute(
            'INSERT INTO notifications(project_id, event_type, channel, destination, secret, created_by, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$projectId, 'scan.completed', $channel, trim($destination), $secret, $createdBy, gmdate('c'), gmdate('c')]
        );

        return $this->db->lastInsertId();
    }
}
