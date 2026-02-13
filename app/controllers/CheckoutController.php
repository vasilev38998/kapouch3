<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Db;
use App\Lib\Ledger;
use App\Lib\RulesEngine;
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

        $requestedSpend = max(0, (float)($_POST['cashback_spend'] ?? 0));
        $maxSpendByRule = (new RulesEngine())->cashbackMaxSpend($amount);
        $balance = (new Ledger())->cashbackBalance((int)$user['id']);
        $cashbackSpend = round(min($requestedSpend, $maxSpendByRule, $balance), 2);
        $payable = round(max(0.01, $amount - $cashbackSpend), 2);

        $amountKopecks = (int)round($payable * 100);
        $orderId = 'pwa-' . (int)$user['id'] . '-' . date('YmdHis') . '-' . random_int(1000, 9999);

        $baseUrl = rtrim((string)config('app.base_url', ''), '/');

        $receiptEnabled = (string)\App\Lib\Settings::get('tinkoff.receipt_enabled', '1') !== '0';
        $receipt = null;
        if ($receiptEnabled) {
            $vat = (string)\App\Lib\Settings::get('tinkoff.receipt_vat', 'none');
            $paymentObject = (string)\App\Lib\Settings::get('tinkoff.receipt_payment_object', 'service');
            $paymentMethod = (string)\App\Lib\Settings::get('tinkoff.receipt_payment_method', 'full_payment');
            $taxation = (string)\App\Lib\Settings::get('tinkoff.receipt_taxation', 'usn_income');
            $customerEmail = trim((string)\App\Lib\Settings::get('tinkoff.receipt_email', ''));
            $customerPhone = trim((string)($user['phone'] ?? ''));

            $payableKopecks = max(1, (int)round($payable * 100));
            $sumLines = max(1, (int)round($amount * 100));
            $built = [];
            $acc = 0;
            foreach ($lines as $i => $line) {
                $lineKop = max(1, (int)round(((float)$line['sum']) * 100));
                $scaled = (int)floor($lineKop * $payableKopecks / $sumLines);
                if ($scaled < 1) $scaled = 1;
                if ($i === count($lines) - 1) {
                    $scaled = max(1, $payableKopecks - $acc);
                }
                $acc += $scaled;
                $qty = max(1, (int)$line['qty']);
                $priceKop = (int)max(1, round($scaled / $qty));
                $built[] = [
                    'Name' => mb_substr((string)$line['name'], 0, 128),
                    'Price' => $priceKop,
                    'Quantity' => (float)$qty,
                    'Amount' => $scaled,
                    'Tax' => $vat,
                    'PaymentMethod' => $paymentMethod,
                    'PaymentObject' => $paymentObject,
                ];
            }

            $receipt = [
                'Taxation' => $taxation,
                'Items' => $built,
            ];
            if ($customerEmail !== '') {
                $receipt['Email'] = $customerEmail;
            } elseif ($customerPhone !== '') {
                $receipt['Phone'] = $customerPhone;
            }
        }

        $service = new TinkoffSbpService();
        $payment = $service->createSbpPayment([
            'amount_kopecks' => $amountKopecks,
            'order_id' => $orderId,
            'description' => 'Заказ в PWA Kapouch',
            'success_url' => $baseUrl . '/profile',
            'fail_url' => $baseUrl . '/menu',
            'notification_url' => $baseUrl . '/api/payments/tinkoff/notify',
            'customer_key' => 'user-' . (int)$user['id'],
            'receipt' => $receipt,
        ]);

        if (empty($payment['ok']) || empty($payment['payment_url'])) {
            $error = (string)($payment['error'] ?? 'payment_init_failed');
            $details = (string)($payment['details'] ?? '');
            $status = $error === 'config_missing' ? 500 : 502;
            http_response_code($status);
            echo json_encode([
                'ok' => false,
                'error' => $error,
                'message' => $details,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        Db::pdo()->prepare('INSERT INTO payment_sessions(user_id, provider, external_order_id, amount, status, payload_json, created_at, updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())')
            ->execute([
                (int)$user['id'],
                'tinkoff_sbp',
                $orderId,
                $payable,
                'created',
                json_encode([
                    'cart' => $lines,
                    'amount_total' => $amount,
                    'cashback_spend' => $cashbackSpend,
                    'amount_payable' => $payable,
                    'payment' => $payment,
                ], JSON_UNESCAPED_UNICODE),
            ]);

        echo json_encode([
            'ok' => true,
            'payment_url' => $payment['payment_url'],
            'order_id' => $orderId,
            'amount' => $payable,
            'amount_total' => $amount,
            'cashback_spend' => $cashbackSpend,
        ], JSON_UNESCAPED_UNICODE);
    }
}
