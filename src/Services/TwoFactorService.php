<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;
use BacklinkChecker\Exceptions\ValidationException;

final class TwoFactorService
{
    private const CODE_PERIOD = 30;
    private const CODE_DIGITS = 6;
    private const RECOVERY_COUNT = 8;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Generate a new TOTP secret for a user.
     *
     * @return array{secret: string, uri: string, recovery_codes: array<int, string>}
     */
    public function generateSecret(int $userId, string $email): array
    {
        $secret = $this->generateBase32Secret(20);
        $recoveryCodes = $this->generateRecoveryCodes();

        $uri = 'otpauth://totp/BacklinkChecker:' . urlencode($email)
            . '?secret=' . $secret
            . '&issuer=BacklinkChecker'
            . '&algorithm=SHA1'
            . '&digits=' . self::CODE_DIGITS
            . '&period=' . self::CODE_PERIOD;

        return [
            'secret' => $secret,
            'uri' => $uri,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    public function enable(int $userId, string $secret, string $code): void
    {
        if (!$this->verifyCode($secret, $code)) {
            throw new ValidationException('Invalid TOTP code');
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $this->db->execute(
            'UPDATE users SET totp_secret = ?, totp_enabled = 1, recovery_codes = ?, updated_at = ? WHERE id = ?',
            [$secret, json_encode($recoveryCodes), gmdate('c'), $userId]
        );
    }

    public function disable(int $userId): void
    {
        $this->db->execute(
            'UPDATE users SET totp_secret = NULL, totp_enabled = 0, recovery_codes = NULL, updated_at = ? WHERE id = ?',
            [gmdate('c'), $userId]
        );
    }

    public function isEnabled(int $userId): bool
    {
        $user = $this->db->fetchOne('SELECT totp_enabled FROM users WHERE id = ?', [$userId]);
        return $user !== null && (int) ($user['totp_enabled'] ?? 0) === 1;
    }

    /**
     * Verify a TOTP code or recovery code for a user.
     */
    public function verify(int $userId, string $code): bool
    {
        $user = $this->db->fetchOne('SELECT totp_secret, recovery_codes FROM users WHERE id = ? AND totp_enabled = 1', [$userId]);
        if ($user === null) {
            return false;
        }

        // Check TOTP code
        if ($this->verifyCode((string) $user['totp_secret'], $code)) {
            return true;
        }

        // Check recovery codes
        $recoveryCodes = json_decode((string) ($user['recovery_codes'] ?? '[]'), true) ?: [];
        $codeIndex = array_search($code, $recoveryCodes, true);
        if ($codeIndex !== false) {
            unset($recoveryCodes[$codeIndex]);
            $this->db->execute(
                'UPDATE users SET recovery_codes = ?, updated_at = ? WHERE id = ?',
                [json_encode(array_values($recoveryCodes)), gmdate('c'), $userId]
            );
            return true;
        }

        return false;
    }

    private function verifyCode(string $secret, string $code): bool
    {
        $decoded = $this->base32Decode($secret);
        if ($decoded === '') {
            return false;
        }

        $timeSlice = intdiv(time(), self::CODE_PERIOD);

        // Check current and ±1 time window for clock drift tolerance
        for ($i = -1; $i <= 1; $i++) {
            $expected = $this->generateTotp($decoded, $timeSlice + $i);
            if (hash_equals($expected, str_pad($code, self::CODE_DIGITS, '0', STR_PAD_LEFT))) {
                return true;
            }
        }

        return false;
    }

    private function generateTotp(string $key, int $timeSlice): string
    {
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($binary % (10 ** self::CODE_DIGITS)), self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    private function generateBase32Secret(int $length): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    private function base32Decode(string $input): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(rtrim($input, '='));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos($map, $input[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
            $codes[] = strtolower(bin2hex(random_bytes(4))) . '-' . strtolower(bin2hex(random_bytes(4)));
        }
        return $codes;
    }
}
