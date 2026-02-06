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

        [$userId, $issuedAt] = array_pad(explode('.', $payload), 3, null);
        if (!$userId || !$issuedAt) return null;
        $ttlDays = (int)config('app.qr_ttl_days', 30);
        if ((time() - (int)$issuedAt) > $ttlDays * 86400) return null;
        return (int)$userId;
    }

    private static function b64url(string $bin): string {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64urldecode(string $str): string|false {
        return base64_decode(strtr($str . str_repeat('=', (4 - strlen($str) % 4) % 4), '-_', '+/'));
    }
}
