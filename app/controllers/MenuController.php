<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Db;

class MenuController {
    public function favorites(): void {
        header('Content-Type: application/json; charset=utf-8');
        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'auth_required'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $stmt = Db::pdo()->prepare('SELECT menu_item_id FROM user_menu_favorites WHERE user_id=? ORDER BY created_at DESC');
        $stmt->execute([(int)$user['id']]);
        $ids = array_map(static fn ($row): int => (int)$row['menu_item_id'], $stmt->fetchAll() ?: []);
        echo json_encode(['ok' => true, 'ids' => $ids], JSON_UNESCAPED_UNICODE);
    }

    public function toggleFavorite(): void {
        header('Content-Type: application/json; charset=utf-8');
        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'auth_required'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'csrf'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $menuItemId = (int)($_POST['menu_item_id'] ?? 0);
        if ($menuItemId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'bad_menu_item_id'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pdo = Db::pdo();
        $exists = $pdo->prepare('SELECT id FROM menu_items WHERE id=? AND is_active=1 LIMIT 1');
        $exists->execute([$menuItemId]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'menu_item_not_found'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $check = $pdo->prepare('SELECT id FROM user_menu_favorites WHERE user_id=? AND menu_item_id=? LIMIT 1');
        $check->execute([(int)$user['id'], $menuItemId]);
        $favId = (int)$check->fetchColumn();
        if ($favId > 0) {
            $pdo->prepare('DELETE FROM user_menu_favorites WHERE id=?')->execute([$favId]);
            echo json_encode(['ok' => true, 'active' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pdo->prepare('INSERT INTO user_menu_favorites (user_id, menu_item_id, created_at) VALUES (?, ?, NOW())')
            ->execute([(int)$user['id'], $menuItemId]);

        echo json_encode(['ok' => true, 'active' => true], JSON_UNESCAPED_UNICODE);
    }
}
