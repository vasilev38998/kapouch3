<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Audit;
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
        if (!empty($_GET['phone'])) {
            $phone = Phone::normalize((string)$_GET['phone']);
            if ($phone) {
                $stmt = Db::pdo()->prepare('SELECT * FROM users WHERE phone=?');
                $stmt->execute([$phone]);
                $user = $stmt->fetch();
            }
        }
        view('staff/user_search', ['found' => $user]);
    }

    public function scan(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            $userId = QrToken::verify((string)($_POST['token'] ?? ''));
            if (!$userId) exit('Невалидный QR');
            redirect('/staff/order/create?user_id=' . $userId);
        }
        view('staff/scan');
    }

    public function orderCreate(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        if (!method_is('POST')) {
            view('staff/order_create', ['user_id' => $_GET['user_id'] ?? '']);
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
                'meta' => [
                    'category' => $_POST['category'] ?? '',
                    'note' => $_POST['note'] ?? '',
                ],
            ]);
            Audit::log((int)$staff['id'], 'order_create', 'order', $orderId, 'ok');
            redirect('/staff/order/' . $orderId);
        } catch (\Throwable $e) {
            Audit::log((int)$staff['id'], 'order_create', 'order', null, 'error', $e->getMessage());
            exit('Ошибка: ' . $e->getMessage());
        }
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

    public function promocodes(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        $codes = Db::pdo()->query('SELECT * FROM promocodes ORDER BY id DESC LIMIT 100')->fetchAll();
        view('staff/promocodes', ['codes' => $codes]);
    }

    public function missions(): void {
        Auth::requireRole(['barista', 'manager', 'admin']);
        $missions = Db::pdo()->query('SELECT * FROM missions ORDER BY id DESC')->fetchAll();
        view('staff/missions', ['missions' => $missions]);
    }
}
