<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\CsvExport;
use App\Lib\Db;
use App\Lib\Settings;

class AdminController {
    public function settings(): void {
        Auth::requireRole(['admin']);
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            foreach ($_POST as $k => $v) {
                if ($k === '_csrf') continue;
                Settings::set($k, $v);
            }
            redirect('/admin/settings');
        }
        $rows = Db::pdo()->query('SELECT * FROM settings ORDER BY `key`')->fetchAll();
        view('admin/settings', ['rows' => $rows]);
    }

    public function users(): void {
        Auth::requireRole(['admin']);
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            Db::pdo()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$_POST['role'], $_POST['user_id']]);
            redirect('/admin/users');
        }
        $users = Db::pdo()->query('SELECT id, phone, role, ref_code, created_at FROM users ORDER BY id DESC LIMIT 200')->fetchAll();
        view('admin/users', ['users' => $users]);
    }

    public function locations(): void {
        Auth::requireRole(['admin']);
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            Db::pdo()->prepare('INSERT INTO locations(name,address,`2gis_url`,yandex_url,is_active) VALUES(?,?,?,?,1)')
                ->execute([$_POST['name'], $_POST['address'], $_POST['url2gis'], $_POST['urly']]);
            redirect('/admin/locations');
        }
        $locations = Db::pdo()->query('SELECT * FROM locations ORDER BY id DESC')->fetchAll();
        view('admin/locations', ['locations' => $locations]);
    }

    public function exports(): void {
        Auth::requireRole(['manager', 'admin']);
        if (!empty($_GET['type'])) {
            $type = $_GET['type'];
            if ($type === 'orders') {
                $rows = Db::pdo()->query('SELECT id,user_id,staff_user_id,total_amount,status,created_at FROM orders ORDER BY id DESC LIMIT 10000')->fetchAll();
                CsvExport::output('orders.csv', array_keys($rows[0] ?? []), $rows);
            }
            if ($type === 'operations') {
                $rows = Db::pdo()->query("SELECT 'cashback' t,id,user_id,order_id,type,amount,created_at FROM cashback_ledger UNION ALL SELECT 'stamp' t,id,user_id,order_id,reason,delta,created_at FROM stamp_ledger ORDER BY created_at DESC LIMIT 10000")->fetchAll();
                CsvExport::output('operations.csv', array_keys($rows[0] ?? []), $rows);
            }
            if ($type === 'users') {
                $rows = Db::pdo()->query('SELECT id,phone,role,ref_code,birthday,created_at FROM users ORDER BY id DESC LIMIT 10000')->fetchAll();
                CsvExport::output('users.csv', array_keys($rows[0] ?? []), $rows);
            }
        }

        $report = [
            'cashback_earned' => Db::pdo()->query("SELECT COALESCE(SUM(amount),0) FROM cashback_ledger WHERE type='earn' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
            'rewards_used' => Db::pdo()->query("SELECT COUNT(*) FROM rewards WHERE status='redeemed' AND redeemed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
            'active_users' => Db::pdo()->query("SELECT COUNT(DISTINCT user_id) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
        ];

        view('admin/exports', ['report' => $report]);
    }

    public function audit(): void {
        Auth::requireRole(['admin']);
        $rows = Db::pdo()->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 500')->fetchAll();
        view('admin/audit', ['rows' => $rows]);
    }
}
