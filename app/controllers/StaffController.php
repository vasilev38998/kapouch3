<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Audit;
use App\Lib\AqsiExternalOrderProvider;
use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Db;
use App\Lib\Ledger;
use App\Lib\Phone;
use App\Lib\QrToken;

class StaffController {
    public function dashboard(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        view('staff/dashboard');
    }

    public function userSearch(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        $user = null;
        $state = null;
        if (!empty($_GET['phone'])) {
            $phone = Phone::normalize((string)$_GET['phone']);
            if ($phone) {
                $stmt = Db::pdo()->prepare('SELECT * FROM users WHERE phone=?');
                $stmt->execute([$phone]);
                $user = $stmt->fetch();
                if ($user) {
                    $st = Db::pdo()->prepare('SELECT stamps, reward_available FROM loyalty_state WHERE user_id=?');
                    $st->execute([$user['id']]);
                    $state = $st->fetch() ?: ['stamps' => 0, 'reward_available' => 0];
                }
            }
        }
        view('staff/user_search', ['found' => $user, 'state' => $state]);
    }

    public function scan(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            $userId = QrToken::verifyFlexible((string)($_POST['token'] ?? '')); 
            if (!$userId) exit('Невалидный QR');
            redirect('/staff/order/create?user_id=' . $userId);
        }
        view('staff/scan');
    }

    public function orderCreate(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        if (!method_is('POST')) {
            view('staff/order_create', ['user_id' => $_GET['user_id'] ?? '', 'idem' => bin2hex(random_bytes(16))]);
            return;
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
        $staff = Auth::user();
        $ledger = new Ledger();
        try {
            $orderId = $ledger->createOrder([
                'user_id' => (int)$_POST['user_id'],
                'staff_user_id' => (int)$staff['id'],
                'location_id' => (int)($_POST['location_id'] ?: 0),
                'total_amount' => (float)$_POST['total_amount'],
                'cashback_spend' => (float)($_POST['cashback_spend'] ?? 0),
                'promocode' => $_POST['promocode'] ?? null,
                'stamps' => (int)($_POST['stamps'] ?? 1),
                'idempotency_key' => $_POST['idempotency_key'] ?? null,
                'meta' => [
                    'category' => $_POST['category'] ?? '',
                    'note' => $_POST['note'] ?? '',
                    'aqsi_external_id' => trim((string)($_POST['aqsi_external_id'] ?? '')),
                    'aqsi_source' => trim((string)($_POST['aqsi_source'] ?? '')),
                ],
            ]);
            Audit::log((int)$staff['id'], 'order_create', 'order', $orderId, 'ok');
            redirect('/staff/order/' . $orderId);
        } catch (\Throwable $e) {
            Audit::log((int)$staff['id'], 'order_create', 'order', null, 'error', $e->getMessage());
            exit('Ошибка: ' . $e->getMessage());
        }
    }

    public function aqsiLookup(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::verify($_GET['_csrf'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'csrf'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $externalId = trim((string)($_GET['external_id'] ?? ''));
        if ($externalId === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'external_id_required'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $provider = new AqsiExternalOrderProvider();
        $check = $provider->fetchOrderByExternalId($externalId);
        if (!$check) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'external_id' => $check['external_id'],
            'total_amount' => $check['total_amount'],
            'paid_at' => $check['paid_at'],
            'source' => $check['source'] ?? 'unknown',
        ], JSON_UNESCAPED_UNICODE);
    }

    public function orderView(int $id): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        $stmt = Db::pdo()->prepare('SELECT * FROM orders WHERE id=?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        view('staff/order_view', ['order' => $order]);
    }

    public function orderReverse(int $id): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
        $staff = Auth::user();
        try {
            (new Ledger())->reverseOrder($id, (int)$staff['id']);
            Audit::log((int)$staff['id'], 'order_reverse', 'order', $id, 'ok');
        } catch (\Throwable $e) {
            Audit::log((int)$staff['id'], 'order_reverse', 'order', $id, 'error', $e->getMessage());
            exit('Ошибка реверса: ' . $e->getMessage());
        }
        redirect('/staff/order/' . $id);
    }

    public function redeemReward(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
        $staff = Auth::user();
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) exit('Bad user');
        try {
            (new Ledger())->redeemFreeCoffee($userId, (int)$staff['id']);
            Audit::log((int)$staff['id'], 'reward_redeem', 'user', $userId, 'ok');
        } catch (\Throwable $e) {
            Audit::log((int)$staff['id'], 'reward_redeem', 'user', $userId, 'error', $e->getMessage());
            exit('Ошибка списания награды: ' . $e->getMessage());
        }
        redirect('/staff/user/search?phone=' . urlencode((string)($_POST['phone'] ?? '')));
    }

    public function promocodes(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        $codes = Db::pdo()->query('SELECT * FROM promocodes ORDER BY id DESC LIMIT 100')->fetchAll();
        view('staff/promocodes', ['codes' => $codes]);
    }



    public function liveOrders(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        view('staff/live_orders');
    }

    public function liveOrdersFeed(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        header('Content-Type: application/json; charset=utf-8');

        $limit = max(5, min(100, (int)($_GET['limit'] ?? 30)));
        $stmt = Db::pdo()->prepare('SELECT ps.id, ps.external_order_id, ps.amount, ps.status, ps.payload_json, ps.created_at, ps.updated_at, u.phone FROM payment_sessions ps JOIN users u ON u.id=ps.user_id ORDER BY ps.id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $items = array_map(static function (array $row): array {
            $payload = json_decode((string)($row['payload_json'] ?? ''), true) ?: [];
            return [
                'id' => (int)$row['id'],
                'external_order_id' => (string)$row['external_order_id'],
                'amount' => (float)$row['amount'],
                'status' => (string)$row['status'],
                'created_at' => (string)$row['created_at'],
                'updated_at' => (string)$row['updated_at'],
                'phone' => (string)$row['phone'],
                'cart' => $payload['cart'] ?? [],
            ];
        }, $rows ?: []);

        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    }

    public function liveOrderStatus(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        header('Content-Type: application/json; charset=utf-8');
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'csrf'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $allowed = ['created', 'accepted', 'preparing', 'ready', 'done', 'cancelled', 'failed'];
        if ($id <= 0 || !in_array($status, $allowed, true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'bad_input'], JSON_UNESCAPED_UNICODE);
            return;
        }

        Db::pdo()->prepare('UPDATE payment_sessions SET status=?, updated_at=NOW() WHERE id=?')->execute([$status, $id]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    public function missions(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        $missions = Db::pdo()->query('SELECT * FROM missions ORDER BY id DESC')->fetchAll();
        view('staff/missions', ['missions' => $missions]);
    }
}
