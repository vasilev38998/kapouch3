<?php

declare(strict_types=1);

namespace App\Lib;

class FraudGuard {
    public static function checkOrderLimits(int $userId, int $stamps, float $cashbackEarn, float $cashbackSpend = 0.0): void {
        $pdo = Db::pdo();
        $limits = Settings::get('fraud_limits', [
            'max_stamps_per_day' => 20,
            'max_cashback_per_day' => 3000,
            'max_operations_per_hour' => 20,
            'max_spendings_per_day' => 10,
        ]);

        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM orders WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        $stmt->execute([$userId]);
        if ((int)$stmt->fetchColumn() >= (int)$limits['max_operations_per_hour']) {
            self::block($userId, 'max_operations_per_hour');
        }

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(CASE WHEN delta>0 THEN delta ELSE 0 END),0) FROM stamp_ledger WHERE user_id = ? AND DATE(created_at)=CURDATE()');
        $stmt->execute([$userId]);
        if (((int)$stmt->fetchColumn() + $stamps) > (int)$limits['max_stamps_per_day']) {
            self::block($userId, 'max_stamps_per_day');
        }

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type='earn' THEN amount ELSE 0 END),0) FROM cashback_ledger WHERE user_id=? AND DATE(created_at)=CURDATE()");
        $stmt->execute([$userId]);
        if (((float)$stmt->fetchColumn() + $cashbackEarn) > (float)$limits['max_cashback_per_day']) {
            self::block($userId, 'max_cashback_per_day');
        }

        if ($cashbackSpend > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cashback_ledger WHERE user_id=? AND type='spend' AND DATE(created_at)=CURDATE()");
            $stmt->execute([$userId]);
            if (((int)$stmt->fetchColumn() + 1) > (int)$limits['max_spendings_per_day']) {
                self::block($userId, 'max_spendings_per_day');
            }
        }
    }

    private static function block(int $userId, string $kind): never {
        $stmt = Db::pdo()->prepare('INSERT INTO fraud_events(user_id, kind, details, created_at) VALUES(?,?,?,NOW())');
        $stmt->execute([$userId, $kind, 'Limit exceeded']);
        app_log("Fraud blocked user={$userId}, kind={$kind}");
        throw new \RuntimeException('Fraud limit exceeded: ' . $kind);
    }
}
