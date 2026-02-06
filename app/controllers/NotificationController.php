<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Db;

class NotificationController {
    public function subscribe(): void {
        Auth::requireAuth();
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('CSRF');
        }
        $endpoint = trim((string)($_POST['endpoint'] ?? ''));
        if ($endpoint === '') {
            http_response_code(400);
            exit('bad endpoint');
        }
        Db::pdo()->prepare('INSERT INTO push_subscriptions(user_id, endpoint, user_agent, created_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE user_agent=VALUES(user_agent)')
            ->execute([(int)Auth::user()['id'], $endpoint, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function poll(): void {
        Auth::requireAuth();
        $stmt = Db::pdo()->prepare('SELECT id,title,body,url,created_at FROM user_notifications WHERE user_id=? AND is_read=0 ORDER BY id DESC LIMIT 20');
        $stmt->execute([(int)Auth::user()['id']]);
        header('Content-Type: application/json');
        echo json_encode(['items' => $stmt->fetchAll()]);
    }

    public function read(): void {
        Auth::requireAuth();
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('CSRF');
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            Db::pdo()->prepare('UPDATE user_notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$id, (int)Auth::user()['id']]);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }
}
