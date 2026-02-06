<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Db;
use App\Lib\Phone;
use App\Lib\SmsRuClient;

class AuthController {
    public function showPhone(): void {
        view('auth/phone');
    }

    public function sendOtp(): void {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('CSRF');
        }
        $phone = Phone::normalize($_POST['phone'] ?? '');
        if (!$phone) exit('Некорректный телефон');

        $pdo = Db::pdo();
        $cooldown = (int)config('otp.cooldown_sec', 60);
        $daily = (int)config('otp.sms_per_day', 5);
        $attempts = (int)config('otp.attempts', 3);

        $stmt = $pdo->prepare('SELECT sent_at FROM otp_requests WHERE phone=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$phone]);
        $last = $stmt->fetchColumn();
        if ($last && (time() - strtotime((string)$last)) < $cooldown) {
            exit('Слишком часто. Подождите минуту.');
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM otp_requests WHERE phone=? AND DATE(sent_at)=CURDATE()');
        $stmt->execute([$phone]);
        if ((int)$stmt->fetchColumn() >= $daily) {
            exit('Лимит SMS в сутки исчерпан.');
        }

        $otp = (string)random_int(100000, 999999);
        $otpHash = hash_hmac('sha256', $otp, config('app.secret'));
        $expiresAt = date('Y-m-d H:i:s', time() + ((int)config('otp.ttl_minutes', 5) * 60));

        $sms = (new SmsRuClient())->sendOtp($phone, $otp);
        $stmt = $pdo->prepare('INSERT INTO otp_requests(phone, otp_hash, expires_at, attempts_left, sent_at, ip, sms_status, sms_message_id) VALUES(?,?,?,?,NOW(),?,?,?)');
        $stmt->execute([$phone, $otpHash, $expiresAt, $attempts, $_SERVER['REMOTE_ADDR'] ?? '', $sms['ok'] ? 'ok' : 'error', $sms['message_id']]);

        app_log('OTP sent phone=' . $phone . ' status=' . ($sms['ok'] ? 'ok' : 'error'));
        $_SESSION['otp_phone'] = $phone;
        redirect('/auth/verify');
    }

    public function showVerify(): void {
        view('auth/verify', ['phone' => $_SESSION['otp_phone'] ?? '']);
    }

    public function verifyOtp(): void {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('CSRF');
        }
        $phone = $_SESSION['otp_phone'] ?? null;
        if (!$phone) redirect('/auth');
        $otp = trim((string)($_POST['otp'] ?? ''));

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM otp_requests WHERE phone=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$phone]);
        $req = $stmt->fetch();
        if (!$req) exit('OTP не найден');
        if (strtotime($req['expires_at']) < time()) exit('OTP истёк');
        if ((int)$req['attempts_left'] <= 0) exit('Превышено число попыток');

        $hash = hash_hmac('sha256', $otp, config('app.secret'));
        if (!hash_equals($req['otp_hash'], $hash)) {
            $pdo->prepare('UPDATE otp_requests SET attempts_left = attempts_left - 1 WHERE id=?')->execute([$req['id']]);
            exit('Неверный OTP');
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE phone=?');
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        if (!$user) {
            $refBy = $_SESSION['ref_user_id'] ?? null;
            $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $pdo->prepare('INSERT INTO users(phone, role, ref_code, referred_by_user_id, created_at, updated_at) VALUES(?,\'user\',?,?,NOW(),NOW())')
                ->execute([$phone, $ref, $refBy]);
            $userId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO loyalty_state(user_id, stamps, reward_available, updated_at) VALUES(?,0,0,NOW())')->execute([$userId]);
        } else {
            $userId = (int)$user['id'];
        }

        Auth::login($userId);
        unset($_SESSION['otp_phone']);
        redirect('/profile');
    }

    public function logout(): void {
        Auth::logout();
        redirect('/');
    }
}
