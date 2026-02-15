<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Db;
use App\Lib\Ledger;
use App\Lib\Phone;
use App\Lib\QrToken;
use App\Lib\Settings;

class ProfileController {
    public function index(): void {
        Auth::requireAuth();
        $user = Auth::user();
        $pdo = Db::pdo();
        $state = $pdo->prepare('SELECT * FROM loyalty_state WHERE user_id=?');
        $state->execute([$user['id']]);
        $loyalty = $state->fetch() ?: ['stamps' => 0, 'reward_available' => 0];

        $ledger = new Ledger();
        $cashback = $ledger->cashbackBalance((int)$user['id']);
        $realBalance = $ledger->realBalance((int)$user['id']);

        $history = [];

        $orders = $pdo->prepare('SELECT id,total_amount,status,created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
        $orders->execute([$user['id']]);
        $statusMap = ['created' => 'создан', 'reversed' => 'отменён (реверс)', 'cancelled' => 'отменён'];
        foreach ($orders->fetchAll() as $row) {
            $history[] = ['kind' => 'order', 'title' => 'Заказ #' . $row['id'], 'value' => $row['total_amount'] . ' ₽', 'meta' => ($statusMap[$row['status']] ?? (string)$row['status']), 'created_at' => $row['created_at']];
        }

        $ps = $pdo->prepare("SELECT id, external_order_id, amount, status, payload_json, created_at FROM payment_sessions WHERE user_id=? AND provider=? AND status IN ('accepted','preparing','ready','done') ORDER BY created_at DESC LIMIT 20");
        $ps->execute([$user['id'], 'tinkoff_sbp']);
        $payStatusMap = ['accepted' => 'оплачен', 'preparing' => 'в приготовлении', 'ready' => 'готов', 'done' => 'выдан'];
        foreach ($ps->fetchAll() as $row) {
            $payload = json_decode((string)($row['payload_json'] ?? ''), true) ?: [];
            $total = (float)($payload['amount_total'] ?? (float)$row['amount']);
            $cashbackSpend = (float)($payload['cashback_spend'] ?? 0);
            $meta = ($payStatusMap[$row['status']] ?? (string)$row['status']) . ' · ' . (string)$row['external_order_id'];
            if ($cashbackSpend > 0) {
                $meta .= ' · списано звёздочек ' . number_format($cashbackSpend, 2, '.', ' ') . ' ★';
            }
            $history[] = [
                'kind' => 'online_order',
                'title' => 'Онлайн-заказ',
                'value' => number_format($total, 2, '.', ' ') . ' ₽',
                'meta' => $meta,
                'created_at' => (string)$row['created_at'],
            ];
        }

        $cb = $pdo->prepare('SELECT id,type,amount,created_at FROM cashback_ledger WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
        $cb->execute([$user['id']]);
        $cbTypeMap = ['earn' => 'начисление звёздочек', 'spend' => 'списание звёздочек', 'adjust' => 'пополнение/корректировка', 'reversal' => 'реверс'];
        foreach ($cb->fetchAll() as $row) {
            $history[] = ['kind' => 'cashback', 'title' => 'Баланс: ' . ($cbTypeMap[$row['type']] ?? (string)$row['type']), 'value' => number_format((float)$row['amount'], 2, '.', ' ') . ' ★', 'meta' => 'операция #' . $row['id'], 'created_at' => $row['created_at']];
        }

        try {
            $rb = $pdo->prepare('SELECT id,type,amount,created_at FROM real_balance_ledger WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
            $rb->execute([$user['id']]);
            $rbTypeMap = ['topup' => 'пополнение рублёвого баланса', 'spend' => 'оплата заказа рублёвым балансом', 'adjust' => 'корректировка', 'reversal' => 'реверс'];
            foreach ($rb->fetchAll() as $row) {
                $history[] = ['kind' => 'real_balance', 'title' => 'Рубли: ' . ($rbTypeMap[$row['type']] ?? (string)$row['type']), 'value' => number_format((float)$row['amount'], 2, '.', ' ') . ' ₽', 'meta' => 'операция #' . $row['id'], 'created_at' => $row['created_at']];
            }
        } catch (\Throwable) {
            // миграция real_balance_ledger может быть ещё не применена
        }

        $st = $pdo->prepare('SELECT id,delta,reason,created_at FROM stamp_ledger WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
        $st->execute([$user['id']]);
        foreach ($st->fetchAll() as $row) {
            $history[] = ['kind' => 'stamps', 'title' => 'Штампы ' . $row['reason'], 'value' => (string)$row['delta'], 'meta' => 'ledger#' . $row['id'], 'created_at' => $row['created_at']];
        }

        $rw = $pdo->prepare('SELECT id,type,status,created_at FROM rewards WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
        $rw->execute([$user['id']]);
        foreach ($rw->fetchAll() as $row) {
            $history[] = ['kind' => 'reward', 'title' => 'Награда ' . $row['type'], 'value' => $row['status'], 'meta' => 'reward#' . $row['id'], 'created_at' => $row['created_at']];
        }

        usort($history, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        $history = array_slice($history, 0, 40);

        view('profile/index', [
            'user' => $user,
            'loyalty' => $loyalty,
            'cashback' => $cashback,
            'realBalance' => $realBalance,
            'history' => $history,
            'review2gis' => Settings::get('review_links.2gis_url', config('review_links.2gis_url', '')),
            'reviewYandex' => Settings::get('review_links.yandex_url', config('review_links.yandex_url', '')),
        ]);
    }

    public function qr(): void {
        Auth::requireAuth();
        $userId = (int)Auth::user()['id'];
        $shortCode = QrToken::generateShortCode($userId);
        view('profile/qr', ['shortCode' => $shortCode]);
    }


    public function invite(): void {
        Auth::requireAuth();
        $user = Auth::user();
        $base = rtrim((string)config('app.base_url', ''), '/');
        $inviteLink = ($base ?: '') . '/r/' . $user['ref_code'];
        view('profile/invite', ['user' => $user, 'inviteLink' => $inviteLink]);
    }

    public function phoneChange(): void {
        Auth::requireAuth();
        if (!method_is('POST')) {
            view('profile/phone_change');
            return;
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
        $newPhone = Phone::normalize($_POST['new_phone'] ?? '');
        if (!$newPhone) exit('Некорректный номер');

        $user = Auth::user();
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM otp_requests WHERE phone=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$newPhone]);
        $req = $stmt->fetch();
        $hash = hash_hmac('sha256', trim((string)($_POST['otp'] ?? '')), config('app.secret'));
        if (!$req || strtotime($req['expires_at']) < time() || !hash_equals($req['otp_hash'], $hash)) {
            exit('OTP нового номера не подтверждён');
        }

        $pdo->prepare('UPDATE users SET phone=?, updated_at=NOW() WHERE id=?')->execute([$newPhone, $user['id']]);
        redirect('/profile');
    }

    public function birthday(): void {
        Auth::requireAuth();
        if (!method_is('POST')) {
            view('profile/birthday', ['user' => Auth::user()]);
            return;
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
        $date = $_POST['birthday'] ?: null;
        Db::pdo()->prepare('UPDATE users SET birthday=?, updated_at=NOW() WHERE id=?')->execute([$date, Auth::user()['id']]);
        redirect('/profile');
    }

    public function history(): void {
        Auth::requireAuth();
        redirect('/profile');
    }
}
