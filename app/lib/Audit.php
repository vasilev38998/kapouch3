<?php

declare(strict_types=1);

namespace App\Lib;

class Audit {
    public static function log(?int $actorId, string $action, string $targetType, ?int $targetId, string $status, ?string $message = null): void {
        $stmt = Db::pdo()->prepare('INSERT INTO audit_log(actor_user_id, action, target_type, target_id, ip, user_agent, status, message, created_at) VALUES(?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([
            $actorId,
            $action,
            $targetType,
            $targetId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $status,
            $message,
        ]);
    }
}
