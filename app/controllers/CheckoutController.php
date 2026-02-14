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
    private const ORDER_SETTLE_STATUSES = ['accepted', 'preparing', 'ready', 'done'];

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
            $modIds = array_values(array_unique(array_map('intval', (array)($row['modifier_ids'] ?? []))));
            sort($modIds);
            if ($id > 0 && $qty > 0) {
                $cart[] = ['id' => $id, 'qty' => min($qty, 20), 'modifier_ids' => $modIds];
            }
        }
        if (!$cart) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'bad_cart'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ids = array_values(array_unique(array_map(static fn($r): int => (int)$r['id'], $cart)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Db::pdo()->prepare("SELECT id,name,price,is_active,is_sold_out FROM menu_items WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $menuItems = $stmt->fetchAll();

        $catalog = [];
        foreach ($menuItems as $item) {
            if ((int)$item['is_active'] !== 1 || (int)$item['is_sold_out'] === 1) continue;
            $catalog[(int)$item['id']] = $item;
        }

        $groupMap = [];
        $modifierMap = [];
        try {
            $stmtG = Db::pdo()->prepare("SELECT id, menu_item_id, selection_mode, is_required FROM menu_item_modifier_groups WHERE is_active=1 AND menu_item_id IN ($placeholders)");
            $stmtG->execute($ids);
            foreach ($stmtG->fetchAll() ?: [] as $g) {
                $groupMap[(int)$g['id']] = $g;
            }

            if (!empty($groupMap)) {
                $groupIds = array_keys($groupMap);
                $gph = implode(',', array_fill(0, count($groupIds), '?'));
                $stmtM = Db::pdo()->prepare("SELECT id,group_id,name,price_delta,is_active,is_sold_out FROM menu_item_modifiers WHERE group_id IN ($gph)");
                $stmtM->execute($groupIds);
                foreach ($stmtM->fetchAll() ?: [] as $m) {
                    $modifierMap[(int)$m['id']] = $m;
                }
            }
        } catch (\Throwable) {
            $groupMap = [];
            $modifierMap = [];
        }

        $amount = 0.0;
        $lines = [];
        foreach ($cart as $entry) {
            $id = (int)$entry['id'];
            $qty = (int)$entry['qty'];
            if (!isset($catalog[$id])) continue;

            $selectedMods = [];
            $modsByGroup = [];
            foreach ((array)$entry['modifier_ids'] as $mid) {
                $m = $modifierMap[(int)$mid] ?? null;
                if (!$m) continue;
                if ((int)($m['is_active'] ?? 1) !== 1 || (int)($m['is_sold_out'] ?? 0) === 1) {
                    http_response_code(422);
                    echo json_encode(['ok' => false, 'error' => 'modifier_unavailable'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $g = $groupMap[(int)$m['group_id']] ?? null;
                if (!$g || (int)($g['menu_item_id'] ?? 0) !== $id) continue;
                $selectedMods[] = $m;
                $modsByGroup[(int)$m['group_id']][] = $m;
            }

            foreach ($groupMap as $g) {
                if ((int)($g['menu_item_id'] ?? 0) !== $id) continue;
                $gid = (int)$g['id'];
                $picked = $modsByGroup[$gid] ?? [];
                $required = (int)($g['is_required'] ?? 0) === 1;
                if ($required && empty($picked)) {
                    http_response_code(422);
                    echo json_encode(['ok' => false, 'error' => 'modifier_required'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                if ((string)($g['selection_mode'] ?? 'single') === 'single' && count($picked) > 1) {
                    http_response_code(422);
                    echo json_encode(['ok' => false, 'error' => 'modifier_conflict'], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }

            $basePrice = (float)$catalog[$id]['price'];
            $modsPrice = 0.0;
            $modNames = [];
            foreach ($selectedMods as $mod) {
                $modsPrice += (float)$mod['price_delta'];
                $modNames[] = (string)$mod['name'];
            }
            $unitPrice = $basePrice + $modsPrice;
            $line = $unitPrice * $qty;
            $amount += $line;
            $lines[] = [
                'id' => $id,
                'name' => (string)$catalog[$id]['name'],
                'qty' => $qty,
                'price' => $unitPrice,
                'sum' => round($line, 2),
                'modifier_ids' => array_map(static fn($m): int => (int)$m['id'], $selectedMods),
                'modifiers' => $modNames,
            ];
        }

        if (!$lines || $amount <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'nothing_to_pay'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $amount = round($amount, 2);

        $requestedSpend = max(0, (float)($_POST['stars_spend'] ?? ($_POST['cashback_spend'] ?? 0)));
        $balance = (new Ledger())->cashbackBalance((int)$user['id']);
        $cashbackSpend = round(min($requestedSpend, $amount, $balance), 2);
        $payable = round(max(0, $amount - $cashbackSpend), 2);

        $balanceOnly = isset($_POST['balance_only']) && (string)$_POST['balance_only'] === '1';
        if ($balanceOnly && $payable > 0) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'error' => 'insufficient_balance',
                'message' => 'Недостаточно звёздочек для полной оплаты заказа.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($payable <= 0) {
            $orderId = 'bal-' . (int)$user['id'] . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
            $payload = [
                'cart' => $lines,
                'amount_total' => $amount,
                'cashback_spend' => $cashbackSpend,
                'amount_payable' => 0,
                'payment_type' => 'balance',
            ];

            $pdo = Db::pdo();
            $pdo->prepare('INSERT INTO payment_sessions(user_id, provider, external_order_id, amount, status, payload_json, created_at, updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())')
                ->execute([
                    (int)$user['id'],
                    'internal_balance',
                    $orderId,
                    0,
                    'accepted',
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);
            $sessionId = (int)$pdo->lastInsertId();
            $payload = $this->settleOnlineOrder($pdo, (int)$user['id'], $sessionId, $payload);
            $pdo->prepare('UPDATE payment_sessions SET payload_json=?, updated_at=NOW() WHERE id=?')
                ->execute([json_encode($payload, JSON_UNESCAPED_UNICODE), $sessionId]);

            echo json_encode([
                'ok' => true,
                'paid_with_balance' => true,
                'order_id' => $orderId,
                'amount' => 0,
                'amount_total' => $amount,
                'cashback_spend' => $cashbackSpend,
                'stars_earned' => (float)($payload['stars_earned'] ?? 0),
                'redirect_url' => '/profile',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $amountKopecks = (int)round($payable * 100);
        $orderId = 'pwa-' . (int)$user['id'] . '-' . date('YmdHis') . '-' . random_int(1000, 9999);

        $baseUrl = rtrim((string)config('app.base_url', ''), '/');
        $notificationUrl = (string)\App\Lib\Settings::get('tinkoff.notification_url', config('tinkoff.notification_url', $baseUrl . '/api/payments/tinkoff/notify'));

        $receiptEnabled = (string)\App\Lib\Settings::get('tinkoff.receipt_enabled', '1') !== '0';
        $receipt = null;
        if ($receiptEnabled) {
            $vat = (string)\App\Lib\Settings::get('tinkoff.receipt_vat', 'none');
            $paymentObject = (string)\App\Lib\Settings::get('tinkoff.receipt_payment_object', 'commodity');
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
            'notification_url' => $notificationUrl,
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

    public function tinkoffNotify(): void {
        header('Content-Type: application/json; charset=utf-8');

        $raw = file_get_contents('php://input') ?: '';
        $data = [];
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json') && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $data = $decoded;
        }
        if (!$data && !empty($_POST)) {
            $data = $_POST;
        }
        if (!$data) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'bad_payload'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $token = (string)($data['Token'] ?? '');
        if ($token === '' || !$this->verifyTinkoffToken($data, $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'bad_token'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $orderId = (string)($data['OrderId'] ?? '');
        if ($orderId === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'order_id_required'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $status = $this->mapTinkoffStatus((string)($data['Status'] ?? ''));
        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT id, user_id, provider, payload_json FROM payment_sessions WHERE external_order_id=? LIMIT 1');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            return;
        }

        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) $payload = [];
        $payload['tinkoff_last_notification'] = [
            'received_at' => date('c'),
            'status' => (string)($data['Status'] ?? ''),
            'success' => (bool)($data['Success'] ?? false),
            'payment_id' => (string)($data['PaymentId'] ?? ''),
            'raw' => $data,
        ];

        if ($status === 'accepted' && in_array((string)$row['provider'], ['tinkoff_sbp', 'internal_balance'], true)) {
            $payload = $this->settleOnlineOrder($pdo, (int)$row['user_id'], (int)$row['id'], $payload);
        }

        if ($status === 'accepted' && (string)$row['provider'] === 'tinkoff_topup') {
            $payload = $this->applyTopupBalance($pdo, (int)$row['user_id'], (int)$row['id'], $payload);
        }

        $pdo->prepare('UPDATE payment_sessions SET status=?, payload_json=?, updated_at=NOW() WHERE id=?')
            ->execute([
                $status,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                (int)$row['id'],
            ]);

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    private function verifyTinkoffToken(array $data, string $token): bool {
        $password = (string)\App\Lib\Settings::get('tinkoff.password', config('tinkoff.password', ''));
        if ($password === '') {
            return false;
        }

        unset($data['Token']);
        $data['Password'] = $password;
        $flat = [];
        foreach ($data as $k => $v) {
            if (is_scalar($v)) {
                $flat[$k] = (string)$v;
            }
        }
        ksort($flat);
        $expected = hash('sha256', implode('', $flat));
        return hash_equals($expected, $token);
    }

    private function mapTinkoffStatus(string $providerStatus): string {
        return match (strtoupper($providerStatus)) {
            'CONFIRMED', 'AUTHORIZED' => 'accepted',
            'REVERSED', 'REFUNDED', 'PARTIAL_REFUNDED' => 'cancelled',
            'REJECTED', 'DEADLINE_EXPIRED', 'EXPIRED' => 'failed',
            default => 'created',
        };
    }

    public function topup(): void {
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

        $amount = round((float)($_POST['amount'] ?? 0), 2);
        if ($amount <= 0 || $amount > 50000) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'bad_amount', 'message' => 'Сумма пополнения должна быть от 0.01 до 50000 ₽'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $orderId = 'topup-' . (int)$user['id'] . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
        $baseUrl = rtrim((string)config('app.base_url', ''), '/');
        $notificationUrl = (string)\App\Lib\Settings::get('tinkoff.notification_url', config('tinkoff.notification_url', $baseUrl . '/api/payments/tinkoff/notify'));
        $service = new TinkoffSbpService();
        $payment = $service->createSbpPayment([
            'amount_kopecks' => (int)round($amount * 100),
            'order_id' => $orderId,
            'description' => 'Пополнение баланса Kapouch',
            'success_url' => $baseUrl . '/profile',
            'fail_url' => $baseUrl . '/profile',
            'notification_url' => $notificationUrl,
            'customer_key' => 'user-' . (int)$user['id'],
            'receipt' => null,
        ]);

        if (empty($payment['ok']) || empty($payment['payment_url'])) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => (string)($payment['error'] ?? 'payment_init_failed')], JSON_UNESCAPED_UNICODE);
            return;
        }

        Db::pdo()->prepare('INSERT INTO payment_sessions(user_id, provider, external_order_id, amount, status, payload_json, created_at, updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())')
            ->execute([
                (int)$user['id'],
                'tinkoff_topup',
                $orderId,
                $amount,
                'created',
                json_encode([
                    'type' => 'topup',
                    'topup_amount' => $amount,
                    'payment' => $payment,
                ], JSON_UNESCAPED_UNICODE),
            ]);

        echo json_encode(['ok' => true, 'payment_url' => $payment['payment_url'], 'amount' => $amount], JSON_UNESCAPED_UNICODE);
    }

    private function settleOnlineOrder(\PDO $pdo, int $userId, int $sessionId, array $payload): array {
        if (!empty($payload['ledger_applied_at'])) {
            return $payload;
        }

        $ledger = new Ledger();
        $spend = round(max(0, (float)($payload['cashback_spend'] ?? 0)), 2);
        $amountTotal = round(max(0, (float)($payload['amount_total'] ?? 0)), 2);
        $starsEarned = round((new RulesEngine())->calculateOrder(['total_amount' => $amountTotal])['cashback_earn'] ?? 0, 2);

        if ($spend > 0) {
            $ledger->addCashback($userId, null, 'spend', $spend, null, ['source' => 'online_order', 'payment_session_id' => $sessionId]);
        }
        if ($starsEarned > 0) {
            $ledger->addCashback($userId, null, 'earn', $starsEarned, null, ['source' => 'online_order', 'payment_session_id' => $sessionId]);
        }

        $payload['ledger_applied_at'] = date('c');
        $payload['stars_earned'] = $starsEarned;
        $payload['stars_spent'] = $spend;
        return $payload;
    }

    private function applyTopupBalance(\PDO $pdo, int $userId, int $sessionId, array $payload): array {
        if (!empty($payload['topup_applied_at'])) {
            return $payload;
        }

        $amount = round(max(0, (float)($payload['topup_amount'] ?? 0)), 2);
        if ($amount > 0) {
            (new Ledger())->addCashback($userId, null, 'adjust', $amount, null, ['source' => 'topup', 'payment_session_id' => $sessionId]);
        }

        $payload['topup_applied_at'] = date('c');
        $payload['topup_amount'] = $amount;
        return $payload;
    }

}
