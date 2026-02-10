<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Db;
use App\Lib\TinkoffSbpService;

class CheckoutController {
    public function sbp(): void {
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

        $itemsRaw = json_decode((string)($_POST['items'] ?? '[]'), true);
        if (!is_array($itemsRaw) || empty($itemsRaw)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'empty_cart'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $cart = [];
        foreach ($itemsRaw as $row) {
            if (!is_array($row)) continue;
            $id = (int)($row['id'] ?? 0);
            $qty = (int)($row['qty'] ?? 0);
            if ($id > 0 && $qty > 0) {
                $cart[$id] = ($cart[$id] ?? 0) + min($qty, 20);
            }
        }
        if (!$cart) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'bad_cart'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ids = array_keys($cart);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Db::pdo()->prepare("SELECT id,name,price,is_active,is_sold_out FROM menu_items WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $menuItems = $stmt->fetchAll();

        $catalog = [];
        foreach ($menuItems as $item) {
            if ((int)$item['is_active'] !== 1 || (int)$item['is_sold_out'] === 1) continue;
            $catalog[(int)$item['id']] = $item;
        }

        $amount = 0.0;
        $lines = [];
        foreach ($cart as $id => $qty) {
            if (!isset($catalog[$id])) continue;
            $price = (float)$catalog[$id]['price'];
            $line = $price * $qty;
            $amount += $line;
            $lines[] = [
                'id' => $id,
                'name' => (string)$catalog[$id]['name'],
                'qty' => $qty,
                'price' => $price,
                'sum' => round($line, 2),
            ];
        }

        if (!$lines || $amount <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'nothing_to_pay'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $amount = round($amount, 2);
        $amountKopecks = (int)round($amount * 100);
        $orderId = 'pwa-' . (int)$user['id'] . '-' . date('YmdHis') . '-' . random_int(1000, 9999);

        $baseUrl = rtrim((string)config('app.base_url', ''), '/');
        $service = new TinkoffSbpService();
        $payment = $service->createSbpPayment([
            'amount_kopecks' => $amountKopecks,
            'order_id' => $orderId,
            'description' => 'Заказ в PWA Kapouch',
            'success_url' => $baseUrl . '/profile',
            'fail_url' => $baseUrl . '/menu',
            'notification_url' => $baseUrl . '/api/payments/tinkoff/notify',
            'customer_key' => 'user-' . (int)$user['id'],
        ]);

        if (!$payment || $payment['payment_url'] === '') {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'payment_init_failed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        Db::pdo()->prepare('INSERT INTO payment_sessions(user_id, provider, external_order_id, amount, status, payload_json, created_at, updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())')
            ->execute([
                (int)$user['id'],
                'tinkoff_sbp',
                $orderId,
                $amount,
                'created',
                json_encode([
                    'cart' => $lines,
                    'payment' => $payment,
                ], JSON_UNESCAPED_UNICODE),
            ]);

        echo json_encode([
            'ok' => true,
            'payment_url' => $payment['payment_url'],
            'order_id' => $orderId,
            'amount' => $amount,
        ], JSON_UNESCAPED_UNICODE);
    }
}
