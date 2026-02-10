<?php

declare(strict_types=1);

namespace App\Lib;

class Auth {
    public static function user(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        $stmt = Db::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    public static function login(int $userId): void {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void {
        $_SESSION = [];
        session_destroy();
    }

    public static function requireAuth(): void {
        if (!self::user()) redirect('/auth');
    }

    public static function requireRole(array $roles): void {
        $user = self::user();
        if (!$user || !in_array($user['role'], $roles, true)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}
