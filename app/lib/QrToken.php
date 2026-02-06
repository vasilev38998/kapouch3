<?php

declare(strict_types=1);

namespace App\Lib;

class QrToken {
    public static function generate(int $userId): string {
        $payload = $userId . '.' . time() . '.' . bin2hex(random_bytes(8));
        $sig = hash_hmac('sha256', $payload, config('app.secret'), true);
        return self::b64url($payload) . '.' . self::b64url($sig);
    }

    public static function verify(string $token): ?int {
        $parts = explode('.', $token);
        if (count($parts) !== 2) return null;
        $payload = self::b64urldecode($parts[0]);
        $sig = self::b64urldecode($parts[1]);
        if ($payload === false || $sig === false) return null;

        $expected = hash_hmac('sha256', $payload, config('app.secret'), true);
        if (!hash_equals($expected, $sig)) return null;

        [$userId, $issuedAt, $nonce] = array_pad(explode('.', $payload), 3, null);
        if (!$userId || !$issuedAt || !$nonce) return null;
        $ttlDays = (int)config('app.qr_ttl_days', 30);
        if ((time() - (int)$issuedAt) > $ttlDays * 86400) return null;

        if ((bool)config('features.qr_nonce_single_use', false)) {
            $stmt = Db::pdo()->prepare('INSERT INTO qr_nonces(nonce, user_id, used_at) VALUES(?,?,NOW())');
            try {
                $stmt->execute([$nonce, (int)$userId]);
            } catch (\Throwable) {
                return null;
            }
        }

        return (int)$userId;
    }

    public static function generateShortCode(int $userId): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $ttlMinutes = max(5, (int)config('app.qr_short_code_ttl_minutes', 120));
        Db::pdo()->prepare('INSERT INTO qr_short_codes(code, user_id, expires_at, created_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE),NOW())')
            ->execute([$code, $userId, $ttlMinutes]);
        return $code;
    }

    public static function verifyFlexible(string $value): ?int {
        $value = trim($value);
        if ($value === '') return null;

        $tokenUserId = self::verify($value);
        if ($tokenUserId) return $tokenUserId;

        if (preg_match('#/q/([A-Za-z0-9]+)$#', $value, $m)) {
            $value = $m[1];
        }

        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');
        if ($code === '') return null;

        $stmt = Db::pdo()->prepare('SELECT id, user_id FROM qr_short_codes WHERE code=? AND expires_at >= NOW() AND used_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if (!$row) return null;

        Db::pdo()->prepare('UPDATE qr_short_codes SET used_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
        return (int)$row['user_id'];
    }

    private static function b64url(string $bin): string {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64urldecode(string $str): string|false {
        return base64_decode(strtr($str . str_repeat('=', (4 - strlen($str) % 4) % 4), '-_', '+/'));
    }
}
