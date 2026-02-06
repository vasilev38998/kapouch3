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
        $ttlMinutes = self::shortCodeTtlMinutes();

        $stmt = Db::pdo()->prepare('SELECT code FROM qr_short_codes WHERE user_id=? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $active = (string)$stmt->fetchColumn();
        if ($active !== '') {
            return $active;
        }

        $code = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        for ($i = 0; $i < 5; $i++) {
            try {
                Db::pdo()->prepare('INSERT INTO qr_short_codes(code, user_id, expires_at, created_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE),NOW())')
                    ->execute([$code, $userId, $ttlMinutes]);
                return $code;
            } catch (\Throwable) {
                $code = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            }
        }

        throw new \RuntimeException('Не удалось сгенерировать короткий код');
    }

    public static function verifyFlexible(string $value): ?int {
        $value = trim($value);
        if ($value === '') return null;

        if (preg_match('#/q/(\d{5})$#', $value, $m)) {
            $value = $m[1];
        }

        $code = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($code) !== 5) return null;

        $stmt = Db::pdo()->prepare('SELECT user_id FROM qr_short_codes WHERE code=? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$code]);
        $userId = (int)$stmt->fetchColumn();
        return $userId > 0 ? $userId : null;
    }

    private static function shortCodeTtlMinutes(): int {
        $v = (int)config('app.short_code_ttl_minutes', 6);
        if ($v < 5) return 5;
        if ($v > 7) return 7;
        return $v;
    }

    private static function b64url(string $bin): string {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64urldecode(string $str): string|false {
        return base64_decode(strtr($str . str_repeat('=', (4 - strlen($str) % 4) % 4), '-_', '+/'));
    }
}
