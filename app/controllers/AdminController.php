<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Audit;
use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\CsvExport;
use App\Lib\Db;
use App\Lib\Settings;
use PDO;

class AdminController {
    public function dashboard(): void {
        Auth::requireRole(['manager', 'admin']);
        $pdo = Db::pdo();
        $stats = [
            'users_total' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'orders_30d' => (int)$pdo->query('SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn(),
            'cashback_30d' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM cashback_ledger WHERE type='earn' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
            'rewards_30d' => (int)$pdo->query("SELECT COUNT(*) FROM rewards WHERE status='redeemed' AND redeemed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
        ];
        $recentUsers = $pdo->query('SELECT id,phone,role,created_at FROM users ORDER BY id DESC LIMIT 8')->fetchAll();
        view('admin/dashboard', ['stats' => $stats, 'recentUsers' => $recentUsers]);
    }

    public function settings(): void {
        Auth::requireRole(['admin']);
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            foreach ($_POST as $k => $v) {
                if ($k === '_csrf') continue;
                $old = Settings::get($k, null);
                Settings::set($k, $v);
                Audit::log((int)Auth::user()['id'], 'settings_update', 'setting', null, 'ok', $k . ': ' . (string)$old . ' -> ' . (string)$v);
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
            Audit::log((int)Auth::user()['id'], 'role_update', 'user', (int)$_POST['user_id'], 'ok', 'role=' . $_POST['role']);
            redirect('/admin/users');
        }
        $users = Db::pdo()->query('SELECT id, phone, role, ref_code, created_at FROM users ORDER BY id DESC LIMIT 300')->fetchAll();
        view('admin/users', ['users' => $users]);
    }

    public function locations(): void {
        Auth::requireRole(['admin']);
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            if (($_POST['action'] ?? '') === 'toggle') {
                Db::pdo()->prepare('UPDATE locations SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([(int)$_POST['location_id']]);
                redirect('/admin/locations');
            }
            Db::pdo()->prepare('INSERT INTO locations(name,address,`2gis_url`,yandex_url,is_active) VALUES(?,?,?,?,1)')
                ->execute([$_POST['name'], $_POST['address'], $_POST['url2gis'], $_POST['urly']]);
            redirect('/admin/locations');
        }
        $locations = Db::pdo()->query('SELECT * FROM locations ORDER BY id DESC')->fetchAll();
        view('admin/locations', ['locations' => $locations]);
    }

    public function promocodes(): void {
        Auth::requireRole(['manager', 'admin']);
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            $action = $_POST['action'] ?? 'create';
            if ($action === 'toggle') {
                Db::pdo()->prepare('UPDATE promocodes SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([(int)$_POST['promocode_id']]);
                redirect('/admin/promocodes');
            }
            Db::pdo()->prepare('INSERT INTO promocodes(code,type,value,starts_at,ends_at,max_uses_total,max_uses_per_user,min_order_amount,location_id,is_active,meta_json) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    strtoupper(trim((string)$_POST['code'])), $_POST['type'], $_POST['value'],
                    $_POST['starts_at'] ?: null, $_POST['ends_at'] ?: null,
                    $_POST['max_uses_total'] ?: null, $_POST['max_uses_per_user'] ?: null,
                    $_POST['min_order_amount'] ?: null, $_POST['location_id'] ?: null,
                    isset($_POST['is_active']) ? 1 : 0, $_POST['meta_json'] ?: '{}',
                ]);
            redirect('/admin/promocodes');
        }
        $rows = Db::pdo()->query('SELECT * FROM promocodes ORDER BY id DESC LIMIT 300')->fetchAll();
        view('admin/promocodes', ['rows' => $rows]);
    }

    public function missions(): void {
        Auth::requireRole(['manager', 'admin']);
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            $action = $_POST['action'] ?? 'create';
            if ($action === 'toggle') {
                Db::pdo()->prepare('UPDATE missions SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([(int)$_POST['mission_id']]);
                redirect('/admin/missions');
            }
            Db::pdo()->prepare('INSERT INTO missions(name,type,config_json,reward_json,starts_at,ends_at,is_active) VALUES(?,?,?,?,?,?,?)')
                ->execute([
                    $_POST['name'], $_POST['type'], $_POST['config_json'] ?: '{}', $_POST['reward_json'] ?: '{}',
                    $_POST['starts_at'] ?: null, $_POST['ends_at'] ?: null, isset($_POST['is_active']) ? 1 : 0,
                ]);
            redirect('/admin/missions');
        }
        $rows = Db::pdo()->query('SELECT * FROM missions ORDER BY id DESC LIMIT 300')->fetchAll();
        view('admin/missions', ['rows' => $rows]);
    }

    public function push(): void {
        Auth::requireRole(['manager', 'admin']);
        $allowedAudience = ['all', 'user', 'barista', 'manager', 'admin'];
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            $title = trim((string)($_POST['title'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));
            $url = trim((string)($_POST['url'] ?? '/profile')) ?: '/profile';
            $audience = (string)($_POST['audience'] ?? 'all');
            if (!in_array($audience, $allowedAudience, true)) {
                $audience = 'all';
            }
            if ($title && $body) {
                $pdo = Db::pdo();
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO push_campaigns(title, body, url, target_role, created_by_user_id, created_at) VALUES(?,?,?,?,?,NOW())')
                    ->execute([$title, $body, $url, $audience, (int)Auth::user()['id']]);
                $campaignId = (int)$pdo->lastInsertId();

                $sql = 'INSERT INTO user_notifications(campaign_id,user_id,title,body,url,is_read,created_at) SELECT ?,id,?,?,?,0,NOW() FROM users';
                $params = [$campaignId, $title, $body, $url];
                if ($audience !== 'all') {
                    $sql .= ' WHERE role=?';
                    $params[] = $audience;
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $recipients = (int)$stmt->rowCount();
                $pdo->prepare('UPDATE push_campaigns SET recipients_count=? WHERE id=?')->execute([$recipients, $campaignId]);

                Audit::log((int)Auth::user()['id'], 'push_send', 'push_campaign', $campaignId, 'ok', 'audience=' . $audience . '; recipients=' . $recipients . '; title=' . $title);
                $pdo->commit();
            }
            redirect('/admin/push');
        }
        $campaigns = Db::pdo()->query('SELECT c.*, COALESCE(SUM(CASE WHEN n.is_read=1 THEN 1 ELSE 0 END),0) AS read_count, COALESCE(COUNT(n.id),0) AS sent_count FROM push_campaigns c LEFT JOIN user_notifications n ON n.campaign_id=c.id GROUP BY c.id ORDER BY c.id DESC LIMIT 50')->fetchAll();
        $subs = (int)Db::pdo()->query('SELECT COUNT(*) FROM push_subscriptions')->fetchColumn();
        $audienceStats = Db::pdo()->query('SELECT role, COUNT(*) c FROM users GROUP BY role ORDER BY role')->fetchAll();
        view('admin/push', ['campaigns' => $campaigns, 'subscriptions' => $subs, 'audienceStats' => $audienceStats]);
    }

    public function data(): void {
        Auth::requireRole(['admin']);
        $tables = $this->listTables();
        $table = $_GET['table'] ?? ($tables[0] ?? 'users');
        $this->assertAllowedTable($table, $tables);

        $search = trim((string)($_GET['q'] ?? ''));
        $limit = (int)($_GET['limit'] ?? 200);
        if ($limit < 20) $limit = 20;
        if ($limit > 500) $limit = 500;

        $pdo = Db::pdo();
        $columns = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll();
        $rows = [];

        $textColumns = [];
        foreach ($columns as $column) {
            $type = strtolower((string)($column['Type'] ?? ''));
            if (str_contains($type, 'char') || str_contains($type, 'text') || str_contains($type, 'json')) {
                $textColumns[] = (string)$column['Field'];
            }
        }

        if ($search !== '' && $textColumns) {
            $where = implode(' OR ', array_map(fn($c) => '`' . $c . '` LIKE ?', $textColumns));
            $sql = 'SELECT * FROM `' . $table . '` WHERE ' . $where . ' ORDER BY 1 DESC LIMIT ' . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_fill(0, count($textColumns), '%' . $search . '%'));
            $rows = $stmt->fetchAll();
        } else {
            $rows = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY 1 DESC LIMIT ' . $limit)->fetchAll();
        }

        if (($_GET['export'] ?? '') === 'csv') {
            CsvExport::output($table . '.csv', array_column($columns, 'Field'), $rows);
        }

        $pk = null;
        foreach ($columns as $c) if (($c['Key'] ?? '') === 'PRI') { $pk = $c['Field']; break; }

        view('admin/data', [
            'tables' => $tables,
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
            'pk' => $pk,
            'search' => $search,
            'limit' => $limit,
            'supportsSearch' => !empty($textColumns),
        ]);
    }

    public function dataSave(): void {
        Auth::requireRole(['admin']);
        if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
        $tables = $this->listTables();
        $table = (string)($_POST['table'] ?? '');
        $this->assertAllowedTable($table, $tables);

        $pdo = Db::pdo();
        $columns = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll();
        $colNames = array_column($columns, 'Field');
        $pk = null;
        foreach ($columns as $c) if (($c['Key'] ?? '') === 'PRI') { $pk = $c['Field']; break; }

        $action = $_POST['action'] ?? 'upsert';
        if ($action === 'delete' && $pk && isset($_POST[$pk])) {
            $pdo->prepare('DELETE FROM `' . $table . '` WHERE `' . $pk . '`=?')->execute([$_POST[$pk]]);
            redirect('/admin/data?table=' . urlencode($table));
        }

        $payload = [];
        foreach ($colNames as $c) {
            if (array_key_exists($c, $_POST)) {
                $payload[$c] = $_POST[$c] === '' ? null : $_POST[$c];
            }
        }

        if ($pk && !empty($_POST[$pk])) {
            $sets = [];
            $vals = [];
            foreach ($payload as $k => $v) {
                if ($k === $pk) continue;
                $sets[] = "`$k`=?";
                $vals[] = $v;
            }
            if ($sets) {
                $vals[] = $_POST[$pk];
                $pdo->prepare('UPDATE `' . $table . '` SET ' . implode(',', $sets) . ' WHERE `' . $pk . '`=?')->execute($vals);
            }
        } else {
            $insert = $payload;
            if ($pk && array_key_exists($pk, $insert)) unset($insert[$pk]);
            if ($insert) {
                $cols = array_keys($insert);
                $place = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES(' . $place . ')')->execute(array_values($insert));
            }
        }

        redirect('/admin/data?table=' . urlencode($table));
    }


    public function menu(): void {
        Auth::requireRole(['manager', 'admin']);
        if (method_is('POST')) {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) exit('CSRF');
            $action = (string)($_POST['action'] ?? 'create');
            if ($action === 'toggle') {
                Db::pdo()->prepare('UPDATE menu_items SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([(int)$_POST['item_id']]);
                redirect('/admin/menu');
            }
            if ($action === 'delete') {
                Db::pdo()->prepare('DELETE FROM menu_items WHERE id=?')->execute([(int)$_POST['item_id']]);
                redirect('/admin/menu');
            }
            Db::pdo()->prepare('INSERT INTO menu_items(name,price,description,image_url,is_active,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())')
                ->execute([
                    trim((string)$_POST['name']),
                    (float)$_POST['price'],
                    trim((string)($_POST['description'] ?? '')) ?: null,
                    trim((string)($_POST['image_url'] ?? '')) ?: null,
                    isset($_POST['is_active']) ? 1 : 0,
                    (int)($_POST['sort_order'] ?? 100),
                ]);
            redirect('/admin/menu');
        }
        $items = Db::pdo()->query('SELECT * FROM menu_items ORDER BY sort_order ASC, id DESC')->fetchAll();
        view('admin/menu', ['items' => $items]);
    }

    public function exports(): void {
        Auth::requireRole(['manager', 'admin']);
        if (!empty($_GET['type'])) {
            $type = $_GET['type'];
            if ($type === 'orders') {
                $rows = Db::pdo()->query('SELECT id,user_id,staff_user_id,total_amount,status,idempotency_key,created_at FROM orders ORDER BY id DESC LIMIT 10000')->fetchAll();
                CsvExport::output('orders.csv', array_keys($rows[0] ?? ['id']), $rows);
            }
            if ($type === 'operations') {
                $rows = Db::pdo()->query("SELECT 'cashback' t,id,user_id,order_id,type,amount,created_at FROM cashback_ledger UNION ALL SELECT 'stamp' t,id,user_id,order_id,reason,delta,created_at FROM stamp_ledger ORDER BY created_at DESC LIMIT 10000")->fetchAll();
                CsvExport::output('operations.csv', array_keys($rows[0] ?? ['t']), $rows);
            }
            if ($type === 'users') {
                $rows = Db::pdo()->query('SELECT id,phone,role,ref_code,birthday,created_at FROM users ORDER BY id DESC LIMIT 10000')->fetchAll();
                CsvExport::output('users.csv', array_keys($rows[0] ?? ['id']), $rows);
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

    private function listTables(): array {
        $rows = Db::pdo()->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        $tables = array_map(fn($r) => (string)$r[0], $rows);
        return array_values(array_filter($tables, fn($t) => !in_array($t, ['otp_requests'], true)));
    }

    private function assertAllowedTable(string $table, array $tables): void {
        if (!in_array($table, $tables, true)) {
            http_response_code(400);
            exit('Invalid table');
        }
    }
}
