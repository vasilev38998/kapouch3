<?php

declare(strict_types=1);

namespace App\Lib;

use RuntimeException;

class Ledger {
    public function createOrder(array $payload): int {
        $pdo = Db::pdo();
        $engine = new RulesEngine();
        $calc = $engine->calculateOrder($payload);

        FraudGuard::checkOrderLimits((int)$payload['user_id'], $calc['stamps'], $calc['cashback_earn'], (float)($payload['cashback_spend'] ?? 0));

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO orders(user_id, staff_user_id, location_id, total_amount, status, meta_json, created_at) VALUES(?,?,?,?,\'created\',?,NOW())');
            $stmt->execute([
                $payload['user_id'],
                $payload['staff_user_id'],
                $payload['location_id'] ?: null,
                $payload['total_amount'],
                json_encode($payload['meta'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $this->addCashback((int)$payload['user_id'], $orderId, 'earn', $calc['cashback_earn'], (int)$payload['staff_user_id'], ['source' => 'order']);
            if (!empty($payload['cashback_spend']) && (float)$payload['cashback_spend'] > 0) {
                $maxSpend = $engine->cashbackMaxSpend((float)$payload['total_amount']);
                $spend = min((float)$payload['cashback_spend'], $maxSpend, $this->cashbackBalance((int)$payload['user_id']));
                if ($spend > 0) {
                    $this->addCashback((int)$payload['user_id'], $orderId, 'spend', $spend, (int)$payload['staff_user_id'], ['source' => 'order']);
                }
            }

            $this->addStamps((int)$payload['user_id'], $orderId, $calc['stamps'], 'order', (int)$payload['staff_user_id']);
            $this->applyPromocodeIfAny((int)$payload['user_id'], $orderId, $payload);
            $this->processReferral((int)$payload['user_id'], $orderId, (int)$payload['staff_user_id']);
            $this->processMissions((int)$payload['user_id'], $orderId, $payload);
            $this->processBirthdayBonus((int)$payload['user_id'], $orderId, (int)$payload['staff_user_id']);
            $this->syncLoyaltyState((int)$payload['user_id']);
            $pdo->commit();
            return $orderId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function reverseOrder(int $orderId, int $staffId): void {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $order = $pdo->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
            $order->execute([$orderId]);
            $o = $order->fetch();
            if (!$o || $o['status'] !== 'created') throw new RuntimeException('Order not reversible');

            $pdo->prepare("UPDATE orders SET status='reversed' WHERE id=?")->execute([$orderId]);
            $pdo->prepare("INSERT INTO cashback_ledger(user_id, order_id, type, amount, created_by_staff_id, meta_json, created_at)
                SELECT user_id, order_id, 'reversal', amount, ?, JSON_OBJECT('reverse_of', id), NOW() FROM cashback_ledger WHERE order_id=? AND type IN ('earn','spend')")
                ->execute([$staffId, $orderId]);
            $pdo->prepare("INSERT INTO stamp_ledger(user_id, order_id, delta, reason, created_by_staff_id, created_at)
                SELECT user_id, order_id, -delta, 'reversal', ?, NOW() FROM stamp_ledger WHERE order_id=?")
                ->execute([$staffId, $orderId]);
            $this->syncLoyaltyState((int)$o['user_id']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function cashbackBalance(int $userId): float {
        $stmt = Db::pdo()->prepare("SELECT COALESCE(SUM(CASE WHEN type IN ('earn','adjust') THEN amount WHEN type IN ('spend','reversal') THEN -amount ELSE 0 END),0) FROM cashback_ledger WHERE user_id=?");
        $stmt->execute([$userId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    public function addCashback(int $userId, ?int $orderId, string $type, float $amount, ?int $staffId, array $meta = []): void {
        if ($amount <= 0) return;
        $stmt = Db::pdo()->prepare('INSERT INTO cashback_ledger(user_id, order_id, type, amount, created_by_staff_id, meta_json, created_at) VALUES(?,?,?,?,?,?,NOW())');
        $stmt->execute([$userId, $orderId, $type, $amount, $staffId, json_encode($meta, JSON_UNESCAPED_UNICODE)]);
    }

    public function addStamps(int $userId, ?int $orderId, int $delta, string $reason, ?int $staffId): void {
        if ($delta === 0) return;
        $stmt = Db::pdo()->prepare('INSERT INTO stamp_ledger(user_id, order_id, delta, reason, created_by_staff_id, created_at) VALUES(?,?,?,?,?,NOW())');
        $stmt->execute([$userId, $orderId, $delta, $reason, $staffId]);
    }

    public function syncLoyaltyState(int $userId): void {
        $required = (int)Settings::get('stamps_required_for_reward', 6);
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(delta),0) FROM stamp_ledger WHERE user_id=?');
        $stmt->execute([$userId]);
        $stamps = max(0, (int)$stmt->fetchColumn());
        $reward = $stamps >= $required ? 1 : 0;
        $pdo->prepare('INSERT INTO loyalty_state(user_id, stamps, reward_available, updated_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE stamps=VALUES(stamps),reward_available=VALUES(reward_available),updated_at=NOW()')
            ->execute([$userId, $stamps, $reward]);
    }

    public function redeemFreeCoffee(int $userId, int $staffId): void {
        $required = (int)Settings::get('stamps_required_for_reward', 6);
        $this->addStamps($userId, null, -$required, 'free_coffee_redeem', $staffId);
        Db::pdo()->prepare("INSERT INTO rewards(user_id, type, value, status, meta_json, created_at, redeemed_at) VALUES(?, 'free_coffee', 0, 'redeemed', '{}', NOW(), NOW())")
            ->execute([$userId]);
        $this->syncLoyaltyState($userId);
    }

    private function applyPromocodeIfAny(int $userId, int $orderId, array $payload): void {
        if (empty($payload['promocode'])) return;
        $code = strtoupper(trim((string)$payload['promocode']));
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM promocodes WHERE code=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW())');
        $stmt->execute([$code]);
        $p = $stmt->fetch();
        if (!$p) return;

        if ($p['min_order_amount'] !== null && (float)$payload['total_amount'] < (float)$p['min_order_amount']) return;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM promocode_redemptions WHERE promocode_id=?');
        $stmt->execute([$p['id']]);
        if ($p['max_uses_total'] !== null && (int)$stmt->fetchColumn() >= (int)$p['max_uses_total']) return;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM promocode_redemptions WHERE promocode_id=? AND user_id=?');
        $stmt->execute([$p['id'], $userId]);
        if ($p['max_uses_per_user'] !== null && (int)$stmt->fetchColumn() >= (int)$p['max_uses_per_user']) return;

        switch ($p['type']) {
            case 'stamps':
                $this->addStamps($userId, $orderId, (int)$p['value'], 'promocode', null);
                break;
            case 'cashback_fixed':
                $this->addCashback($userId, $orderId, 'earn', (float)$p['value'], null, ['source' => 'promocode']);
                break;
            case 'cashback_boost_percent':
                $boost = round((float)$payload['total_amount'] * ((float)$p['value'] / 100), 2);
                $this->addCashback($userId, $orderId, 'earn', $boost, null, ['source' => 'promocode']);
                break;
            case 'reward':
                $pdo->prepare("INSERT INTO rewards(user_id,type,value,status,expires_at,meta_json,created_at) VALUES(?, 'coupon', ?, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY), '{}', NOW())")
                    ->execute([$userId, $p['value']]);
                break;
        }

        $pdo->prepare('INSERT INTO promocode_redemptions(promocode_id,user_id,order_id,redeemed_at) VALUES(?,?,?,NOW())')
            ->execute([$p['id'], $userId, $orderId]);
    }

    private function processReferral(int $userId, int $orderId, int $staffId): void {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT referred_by_user_id FROM users WHERE id=?');
        $stmt->execute([$userId]);
        $referrer = $stmt->fetchColumn();
        if (!$referrer) return;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id=? AND status=\'created\'');
        $stmt->execute([$userId]);
        if ((int)$stmt->fetchColumn() !== 1) return;

        $bonus = Settings::get('referral_bonus_value', 50);
        $type = Settings::get('referral_bonus_type', 'cashback');
        if ($type === 'stamp') {
            $this->addStamps((int)$referrer, $orderId, (int)$bonus, 'referral_bonus', $staffId);
            $this->addStamps($userId, $orderId, (int)$bonus, 'referral_bonus', $staffId);
        } else {
            $this->addCashback((int)$referrer, $orderId, 'earn', (float)$bonus, $staffId, ['source' => 'referral']);
            $this->addCashback($userId, $orderId, 'earn', (float)$bonus, $staffId, ['source' => 'referral']);
        }
    }

    private function processMissions(int $userId, int $orderId, array $payload): void {
        $pdo = Db::pdo();
        $missions = $pdo->query("SELECT * FROM missions WHERE is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW())")->fetchAll();
        foreach ($missions as $m) {
            $config = json_decode($m['config_json'], true) ?: [];
            $reward = json_decode($m['reward_json'], true) ?: [];
            $stmt = $pdo->prepare('SELECT * FROM mission_progress WHERE mission_id=? AND user_id=?');
            $stmt->execute([$m['id'], $userId]);
            $progress = $stmt->fetch();
            $state = $progress ? (json_decode($progress['progress_json'], true) ?: ['orders' => 0]) : ['orders' => 0];
            $state['orders'] = ($state['orders'] ?? 0) + 1;
            $target = (int)($config['orders_target'] ?? 3);
            $completed = $state['orders'] >= $target ? 1 : 0;

            if ($progress) {
                $pdo->prepare('UPDATE mission_progress SET progress_json=?, completed=?, completed_at=IF(?=1,NOW(),completed_at), last_updated_at=NOW() WHERE id=?')
                    ->execute([json_encode($state), $completed, $completed, $progress['id']]);
            } else {
                $pdo->prepare('INSERT INTO mission_progress(mission_id,user_id,progress_json,completed,completed_at,last_updated_at) VALUES(?,?,?,?,IF(?=1,NOW(),NULL),NOW())')
                    ->execute([$m['id'], $userId, json_encode($state), $completed, $completed]);
            }

            $alreadyCompleted = $progress ? (int)$progress['completed'] === 1 : false;
            if ($completed && !$alreadyCompleted) {
                if (($reward['type'] ?? '') === 'stamp') {
                    $this->addStamps($userId, $orderId, (int)($reward['value'] ?? 1), 'mission_reward', null);
                }
                if (($reward['type'] ?? '') === 'cashback') {
                    $this->addCashback($userId, $orderId, 'earn', (float)($reward['value'] ?? 50), null, ['source' => 'mission']);
                }
            }
        }
    }

    private function processBirthdayBonus(int $userId, int $orderId, int $staffId): void {
        $stmt = Db::pdo()->prepare('SELECT birthday FROM users WHERE id=?');
        $stmt->execute([$userId]);
        $birthday = $stmt->fetchColumn();
        if (!$birthday) return;

        $window = (int)Settings::get('birthday_bonus_window_days', 7);
        $bonusType = (string)Settings::get('birthday_bonus_type', 'cashback');
        $bonusValue = (float)Settings::get('birthday_bonus_value', 100);
        $yearDate = date('Y') . '-' . date('m-d', strtotime((string)$birthday));
        $diff = abs((int)((strtotime(date('Y-m-d')) - strtotime($yearDate)) / 86400));
        if ($diff > $window) return;

        $metaKey = 'birthday_' . date('Y');
        $stmt = Db::pdo()->prepare('SELECT COUNT(*) FROM cashback_ledger WHERE user_id=? AND JSON_EXTRACT(meta_json, "$.bonus") = ?');
        $stmt->execute([$userId, $metaKey]);
        if ((int)$stmt->fetchColumn() > 0) return;

        if ($bonusType === 'stamp') {
            $this->addStamps($userId, $orderId, (int)$bonusValue, 'birthday_bonus', $staffId);
        } elseif ($bonusType === 'coupon') {
            Db::pdo()->prepare("INSERT INTO rewards(user_id,type,value,status,expires_at,meta_json,created_at) VALUES(?, 'coupon', ?, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY), JSON_OBJECT('bonus', ?), NOW())")
                ->execute([$userId, $bonusValue, $metaKey]);
        } else {
            $this->addCashback($userId, $orderId, 'earn', $bonusValue, $staffId, ['bonus' => $metaKey]);
        }
    }
}
