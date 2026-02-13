<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Db;

class NotificationController {
    public function subscribe(): void {
        Auth::requireAuth();

        $payload = $_POST;
        if (empty($payload)) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $payload = $decoded;
        }

        if (!Csrf::verify($payload['_csrf'] ?? null)) {
            http_response_code(419);
            exit('CSRF');
        }

        $endpoint = trim((string)($payload['endpoint'] ?? ''));
        $permission = (string)($payload['permission'] ?? 'default');
        if (!in_array($permission, ['default','granted','denied'], true)) $permission = 'default';
        if ($endpoint === '') {
            http_response_code(400);
            exit('bad endpoint');
        }

        $keys = is_array($payload['keys'] ?? null) ? $payload['keys'] : [];
        $p256dh = trim((string)($keys['p256dh'] ?? ($payload['p256dh'] ?? '')));
        $auth = trim((string)($keys['auth'] ?? ($payload['auth'] ?? '')));
        $encoding = trim((string)($payload['contentEncoding'] ?? ($payload['content_encoding'] ?? '')));

        Db::pdo()->prepare('INSERT INTO push_subscriptions(user_id, endpoint, p256dh, auth, content_encoding, user_agent, permission, is_active, created_at, last_seen_at, last_error) VALUES(?,?,?,?,?,?,?,1,NOW(),NOW(),NULL) ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth=VALUES(auth), content_encoding=VALUES(content_encoding), user_agent=VALUES(user_agent), permission=VALUES(permission), is_active=1, last_seen_at=NOW(), last_error=NULL')
            ->execute([(int)Auth::user()['id'], $endpoint, $p256dh ?: null, $auth ?: null, $encoding ?: null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $permission]);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function poll(): void {
        Auth::requireAuth();

        Db::pdo()->prepare('UPDATE push_subscriptions SET last_seen_at=NOW() WHERE user_id=?')->execute([(int)Auth::user()['id']]);
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

    public function click(): void {
        Auth::requireAuth();
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('CSRF');
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = Db::pdo()->prepare('SELECT campaign_id FROM user_notifications WHERE id=? AND user_id=? LIMIT 1');
            $stmt->execute([$id, (int)Auth::user()['id']]);
            $campaignId = (int)$stmt->fetchColumn();
            if ($campaignId > 0) {
                Db::pdo()->prepare('UPDATE push_campaigns SET clicks_count=clicks_count+1 WHERE id=?')->execute([$campaignId]);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function readAll(): void {
        Auth::requireAuth();
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('CSRF');
        }
        Db::pdo()->prepare('UPDATE user_notifications SET is_read=1 WHERE user_id=?')->execute([(int)Auth::user()['id']]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }
}
