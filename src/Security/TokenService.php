<?php

declare(strict_types=1);

namespace BacklinkChecker\Security;

use BacklinkChecker\Database\Database;

final class TokenService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<int, string> $scopes
     * @return array{plain: string, id: int}
     */
    public function createToken(int $userId, string $name, array $scopes, ?string $expiresAt = null): array
    {
        $plain = 'bct_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $plain);

        $this->db->execute(
            'INSERT INTO api_tokens(user_id, name, token_hash, scopes, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $name, $hash, json_encode(array_values($scopes)), $expiresAt, gmdate('c')]
        );

        return ['plain' => $plain, 'id' => $this->db->lastInsertId()];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveToken(string $plain): ?array
    {
        $hash = hash('sha256', $plain);
        $token = $this->db->fetchOne(
            'SELECT t.*, u.email, u.display_name FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE token_hash = ? AND revoked_at IS NULL',
            [$hash]
        );

        if ($token === null) {
            return null;
        }

        if (!empty($token['expires_at']) && strtotime((string) $token['expires_at']) < time()) {
            return null;
        }

        $this->db->execute('UPDATE api_tokens SET last_used_at = ? WHERE id = ?', [gmdate('c'), $token['id']]);

        return $token;
    }

    /**
     * @param array<int, string> $required
     */
    public function hasScopes(array $required, string $scopesJson): bool
    {
        $granted = json_decode($scopesJson, true);
        if (!is_array($granted)) {
            $granted = [];
        }

        foreach ($required as $scope) {
            if (!in_array($scope, $granted, true) && !in_array('*', $granted, true)) {
                return false;
            }
        }

        return true;
    }
}
