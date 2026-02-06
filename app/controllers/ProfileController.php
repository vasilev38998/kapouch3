<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Db;
use App\Lib\Ledger;
use App\Lib\Phone;
use App\Lib\QrToken;

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

        $history = [];

        $orders = $pdo->prepare('SELECT id,total_amount,status,created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
        $orders->execute([$user['id']]);
        foreach ($orders->fetchAll() as $row) {
            $history[] = ['kind' => 'order', 'title' => 'Заказ #' . $row['id'], 'value' => $row['total_amount'] . ' ₽', 'meta' => $row['status'], 'created_at' => $row['created_at']];
        }

        $cb = $pdo->prepare('SELECT id,type,amount,created_at FROM cashback_ledger WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
        $cb->execute([$user['id']]);
        foreach ($cb->fetchAll() as $row) {
            $history[] = ['kind' => 'cashback', 'title' => 'Cashback ' . $row['type'], 'value' => $row['amount'], 'meta' => 'ledger#' . $row['id'], 'created_at' => $row['created_at']];
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
            'history' => $history,
            'review2gis' => config('review_links.2gis_url'),
            'reviewYandex' => config('review_links.yandex_url'),
        ]);
    }

    public function qr(): void {
        Auth::requireAuth();
        $token = QrToken::generate((int)Auth::user()['id']);
        view('profile/qr', ['token' => $token]);
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
